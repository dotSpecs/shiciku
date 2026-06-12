<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Wechat\MiniAppRegistry;
use App\Services\Wechat\SessionStore;
use App\Services\Wechat\Signer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyWxSign
{
    public function __construct(
        private SessionStore $sessions,
        private MiniAppRegistry $apps,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $appKey = (string) $request->header('X-APPKEY', '');
        if ($appKey === '') {
            return $this->reject('invalid_app');
        }
        $app = $this->apps->findEnabledByAppKey($appKey);
        if (! $app) {
            return $this->reject('invalid_app');
        }

        $auth = (string) $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return $this->reject('missing_signature_headers');
        }
        $token = substr($auth, 7);
        $ts = (int) $request->header('X-WX-Timestamp', 0);
        $nonce = (string) $request->header('X-WX-Nonce', '');
        $sign = (string) $request->header('X-WX-Sign', '');
        if ($token === '' || ! $ts || $nonce === '' || $sign === '') {
            return $this->reject('missing_signature_headers');
        }

        if (abs(time() - $ts) > 300) {
            return $this->reject('timestamp_out_of_window');
        }

        $nonceKey = "wx:nonce:{$token}:{$nonce}";
        if (! Cache::add($nonceKey, 1, 600)) {
            return $this->reject('nonce_replay');
        }

        $session = $this->sessions->find($token);
        if (! $session) {
            return $this->reject('invalid_token');
        }

        if (($session['appid'] ?? null) !== $app->appid) {
            return $this->reject('app_mismatch');
        }

        $path = $request->getRequestUri();
        $signKey = $session['sign_key'] ?? null;
        if (! $signKey || ! Signer::verify($path, $ts, $nonce, $signKey, $sign)) {
            return $this->reject('bad_signature');
        }

        $this->sessions->touch($token);

        $request->setUserResolver(fn () => User::find($session['user_id']));
        $request->attributes->set('wx_session', $session);

        return $next($request);
    }

    private function reject(string $code): JsonResponse
    {
        return response()->json(['error' => $code], 401);
    }
}
