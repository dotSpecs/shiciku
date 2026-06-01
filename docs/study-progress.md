# 学习进度后端设计

## 背景

学习进度依托现有专题体系实现，不新增独立课程内容表。

当前内容关系：

- `zhuantis.alias` 是学习路径标识，例如 `xiaoxue`、`chuzhong`、`gaozhong`。
- `zhuanti_chapters.sort` 决定章节顺序。
- `zhuanti_poems.order` 决定章节内诗词顺序。
- `poems.poem_id` 是小程序和 API 使用的诗词对外 ID。

后端负责保存用户在某个专题下对某首诗词的学习状态，并按专题顺序计算统计和下一首。

## 目标

- 支持小程序按专题展示学习进度。
- 支持用户进入详情后记录已读。
- 支持用户主动标记已学和取消已学。
- 支持按专题顺序返回“继续学习”的下一首。
- 统计口径稳定，避免前端重复计算导致各页面不一致。

## 不做范围

- 不支持脱离专题的全站学习进度。
- 不记录逐字、背诵、测验等细粒度学习行为。
- 不保存专题内容快照。专题内容调整后，进度按当前专题内容实时计算。
- 不在第一版实现学习历史时间线。

## 小程序端文档调整建议

小程序端现有设计方向可用，但建议调整以下点：

1. `started` 不需要用户手动点击。只要从带 `zhuanti_alias` 的学习入口进入诗词详情，详情页自动调用“记录阅读”接口；用户点击只用于“标为已学”。
2. 取消已学不建议回到 `todo`，默认回到 `started`。因为用户已经打开并读过详情，清掉 `learned_at` 即可，不应丢掉阅读事实。
3. “继续学习”必须以后端返回的 `next_poem_id` 为准。前端可以本地兜底，但不要自行定义另一套排序规则。
4. 学习进度页不需要把每次状态合并规则写死在页面里，应使用后端返回的 `chapters[].poems[].study` 字段直接渲染。
5. 诗词详情页只有在 URL 携带 `zhuanti_alias` 时才展示学习状态和学习操作；从搜索、收藏、作者页、分享等普通入口进入时不带 `zhuanti_alias`，不展示学习状态，也不调用学习进度接口。

## 状态模型

每条进度记录表示“某个用户在某个专题下的某首诗词状态”。

| 状态 | 含义 | 计入已学 | 计入读过 |
|---|---|---|---|
| `started` | 用户打开过详情，但未标记已学 | 否 | 是 |
| `learned` | 用户主动标记已学 | 是 | 是 |

没有进度记录表示 `todo`，不入库。

接口响应里会返回 `todo`，但数据库不保存 `todo`，避免为所有未学内容预建记录。

`started` 是低强度行为，只表示“打开并读过详情”，不计入学习完成进度，因此可以自动记录。`learned` 是高强度行为，必须由用户点击确认。

## 状态流转

```text
无记录(todo)
  ├─ 记录阅读 ─> started
  └─ 标为已学 ─> learned

started
  └─ 标为已学 ─> learned

learned
  ├─ 记录阅读 ─> learned
  └─ 取消已学 ─> started
```

字段更新规则：

- 记录阅读：`read_count + 1`，更新 `last_read_at`；无记录则创建 `started`。
- 标为已学：状态设为 `learned`，更新 `learned_at` 和 `last_read_at`；无记录则创建。
- 取消已学：状态设为 `started`，清空 `learned_at`，保留 `last_read_at` 和 `read_count`。

## 数据表

新增 `user_study_progress`。

```sql
CREATE TABLE user_study_progress (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  zhuanti_id BIGINT UNSIGNED NOT NULL,
  poem_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'started',
  read_count INT UNSIGNED NOT NULL DEFAULT 0,
  learned_at DATETIME NULL,
  last_read_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_user_zhuanti_poem (user_id, zhuanti_id, poem_id),
  KEY idx_user_zhuanti_status (user_id, zhuanti_id, status),
  KEY idx_user_zhuanti_last_read (user_id, zhuanti_id, last_read_at),
  CONSTRAINT chk_study_status CHECK (status IN ('started', 'learned'))
);
```

说明：

- 表内使用数据库 PK：`zhuanti_id`、`poem_id`，减少字符串索引成本。
- API 入参和响应仍使用 `alias`、`poem_id` slug。
- 是否属于专题必须通过 `zhuanti_poems` 校验，不能允许任意诗词写入任意专题。

建议模型：

- `App\Models\UserStudyProgress`
- 常量：`STATUS_STARTED`、`STATUS_LEARNED`
- 关系：`user()`、`zhuanti()`、`poem()`

## 进度计算

专题总数：

```text
total = 当前专题下 zhuanti_poems 数量
```

已学数：

```text
learned_count = 当前用户 + 当前专题 + 当前专题有效诗词中 status=learned 的数量
```

读过数：

```text
started_count = 当前用户 + 当前专题 + 当前专题有效诗词中有记录的数量
```

百分比：

```text
percent = total > 0 ? floor(learned_count / total * 100) : 0
```

下一首：

- 按章节 `sort` 升序、章节内 `zhuanti_poems.order` 升序展开。
- 找到第一首非 `learned` 的诗词。
- 全部已学时返回 `null`。

最近阅读：

- `last_poem_id` 取当前专题下 `last_read_at` 最新的有效记录。
- 仅作展示或“最近阅读”入口，不替代 `next_poem_id`。

## 接口设计

所有接口均在 `wx.sign` 中间件内，需要登录用户。

### 获取专题学习进度

```http
GET /api/study-progress/{alias}
```

用于学习进度页，也可用于专题页合并展示。

响应：

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
          "study": {
            "status": "learned",
            "read_count": 2,
            "learned_at": "2026-05-29 12:00:00",
            "last_read_at": "2026-05-29 12:00:00"
          }
        },
        {
          "poem_id": "chunxiao",
          "name": "春晓",
          "author_name": "孟浩然",
          "chaodai": "唐代",
          "study": {
            "status": "todo",
            "read_count": 0,
            "learned_at": null,
            "last_read_at": null
          }
        }
      ]
    }
  ]
}
```

错误：

- 专题不存在：`404 {"error":"zhuanti_not_found"}`

### 获取单首在专题内的学习状态

```http
GET /api/study-progress/{alias}/poems/{poem_id}
```

用于诗词详情页在“有 `zhuanti_alias` 但没有状态缓存”时获取按钮状态。详情页如果已经从学习进度页或专题页拿到 `study` 数据，可以直接使用传入数据，不需要重复调用。

该接口不提供“无专题上下文”的查询能力。学习状态绑定在“用户 + 专题 + 诗词”上，同一首诗可能出现在多个专题中；没有 `alias` 时无法判断要读取哪条进度。

响应：

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

错误：

- 专题不存在：`404 {"error":"zhuanti_not_found"}`
- 诗词不存在或不属于该专题：`404 {"error":"study_target_not_found"}`

### 记录阅读

```http
POST /api/study-progress/{alias}/poems/{poem_id}/read
```

用于带 `zhuanti_alias` 的详情页打开后自动调用。该接口不把内容计入已学，不需要用户手动点击。

响应：

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

幂等性说明：

- 该接口每次调用都会增加 `read_count`，所以不是严格幂等。
- 前端应在详情页一次进入周期内只调用一次。
- 如果担心误触进入详情导致读过数虚高，可以在前端加轻量触发阈值，例如停留 3-5 秒后调用、滚动到正文区域后调用，或页面 `onShow` 后 debounce 一次。

### 更新学习状态

```http
PUT /api/study-progress/{alias}/poems/{poem_id}
```

请求：

```json
{
  "status": "learned"
}
```

取消已学：

```json
{
  "status": "started"
}
```

响应：

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

错误：

- `status` 非法：`422`
- 专题不存在：`404 {"error":"zhuanti_not_found"}`
- 诗词不存在或不属于该专题：`404 {"error":"study_target_not_found"}`

## 路由

建议新增：

```php
Route::get('/study-progress/{alias}', [StudyProgressController::class, 'show']);
Route::get('/study-progress/{alias}/poems/{poem_id}', [StudyProgressController::class, 'status']);
Route::post('/study-progress/{alias}/poems/{poem_id}/read', [StudyProgressController::class, 'read']);
Route::put('/study-progress/{alias}/poems/{poem_id}', [StudyProgressController::class, 'update']);
```

放在现有 `Route::middleware('wx.sign')->group(...)` 内。

## 服务层设计

建议新增 `App\Services\StudyProgressService`，控制器只负责参数校验和响应转换。

核心方法：

- `getOverview(User $user, string $alias): array`
- `getStatus(User $user, string $alias, string $poemId): array`
- `recordRead(User $user, string $alias, string $poemId): UserStudyProgress`
- `setStatus(User $user, string $alias, string $poemId, string $status): UserStudyProgress`

内部公共逻辑：

- `resolveZhuanti($alias)`
- `resolveTarget($zhuanti, $poemId)`：返回专题关联行和诗词。
- `orderedPoems($zhuanti)`：统一专题顺序。
- `defaultStudy()`：把无记录转换为响应里的 `todo`。

## 并发与一致性

- 写入使用 `updateOrCreate` 或数据库唯一键兜底。
- 标记已学和取消已学使用事务，避免并发时 `learned_at` 和 `status` 不一致。
- `read_count` 使用原子自增。
- 统计接口只读取当前专题有效内容，专题移除的诗词不会再计入统计，但历史进度记录保留。

## 首页摘要

首页后续可以在 `/api/home` 中增加学习摘要，减少小程序首页多次请求。

建议结构：

```json
{
  "study_progress": [
    {
      "alias": "xiaoxue",
      "name": "小学古诗",
      "total": 75,
      "learned_count": 12,
      "percent": 16,
      "next_poem_id": "chunxiao"
    }
  ]
}
```

第一版可以只返回固定专题：`xiaoxue`、`chuzhong`、`gaozhong`。如果后续专题增多，再加配置字段控制是否展示在首页。

## 前端接入约定

学习进度页：

- 调用 `GET /api/study-progress/{alias}`。
- 用 `chapters[].poems[].study.status` 渲染状态。
- “继续学习”使用 `next_poem_id`。
- “只看未学”筛选 `status !== 'learned'`。

专题页：

- 可复用 `GET /api/study-progress/{alias}` 替代单独专题详情接口，或保留现有专题接口后再请求进度。
- 如果页面需要展示完整进度，优先使用进度接口，避免前端二次合并。

诗词详情页：

- URL 携带 `zhuanti_alias` 时展示学习操作。
- URL 不携带 `zhuanti_alias` 时按普通诗词详情处理，不展示学习状态，不调用学习进度接口。
- 从学习进度页或专题页进入详情时，优先把当前诗词的 `study` 数据随页面跳转传入详情页，用于首屏渲染按钮状态。
- 只有在携带 `zhuanti_alias` 但没有传入 `study` 数据时，才调用 `GET /api/study-progress/{alias}/poems/{poem_id}` 兜底获取状态。
- 页面进入后自动调用 `POST /api/study-progress/{alias}/poems/{poem_id}/read`，不需要用户点击“已读”。
- 可选优化：停留 3-5 秒或滚动到正文后再调用 `/read`，避免快速误入详情时增加 `read_count`。
- 点击“标为已学”调用 `PUT`，`status=learned`。
- 点击“已学”取消时调用 `PUT`，`status=started`。

## 实现顺序

1. 新增 migration 和 `UserStudyProgress` 模型。
2. 新增 `StudyProgressService`，实现专题内容解析、状态写入和进度聚合。
3. 新增 `StudyProgressController` 和路由。
4. 更新 `docs/api.md`，把学习进度接口加入正式 API 文档。
5. 为服务层补充单元测试或功能测试：
   - 无进度时全部 `todo`。
   - 记录阅读生成 `started`。
   - 标记已学计入 `learned_count`。
   - 取消已学回到 `started` 并清空 `learned_at`。
   - `next_poem_id` 返回第一个未学内容。
