@extends('web.layout')

@section('title', '测验结果')
@section('full_width', 'true')

@php
    $score = $result['total'] > 0 ? round($result['correct_count'] / $result['total'] * 100) : 0;
    $scoreAngle = max(0, min(360, $score * 3.6));
    $duration = sprintf('%02d:%02d', intdiv($result['duration_seconds'], 60), $result['duration_seconds'] % 60);
    $typeLabels = [
        'blank' => '补空',
        'next' => '下一句',
        'previous' => '上一句',
        'author_choice' => '作者',
        'annotation_meaning' => '释义',
        'poem_source' => '出处',
        'sentence_order' => '排序',
    ];
    $retryLimit = in_array((int) ($result['limit'] ?? 10), [10, 20], true) ? (int) ($result['limit'] ?? 10) : 10;
    $renderDictationPrompt = function (array $item): string {
        $prompt = preg_replace('/\R{2,}/u', "\n", trim((string) ($item['prompt'] ?? '')));
        if (($item['type'] ?? null) !== 'blank' || $prompt === '') {
            return nl2br(e($prompt));
        }

        $blankPattern = '/(_+|＿+|-{2,}|—{2,})/u';
        preg_match_all($blankPattern, $prompt, $matches, PREG_OFFSET_CAPTURE);
        if (($matches[0] ?? []) === []) {
            return nl2br(e($prompt));
        }

        $groups = $matches[0];
        $cursor = 0;
        $html = '';

        foreach ($groups as [$placeholder, $offset]) {
            if ($offset > $cursor) {
                $html .= nl2br(e(substr($prompt, $cursor, $offset - $cursor)));
            }

            $slotCount = mb_strlen($placeholder, 'UTF-8');
            $answer = preg_replace('/\s+/u', '', (string) ($item['answer'] ?? ''));
            if (count($groups) === 1 && $answer !== '') {
                $slotCount = max(1, mb_strlen($answer, 'UTF-8'));
            }

            $html .= '<span class="dictation-blank-group" aria-label="空 '.$slotCount.' 个字">';
            for ($slot = 0; $slot < $slotCount; $slot++) {
                $html .= '<span class="dictation-blank-slot"></span>';
            }
            $html .= '</span>';

            $cursor = $offset + strlen($placeholder);
        }

        if ($cursor < strlen($prompt)) {
            $html .= nl2br(e(substr($prompt, $cursor)));
        }

        return $html;
    };
@endphp

@section('content')
<div class="dictation-page">
    <div class="dictation-shell dictation-result">
        <div class="dictation-result-hero">
            <div class="dictation-score-ring" style="--score-angle: {{ $scoreAngle }}deg">
                <strong>{{ $score }}%</strong>
                <span>得分</span>
            </div>
            <div class="dictation-result-title">
                <span class="dictation-kicker">{{ $result['grade_name'] }}</span>
                <h1>测验结果</h1>
                <div class="dictation-result-meta">
                    <span>{{ $result['correct_count'] }} / {{ $result['total'] }}</span>
                    <span>{{ $duration }}</span>
                </div>
            </div>
        </div>

        <div class="dictation-stats-grid">
            <div>
                <span>题数</span>
                <strong>{{ $result['total'] }}</strong>
            </div>
            <div>
                <span>答对</span>
                <strong class="is-good">{{ $result['correct_count'] }}</strong>
            </div>
            <div>
                <span>答错</span>
                <strong class="is-bad">{{ $result['wrong_count'] }}</strong>
            </div>
            <div>
                <span>用时</span>
                <strong>{{ $duration }}</strong>
            </div>
        </div>

        <div class="dictation-actions dictation-result-actions">
            <a href="{{ route('dictation.challenge', ['grade_name' => $result['grade_name'], 'limit' => $retryLimit]) }}" class="dictation-primary-button">
                再测一次
            </a>
            <a href="{{ route('dictation.index') }}" class="dictation-secondary-button">
                返回年级
            </a>
        </div>
    </div>

    <div class="dictation-review-list">
        @foreach ($result['items'] as $item)
        <section class="dictation-review-item {{ $item['is_correct'] ? 'is-correct' : 'is-wrong' }}">
            <div class="dictation-review-head">
                <div>
                    <span class="dictation-type-label">{{ $typeLabels[$item['type']] ?? $item['type'] }}</span>
                    <strong>第 {{ $loop->iteration }} 题</strong>
                </div>
                <span class="dictation-result-badge">{{ $item['is_correct'] ? '正确' : '错误' }}</span>
            </div>

            @if (!empty($item['poem_name']))
            <div class="dictation-source">
                {{ $item['poem_name'] }}
            </div>
            @endif

            <div class="dictation-prompt">
                {!! $renderDictationPrompt($item) !!}
            </div>

            @if (!empty($item['options']) && is_array($item['options']))
            <div class="dictation-review-options">
                @foreach ($item['options'] as $option)
                    @php
                        $isChosen = $option === $item['user_answer'];
                        $isAnswer = $option === $item['answer'] || in_array($option, $item['accepted_answers'] ?? [], true);
                    @endphp
                    <div class="dictation-review-option @if($isAnswer) is-answer @elseif($isChosen) is-chosen @endif">
                        {{ $option }}
                    </div>
                @endforeach
            </div>
            @endif

            <div class="dictation-answer-grid">
                <div>
                    <span>你的答案</span>
                    <strong>{{ $item['user_answer'] !== '' ? $item['user_answer'] : '未作答' }}</strong>
                </div>
                <div>
                    <span>正确答案</span>
                    <strong>{{ $item['answer'] }}</strong>
                </div>
            </div>
        </section>
        @endforeach
    </div>
</div>
@endsection
