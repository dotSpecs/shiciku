@extends('web.layout')

@section('title', '诗词测验')
@section('full_width', 'true')

@section('content')
<div class="dictation-page">
    <div class="dictation-shell dictation-home">
        <div class="dictation-home-grid">
            <div class="dictation-home-title">
                <span class="dictation-kicker">Web 练习</span>
                <h1>诗词测验</h1>
                <div class="dictation-home-meta">
                    <span>混合题</span>
                    <span>10 / 20 题</span>
                    <span>限时 30 分钟</span>
                </div>
            </div>

            <div class="dictation-start-panel">
                @if (session('dictation_error'))
                <div class="dictation-alert">
                    {{ session('dictation_error') }}
                </div>
                @endif

                @if ($errors->any())
                <div class="dictation-alert">
                    {{ $errors->first() }}
                </div>
                @endif

                @if ($grades === [])
                <div class="dictation-empty">题库暂未生成</div>
                @else
                <form method="GET" action="{{ route('dictation.challenge') }}" class="dictation-start-form">
                    <div class="dictation-field">
                        <label for="grade_name">年级册</label>
                        <select id="grade_name" name="grade_name" class="dictation-select">
                            @foreach ($grades as $grade)
                            <option value="{{ $grade }}" @selected(old('grade_name', request('grade_name')) === $grade)>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="dictation-field">
                        <label for="limit">题量</label>
                        <select id="limit" name="limit" class="dictation-select">
                            <option value="10" @selected((int) old('limit', request('limit', 10)) === 10)>10 题</option>
                            <option value="20" @selected((int) old('limit', request('limit', 10)) === 20)>20 题</option>
                        </select>
                    </div>

                    <div class="dictation-setup-grid">
                        <div>
                            <span>题型</span>
                            <strong>混合</strong>
                        </div>
                    </div>

                    <button type="submit" class="dictation-primary-button">
                        开始测验
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
