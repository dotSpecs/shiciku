# 诗词闯关题库与无缓存答题方案

## 目标

把闯关题目预先生成到数据库，接口只负责抽题和实例化。本方案不再依赖 challenge cache 保存答案，而是用服务端签名的 `instance_token` 固化本次随机结果。

核心原则：

- 题库表保存稳定的题目模板。
- 接口返回前才生成本次展示实例。
- 所有随机结果都写入 `instance_token`。
- 提交时只信任题库和 token，不信任客户端提交的题面或标准答案。
- 错题表保存最近一次错误实例，后续同题再错时覆盖上次实例。

## 数据表

### dictation_questions

题库模板表。每条记录是一道稳定题目模板，不一定等于一次展示给用户的题面。

```sql
CREATE TABLE dictation_questions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  poem_id BIGINT UNSIGNED NOT NULL,
  zhuanti_id BIGINT UNSIGNED NULL,
  chapter_id BIGINT UNSIGNED NULL,
  grade_name VARCHAR(64) NOT NULL,
  question_type VARCHAR(32) NOT NULL,
  prompt TEXT NOT NULL,
  answer TEXT NULL,
  accepted_answers TEXT NULL,
  options TEXT NULL,
  metadata TEXT NULL,
  source_key VARCHAR(191) NOT NULL,
  source_hash CHAR(40) NOT NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1,
  generated_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_dict_question_source (source_key),
  KEY idx_dict_question_grade_type (grade_name, question_type, status),
  KEY idx_dict_question_poem_type (poem_id, question_type)
);
```

字段说明：

| 字段 | 说明 |
|---|---|
| `prompt` | 模板题面。填空题存完整句子，排序题存基础提示。 |
| `answer` | 模板答案。填空题可存完整句子，选择题存正确选项文本。 |
| `accepted_answers` | 模板可接受答案集合。填空题存完整句子的异文集合，实例化后按挖空位置切出答案。 |
| `options` | 选择题选项集合，入库顺序不作为展示顺序。 |
| `metadata` | 题型扩展数据，例如句子序号、异文、排序题句子列表。 |
| `source_key` | 稳定来源 key，用于脚本幂等 upsert。 |
| `source_hash` | 源内容 hash，用于判断题目内容是否变化。 |
| `status` | `1` 启用 / `0` 停用。 |

### dictation_attempt_items

历史闯关详情不需要还原，因此答题明细表精简为结果记录。

```sql
CREATE TABLE dictation_attempt_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NULL,
  user_answer TEXT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  sort INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY idx_dict_attempt_item_sort (attempt_id, sort),
  KEY idx_dict_attempt_item_question (question_id)
);
```

### dictation_wrong_items

错题按 `user_id + question_id` 在应用层查询并覆盖。数据库不加唯一约束。

```sql
CREATE TABLE dictation_wrong_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NULL,
  first_attempt_item_id BIGINT UNSIGNED NULL,
  last_attempt_item_id BIGINT UNSIGNED NULL,
  poem_id BIGINT UNSIGNED NOT NULL,
  grade_name VARCHAR(64) NOT NULL,
  zhuanti_id BIGINT UNSIGNED NULL,
  chapter_id BIGINT UNSIGNED NULL,
  question_type VARCHAR(32) NOT NULL,
  prompt TEXT NOT NULL,
  answer TEXT NOT NULL,
  accepted_answers TEXT NULL,
  options TEXT NULL,
  last_user_answer TEXT NULL,
  instance_metadata TEXT NULL,
  wrong_count INT UNSIGNED NOT NULL DEFAULT 1,
  reviewed_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_wrong_at DATETIME NULL,
  last_reviewed_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY idx_dict_wrong_user_resolved (user_id, resolved_at),
  KEY idx_dict_wrong_user_grade (user_id, grade_name),
  KEY idx_dict_wrong_question (question_id)
);
```

## 题型模板

### blank

题库模板：

- `prompt`: 完整句子，例如 `床前明月光`
- `answer`: 完整句子
- `accepted_answers`: 完整句子的异文集合
- `metadata`: `sentence_index`、`variants`

接口返回前随机挖空：

- 生成 `positions`
- 生成展示题面 `床__月光`
- 根据 `positions` 从完整句子切出标准答案
- 根据 `positions` 从异文句子切出可接受答案
- token 保存 `positions`

### next / previous

稳定题型，直接存题面和答案。

- `next`: `prompt = 上句`，`answer = 下句`
- `previous`: `prompt = 下句`，`answer = 上句`
- `accepted_answers` 保存答案句异文集合

不需要 token。

### author_choice

稳定模板：

- `prompt = 《诗名》的作者是？`
- `answer = 作者`
- `options = 作者选项集合`

接口返回前打乱 `options`，token 保存本次选项顺序。

### annotation_meaning

脚本预生成。AI 只在脚本中调用，不在接口请求时调用。

- `prompt = 诗句「...」中「词」的意思是？`
- `answer = 正确释义`
- `options = 正确释义 + 干扰项`
- `metadata = word / sentence`

接口返回前打乱 `options`，token 保存本次选项顺序。

### poem_source

可为同一首诗的多个诗句生成多条题目。

- `prompt = 「诗句」出自哪首诗？`
- `answer = 诗名`
- `options = 诗名选项集合`

接口返回前打乱 `options`，token 保存本次选项顺序。

### sentence_order

题库模板保存原始顺序的连续诗句。

- `prompt = 将下列诗句按正确顺序排列`
- `metadata.sentences = 原始顺序句子列表`

接口返回前：

- 打乱句子标签 A/B/C/D
- 计算本次正确顺序，例如 `C-A-D-B`
- 生成选项并打乱
- token 保存标签映射和本次选项顺序

## instance_token

使用 Laravel `Crypt` 加密签名 JSON。token 可解密表示未被篡改。

通用字段：

```json
{
  "qid": 123,
  "type": "blank"
}
```

填空题：

```json
{
  "qid": 123,
  "type": "blank",
  "positions": [1, 2]
}
```

选择题：

```json
{
  "qid": 124,
  "type": "author_choice",
  "options": ["孟浩然", "李白", "杜甫", "王维"]
}
```

排序题：

```json
{
  "qid": 125,
  "type": "sentence_order",
  "labels": {
    "0": "C",
    "1": "A",
    "2": "D",
    "3": "B"
  },
  "options": ["A-B-C-D", "C-A-D-B", "B-A-D-C", "D-C-A-B"]
}
```

## 接口流程

### 获取 challenge

1. 校验年级册。
2. 从 `dictation_questions` 按 `grade_name + question_type + status` 抽题。
3. 每题实例化：
   - 填空题随机挖空。
   - 选择题打乱选项。
   - 排序题打乱句子和选项。
4. 返回公开字段和 `instance_token`。

不写 cache。

### 提交

提交结构：

```json
{
  "grade_name": "一年级下册",
  "mode": "mixed",
  "duration_seconds": 120,
  "answers": [
    {
      "question_id": 123,
      "user_answer": "前明",
      "instance_token": "..."
    }
  ]
}
```

判题：

1. 按 `question_id` 加载题库模板。
2. 对需要 token 的题校验 token 的 `qid/type`。
3. 服务端根据题库和 token 重新计算本次 `prompt / answer / accepted_answers / options`。
4. 判定 `user_answer`。
5. 写入 `dictation_attempts` 和精简后的 `dictation_attempt_items`。
6. 错题按 `user_id + question_id` 查询，存在则覆盖最近一次错误实例，不存在则新增。

## 生成命令

```bash
php artisan dictation:questions:generate --grade="一年级下册"
php artisan dictation:questions:generate --all
php artisan dictation:questions:generate --type=blank
php artisan dictation:questions:generate --refresh-ai
php artisan dictation:questions:generate --dry-run
```

脚本职责：

- 使用 `GradeScopeResolver` 取年级候选诗词。
- 为所有支持题型生成题库模板。
- 用 `source_key` 幂等 upsert。
- 源内容变化时更新 `source_hash` 和题目内容。
- AI 题生成失败不阻塞其它题型。
