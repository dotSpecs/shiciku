<?php

namespace App\Services\Dictation;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DeepSeek AI 服务
 * 用于生成注释理解题干扰项。
 */
class DeepSeekAIService
{
    private string $apiKey;

    private string $apiUrl;

    private string $model;

    private int $timeout;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $this->apiKey = (string) config('services.deepseek.api_key', '');
        $this->apiUrl = (string) config('services.deepseek.api_url', 'https://api.deepseek.com/chat/completions');
        $this->model = (string) config('services.deepseek.model', 'deepseek-chat');
        $this->timeout = (int) config('services.deepseek.timeout', 60);
        $this->maxTokens = (int) config('services.deepseek.max_tokens', 1024);
        $this->temperature = (float) config('services.deepseek.temperature', 0.2);
    }

    /**
     * 生成注释理解题的干扰项。
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    public function generateAnnotationDistractors(string $word, string $correctMeaning, string $sentence): array
    {
        $forbiddenMeanings = implode('、', $this->forbiddenMeanings($word, $correctMeaning));

        $prompt = <<<PROMPT
请为小学古诗词选择题生成10个候选错误释义，系统会从中挑选3个入题。

字词：{$word}
正确释义：{$correctMeaning}
所在诗句：{$sentence}
禁用释义：{$forbiddenMeanings}

要求：
1. 每个干扰项都必须是小学老师不能判对的错误释义。
2. 禁止使用正确释义的同义词、近义词、换一种说法、具体化动作或具体化结果。
3. 如果某个选项放回原诗句也说得通，或学生选择它也应给分，必须排除。
4. 干扰项应选择该字词在其他语境下的不同义项，和原句语境有清楚区别。
5. 不要使用“受惊而飞”这类把“吃惊，害怕”具体化的选项；不要使用“拨动”“拨开”这类把“划动”换说法的选项。
6. 只返回合法 JSON，不要 markdown，不要解释。

返回格式：
{"distractors":["候选1","候选2","候选3","候选4","候选5","候选6","候选7","候选8","候选9","候选10"]}
PROMPT;

        $distractors = [];
        $startedAt = microtime(true);

        try {
            $result = $this->callDeepSeek($prompt);
            $distractors = $this->filterAnnotationDistractors(
                $this->stringList($result['distractors'] ?? $result),
                $word,
                $correctMeaning
            );
            $this->reportAnnotationTiming($word, 'ok', $startedAt, [
                'distractors' => count($distractors),
            ]);
        } catch (Exception $e) {
            $this->reportAnnotationTiming($word, 'fallback', $startedAt, [
                'error' => $e->getMessage(),
            ]);

            Log::warning('Using fallback annotation distractors after DeepSeek failure', [
                'word' => $word,
                'error' => $e->getMessage(),
            ]);
        }

        $distractors = $this->filterAnnotationDistractors(
            array_merge($distractors, $this->fallbackAnnotationDistractors($word, $correctMeaning)),
            $word,
            $correctMeaning
        );

        if (count($distractors) < 3) {
            throw new Exception('DeepSeek returned insufficient annotation distractors');
        }

        return array_slice($distractors, 0, 3);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reportAnnotationTiming(string $word, string $status, float $startedAt, array $context = []): void
    {
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $payload = [
            'word' => $word,
            'status' => $status,
            'elapsed_ms' => $elapsedMs,
            ...$context,
        ];

        Log::info('DeepSeek annotation distractor timing', $payload);

        if (! app()->runningInConsole() || app()->runningUnitTests() || ! defined('STDERR')) {
            return;
        }

        $message = sprintf(
            '[AI] annotation word=%s status=%s elapsed=%.2fs',
            $word,
            $status,
            $elapsedMs / 1000
        );

        if (isset($context['distractors'])) {
            $message .= ' distractors='.$context['distractors'];
        }

        if (isset($context['error'])) {
            $message .= ' error='.$context['error'];
        }

        fwrite(STDERR, $message.PHP_EOL);
    }

    /**
     * @param  array<int, string>  $distractors
     * @return array<int, string>
     */
    private function filterAnnotationDistractors(array $distractors, string $word, string $correctMeaning): array
    {
        $correct = $this->normalizedMeaning($correctMeaning);
        $forbidden = array_map(
            fn (string $meaning) => $this->normalizedMeaning($meaning),
            $this->forbiddenMeanings($word, $correctMeaning)
        );

        return array_values(array_filter($distractors, function (string $distractor) use ($correct, $forbidden) {
            $candidate = $this->normalizedMeaning($distractor);
            if ($candidate === '') {
                return false;
            }

            if ($candidate === $correct || str_contains($candidate, $correct) || str_contains($correct, $candidate)) {
                return false;
            }

            foreach ($forbidden as $meaning) {
                if ($meaning === '') {
                    continue;
                }

                if ($candidate === $meaning || str_contains($candidate, $meaning) || str_contains($meaning, $candidate)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @return array<int, string>
     */
    private function fallbackAnnotationDistractors(string $word, string $correctMeaning): array
    {
        $byWord = [
            '何' => ['什么', '哪里', '为什么', '怎样'],
            '田田' => ['田地很多', '田野宽阔', '农田整齐', '田间小路'],
            '曲项' => ['曲调名称', '项目弯曲', '弯曲的道路', '低头弯腰'],
            '歌' => ['歌曲', '诗歌', '歌颂', '歌谣'],
            '拨' => ['分给，调配', '用手指弹', '掉转方向', '挑拨'],
            '对' => ['回答', '正确', '向着，朝着', '核对'],
            '色' => ['神色，脸色', '种类', '女色', '佛教指一切物质现象'],
            '惊' => ['惊动，打扰', '警醒', '震动', '声音很大'],
            '禾' => ['幼苗', '稻草', '田地', '农具'],
            '餐' => ['吃饭', '饭食', '量词，一顿', '餐具'],
            '呼作' => ['呼喊起来', '呼吸动作', '呼唤别人', '大声叫喊'],
            '白玉盘' => ['白色玉石', '玉制托盘', '月亮周围的云', '盛食物的盘子'],
            '疑' => ['疑问', '迟疑', '猜测', '怀恨'],
            '瑶台' => ['用玉装饰的台阶', '高高的楼台', '宫殿前的平台', '唱戏的舞台'],
            '解落' => ['解开绳结', '理解落下', '解除职务', '分解脱落'],
            '解' => ['解开', '解释', '了解', '解除'],
            '三秋' => ['三个秋天', '深秋时节', '三年时间', '秋天三次'],
            '能' => ['才能', '能力', '有才能', '能量'],
            '二月' => ['第二个月', '两个月', '二更时分', '两轮月亮'],
            '过' => ['去世', '过失', '超过', '拜访'],
            '斜' => ['弯曲', '倒下', '交错', '偏僻'],
        ];

        return array_values(array_unique(array_merge(
            $byWord[$word] ?? [],
            $this->meaningCategoryDistractors($correctMeaning),
            $this->genericAnnotationDistractors()
        )));
    }

    /**
     * @return array<int, string>
     */
    private function meaningCategoryDistractors(string $correctMeaning): array
    {
        $meaning = $this->normalizedMeaning($correctMeaning);
        $categories = [
            '/月|日|年|季|春|夏|秋|冬|早|晚|晨|暮|夜|旦|夕/u' => ['地名', '官职名', '乐器名', '颜色', '动作缓慢', '心中忧愁'],
            '/山|水|江|河|湖|海|溪|洲|岸|峰|岭|台|楼|阁|亭/u' => ['时间很久', '官职名', '声音很大', '心中害怕', '回答问题', '颜色鲜明'],
            '/草|木|花|叶|禾|苗|竹|松|柳|梅|菊|莲|稻|麦/u' => ['古代官职', '一种乐器', '道路弯曲', '心中疑惑', '声音响亮', '回答正确'],
            '/鸟|马|鱼|雁|燕|犬|鸡|鹿|猿|龙|凤/u' => ['植物名称', '古代地名', '官职名称', '颜色暗淡', '时间短暂', '解开绳结'],
            '/衣|裳|冠|带|履|巾|袍|衫|袖/u' => ['山水名称', '古代乐器', '吃饭', '惊动打扰', '回答正确', '春季'],
            '/酒|杯|樽|觞|餐|饭|食|羹|浆/u' => ['衣服名称', '古代地名', '官职名称', '道路弯曲', '颜色鲜明', '声音很大'],
            '/官|侯|君|王|帝|相|守|郎|将|尉|吏/u' => ['植物名称', '一种乐器', '饭食', '水边陆地', '时间很久', '颜色'],
            '/愁|悲|哀|怨|恨|忧|伤|苦/u' => ['高兴快乐', '颜色鲜明', '道路弯曲', '古代官职', '一种植物', '回答正确'],
            '/喜|乐|欢|悦|快/u' => ['忧愁悲伤', '古代地名', '动作缓慢', '颜色暗淡', '解开绳结', '饭食'],
            '/清|明|白|净|澄|洁/u' => ['昏暗不明', '声音很大', '官职名称', '时间长久', '植物名称', '吃饭'],
            '/黑|暗|暮|暝|阴/u' => ['清澈明亮', '回答问题', '古代乐器', '饭食', '春季', '道路弯曲'],
            '/行|走|去|来|至|到|归|还|过|入|出|登|临/u' => ['颜色', '官职名', '乐器名', '饭食', '心中忧愁', '植物名称'],
            '/看|望|视|见|观|顾/u' => ['吃饭', '古代官职', '一种植物', '声音很大', '时间很久', '解开'],
            '/说|言|语|曰|谓|呼|问|答/u' => ['行走', '颜色', '植物名称', '古代地名', '吃饭', '弯曲'],
            '/多|盛|繁|满|众|广|阔/u' => ['稀少', '官职名', '一种乐器', '心中害怕', '回答正确', '饭食'],
            '/少|稀|微|细|小/u' => ['众多', '山水名称', '古代官职', '声音很大', '春季', '吃饭'],
        ];

        $distractors = [];
        foreach ($categories as $pattern => $items) {
            if (preg_match($pattern, $meaning) === 1) {
                $distractors = array_merge($distractors, $items);
            }
        }

        return array_values(array_unique($distractors));
    }

    /**
     * @return array<int, string>
     */
    private function genericAnnotationDistractors(): array
    {
        return [
            '什么',
            '哪里',
            '为什么',
            '怎样',
            '回答',
            '正确',
            '向着，朝着',
            '种类',
            '神色，脸色',
            '颜色',
            '声音很大',
            '去世',
            '过失',
            '拜访',
            '解释',
            '了解',
            '解除',
            '解开',
            '才能',
            '能力',
            '可以',
            '高台',
            '官职名',
            '古代地名',
            '一种乐器',
            '饭食',
            '吃饭',
            '餐具',
            '农具',
            '田地',
            '植物名称',
            '水边陆地',
            '道路弯曲',
            '道路平直',
            '时间很久',
            '时间短暂',
            '春季',
            '秋季',
            '早晨',
            '傍晚',
            '独自',
            '一起',
            '慢慢地',
            '忽然',
            '心中忧愁',
            '高兴快乐',
            '心中害怕',
            '清澈明亮',
            '昏暗不明',
            '繁盛茂密',
            '稀少',
            '弯曲',
            '倒下',
            '飘动',
            '遮盖',
            '关闭',
            '打开',
            '分给，调配',
            '用手指弹',
            '挑拨',
            '歌颂',
            '诗歌',
            '怀疑',
            '迟疑',
            '警醒',
            '震动',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function forbiddenMeanings(string $word, string $correctMeaning): array
    {
        $normalized = $this->normalizedMeaning($correctMeaning);
        $groups = [
            ['划动', '拨动', '拨开', '划开', '划水', '搅动', '推动', '划拉'],
            ['吃惊', '害怕', '受惊', '受惊而飞', '惊吓', '惊怕', '惊惧', '惊恐', '惊慌', '惊奇', '诧异', '恐惧', '畏惧', '惧怕'],
            ['怀疑', '疑惑', '猜测', '以为', '认为', '疑心'],
            ['能够', '能', '可以', '才能', '能力', '有才能', '能做到'],
            ['倾斜', '斜', '歪斜', '偏斜', '斜着', '弯曲', '倒下'],
        ];

        $forbidden = [$correctMeaning];
        foreach ($groups as $group) {
            foreach ($group as $term) {
                if (str_contains($normalized, $this->normalizedMeaning($term))) {
                    $forbidden = array_merge($forbidden, $group);
                    break;
                }
            }
        }

        if ($word === '拨' && str_contains($normalized, '动')) {
            $forbidden = array_merge($forbidden, $groups[0]);
        }

        if ($word === '惊' && preg_match('/惊|怕|惧|恐/u', $normalized) === 1) {
            $forbidden = array_merge($forbidden, $groups[1]);
        }

        return array_values(array_unique(array_filter(array_map('trim', $forbidden))));
    }

    private function normalizedMeaning(string $meaning): string
    {
        $meaning = mb_strtolower($meaning, 'UTF-8');

        return preg_replace('/[\s　，,。！？；;、：:"“”‘’\'()（）【】\[\]《》<>]+/u', '', $meaning) ?: '';
    }

    /**
     * @return array<string|int, mixed>
     *
     * @throws Exception
     */
    private function callDeepSeek(string $prompt): array
    {
        if ($this->apiKey === '') {
            throw new Exception('DeepSeek API key is not configured. Please set DEEPSEEK_API_KEY in .env');
        }

        try {
            $response = $this->request($prompt);

            if (! $response->successful()) {
                $body = $response->body();
                Log::error('DeepSeek API request failed', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                throw new Exception("DeepSeek API request failed: {$body}");
            }

            $content = $this->responseText($response->json() ?? []);
            if ($content === '') {
                throw new Exception('DeepSeek API returned empty content');
            }

            Log::info('get deepseek ai content', [
                'content' => $content,
            ]);

            $result = $this->extractJson($content);
            if ($result === []) {
                Log::warning('Failed to parse DeepSeek response as JSON', [
                    'content' => $content,
                ]);

                throw new Exception('Failed to parse DeepSeek response as JSON');
            }

            return $result;
        } catch (Exception $e) {
            Log::error('DeepSeek API call failed', [
                'model' => $this->model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function request(string $prompt): Response
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '你是严谨的小学古诗词题库编辑。必须只输出合法 JSON。',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'stream' => false,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function responseText(array $data): string
    {
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            return trim(implode('', array_map(
                fn (mixed $part) => is_array($part) ? (string) ($part['text'] ?? '') : (string) $part,
                $content
            )));
        }

        return '';
    }

    /**
     * @return array<string|int, mixed>
     */
    private function extractJson(string $text): array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        foreach ([['{', '}'], ['[', ']']] as [$start, $end]) {
            $startPosition = strpos($text, $start);
            $endPosition = strrpos($text, $end);

            if ($startPosition === false || $endPosition === false || $endPosition <= $startPosition) {
                continue;
            }

            $candidate = substr($text, $startPosition, $endPosition - $startPosition + 1);
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item) => is_string($item) || is_numeric($item) ? trim((string) $item) : '',
            $value
        ))));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }
}
