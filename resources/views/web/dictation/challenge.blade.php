@extends('web.layout')

@section('title', '诗词测验')
@section('full_width', 'true')

@php
    $typeLabels = [
        'blank' => '补空',
        'next' => '下一句',
        'previous' => '上一句',
        'author_choice' => '作者',
        'annotation_meaning' => '释义',
        'poem_source' => '出处',
        'sentence_order' => '排序',
    ];
    $typeInstructions = [
        'blank' => '填写空缺字词',
        'next' => '填写下一句',
        'previous' => '填写上一句',
        'author_choice' => '选择正确作者',
        'annotation_meaning' => '选择正确释义',
        'poem_source' => '选择诗句出处',
        'sentence_order' => '选择正确顺序',
    ];
    $total = count($challenge['questions']);
    $renderDictationPrompt = function (array $question): string {
        $prompt = preg_replace('/\R{2,}/u', "\n", trim((string) ($question['prompt'] ?? '')));
        if (($question['type'] ?? null) !== 'blank' || $prompt === '') {
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
            if (count($groups) === 1 && preg_match('/(\d+)\s*个?字/u', (string) ($question['answer_hint'] ?? ''), $hintMatch)) {
                $slotCount = max(1, (int) $hintMatch[1]);
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
    <form method="POST" action="{{ route('dictation.submit') }}" id="dictation-form" class="dictation-shell dictation-run">
        @csrf
        <input type="hidden" name="challenge_token" value="{{ $challenge['challenge_token'] }}">
        <input type="hidden" name="duration_seconds" id="duration_seconds" value="0">

        <div class="dictation-topbar">
            <a href="{{ route('dictation.index') }}" class="dictation-back-button" aria-label="返回年级选择">‹</a>
            <div>
                <span class="dictation-kicker">{{ $challenge['grade_name'] }}</span>
                <h1>诗词测验</h1>
            </div>
            <div class="dictation-timer" aria-live="polite">
                <span>剩余</span>
                <strong id="dictation-countdown">--:--</strong>
            </div>
        </div>

        <div class="dictation-progress-panel">
            <div class="dictation-progress-copy">
                <span id="dictation-current-text">1 / {{ $total }}</span>
                <span>{{ $total }} 题</span>
            </div>
            <div class="dictation-progress-track" aria-hidden="true">
                <div class="dictation-progress-fill" id="dictation-progress-fill"></div>
            </div>
            @if (($challenge['ttl_seconds'] ?? 0) > 0)
            <div class="dictation-ttl-notice" id="dictation-ttl-notice">
                请在本次题目有效期内完成并提交，剩余 <span id="dictation-ttl-inline">--:--</span>
            </div>
            @endif
            <div class="dictation-question-dots" id="dictation-question-dots" aria-label="题目进度">
                @foreach ($challenge['questions'] as $question)
                <button type="button" class="dictation-question-dot @if($loop->first) is-active @endif" data-question-jump="{{ $loop->index }}" aria-label="第 {{ $loop->iteration }} 题">
                    {{ $loop->iteration }}
                </button>
                @endforeach
            </div>
        </div>

        <div class="dictation-question-stack">
            @foreach ($challenge['questions'] as $index => $question)
            @php
                $metaParts = [];
                if (($question['type'] ?? null) !== 'poem_source' && !empty($question['poem_name'])) {
                    $metaParts[] = $question['poem_name'];
                }
                if (($question['type'] ?? null) !== 'author_choice' && !empty($question['author_name'])) {
                    $metaParts[] = $question['author_name'];
                }
            @endphp
            <section class="dictation-question @if($loop->first) is-active @endif" data-question-index="{{ $index }}">
                <div class="dictation-question-head">
                    <div class="dictation-question-meta">
                        <span class="dictation-type-label">{{ $typeLabels[$question['type']] ?? $question['type'] }}</span>
                        <span class="dictation-instruction">{{ $typeInstructions[$question['type']] ?? '填写答案' }}</span>
                    </div>
                    <span class="dictation-question-count">第 {{ $loop->iteration }} / {{ $total }} 题</span>
                </div>

                @if ($metaParts !== [])
                <div class="dictation-source">
                    {{ implode(' · ', $metaParts) }}
                </div>
                @endif

                <div class="dictation-prompt">
                    {!! $renderDictationPrompt($question) !!}
                </div>

                <input type="hidden" name="answers[{{ $index }}][question_id]" value="{{ $question['question_id'] }}">
                @if (!empty($question['instance_token']))
                <input type="hidden" name="answers[{{ $index }}][instance_token]" value="{{ $question['instance_token'] }}">
                @endif

                @if (!empty($question['options']) && is_array($question['options']))
                <div class="dictation-options">
                    @foreach ($question['options'] as $option)
                    <label class="dictation-option">
                        <input type="radio" name="answers[{{ $index }}][user_answer]" value="{{ $option }}">
                        <span class="dictation-option-mark">{{ chr(65 + $loop->index) }}</span>
                        <span class="dictation-option-text">{{ $option }}</span>
                    </label>
                    @endforeach
                </div>
                @else
                <div class="dictation-answer-wrap">
                    <input type="text" name="answers[{{ $index }}][user_answer]" autocomplete="off" class="dictation-answer-input" placeholder="输入答案">
                </div>
                @endif

                <div class="dictation-question-tools">
                    <span class="dictation-answer-status" data-answer-status>未作答</span>
                    <button type="button" class="dictation-clear-answer hidden" data-clear-answer>清空</button>
                </div>
            </section>
            @endforeach
        </div>

        <div class="dictation-actions">
            <button type="button" class="dictation-secondary-button" id="dictation-prev">上一题</button>
            <button type="button" class="dictation-primary-button" id="dictation-next">下一题</button>
            <button type="submit" class="dictation-primary-button hidden" id="dictation-submit">提交答案</button>
        </div>
    </form>
</div>
@endsection

@section('script')
<script>
    (() => {
        const startedAt = Date.now();
        const countdownSeconds = Number(@json($challenge['ttl_seconds'] ?? 0));
        const countdownEl = document.getElementById('dictation-countdown');
        const countdownInlineEl = document.getElementById('dictation-ttl-inline');
        const ttlNoticeEl = document.getElementById('dictation-ttl-notice');
        const durationInput = document.getElementById('duration_seconds');
        const form = document.getElementById('dictation-form');
        const questions = Array.from(document.querySelectorAll('[data-question-index]'));
        const dots = Array.from(document.querySelectorAll('[data-question-jump]'));
        const progressFill = document.getElementById('dictation-progress-fill');
        const currentText = document.getElementById('dictation-current-text');
        const prevButton = document.getElementById('dictation-prev');
        const nextButton = document.getElementById('dictation-next');
        const submitButton = document.getElementById('dictation-submit');
        let currentIndex = 0;

        const formatSeconds = (seconds) => {
            const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
            const rest = Math.floor(seconds % 60).toString().padStart(2, '0');

            return `${minutes}:${rest}`;
        };

        const updateTimer = () => {
            const elapsed = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
            const remaining = countdownSeconds > 0 ? Math.max(0, countdownSeconds - elapsed) : elapsed;

            if (countdownEl) {
                countdownEl.textContent = formatSeconds(remaining);
            }

            if (countdownInlineEl) {
                countdownInlineEl.textContent = formatSeconds(remaining);
            }

            if (ttlNoticeEl) {
                ttlNoticeEl.classList.toggle('is-expired', countdownSeconds > 0 && remaining <= 0);
            }

            if (durationInput) {
                durationInput.value = elapsed.toString();
            }
        };

        const answerValue = (question) => {
            const answerInputs = Array.from(question.querySelectorAll('input[name$="[user_answer]"]'));
            const checked = answerInputs.find((input) => input.type === 'radio' && input.checked);
            if (checked) {
                return checked.value;
            }

            const textInput = answerInputs.find((input) => input.type !== 'radio');
            return textInput ? textInput.value.trim() : '';
        };

        const isAnswered = (question) => {
            return answerValue(question) !== '';
        };

        const refreshQuestionState = (question) => {
            const value = answerValue(question);
            const radioInputs = Array.from(question.querySelectorAll('input[type="radio"][name$="[user_answer]"]'));
            const hasChoices = radioInputs.length > 0;
            const statusEl = question.querySelector('[data-answer-status]');
            const clearButton = question.querySelector('[data-clear-answer]');

            question.querySelectorAll('.dictation-option').forEach((option) => {
                const input = option.querySelector('input[type="radio"]');
                option.classList.toggle('is-selected', !!input?.checked);
            });

            if (statusEl) {
                statusEl.textContent = hasChoices
                    ? (value ? `已选择：${value}` : '未选择')
                    : `${value.length} 字`;
            }

            if (clearButton) {
                clearButton.classList.toggle('hidden', value === '');
            }
        };

        const unansweredIndexes = () => questions
            .map((question, index) => isAnswered(question) ? -1 : index)
            .filter((index) => index >= 0);

        const nextOrSubmit = () => {
            if (currentIndex < questions.length - 1) {
                showQuestion(currentIndex + 1);
                return;
            }

            if (typeof form?.requestSubmit === 'function') {
                form.requestSubmit(submitButton);
            } else {
                submitButton?.click();
            }
        };

        const showQuestion = (index) => {
            if (!questions.length) {
                return;
            }

            currentIndex = Math.min(Math.max(index, 0), questions.length - 1);

            questions.forEach((question, questionIndex) => {
                question.classList.toggle('is-active', questionIndex === currentIndex);
                refreshQuestionState(question);
            });

            dots.forEach((dot, dotIndex) => {
                const answered = isAnswered(questions[dotIndex]);
                dot.classList.toggle('is-active', dotIndex === currentIndex);
                dot.classList.toggle('is-answered', answered);
                dot.setAttribute('aria-current', dotIndex === currentIndex ? 'step' : 'false');
            });

            if (progressFill) {
                progressFill.style.width = `${((currentIndex + 1) / questions.length) * 100}%`;
            }

            if (currentText) {
                currentText.textContent = `${currentIndex + 1} / ${questions.length}`;
            }

            if (prevButton) {
                prevButton.disabled = currentIndex === 0;
            }

            if (nextButton && submitButton) {
                const isLast = currentIndex === questions.length - 1;
                nextButton.classList.toggle('hidden', isLast);
                submitButton.classList.toggle('hidden', !isLast);
            }
        };

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                showQuestion(Number(dot.dataset.questionJump));
            });
        });

        questions.forEach((question) => {
            question.addEventListener('input', () => showQuestion(currentIndex));
            question.addEventListener('change', () => showQuestion(currentIndex));

            question.querySelector('[data-clear-answer]')?.addEventListener('click', () => {
                question.querySelectorAll('input[name$="[user_answer]"]').forEach((input) => {
                    if (input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
                showQuestion(currentIndex);
            });
        });

        prevButton?.addEventListener('click', () => showQuestion(currentIndex - 1));
        nextButton?.addEventListener('click', nextOrSubmit);

        form?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.isComposing) {
                return;
            }

            const target = event.target;
            if (!(target instanceof HTMLInputElement) || !target.matches('input[name$="[user_answer]"]')) {
                return;
            }

            event.preventDefault();

            if (target.type === 'radio') {
                target.checked = true;
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }

            nextOrSubmit();
        });

        form?.addEventListener('submit', (event) => {
            updateTimer();

            const missing = unansweredIndexes();
            if (missing.length > 0 && !window.confirm(`还有 ${missing.length} 题未作答，确定提交吗？`)) {
                event.preventDefault();
                showQuestion(missing[0]);
            }
        });

        updateTimer();
        setInterval(updateTimer, 1000);
        showQuestion(0);
    })();
</script>
@endsection
