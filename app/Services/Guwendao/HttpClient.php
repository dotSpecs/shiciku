<?php

namespace App\Services\Guwendao;

use RuntimeException;

class HttpClient
{
    private const BASE = 'https://app24.guwendao.net/router/';
    private const RATE_LIMIT_USEC = 200_000;

    private ?string $proxy;

    /** @var \CurlHandle|null */
    private $ch = null;

    public function __construct(?string $proxy = null)
    {
        $this->proxy = $proxy ?: (env('GWD_HTTP_PROXY') ?: null);
    }

    public function __destruct()
    {
        if ($this->ch) {
            curl_close($this->ch);
        }
    }

    public function get(string $endpoint, array $params = []): array
    {
        $query = http_build_query($params);
        $url = self::BASE . ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        $signed = generateTokenUrl($url);

        $paramsStr = $query !== '' ? urldecode($query) : '-';

        // 复用 curl handle，保持 TCP + TLS 连接
        if (!$this->ch) {
            $this->ch = curl_init();
            curl_setopt_array($this->ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            if ($this->proxy) {
                curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);
                curl_setopt($this->ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            }
        }

        $attempts = 0;
        $body = false;
        while ($attempts < 3) {
            $attempts++;
            $started = microtime(true);

            curl_setopt($this->ch, CURLOPT_URL, $signed);
            $body = curl_exec($this->ch);
            $errno = curl_errno($this->ch);
            $error = curl_error($this->ch);

            $elapsedMs = (int) round((microtime(true) - $started) * 1000);
            if ($errno === 0 && $body !== false) {
                fwrite(STDERR, sprintf("  · GET %s ? %s  %dms\n", $endpoint, $paramsStr, $elapsedMs));
                break;
            }
            // 连接失败时重置 handle，下次重建
            curl_reset($this->ch);
            $this->ch = null;
            if ($attempts < 3) {
                $backoffMs = 500 * $attempts;
                fwrite(STDERR, sprintf("    ✗ GET %s %dms (err %d: %s), backoff %dms\n", $endpoint, $elapsedMs, $errno, $error, $backoffMs));
                usleep($backoffMs * 1000);
            } else {
                fwrite(STDERR, sprintf("    ✗ GET %s %dms (err %d: %s) (final)\n", $endpoint, $elapsedMs, $errno, $error));
            }
        }
        if ($body === false) {
            throw new RuntimeException("HTTP failed after retries: {$signed}");
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            $len = strlen($body);
            $err = json_last_error_msg();
            $preview = mb_substr($body, 0, 120) . ' ... ' . mb_substr($body, -80);
            throw new RuntimeException("Invalid JSON ({$len} bytes, {$err}): {$preview}");
        }
        if (($payload['code'] ?? 0) !== 200) {
            $msg = $payload['msg'] ?? 'unknown';
            throw new RuntimeException("API error code={$payload['code']} msg={$msg} url={$signed}");
        }

        usleep(self::RATE_LIMIT_USEC);

        return $payload['result'] ?? [];
    }
}
