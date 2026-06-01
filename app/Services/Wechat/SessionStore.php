<?php

namespace App\Services\Wechat;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class SessionStore
{
    public const TTL = 7200;

    private const KEY_PREFIX = 'wx:session:';

    /**
     * @return array{token: string, sign_key: string, expires_in: int}
     */
    public function issue(User $user, string $appid, string $wxSessionKey, int $wxUserId): array
    {
        $token = bin2hex(random_bytes(32));
        $signKey = bin2hex(random_bytes(32));
        $payload = json_encode([
            'user_id' => $user->id,
            'wx_user_id' => $wxUserId,
            'appid' => $appid,
            'wx_session_key' => $wxSessionKey,
            'sign_key' => $signKey,
        ], JSON_UNESCAPED_UNICODE);

        Redis::setex(self::KEY_PREFIX.$token, self::TTL, $payload);

        return ['token' => $token, 'sign_key' => $signKey, 'expires_in' => self::TTL];
    }

    /**
     * @return array{user_id: int, wx_user_id: int, appid: string, wx_session_key: string, sign_key: string}|null
     */
    public function find(string $token): ?array
    {
        $raw = Redis::get(self::KEY_PREFIX.$token);
        if (! $raw) {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public function touch(string $token): void
    {
        Redis::expire(self::KEY_PREFIX.$token, self::TTL);
    }

    public function revoke(string $token): void
    {
        Redis::del(self::KEY_PREFIX.$token);
    }
}
