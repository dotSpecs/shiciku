# 数组键问题修复报告

## 问题
```
Undefined array key 1
app/Services/Dictation/ChoiceQuestionGenerator.php :201
```

## 原因
`array_filter()` 函数会保留原始数组的键，导致返回关联数组而不是索引数组。

例如：
```php
$arr = ['a', 'b', '', 'c'];
$filtered = array_filter($arr);
// 结果: [0 => 'a', 1 => 'b', 3 => 'c']  // 注意键 2 被跳过了
```

当尝试访问 `$filtered[1]` 时可以正常工作，但访问 `$filtered[2]` 会报错，因为实际的键是 3。

## 修复方案

### splitSentences() 方法

在返回前使用 `array_values()` 重建索引数组：

```php
// 修复前
private function splitSentences(string $content): array
{
    $sentences = preg_split('/[，。！？；、,.!?;]/', $content);

    return array_filter(array_map('trim', $sentences), function ($s) {
        return mb_strlen($s) >= 2;
    });
}

// 修复后
private function splitSentences(string $content): array
{
    $sentences = preg_split('/[，。！？；、,.!?;]/', $content);

    $filtered = array_filter(array_map('trim', $sentences), function ($s) {
        return mb_strlen($s) >= 2;
    });

    // 重建索引数组，确保键从 0 开始连续
    return array_values($filtered);
}
```

## 影响范围

修复后，以下方法可以安全使用数组索引：

- ✅ `generatePoemSourceQuestion()` - 第 201 行
- ✅ `generateSentenceOrderQuestion()` - 第 328 行
- ✅ `findSentenceContainingWord()` - 使用 foreach，不受影响

## 相关知识

### PHP 数组函数对键的影响

**保留键的函数：**
- `array_filter()` - 保留原始键
- `array_map()` - 保留原始键
- `array_unique()` - 保留原始键

**重建键的函数：**
- `array_values()` - 重建为 0 开始的索引数组
- `array_slice()` - 默认重建键（除非设置 `preserve_keys = true`）
- `array_merge()` - 重建数字键

### 最佳实践

当使用 `array_filter()` 后需要按索引访问元素时，应该：

```php
// 推荐方式 1：立即重建索引
$result = array_values(array_filter($array));

// 推荐方式 2：使用 foreach 遍历
foreach (array_filter($array) as $item) {
    // 不依赖键
}

// 避免：直接按索引访问过滤后的数组
$filtered = array_filter($array);
$item = $filtered[1]; // 可能报错！
```

## 验证

```bash
✅ php -l app/Services/Dictation/ChoiceQuestionGenerator.php
   No syntax errors detected
```

## 测试建议

```php
// 测试用例
$content = "春眠不觉晓，处处闻啼鸟。夜来风雨声，花落知多少。";
$sentences = $this->splitSentences($content);

// 验证返回的是索引数组
assert($sentences === array_values($sentences));
assert(array_key_exists(0, $sentences));
assert(array_key_exists(count($sentences) - 1, $sentences));
```

## 状态
✅ 已修复，数组索引访问安全
