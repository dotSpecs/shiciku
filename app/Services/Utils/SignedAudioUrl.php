<?php

namespace App\Services\Utils;

class SignedAudioUrl
{
    public static function generate(?string $audioPath): ?string
    {
        if ($audioPath === null || $audioPath === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $audioPath)) {
            return $audioPath;
        }

        $domain = config('services.cf_worker.audio_domain');
        $key = config('services.cf_worker.audio_key');
        $ttl = (int) config('services.cf_worker.audio_ttl', 1800);

        if (!$domain || !$key) {
            return null;
        }

        $path = '/' . ltrim($audioPath, '/');
        $base64Path = rtrim(strtr(base64_encode($path), '+/', '-_'), '=');

        $timestamp = time() + $ttl;
        $sign = md5($base64Path . $timestamp . $key);

        return rtrim($domain, '/')  . '/' . $base64Path . '?ts=' . $timestamp . '&sign=' . $sign;
    }
}
