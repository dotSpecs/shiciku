<?php

namespace App\Services\Dictation;

class AnswerNormalizer
{
    public function normalize(?string $value): string
    {
        $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        if (function_exists('mb_convert_kana')) {
            $text = mb_convert_kana($text, 'asKV', 'UTF-8');
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[\s\p{P}\p{S}]+/u', '', $text) ?? '';

        return $text;
    }

    /**
     * @param  array<int, string>  $acceptedAnswers
     */
    public function isCorrect(?string $userAnswer, array $acceptedAnswers): bool
    {
        $normalized = $this->normalize($userAnswer);
        if ($normalized === '') {
            return false;
        }

        foreach ($acceptedAnswers as $answer) {
            if ($normalized === $this->normalize($answer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $acceptedAnswers
     */
    public function answerKey(array $acceptedAnswers, ?string $fallback = null): string
    {
        $answers = $acceptedAnswers ?: [$fallback ?? ''];
        $normalized = [];

        foreach ($answers as $answer) {
            $value = $this->normalize($answer);
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        $values = array_keys($normalized);
        sort($values, SORT_STRING);

        return md5(implode('|', $values));
    }
}
