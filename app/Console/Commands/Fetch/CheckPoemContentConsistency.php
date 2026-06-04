<?php

namespace App\Console\Commands\Fetch;

use App\Models\Poem;
use Illuminate\Console\Command;

class CheckPoemContentConsistency extends Command
{
    protected $signature = 'fetch:check-poem-content-consistency
        {--from= : 从指定 poem id 开始}
        {--to= : 到指定 poem id 结束}
        {--output= : 输出不一致ID的文件路径（默认：storage/logs/inconsistent_poem_ids.txt）}';

    protected $description = '检查 content_py 去除拼音后与 content 的一致性，输出不一致的记录';

    private int $totalProcessed = 0;
    private int $inconsistentCount = 0;
    private array $inconsistentIds = [];

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('诗词内容一致性检查');
        $this->info('========================================');
        $this->newLine();

        // 构建查询
        $query = Poem::orderBy('id');

        if ($from = $this->option('from')) {
            $query->where('id', '>=', (int) $from);
        }

        if ($to = $this->option('to')) {
            $query->where('id', '<=', (int) $to);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('没有需要检查的诗词');
            return self::SUCCESS;
        }

        $this->info("📊 共找到 {$total} 条诗词记录");
        $this->newLine();

        // 分批处理
        $query->chunkById(100, function ($poems) use ($total) {
            foreach ($poems as $poem) {
                $this->totalProcessed++;
                $this->checkPoem($poem, $this->totalProcessed, $total);
            }
        }, 'id');

        $this->newLine();
        $this->info('========================================');
        $this->info('✅ 检查完成！');
        $this->line("统计: 检查 <fg=cyan>{$this->totalProcessed}</> 条，不一致 <fg=red>{$this->inconsistentCount}</> 条");

        // 保存不一致的 ID 到文件
        if ($this->inconsistentCount > 0) {
            $outputPath = $this->option('output') ?: storage_path('logs/inconsistent_poem_ids.txt');

            // 确保目录存在
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($outputPath, implode("\n", $this->inconsistentIds) . "\n");

            $this->newLine();
            $this->info("📄 不一致的 ID 已保存到: <fg=yellow>{$outputPath}</>");
        }

        $this->info('========================================');

        return self::SUCCESS;
    }

    /**
     * 检查单个诗词
     */
    private function checkPoem(Poem $poem, int $index, int $total): void
    {
        // 输出进度
        $this->line("[{$index}/{$total}] 检查 ID: <fg=cyan>{$poem->id}</> ({$poem->name})");

        $dbContent = $poem->content ?? '';
        $dbContentPy = $poem->content_py ?? '';

        // 跳过 content 或 content_py 为空的
        if (empty($dbContent) || empty($dbContentPy)) {
            return;
        }

        // 从 content_py 中提取纯汉字
        $contentFromPy = $this->extractChineseFromPinyin($dbContentPy);

        // 比对
        if (trim($contentFromPy) === trim($dbContent)) {
            // 一致，不输出详细信息
            return;
        }

        // 不一致，输出详细信息
        $this->inconsistentCount++;

        // 记录 ID
        $this->inconsistentIds[] = $poem->id;

        $this->newLine();
        $this->error("========== 不一致记录 #{$this->inconsistentCount} ==========");
        $this->line("ID: <fg=yellow>{$poem->id}</>");
        $this->line("ID_STR: <fg=yellow>{$poem->id_str}</>");
        $this->line("POEM_ID: <fg=yellow>{$poem->poem_id}</>");
        $this->line("标题: <fg=cyan>{$poem->name}</>");
        $this->newLine();

        $this->line('<fg=magenta>【内容比对】</>');
        $this->line(str_repeat('-', 76));
        $this->displayDiff($dbContent, $contentFromPy);
        $this->line(str_repeat('-', 76));
        $this->newLine();
    }

    /**
     * 从拼音内容中提取纯汉字（去除拼音标注）
     */
    private function extractChineseFromPinyin(string $contentPy): string
    {
        // 分割 HTML 标签和内容，只处理内容部分
        $parts = preg_split('/(<[^>]+>)/u', $contentPy, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($parts as $part) {
            // 如果是 HTML 标签，保持不变
            if (preg_match('/^<[^>]+>$/u', $part)) {
                $result .= $part;
            } else {
                // 否则去除拼音字母
                $result .= preg_replace('/[a-zāáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜüA-Z]+/u', '', $part);
            }
        }

        return $result;
    }

    /**
     * 显示差异（高亮不同的部分）
     */
    private function displayDiff(string $db, string $fromPy): void
    {
        $this->line('<fg=red>【数据库 content】</>');
        $this->displayWithHighlight($db, $fromPy, 'red');

        $this->newLine();

        $this->line('<fg=green>【从 content_py 提取的汉字】</>');
        $this->displayWithHighlight($fromPy, $db, 'green');
    }

    /**
     * 显示文本并高亮差异部分
     */
    private function displayWithHighlight(string $text1, string $text2, string $color): void
    {
        $len1 = mb_strlen($text1);
        $len2 = mb_strlen($text2);
        $maxLen = max($len1, $len2);

        $output = '    ';
        $lineLength = 0;
        $maxLineLength = 70;

        for ($i = 0; $i < $maxLen; $i++) {
            $char1 = $i < $len1 ? mb_substr($text1, $i, 1) : '';
            $char2 = $i < $len2 ? mb_substr($text2, $i, 1) : '';

            // 换行处理
            if ($char1 === "\n" || $char1 === "\r" || $lineLength >= $maxLineLength) {
                $this->line($output);
                $output = '    ';
                $lineLength = 0;
                if ($char1 === "\n" || $char1 === "\r") {
                    continue;
                }
            }

            if ($char1 !== $char2) {
                // 差异字符，高亮显示
                if ($char1 !== '') {
                    $output .= "<fg={$color};options=bold,underscore>{$char1}</>";
                } else {
                    // 缺失的字符
                    $output .= "<fg={$color}>[缺失]</>";
                }
            } else {
                // 相同字符
                $output .= $char1;
            }

            $lineLength++;
        }

        if ($output !== '    ') {
            $this->line($output);
        }
    }
}
