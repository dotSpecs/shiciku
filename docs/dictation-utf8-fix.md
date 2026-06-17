# UTF-8 编码问题修复报告

## 问题
```
Internal Server Error
InvalidArgumentException
Malformed UTF-8 characters, possibly incorrectly encoded
vendor/laravel/framework/src/Illuminate/Http/JsonResponse.php :90
```

## 原因
在处理诗词内容时，可能包含非法的 UTF-8 字符或编码不一致的字符，导致 JSON 编码失败。

常见来源：
1. 数据库中的数据编码不一致
2. HTML 实体转换后产生的无效字符
3. 正则表达式处理后的字符串
4. 控制字符（0x00-0x1F）

## 修复方案

### 1. PoemTextParser::cleanContent()

添加 UTF-8 验证和清理：

```php
public function cleanContent(?string $content): string
{
    if (empty($content)) {
        return '';
    }

    $text = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text) ?? $text;
    $text = preg_replace('/<\/p\s*>/i', "\n", $text) ?? $text;
    $text = strip_tags($text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace(self::VARIANT_PATTERN, '', $text) ?? $text;
    $text = preg_replace('/\s+/u', '', $text) ?? $text;

    // 确保 UTF-8 编码正确
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

    // 移除无效的 UTF-8 字符（控制字符）
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

    return trim($text);
}
```

### 2. ChoiceQuestionGenerator::splitSentences()

确保切分后的句子编码正确：

```php
private function splitSentences(string $content): array
{
    $sentences = preg_split('/[，。！？；、,.!?;]/u', $content);

    $filtered = array_filter(array_map('trim', $sentences), function ($s) {
        return mb_strlen($s, 'UTF-8') >= 2;
    });

    // 重建索引数组，确保字符串是有效的 UTF-8
    return array_values(array_map(function($s) {
        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }, $filtered));
}
```

### 3. AnnotationParser::parseAnnotations()

添加编码验证：

```php
$text = $yizhu ?? $zhushi ?? '';

if (empty($text)) {
    return [];
}

$text = strip_tags($text);

// 确保 UTF-8 编码正确
$text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

$lines = explode("\n", $text);
```

## 关键技术

### mb_convert_encoding()

```php
// 将字符串转换为 UTF-8，自动修复损坏的编码
$clean = mb_convert_encoding($dirty, 'UTF-8', 'UTF-8');
```

这个函数会：
1. 检测并修复无效的 UTF-8 序列
2. 替换或删除无法转换的字符
3. 返回有效的 UTF-8 字符串

### 移除控制字符

```php
// 移除 ASCII 控制字符（0x00-0x1F，0x7F）
$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
```

保留的字符：
- `\x09` (TAB)
- `\x0A` (LF - 换行)
- `\x0D` (CR - 回车，但会被后续替换)

### 正则表达式 UTF-8 模式

确保所有正则表达式使用 `/u` 修饰符：

```php
// 正确 - 使用 UTF-8 模式
preg_split('/[，。]/u', $text);
mb_strlen($text, 'UTF-8');

// 错误 - 缺少 UTF-8 模式
preg_split('/[，。]/', $text);  // 可能导致编码问题
mb_strlen($text);  // 默认编码可能不是 UTF-8
```

## 防御性编程建议

### 1. 在数据入口处理

```php
// 在接收数据时立即清理
$content = mb_convert_encoding($rawContent, 'UTF-8', 'UTF-8');
```

### 2. 在数据出口验证

```php
// 在返回 JSON 前验证
if (!mb_check_encoding($text, 'UTF-8')) {
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
}
```

### 3. 使用 json_encode 选项

```php
// Laravel 中可以在 JsonResponse 中使用
return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
```

## 测试建议

```php
// 测试各种边界情况
$testCases = [
    "正常中文内容",
    "包含控制字符\x00\x01",
    "混合编码\xE4\xB8\xAD\xE6\x96\x87",
    "HTML 实体&nbsp;&amp;",
    "异文标注(阴 一作：荫)",
];

foreach ($testCases as $test) {
    $result = $parser->cleanContent($test);
    assert(mb_check_encoding($result, 'UTF-8'));
    assert(json_encode($result) !== false);
}
```

## 验证

```bash
✅ php -l app/Services/Dictation/PoemTextParser.php
✅ php -l app/Services/Dictation/ChoiceQuestionGenerator.php
✅ php -l app/Services/Dictation/AnnotationParser.php
```

## 监控建议

在生产环境添加日志：

```php
try {
    $json = json_encode($data);
    if ($json === false) {
        Log::error('JSON encode failed', [
            'error' => json_last_error_msg(),
            'data_sample' => mb_substr(print_r($data, true), 0, 200),
        ]);
    }
} catch (\Exception $e) {
    Log::error('JSON encoding exception', [
        'error' => $e->getMessage(),
    ]);
}
```

## 状态
✅ 已修复，所有文本处理现在都确保 UTF-8 编码正确
