@extends('web.layout')

@section('title', $article->book->name . '·' . ($article->chapter && $article->chapter->name ? $article->chapter->name. '·' : '') . $article->name . '的原文、注释、翻译、赏析')

@section('keywords', $article->book->name . ',' . ($article->chapter && $article->chapter->name ? $article->chapter->name. ',' : '') . $article->name . ',')
@section('description', $article->book->name . '·' . ($article->chapter && $article->chapter->name ? $article->chapter->name. '·' : '') . $article->name . '的原文、注释、翻译、赏析,')

@section('content')
<div class="card mb-8">
    <div class="card-title flex items-center justify-between @if(empty($article->book->author)) !mb-0 @endif">
        <h1 class="text-xl">{{ $article->book->name }}</h1>
        <a href="{{ route('book.show', $article->book->book_id) }}" class="link text-sm primary">返回目录</a>
    </div>

    <div class="card-content">
        @if($article->book->author)
        <p class="secondary">
            作者：<a class="link secondary" href="{{ route('author.show', $article->book->author->author_id) }}" id="poem-author">{{ $article->book->author->name }}</a>
        </p>
        @endif
    </div>
</div>

<div class="card @if($article->metadatas->count() > 0) mb-8 @endif">
    <div class="flex justify-between items-center mb-5">
        <h2 class="card-title text-xl !mb-0">
            <span id="poem-title">{{ $article->name }}</span>
            <span class="text-base font-normal text-gray-500">原文</span>
        </h2>
        <div class="flex gap-2">
            <span class="badge cursor-pointer" id="readAloudBtn" onclick="handleReadAloud()">朗读</span>
            <span class="badge cursor-pointer" onclick="togglePinyin()">拼音</span>
        </div>
    </div>
    
    <div class="card-content escape-html leading-10 [&>p]:mb-6" id="poem-content">
        {!! $article->content !!}
    </div>

    <!-- Audio Player Container -->
    <div id="audioPlayerContainer" class="mt-6 p-4 bg-slate-50 dark:bg-slate-700 rounded-md" style="display: none;">
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium">正在朗读...</span>
                <button onclick="closeAudioPlayer()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <audio id="audioPlayer" controls class="w-full h-10"></audio>
        </div>
    </div>

    <script>
        window.poemData = {
            title: @json($article->name),
            dynasty: '',
            author: @json($article->book->author ? $article->book->author->name : ''),
            content: @json($article->content)
        };

        // Audio Manager Class (Inline for simplicity or reuse existing if global)
        // Since we need to point to the book audio route, we might need a custom handler or reuse common logic with URL override
        
        let isLoadingAudio = false;
        let audioLoaded = false;

        async function handleReadAloud() {
            if (isLoadingAudio || audioLoaded) return;

            const btn = document.getElementById('readAloudBtn');
            const playerContainer = document.getElementById('audioPlayerContainer');
            const player = document.getElementById('audioPlayer');
            
            // Set button to loading state
            isLoadingAudio = true;
            btn.textContent = '获取中...';
            btn.classList.add('cursor-not-allowed', 'opacity-50');
            
            try {
                const response = await fetch("{{ route('book.audio', ['book_id' => $article->book->book_id, 'article_id' => $article->article_id]) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (data.status === 'success' && data.body) {
                    // Convert base64 audio data to blob URL
                    const audioBlob = base64ToBlob(data.body, 'audio/mpeg');
                    const audioUrl = URL.createObjectURL(audioBlob);
                    
                    player.src = audioUrl;
                    playerContainer.style.display = 'block';
                    player.play();
                    
                    // Update state
                    audioLoaded = true;
                    btn.textContent = '已加载';
                    // Keep disabled style
                } else {
                    alert('获取音频失败：' + (data.message || '未知错误'));
                    // Reset button if failed
                    btn.classList.remove('cursor-not-allowed', 'opacity-50');
                    btn.textContent = '朗读';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('获取音频出错');
                // Reset button if error
                btn.classList.remove('cursor-not-allowed', 'opacity-50');
                btn.textContent = '朗读';
            } finally {
                isLoadingAudio = false;
            }
        }
        
        // Helper function to convert Base64 to Blob
        function base64ToBlob(base64, mimeType) {
            const byteCharacters = atob(base64);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            const byteArray = new Uint8Array(byteNumbers);
            return new Blob([byteArray], { type: mimeType });
        }

        function closeAudioPlayer() {
            const playerContainer = document.getElementById('audioPlayerContainer');
            const player = document.getElementById('audioPlayer');
            player.pause();
            player.currentTime = 0;
            playerContainer.style.display = 'none';
        }
    </script>
</div>

@foreach ($article->metadatas as $metadata)
<div class="article-metadata card @if(!$loop->last) mb-8 @endif">
    <h2 class="article-metadata-title card-title">{{ $metadata->title }}</h2>
    <div class="article-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach

@if($article->previous || $article->next)
<div class="card mt-8">
    <div class="flex justify-between items-center">
        @if($article->previous)
        <a href="{{ route('book.article', ['book_id' => $article->book->book_id, 'article_id' => $article->previous->article_id]) }}" class="link primary">上一篇：{{ $article->previous->name }}</a>
        @else
        <span></span>
        @endif

        @if($article->next)
        <a href="{{ route('book.article', ['book_id' => $article->book->book_id, 'article_id' => $article->next->article_id]) }}" class="link primary">下一篇：{{ $article->next->name }}</a>
        @else
        <span></span>
        @endif
    </div>
</div>
@endif

@endsection


@section('sidebar')
<x-sidebar.book-article :book-id="$article->book->book_id" :article-id="$article->article_id" class="mb-8" />

<x-sidebar.hot-book class="mb-8" />

<x-sidebar.hot-tag />
@endsection