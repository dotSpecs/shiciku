# API 文档

面向小程序 / 第三方客户端的开放接口。

---

## 通用约定

### Base URL

| 环境 | URL |
|---|---|
| 生产 | `https://ku.meirishici.com` |
| 本地 | `http://localhost:8000` |

所有接口路径以 `/api` 开头。

### 认证

除 `POST /api/wx/login` 外，**所有 `/api/*` 接口必须经过 `wx.sign` 中间件**（X-APPKEY + Bearer token + HMAC 签名 + 防重放）。具体协议见 `docs/wx-mp-auth.md`。

| 接口 | 鉴权 |
|---|---|
| `POST /api/wx/login` | 仅需 `X-APPKEY` |
| 其他全部 `/api/*` | 完整 `wx.sign` |

### ID 暴露原则

主资源对外**只暴露 slug**，不返回数据库 PK：

| 资源 | 对外标识 | 字段名 |
|---|---|---|
| 诗词 Poem | slug | `poem_id` |
| 作者 Author | slug | `author_id` |
| 古籍 Book | slug | `book_id` |
| 古籍篇章 BookArticle | slug | `article_id` |
| 名句 Mingju | slug | `mingju_id` |
| 专题 Zhuanti | alias | `alias` |

枚举类资源（朝代 Dynasty、合集 Tag）的 `id` 会暴露，因为前端筛选必须以 `id` 作参数。

### 分页

列表接口统一使用 simple pagination：

```json
{
  "data": [...],
  "current_page": 1,
  "per_page": 15,
  "has_more": true
}
```

- `per_page` 由服务端固定（当前 15）
- 列表接口支持可选 `limit` 参数：1 ≤ limit ≤ per_page，超出按 per_page 截断，非法值回退默认。常见用法：作者详情等"预览前 N 条"的场景传 `limit=3` 减少传输
- `page` 上限 50，超出返回空 `data` + `has_more=false`
- 无 `total` / `last_page`（避免大表 COUNT 性能开销）

### 错误

非鉴权类错误统一返回 `200` + 空 `data`（如 `author_id` 不存在）。鉴权失败返回 `401` + `{"error": "<code>"}`。资源详情未找到返回 `404` + `{"error": "<code>"}`。

---

## 收藏接口

收藏接口均需要完整 `wx.sign` 鉴权，`id` 使用各资源对外 slug。

### 支持类型

| type | 说明 | id 字段 |
|---|---|---|
| `poem` | 诗词 | `poem_id` |
| `mingju` | 名句 | `mingju_id` |
| `book` | 古籍整本 | `book_id` |
| `book_article` | 古籍篇章 | `article_id` |

### `POST /api/favorites/{type}/{id}` — 收藏

**响应**

```json
{
  "favorited": true,
  "favorite_id": 123
}
```

重复收藏会保持幂等，仍返回 `favorited=true`。

### `DELETE /api/favorites/{type}/{id}` — 取消收藏

**响应**

```json
{
  "favorited": false
}
```

未收藏时调用也保持幂等。

### `GET /api/favorites/{type}/{id}` — 收藏状态

**响应**

```json
{
  "favorited": true
}
```

资源不存在返回 `404`：

```json
{
  "error": "target_not_found"
}
```

### `GET /api/favorites` — 我的收藏列表

用于“我的收藏”页面。支持按类型筛选；不传 `type` 时返回全部类型，按收藏时间倒序。

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `type` | string | 否 | `poem` / `mingju` / `book` / `book_article`，不传返回全部 |
| `page` | int | 否 | 页码，默认 1，上限 50 |

**响应**

```json
{
  "data": [
    {
      "type": "poem",
      "favorited_at": "2026-05-27 12:00:00",
      "item": {
        "poem_id": "tb2yd7acup",
        "name": "桃花源记",
        "author_name": "陶渊明",
        "chaodai": "魏晋",
        "dynasty": { "id": 3, "name": "魏晋" },
        "author": { "author_id": "taoyuanming", "name": "陶渊明" }
      }
    },
    {
      "type": "mingju",
      "favorited_at": "2026-05-27 12:00:30",
      "item": {
        "mingju_id": "mj-huidang",
        "name": "会当凌绝顶，一览众山小。",
        "source": "望岳",
        "guishu": 1,
        "author_name": "杜甫",
        "chaodai": "唐代",
        "author": { "author_id": "dufu", "name": "杜甫" }
      }
    },
    {
      "type": "book_article",
      "favorited_at": "2026-05-27 12:01:00",
      "item": {
        "article_id": "xueer",
        "name": "学而",
        "chapter": { "id": 1, "name": "卷一" },
        "book": {
        "book_id": "lunyu",
        "name": "论语",
        "author_name": "孔子",
        "chaodai": "春秋",
        "dynasty": null,
        "author": { "author_id": "kongzi", "name": "孔子" }
      }
    }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "has_more": true
}
```

不同 `type` 的 `item` 字段：

| type | item 主要字段 |
|---|---|
| `poem` | `poem_id`, `name`, `author_name`, `chaodai`, `dynasty`, `author` |
| `mingju` | `mingju_id`, `name`, `source`, `guishu`, `author_name`, `chaodai`, `author` |
| `book` | `book_id`, `name`, `class`, `type`, `author_name`, `chaodai`, `dynasty`, `author` |
| `book_article` | `article_id`, `name`, `chapter`, `book`（含古籍的 `author_name`, `chaodai`, `dynasty`, `author`） |

`type` 非法返回 `400`：

```json
{
  "error": "invalid_type"
}
```

小程序古籍页顶部展示用户收藏古籍时，调用 `GET /api/favorites?type=book`。

---

## 工具接口

### `POST /api/qrcode` — 生成小程序码

用于生成微信小程序码，扫码后先进入首页，再由首页根据 `scene` 做二次跳转。

**鉴权**

需要完整 `wx.sign` 鉴权。

**请求头**

| Header | 说明 |
|---|---|
| `X-APPKEY` | 小程序应用标识 |
| `Authorization` | `Bearer <token>` |
| `X-WX-Timestamp` | 时间戳 |
| `X-WX-Nonce` | 随机串 |
| `X-WX-Sign` | 签名 |

**请求体**

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `page` | string | 是 | 小程序页面路径，例如 `pages/index/index` |
| `scene` | string | 是 | 场景值，格式如 `poem*5yiwcmqkwe`、`ju*6dd50e9229`，最长 32 字符 |
| `type` | string | 否 | `poem` / `ju`，用于基础校验 |
| `is_hyaline` | boolean | 否 | 是否透明底，默认 `true` |
| `check_path` | boolean | 否 | 是否检查页面路径，默认 `true` |

**响应**

返回图片二进制流，`Content-Type: image/jpeg`。

**错误响应**

```json
{ "error": "invalid_scene" }
```

```json
{ "error": "invalid_app" }
```

```json
{ "error": "qrcode_failed", "message": "小程序码生成失败" }
```

**示例**

```json
{
  "page": "pages/index/index",
  "scene": "poem*5yiwcmqkwe",
  "type": "poem"
}
```

首页收到 `scene` 后自行解析并跳转到对应内容页。

---

## 学习进度接口

学习进度基于专题记录。状态绑定在“用户 + 专题 + 诗词”上，因此所有读写接口都需要专题 `alias`。

诗词详情页只有在 URL 携带 `zhuanti_alias` 时展示学习状态和学习操作；从搜索、收藏、作者页、分享等普通入口进入时不带 `zhuanti_alias`，不调用学习进度接口。

### 状态

| 状态 | 含义 | 是否入库 |
|---|---|---|
| `todo` | 未学习 | 否，仅响应中返回 |
| `started` | 已打开详情读过，但未标记已学 | 是 |
| `learned` | 用户主动标记已学 | 是 |

### `GET /api/study-progress/{alias}` — 获取专题学习进度

用于学习进度页，也可用于专题页合并展示。

**响应**

```json
{
  "alias": "xiaoxue",
  "name": "小学古诗",
  "total": 75,
  "learned_count": 12,
  "started_count": 20,
  "percent": 16,
  "last_poem_id": "jingyesi",
  "next_poem_id": "chunxiao",
  "chapters": [
    {
      "id": 1,
      "name": "一年级上册",
      "sub_title": null,
      "sort": 1,
      "total": 8,
      "learned_count": 3,
      "poems": [
        {
          "poem_id": "jingyesi",
          "name": "静夜思",
          "author_name": "李白",
          "chaodai": "唐代",
          "dynasty": { "id": 6, "name": "唐代" },
          "author": { "author_id": "libai", "name": "李白" },
          "study": {
            "status": "learned",
            "read_count": 2,
            "learned_at": "2026-05-29 12:00:00",
            "last_read_at": "2026-05-29 12:00:00"
          }
        }
      ]
    }
  ]
}
```

`next_poem_id` 按专题章节顺序返回第一首未学诗词；全部已学时为 `null`。`last_poem_id` 是最近读过的诗词，仅用于“最近阅读”类入口。

### `GET /api/study-progress/{alias}/poems/{poem_id}` — 获取单首学习状态

仅用于“有 `zhuanti_alias` 但没有状态缓存”的兜底场景。从学习进度页或专题页进入详情时，应优先使用页面跳转时传入的 `study` 数据。

**响应**

```json
{
  "alias": "xiaoxue",
  "poem_id": "jingyesi",
  "status": "learned",
  "read_count": 2,
  "learned_at": "2026-05-29 12:00:00",
  "last_read_at": "2026-05-29 12:00:00"
}
```

### `POST /api/study-progress/{alias}/poems/{poem_id}/read` — 记录阅读

用于带 `zhuanti_alias` 的诗词详情页打开后调用。该接口会创建或更新 `started` 记录，增加 `read_count`，更新 `last_read_at`，但不会计入已学。

**响应**

```json
{
  "alias": "xiaoxue",
  "poem_id": "jingyesi",
  "status": "started",
  "read_count": 1,
  "learned_at": null,
  "last_read_at": "2026-05-29 12:00:00"
}
```

### `PUT /api/study-progress/{alias}/poems/{poem_id}` — 更新学习状态

**请求**

```json
{
  "status": "learned"
}
```

取消已学时传：

```json
{
  "status": "started"
}
```

取消已学会清空 `learned_at`，但保留 `read_count` 和 `last_read_at`。

**响应**

```json
{
  "alias": "xiaoxue",
  "poem_id": "jingyesi",
  "status": "learned",
  "read_count": 2,
  "learned_at": "2026-05-29 12:00:00",
  "last_read_at": "2026-05-29 12:00:00"
}
```

### 错误

| 场景 | 状态码 | 响应 |
|---|---|---|
| 专题不存在 | 404 | `{"error":"zhuanti_not_found"}` |
| 诗词不存在或不属于该专题 | 404 | `{"error":"study_target_not_found"}` |
| `status` 非法 | 422 | Laravel validation errors |

---

## 工具接口

### `GET /api/custom_pinyin` — 获取自定义拼音配置

获取前端拼音转换器的自定义拼音映射表，用于处理特殊读音的字词。

**请求**

无需参数。

**响应**

```json
{
  "data": {
    "将进酒": "qiāng jìn jiǔ",
    "夕阳斜": "xī yáng xiá",
    "万竿斜": "wàn gān xiá",
    "春风拂槛": "chūn fēng fú jiàn",
    "槛外": "jiàn wài",
    "鬓毛衰": "bìn máo cuī",
    "朝如青丝": "zhāo rú qīng sī",
    "天姥": "tiān mǔ",
    "泪不乾": "lèi bù gān",
    "重叠": "chóng dié",
    "曲项": "qū xiàng",
    "长相": "cháng xiāng"
  }
}
```

**说明**

- 配置集中管理在 `App\Helpers\CustomPinyin` 类中
- 前端 `resources/js/pinyin.js` 在加载时自动调用此接口应用配置
- 用于 `pinyin-pro` 库的 `customPinyin()` 方法

### `POST /api/audio` — 生成诗词朗读音频

当诗词详情接口的 `audio` 字段为 `null` 时，可调用该接口临时生成朗读音频。

**请求**

```json
{
  "id": "tb2yd7acup",
  "type": "poem"
}
```

`type` 可不传，默认 `poem`。后续可扩展其他朗读类型。

**响应**

成功时返回 base64 音频内容：

```json
{
  "status": "success",
  "body": "base64-audio"
}
```

失败时：

```json
{
  "status": "error",
  "message": "生成失败，请稍候重试！"
}
```

诗词不存在返回 `404`：

```json
{
  "status": "error",
  "message": "诗词不存在"
}
```

暂不支持的 `type` 返回 `400`：

```json
{
  "error": "unsupported_type"
}
```

### `GET /api/upload/token` — 获取头像上传 Token

只支持头像上传，七牛 token 有效期 10 分钟。

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `scope` | string | 否 | 只能为 `avatar`，默认 `avatar` |
| `ext` | string | 否 | 文件扩展名，支持 `jpg` / `jpeg` / `png` / `gif` / `webp` / `bmp` |
| `filename` | string | 否 | 未传 `ext` 时从文件名提取扩展名 |

**响应**

```json
{
  "token": "qiniu-upload-token",
  "key": "avatars/poems/123/1780000000.jpg",
  "expires": 600,
  "host": "https://up-z1.qiniup.com/",
  "scope": "avatar"
}
```

`key` 格式固定为 `avatars/poems/{uid}/{time}.{ext}`。非法扩展名回退为 `jpg`。

`scope` 非 `avatar` 返回 `400`：

```json
{
  "error": "invalid_scope"
}
```

---

## 内容接口

### `GET /api/home` — 首页聚合

小程序首页一次拉取所需全部数据。除每日一诗外，其余 4 个 section 在服务端缓存 5 分钟。

**响应**

```json
{
  "daily_poem": {
    "poem_id": "tb2yd7acup",
    "name": "桃花源记",
    "favorited": false,
    "content": "<p>……</p>",
    "audio": "https://audio.070022.xyz/poem/tb2yd7acup.mp3?ts=1748160000&sign=8e1c…",
    "author_name": "陶渊明",
    "chaodai": "魏晋",
    "author": { "author_id": "taoyuanming", "name": "陶渊明" },
    "dynasty": { "id": 3, "name": "魏晋" }
  },
  "recommend_authors": [
    {
      "author_id": "dufu",
      "name": "杜甫",
      "pic": "https://cdn.meirishici.com/author/dufu.jpg",
      "dynasty": { "id": 6, "name": "唐" }
    }
  ],
  "featured_tags": [
    {
      "id": 23,
      "name": "小学古诗",
      "icon": "/static/images/tags/23.png",
      "poem_count": 128,
      "zhuanti": { "alias": "xiaoxue", "name": "小学古诗" }
    },
    {
      "id": 20,
      "name": "送别",
      "icon": "/static/images/tags/20.png",
      "poem_count": 45,
      "zhuanti": null
    }
  ],
  "featured_books": [
    {
      "book_id": "lunyu",
      "name": "论语",
      "author_name": "孔子",
      "chaodai": "春秋",
      "dynasty": null,
      "author": { "author_id": "kongzi", "name": "孔子" }
    }
  ],
  "quotes": [
    {
      "mingju_id": "mj-jyl",
      "name": "举头望明月，低头思故乡。",
      "source": "静夜思",
      "author_name": "李白",
      "chaodai": "唐代",
      "author": { "author_id": "libai", "name": "李白" }
    }
  ]
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `daily_poem` | object \| null | 当日推荐诗词，懒生成；为 null 表示未能选出（极端情况） |
| `daily_poem.poem_id` | string | 用于跳转诗词详情 |
| `daily_poem.favorited` | bool | 当前用户是否已收藏该诗词；实时查询，不进入缓存 |
| `daily_poem.audio` | string \| null | 朗读音频签名 URL（30 分钟有效）；无朗读资源时为 `null` |
| `daily_poem.author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `daily_poem.chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `recommend_authors[]` | array (10) | 从 `order < 999999` 的作者池随机 10 位 |
| `recommend_authors[].pic` | string \| null | 头像 URL |
| `featured_tags[]` | array (≤8) | 固定 8 个 tag（id 23/35/67/20/552/52/49/63），按该顺序返回；DB 中缺失的 id 跳过 |
| `featured_tags[].icon` | string | tag 图标路径，格式为 `/static/images/tags/{id}.png` |
| `featured_tags[].poem_count` | int | 该 tag 下的诗词数量；有专题时统计专题中的诗词数（`zhuanti_poems` 表），无专题时统计 tag 关联的诗词数（`poem_tag` 表） |
| `featured_tags[].zhuanti` | object \| null | 该 tag 关联的专题。有专题：跳转 `/pages/zhuanti/detail?alias=…`；无专题：跳转诗词列表 `tag_id=…` |
| `featured_tags[].zhuanti.alias` | string | 专题 slug |
| `featured_books[]` | array (10) | 随机 10 本古籍 |
| `featured_books[].author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `featured_books[].chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `featured_books[].dynasty` | string \| null | 关联朝代名 |
| `quotes[]` | array (3) | 随机 3 条 `guishu=1`（诗文出处）且有 `source_poem_id` 的名句，仅从出处诗词关联 tag_id 为 23/35/67/447/263/262 的名句中筛选 |
| `quotes[].author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `quotes[].chaodai` | string \| null | 抓取接口返回的朝代文本 |

**缓存策略**

| key | TTL | 备注 |
|---|---|---|
| `home:authors` | 5 min | |
| `home:tags` | 5 min | tag/zhuanti 关系几乎不变，可考虑延长 |
| `home:books` | 5 min | |
| `home:quotes` | 5 min | |

`daily_poem` 不进缓存，直接走 `DailyPoemService::today()`（已天级幂等）。

**示例**

```bash
curl 'http://localhost/api/home'
```

---

### `GET /api/poems` — 诗词列表

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `tag_id` | int | 否 | 合集 ID（Tag PK） |
| `dynasty_id` | int | 否 | 朝代 ID |
| `author_id` | string | 否 | 作者 slug |
| `type` | string | 否 | 类型，仅接受 `诗` / `词` / `曲` / `文言文`，其他值忽略 |
| `page` | int | 否 | 页码，默认 1，上限 50 |
| `limit` | int | 否 | 每页条数，1 ≤ limit ≤ 15（超出截断为 15） |

**响应**

```json
{
  "data": [
    {
      "poem_id": "tb2yd7acup",
        "name": "桃花源记",
        "content": "<p>……</p>",
        "author_name": "陶渊明",
        "chaodai": "魏晋",
        "dynasty": { "id": 3, "name": "魏晋" },
      "author": { "author_id": "pchx3eyuar", "name": "陶渊明" },
      "tags": [
        { "id": 51, "name": "专升本" },
        { "id": 263, "name": "初中文言文" },
        { "id": 424, "name": "古文观止" }
      ]
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "has_more": true
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `poem_id` | string | slug，用于跳转详情 |
| `name` | string | 诗词标题 |
| `content` | string (HTML) | 正文，含 `<p>` `<br>` |
| `author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `dynasty` | object \| null | 朝代信息 |
| `dynasty.id` | int | 朝代 ID（可作为 `dynasty_id` 参数） |
| `dynasty.name` | string | 朝代名 |
| `author` | object \| null | 作者信息，作者缺失时为 `null`（佚名） |
| `author.author_id` | string | 作者 slug |
| `author.name` | string | 作者名 |
| `tags[]` | array | 关联合集 |
| `tags[].id` | int | Tag ID（可作为 `tag_id` 参数） |
| `tags[].name` | string | 合集名 |

**排序**：按 `poems.order ASC, poems.id ASC` 固定排序。

**边界**

- `author_id` 未匹配到任何作者 → 空列表
- `page > 50` → 空列表 + `has_more=false`
- 多筛选条件并存使用 AND 组合

**示例**

```bash
# 唐诗三百首第 1 页
curl 'http://localhost/api/poems?tag_id=10'

# 唐代诗人
curl 'http://localhost/api/poems?dynasty_id=6'

# 某作者
curl 'http://localhost/api/poems?author_id=pchx3eyuar'

# 组合：唐诗三百首 + 唐代
curl 'http://localhost/api/poems?tag_id=10&dynasty_id=6'

# 只看词
curl 'http://localhost/api/poems?type=词'
```

---

### `GET /api/poems/{poem_id}` — 诗词详情

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `poem_id` | string | 是 | 诗词 slug |

**响应**

```json
{
  "poem_id": "tb2yd7acup",
  "name": "桃花源记",
  "favorited": false,
  "content": "<p>……</p>",
  "author_name": "陶渊明",
  "chaodai": "魏晋",
  "type": "诗",
  "supports": { "yin": true, "yizhu": true },
  "audio": "https://audio.070022.xyz/poem/tb2yd7acup.mp3?ts=1748160000&sign=8e1c…",
  "dynasty": { "id": 3, "name": "魏晋" },
  "author": { "author_id": "pchx3eyuar", "name": "陶渊明", "pic": "https://cdn.meirishici.com/author/taoyuanming.jpg" },
  "tags": [
    { "id": 51, "name": "专升本" }
  ],
  "fanyis": [
    { "id": 101, "name": "译文", "content": "<p>……</p>", "order": 0 }
  ],
  "shangxis": [
    { "id": 202, "name": "赏析", "content": "<p>……</p>", "order": 0 }
  ],
  "mingjus": [
    {
      "mingju_id": "abc123",
      "name": "黄发垂髫，并怡然自乐。",
      "author_name": "陶渊明",
      "chaodai": "魏晋"
    }
  ]
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `poem_id` | string | slug |
| `name` | string | 诗词标题 |
| `favorited` | bool | 当前用户是否已收藏 |
| `content` | string (HTML) | 正文 |
| `author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `type` | string \| null | 类型：诗、词、曲、文言文 |
| `supports.yin` | bool | 是否有拼音版（由 `yzsy` 字段含「音」决定）。`true` 时可调 `/api/poems/{poem_id}/yinyi` 取拼音 |
| `supports.yizhu` | bool | 是否有译注（由 `yzsy` 字段含「注」决定）。`true` 时可调 `/api/poems/{poem_id}/yinyi` 取译注 |
| `audio` | string \| null | 朗读音频签名 URL（30 分钟有效，Cloudflare Worker 校验 `ts`/`sign`）；无朗读资源时为 `null` |
| `dynasty` | object \| null | 朝代 |
| `author` | object \| null | 作者，缺失为 `null` |
| `author.pic` | string \| null | 作者头像 URL |
| `tags[]` | array | 关联合集 |
| `fanyis[]` | array | 译文列表，按 `order ASC` |
| `fanyis[].id` | int | 译文 ID（前端 list key 用，不作业务标识） |
| `fanyis[].name` | string | 段落标题（"译文"等） |
| `fanyis[].content` | string (HTML) | 段落内容 |
| `fanyis[].order` | int | 排序 |
| `shangxis[]` | array | 赏析列表，结构同 `fanyis[]` |
| `mingjus[]` | array | 该诗词关联的名句 |
| `mingjus[].mingju_id` | string | 名句 slug |
| `mingjus[].name` | string | 名句正文 |
| `mingjus[].author_name` | string \| null | 名句抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `mingjus[].chaodai` | string \| null | 名句抓取接口返回的朝代文本 |

**边界**

- `poem_id` 未匹配 → `404` + `{"error": "poem_not_found"}`

**示例**

```bash
curl 'http://localhost/api/poems/0007dfjviw'
```

---

### `GET /api/poems/{poem_id}/yinyi` — 拼音 + 译注

按需取拼音版与译注内容；是否有数据由诗词详情的 `supports` 字段决定，避免前端徒劳调用。

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `poem_id` | string | 是 | 诗词 slug |

**响应**

```json
{
  "yin": {
    "name": "táo huā yuán jì",
    "author": "táo yuān míng",
    "dynasty": "wèi jìn",
    "content": "jìn tài yuán zhōng……"
  },
  "yizhu": {
    "content": "<p>译注内容……</p>"
  }
}
```

| 字段 | 类型 | 说明 |
|---|---|---|
| `yin` | object \| null | 拼音版；`yzsy` 不含「音」时为 `null` |
| `yin.name` | string \| null | 标题拼音 |
| `yin.author` | string \| null | 作者拼音 |
| `yin.dynasty` | string \| null | 朝代拼音 |
| `yin.content` | string \| null | 正文拼音（原文以空格分隔每个字的拼音） |
| `yizhu` | object \| null | 译注；`yzsy` 不含「注」时为 `null`。预留 object 形式以便后续追加 `author`/`cankao` 字段 |
| `yizhu.content` | string (HTML) | 译注正文 |

**边界**

- `poem_id` 未匹配 → `404` + `{"error": "poem_not_found"}`
- 诗词存在但 `yzsy` 既不含「音」也不含「注」 → 返回 `{"yin": null, "yizhu": null}`，不报错

**示例**

```bash
curl 'http://localhost/api/poems/0007dfjviw/yinyi'
```

---

### `GET /api/zhuantis/{alias}` — 专题详情

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `alias` | string | 是 | 专题英文别名（如 `chuci` `tangshi`） |

**响应**

```json
{
  "alias": "tangshi",
  "name": "唐诗三百首",
  "chapters": [
    {
      "id": 21,
      "name": "五言绝句",
      "sub_title": "",
      "sort": 1,
      "poems": [
        {
          "poem_id": "jingyesi",
          "name": "静夜思",
          "author_name": "李白",
          "chaodai": "唐代",
          "author": { "author_id": "libai", "name": "李白" },
          "dynasty": { "id": 6, "name": "唐" }
        }
      ]
    }
  ]
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `alias` | string | 专题别名 |
| `name` | string | 专题名称 |
| `chapters[]` | array | 章节列表，按 `sort ASC` |
| `chapters[].id` | int | 章节 ID（前端 list key 用，无独立业务接口） |
| `chapters[].name` | string | 章节名 |
| `chapters[].sub_title` | string | 副标题，可能为空串 |
| `chapters[].sort` | int | 排序值 |
| `chapters[].poems[]` | array | 该章节诗词，按 `zhuanti_poems.order ASC` |
| `chapters[].poems[].poem_id` | string | 诗词 slug |
| `chapters[].poems[].name` | string | 诗词标题 |
| `chapters[].poems[].author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `chapters[].poems[].chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `chapters[].poems[].author` | object \| null | 作者，缺失为 `null` |
| `chapters[].poems[].dynasty` | object \| null | 朝代 |

**边界**

- `alias` 未匹配 → `404` + `{"error": "zhuanti_not_found"}`

**示例**

```bash
curl 'http://localhost/api/zhuantis/tangshi'
```

---

### `GET /api/books` — 古籍列表

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `class` | string | 否 | 大类（`经部` / `史部` / `子部` / `集部`） |
| `type` | string | 否 | 小类（如 `四书类` / `正史类`） |
| `page` | int | 否 | 页码，默认 1，上限 100 |

**响应**

```json
{
  "data": [
    {
      "book_id": "hmwfcrllaq",
      "name": "论语",
      "content": "<p>……</p>",
      "class": "经部",
      "type": "四书类",
      "author_name": "孔子",
      "chaodai": "春秋",
      "dynasty": "春秋",
      "author": { "author_id": "kongzi", "name": "孔子" }
    }
  ],
  "current_page": 1,
  "per_page": 10,
  "has_more": true
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `book_id` | string | 古籍 slug |
| `name` | string | 古籍名 |
| `content` | string (HTML) | 简介，HTML |
| `class` | string \| null | 大类 |
| `type` | string \| null | 小类 |
| `author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `dynasty` | string \| null | 朝代名 |
| `author` | object \| null | 作者，缺失为 `null`（佚名） |

**排序**：`books.order ASC, books.id ASC`。

**示例**

```bash
curl 'http://localhost/api/books?class=经部'
curl 'http://localhost/api/books?class=史部&type=正史类'
```

---

### `GET /api/books/{book_id}` — 古籍详情

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `book_id` | string | 是 | 古籍 slug |

**响应**

```json
{
  "book_id": "hmwfcrllaq",
  "name": "论语",
  "favorited": false,
  "content": "<p>……</p>",
  "class": "经部",
  "type": "四书类",
  "author_name": "孔子",
  "chaodai": "春秋",
  "dynasty": "春秋",
  "author": { "author_id": "kongzi", "name": "孔子" },
  "chapters": [
    {
      "id": 6001,
      "name": "学而第一",
      "order": 1,
      "articles": [
        { "article_id": "lunyu-xueer-1", "name": "学而时习之", "order": 1 }
      ]
    }
  ]
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `favorited` | bool | 当前用户是否已收藏该古籍 |
| `chapters[]` | array | 章节列表，按 `order ASC` |
| `chapters[].id` | int | 章节内部 ID（前端 wx:key 用） |
| `chapters[].articles[]` | array | 该章节文章列表，按 `order ASC` |
| `chapters[].articles[].article_id` | string | 文章 slug |

其他字段与列表接口一致。

**边界**

- `book_id` 未匹配 → `404` + `{"error": "book_not_found"}`

**示例**

```bash
curl 'http://localhost/api/books/hmwfcrllaq'
```

---

### `GET /api/articles/{article_id}` — 古籍文章详情

> 文章 slug `article_id` 在全库唯一，无需带 `book_id`。所属古籍由响应体 `book` 字段携带。

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `article_id` | string | 是 | 文章 slug |

**响应**

```json
{
  "article_id": "lunyu-xueer-1",
  "name": "学而时习之",
  "favorited": false,
  "content": "<p>……</p>",
  "chapter": { "id": 6001, "name": "学而第一" },
  "book": {
    "book_id": "lunyu",
    "name": "论语",
    "author_name": "孔子",
    "chaodai": "春秋",
    "dynasty": "春秋",
    "author": { "author_id": "kongzi", "name": "孔子" }
  },
  "supplements": [
    { "id": 1, "name": "段译", "content": "<p>……</p>" },
    { "id": 17278, "name": "注释", "content": "<p>……</p>" },
    { "id": 17279, "name": "赏析", "content": "<p>……</p>" }
  ],
  "previous": { "article_id": "lunyu-xueer-0", "name": "前一篇" },
  "next": { "article_id": "lunyu-xueer-2", "name": "其为人也孝弟" }
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `article_id` | string | 文章 slug |
| `name` | string | 文章标题 |
| `favorited` | bool | 当前用户是否已收藏该文章 |
| `content` | string (HTML) | 正文 |
| `chapter` | object \| null | 所属章节 |
| `chapter.id` | int | 章节内部 ID |
| `book` | object | 所属古籍简介 |
| `book.book_id` | string | 古籍 slug |
| `book.author_name` | string \| null | 古籍抓取接口返回的作者文本，`book.author` 为空时可作为展示回退 |
| `book.chaodai` | string \| null | 古籍抓取接口返回的朝代文本，`book.dynasty` 为空时可作为展示回退 |
| `book.dynasty` | string \| null | 古籍关联朝代名 |
| `book.author` | object \| null | 古籍作者 |
| `supplements[]` | array | 附属内容（译/注/赏析），按 `id ASC` |
| `supplements[].id` | int | 内部 ID（前端 wx:key 用，同时承担排序） |
| `supplements[].name` | string | 段落名 |
| `previous` | object \| null | 同章节内上一篇（首篇为 `null`） |
| `next` | object \| null | 同章节内下一篇（末篇为 `null`） |

**边界**

- `article_id` 未匹配 → `404` + `{"error": "article_not_found"}`

**示例**

```bash
curl 'http://localhost/api/articles/lunyu-xueer-1'
```

---

### `GET /api/mingjus` — 名句列表

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `tag_id` | int | 否 | 合集 ID |
| `dynasty_id` | int | 否 | 朝代 ID |
| `author_id` | string | 否 | 作者 slug |
| `page` | int | 否 | 页码，默认 1，上限 50 |
| `limit` | int | 否 | 每页条数，1 ≤ limit ≤ 15（超出截断为 15） |

**响应**

```json
{
  "data": [
    {
      "mingju_id": "mj-huidang",
      "name": "会当凌绝顶，一览众山小。",
      "source": "望岳",
      "guishu": 1,
      "author_name": "杜甫",
      "chaodai": "唐代",
      "dynasty": { "id": 6, "name": "唐" },
      "author": { "author_id": "dufu", "name": "杜甫" },
      "sourceBookArticle": null
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "has_more": true
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `mingju_id` | string | 名句 slug |
| `name` | string | 名句正文 |
| `source` | string | 出处文字（如《望岳》、《论语·学而》） |
| `guishu` | int | 归属类型（1=诗文 / 2=古籍 / 3=谚语 / 4=对联） |
| `author_name` | string \| null | 抓取接口返回的作者文本，`author` 为空时可作为展示回退 |
| `chaodai` | string \| null | 抓取接口返回的朝代文本，`dynasty` 为空时可作为展示回退 |
| `dynasty` | object \| null | 朝代 |
| `author` | object \| null | 作者，可能为佚名 → `null` |
| `sourceBookArticle` | object \| null | 当 `guishu=2` 时给出所属古籍/篇章 slug |
| `sourceBookArticle.article_id` | string | 文章 slug |
| `sourceBookArticle.book.book_id` | string | 古籍 slug |
| `sourceBookArticle.book.name` | string | 古籍名 |

**排序**：`mingjus.order ASC, mingjus.id ASC`。

**示例**

```bash
curl 'http://localhost/api/mingjus'
curl 'http://localhost/api/mingjus?author_id=dbzxvttxzu'
curl 'http://localhost/api/mingjus?tag_id=10&dynasty_id=6'
```

---

### `GET /api/mingjus/{mingju_id}` — 名句详情

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `mingju_id` | string | 是 | 名句 slug |

**响应**

```json
{
  "mingju_id": "mj-huidang",
  "name": "会当凌绝顶，一览众山小。",
  "favorited": false,
  "source": "望岳",
  "guishu": 1,
  "yiwen": "<p>……</p>",
  "zhushi": "<p>……</p>",
  "shangxi": "<p>……</p>",
  "author_name": "杜甫",
  "chaodai": "唐代",
  "dynasty": { "id": 6, "name": "唐" },
  "author": { "author_id": "dufu", "name": "杜甫" },
  "tags": [ { "id": 10, "name": "唐诗三百首" } ],
  "sourcePoem": {
    "poem_id": "wangyue",
    "name": "望岳",
    "content": "<p>……</p>",
    "author_name": "杜甫",
    "chaodai": "唐代",
    "author": { "author_id": "dufu", "name": "杜甫" },
    "dynasty": { "id": 6, "name": "唐" }
  },
  "sourceBookArticle": null
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `favorited` | bool | 当前用户是否已收藏该名句 |
| `yiwen` | string (HTML) \| null | 译文 |
| `zhushi` | string (HTML) \| null | 注释 |
| `shangxi` | string (HTML) \| null | 赏析 |
| `tags[]` | array | 关联合集 |
| `sourcePoem` | object \| null | 当 `guishu=1` 且能匹配到源诗时给出，结构与诗词简介一致 |
| `sourceBookArticle` | object \| null | 当 `guishu=2` 时给出 |

其他字段与列表接口一致。

**边界**

- `mingju_id` 未匹配 → `404` + `{"error": "mingju_not_found"}`

**示例**

```bash
curl 'http://localhost/api/mingjus/mj-huidang'
```

---

### `GET /api/authors` — 作者列表

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `dynasty_id` | int | 否 | 朝代 ID |
| `page` | int | 否 | 页码，默认 1，上限 50 |
| `limit` | int | 否 | 每页条数，1 ≤ limit ≤ 15（超出截断为 15） |

**响应**

```json
{
  "data": [
    {
      "author_id": "dufu",
      "name": "杜甫",
      "content": "杜甫（712—770），字子美……",
      "pic": "https://cdn.meirishici.com/author/dufu.jpg",
      "shiwen_num": 1457,
      "mingju_num": 35,
      "dynasty": { "id": 6, "name": "唐代" }
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "has_more": true
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `author_id` | string | 作者 slug |
| `name` | string | 姓名 |
| `content` | string \| null | 简介（短文本） |
| `pic` | string \| null | 头像 URL |
| `shiwen_num` | int | 诗文数量 |
| `mingju_num` | int | 名句数量 |
| `dynasty` | object \| null | 朝代 |

**排序**：`authors.order ASC, authors.id ASC`，只返回 `order < 999999` 的"主表"作者。

**示例**

```bash
curl 'http://localhost/api/authors'
curl 'http://localhost/api/authors?dynasty_id=6'
curl 'http://localhost/api/authors?dynasty_id=6&limit=5'
```

---

### `GET /api/authors/{author_id}` — 作者详情

**Path 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `author_id` | string | 是 | 作者 slug |

**响应**

```json
{
  "author_id": "dufu",
  "name": "杜甫",
  "content": "杜甫（712—770），字子美……",
  "shiwen_num": 1457,
  "mingju_num": 35,
  "pic": "https://cdn.meirishici.com/author/dufu.jpg",
  "dynasty": { "id": 6, "name": "唐" },
  "ziliaos": [
    { "id": 1, "name": "生平", "content": "<p>……</p>", "order": 1 }
  ]
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `author_id` | string | 作者 slug |
| `name` | string | 姓名 |
| `content` | string \| null | 简介（短文本，可能含若干句号） |
| `shiwen_num` | int | 诗文数量 |
| `mingju_num` | int | 名句数量 |
| `pic` | string \| null | 头像 URL |
| `dynasty` | object \| null | 朝代 |
| `ziliaos[]` | array | 资料段（生平/成就等），按 `order ASC` |
| `ziliaos[].id` | int | 内部 ID（前端 wx:key 用） |
| `ziliaos[].name` | string | 段落标题 |
| `ziliaos[].content` | string (HTML) | 段落内容 |
| `ziliaos[].order` | int | 排序 |

**关联数据**

作者下的诗词列表 → 调 `GET /api/poems?author_id={author_id}`
作者下的名句列表 → 调 `GET /api/mingjus?author_id={author_id}`

**边界**

- `author_id` 未匹配 → `404` + `{"error": "author_not_found"}`

**示例**

```bash
curl 'http://localhost/api/authors/dbzxvttxzu'
```

## 微信鉴权接口

详见 `docs/wx-mp-auth.md`。

### `POST /api/wx/login` — 小程序登录

仅需 `X-APPKEY`，请求体传 `code`。

**响应**

```json
{
  "token": "64hex",
  "sign_key": "64hex-api-sign-key",
  "expires_in": 7200,
  "id": 1,
  "name": "用户昵称",
  "avatar": "https://cdn.example.com/avatar.jpg"
}
```

`sign_key` 是后端随机生成的 API 签名密钥，用于后续 `X-WX-Sign`。微信 `code2Session` 返回的原始 `session_key` 不会返回给小程序。

### `PUT /api/wx/me` — 完善用户资料

只接受 `name` 和 `avatar` 字段。两个字段都可选，只更新传入字段。传入非空 `name` 时，后端会调用微信小程序 `msg_sec_check` 做文本内容安全校验；只有校验通过才会保存昵称。

**请求**

```json
{
  "name": "用户昵称",
  "avatar": "https://cdn.example.com/avatar.jpg"
}
```

**响应**

```json
{
  "id": 1,
  "name": "用户昵称",
  "avatar": "https://cdn.example.com/avatar.jpg"
}
```

传入其他字段返回 `400`：

```json
{
  "error": "invalid_fields"
}
```

昵称内容安全校验不通过返回 `422`：

```json
{
  "error": "nickname_content_risky",
  "suggest": "risky",
  "label": 100,
  "trace_id": "微信返回的 trace_id"
}
```

微信内容安全接口暂时不可用返回 `503`：

```json
{
  "error": "content_security_check_failed"
}
```

| 端点 | 方法 | 鉴权 |
|---|---|---|
| `/api/wx/login` | POST | 仅 `X-APPKEY` |
| `/api/wx/me` | GET | `wx.sign` 全套 |
| `/api/wx/me` | PUT | `wx.sign` 全套 |
| 其他业务端点 | * | `wx.sign` 全套 |

---

## 搜索接口

### `GET /api/search` — 跨类型搜索

按 `type` 分流：诗词、古籍文章走 ES（`poems_index` / `articles_index`），名句、作者走数据库 LIKE。

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `type` | string | 是 | `poem` / `article` / `mingju` / `author` |
| `q` | string | 是 | 搜索关键词，最长 64 字符（超出截断）；空串返回空 `data` |
| `page` | int | 否 | 页码，默认 1，上限 50 |

**响应**

每种 type 返回各自资源的列表项结构 + 统一分页字段：

```json
{
  "type": "poem",
  "data": [
    {
      "poem_id": "tb2yd7acup",
      "name": "桃花源记",
      "content": "<p>……</p>",
      "author_name": "陶渊明",
      "chaodai": "魏晋",
      "dynasty": { "id": 3, "name": "魏晋" },
      "author": { "author_id": "taoyuanming", "name": "陶渊明" },
      "highlight": {
        "name": ["桃花源记"],
        "content": ["忽逢<em>桃花</em>林，夹岸数百步"]
      }
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "has_more": true,
  "total": 1234
}
```

`type=article` 在 `page=1` 时额外返回 `books` 字段（按 `books.name LIKE %q%` 取最多 5 条，按 `order` 排序）：

```json
{
  "type": "article",
  "books": [
    {
      "book_id": "hlm",
      "name": "红楼梦",
      "class": "古籍",
      "type": "小说",
      "author_name": "曹雪芹",
      "chaodai": "清代",
      "author": { "author_id": "caoxueqin", "name": "曹雪芹" },
      "dynasty": { "id": 11, "name": "清代" }
    }
  ],
  "data": [ /* 文章列表 */ ],
  "current_page": 1,
  "per_page": 15,
  "has_more": true,
  "total": 320
}
```

| 字段 | 类型 | 说明 |
|---|---|---|
| `type` | string | 与请求 `type` 一致，前端用于路由匹配 |
| `data[]` | array | 对应资源的列表项;具体字段见下方按 type 拆分说明 |
| `books[]` | array | 仅 `type=article` + `page=1` 时返回:DB 搜书名直出整本书,放在文章列表上方 |
| `total` | int \| 缺省 | ES 类型（poem/article）返回精确总数（上限 10000，超过截断）；DB 类型（mingju/author）不返回 |

**各 type 的 `data[]` 结构**

- `type=poem` → 同 `/api/poems` 列表项（不含 `tags`），额外包含 `highlight`
- `type=article` → `{article_id, name, book: {book_id, name, author_name, chaodai, dynasty, author: {author_id, name} | null} | null, highlight}`
- `type=mingju` → `{mingju_id, name, source, author_name, chaodai, author: {author_id, name} | null}`
- `type=author` → 同 `/api/authors` 列表项（含 `content`/`pic`/`shiwen_num`/`mingju_num`/`dynasty`）

**高亮字段**

`highlight` 仅 ES 类型（`poem` / `article`）返回，类型为对象；没有命中的字段返回空对象 `{}`。高亮片段由 ES 生成，命中词使用 `<em>...</em>` 包裹，前端可只对该字段做受控 HTML 渲染，原始 `name` / `content` 不被覆盖。

| type | highlight 字段 | 说明 |
|---|---|---|
| `poem` | `name` | 诗词标题，整字段返回（`number_of_fragments=0`） |
| `poem` | `content` | 正文片段，最多 2 段，每段约 120 字符 |
| `article` | `book_name` | 古籍书名，整字段返回 |
| `article` | `article_name` | 篇章标题，整字段返回 |
| `article` | `content` | 正文片段，最多 2 段，每段约 160 字符 |

`type=article` 示例片段：

```json
{
  "article_id": "xxx",
  "name": "第一回",
  "book": { "book_id": "hlm", "name": "红楼梦" },
  "highlight": {
    "book_name": ["<em>红楼</em>梦"],
    "content": ["此开卷第一回也，作者自云..."]
  }
}
```

**评分策略（ES 类型）**

统一由 `App\Services\Search\EsQueryBuilder::build()` 生成。Web 端 `Web/PoemController::search` 与 API 端 `Api/SearchController` 共用同一套公式：

- `match_phrase`（整句命中）走 `constant_score`，权重远高于 `match`（分词命中）
- 查询中有空格或标点分隔的多段短语时，会额外奖励同一字段内命中全部短语片段的结果；单个短语片段也按长度加权，长片段命中优先于零散短词命中
- `match` 加 `minimum_should_match=75%` + `operator=and`，降低噪声
- 指定 `orderField` 时叠加高斯衰减（`origin=0, scale=5000, decay=0.5, weight=500`），将 `order` 越小的越靠前作为 popularity 信号
- 指定 `authorBoost` 时追加 `term: { author.keyword: q }` 子句，搜作者名时同名作品集中靠前

具体字段权重：

| type | 字段 | phrase boost | match boost |
|---|---|---|---|
| poem | name | 200 | 2 |
| poem | content | 90 | 5 |
| article | book_name | 100 | 8 |
| article | article_name | 60 | 4 |
| article | content | 90 | 5 |

附加项：
- poem 启用 `authorBoost=150`（搜"李白"时李白作品先出）+ `orderField=order`
- article 启用 `orderField=book_order`（《史记》《论语》等高优先级古籍的文章先出）

article 中 `book_name` 权重最高,加上 `books` 字段前置,搜"红楼"会先出红楼梦这本书,再出红楼梦的篇章。

**边界**

- `type` 不在白名单 → `400` + `{"error": "invalid_type"}`
- `q` 空 / `page > 50` → 空 `data` + `has_more=false`
- DB 类型 LIKE 使用 `%q%` 子串匹配，特殊字符 `\` / `%` / `_` 自动转义
- `books` 仅在 `type=article` + `page=1` 时存在;翻页后省略

**示例**

```bash
curl 'http://localhost/api/search?type=poem&q=明月'
curl 'http://localhost/api/search?type=poem&q=李白'      # author boost 生效
curl 'http://localhost/api/search?type=article&q=红楼'   # 顶部返回红楼梦
curl 'http://localhost/api/search?type=mingju&q=举头'
curl 'http://localhost/api/search?type=author&q=杜甫'
```

---
