<?php

namespace App\Services\Dictation;

/**
 * 注释解析器
 * 从诗词的 yizhu_content 中提取适合出题的短注释
 */
class AnnotationParser
{
    private const MAX_WORD_LENGTH = 5;

    private const MAX_MEANING_LENGTH = 40;

    /**
     * 解析诗词注释，提取字词和释义
     *
     * @param  object|array  $poem  诗词数据（支持 Poem 模型、对象或数组）
     * @return array<int, array{word: string, meaning: string}>
     */
    public function parseAnnotations($poem): array
    {
        $annotations = [];
        $content = $this->plainText((string) $this->value($poem, 'content', ''));

        foreach ($this->annotationSources($poem) as $source) {
            $texts = $this->zhuSpanTexts($source);
            if ($texts === []) {
                $texts = [$this->annotationSection($this->plainText($source))];
            }

            foreach ($texts as $text) {
                if ($text === '') {
                    continue;
                }

                foreach ($this->parseAnnotationText($text) as $annotation) {
                    if ($content !== '' && mb_strpos($content, $annotation['word'], 0, 'UTF-8') === false) {
                        continue;
                    }

                    $annotations[$annotation['word'].'|'.$annotation['meaning']] = $annotation;
                }
            }
        }

        return array_values($annotations);
    }

    /**
     * @return array<int, string>
     */
    private function annotationSources(object|array $poem): array
    {
        $value = $this->value($poem, 'yizhu_content');

        if (is_string($value) && trim($value) !== '') {
            return [$value];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function zhuSpanTexts(string $html): array
    {
        $texts = $this->zhuSpanTextsFromDom($html);
        if ($texts !== []) {
            return $texts;
        }

        preg_match_all('/<span\b(?=[^>]*\bzhu\b)[^>]*>(.*?)<\/span>/isu', $html, $matches);

        return array_values(array_filter(array_map(
            fn (string $value) => $this->plainText($value),
            $matches[1] ?? []
        )));
    }

    /**
     * @return array<int, string>
     */
    private function zhuSpanTextsFromDom(string $html): array
    {
        if (trim($html) === '' || ! class_exists(\DOMDocument::class)) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="annotation-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@zhu and not(ancestor::*[@zhu])]');
        if (! $nodes) {
            return [];
        }

        $texts = [];
        foreach ($nodes as $node) {
            $text = $this->plainText($node->textContent);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    private function value(object|array $source, string $key, mixed $default = null): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? $default;
        }

        return $source->{$key} ?? $default;
    }

    private function plainText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<\s*br\s*\/?>/iu', "\n", $text);
        $text = preg_replace('/<\s*\/\s*(p|div|li|tr|h[1-6]|strong)\s*>/iu', "\n", $text);
        $text = strip_tags((string) $text);
        $text = str_replace(["\xc2\xa0", '　'], ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\R{2,}/u', "\n", (string) $text);

        return trim(mb_convert_encoding((string) $text, 'UTF-8', 'UTF-8'));
    }

    private function annotationSection(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
        if ($lines === []) {
            return '';
        }

        $headingIndex = null;
        $isExactHeading = false;

        foreach ($lines as $index => $line) {
            $heading = $this->compactHeading($line);
            if (in_array($heading, ['注释', '注解', '词句注释', '字词注释'], true)) {
                $headingIndex = $index;
                $isExactHeading = true;
            }
        }

        if ($headingIndex === null) {
            foreach ($lines as $index => $line) {
                $heading = $this->compactHeading($line);
                if (mb_strlen($heading, 'UTF-8') <= 8 && str_contains($heading, '注')) {
                    $headingIndex = $index;
                    break;
                }
            }
        }

        if ($headingIndex === null) {
            return implode("\n", $lines);
        }

        $section = [];
        for ($index = $headingIndex + 1; $index < count($lines); $index++) {
            if ($isExactHeading && $this->isNonAnnotationHeading($lines[$index])) {
                break;
            }

            $section[] = $lines[$index];
        }

        return implode("\n", $section);
    }

    private function compactHeading(string $line): string
    {
        return preg_replace('/[\s：:]+/u', '', trim($line)) ?: '';
    }

    private function isNonAnnotationHeading(string $line): bool
    {
        return in_array($this->compactHeading($line), [
            '译文',
            '赏析',
            '鉴赏',
            '简析',
            '创作背景',
            '中心思想',
        ], true);
    }

    /**
     * @return array<int, array{word: string, meaning: string}>
     */
    private function parseAnnotationText(string $text): array
    {
        $numberedAnnotations = $this->parseNumberedAnnotationText($text);
        if ($numberedAnnotations !== []) {
            return $numberedAnnotations;
        }

        $marker = $this->markerPattern();
        $entryStart = '(?:'.$marker.')?[^：:，,\n]{1,'.self::MAX_WORD_LENGTH.'}[：:]';

        $text = preg_replace('/([。！？；;])\s*('.$entryStart.')/u', "$1\n$2", $text) ?: $text;
        $pattern = '/(?:^|\n)\s*(?:'.$marker.')?([^：:，,\n]{1,'.self::MAX_WORD_LENGTH.'})[：:]\s*(.*?)(?=\n\s*'.$entryStart.'|$)/su';

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $annotations = [];
        foreach ($matches as $match) {
            $word = $this->normalizeWord($match[1]);
            $meaning = $this->normalizeMeaning($match[2]);

            if ($this->isValidWord($word) && $this->isValidMeaning($meaning)) {
                $annotations[] = [
                    'word' => $word,
                    'meaning' => $meaning,
                ];
            }
        }

        if ($annotations !== []) {
            return $annotations;
        }

        return $this->parseCommaSeparatedLines($text);
    }

    /**
     * @return array<int, array{word: string, meaning: string}>
     */
    private function parseNumberedAnnotationText(string $text): array
    {
        $marker = $this->markerPattern();
        $entryStart = $marker.'[^：:，,\n]{1,'.self::MAX_WORD_LENGTH.'}[：:]';
        $pattern = '/'.$marker.'([^：:，,\n]{1,'.self::MAX_WORD_LENGTH.'})[：:]\s*(.*?)(?=\s*'.$entryStart.'|$)/su';

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $annotations = [];
        foreach ($matches as $match) {
            $word = $this->normalizeWord($match[1]);
            $meaning = $this->normalizeMeaning($match[2]);

            if ($this->isValidWord($word) && $this->isValidMeaning($meaning)) {
                $annotations[] = [
                    'word' => $word,
                    'meaning' => $meaning,
                ];
            }
        }

        return $annotations;
    }

    /**
     * @return array<int, array{word: string, meaning: string}>
     */
    private function parseCommaSeparatedLines(string $text): array
    {
        $annotations = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if (! preg_match('/^([^：:，,]{1,5})[，,](.+)$/u', $line, $matches)) {
                continue;
            }

            $word = $this->normalizeWord($matches[1]);
            $meaning = $this->normalizeMeaning($matches[2]);

            if ($this->isValidWord($word) && $this->isValidMeaning($meaning)) {
                $annotations[] = [
                    'word' => $word,
                    'meaning' => $meaning,
                ];
            }
        }

        return $annotations;
    }

    private function markerPattern(): string
    {
        return '(?:[⑴⑵⑶⑷⑸⑹⑺⑻⑼⑽⑾⑿⒀⒁⒂⒃⒄⒅⒆⒇①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮⑯⑰⑱⑲⑳]|[¹²³⁴⁵⁶⁷⁸⁹⁰]+|[\(（]\d+[\)）]|\d+[\.、])\s*';
    }

    private function normalizeWord(string $word): string
    {
        $word = preg_replace('/^'.$this->markerPattern().'/u', '', trim($word)) ?: trim($word);

        return preg_replace('/\s+/u', '', $word) ?: '';
    }

    private function normalizeMeaning(string $meaning): string
    {
        $meaning = preg_replace('/\s+/u', ' ', trim($meaning)) ?: '';
        $meaning = preg_replace(
            '/[（(]?\s*(?:一作|又作|亦作)\s*[：:]?\s*[“"‘\']?[^”"’\'。！？；;，,、）)]{1,20}[”"’\']?\s*[）)]?\s*[。！？；;，,、]*/u',
            '',
            $meaning
        ) ?: '';
        $meaning = preg_replace('/^[\s。！？；、，,.!?;：:]+/u', '', $meaning) ?: '';
        $meaning = preg_replace('/[\s。！？；、，,.!?;：:]+$/u', '', $meaning) ?: '';

        return trim($meaning);
    }

    /**
     * 判断字词是否适合作为题目
     *
     * @param string $word
     * @return bool
     */
    private function isValidWord(string $word): bool
    {
        // 至少1个字符
        if (mb_strlen($word, 'UTF-8') < 1) {
            return false;
        }

        // 不超过5个字符（避免过长的词组）
        if (mb_strlen($word, 'UTF-8') > self::MAX_WORD_LENGTH) {
            return false;
        }

        // 排除常见虚词和过于简单的字
        $excludedWords = ['的', '了', '着', '呢', '吗', '吧', '啊', '呀', '哦', '嗯'];

        if (in_array($word, $excludedWords, true)) {
            return false;
        }

        // 排除纯标点
        if (preg_match('/^[，。！？；、,.!?;]+$/u', $word)) {
            return false;
        }

        return true;
    }

    private function isValidMeaning(string $meaning): bool
    {
        $length = mb_strlen($meaning, 'UTF-8');

        if ($length < 1 || $length > self::MAX_MEANING_LENGTH) {
            return false;
        }

        if (preg_match('/^(原是|后来|古代官署|作者|朝代)/u', $meaning)) {
            return false;
        }

        return true;
    }

    /**
     * 从注释中提取特定字词的释义
     *
     * @param object|array $poem 诗词数据
     * @param string $targetWord
     * @return string|null
     */
    public function findMeaningForWord($poem, string $targetWord): ?string
    {
        $annotations = $this->parseAnnotations($poem);

        foreach ($annotations as $annotation) {
            if ($annotation['word'] === $targetWord) {
                return $annotation['meaning'];
            }
        }

        return null;
    }
}
