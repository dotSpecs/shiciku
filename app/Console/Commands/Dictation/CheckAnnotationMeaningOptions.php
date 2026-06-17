<?php

namespace App\Console\Commands\Dictation;

use App\Models\Dictation\Question;
use App\Services\Dictation\AlibabaAIService;
use App\Services\Dictation\QuestionGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CheckAnnotationMeaningOptions extends Command
{
    // 已经更新到 id = 4120 了
    protected $signature = 'dictation:annotation-options:check
        {--id=* : 指定 dictation_questions.id，可重复传入}
        {--grade= : 只检查指定年级册}
        {--status=active : active|inactive|all}
        {--limit=50 : 最多检查多少题}
        {--from-id= : 从指定 dictation_questions.id 开始检查，包含该 ID} 
        {--report-only : 只列出问题，不询问修改}
        {--apply : 兼容旧参数；默认已经会逐条确认后写入}
        {--yes : 跳过逐条确认，自动写入合法 replacement_options}';

    protected $description = '调用阿里模型检查 annotation_meaning 题目的 options 是否有近义、可判对或重复等问题';

    private int $checked = 0;

    private int $problematic = 0;

    private int $updated = 0;

    public function handle(AlibabaAIService $ai): int
    {
        if (! $ai->isConfigured()) {
            $this->error('未配置阿里模型 API Key，请设置 ALIBABA_AI_API_KEY 或 DASHSCOPE_API_KEY');

            return self::FAILURE;
        }

        $questions = $this->query()
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($questions->isEmpty()) {
            $this->warn('没有找到需要检查的 annotation_meaning 题目');

            return self::SUCCESS;
        }

        $this->info(sprintf('开始检查 %d 道 annotation_meaning 题目', $questions->count()));
        $this->newLine();

        foreach ($questions as $question) {
            $this->checked++;
            $this->line(sprintf('[%d/%d] #%d %s', $this->checked, $questions->count(), $question->id, $question->grade_name));

            try {
                $review = $ai->reviewAnnotationMeaningOptions($question);
            } catch (\Throwable $e) {
                $this->error('  模型校验失败：'.$e->getMessage());
                $this->newLine();

                continue;
            }

            if ($review['ok'] && $review['issues'] === []) {
                $this->line('  OK');

                continue;
            }

            $this->problematic++;
            $this->displayProblem($question, $review);

            if ($this->option('report-only')) {
                continue;
            }

            $replacement = $this->validReplacementOptions($question, $review['replacement_options']);
            if ($replacement === null) {
                $this->warn('  跳过写入：模型没有返回合法 replacement_options');

                continue;
            }

            if (! $this->option('yes') && ! $this->input->isInteractive()) {
                $this->warn('  跳过写入：当前是非交互模式；需要自动写入请加 --yes');

                continue;
            }

            if (! $this->option('yes') && ! $this->confirm('  确认将该题 options 替换为模型建议？', true)) {
                $this->line('  已跳过');

                continue;
            }

            $this->updateOptions($question, $replacement);
            $this->updated++;
            $this->info('  已更新');
        }

        $this->newLine();
        $this->info(sprintf(
            '完成：检查 %d 题，发现问题 %d 题，更新 %d 题',
            $this->checked,
            $this->problematic,
            $this->updated
        ));

        if ($this->option('report-only') && $this->problematic > 0) {
            $this->line('当前为 report-only，只列出问题，不写入修改。');
        }

        return self::SUCCESS;
    }

    private function query(): Builder
    {
        $query = Question::query()
            ->where('question_type', QuestionGenerator::TYPE_ANNOTATION_MEANING)
            ->whereNotNull('options')
            ->orderBy('id');

        $ids = array_values(array_filter(array_map('intval', $this->option('id') ?: [])));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $fromId = (int) $this->option('from-id', 4120);
        if ($fromId > 0) {
            $query->where('id', '>=', $fromId);
        }

        $grade = trim((string) $this->option('grade'));
        if ($grade !== '') {
            $query->where('grade_name', $grade);
        }

        $status = (string) $this->option('status');
        if ($status === 'active') {
            $query->where('status', Question::STATUS_ACTIVE);
        } elseif ($status === 'inactive') {
            $query->where('status', Question::STATUS_INACTIVE);
        } elseif ($status !== 'all') {
            $this->warn('未知 status，已按 active 处理');
            $query->where('status', Question::STATUS_ACTIVE);
        }

        return $query;
    }

    /**
     * @param  array{ok: bool, issues: array<int, string>, replacement_options: array<int, string>, raw: array<string|int, mixed>}  $review
     */
    private function displayProblem(Question $question, array $review): void
    {
        $this->warn('  发现问题');
        $this->line('  题干：'.$question->prompt);
        $this->line('  答案：'.$question->answer);
        $this->line('  当前选项：'.implode(' / ', $question->options ?? []));

        foreach ($review['issues'] as $issue) {
            $this->line('  - '.$issue);
        }

        if ($review['replacement_options'] !== []) {
            $this->line('  建议选项：'.implode(' / ', $review['replacement_options']));
        }

        $this->newLine();
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>|null
     */
    private function validReplacementOptions(Question $question, array $options): ?array
    {
        $options = array_values(array_unique(array_filter(array_map(
            fn (string $option) => trim($option),
            $options
        ))));

        if (count($options) !== 4) {
            return null;
        }

        if (! in_array((string) $question->answer, $options, true)) {
            return null;
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $options
     */
    private function updateOptions(Question $question, array $options): void
    {
        shuffle($options);

        $question->options = $options;
        $question->source_hash = $this->sourceHash($question);
        $question->save();
    }

    private function sourceHash(Question $question): string
    {
        return sha1(json_encode([
            'question_type' => $question->question_type,
            'prompt' => $question->prompt,
            'answer' => $question->answer,
            'accepted_answers' => $question->accepted_answers ?? [],
            'options' => $question->options ?? [],
            'metadata' => $question->metadata ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
