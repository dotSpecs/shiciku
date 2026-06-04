<?php

namespace App\Console\Commands\Fetch;

use App\Models\Poem;
use App\Services\Guwendao\HttpClient;
use Illuminate\Console\Command;
use Throwable;

class SyncPoemContentPy extends Command
{
    protected $signature = 'fetch:sync-poem-content-py
        {--from= : 从指定 poem id 开始}
        {--to= : 到指定 poem id 结束}
        {--id= : 仅处理指定 poem id}
        {--auto : 自动全部更新，不询问}';

    protected $description = '同步诗词拼音内容：从古文岛接口获取 contentTxtPy，与数据库比对并高亮差异';

    private HttpClient $http;
    private int $updatedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    private int $totalProcessed = 0;

    public function handle(HttpClient $http): int
    {
        $this->http = $http;

        $this->info('========================================');
        $this->info('诗词拼音内容同步脚本');
        $this->info('========================================');
        $this->newLine();

        $auto = $this->option('auto');

        // 如果指定了单个 id
        if ($id = $this->option('id')) {
            $poem = Poem::find($id);
            if (!$poem) {
                $this->error("未找到 ID 为 {$id} 的诗词");
                return self::FAILURE;
            }
            $this->processPoem($poem, 1, 1, $auto);
            $this->displayFinalStats();
            return self::SUCCESS;
        }

        // 获取总数
        $query = Poem::whereNotNull('id_str');

        if ($from = $this->option('from')) {
            $query->where('id', '>=', (int) $from);
        }

        if ($to = $this->option('to')) {
            $query->where('id', '<=', (int) $to);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('没有需要处理的诗词');
            return self::SUCCESS;
        }

        $this->info("📊 共找到 {$total} 条诗词记录");
        $this->newLine();

        // 分批处理
        $query->orderBy('id')->chunkById(100, function ($poems) use ($total, $auto) {
            foreach ($poems as $poem) {
                $this->totalProcessed++;

                $result = $this->processPoem($poem, $this->totalProcessed, $total, $auto);

                // 如果用户选择退出
                if ($result === 'quit') {
                    return false; // 停止 chunk 循环
                }
            }
        }, 'id');

        $this->displayFinalStats();

        return self::SUCCESS;
    }

    /**
     * 处理单个诗词
     *
     * @return string|null 'quit' 表示用户选择退出，null 表示正常继续
     */
    private function processPoem(Poem $poem, int $index, int $total, bool $auto): ?string
    {
        $this->line("[{$index}/{$total}] 处理: <fg=cyan>{$poem->name}</> (ID: {$poem->id})");
        $this->line("    id_str: <fg=yellow>{$poem->id_str}</>");

        try {
            // 调用 YZSY 接口获取内容
            $yzsyData = $this->fetchPoemContent($poem->id_str);

            if ($yzsyData === null) {
                $this->warn('    ⚠️  YZSY 接口返回为空或请求失败，跳过');
                $this->newLine();
                $this->errorCount++;
                return null;
            }

            $yzsyContent = $yzsyData['content'] ?? '';
            $yzsyContentPy = $yzsyData['contentPy'] ?? '';

            // 从 contentPy 中提取纯汉字
            $yzsyContentFromPy = $this->extractChineseFromPinyin($yzsyContentPy);

            // 比对内容
            $dbContent = $poem->content ?? '';
            $dbContentPy = $poem->content_py ?? '';

            $contentDiff = trim($yzsyContent) !== trim($dbContent);
            $contentPyDiff = trim($yzsyContentPy) !== trim($dbContentPy);
            $contentFromPyDiff = trim($yzsyContentFromPy) !== trim($dbContent);

            // 先检查 contentPy 提取的汉字与数据库 content 是否一致
            if ($contentFromPyDiff) {
                $this->error('    ❌ 从 contentPy 提取的汉字与数据库 content 不一致！');
                $this->comment('    🔍 调用 shiwenInfo 接口进行三方比对...');
                $this->newLine();

                $infoData = $this->fetchPoemInfo($poem->id_str);

                if ($infoData) {
                    $infoContent = $infoData['contentTxt'] ?? '';

                    $this->line('    <fg=magenta>【三方 content 比对（从 contentPy 提取）】</>');
                    $this->line('    ' . str_repeat('=', 76));

                    // 三方比对
                    $dbTrim = trim($dbContent);
                    $fromPyTrim = trim($yzsyContentFromPy);
                    $infoTrim = trim($infoContent);

                    if ($dbTrim === $fromPyTrim && $fromPyTrim === $infoTrim) {
                        $this->info('    ✅ 三方完全一致（空格问题）');
                    } elseif ($fromPyTrim === $infoTrim) {
                        $this->warn('    ⚠️  contentPy提取 和 Info 接口一致，但与数据库不同');
                        $this->newLine();
                        $this->displayDiff($dbContent, $yzsyContentFromPy, 'content [数据库] vs [contentPy提取&Info接口]');
                    } elseif ($dbTrim === $fromPyTrim) {
                        $this->warn('    ⚠️  数据库和 contentPy提取 一致，但 Info 接口不同');
                        $this->newLine();
                        $this->displayDiff($dbContent, $infoContent, 'content [数据库&contentPy提取] vs [Info接口]');
                    } elseif ($dbTrim === $infoTrim) {
                        // 数据库和 Info 一致，说明数据库正确，contentPy 提取有问题
                        $this->info('    ✅ 数据库和 Info 接口一致，contentPy 提取有误，跳过');
                        $this->line('    ' . str_repeat('=', 76));
                        $this->newLine();
                        $this->skippedCount++;
                        return null;
                    } else {
                        $this->error('    ❌ 三方数据均不一致！');
                        $this->newLine();

                        // 显示三方数据
                        $this->line('    <fg=blue>【数据库】</>');
                        $this->displaySingleContent($dbContent);
                        $this->newLine();

                        $this->line('    <fg=green>【从 contentPy 提取】</>');
                        $this->displaySingleContent($yzsyContentFromPy);
                        $this->newLine();

                        $this->line('    <fg=yellow>【Info 接口】</>');
                        $this->displaySingleContent($infoContent);
                    }

                    $this->line('    ' . str_repeat('=', 76));
                    $this->newLine();
                } else {
                    $this->error('    ❌ Info 接口请求失败，无法三方比对，跳过');
                    $this->line('    ' . str_repeat('-', 76));
                    $this->displayDiff($dbContent, $yzsyContentFromPy, 'content [数据库] vs [从contentPy提取的汉字]');
                    $this->line('    ' . str_repeat('-', 76));
                    $this->newLine();
                    $this->errorCount++;
                    return null;
                }
            }

            if (!$contentDiff && !$contentPyDiff) {
                $this->info('    ✅ content 和 content_py 均一致，无需更新');
                $this->newLine();
                $this->skippedCount++;
                return null;
            }

            // 如果 content 不一致，调用 shiwenInfo 接口进行三方比对
            $infoContent = null;
            if ($contentDiff) {
                $this->warn('    ⚠️  content 不一致！');
                $this->comment('    🔍 调用 shiwenInfo 接口进行三方比对...');
                $this->newLine();

                $infoData = $this->fetchPoemInfo($poem->id_str);

                if ($infoData) {
                    $infoContent = $infoData['contentTxt'] ?? '';

                    $this->line('    <fg=magenta>【三方 content 比对】</>');
                    $this->line('    ' . str_repeat('=', 76));

                    // 三方比对
                    $dbTrim = trim($dbContent);
                    $yzsyTrim = trim($yzsyContent);
                    $infoTrim = trim($infoContent);

                    if ($dbTrim === $yzsyTrim && $yzsyTrim === $infoTrim) {
                        $this->info('    ✅ 三方完全一致（这不应该发生，可能是空格问题）');
                    } elseif ($yzsyTrim === $infoTrim) {
                        $this->warn('    ⚠️  YZSY 和 Info 接口一致，但与数据库不同');
                        $this->newLine();
                        $this->displayDiff($dbContent, $yzsyContent, 'content [数据库] vs [YZSY&Info接口]');
                    } elseif ($dbTrim === $yzsyTrim) {
                        $this->warn('    ⚠️  数据库和 YZSY 接口一致，但 Info 接口不同');
                        $this->newLine();
                        $this->displayDiff($dbContent, $infoContent, 'content [数据库&YZSY] vs [Info接口]');
                    } elseif ($dbTrim === $infoTrim) {
                        // 数据库和 Info 一致，说明数据库正确，YZSY 错误，跳过
                        $this->info('    ✅ 数据库和 Info 接口一致，YZSY 接口错误，跳过更新');
                        $this->line('    ' . str_repeat('=', 76));
                        $this->newLine();
                        $this->skippedCount++;
                        return null;
                    } else {
                        $this->error('    ❌ 三方数据均不一致！');
                        $this->newLine();

                        // 显示三方数据
                        $this->line('    <fg=blue>【数据库】</>');
                        $this->displaySingleContent($dbContent);
                        $this->newLine();

                        $this->line('    <fg=green>【YZSY 接口】</>');
                        $this->displaySingleContent($yzsyContent);
                        $this->newLine();

                        $this->line('    <fg=yellow>【Info 接口】</>');
                        $this->displaySingleContent($infoContent);
                    }

                    $this->line('    ' . str_repeat('=', 76));
                    $this->newLine();
                } else {
                    $this->error('    ❌ Info 接口请求失败，仅显示两方对比');
                    $this->newLine();
                    $this->line('    ' . str_repeat('-', 76));
                    $this->displayDiff($dbContent, $yzsyContent, 'content [数据库] vs [YZSY接口]');
                    $this->line('    ' . str_repeat('-', 76));
                    $this->newLine();
                }
            }

            // 显示 content_py 差异
            if ($contentPyDiff) {
                $this->warn('    ⚠️  content_py 不一致！');
                $this->line('    ' . str_repeat('-', 76));
                $this->displayDiff($dbContentPy, $yzsyContentPy, 'content_py');
                $this->line('    ' . str_repeat('-', 76));
                $this->newLine();
            }

            // 询问是否更新
            $shouldUpdate = $auto;
            if (!$auto) {
                $choice = $this->choice(
                    '是否用接口数据替换数据库？',
                    ['yes' => '是', 'no' => '否', 'quit' => '退出'],
                    'no'
                );

                if ($choice === 'quit') {
                    $this->newLine();
                    $this->comment('⏹  用户选择退出');
                    return 'quit';
                }

                $shouldUpdate = ($choice === 'yes');
            }

            if ($shouldUpdate) {
                $updated = false;
                if ($contentDiff) {
                    $poem->content = $yzsyContent;
                    $updated = true;
                }
                if ($contentPyDiff) {
                    $poem->content_py = $yzsyContentPy;
                    $updated = true;
                }
                if ($updated) {
                    $poem->save();
                    $this->info('    ✅ 已更新到数据库（使用 YZSY 接口数据）');
                    $this->updatedCount++;
                }
            } else {
                $this->comment('    ⏭️  跳过更新');
                $this->skippedCount++;
            }

            $this->newLine();

        } catch (Throwable $e) {
            $this->error("    ❌ 错误: {$e->getMessage()}");
            $this->newLine();
            $this->errorCount++;
        }

        return null;
    }

    /**
     * 从古文岛接口获取 content 和 contentTxtPy
     */
    private function fetchPoemContent(string $idStr): ?array
    {
        $result = $this->http->get('shiwen/shiwenYZSY.aspx', ['idStr' => $idStr]);
        $shiwen = $result['shiwen'] ?? null;

        if (!$shiwen) {
            return null;
        }

        return [
            'content' => $shiwen['contentTxt'] ?? '',
            'contentPy' => $shiwen['contentTxtPy'] ?? '',
        ];
    }

    /**
     * 从 shiwenInfo 接口获取详细信息（用于二次比对）
     */
    private function fetchPoemInfo(string $idStr): ?array
    {
        $result = $this->http->get('shiwen/shiwenInfo.aspx', ['idStr' => $idStr]);
        $shiwen = $result['shiwen'] ?? null;

        if (!$shiwen) {
            return null;
        }

        return [
            'nameStr' => $shiwen['nameStr'] ?? '',
            'author' => $shiwen['author'] ?? '',
            'chaodai' => $shiwen['chaodai'] ?? '',
            'contentTxt' => $shiwen['contentTxt'] ?? '',
        ];
    }

    /**
     * 从拼音内容中提取纯汉字（去除拼音标注）
     * 例如：<p>cǎi采wēi薇</p> -> <p>采薇</p>
     */
    private function extractChineseFromPinyin(string $contentPy): string
    {
        // 只去除拼音字母（不在 HTML 标签内的），保留汉字、标点和 HTML 标签
        // 策略：匹配不在 < 和 > 之间的拼音字母
        // 使用负向预查，确保不删除 HTML 标签中的字母

        // 方法：分割 HTML 标签和内容，只处理内容部分
        $parts = preg_split('/(<[^>]+>)/u', $contentPy, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($parts as $part) {
            // 如果是 HTML 标签（以 < 开头），保持不变
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
    private function displayDiff(string $db, string $api, string $fieldName): void
    {
        $dbLines = $this->splitLines($db);
        $apiLines = $this->splitLines($api);

        $this->line("    <fg=cyan>字段: {$fieldName}</>");
        $this->line('    <fg=red>【数据库中】:</>');
        $this->displayWithHighlight($dbLines, $apiLines, 'red');

        $this->newLine();

        $this->line('    <fg=green>【接口返回】:</>');
        $this->displayWithHighlight($apiLines, $dbLines, 'green');
    }

    /**
     * 分割文本为行（保留句子结构）
     */
    private function splitLines(string $text): array
    {
        // 按换行符分割
        $lines = preg_split('/[\n\r]+/', trim($text));

        if (count($lines) === 1 && mb_strlen($text) > 100) {
            // 如果是单行且太长，按句子分割
            $lines = preg_split('/([，。；！？、])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $result = [];
            for ($i = 0; $i < count($lines); $i += 2) {
                $result[] = ($lines[$i] ?? '') . ($lines[$i + 1] ?? '');
            }
            return array_filter($result);
        }

        return array_filter($lines);
    }

    /**
     * 显示文本并高亮差异部分
     */
    private function displayWithHighlight(array $lines1, array $lines2, string $color): void
    {
        $text1 = implode('', $lines1);
        $text2 = implode('', $lines2);

        // 字符级别的差异检测
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

    /**
     * 显示单个内容（不做比对，仅显示）
     */
    private function displaySingleContent(string $text): void
    {
        $lines = $this->splitLines($text);
        $output = '    ';
        $lineLength = 0;
        $maxLineLength = 70;

        foreach ($lines as $line) {
            $len = mb_strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($line, $i, 1);

                if ($char === "\n" || $char === "\r" || $lineLength >= $maxLineLength) {
                    $this->line($output);
                    $output = '    ';
                    $lineLength = 0;
                    if ($char === "\n" || $char === "\r") {
                        continue;
                    }
                }

                $output .= $char;
                $lineLength++;
            }
        }

        if ($output !== '    ') {
            $this->line($output);
        }
    }

    /**
     * 显示最终统计信息
     */
    private function displayFinalStats(): void
    {
        $this->info('========================================');
        $this->info('✅ 处理完成！');
        $this->line("统计: 更新 <fg=green>{$this->updatedCount}</> 条，跳过 <fg=yellow>{$this->skippedCount}</> 条，错误 <fg=red>{$this->errorCount}</> 条");
        $this->info('========================================');
    }
}
