<?php

namespace App\Console\Commands\NewApi;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestResponses extends Command
{
    protected $signature = 'new-api:responses:test
        {--endpoint= : 完整接口地址，例如 https://example.com/v1/responses}
        {--base-url= : NEW API 基础地址，例如 https://example.com}
        {--api-key= : API Key；默认读取 NEW_API_API_KEY 或 OPENAI_API_KEY}
        {--model= : 模型名；默认读取 NEW_API_MODEL}
        {--input=请回复 pong，用一句话说明接口已连通。 : Responses input}
        {--instructions=你是一个接口连通性测试助手，只做简短回答。 : Responses instructions}
        {--max-output-tokens=128 : max_output_tokens}
        {--temperature=0 : temperature}
        {--top-p=1 : top_p}
        {--reasoning-effort= : reasoning.effort，例如 low/medium/high}
        {--previous-response-id= : previous_response_id}
        {--tool-choice= : tool_choice}
        {--stream : 使用 stream=true 并按块输出响应}
        {--timeout=60 : 请求超时时间，秒}
        {--dump-payload : 打印请求 payload，不包含 token}';

    protected $description = '临时测试 NEW API 原生 OpenAI Responses 格式接口是否连通';

    public function handle(): int
    {
        $endpoint = $this->endpoint();
        $apiKey = $this->apiKey();
        $model = $this->model();

        if ($endpoint === '') {
            $this->error('请传入 --endpoint=... 或 --base-url=...，也可设置 NEW_API_ENDPOINT / NEW_API_BASE_URL');

            return self::FAILURE;
        }

        if ($apiKey === '') {
            $this->error('请传入 --api-key=...，或设置 NEW_API_API_KEY / OPENAI_API_KEY');

            return self::FAILURE;
        }

        if ($model === '') {
            $this->error('请传入 --model=...，或设置 NEW_API_MODEL');

            return self::FAILURE;
        }

        $payload = $this->payload($model);

        $this->line("POST {$endpoint}");
        $this->line(sprintf(
            'model=%s stream=%s timeout=%ss',
            $model,
            $payload['stream'] ? 'true' : 'false',
            (int) $this->option('timeout')
        ));

        if ($this->option('dump-payload')) {
            $this->newLine();
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        $this->newLine();

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) $this->option('timeout'))
                ->withOptions(['stream' => $payload['stream']])
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            $this->error('连接失败：'.$e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('请求失败：'.$e->getMessage());

            return self::FAILURE;
        }

        $this->line("HTTP {$response->status()} {$response->reason()}");
        $this->line('content-type: '.($response->header('content-type') ?: '-'));
        $this->newLine();

        if ($payload['stream']) {
            $this->printStream($response->resource());

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        }

        $body = $response->body();
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $this->printSummary($decoded);
            $this->newLine();
            $this->line(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->line($body);
        }

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function endpoint(): string
    {
        $endpoint = trim((string) ($this->option('endpoint') ?: env('NEW_API_ENDPOINT', '')));
        if ($endpoint !== '') {
            return $endpoint;
        }

        $baseUrl = trim((string) ($this->option('base-url') ?: env('NEW_API_BASE_URL', '')));
        if ($baseUrl === '') {
            return '';
        }

        return rtrim($baseUrl, '/').'/v1/responses';
    }

    private function apiKey(): string
    {
        return trim((string) ($this->option('api-key') ?: env('NEW_API_API_KEY') ?: env('OPENAI_API_KEY', '')));
    }

    private function model(): string
    {
        return trim((string) ($this->option('model') ?: env('NEW_API_MODEL', '')));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $model): array
    {
        $payload = [
            'model' => $model,
            'input' => (string) $this->option('input'),
            'instructions' => (string) $this->option('instructions'),
            'max_output_tokens' => (int) $this->option('max-output-tokens'),
            'temperature' => (float) $this->option('temperature'),
            'top_p' => (float) $this->option('top-p'),
            'stream' => (bool) $this->option('stream'),
            'truncation' => 'auto',
        ];

        if ($this->option('reasoning-effort')) {
            $payload['reasoning'] = ['effort' => (string) $this->option('reasoning-effort')];
        }

        if ($this->option('previous-response-id')) {
            $payload['previous_response_id'] = (string) $this->option('previous-response-id');
        }

        if ($this->option('tool-choice')) {
            $payload['tool_choice'] = (string) $this->option('tool-choice');
        }

        return $payload;
    }

    /**
     * @param  resource  $resource
     */
    private function printStream($resource): void
    {
        while (! feof($resource)) {
            $chunk = fread($resource, 4096);
            if ($chunk === false || $chunk === '') {
                usleep(50_000);

                continue;
            }

            $this->output->write($chunk);
        }

        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function printSummary(array $decoded): void
    {
        foreach (['id', 'status', 'model', 'output_text'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                $this->line("{$key}: {$decoded[$key]}");
            }
        }
    }
}
