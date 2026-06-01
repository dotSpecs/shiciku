<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App as MiniApp;
use App\Models\User;
use App\Models\WxUser;
use App\Services\Wechat\MiniProgramClient;
use App\Services\Wechat\SessionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WxAuthController extends Controller
{
    public function __construct(
        private MiniProgramClient $wx,
        private SessionStore $sessions,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string']);

        $appKey = (string) $request->header('X-APPKEY', '');
        $app = MiniApp::where('app_key', $appKey)->where('enabled', true)->first();
        if (! $app) {
            return response()->json(['error' => 'invalid_app'], 401);
        }

        try {
            $session = $this->wx->code2Session($app->appid, $app->secret, $data['code']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'code2session_failed', 'message' => $e->getMessage()], 400);
        }

        [$user, $wxUser] = DB::transaction(function () use ($app, $session) {
            $wxUser = WxUser::where('appid', $app->appid)
                ->where('openid', $session['openid'])
                ->first();

            if (! $wxUser) {
                $userId = null;
                if (! empty($session['unionid'])) {
                    $sibling = WxUser::where('unionid', $session['unionid'])->first();
                    $userId = $sibling?->user_id;
                }
                if (! $userId) {
                    $userId = User::create(['name' => null])->id;
                }
                $wxUser = WxUser::create([
                    'user_id' => $userId,
                    'appid' => $app->appid,
                    'openid' => $session['openid'],
                    'unionid' => $session['unionid'] ?? null,
                ]);
            } elseif (! empty($session['unionid']) && $wxUser->unionid !== $session['unionid']) {
                $wxUser->unionid = $session['unionid'];
                $wxUser->save();
            }

            return [User::find($wxUser->user_id), $wxUser];
        });

        $issued = $this->sessions->issue($user, $app->appid, $session['session_key'], $wxUser->id);

        return response()->json([
            'token' => $issued['token'],
            'sign_key' => $issued['sign_key'],
            'expires_in' => $issued['expires_in'],
            ...$this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'phone' => $user->phone,
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $allowed = ['name', 'avatar'];
        $extra = array_diff(array_keys($request->all()), $allowed);
        if ($extra) {
            return response()->json(['error' => 'invalid_fields'], 400);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->fill($data);
        $user->save();

        return response()->json($this->userPayload($user));
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
    }
}
