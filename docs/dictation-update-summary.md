# 诗词闯关功能更新总结

## 更新日期
2026-06-15

## 主要变更

### 1. 文档更新
- ✅ 将"默写闯关"统一改为"诗词闯关"
- ✅ 补充完整的题型设计文档（7种题型）
- ✅ 更新数据表设计，增加 `options` 字段
- ✅ 更新服务架构说明

### 2. 新增题型

#### 已有题型（不需要 AI）
1. **补空题 (blank)** - 挖空填字
2. **上下句题 (next/previous)** - 填写上一句或下一句
3. **作者选择题 (author_choice)** - 选择诗词作者或作者的作品

#### 新增题型
4. **注释理解题 (annotation_meaning)** - 选择字词在诗句中的正确释义
5. **诗句出处题 (poem_source)** - 判断诗句出自哪首诗
6. **诗句排序题 (sentence_order)** - 将打乱的诗句按正确顺序排列

### 3. 新增服务

#### ClaudeAIService
- **文件**: `app/Services/Dictation/ClaudeAIService.php`
- **功能**: 封装 Claude API 调用
- **方法**:
  - `generateAnnotationDistractors()` - 生成注释理解题的干扰项
  - `isConfigured()` - 检查 API 是否已配置

#### AnnotationParser
- **文件**: `app/Services/Dictation/AnnotationParser.php`
- **功能**: 解析诗词注释
- **方法**:
  - `parseAnnotations()` - 从 yizhu_content 提取字词释义
  - `findMeaningForWord()` - 查找特定字词的释义

#### ChoiceQuestionGenerator
- **文件**: `app/Services/Dictation/ChoiceQuestionGenerator.php`
- **功能**: 生成所有选择题类型
- **方法**:
  - `generateAuthorChoiceQuestion()` - 生成作者选择题
  - `generateAnnotationMeaningQuestion()` - 生成注释理解题（需要 AI）
  - `generatePoemSourceQuestion()` - 生成诗句出处题
  - `generateSentenceOrderQuestion()` - 生成诗句排序题

### 4. 配置更新

#### config/services.php
```php
'claude' => [
    'api_key' => env('CLAUDE_API_KEY'),
    'api_url' => env('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages'),
    'model' => env('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022'),
],
```

#### .env.example
```bash
CLAUDE_API_KEY=
CLAUDE_API_URL=https://api.anthropic.com/v1/messages
CLAUDE_MODEL=claude-3-5-sonnet-20241022
```

### 5. 代码更新

#### QuestionGenerator.php
新增常量：
```php
public const TYPE_AUTHOR_CHOICE = 'author_choice';
public const TYPE_ANNOTATION_MEANING = 'annotation_meaning';
public const TYPE_POEM_SOURCE = 'poem_source';
public const TYPE_SENTENCE_ORDER = 'sentence_order';
```

#### ChallengeService.php
- 更新 `MODES` 常量，包含所有新题型
- 更新 `publicQuestion()` 方法，支持 `options` 字段
- 更新 `resultItem()` 方法，支持 `options` 字段

### 6. 数据库变更

#### dictation_attempt_items 表
新增字段：
```sql
options JSON NULL COMMENT '选择题选项数组'
```

### 7. 文档

#### docs/dictation-challenge.md
- 完整的题型设计说明
- 每种题型的生成规则
- 混合题的比例建议

#### docs/dictation-claude-ai.md
- Claude AI 服务使用说明
- 配置指南
- API 调用示例
- 成本估算
- 优化建议
- 常见问题

## 使用方式

### 第一阶段（不需要 AI）
实现基础题型：
- 补空题
- 上下句题
- 作者选择题

### 第二阶段
配置 Claude API 后可增加：
- 注释理解题（需要 AI 生成干扰项）

同时可增加纯算法题型：
- 诗句出处题
- 诗句排序题

## 配置步骤

1. 在 `.env` 中添加 Claude API Key：
   ```bash
   CLAUDE_API_KEY=sk-ant-api03-xxx
   ```

2. 运行数据库迁移（如果有新的迁移文件）

3. 清除配置缓存：
   ```bash
   php artisan config:clear
   ```

## 注意事项

1. **降级策略**: 如果 Claude API 未配置或调用失败，相关题型会自动跳过，不影响其他题型
2. **成本控制**: 建议使用缓存策略，相同诗词的 AI 生成结果可以复用
3. **错误处理**: 所有 AI 调用都有完善的异常捕获和日志记录
4. **模型选择**: 
   - `claude-3-5-sonnet-20241022` (推荐，性价比最高)
   - `claude-3-haiku-20240307` (最快速度，成本最低)
   - `claude-3-opus-20240229` (最强能力，成本较高)

## 成本估算

使用 `claude-3-5-sonnet-20241022` 模型：
- 单次题目生成：约 $0.002 - $0.003
- 每天生成 1000 道题：约 $2 - $3
- 预计月成本：约 $60 - $90

## 后续建议

1. 为 AI 生成的注释干扰项添加缓存
2. 监控 API 调用成功率和响应时间
3. 根据实际使用情况调整题型比例
4. 考虑批量生成题目并预存储

## 相关文件

- `docs/dictation-challenge.md` - 功能设计文档
- `docs/dictation-claude-ai.md` - AI 服务使用说明
- `app/Services/Dictation/ClaudeAIService.php` - AI 服务封装
- `app/Services/Dictation/AnnotationParser.php` - 注释解析器
- `app/Services/Dictation/ChoiceQuestionGenerator.php` - 选择题生成器
- `config/services.php` - 服务配置
- `.env.example` - 环境变量示例
