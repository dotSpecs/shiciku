# 诗词闯关 - DeepSeek AI 服务

`annotation_meaning` 会调用 DeepSeek 生成选项。未配置 `DEEPSEEK_API_KEY` 时，这类题会自动跳过，不影响基础题型生成。

## 配置

在 `.env` 中配置：

```bash
DEEPSEEK_API_KEY=your-deepseek-key
DEEPSEEK_API_URL=https://api.deepseek.com/chat/completions
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_TIMEOUT=60
DEEPSEEK_MAX_TOKENS=1024
DEEPSEEK_TEMPERATURE=0.2
```

如果线上开启了配置缓存，改完环境变量后执行：

```bash
php artisan config:clear
```

## 生成 AI 题型

默认生成命令不会生成 AI 题型，需要显式指定：

```bash
php artisan dictation:questions:generate --all --type=annotation_meaning
```

或在生成全部题型时加 `--refresh-ai`：

```bash
php artisan dictation:questions:generate --all --refresh-ai
```

## 服务

核心类是 `App\Services\Dictation\DeepSeekAIService`，使用 DeepSeek Chat Completions 格式：

```json
{
  "model": "deepseek-chat",
  "messages": [
    {"role": "system", "content": "你是严谨的小学古诗词题库编辑。必须只输出合法 JSON。"},
    {"role": "user", "content": "..."}
  ],
  "temperature": 0.2,
  "max_tokens": 1024,
  "stream": false
}
```

返回内容会从 `choices.0.message.content` 读取，并解析纯 JSON 或 markdown JSON 代码块。
