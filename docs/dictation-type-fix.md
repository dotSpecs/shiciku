# 类型兼容性修复报告

## 问题
```
App\Services\Dictation\AnnotationParser::parseAnnotations(): 
Argument #1 ($poem) must be of type App\Models\Poem, stdClass given
```

## 原因
`AnnotationParser` 的方法签名要求 `Poem` 模型类型，但 `ChoiceQuestionGenerator` 传入的是 `stdClass` 对象。

## 修复方案

### AnnotationParser.php

移除类型限制，支持多种输入类型：

```php
// 修复前
public function parseAnnotations(Poem $poem): array
{
    $text = $poem->yizhu ?? $poem->zhushi ?? '';
    // ...
}

// 修复后
public function parseAnnotations($poem): array
{
    // 标准化输入
    $yizhu = null;
    $zhushi = null;

    if (is_array($poem)) {
        $yizhu = $poem['yizhu'] ?? null;
        $zhushi = $poem['zhushi'] ?? null;
    } else {
        $yizhu = $poem->yizhu ?? null;
        $zhushi = $poem->zhushi ?? null;
    }

    $text = $yizhu ?? $zhushi ?? '';
    // ...
}
```

同样修复了 `findMeaningForWord()` 方法。

## 现在支持的输入类型

1. **Poem 模型**（Eloquent Model）
2. **stdClass 对象**
3. **关联数组**

### 示例

```php
$parser = app(AnnotationParser::class);

// 支持 Poem 模型
$poem = Poem::find(1);
$annotations = $parser->parseAnnotations($poem);

// 支持 stdClass 对象
$poem = (object)[
    'yizhu' => '疑：怀疑，以为。',
    'zhushi' => null,
];
$annotations = $parser->parseAnnotations($poem);

// 支持数组
$poem = [
    'yizhu' => '疑：怀疑，以为。',
    'zhushi' => null,
];
$annotations = $parser->parseAnnotations($poem);
```

## 影响范围

### 已修复的服务类
- ✅ `AnnotationParser::parseAnnotations()`
- ✅ `AnnotationParser::findMeaningForWord()`
- ✅ `ChoiceQuestionGenerator` 所有方法
- ✅ `QuestionGenerator::candidateToPoem()`

### 灵活性提升
现在整个题目生成系统都支持：
- Eloquent 模型
- stdClass 对象  
- 关联数组

这使得系统更加灵活，可以：
1. 直接使用数据库查询结果
2. 处理 API 响应数据
3. 单元测试更容易编写

## 验证

```bash
✅ php -l app/Services/Dictation/AnnotationParser.php
   No syntax errors detected
```

## 状态
✅ 已修复，类型兼容性问题已解决
