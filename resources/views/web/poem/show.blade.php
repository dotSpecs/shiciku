@extends('web.layout')

@section('title', $poem->name . '的原文、注释、翻译、赏析、序' . ($poem->dynasty ? ' - 【' . $poem->dynasty->name .'】' : '') . ($poem->author ? $poem->author->name : ''))

@section('keywords', $poem->name . ',' . ($poem->dynasty ? $poem->dynasty->name . ',' : '') . ($poem->author ? $poem->author->name . ',' : ''))
@section('description', $poem->name . '的原文、注释、翻译、赏析、序,' . ($poem->dynasty ? $poem->dynasty->name  : '') . ($poem->author ? $poem->author->name . '的' : '') . '诗词,')


@section('content')
<div class="poem card mb-8">
    <h1 class="poem-name card-title flex items-center justify-between">
        {{-- <a href="{{ route('poem.show', poem_slug($poem)) }}" class="link text-xl"> --}}
            <span id="poem-title">{{ $poem->name }}</span>
        {{-- </a> --}}

        <div class="flex gap-2">
            <span class="badge cursor-pointer" id="readAloudBtn" onclick="handleReadAloud()">朗读</span>
            <span class="badge cursor-pointer" onclick="togglePinyin()">拼音</span>
            <span class="badge cursor-pointer @if(empty($poem->yizhu)) !hidden @endif" onclick="toggleYizhu()">译注</span>
        </div>
    </h1>
    <div class="card-content ">
        <div class="poem-info my-2 secondary">
            @if($poem->dynasty)
            <a href="{{ route('poem.index', ['dynasty_id' => $poem->dynasty->id]) }}" class="link secondary" id="poem-dynasty">
                {{ $poem->dynasty->name }}
            </a> ·
            @endif
            @if($poem->author)
            <a href="{{ route('author.show', $poem->author->author_id) }}" class="link secondary" id="poem-author">
                {{ $poem->author->name }}
            </a>
            @else
            <span id="poem-author">佚名</span>
            @endif
        </div>
        <div class="poem-content escape-html leading-10 [&>p]:mb-6" id="poem-content">{!! $poem->content !!}</div>
        <div class="poem-yizhu-content escape-html leading-10 [&>p]:mb-6 hidden">{!! $poem->yizhu !!}</div>

        <script>
            window.poemData = {
                title: @json($poem->name),
                dynasty: @json($poem->dynasty ? $poem->dynasty->name : ''),
                author: @json($poem->author ? $poem->author->name : '佚名'),
                content: @json($poem->content)
            };
        </script>
        
        <!-- Audio Player Container -->
        <div id="audioPlayerContainer" class="mt-6 p-4 bg-slate-50 dark:bg-slate-700 rounded-md" style="display: none;">
            <audio id="audioPlayer" controls class="w-full">
                您的浏览器不支持音频播放。
            </audio>
        </div>
    </div>
    <div class="card-content poem-tags mt-8" @if($poem->tags->isEmpty()) style="display: none;" @endif>
        所属合集：
        @foreach ($poem->tags as $tag)
        <a href="{{ route('poem.index', ['tag_id' => $tag->id]) }}" class="link badge">{{ $tag->name }}</a>
        @endforeach
    </div>
</div>

@if ($poem->quotes->count() > 0)
<div class="card mb-8">
    <h2 class="card-title">名句</h2>
    <div class="card-content">
        <ul class="marker:text-red-500 list-disc ps-5 space-y-2 ">
            @foreach ($poem->quotes as $quote)
            <li class="quote">{{ $quote->mingju }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@foreach ($poem->metadatas as $metadata)
<div class="poem-metadata card @if(!$loop->last || $poem->author) mb-8 @endif">
    <h2 class="poem-metadata-title card-title">{{ $metadata->title }}</h2>
    <div class="poem-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach

@if($poem->author)
    <div class="card py-8">
        @if($poem->author->pic)
        <img class="w-20 h-auto rounded-md mr-4 float-left" src="{{ $poem->author->pic }}" alt="{{ $poem->author->name }}">
        @endif

        <h2 class="text-lg mb-3">
            <a class="link" href="{{ route('author.show', $poem->author->author_id) }}">{{ $poem->author->name }}</a>
        </h2>
        <div class="author-content escape-html leading-10 [&>p]:mb-6">
            {!! $poem->author->content !!}
        </div>
    </div>
@endif

@endsection


@section('sidebar')
@if($poem->author)
<x-sidebar.author-poem :author-id="$poem->author->author_id" class="mb-8" />
@endif
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-book />
@endsection

@section('script')
<script>
    function toggleYizhu() {
        const poemContent = document.querySelector('.poem-content');
        const yizhuContent = document.querySelector('.poem-yizhu-content');
        poemContent.classList.toggle('hidden');
        yizhuContent.classList.toggle('hidden');
    }

    let isLoadingAudio = false;
    let audioLoaded = false;

    async function handleReadAloud() {
        if (isLoadingAudio || audioLoaded) {
            return;
        }

        const btn = document.getElementById('readAloudBtn');
        const audioPlayerContainer = document.getElementById('audioPlayerContainer');
        const audioPlayer = document.getElementById('audioPlayer');
        
        // 设置按钮为加载状态
        isLoadingAudio = true;
        btn.textContent = '获取中';
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        btn.style.pointerEvents = 'none';

        try {
            const response = await fetch('/poem/{{ $poem->poem_id }}/audio', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            // 获取响应文本并清理可能的 PHP Notice
            const responseText = await response.text();
            
            // 提取 JSON 部分（从第一个 { 到最后一个 }）
            const jsonMatch = responseText.match(/\{[\s\S]*\}/);
            if (!jsonMatch) {
                throw new Error('Invalid response format');
            }
            
            const data = JSON.parse(jsonMatch[0]);

            if (data.status === 'success') {
                // 将base64音频数据转换为可播放的URL
                const audioBlob = base64ToBlob(data.body, 'audio/mpeg');
                const audioUrl = URL.createObjectURL(audioBlob);
                
                // 设置音频源并显示播放器
                audioPlayer.src = audioUrl;
                audioPlayerContainer.style.display = 'block';
                
                // 自动播放
                audioPlayer.play();
                
                // 更新状态
                audioLoaded = true;
                btn.textContent = '已加载';
            } else {
                // 错误处理
                alert(data.message || '获取音频失败，请稍后重试');
                btn.textContent = '朗读';
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                btn.style.pointerEvents = 'auto';
            }
        } catch (error) {
            console.error('Error fetching audio:', error);
            alert('获取音频失败，请稍后重试');
            btn.textContent = '朗读';
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            btn.style.pointerEvents = 'auto';
        } finally {
            isLoadingAudio = false;
        }
    }

    // Base64转Blob的辅助函数
    function base64ToBlob(base64, mimeType) {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: mimeType });
    }
</script>
@endsection