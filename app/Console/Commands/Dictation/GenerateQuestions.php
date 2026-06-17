<?php

namespace App\Console\Commands\Dictation;

use App\Models\Dictation\Question;
use App\Models\ZhuantiChapter;
use App\Services\Dictation\GradeScopeResolver;
use App\Services\Dictation\QuestionGenerator;
use App\Services\Dictation\QuestionTemplateGenerator;
use Illuminate\Console\Command;

class GenerateQuestions extends Command
{
    protected $signature = 'dictation:questions:generate
        {--grade= : 指定年级册名称}
        {--all : 生成全部年级册}
        {--type=* : 指定题型，可重复传入}
        {--refresh-ai : 生成需要 AI 的题型}
        {--dry-run : 只统计不写入数据库}';

    protected $description = '生成诗词闯关题库模板';

    private const AI_TYPES = [
        QuestionGenerator::TYPE_ANNOTATION_MEANING,
    ];

    public function handle(GradeScopeResolver $resolver, QuestionTemplateGenerator $generator): int
    {
        $gradeNames = $this->gradeNames();
        if ($gradeNames === []) {
            $this->error('请传入 --grade=年级册 或 --all');

            return self::FAILURE;
        }

        $types = $this->types();
        if ($types === []) {
            $this->error('没有可生成的题型');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($gradeNames as $gradeName) {
            $scope = $resolver->resolve($gradeName);
            if (! $scope) {
                $this->warn("跳过：{$gradeName} 未找到年级册范围");

                continue;
            }

            $templates = $generator->generate($scope['grade_name'], $scope['candidates'], $types);
            $total += count($templates);

            $this->line(sprintf(
                '%s: %d questions (%s)',
                $scope['grade_name'],
                count($templates),
                $this->typeSummary($templates)
            ));

            if ($dryRun) {
                continue;
            }

            foreach ($templates as $template) {
                Question::query()->updateOrCreate(
                    ['source_key' => $template['source_key']],
                    $template
                );
            }

            $sourceKeys = array_column($templates, 'source_key');
            if ($sourceKeys !== []) {
                Question::query()
                    ->where('grade_name', $scope['grade_name'])
                    ->whereIn('question_type', $types)
                    ->whereNotIn('source_key', $sourceKeys)
                    ->update(['status' => Question::STATUS_INACTIVE]);
            }
        }

        $this->info($dryRun ? "dry-run complete: {$total} questions" : "generated: {$total} questions");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function gradeNames(): array
    {
        $grade = trim((string) $this->option('grade'));
        if ($grade !== '') {
            return [$grade];
        }

        if (! $this->option('all')) {
            return [];
        }

        return ZhuantiChapter::query()
            ->whereIn('zhuanti_id', GradeScopeResolver::ZHUANTI_IDS)
            ->orderBy('zhuanti_id')
            ->orderBy('sort')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function types(): array
    {
        $types = $this->option('type') ?: QuestionTemplateGenerator::TYPES;
        $types = array_values(array_intersect($types, QuestionTemplateGenerator::TYPES));

        if ($this->option('refresh-ai')) {
            return $types;
        }

        $explicitTypes = $this->option('type') !== [];
        if ($explicitTypes) {
            return $types;
        }

        return array_values(array_diff($types, self::AI_TYPES));
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function typeSummary(array $templates): string
    {
        $counts = [];
        foreach ($templates as $template) {
            $counts[$template['question_type']] = ($counts[$template['question_type']] ?? 0) + 1;
        }

        ksort($counts);

        return implode(', ', array_map(
            fn (string $type, int $count) => "{$type}:{$count}",
            array_keys($counts),
            array_values($counts)
        ));
    }
}
