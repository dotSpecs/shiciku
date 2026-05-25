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
- `page` 上限 50，超出返回空 `data` + `has_more=false`
- 无 `total` / `last_page`（避免大表 COUNT 性能开销）

### 错误

非鉴权类错误统一返回 `200` + 空 `data`（如 `author_id` 不存在）。鉴权失败返回 `401` + `{"error": "<code>"}`。资源详情未找到返回 `404` + `{"error": "<code>"}`。

---

## 内容接口

### `GET /api/poems` — 诗词列表

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `tag_id` | int | 否 | 合集 ID（Tag PK） |
| `dynasty_id` | int | 否 | 朝代 ID |
| `author_id` | string | 否 | 作者 slug |
| `page` | int | 否 | 页码，默认 1，上限 50 |

**响应**

```json
{
  "data": [
    {
      "poem_id": "tb2yd7acup",
      "name": "桃花源记",
      "content": "<p>……</p>",
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
  "content": "<p>……</p>",
  "yizhu_content": "<p>译注……</p>",
  "dynasty": { "id": 3, "name": "魏晋" },
  "author": { "author_id": "pchx3eyuar", "name": "陶渊明" },
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
    { "mingju_id": "abc123", "name": "黄发垂髫，并怡然自乐。" }
  ]
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `poem_id` | string | slug |
| `name` | string | 诗词标题 |
| `content` | string (HTML) | 正文 |
| `yizhu_content` | string \| null | 译注正文（HTML） |
| `dynasty` | object \| null | 朝代 |
| `author` | object \| null | 作者，缺失为 `null` |
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

**边界**

- `poem_id` 未匹配 → `404` + `{"error": "poem_not_found"}`

**示例**

```bash
curl 'http://localhost/api/poems/0007dfjviw'
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
  "content": "<p>……</p>",
  "class": "经部",
  "type": "四书类",
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
  "content": "<p>……</p>",
  "chapter": { "id": 6001, "name": "学而第一" },
  "book": {
    "book_id": "lunyu",
    "name": "论语",
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
| `content` | string (HTML) | 正文 |
| `chapter` | object \| null | 所属章节 |
| `chapter.id` | int | 章节内部 ID |
| `book` | object | 所属古籍简介 |
| `book.book_id` | string | 古籍 slug |
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

## 微信鉴权接口

详见 `docs/wx-mp-auth.md`。

| 端点 | 方法 | 鉴权 |
|---|---|---|
| `/api/wx/login` | POST | 仅 `X-APPKEY` |
| `/api/wx/me` | GET | `wx.sign` 全套 |
| 其他业务端点 | * | `wx.sign` 全套 |
