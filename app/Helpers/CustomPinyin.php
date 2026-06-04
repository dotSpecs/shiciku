<?php

namespace App\Helpers;

class CustomPinyin
{
    /**
     * 获取自定义拼音映射
     *
     * @return array
     */
    public static function getMapping(): array
    {
        return [
            '将进酒' => 'qiāng jìn jiǔ',
            '夕阳斜' => 'xī yáng xiá',
            '万竿斜' => 'wàn gān xiá',
            '春风拂槛' => 'chūn fēng fú jiàn',
            '槛外' => 'jiàn wài',
            '鬓毛衰' => 'bìn máo cuī',
            '朝如青丝' => 'zhāo rú qīng sī',
            '天姥' => 'tiān mǔ',
            '泪不乾' => 'lèi bù gān',
            '重叠' => 'chóng dié',
            '曲项' => 'qū xiàng',
            '长相' => 'cháng xiāng',
        ];
    }

    /**
     * 获取 JavaScript 格式的配置（用于前端）
     *
     * @return string
     */
    public static function getJavaScriptConfig(): string
    {
        $mapping = self::getMapping();
        $lines = [];

        foreach ($mapping as $key => $value) {
            $lines[] = "    {$key}: \"{$value}\"";
        }

        return "{\n" . implode(",\n", $lines) . "\n}";
    }
}
