<?php

namespace App\Services\Utils;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AudioService
{
    /**
     * 文字转语音
     * @param string $text 文本
     * @param string $voice 音色 
     * @param float $speed 速度
     * @return array 结果
     */
    // 云扬: zh-CN-YunyangNeural, 云夏(小男孩): zh-CN-YunxiaNeural, 晓甄(女): zh-CN-XiaozhenNeural
    // 晓悠(儿童): zh-CN-XiaoyouNeural, 晓萱(小女孩): zh-CN-XiaoxuanNeural, 晓双(儿童): zh-CN-XiaoshuangNeural
    // 晓晓: zh-CN-XiaoxiaoNeural, 云健: zh-CN-YunjianNeural, 晓秋: zh-CN-XiaoqiuNeural
    public static function getAudio($text, $voice = 'zh-CN-YunyangNeural', $speed = 0.85)
    {
        $url = 'https://tts.meirishici.com/api/v1/audio/speech';

        try {
            $starttime = microtime(true);
            $resp = Http::withToken(config('services.edgetts.token'))
                ->asJson()
                ->withOptions([
                    // 'proxy' => 'http://127.0.0.1:64892',
                ])
                ->timeout(50)
                ->post($url, [
                    'input' => $text,
                    'pitch' => 1,
                    'speed' => $speed,
                    'stream' => false,
                    'voice' => $voice,
                    'cleaning_options' => [
                        'custom_keywords' => '',
                        'remove_citation_numbers' => false,
                        'remove_emoji' => true,
                        'remove_line_breaks' => false,
                        'remove_markdown' => true,
                        'remove_urls' => true,
                    ]
                ]);

            // Log::info('rest result', [
            //     'resp' => $resp,
            // ]);
            if ($resp->successful()) {
                return ['status' => 'success', 'body' => base64_encode($resp->body())];
            } else {
                Log::error('get poetry audio failed', [
                    'text' => $text,
                    'code' => $resp->status(),
                    'body' => $resp->body(),
                ]);
                return ['status' => 'error', 'message' => '生成失败，请稍候重试！'];
            }
        } catch (\Throwable $th) {
            Log::error('get poetry audio failed', [
                'text' => $text,
                'message' => $th->getMessage(),
            ]);
            return ['status' => 'error', 'message' => '生成失败，请稍候重试！'];
        }
    }
}
