<?php

namespace Tests\Feature\Dictation;

use App\Services\Dictation\DeepSeekAIService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekAIServiceTest extends TestCase
{
    public function test_generates_annotation_distractors_with_chat_completions_payload(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.deepseek.model' => 'deepseek-chat',
            'services.deepseek.timeout' => 60,
            'services.deepseek.max_tokens' => 512,
            'services.deepseek.temperature' => 0.1,
        ]);

        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"distractors":["疑问","迟疑","怀恨"]}',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(DeepSeekAIService::class)->generateAnnotationDistractors('疑', '怀疑，以为', '疑是地上霜');

        $this->assertSame(['疑问', '迟疑', '怀恨'], $result);

        Http::assertSent(function (Request $request) {
            $payload = $request->data();
            $prompt = $payload['messages'][1]['content'] ?? '';

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->url() === 'https://api.deepseek.test/chat/completions'
                && $payload['model'] === 'deepseek-chat'
                && $payload['stream'] === false
                && $payload['max_tokens'] === 512
                && $payload['messages'][0]['role'] === 'system'
                && $payload['messages'][1]['role'] === 'user'
                && str_contains($prompt, '小学老师不能判对')
                && str_contains($prompt, '如果某个选项放回原诗句也说得通');
        });
    }

    public function test_filters_annotation_distractors_that_are_too_close_to_correct_meaning(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"distractors":["拨动","拨开","划水","调拨","拨款","挑拨","点拨"]}',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(DeepSeekAIService::class)->generateAnnotationDistractors('拨', '划动', '红掌拨清波');

        $this->assertSame(['调拨', '拨款', '挑拨'], $result);
    }

    public function test_filters_annotation_distractors_that_are_specific_correct_senses(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"distractors":["受惊而飞","惊奇，诧异","惊慌","惊动，打扰","警醒","震动","惊扰"]}',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(DeepSeekAIService::class)->generateAnnotationDistractors('惊', '吃惊，害怕', '人来鸟不惊');

        $this->assertSame(['惊动，打扰', '警醒', '震动'], $result);
    }

    public function test_fills_filtered_annotation_distractors_from_local_fallbacks(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"distractors":["拨动","拨开","划水","调拨"]}',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(DeepSeekAIService::class)->generateAnnotationDistractors('拨', '划动', '红掌拨清波');

        $this->assertSame(['调拨', '分给，调配', '用手指弹'], $result);
    }

    public function test_fills_unknown_annotation_words_from_generic_fallbacks(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"distractors":[]}',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(DeepSeekAIService::class)->generateAnnotationDistractors('沧浪', '青苍色的水', '沧浪之水清兮');

        $this->assertCount(3, $result);
        $this->assertSame(['时间很久', '官职名', '声音很大'], $result);
    }

    public function test_uses_local_fallbacks_when_deepseek_request_fails(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        Http::fake([
            'api.deepseek.test/*' => Http::response(['error' => 'server unavailable'], 503),
        ]);

        $result = app(DeepSeekAIService::class)->generateAnnotationDistractors('拨', '划动', '红掌拨清波');

        $this->assertSame(['分给，调配', '用手指弹', '掉转方向'], $result);
    }
}
