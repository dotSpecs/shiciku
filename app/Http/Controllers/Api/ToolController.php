<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poem;
use App\Services\Utils\AudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    private const UPLOAD_EXPIRES = 600;
    private const UPLOAD_HOST = 'https://up-z1.qiniup.com/';

    public function audio(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'type' => ['sometimes', 'string'],
        ]);

        $type = $data['type'] ?? 'poem';
        if ($type !== 'poem') {
            return response()->json(['error' => 'unsupported_type'], 400);
        }

        $poem = Poem::query()
            ->select('id', 'poem_id', 'name', 'content', 'author_id', 'dynasty_id')
            ->where('poem_id', $data['id'])
            ->with([
                'author:id,name',
                'dynasty:id,name',
            ])
            ->first();

        if (!$poem) {
            return response()->json([
                'status' => 'error',
                'message' => '诗词不存在',
            ], 404);
        }

        return response()->json(AudioService::getAudio($this->poemAudioText($poem)));
    }

    public function uploadToken(Request $request): JsonResponse
    {
        $scope = $request->input('scope', 'avatar');
        if ($scope !== 'avatar') {
            return response()->json(['error' => 'invalid_scope'], 400);
        }

        $uid = $request->user()->id;
        $ext = $this->normalizeUploadExt($request->input('ext'), $request->input('filename'));
        $key = 'avatars/poems/' . $uid . '/' . time() . '.' . $ext;

        return response()->json([
            'token' => $this->makeQiniuUploadToken($uid, $key),
            'key' => $key,
            'expires' => self::UPLOAD_EXPIRES,
            'host' => self::UPLOAD_HOST,
            'scope' => $scope,
        ]);
    }

    private function normalizeUploadExt(?string $ext = null, ?string $filename = null): string
    {
        $ext = $ext ?: pathinfo((string) $filename, PATHINFO_EXTENSION);
        $ext = strtolower(ltrim((string) $ext, '.'));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'jpg';
        }

        return $ext;
    }

    private function poemAudioText(Poem $poem): string
    {
        $content = str_replace(
            ['</p>', '<br>', '<br/>', '<br />'],
            "\n\n",
            (string) $poem->content
        );
        $content = strip_tags($content);
        $content = preg_replace('/\(.*?\)|（.*?）/u', '', $content);

        $meta = collect([
            $poem->dynasty?->name,
            $poem->author?->name,
        ])->filter()->implode(' · ');

        return $poem->name
            . '<break time="1s"/>'
            . "\n\n"
            . ($meta ? $meta . '<break time="1s"/>' . "\n\n" : '')
            . $content;
    }

    private function makeQiniuUploadToken(int $uid, string $key): string
    {
        $accessKey = (string) config('services.qiniu.ak');
        $secretKey = (string) config('services.qiniu.sk');
        $bucket = (string) config('services.qiniu.bucket');
        $host = rtrim((string) config('services.qiniu.host'), '/') . '/';

        $policy = [
            'scope' => $bucket . ':' . $key,
            'deadline' => time() + self::UPLOAD_EXPIRES,
            'mimeLimit' => 'image/*',
            'endUser' => (string) $uid,
            'fsizeLimit' => 4194304,
            'returnBody' => '{"url":"' . $host . $key . '","path":"$(key)","key":"$(key)"}',
            'insertOnly' => 0,
        ];

        $encodedPolicy = $this->base64UrlSafeEncode(json_encode($policy, JSON_UNESCAPED_SLASHES));
        $sign = hash_hmac('sha1', $encodedPolicy, $secretKey, true);

        return $accessKey . ':' . $this->base64UrlSafeEncode($sign) . ':' . $encodedPolicy;
    }

    private function base64UrlSafeEncode(string $value): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($value));
    }
}
