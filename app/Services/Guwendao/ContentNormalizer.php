<?php

namespace App\Services\Guwendao;

class ContentNormalizer
{
    /**
     * 把上游 HTML 中的旧高亮色替换为本站配色。
     * 命中的字段：所有从 guwendao 接口拉回的富文本（content / yiwen / shangxi / zhushi / yizhu_content 等）。
     */
    private const COLOR_MAP = [
        '#af9100' => '#15559a',
        '#518564' => '#835e1d',
    ];

    public static function html(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }
        return trim(strtr($html, self::COLOR_MAP));
    }
}
