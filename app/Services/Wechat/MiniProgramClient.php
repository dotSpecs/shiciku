<?php

namespace App\Services\Wechat;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MiniProgramClient
{
    private const ENDPOINT = 'https://api.weixin.qq.com/sns/jscode2session';

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
}
