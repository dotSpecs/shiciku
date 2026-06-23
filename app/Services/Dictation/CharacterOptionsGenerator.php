<?php

namespace App\Services\Dictation;

class CharacterOptionsGenerator
{
    /**
     * 常见诗词高频字
     */
    private const COMMON_CHARS = [
        '春', '花', '秋', '月', '风', '雨', '云', '山', '水', '江',
        '天', '人', '心', '夜', '日', '时', '年', '来', '去', '看',
        '红', '白', '青', '绿', '明', '暗', '长', '短', '高', '低',
        '东', '西', '南', '北', '中', '前', '后', '上', '下', '里',
        '声', '色', '香', '光', '影', '情', '意', '思', '梦', '愁',
    ];

    /**
     * 为填空题或上下句题生成候选字
     *
     * @param  string  $answer  正确答案
     * @param  string  $poemContent  完整诗词内容
     * @param  int  $targetCount  目标候选字数量
     * @return array<int, string>
     */
    public function generate(string $answer, string $poemContent, int $targetCount = 12): array
    {
        $answerChars = $this->splitChars($answer);
        $poemChars = $this->splitChars($poemContent);

        // 计算每个答案字需要的数量（处理重复字）
        $answerCharCounts = array_count_values($answerChars);

        // 1. 添加所有答案字（包含重复）
        $options = $answerChars;

        // 2. 收集干扰字
        $distractors = $this->collectDistractors($answerChars, $poemChars, $answerCharCounts);

        // 3. 补充到目标数量
        $needed = max(0, $targetCount - count($options));
        $selectedDistractors = $this->selectDistractors($distractors, $needed);

        foreach ($selectedDistractors as $char) {
            $options[] = $char;
        }

        // 4. 完全打乱顺序
        shuffle($options);

        return array_values($options);
    }

    /**
     * 收集干扰字
     *
     * @param  array<int, string>  $answerChars
     * @param  array<int, string>  $poemChars
     * @param  array<string, int>  $answerCharCounts
     * @return array<int, string>
     */
    private function collectDistractors(array $answerChars, array $poemChars, array $answerCharCounts): array
    {
        $distractors = [];
        $answerSet = array_flip($answerChars);

        // 优先级1: 同诗其他字（排除已经在答案中的字，或只取不超过答案需要数量的）
        foreach ($poemChars as $char) {
            if (! isset($answerSet[$char])) {
                $distractors[] = $char;
            } elseif (isset($answerCharCounts[$char])) {
                // 如果答案中只需要1个"处"，但诗中有3个"处"，额外的2个可以作为干扰项
                $countInPoem = count(array_keys($poemChars, $char));
                $countNeeded = $answerCharCounts[$char];

                for ($i = 0; $i < ($countInPoem - $countNeeded); $i++) {
                    $distractors[] = $char;
                }
            }
        }

        // 优先级2: 高频诗词用字
        foreach (self::COMMON_CHARS as $char) {
            if (! isset($answerSet[$char]) && ! in_array($char, $poemChars, true)) {
                $distractors[] = $char;
            }
        }

        return array_values(array_unique($distractors));
    }

    /**
     * 从干扰字中选择指定数量
     *
     * @param  array<int, string>  $distractors
     * @return array<int, string>
     */
    private function selectDistractors(array $distractors, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if (count($distractors) <= $count) {
            return $distractors;
        }

        shuffle($distractors);

        return array_slice($distractors, 0, $count);
    }

    /**
     * 将字符串拆分为字符数组
     *
     * @return array<int, string>
     */
    private function splitChars(string $text): array
    {
        // 移除标点符号和空白字符
        $text = preg_replace('/[\s\p{P}]/u', '', $text);

        return mb_str_split($text, 1, 'UTF-8');
    }
}
