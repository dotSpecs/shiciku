<?php

namespace App\Services\Wechat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MiniProgramClient
{
    private const ENDPOINT = 'https://api.weixin.qq.com/sns/jscode2session';
    private const ACCESS_TOKEN_ENDPOINT = 'https://api.weixin.qq.com/cgi-bin/token';
    private const WXA_CODE_ENDPOINT = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit';
    private const MSG_SEC_CHECK_ENDPOINT = 'https://api.weixin.qq.com/wxa/msg_sec_check';
    private const ACCESS_TOKEN_TTL = 7000;

    public const MSG_SEC_SCENE_PROFILE = 1;

    /**
     * @return array{openid: string, session_key: string, unionid?: string}
     */
    public function code2Session(string $appid, string $secret, string $code): array
    {
        $res = Http::timeout(10)->get(self::ENDPOINT, [
            'appid' => $appid,
            'secret' => $secret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (!$res->successful()) {
            throw new RuntimeException("jscode2session http {$res->status()}");
        }

        $data = $res->json();
        if (!is_array($data) || empty($data['openid']) || empty($data['session_key'])) {
            $code = $data['errcode'] ?? 'unknown';
            $msg = $data['errmsg'] ?? 'invalid response';
            throw new RuntimeException("jscode2session failed code={$code} msg={$msg}");
        }

        return [
            'openid' => $data['openid'],
            'session_key' => $data['session_key'],
            'unionid' => $data['unionid'] ?? null,
        ];
    }

    public function getWxaCode(string $appid, string $secret, array $params): string
    {
        $token = $this->getAccessToken($appid, $secret);

        $res = Http::timeout(20)->post(self::WXA_CODE_ENDPOINT . '?access_token=' . urlencode($token), $params);

        $contentType = strtolower($res->header('Content-Type', ''));
        if (str_contains($contentType, 'json')) {
            $data = $res->json();
            $code = is_array($data) ? ($data['errcode'] ?? 'unknown') : 'unknown';
            $msg = is_array($data) ? ($data['errmsg'] ?? 'invalid response') : 'invalid response';
            throw new RuntimeException("getwxacodeunlimit failed code={$code} msg={$msg}");
        }

        if (! $res->successful()) {
            throw new RuntimeException("getwxacodeunlimit http {$res->status()}");
        }

        return $res->body();
    }

    /**
     * @return array{pass: bool, suggest: string|null, label: int|null, trace_id: string|null}
     */
    public function checkMessageSecurity(
        string $appid,
        string $secret,
        string $openid,
        string $content,
        int $scene = self::MSG_SEC_SCENE_PROFILE
    ): array {
        $token = $this->getAccessToken($appid, $secret);

        $res = Http::timeout(10)->post(self::MSG_SEC_CHECK_ENDPOINT . '?access_token=' . urlencode($token), [
            'content' => $content,
            'version' => 2,
            'scene' => $scene,
            'openid' => $openid,
        ]);

        if (! $res->successful()) {
            throw new RuntimeException("msg_sec_check http {$res->status()}");
        }

        $data = $res->json();
        if (! is_array($data)) {
            throw new RuntimeException('msg_sec_check invalid response');
        }

        $code = (int) ($data['errcode'] ?? 0);
        $message = (string) ($data['errmsg'] ?? 'invalid response');
        $traceId = isset($data['trace_id']) ? (string) $data['trace_id'] : null;

        if ($code === 87014) {
            return [
                'pass' => false,
                'suggest' => 'risky',
                'label' => null,
                'trace_id' => $traceId,
            ];
        }

        if ($code !== 0) {
            throw new RuntimeException("msg_sec_check failed code={$code} msg={$message}");
        }

        $result = is_array($data['result'] ?? null) ? $data['result'] : [];
        $suggest = isset($result['suggest']) ? (string) $result['suggest'] : 'pass';
        $label = isset($result['label']) ? (int) $result['label'] : null;

        return [
            'pass' => $suggest === 'pass',
            'suggest' => $suggest,
            'label' => $label,
            'trace_id' => $traceId,
        ];
    }

    private function getAccessToken(string $appid, string $secret): string
    {
        $cacheKey = 'wx:access_token:' . $appid;

        return Cache::remember($cacheKey, self::ACCESS_TOKEN_TTL, function () use ($appid, $secret) {
            $res = Http::timeout(10)->get(self::ACCESS_TOKEN_ENDPOINT, [
                'grant_type' => 'client_credential',
                'appid' => $appid,
                'secret' => $secret,
            ]);

            if (! $res->successful()) {
                throw new RuntimeException("getAccessToken http {$res->status()}");
            }

            $data = $res->json();
            if (! is_array($data) || empty($data['access_token'])) {
                $code = $data['errcode'] ?? 'unknown';
                $msg = $data['errmsg'] ?? 'invalid response';
                Log::warning('get access token failed', ['appid' => $appid, 'code' => $code, 'msg' => $msg]);
                throw new RuntimeException("getAccessToken failed code={$code} msg={$msg}");
            }

            return (string) $data['access_token'];
        });
    }
}
