# guwendao API 接口参考

数据源：`https://app24.guwendao.net/router/...`，所有请求必须先用 `app/Helpers/helpers.php:generateTokenUrl()` 生成带 `token=` 的完整 URL，否则会被服务端拒绝。

## 诗词 (shiwen)

- **列表**: `shiwen/shiwenList2409.aspx?page=1`
  - 可选过滤：`tag=咏物` `type=词`（诗/词/曲/文）`chaodai=隋代` `author=王维`
  - 返回顺序即热度排序，下标=rank
- **详情**: `shiwen/shiwenInfo.aspx?idStr=144f86c44d85`
  - 返回 `result.shiwen`（内含 `fanyiList[]`+`shangxiList[]`） + `result.author`
- **译注赏音**: `shiwen/shiwenYZSY.aspx?idStr=144f86c44d85&shang=true`
  - `result.shiwen` 包含拼音字段 `nameStrPy/contentTxtPy/authorPy/chaodaiPy`
  - `yizhu`（flat 对象：`contentTxt/author/cankao`）
  - `shangxi`（有 id 的对象）

## 名句 (mingju)

- **列表**: `mingju/mingjuList.aspx?page=1`
  - 可选过滤：`tag=冬天` `author=苏轼` `chaodai=两汉` `guishu=诗文`
  - 注意：list 接口的 `guishu` 参数是文本，但返回值是 tinyint 1-4
- **详情**: `mingju/mingjuInfo.aspx?idStr=ed5bfb31d014`
- **guishu 字段映射**：1=诗文, 2=古籍, 3=谚语, 4=对联（model 里有 `Mingju::GUISHU_*` 常量）

## 作者 (author)

- **列表**: `author/authorList.aspx?chaodai=&page=1`
- **详情**: `author/authorInfo.aspx?idStr=b90660e3e492`
  - 返回 `result.author` 内含 `ziliaoList[]`

## 专题 / 图书

- **特殊专题**: `teshuZhuanti.aspx?title=诗经`
  - title 中文，`generateTokenUrl` 会自动 urlencode
- **图书列表**: `book/bookList.aspx?page=1`
- **图书 info**: `book/bookInfo.aspx?idStr=db8fe8b5a11f`
  - 返回 `result.book` 内含 `zhangjieList[].zhangjieChilds[]`
  - 章节 `parentName` 允许空字符串
- **图书章节**: `book/bookViewInfo.aspx?idStr=...`
  - 返回 `result.bookZhangjie` 顶层即文章正文 + 四类附录 `fanyi/zhushi/duanshang/shangxi`

## 响应包装的实际位置（早期文档容易写错）

- `result.shiwen.fanyiList/shangxiList`（不在 `shiwenInfo` 包装下）
- `result.shiwen.yizhu/shangxi` 在 YZSY 接口里嵌在 `result.shiwen` 内
- `result.book.zhangjieList`（不在 `bookInfo` 包装下）
- `result.bookZhangjie.{fanyi,zhushi,duanshang,shangxi}`（不需要再钻 `zhangjie` 一层）

## 字段对照（已确认）

| 接口字段 | 本站字段 |
| --- | --- |
| `shiwen.id` | `poems.id` |
| `shiwen.idStr` | `poems.id_str` |
| `author.id` | `authors.id` |
| `author.idStr` | `authors.id_str` |
| `book.id` | `books.id` |
| `book.idStr` | `books.id_str` |
| `chaodai`（字符串） | `dynasties.name`（异常用 `firstOrCreate` 兜底） |

## 排序约定（gushiwen3 库）

- `authors / poems / mingjus / books.order` 默认 **999999**（哨兵），抓 list 时按 `(page-1)*limit+idx` 写入
- **只有不带过滤的全局列表才写主表 `.order`**，过滤列表不覆盖
- `poem_tag.order / mingju_tag.order` 默认 999999，**仅在 tag 过滤抓取时**写入对应 pivot
- 排序统一 `ORDER BY order ASC` —— rank 越小越靠前

## 推荐抓取顺序

1. `fetch:author --all` — 预热作者
2. `fetch:poem --all` — 全局 `poems.order`，级联补 author
3. `fetch:mingju --all` — 全局 `mingjus.order`，级联补出处 poem
4. `fetch:book --all` — 全局 `books.order` + 章节骨架（**先不要加 `--articles`**）
5. `fetch:zhuanti` — 14 个固定专题，把 zhuanti↔poem 映射建好
6. 按需 tag 维度：`fetch:poem --tag=X --all` / `fetch:mingju --tag=X --all` 写 pivot order
7. 逐 `fetch:book --id-str=... --articles` 抓所有图书章节正文+附录（最重，分批）
8. `fill:slugs` — 全部数据到位后一次性回填 slug
