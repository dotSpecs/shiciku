# 微信小程序 API 鉴权与签名

为微信小程序开放的 `/api/wx/*` 接口提供身份识别、请求防伪、防重放三层保护。支持**多个小程序**共用同一套后端。

---

## 体系总览

```
┌─────────────────────────────────────────────────────────────┐
│ users           (用户主体: name/avatar/email/phone/password) │
│   ↑ 1                                                        │
│   │ N                                                        │
│ wx_users        (微信登录通道: appid + openid + unionid)     │
│                                                              │
│ apps            (小程序注册表: app_key ↔ appid + secret)     │
└─────────────────────────────────────────────────────────────┘
```

- `users` 干净：只承载身份+展示信息。后续邮箱、手机号登录都直接用 users 字段。
- `wx_users` 是登录通道：每条对应"某用户在某小程序"的一次绑定。`(appid, openid)` 复合 UNIQUE。
- `apps` 多小程序注册：小程序请求头带 `X-APPKEY`，服务端反查 appid + secret。
- 跨小程序合并：同一 unionid 命中多条 wx_users → 合并到同一 user。

---

## 表结构

### `apps`
| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| app_key | string(64) UNIQUE | 业务简称，下发给小程序，用作 X-APPKEY |
| appid | string(64) UNIQUE | 微信 appid |
| secret | string(64) | 微信 app secret |
| name | string(64) NULL | 显示用 |
| enabled | bool default true | 关闭后该小程序所有请求 401 |
| timestamps | | |

### `users`（沿用 Laravel 默认 + 调整）
- `name / email / password` 都改成 nullable（小程序用户没有 email/password）
- 加 `avatar string(255) NULL`
- 加 `phone string(32) NULL UNIQUE`

### `wx_users`
| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| user_id | FK users.id ON DELETE CASCADE | |
| appid | string(64) INDEX | 属于哪个小程序 |
| openid | string(64) | 小程序内用户 ID |
| unionid | string(64) NULL INDEX | 跨小程序聚合用 |
| timestamps | | |

复合 UNIQUE：`(appid, openid)`

---

## 配置

`.env` 不需要 appid/secret（数据库管理）。`config/services.php` 可不动。

新增 app 走 tinker 或 seeder：
```php
App\Models\App::create([
    'app_key' => 'shici_main',
    'appid'   => 'wx1234...',
    'secret'  => 'abcd...',
    'name'    => '诗词主站小程序',
]);
```

---

## 协议

### 登录
```
POST /api/wx/login
Headers:  X-APPKEY: <app_key>
Body:     { "code": "<wx.login code>" }
```

后端流程：
1. `App::where('app_key', $key)->first()` → 拿到 appid/secret
2. `MiniProgramClient::code2Session($appid, $secret, $code)` → `{openid, session_key, unionid?}`
3. `WxUser::firstOrNew(['appid'=>$appid, 'openid'=>$openid])`
4. `user_id` 为空 → 看 unionid 是否能匹配已有 user，否则新建 user
5. `SessionStore::issue($user, $appid, $sessionKey, $wxUserId)` → 64 位 hex token + 64 位 hex `sign_key`，写 Redis
6. 返回：
```json
{
  "token": "<64hex>",
  "sign_key": "<64hex>",
  "expires_in": 7200
}
```

> 返回给小程序的 `sign_key` 是后端随机生成的 API 签名密钥，不是微信 `code2Session` 返回的原始 `session_key`。微信原始 `session_key` 只保存在服务端会话中，不下发给小程序。
>
> 不使用 `md5(wx_session_key)` 这类派生值。MD5 不是加密，且会把前端凭证和微信凭证明显绑定；API 签名密钥必须独立随机生成。

### 业务请求 Headers

| Header | 内容 |
|---|---|
| `X-APPKEY` | 同登录时的 app_key |
| `Authorization` | `Bearer <token>` |
| `X-WX-Timestamp` | 当前 Unix 时间戳（秒） |
| `X-WX-Nonce` | 16 位随机串 |
| `X-WX-Sign` | hex(HMAC-SHA256(sign_key, canonical)) |

### 签名

`canonical`（3 行 `\n` 连接）：
```
<path-with-query>
<timestamp>
<nonce>
```

- **path-with-query**：完整的请求 URI，含 query string（如 `/api/poems?tag_id=10&page=2`）。无 query 时即纯 path（如 `/api/wx/me`）。
- 不含 scheme/host/method/body。

例：
- `/api/wx/me\n1715900000\nabc123def456`
- `/api/poems?tag_id=10&page=2\n1715900000\nabc123def456`

```php
$canonical = implode("\n", [$pathWithQuery, $ts, $nonce]);
$sign = hash_hmac('sha256', $canonical, $sign_key);   // hex
```

> 故意不签 METHOD/body：HTTPS 已防传输篡改，小程序端实现成本低。签 `path + query` 是防止"一个签名打任意端点/任意参数"。
>
> **小程序端**：传给签名函数的 URL 必须与实际请求的 URL 完全一致（含 query 顺序/编码）。服务端用 `$request->getRequestUri()` 取原始 URI 重算。

---

## 中间件 `wx.sign` 校验顺序

```
1. X-APPKEY 存在 → apps 查到 enabled 行 → 拿 appid，否则 401 invalid_app
2. Authorization Bearer + X-WX-Timestamp/Nonce/Sign 齐全 → 否则 401 missing_signature_headers
3. |now - timestamp| ≤ 300 → 否则 401 timestamp_out_of_window
4. Redis SET wx:nonce:{token}:{nonce} NX EX 600 → 重复返 401 nonce_replay
5. Redis GET wx:session:{token} → 不存在返 401 invalid_token
6. session.appid 必须等于步骤 1 的 appid → 否则 401 app_mismatch
7. 重算 HMAC（用服务端会话里保存的 sign_key）→ hash_equals 比对 → 错返 401 bad_signature
8. 滑动续期：Redis EXPIRE wx:session:{token} 7200
9. setUserResolver + attributes->set('wx_session', $session) → next
```

控制器里：
```php
public function me(Request $request) {
    $user = $request->user();   // 标准 Laravel 用法
    return ['name' => $user->name, 'avatar' => $user->avatar];
}
```

---

## 容灾：token 过期 / Redis 丢失

三层兜底，用户无感：

1. **滑动续期**（步骤 8）：只要小程序在用，token TTL 一直被刷到 7200s，永不过期。
2. **小程序端 401 拦截器**：封装 `wx.request`，遇到 `401 invalid_token` 或 `401 bad_signature` → 自动 `wx.login` → POST `/api/wx/login` → 拿新 token + sign_key 存本地 → 重放原请求（最多一次防循环）。
3. **Redis 持久化**：`redis.conf` 开 `appendonly yes`（AOF），重启基本不丢；极端丢了走机制 2。

微信侧 session_key 自身也会过期（官方未承诺具体时长），后端再次登录时会刷新服务端保存的微信 session_key 和小程序端使用的 sign_key。

---

## 小程序端示例（伪代码）

```js
// utils/request.js
const KEY = 'shici_main'

async function call(method, path, body) {
  let token = wx.getStorageSync('wx_token')
  let sk = wx.getStorageSync('wx_sign_key')
  if (!token) ({ token, sk } = await loginAndStore())
  const headers = signHeaders(path, token, sk)
  const res = await wx.request({ url: BASE + path, method, data: body, header: headers })
  if (res.statusCode === 401 && /invalid_token|bad_signature/.test(res.data?.error)) {
    ({ token, sk } = await loginAndStore())
    const h2 = signHeaders(path, token, sk)
    return wx.request({ url: BASE + path, method, data: body, header: h2 })
  }
  return res
}

function signHeaders(path, token, sk) {
  const ts = Math.floor(Date.now() / 1000)
  const nonce = randomHex(16)
  // path 必须包含 query string，与 wx.request 的 url 末段保持一致
  const canonical = `${path}\n${ts}\n${nonce}`
  const sign = hmacSha256Hex(sk, canonical)
  return {
    'X-APPKEY': KEY,
    'Authorization': `Bearer ${token}`,
    'X-WX-Timestamp': String(ts),
    'X-WX-Nonce': nonce,
    'X-WX-Sign': sign,
  }
}

async function loginAndStore() {
  const { code } = await wx.login()
  const { data } = await wx.request({
    url: BASE + '/api/wx/login',
    method: 'POST',
    header: { 'X-APPKEY': KEY },
    data: { code },
  })
  wx.setStorageSync('wx_token', data.token)
  wx.setStorageSync('wx_sign_key', data.sign_key)
  return { token: data.token, sk: data.sign_key }
}
```

---

## Redis Key 约定

| Key | 类型 | TTL | 用途 |
|---|---|---|---|
| `wx:session:{token}` | string(json) | 7200s 滑动 | 会话状态 |
| `wx:nonce:{token}:{nonce}` | string | 600s | 防重放 |

---

## Verification

```bash
# 1. 跑 migration
php artisan migrate

# 2. tinker 准备数据
> $app = App\Models\App::create(['app_key'=>'test','appid'=>'wx_test','secret'=>'fake','name'=>'test']);
> $u = User::create(['name'=>'mock']);
> $w = App\Models\WxUser::create(['user_id'=>$u->id,'appid'=>$app->appid,'openid'=>'mock_openid']);
> $r = app(App\Services\Wechat\SessionStore::class)->issue($u, $app->appid, 'wx_session_key_test_123', $w->id);
> echo $r['token'];
> echo $r['sign_key'];

# 3. curl 验签 /api/wx/me
TOKEN=...; SK=...; KEY=test
TS=$(date +%s); NONCE=$(openssl rand -hex 8)
PATH_=/api/wx/me   # 若带 query，例：PATH_='/api/poems?tag_id=10&page=2'
CANON=$(printf '%s\n%s\n%s' "$PATH_" "$TS" "$NONCE")
SIGN=$(printf '%s' "$CANON" | openssl dgst -sha256 -hmac "$SK" -hex | awk '{print $2}')
curl -i http://localhost:8000$PATH_ \
  -H "X-APPKEY: $KEY" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-WX-Timestamp: $TS" \
  -H "X-WX-Nonce: $NONCE" \
  -H "X-WX-Sign: $SIGN"
# 期望 200

# 4. 异常用例
# 同一组 headers 重发     → 401 nonce_replay
# X-WX-Sign 末尾改一字节  → 401 bad_signature
# TS 减 1000s             → 401 timestamp_out_of_window
# 不传 X-APPKEY           → 401 invalid_app
```

---

## 不在本期范围

- 手机号授权 `wx.getPhoneNumber` 解密 → 回填 `users.phone`
- 邮箱/密码登录（直接 users.email + password + Laravel 默认 auth）
- AES-GCM body 加密层
- 多端 token 互踢 / 强制下线
- 后台管理 apps 的 UI（首版用 tinker）
