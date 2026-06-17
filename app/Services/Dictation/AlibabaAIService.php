<?php

namespace App\Services\Dictation;

use App\Models\Dictation\Question;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlibabaAIService
{
    private string $apiKey;

    private string $apiUrl;

    private string $model;

    private int $timeout;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $this->apiKey = (string) config('services.alibaba_ai.api_key', '');
        $this->apiUrl = (string) config('services.alibaba_ai.api_url', 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions');
        $this->model = (string) config('services.alibaba_ai.model', 'qwen-plus');
        $this->timeout = (int) config('services.alibaba_ai.timeout', 60);
        $this->maxTokens = (int) config('services.alibaba_ai.max_tokens', 2048);
        $this->temperature = (float) config('services.alibaba_ai.temperature', 0.1);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{ok: bool, issues: array<int, string>, replacement_options: array<int, string>, raw: array<string|int, mixed>}
     *
     * @throws Exception
     */
    public function reviewAnnotationMeaningOptions(Question $question): array
    {
        $result = $this->callAlibaba($this->reviewPrompt($question));

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'issues' => $this->stringList($result['issues'] ?? []),
            'replacement_options' => $this->stringList($result['replacement_options'] ?? $result['suggested_options'] ?? []),
            'raw' => $result,
        ];
    }

    private function reviewPrompt(Question $question): string
    {
        $metadata = is_array($question->metadata) ? $question->metadata : [];
        $word = (string) ($metadata['word'] ?? '');
        $sentence = (string) ($metadata['sentence'] ?? '');
        $options = json_encode($question->options ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
请校验一道小学古诗词“注释理解”选择题的选项质量，并在有问题时给出替换后的完整选项。

题目ID：{$question->id}
题干：{$question->prompt}
字词：{$word}
所在诗句：{$sentence}
正确答案：{$question->answer}
当前选项：{$options}

判定标准：
1. 只有“正确答案”可以判对，其他选项必须是明确错误释义。
2. 如果错误选项与正确答案意思太接近、属于同义/近义/换说法/具体化表述/可在诗句语境中成立，必须标为问题。
3. 错误选项之间也不要高度重复或只换一种说法。
4. 选项必须短、清楚、适合小学古诗词注释题。
5. replacement_options 必须正好 4 个，必须包含原始“正确答案”原文，另外 3 个必须是更合适的错误选项；如果无需修改，replacement_options 返回 []。
6. 只返回合法 JSON，不要 markdown，不要解释。

返回格式：
{
  "ok": true,
  "issues": [],
  "replacement_options": []
}

有问题时示例：
{
  "ok": false,
  "issues": ["选项“拨动”与正确答案“划动”太接近"],
  "replacement_options": ["划动", "分给，调配", "用手指弹", "挑拨"]
}
PROMPT;
    }

    /**
     * @return array<string|int, mixed>
     *
     * @throws Exception
     */
    private function callAlibaba(string $prompt): array
    {
        if ($this->apiKey === '') {
            throw new Exception('Alibaba AI API key is not configured. Please set ALIBABA_AI_API_KEY or DASHSCOPE_API_KEY in .env');
        }

        $response = $this->request($prompt);
        if (! $response->successful()) {
            Log::error('Alibaba AI API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('Alibaba AI API request failed: '.$response->body());
        }

        $content = $this->responseText($response->json() ?? []);
        if ($content === '') {
            throw new Exception('Alibaba AI API returned empty content');
        }

        $result = $this->extractJson($content);
        if ($result === []) {
            Log::warning('Failed to parse Alibaba AI response as JSON', [
                'content' => $content,
            ]);

            throw new Exception('Failed to parse Alibaba AI response as JSON');
        }

        return $result;
    }

    private function request(string $prompt): Response
    {
        $messages = [
            [
                'role' => 'system',
                'content' => '你是严谨的小学古诗词题库审校员。必须只输出合法 JSON。',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        if ($this->usesNativeDashScopeEndpoint()) {
            return Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'input' => [
                        'messages' => $messages,
                    ],
                    'parameters' => [
                        'temperature' => $this->temperature,
                        'max_tokens' => $this->maxTokens,
                        'result_format' => 'message',
                    ],
                ]);
        }

        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'stream' => false,
            ]);
    }

    private function usesNativeDashScopeEndpoint(): bool
    {
        return str_contains($this->apiUrl, '/api/v1/services/');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function responseText(array $data): string
    {
        $content = $data['choices'][0]['message']['content']
            ?? $data['output']['choices'][0]['message']['content']
            ?? $data['output']['text']
            ?? '';

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            return trim(implode('', array_map(
                fn (mixed $part) => is_array($part) ? (string) ($part['text'] ?? '') : (string) $part,
                $content
            )));
        }

        return '';
    }

    /**
     * @return array<string|int, mixed>
     */
    private function extractJson(string $text): array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        foreach ([['{', '}'], ['[', ']']] as [$start, $end]) {
            $startPosition = strpos($text, $start);
            $endPosition = strrpos($text, $end);

            if ($startPosition === false || $endPosition === false || $endPosition <= $startPosition) {
                continue;
            }

            $candidate = substr($text, $startPosition, $endPosition - $startPosition + 1);
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item) => is_string($item) || is_numeric($item) ? trim((string) $item) : '',
            $value
        ))));
    }
}
