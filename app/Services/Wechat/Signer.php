<?php

namespace App\Services\Wechat;

class Signer
{
    public static function canonical(string $path, int $ts, string $nonce): string
    {
        return implode("\n", [$path, $ts, $nonce]);
    }

    public static function sign(string $canonical, string $key): string
    {
        return hash_hmac('sha256', $canonical, $key);
    }

    public static function verify(string $path, int $ts, string $nonce, string $key, string $expected): bool
    {
        return hash_equals(self::sign(self::canonical($path, $ts, $nonce), $key), $expected);
    }
}
