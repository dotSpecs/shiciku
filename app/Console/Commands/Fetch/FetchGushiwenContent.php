<?php

namespace App\Console\Commands\Fetch;

use App\Models\Poem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchGushiwenContent extends Command
{
    protected $signature = 'fetch:gushiwen-content
        {--file= : ID文件路径（默认：storage/logs/inconsistent_poem_ids.txt）}
        {--from-id= : 从指定ID开始}
        {--auto : 自动更新所有不一致的内容}';

    protected $description = '从古诗文网抓取诗词内容并与数据库比对';

    private int $totalProcessed = 0;
    private int $contentDiffCount = 0;
    private int $contentPyDiffCount = 0;
    private int $updatedCount = 0;

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('古诗文网内容抓取比对');
        $this->info('========================================');
        $this->newLine();

        // 读取ID文件
        $filePath = $this->option('file') ?: storage_path('logs/inconsistent_poem_ids.txt');

        if (!file_exists($filePath)) {
            $this->error("文件不存在: {$filePath}");
            return self::FAILURE;
        }

        $ids = array_filter(array_map('trim', file($filePath)));
        $ids = array_map('intval', $ids);

        // 如果指定了从某个ID开始
        if ($fromId = $this->option('from-id')) {
            $ids = array_filter($ids, fn($id) => $id >= (int) $fromId);
        }

        $total = count($ids);

        if ($total === 0) {
            $this->warn('没有需要处理的ID');
            return self::SUCCESS;
        }

        $this->info("📊 共读取 {$total} 个 ID");
        $this->newLine();

        foreach ($ids as $index => $id) {
            $this->totalProcessed++;
            $idx = $index + 1;

            $poem = Poem::find($id);

            if (!$poem || !$poem->id_str) {
                $this->warn("[{$idx}/{$total}] ID: {$id} - 未找到或缺少 id_str，跳过");
                continue;
            }

            $this->line("[{$idx}/{$total}] 处理 ID: <fg=cyan>{$id}</> - {$poem->name}");

            $result = $this->processPoem($poem, $idx, $total);

            // 如果用户选择退出
            if ($result === 'quit') {
                $this->newLine();
                $this->comment('⏹  用户选择退出');
                break;
            }

            // 延迟，避免请求过快
            usleep(500000); // 0.5秒
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('✅ 处理完成！');
        $this->line("统计: 处理 <fg=cyan>{$this->totalProcessed}</> 条");
        $this->line("      content 不一致 <fg=red>{$this->contentDiffCount}</> 条");
        $this->line("      content_py 不一致 <fg=yellow>{$this->contentPyDiffCount}</> 条");
        $this->line("      已更新 <fg=green>{$this->updatedCount}</> 条");
        $this->info('========================================');

        return self::SUCCESS;
    }

    /**
     * 处理单个诗词
     *
     * @return string|null 'quit' 表示用户选择退出
     */
    private function processPoem(Poem $poem, int $index, int $total): ?string
    {
        $idStr = $poem->id_str;
        $auto = $this->option('auto');

        try {
            // 1. 抓取主页面获取 content 和 pinyin detail id
            $mainUrl = "https://www.gushiwen.cn/shiwenv_{$idStr}.aspx";
            $this->comment("    🔍 抓取: {$mainUrl}");

            $response = Http::timeout(30)->get($mainUrl);

            if (!$response->successful()) {
                $this->error("    ❌ 请求失败: HTTP {$response->status()}");
                return null;
            }

            $html = $response->body();

            // 提取 content
            $gswContent = $this->extractContent($html, $idStr);

            if (!$gswContent) {
                $this->error("    ❌ 未找到 content 标签");
                return null;
            }

            // 提取 pinyin detail id
            $pinyinId = $this->extractPinyinId($html, $idStr);

            // 比对 content
            $dbContent = $poem->content ?? '';
            $contentDiff = trim($gswContent) !== trim($dbContent);

            $gswContentPy = null;
            $contentPyDiff = false;

            // 2. 如果有拼音ID，抓取拼音内容
            if ($pinyinId) {
                $this->comment("    🔍 抓取拼音内容...");

                $pinyinUrl = "https://www.gushiwen.cn/nocdn/ajaxshiwenDetailCont.aspx?id={$pinyinId}&value=yin";

                $pinyinResponse = Http::timeout(30)->get($pinyinUrl);

                if ($pinyinResponse->successful()) {
                    $gswContentPy = $this->extractPinyinContent($pinyinResponse->body());

                    if ($gswContentPy) {
                        // 比对 content_py
                        $dbContentPy = $poem->content_py ?? '';
                        $contentPyDiff = trim($gswContentPy) !== trim($dbContentPy);
                    } else {
                        $this->warn("    ⚠️  未找到拼音内容");
                    }
                } else {
                    $this->warn("    ⚠️  拼音内容请求失败");
                }
            }

            // 显示差异
            if ($contentDiff) {
                $this->contentDiffCount++;
                $this->newLine();
                $this->error("    ⚠️  content 不一致！");
                $this->line('    ' . str_repeat('-', 76));
                $this->displayDiff($dbContent, $gswContent, 'content');
                $this->line('    ' . str_repeat('-', 76));
            } else {
                $this->info("    ✅ content 一致");
            }

            if ($contentPyDiff) {
                $this->contentPyDiffCount++;
                $this->newLine();
                $this->error("    ⚠️  content_py 不一致！");
                $this->line('    ' . str_repeat('-', 76));
                $this->displayDiff($dbContentPy, $gswContentPy, 'content_py');
                $this->line('    ' . str_repeat('-', 76));
            } else if ($pinyinId) {
                $this->info("    ✅ content_py 一致");
            }

            // 如果有差异，询问是否更新
            if ($contentDiff || $contentPyDiff) {
                $this->newLine();

                $shouldUpdate = $auto;
                if (!$auto) {
                    $choice = $this->choice(
                        '是否用古诗文网数据更新数据库？',
                        ['yes' => '是', 'no' => '否', 'quit' => '退出'],
                        'yes'
                    );

                    if ($choice === 'quit') {
                        return 'quit';
                    }

                    $shouldUpdate = ($choice === 'yes');
                }

                if ($shouldUpdate) {
                    $updated = false;
                    if ($contentDiff) {
                        $poem->content = $gswContent;
                        $updated = true;
                    }
                    if ($contentPyDiff && $gswContentPy) {
                        $poem->content_py = $gswContentPy;
                        $updated = true;
                    }
                    if ($updated) {
                        $poem->save();
                        $this->updatedCount++;
                        $this->info('    ✅ 已更新到数据库');
                    }
                } else {
                    $this->comment('    ⏭️  跳过更新');
                }
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->error("    ❌ 错误: {$e->getMessage()}");
            $this->newLine();
        }

        return null;
    }

    /**
     * 提取 content
     */
    private function extractContent(string $html, string $idStr): ?string
    {
        $pattern = '/<div class="contson" id="contson' . preg_quote($idStr, '/') . '">(.*?)<\/div>/s';

        if (preg_match($pattern, $html, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * 提取拼音 detail id
     */
    private function extractPinyinId(string $html, string $idStr): ?string
    {
        $pattern = '/onclick="OnPinyinDetail\(\'' . preg_quote($idStr, '/') . '\',\'([^\']+)\'\)"/';

        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 提取拼音内容并移除 span 标签
     */
    private function extractPinyinContent(string $html): ?string
    {
        $pattern = '/<div class="pinyinContson">(.*?)<\/div>/s';

        if (preg_match($pattern, $html, $matches)) {
            $content = $matches[1];

            // 循环移除所有 span 标签（处理嵌套情况）
            while (preg_match('/<span[^>]*>/', $content)) {
                $content = preg_replace('/<span[^>]*>(.*?)<\/span>/s', '$1', $content);
            }

            // 移除所有空白符（包括全角空格、半角空格、制表符等），但保留换行
            $content = preg_replace('/[\s\x{3000}]+/u', '', $content);

            // 统一 br 标签格式：<br/> -> <br />
            $content = preg_replace('/<br\s*\/?>/i', '<br />', $content);

            // 恢复 p 和 br 标签周围可能需要的换行
            $content = str_replace('<br />', '<br />', $content);
            $content = str_replace('<p>', '<p>', $content);
            $content = str_replace('</p>', '</p>', $content);

            return trim($content);
        }

        return null;
    }

    /**
     * 显示差异（高亮不同的部分）
     */
    private function displayDiff(string $db, string $gsw, string $fieldName): void
    {
        $this->line("    <fg=cyan>字段: {$fieldName}</>");
        $this->line('    <fg=red>【数据库】</>');
        $this->displayWithHighlight($db, $gsw, 'red');

        $this->newLine();

        $this->line('    <fg=green>【古诗文网】</>');
        $this->displayWithHighlight($gsw, $db, 'green');
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
