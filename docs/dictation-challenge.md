# 诗词闯关功能方案

## 背景

当前项目已经具备诗词、专题、学习进度、收藏、浏览历史、朗读、拼音、译注、分享卡等能力。小程序端的学习路径已经实现，用户可以按小学、初中、高中查看古诗词学习进度，并在诗词详情页记录读过和标为已学。

诗词闯关适合作为学习路径之后的练习能力：用户学完内容后，可以通过补空、上下句、作者识别、注释理解等方式巩固记忆。第一版目标不是做复杂题库，而是基于现有专题和诗词正文、作者、注释等数据动态生成题目，先完成可用闭环。

## 目标

- 支持用户按年级册选择练习范围，例如一年级上册、三年级下册。
- 支持多种题型：补空题、上下句题、作者选择题、注释理解题、诗句出处题、诗句排序题。
- 支持每关固定题量、实时作答、结果统计。
- 支持错题记录和错题复习。
- 支持后续扩展听写、全诗默写、每日挑战、学习报告。

## 不做范围

第一版暂不做以下能力：

- 不做 AI 判分。
- 不做排行榜。
- 不做家长端学习报告。
- 不做复杂组卷策略。
- 不做手工题库后台。
- 不做全文语音识别跟读。

这些能力可以在诗词闯关闭环稳定后逐步增加。

## 使用入口

小程序端已有 `发现页` 的”诗词闯关”入口，第一版直接复用该入口。

建议新增页面：

| 页面 | 路径 | 说明 |
|---|---|---|
| 闯关首页 | `/sub-pages/dictation/index` | 选择年级册、题型、题量 |
| 答题页 | `/sub-pages/dictation/challenge` | 展示题目、输入答案、切题、提交 |
| 结果页 | `/sub-pages/dictation/result` | 展示正确数、用时、错题列表 |
| 错题本 | `/sub-pages/dictation/wrongs` | 查看和复习错题 |

发现页入口跳转：

```js
uni.navigateTo({ url: '/sub-pages/dictation/index' })
```

## 年级册题库范围

诗词闯关的练习范围改为按 `zhuanti_chapters.name` 精确选择年级册，不再只按小学、初中、高中粗粒度选择。

诗词闯关只出古诗词相关题目。可参与题库的专题固定为以下 3 个：

| id | name | alias |
|---|---|---|
| 4 | 小学古诗 | `xiaoxue` |
| 5 | 初中古诗 | `chuzhong` |
| 6 | 高中古诗 | `gaozhong` |

用户选择的是年级册名称，例如：

| 年级册 | 当前诗词数 |
|---|---:|
| 一年级上册 | 7 |
| 一年级下册 | 9 |
| 二年级上册 | 7 |
| 二年级下册 | 7 |
| 三年级上册 | 9 |
| 三年级下册 | 10 |
| 四年级上册 | 11 |
| 四年级下册 | 12 |
| 五年级上册 | 13 |
| 五年级下册 | 13 |
| 六年级上册 | 13 |
| 六年级下册 | 19 |
| 七年级上册 | 21 |
| 七年级下册 | 19 |
| 八年级上册 | 27 |
| 八年级下册 | 21 |
| 九年级上册 | 19 |
| 九年级下册 | 24 |

九年义务教育的年级册列表基本稳定，小程序端可以写死这 18 个选项，不需要后端提供年级册列表接口。后端只需要在生成题目时根据 `grade_name` 查询对应 chapter；如果查不到则返回 `grade_scope_not_found`。

后端根据 `zhuanti_chapters.name` 查出所有同名古诗 chapter，再从这些 chapter 下的 `zhuanti_poems` 取诗词作为题库。

示例：用户选择 `一年级下册` 时，以下 chapter 都应进入题库：

| id | zhuanti_id | name | sub_title |
|---|---|---|---|
| 25 | 4 | 一年级下册 |  |
| 26 | 4 | 一年级下册 | 日积月累 |

也就是说，同一个年级册可能包含同一个古诗专题下的多个 chapter，例如正文篇目和“日积月累”。这些 chapter 都应作为同一个年级册题库。

其他专题不参与诗词闯关题库。

## 题型设计

### 1. 补空题 (blank)

从一句诗文中随机挖掉若干字，不要求连续。

示例：

```text
题目：疑是__霜
答案：地上
原句：疑是地上霜
```

第一版建议规则：

- 句子长度小于 5 个汉字时不生成补空题。
- 挖空位置随机，不固定取最中间的字，不限制首字或尾字。
- 每个被挖掉的字展示为 1 个 `_`。
- 同一句可以生成多个不同挖空位置的题，但同一关内不能出现完全重复题。
- 挖空长度按句长决定：
  - 5 字：挖 2 字。
  - 6 字：挖 2 到 3 字。
  - 7 字：挖 3 到 4 字。
  - 8 到 14 字：挖 3 到 5 字。
  - 15 字以上：挖 4 到 6 字。
- 同一关内避免同一句重复出题。

### 2. 上下句题 (next/previous)

从相邻句子生成题目。优先在同一个句号、问号、叹号或分号之前的句组里配对。

示例：

```text
题目：床前明月光
要求：填写下一句
答案：疑是地上霜
```

也可以反向生成：

```text
题目：疑是地上霜
要求：填写上一句
答案：床前明月光
```

第一版建议上下句各占一半，或者统一由后端随机。

### 3. 作者选择题 (author_choice)

给出诗词名或诗句，从多个作者中选择正确答案。

示例：

```text
题目：《静夜思》的作者是？
选项：
  A. 杜甫
  B. 李白  ✓
  C. 白居易
  D. 王维
```

或：

```text
题目：下列哪首诗是李白的作品？
选项：
  A. 春晓
  B. 静夜思  ✓
  C. 登鹳雀楼
  D. 咏鹅
```

第一版建议规则：

- 使用 `poems.author` 字段作为标准答案。
- 干扰项从同年级册或相近朝代的其他诗人中选择，增加难度。
- 可以给出诗名让用户选作者，也可以给出作者让用户选诗名。
- 选项固定 4 个：1 个正确答案 + 3 个干扰项。
- 干扰项需避免重复，且不能包含正确答案。
- 干扰项优先从题库范围内（当前年级册）的其他诗词作者中选择。

### 4. 注释理解题 (annotation_meaning)

从诗词的注释中提取字词释义，生成选择题。

示例：

```text
原文：疑是地上霜
注释：疑：怀疑，以为。
题目：诗句"疑是地上霜"中"疑"的意思是？
选项：
  A. 怀疑，以为  ✓
  B. 疑问
  C. 惊讶
  D. 猜测
```

第一版建议规则：

- 从 `poems.yizhu_content` 字段中提取注释。
- 解析格式：`字词：释义` 或 `字词，释义`。
- 优先选择诗句中的关键字词，避免生僻字或过于简单的字（如"的"、"了"等）。
- 只有当注释清晰、格式规范时才生成此类题目。
- **干扰项通过 AI 生成**，提供语义相近但错误的释义。
- AI Prompt 示例：
  ```
  请为以下字词生成3个错误但看起来合理的释义选项，用于古诗词学习的选择题。

  字词：{word}
  正确释义：{correct_meaning}
  诗句：{sentence}

  要求：
  1. 生成3个干扰项，每个都是错误的释义
  2. 干扰项应该是该字词在其他语境下可能的含义
  3. 难度适中，不要过于离谱
  4. 以JSON数组格式返回：["干扰项1", "干扰项2", "干扰项3"]
  ```

### 5. 诗句出处题 (poem_source)

给出一句诗，选择它来自哪首诗。

示例：

```text
题目："床前明月光"出自哪首诗？
选项：
  A. 望庐山瀑布
  B. 静夜思  ✓
  C. 早发白帝城
  D. 赠汪伦
```

第一版建议规则：

- 题干使用诗中的名句或特征句。
- 干扰项从同一作者或同年级册的其他诗词中选择。
- 避免使用过于明显的名句（如诗词标题本身）。
- 确保干扰项的诗词在题库范围内，用户有学习过的可能。

### 6. 诗句排序题 (sentence_order)

打乱诗句顺序，让用户选择正确的顺序。

示例：

```text
题目：将下列诗句按正确顺序排列
A. 疑是地上霜
B. 床前明月光
C. 低头思故乡
D. 举头望明月

选项：
  A. B-A-D-C  ✓
  B. B-D-A-C
  C. A-B-D-C
  D. B-C-A-D
```

第一版建议规则：

- 选择 4 句连续的诗句进行打乱。
- 生成 4 个排列选项，其中 1 个为正确答案。
- 干扰项的排列应该有一定合理性（如首句不变，只调整中间句子），增加难度。
- 避免过于简单的诗词（如只有 4 句的绝句）。
- 优先选择律诗或较长的古诗。

### 混合题

`mode=mixed` 时同时生成多种题型。

建议默认（每关 10 题）：

- 补空题：2 题
- 上下句题：2 题  
- 作者选择题：2 题
- 注释理解题：1 题
- 诗句出处题：2 题
- 诗句排序题：1 题

如果某类题型可生成题量不足，则自动调整其他题型比例，确保总题量达标。

## 题目生成

### 文本清洗

从 `poems.content` 中提取正文，生成题目前先做清洗：

- 去掉 HTML 标签。
- 将 `<br>`、`</p>` 等转成换行。
- 解码 HTML 实体。
- 去掉空白行。
- 去掉作者、标题等非正文内容。
- 去掉正文中的异文说明，例如 `(阴 一作：荫)`、`（阴 一作：荫）`。
- 保留中文、数字和必要标点用于切句。

### 异文标注

部分诗词正文会带有“一作”标注：

```html
泉眼无声惜细流，树阴照水爱晴柔。(阴 一作：荫)<br />小荷才露尖尖角，早有蜻蜓立上头。
```

生成题目时需要同时处理两件事：

- 题干和展示正文中去掉 `(阴 一作：荫)` 这类标注。
- 判分时把原字和异文字都作为可接受答案。

上面的内容应解析为：

```text
展示句子：树阴照水爱晴柔
可接受句子：
  - 树阴照水爱晴柔
  - 树荫照水爱晴柔
```

因此用户输入以下任一答案都应判为正确：

```text
树阴照水爱晴柔
树荫照水爱晴柔
```

第一版建议支持以下常见格式：

```text
(阴 一作：荫)
（阴 一作：荫）
(阴 一作 荫)
（阴 一作 荫）
(阴一作：荫)
（阴一作：荫）
(阴一作荫)
（阴一作荫）
```

解析规则：

- 在清洗正文时先提取标注，再从展示文本中删除标注。
- 标注形如 `A 一作 B` 或 `A一作B` 时，记录替换关系 `A -> B`。
- 替换关系只作用于标注所在的相邻句或当前行，避免影响整首诗里其他同字。
- 如果同一句存在多个异文标注，应生成多个可接受版本。
- 如果无法可靠定位到相邻句，则只删除标注，不生成异文答案，避免误判。

题型处理：

- 上下句题：标准答案是完整句子时，`accepted_answers` 保存原句和异文句。
- 补空题：如果挖空部分包含异文字，则 `accepted_answers` 保存所有可接受填空，例如 `["阴", "荫"]`。
- 如果异文不在答案范围内，只影响题干展示，不影响判分。

### 切句规则

按以下标点切句：

```text
句组：。 ！ ？ ； . ! ? ;
句内短句：， 、 ,
```

切句后再过滤：

- 去掉前后空格。
- 去掉纯标点。
- 去掉长度小于 2 的短句。
- 去掉明显不是正文的片段。
- 补空题使用短句生成；上下句题优先使用同一句组内相邻短句生成。

### 题目来源

第一版优先从当前年级册下的专题诗词中抽题。

候选诗词来源：

```text
用户选择 grade_name
  -> zhuanti_chapters.name = grade_name
  -> zhuanti_chapters.zhuanti_id IN (4,5,6)
  -> chapter_id 集合
  -> zhuanti_poems.chapter_id IN (...)
  -> poems
```

推荐查询逻辑：

```sql
SELECT id, zhuanti_id, name, sub_title
FROM zhuanti_chapters
WHERE zhuanti_id IN (4, 5, 6)
  AND name = :grade_name;
```

拿到 chapter 后，再按这些 `chapter_id` 查询题库诗词：

```sql
SELECT
  poems.*,
  zhuanti_poems.zhuanti_id,
  zhuanti_poems.chapter_id,
  zhuanti_poems.order AS zhuanti_poem_order
FROM zhuanti_poems
JOIN poems ON poems.id = zhuanti_poems.poem_id
WHERE zhuanti_poems.chapter_id IN (:chapter_ids)
ORDER BY zhuanti_poems.order, poems.order, poems.id;
```

实现时建议保留每首诗词的来源信息：

- `zhuanti_id`
- `zhuanti.alias`
- `chapter_id`
- `chapter.name`
- `chapter.sub_title`

如果同一首诗在多个 chapter 中出现，第一版可以按 `poems.id` 去重，避免同一关重复出现同一首诗。去重时保留排序最靠前的来源即可。

抽题策略：

1. 优先抽用户未学或读过但未掌握的诗词。
2. 若无学习进度，则按专题顺序取前若干首随机。
3. 若有错题记录，可优先混入历史错题。

第一版可以先实现简单策略：从当前 `grade_name` 对应的 chapter 集合中取诗词，先为每首诗生成可用题目池，再从题目池中抽取指定数量的题目。

### 题量不足处理

低年级诗词数量可能少于默认题量。例如一年级上册当前只有 7 首，一年级下册当前只有 9 首。如果用户请求 10 题，不能简单按“每首诗只出一道题”处理。

组题规则：

- `limit` 表示题目数量，不是诗词数量。
- 同一首诗可以出多道不同题，例如一道上下句题、一到两道补空题。
- 同一句五字诗句也可以生成多个不同挖空位置，例如 `_眠_觉晓`、`春_不_晓`、`春眠__晓`。
- 同一关内避免完全重复的题：`poem_id + question_type + prompt + answer_key` 相同视为重复。
- 获取闯关题目时优先让更多诗词出现一次，再从已入选诗词中补充第二道、第三道题。
- 同一首诗词需要补充多题时，优先选择尚未出现过的题型；只有题量仍不足时，才使用同一题型下其他不重复题目。
- 后端应随机打乱候选诗词、同诗题目池和最终题序，避免每次挑战都是相同题目顺序。
- 同一首诗连续出现多题时，前端可正常展示；后端随机排序即可，不需要做强制间隔。
- 如果整个年级册生成的题目池仍少于 `limit`，则返回实际可生成题量。

示例：

```text
一年级上册共 7 首，请求 limit=10
  -> 先从 7 首各取 1 题
  -> 再从其中 3 首各补 1 道不同题
  -> 返回 10 题
```

## 答案判定

第一版使用确定性判分，不使用 AI。

### 标准化规则

比较答案前，对标准答案、可接受答案和用户答案做统一处理：

- 去掉空格、换行、制表符。
- 去掉常见标点。
- 全角转半角。
- 英文字母统一小写。
- 中文数字和阿拉伯数字暂不互转。
- 暂不做繁简转换，后续可增加。

示例：

```text
用户输入：疑 是 地 上 霜。
标准答案：疑是地上霜
标准化后：疑是地上霜
结果：正确
```

异文示例：

```text
用户输入：树荫照水爱晴柔
标准答案：树阴照水爱晴柔
可接受答案：树阴照水爱晴柔、树荫照水爱晴柔
结果：正确
```

### 判定结果

| 结果 | 条件 |
|---|---|
| 正确 | 用户答案标准化后，命中任一标准化后的可接受答案 |
| 错误 | 用户答案标准化后，未命中任何可接受答案 |

数据结构建议：

```json
{
  "answer": "树阴照水爱晴柔",
  "accepted_answers": ["树阴照水爱晴柔", "树荫照水爱晴柔"]
}
```

说明：

- `answer` 是展示用的主答案，通常使用正文原文。
- `accepted_answers` 是判分用答案集合，必须包含 `answer` 本身。
- 没有异文时，`accepted_answers` 可以只包含一个元素。

后续可以增加“近似正确”：

- 只错 1 个字时提示“差一个字”。
- 漏字、多字时提示字数差异。
- 但第一版不建议把近似正确计为通过，避免判分口径不稳定。

## 数据表设计

### dictation_attempts

记录一次闯关。

```sql
CREATE TABLE dictation_attempts (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  scope_type VARCHAR(32) NOT NULL DEFAULT 'grade',
  grade_name VARCHAR(64) NOT NULL,
  chapter_ids JSON NULL,
  mode VARCHAR(32) NOT NULL DEFAULT 'mixed',
  total INT UNSIGNED NOT NULL DEFAULT 0,
  correct_count INT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  started_at DATETIME NULL,
  submitted_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY idx_user_created (user_id, created_at),
  KEY idx_user_grade_created (user_id, grade_name, created_at),
  KEY idx_user_scope_created (user_id, scope_type, created_at)
);
```

字段说明：

| 字段 | 说明 |
|---|---|
| `user_id` | 当前登录用户 |
| `scope_type` | 范围类型，第一版固定为 `grade` |
| `grade_name` | 年级册名称，例如 `一年级下册` |
| `chapter_ids` | 本次闯关实际使用的 chapter id 集合 |
| `mode` | `blank` / `next` / `previous` / `mixed` |
| `total` | 本关题目数 |
| `correct_count` | 正确数量 |
| `duration_seconds` | 用时 |
| `started_at` | 开始时间 |
| `submitted_at` | 提交时间 |

### dictation_attempt_items

记录一次闯关中的每道题。

```sql
CREATE TABLE dictation_attempt_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  attempt_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  poem_id BIGINT UNSIGNED NOT NULL,
  zhuanti_id BIGINT UNSIGNED NULL,
  chapter_id BIGINT UNSIGNED NULL,
  question_type VARCHAR(32) NOT NULL,
  prompt TEXT NOT NULL,
  answer TEXT NOT NULL,
  accepted_answers TEXT NULL,
  options JSON NULL COMMENT '选择题选项数组',
  user_answer TEXT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  sort INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY idx_attempt_sort (attempt_id, sort),
  KEY idx_user_poem (user_id, poem_id),
  KEY idx_chapter (chapter_id)
);
```

字段说明：

| 字段 | 说明 |
|---|---|
| `attempt_id` | 所属闯关记录 |
| `poem_id` | 题目来源诗词 |
| `zhuanti_id` | 题目来源专题 |
| `chapter_id` | 题目来源 chapter |
| `question_type` | `blank` / `next` / `previous` / `author_choice` / `annotation_meaning` / `poem_source` / `sentence_order` |
| `prompt` | 展示给用户的题干 |
| `answer` | 标准答案 |
| `accepted_answers` | 可接受答案集合，包含异文答案（填空题和上下句题用） |
| `options` | 选择题选项数组（选择题用），JSON 格式，例如 `["选项A", "选项B", "选项C", "选项D"]` |
| `user_answer` | 用户答案 |
| `is_correct` | 是否正确 |
| `sort` | 题目顺序 |

### dictation_wrong_items

记录用户错题聚合状态。相同用户、年级册、诗词、题型、考察答案相同的错题只保留一条记录，通过 `wrong_count` 累计错误次数。

`dictation_wrong_items` 不只保存 `dictation_attempt_items.id`。它需要保留题目快照，便于错题本稳定展示；同时记录首次和最近一次错误的 attempt item id，用于追溯原始答题明细。

```sql
CREATE TABLE dictation_wrong_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  first_attempt_item_id BIGINT UNSIGNED NULL,
  last_attempt_item_id BIGINT UNSIGNED NULL,
  poem_id BIGINT UNSIGNED NOT NULL,
  grade_name VARCHAR(64) NULL,
  zhuanti_id BIGINT UNSIGNED NULL,
  chapter_id BIGINT UNSIGNED NULL,
  question_type VARCHAR(32) NOT NULL,
  answer_key CHAR(32) NOT NULL,
  prompt TEXT NOT NULL,
  answer TEXT NOT NULL,
  accepted_answers TEXT NULL,
  last_user_answer TEXT NULL,
  wrong_count INT UNSIGNED NOT NULL DEFAULT 1,
  reviewed_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_wrong_at DATETIME NULL,
  last_reviewed_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_wrong_item (user_id, grade_name, poem_id, question_type, answer_key)
);
```

第一版同一用户的错题量预计不会很大，`dictation_wrong_items` 先只保留业务必须的唯一约束，避免过度索引。错题本列表、按年级册筛选、按状态筛选可以先依赖 `user_id` 维度的数据量控制；如果后续用户错题量变大，再按真实查询慢点补索引。

字段说明：

| 字段 | 说明 |
|---|---|
| `first_attempt_item_id` | 首次在闯关中产生该错题的答题明细 |
| `last_attempt_item_id` | 最近一次在闯关中产生该错题的答题明细 |
| `grade_name` | 错题产生时的年级册 |
| `zhuanti_id` | 错题来源专题 |
| `chapter_id` | 错题来源 chapter |
| `question_type` | `blank` / `next` / `previous` |
| `answer_key` | 用于错题聚合的答案指纹 |
| `prompt` | 最近一次错误时的题干快照，用于展示 |
| `answer` | 展示用主答案 |
| `accepted_answers` | 可接受答案集合，包含异文答案 |
| `last_user_answer` | 最近一次错误答案 |
| `wrong_count` | 错误累计次数 |
| `reviewed_count` | 复习作答次数 |
| `resolved_at` | 用户复习正确后可标记为已掌握 |

错题合并策略建议：

- 第一版按 `user_id + grade_name + poem_id + question_type + answer_key` 合并。
- 不使用 `prompt` 作为去重 key，因为题干是展示文案，可能从 `泉眼无声惜细流` 变成 `请填写下一句：泉眼无声惜细流`，但考察内容没有变。
- `prompt`、`answer`、`accepted_answers` 仍然保留在错题表里，作为错题本展示快照。

`answer_key` 生成规则：

1. 取 `accepted_answers`，如果为空则使用 `[answer]`。
2. 对每个答案执行与判分一致的标准化。
3. 去重。
4. 排序，避免异文答案顺序不同导致 key 不同。
5. 用 `|` 拼接。
6. 计算 MD5，写入 `answer_key`。这里不是安全签名，只是错题聚合指纹，32 位长度足够第一版使用。

示例：

```text
answer: 树阴照水爱晴柔
accepted_answers:
  - 树阴照水爱晴柔
  - 树荫照水爱晴柔

标准化并排序后：
树荫照水爱晴柔|树阴照水爱晴柔

answer_key:
md5("树荫照水爱晴柔|树阴照水爱晴柔")
```

错题写入逻辑：

```text
命中 uniq_wrong_item:
  wrong_count = wrong_count + 1
  prompt = 本次题干快照
  answer = 本次主答案
  accepted_answers = 本次可接受答案集合
  last_user_answer = 本次错误答案
  last_attempt_item_id = 本次 attempt item id
  last_wrong_at = now
  resolved_at = null

未命中:
  创建新错题
  first_attempt_item_id = 本次 attempt item id
  last_attempt_item_id = 本次 attempt item id
  answer_key = 本次答案指纹
  prompt / answer / accepted_answers / last_user_answer = 本次题目和答案快照
  wrong_count = 1
```

如果后续希望做全局错题本，可以在查询层忽略 `grade_name` 聚合展示。

## API 设计

所有接口放在 `wx.sign` 中间件内，需要登录用户。

### 获取闯关题目

```http
GET /api/dictation/challenge?grade_name=一年级下册&mode=mixed&limit=10
```

Query 参数：

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `grade_name` | string | 是 | 年级册名称，对应 `zhuanti_chapters.name` |
| `mode` | string | 否 | `blank` / `next` / `previous` / `author_choice` / `annotation_meaning` / `poem_source` / `sentence_order` / `mixed`，默认 `mixed` |
| `limit` | int | 否 | 题目数量，默认 10，最大 20 |

响应：

```json
{
  "challenge_id": "dc_P1m3c7a9Kxv2r8ZtQn4Ls6Bw",
  "challenge_token": "encrypted-token",
  "grade_name": "一年级下册",
  "mode": "mixed",
  "total": 10,
  "ttl_seconds": 1800,
  "questions": [
    {
      "question_id": 123,
      "type": "blank",
      "poem_id": "jingyesi",
      "poem_name": "静夜思",
      "author_name": "李白",
      "chaodai": "唐代",
      "prompt": "疑是__霜",
      "answer_hint": "2个字",
      "instance_token": "encrypted-instance-token"
    },
    {
      "question_id": 124,
      "type": "next",
      "poem_id": "jingyesi",
      "poem_name": "静夜思",
      "author_name": "李白",
      "chaodai": "唐代",
      "prompt": "床前明月光",
      "direction": "填写下一句",
      "instance_token": "encrypted-instance-token"
    }
  ]
}
```

注意：

- 第一版响应不返回标准答案，避免前端直接暴露答案。
- `ttl_seconds` 用于前端倒计时提示；服务端不按 token 内过期时间拒绝提交。
- 第一版响应不返回 `topic`、`chapter`、`chapter_ids`。这些来源信息保存在题库记录中，并在提交时写入 `dictation_attempt_items`。
- 前端提交时必须原样带回每题的 `question_id` 和 `instance_token`。
- 前端答题页只需要展示诗词基础信息、题干和题型提示。
- 如果后续结果页确实需要带学习路径上下文跳转诗词详情，可以单独返回一个轻量字段 `zhuanti_alias`，不需要返回完整 `topic/chapter` 对象。

后端通过题库记录和加密 token 保存本次题目顺序与实例化状态，提交时不信任前端答案。

### 提交闯关结果

```http
POST /api/dictation/challenge/submit
```

请求：

```json
{
  "challenge_token": "encrypted-token",
  "duration_seconds": 126,
  "answers": [
    {
      "question_id": 123,
      "user_answer": "地上",
      "instance_token": "encrypted-instance-token"
    },
    {
      "question_id": 124,
      "user_answer": "疑是地上霜",
      "instance_token": "encrypted-instance-token"
    }
  ]
}
```

响应：

```json
{
  "attempt_id": 123,
  "total": 10,
  "correct_count": 8,
  "wrong_count": 2,
  "duration_seconds": 126,
  "passed": true,
  "items": [
    {
      "question_id": "q1",
      "type": "blank",
      "poem_id": "jingyesi",
      "poem_name": "静夜思",
      "prompt": "疑是__霜",
      "answer": "地上",
      "accepted_answers": ["地上"],
      "user_answer": "地上",
      "is_correct": true
    }
  ]
}
```

如果题目存在异文答案，结果项可返回多个可接受答案：

```json
{
  "question_id": "q3",
  "type": "next",
  "poem_id": "xiao-chi",
  "poem_name": "小池",
  "prompt": "泉眼无声惜细流",
  "answer": "树阴照水爱晴柔",
  "accepted_answers": ["树阴照水爱晴柔", "树荫照水爱晴柔"],
  "user_answer": "树荫照水爱晴柔",
  "is_correct": true
}
```

通过规则建议：

```text
correct_count / total >= 0.8
```

### 获取错题本

```http
GET /api/dictation/wrongs?page=1
```

Query 参数：

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `page` | int | 否 | 页码 |
| `grade_name` | string | 否 | 按年级册过滤 |
| `status` | string | 否 | `active` / `resolved`，默认 `active` |

响应：

```json
{
  "data": [
    {
      "id": 1,
      "poem_id": "jingyesi",
      "poem_name": "静夜思",
      "author_name": "李白",
      "chaodai": "唐代",
      "question_type": "blank",
      "answer_key": "b3d8c7...",
      "prompt": "疑是__霜",
      "last_user_answer": "地霜",
      "wrong_count": 2,
      "reviewed_count": 1,
      "first_attempt_item_id": 101,
      "last_attempt_item_id": 168,
      "last_wrong_at": "2026-06-05 20:00:00"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "has_more": false
}
```

错题列表默认不返回 `answer` 和 `accepted_answers`，避免用户在复习前直接看到标准答案。复习提交后再返回标准答案和判定结果。

### 提交错题复习答案

```http
POST /api/dictation/wrongs/{id}/review
```

请求：

```json
{
  "user_answer": "地上"
}
```

响应：

```json
{
  "id": 1,
  "question_type": "blank",
  "prompt": "疑是__霜",
  "answer": "地上",
  "accepted_answers": ["地上"],
  "user_answer": "地上",
  "is_correct": true,
  "resolved": true,
  "wrong_count": 2,
  "reviewed_count": 2,
  "last_reviewed_at": "2026-06-05 20:10:00",
  "resolved_at": "2026-06-05 20:10:00"
}
```

规则：

- 每次提交复习答案，都执行 `reviewed_count + 1`，并更新 `last_reviewed_at`。
- 用户答对后，设置 `resolved_at = now`。
- 用户答错后，`wrong_count + 1`，更新 `last_user_answer`、`last_wrong_at`，并保持或清空 `resolved_at` 为未掌握状态。
- 第一版不提供手动“标记已掌握”接口，也不提供删除错题接口。错题状态应由复习作答驱动。

### 获取统计

```http
GET /api/dictation/stats
```

可选 Query：

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `grade_name` | string | 否 | 按年级册统计 |

响应：

```json
{
  "today_attempts": 2,
  "today_correct_count": 16,
  "today_total": 20,
  "active_wrong_count": 12
}
```

第一版统计可以只做小程序“我的”或结果页使用，不必强依赖。

## 后端实现建议

### 新增模型

建议新增：

- `App\Models\DictationAttempt`
- `App\Models\DictationAttemptItem`
- `App\Models\DictationWrongItem`

### 新增服务

建议新增：

- `App\Services\Dictation\GradeScopeResolver`
- `App\Services\Dictation\PoemTextParser`
- `App\Services\Dictation\QuestionGenerator`
- `App\Services\Dictation\ChoiceQuestionGenerator`
- `App\Services\Dictation\AnnotationParser`
- `App\Services\Dictation\DeepSeekAIService`
- `App\Services\Dictation\AnswerNormalizer`
- `App\Services\Dictation\ChallengeService`

职责拆分：

| 服务 | 职责 |
|---|---|
| `GradeScopeResolver` | 根据 `grade_name` 解析古诗 chapter 集合、专题集合和候选诗词 |
| `PoemTextParser` | 清洗 HTML、提取并移除异文标注、切句 |
| `QuestionGenerator` | 生成填空题和上下句题 |
| `ChoiceQuestionGenerator` | 生成作者、诗句出处、排序等选择题，包括干扰项生成 |
| `AnnotationParser` | 从 `yizhu_content` 中提取注释，解析"字词：释义"格式 |
| `DeepSeekAIService` | 封装 DeepSeek API 请求，用于生成注释理解题干扰项 |
| `AnswerNormalizer` | 标准化答案并和 `accepted_answers` 比较 |
| `ChallengeService` | 生成 challenge、提交评分、写入 attempt 和错题 |

### 控制器

建议新增：

- `App\Http\Controllers\Api\DictationController`

路由：

```php
Route::get('/dictation/challenge', [DictationController::class, 'challenge']);
Route::post('/dictation/challenge/submit', [DictationController::class, 'submit']);
Route::get('/dictation/wrongs', [DictationController::class, 'wrongs']);
Route::post('/dictation/wrongs/{id}/review', [DictationController::class, 'reviewWrong']);
Route::get('/dictation/stats', [DictationController::class, 'stats']);
```

## 小程序实现建议

### API 模块

新增：

```text
src/api/dictation.js
```

导出：

```js
export function getDictationChallenge(params) {}
export function submitDictationChallenge(data) {}
export function listDictationWrongs(params) {}
export function reviewDictationWrong(id, userAnswer) {}
export function getDictationStats() {}
```

并在 `src/api/index.js` 中导出。

### 闯关首页

功能：

- 年级册选择：一年级上册、一年级下册、三年级下册等。
- 题型选择：混合、补空、上下句。
- 题量选择：5、10、20。
- 展示错题数量和今日练习情况。
- 点击“开始闯关”进入答题页。

推荐交互：

- 年级册列表由小程序端写死，使用上文列出的 18 个九年义务教育年级册。
- 前端可以按小学、初中做视觉分组，但实际提交参数只使用 `grade_name`。
- 默认选择第一个可用年级册。

开始闯关时传参示例：

```js
uni.navigateTo({
  url: `/sub-pages/dictation/challenge?grade_name=${encodeURIComponent(gradeName)}&mode=${mode}&limit=${limit}`
})
```

### 答题页

核心状态：

```js
const questions = ref([])
const currentIndex = ref(0)
const answers = ref({})
const startedAt = ref(Date.now())
```

交互：

- 展示当前第 N / total 题。
- 展示诗名、作者、朝代。
- 展示题干和题型说明。
- 输入答案。
- 支持上一题、下一题。
- 最后一题显示提交。
- 提交前检查是否有空答案，允许继续提交或返回填写。

### 结果页

展示：

- 正确数量。
- 用时。
- 是否通过。
- 错题列表。
- 每题的用户答案和标准答案。
- 操作：再来一关、复习错题、返回学习路径。

### 错题本

展示：

- 错题列表。
- 诗名、作者、题干、最近错误答案、错误次数。
- 支持重新答一遍。
- 重新答对后自动标记已掌握。
- 不提供手动标记已掌握和删除错题。

## 与学习进度的关系

诗词闯关不直接改变 `user_study_progress.status`。

原因：

- 学习路径里的 `learned` 是用户主动标记。
- 闯关答题正确不一定代表用户已完成整首学习。
- 避免练习状态和学习状态互相覆盖。
- 年级册题库只覆盖古诗专题，但小学、初中、高中分别对应不同 `zhuanti.alias`。

但可以在结果页做提示：

- 如果某首诗多次答对，可提示“是否标为已学”。
- 第一版从结果页跳转诗词详情时，可以只带 `poem_id`，不展示学习路径状态。
- 如果后续确实要在详情页展示学习状态，可以让提交结果额外返回 `zhuanti_alias`，再跳转时带上该参数。
- 后续可增加自动建议，但不自动修改学习进度。

示例：

```text
一年级下册题库
  -> 小学古诗 xiaoxue / chapter 25
  -> 小学古诗 xiaoxue / chapter 26
```

第一版跳转：

```text
/sub-pages/poem/detail?poem_id=xxx
```

后续如需展示学习路径状态，再使用：

```text
/sub-pages/poem/detail?poem_id=xxx&zhuanti_alias=xiaoxue
```

## 与错题复习的关系

第一版错题复习可以复用答题页交互，但题目来源从 `dictation_wrong_items` 读取。

每次提交错题复习答案后：

- `reviewed_count + 1`
- `last_reviewed_at = now`

错题复习正确后：

- `resolved_at = now`

错题复习错误后：

- `wrong_count + 1`
- `last_user_answer = 本次错误答案`
- `last_wrong_at = now`
- `resolved_at = null`

第一版不提供手动“标记已掌握”和删除错题能力。这样错题状态由复习作答结果驱动，避免用户直接清掉未掌握内容。

后续如果希望更严格，可以改成连续答对 2 次后才设置 `resolved_at`。

## 安全与防作弊

- `GET /challenge` 不返回标准答案。
- 标准答案和 `accepted_answers` 保存在服务端题库记录中，不由前端提交。
- `challenge_token` 绑定 `user_id`、`grade_name`、`mode` 和本次题目 id 顺序。
- `ttl_seconds` 返回 1800 秒，用于前端倒计时提示。
- `instance_token` 保存实例化后的选项顺序、补空位置或排序答案，提交时只接受可解密且匹配题目的实例 token。
- 当前实现不阻止重复提交；重复提交会生成新的 attempt 记录。

## 边界情况

| 场景 | 处理 |
|---|---|
| `grade_name` 不存在 | 返回 `404 {"error":"grade_scope_not_found"}` |
| 年级册存在但筛选后无 chapter | 返回 `404 {"error":"grade_scope_not_found"}` |
| 诗词数量少于 `limit` | 允许同一首诗生成多道不同题 |
| 题目池仍少于 `limit` | 返回实际生成数量 |
| 完全无法生成题目 | 返回 `200`，`questions=[]`，小程序提示“当前内容暂不能生成题目” |
| challenge 过期或 token 非法 | 返回 `400 {"error":"invalid_question_instance"}` |
| 题目实例 token 非法 | 返回 `400 {"error":"invalid_question_instance"}` |
| 用户未填写答案 | 允许提交，按错误处理 |

## 分阶段实现

### 第一阶段：基础闭环

后端：

- 新增数据表。
- 新增题目生成服务。
- 新增闯关获取和提交接口。
- 新增错题写入。

小程序：

- 新增闯关首页。
- 新增答题页。
- 新增结果页。
- 发现页入口跳转。

验收标准：

- 用户能选择 `一年级下册` 开始 10 题闯关。
- 后端能把 `一年级下册` 对应的多个 chapter 合并为同一个题库。
- 用户能提交答案并看到结果。
- 错题能被保存。

### 第二阶段：错题本

后端：

- 新增错题列表接口。
- 新增错题复习提交接口。

小程序：

- 新增错题本页面。
- 结果页跳转错题本。
- 我的页可增加“错题本”入口。

验收标准：

- 用户能查看错题。
- 用户能重新填写错题答案。
- 用户答对后错题自动标记为已掌握。
- 用户答错后错题继续保留并累计错误次数。

### 第三阶段：练习优化

能力：

- 优先抽未掌握诗词。
- 优先抽历史错题。
- 增加今日练习统计。
- 增加连续练习天数。

### 第四阶段：高级玩法

能力：

- 听写模式。
- 全诗默写。
- 每日挑战。
- 诗词大会。
- 飞花令。
- 学习报告。

## 测试建议

### 后端单元测试

- 年级册 scope 解析。
- 同名多个 chapter 聚合。
- HTML 正文清洗。
- 异文标注提取和移除，例如 `(阴 一作：荫)`。
- 切句。
- 补空题生成。
- 上下句题生成。
- 同一首诗生成多道不重复题。
- 异文可接受答案生成。
- `answer_key` 生成：标准化、去重、排序、hash。
- 答案标准化。
- 答案判定：用户输入原字或异文字都算正确。

### 后端接口测试

- 获取 challenge。
- 一年级上册 7 首诗请求 10 题时，应返回 10 道不重复题。
- 提交全对。
- 提交部分错误。
- 提交异文答案，例如 `树荫照水爱晴柔` 应判为正确。
- challenge 过期。
- `grade_name` 不存在。
- 同一年级册多个 chapter 的诗词都能进入候选集。
- 错题合并累计。
- 同一考点不同 prompt 的错题应按 `answer_key` 合并为一条。
- 异文答案顺序不同但内容相同的错题应合并为一条。
- 错题复习答对后设置 `resolved_at`。
- 错题复习答错后清空 `resolved_at` 并累计 `wrong_count`。

### 小程序测试

- 答题页切题。
- 输入答案后保留状态。
- 空答案提交。
- 结果页展示正确/错误。
- 错题本分页。
- 错题重新答题。
- 从发现页进入闯关。
- 年级册选择后能正确传递 `grade_name`。

## 推荐第一版范围

第一版建议只实现：

- `mixed` 混合模式。
- 补空题。
- 下一句题。
- 作者选择题。
- 每关 10 题。
- 结果页。
- 错题保存。

第二版可增加：
- 注释理解题（需要 AI 生成干扰项）。
- 诗句出处题。
- 诗句排序题。

错题本页面可以作为第二阶段补上。这样第一版范围可控，也能最快验证用户是否愿意使用诗词闯关练习。
