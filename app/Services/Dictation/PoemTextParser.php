<?php

namespace App\Services\Dictation;

class PoemTextParser
{
    private const VARIANT_PATTERN = '/[（(]\s*([^（）()]+?)\s*一作\s*[：:]?\s*([^（）()]+?)\s*[）)]/u';

    /**
     * @return array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>
     */
    public function sentences(?string $content): array
    {
        return array_merge(...$this->sentenceGroups($content));
    }

    /**
     * @return array<int, array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>>
     */
    public function sentenceGroups(?string $content): array
    {
        $text = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/p\s*>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $groups = [];
        foreach (preg_split('/\n+/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $variants = [];
            $cleanLine = preg_replace_callback(self::VARIANT_PATTERN, function (array $matches) use (&$variants) {
                $from = $this->compact($matches[1] ?? '');
                $to = $this->compact($matches[2] ?? '');
                if ($from !== '' && $to !== '') {
                    $variants[] = ['from' => $from, 'to' => $to];
                }

                return '';
            }, $line) ?? $line;

            foreach (preg_split('/[。！？；.!?;]+/u', $cleanLine) ?: [] as $groupText) {
                $group = [];

                foreach (preg_split('/[，、,]+/u', $groupText) ?: [] as $part) {
                    $sentence = $this->compact($part);
                    if (mb_strlen($sentence, 'UTF-8') < 2) {
                        continue;
                    }

                    $group[] = [
                        'text' => $sentence,
                        'variants' => array_values(array_filter(
                            $variants,
                            fn (array $variant) => str_contains($sentence, $variant['from'])
                        )),
                    ];
                }

                if ($group !== []) {
                    $groups[] = $group;
                }
            }
        }

        return $groups;
    }

    /**
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $sentence
     * @return array<int, string>
     */
    public function acceptedTexts(array $sentence): array
    {
        $texts = [$sentence['text']];

        foreach ($sentence['variants'] as $variant) {
            $next = $texts;
            foreach ($texts as $text) {
                $replaced = $this->replaceFirst($text, $variant['from'], $variant['to']);
                if ($replaced !== $text) {
                    $next[] = $replaced;
                }
            }
            $texts = array_values(array_unique($next));
        }

        return $texts;
    }

    private function compact(string $value): string
    {
        return preg_replace('/\s+/u', '', trim($value)) ?? '';
    }

    private function replaceFirst(string $text, string $search, string $replace): string
    {
        $pos = mb_strpos($text, $search, 0, 'UTF-8');
        if ($pos === false) {
            return $text;
        }

        return mb_substr($text, 0, $pos, 'UTF-8')
            .$replace
            .mb_substr($text, $pos + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
    }
}
