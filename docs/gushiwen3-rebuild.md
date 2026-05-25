# gushiwen3 数据库重建：完整字段抓取

## Context

现有 `gushiwen2` 数据库存在两个问题：(1) 数据陈旧（多数 2019/2023 抓取）；(2) 字段缺失，新接口的 `fanyiList / shangxiList / ziliaoList / 拼音 / 章节附录` 等都没存。本次目标：

1. 切换到 **gushiwen3** 全新数据库，按 `app24.guwendao.net` 当前接口的全部字段重建表；
2. 不再使用通用 `metadatas` 表，每种附录信息按接口字段独立建表，**所有源 id 都作为本站主键**；
3. 实现完整的 Fetcher 服务和命令骨架，**本次不主动批量抓取**，仅做端到端单点验证；
4. **slug 字段沿用旧约定**（`poems.poem_id` / `authors.author_id` / `books.book_id` / `book_articles.article_id` / `mingjus.mingju_id`），抓取时留空，最后用 `fill:slugs` 命令从 gushiwen2 复用旧值，缺失再随机生成 —— 保护已经被外部索引的旧 URL；
5. **图书结构沿用旧三表** `books / book_chapters / book_articles`：
   - `bookInfo.zhangjieList[]` → `book_chapters`（卷/分组）
   - `bookInfo.zhangjieList[].zhangjieChilds[]` → `book_articles`（每篇 id/idStr/nameStr/yiyi）
   - `zhangjieChilds.idStr` 即 `bookViewInfo.aspx?idStr=` 参数，用来抓正文 + 4 类附录

---

## 数据库切换

- 新建 MySQL 数据库：`CREATE DATABASE gushiwen3 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- 修改 `.env`：`DB_DATABASE=gushiwen3`（`gushiwen2` 物理保留作参考）
- `config/database.php` 增加副连接 `legacy`，硬编码 `database => 'gushiwen2'`；仅供 `fill:slugs` 命令读旧 slug
- 现有 `database/migrations/2026_05_15_000001~000003_*` zhuanti 三表迁移**保留并复用**
- `app/Models/Metadata.php` 删除；其他模型按新结构重写；旧 `metadatas()` 关系全部移除
- 现有 `app/Services/PoemFetcher.php` 删除（被新版 `App\Services\Guwendao\PoemFetcher` 取代）

---

## 表设计（snake_case；源 id 直接做 PK；slug 字段建表时 NULL）

### 公共
- **dynasties** — `id`(自增 PK), `name` UNIQUE
- **tags** — `id`(自增 PK), `name` UNIQUE

### 作者
**authors** （非自增，源 id 即 PK）
```
id, id_str UNIQUE, id_check,
author_id UNIQUE NULL,             -- 本站 slug,fill:slugs 填
name, dynasty_id NULL,
content (longtext), pic_small, pic_big,
shiwen_num, mingju_num,
langsong_url, zimu_api,
up_time, up_time_span (bigint),
timestamps
```

**author_ziliaos** （`authorInfo.ziliaoList`，源 id 即 PK）
```
id, author_id INDEX,               -- 外键 → authors.id (与 authors.author_id 同名但语义不同)
name, author (作者签名), content (longtext), cankao,
langsong_url, zimu_api, up_time, up_time_span,
`order`, timestamps
```

### 诗词
**poems**
```
id, id_str UNIQUE, id_check,
poem_id UNIQUE NULL,               -- slug,fill:slugs 填
name, name_py,
author_id NULL INDEX,              -- 外键 → authors.id
author_pic_url,
dynasty_id NULL INDEX,
content (longtext), content_py (longtext),
author_py, chaodai_py,
type, bieming, yzsy,
langsong_author, langsong_url, zimu_api,
yizhu_content (longtext), yizhu_author, yizhu_cankao,
up_time, up_time_span,
timestamps
```
> `*_py` 来自 `shiwenYZSY.shiwen.*Py`；`yizhu_*` 来自 `shiwenYZSY.yizhu`（object，无 id 直接平铺）。

**poem_fanyis** （`shiwenInfo.fanyiList`）
```
id, poem_id INDEX,                 -- 外键 → poems.id
name, author, content (longtext), cankao,
langsong_url, zimu_api, up_time, up_time_span,
`order`, timestamps
```

**poem_shangxis** （`shiwenInfo.shangxiList` + `shiwenYZSY.shangxi` 按 id 合并）
- 字段同 `poem_fanyis`

### 名句
**mingjus**
```
id, id_str UNIQUE, id_check,
mingju_id UNIQUE NULL,             -- slug
name (text - 名句正文较长),
author_id NULL INDEX,
dynasty_id NULL INDEX,
source, source_id_str,
source_poem_id NULL INDEX,         -- 出处诗词在本站的 id
yiwen (longtext), shangxi (longtext), zhushi (longtext),
guishu (tinyint),
pic_name, pic_author, pic_cangguan, pic_chaodai, pic_url,
up_time, up_time_span,
timestamps
```

### 图书（沿用旧三表）
**books**
```
id, id_str UNIQUE, id_check,
book_id UNIQUE NULL,               -- slug
name, author_id NULL INDEX, dynasty_id NULL INDEX,
content (longtext), bieming,
fenlei, class, type,           -- "道经,德经" / "子部" / "道家类"
mingju_num, big_pic_url, banner_pic_url,
langsong_url, zimu_api,
up_time, up_time_span,
timestamps
```

**book_chapters** （`bookInfo.zhangjieList[]`，自增 id；接口本身没给 chapter id）
```
id (auto PK), book_id INDEX,
parent_name, `order`,
timestamps
```

**book_articles** （`bookInfo.zhangjieList[].zhangjieChilds[]`，源 id 即 PK；正文来自 `bookViewInfo`）
```
id, id_str UNIQUE,
article_id UNIQUE NULL,            -- slug
book_id INDEX, chapter_id INDEX,
num (int),                         -- bookViewInfo.num
name, author, content (longtext),
fenlei, yiyi (bool),
langsong_url, zimu_api,
up_time, up_time_span,
`order`, timestamps
```
> 抓取时分两步：`BookFetcher` 抓 `bookInfo` 写 `books` + `book_chapters` + `book_articles` 骨架（id/id_str/name/yiyi/order）；`BookArticleFetcher` 按 idStr 抓 `bookViewInfo` 补全正文 + 附录。

**book_article_supplements** （合并 `bookViewInfo` 的 `fanyi/zhushi/duanshang/shangxi`）
```
id, article_id INDEX,              -- 外键 → book_articles.id
category ENUM('fanyi','zhushi','duanshang','shangxi'),
name, author, content (longtext), cankao,
is_duanyi (bool, 仅 fanyi 用),
langsong_url, zimu_api,
up_time, up_time_span,
timestamps
```

### 标签关联（多对多，复合 UNIQUE）
- **poem_tag**: `poem_id`, `tag_id`
- **mingju_tag**: `mingju_id`, `tag_id`
- **book_tag**: `book_id`, `tag_id`

### 专题（保留现有三表）
`zhuantis / zhuanti_chapters / zhuanti_poems` 已建好，新数据库下直接 `migrate` 即可。

---

## 文件清单

### 新增迁移（`database/migrations/2026_05_15_010000~`）
1. dynasties / tags
2. authors / author_ziliaos
3. poems / poem_fanyis / poem_shangxis
4. mingjus
5. books / book_chapters / book_articles / book_article_supplements
6. poem_tag / mingju_tag / book_tag

### 模型重写（`app/Models/`）
- 新建：`Dynasty / Tag / Author / AuthorZiliao / Poem / PoemFanyi / PoemShangxi / Mingju / Book / BookChapter / BookArticle / BookArticleSupplement`
- 保留：`Zhuanti / ZhuantiChapter / ZhuantiPoem`
- 删除：`Metadata`、旧 `Quote`（确认未在 routes/views 使用）

### 服务（`app/Services/Guwendao/`）
- `HttpClient.php` — 封装 `generateTokenUrl + http get + json + retry + 限速 300ms`
- `DynastyResolver.php` — `for(string $name): Dynasty`（firstOrCreate）
- `TagResolver.php` — `forString('a|b|c'): Collection<Tag>`
- `AuthorFetcher.php` — `ensure($id, $idStr): ?Author`，含 ziliaoList
- `PoemFetcher.php` — `ensure($id, $idStr)` / `ensureByIdStr($idStr)` —— 串调 shiwenInfo + YZSY，写 poem + fanyis + shangxis + tags + author
- `MingjuFetcher.php` — `ensure($id, $idStr)`，串调 author + source poem
- `BookFetcher.php` — `ensure($id, $idStr)`，写 book + chapters + articles 骨架
- `BookArticleFetcher.php` — `ensure($id, $idStr)`，抓 bookViewInfo 补正文 + supplements

### 命令（`app/Console/Commands/Fetch/`）
- `FetchAuthor.php` — `fetch:author {--id=} {--id-str=} {--page=} {--all}`
- `FetchPoem.php` — `fetch:poem {--id=} {--id-str=} {--page=} {--tag=} {--all}`
- `FetchMingju.php` — `fetch:mingju {--id=} {--id-str=} {--page=} {--tag=} {--all}`
- `FetchBook.php` — `fetch:book {--id=} {--id-str=} {--all} {--articles}`（`--articles` 抓所有章节正文）
- `FetchZhuanTi.php` — 改写：使用 `App\Services\Guwendao\PoemFetcher`

### Slug 回填命令（`app/Console/Commands/Slug/`，最后单跑）
- `FillSlugs.php` — `fill:slugs {table?}`
  - 对 `authors / poems / mingjus / books / book_articles` 每张表：
    1. 用 `legacy` 连接读旧表对应 slug 字段（按源 `id` 匹配），复制到新库
    2. 剩余 NULL 的，`strtolower(Str::random(10))` 生成，确保唯一
    3. 进度日志：`updated_from_legacy / generated / skipped`

### 配置/Helper
- `app/Helpers/helpers.php` 现有 `generateTokenUrl / poem_slug / name2slug` 全部保留，无修改
- `routes/web.php`、`resources/views/...` 中引用旧字段的地方本次**不动**（slug 字段名一致，新数据填充后视图层应能继续工作）

---

## Fetcher 关键逻辑

### `HttpClient::get(string $endpoint, array $params = []): array`
- 拼参数 → `generateTokenUrl` → `file_get_contents` (30s 超时)
- 解析 JSON，断言 `code === 200`，取 `result`
- 每次请求后 `usleep(300_000)` 限速
- 失败 throw `RuntimeException`

### `AuthorFetcher::ensure($id, $idStr)`
1. `Author::find($id)` 命中直接返回
2. 抓 `author/authorInfo.aspx?idStr=...`
3. `dynasty_id = DynastyResolver::for($author.chaodai)`
4. `Author` 字段全量映射（`author_id` 留空）；保存
5. 遍历 `ziliaoList` → `AuthorZiliao::updateOrCreate(['id'=>...], [...])`，order=下标
6. 返回

### `PoemFetcher::ensure($id, $idStr)` / `ensureByIdStr($idStr)`
1. `Poem::find($id)` 或 `where('id_str', $idStr)` 命中直接返回
2. 抓 `shiwenInfo` → 解析 shiwen + author
3. `AuthorFetcher::ensure($author.id, $author.idStr)`
4. 抓 `shiwenYZSY?idStr=...&shang=true` → 取 `*Py / yizhu / shangxi`
5. 写 `Poem`：合并 shiwenInfo + YZSY 字段
6. `fanyiList` → `PoemFanyi::updateOrCreate`
7. 合并 `shiwenInfo.shangxiList` + `YZSY.shangxi`（按 id 去重）→ `PoemShangxi::updateOrCreate`
8. `tag` → `TagResolver::forString` → `$poem->tags()->sync(...)`

### `MingjuFetcher::ensure($id, $idStr)`
- 抓 `mingjuInfo`，串联 author + source poem（`PoemFetcher::ensureByIdStr($mingju.sourceIdStr)`）
- 写 `Mingju`，`source_poem_id` 填 PoemFetcher 返回的 id

### `BookFetcher::ensure($id, $idStr)`
- 抓 `bookInfo`，串联 author
- 写 `Book` + 遍历 `zhangjieList`：
  - 每个 parent → `BookChapter::updateOrCreate(['book_id'=>..., 'parent_name'=>...], ['order'=>...])`
  - 每个 `zhangjieChilds[]` → `BookArticle::updateOrCreate(['id'=>...], ['id_str'=>..., 'book_id'=>..., 'chapter_id'=>..., 'name'=>$child.nameStr, 'yiyi'=>$child.yiyi, 'order'=>...])` —— **正文留空，等 BookArticleFetcher 填**

### `BookArticleFetcher::ensure($id, $idStr)`
- 命中已有 `content` 非空则直接返回
- 抓 `bookViewInfo?idStr=...` → `bookZhangjie`
- 更新 `BookArticle`: `num / author / content / fenlei / langsong_url / zimu_api / up_time*`
- 4 个 supplement object 各检查存在性，按 id `BookArticleSupplement::updateOrCreate(['id'=>...], ['category'=>..., ...])`

---

## Verification

```bash
# 0. 创建数据库
mysql -uroot -p -e "CREATE DATABASE gushiwen3 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 1. 切 .env 后跑迁移
php artisan migrate

# 2. 单点端到端验证
php artisan fetch:author --id-str=b90660e3e492            # 李白 + ziliaoList(5 项)
php artisan fetch:poem   --id-str=144f86c44d85            # 慧庆寺玉兰记: fanyi/shangxi/拼音/yizhu/tag
php artisan fetch:mingju --id-str=ed5bfb31d014            # 名句 + source poem 自动入库
php artisan fetch:book   --id-str=db8fe8b5a11f --articles # 老子 + 2 卷 + 81 文章 + 第一章正文+附录

# 3. tinker 抽查
php artisan tinker
> Author::find(247)->ziliaos->count();                       // 5
> $p = Poem::find(1013496); echo $p->name_py;                // 拼音版标题
> $p->fanyis->count(); $p->shangxis->count();                // 1, 3+
> $p->tags->pluck('name');                                   // [咏物, 感慨, 哲理]
> Book::find(28)->chapters->count();                         // 2
> Book::find(28)->articles->count();                         // 81
> BookArticle::find(3310)->supplements->groupBy('category'); // fanyi/zhushi/duanshang/shangxi
> Mingju::find(25559)->sourcePoem->name;                     // 出处诗词

# 4. slug 回填（gushiwen2 还在,可读)
php artisan fill:slugs                                       # 全表
> Author::find(247)->author_id;                              // 旧库李白的 author_id 应被复用
> Poem::find(1013496)->poem_id;                              // 同上;旧库无的随机生成
```

> 用户已确认"仅表+脚本，不跑批量"——`--all / --page` 自动翻页留作后续手动触发，本次不在 CI 里启动。
