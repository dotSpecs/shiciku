@extends('web.layout')

@php
    $displayDynasty = $article->book->dynasty?->name ?: $article->book->chaodai;
    $displayAuthor = $article->book->author?->name ?: $article->book->author_name;
@endphp

@section('title', $article->book->name . '·' . ($article->chapter && $article->chapter->name ? $article->chapter->name. '·' : '') . $article->name . '的原文、注释、翻译、赏析')

@section('keywords', $article->book->name . ',' . ($displayAuthor ? $displayAuthor . ',' : '') . ($article->chapter && $article->chapter->name ? $article->chapter->name. ',' : '') . $article->name . ',')
@section('description', $article->book->name . '·' . ($article->chapter && $article->chapter->name ? $article->chapter->name. '·' : '') . $article->name . '的原文、注释、翻译、赏析,' . ($displayAuthor ? $displayAuthor . ',' : ''))

@section('content')
<div class="card mb-8">
    <div class="card-title flex items-center justify-between @if(!$displayDynasty && !$displayAuthor) !mb-0 @endif">
        <h1 class="text-xl">{{ $article->book->name }}</h1>
        <a href="{{ route('book.show', $article->book->book_id) }}" class="link text-sm primary">返回目录</a>
    </div>

    <div class="card-content">
        @if($displayDynasty || $displayAuthor)
        <p class="secondary">
            作者：
            @if($article->book->dynasty)
            {{ $article->book->dynasty->name }}
            @elseif($article->book->chaodai)
            {{ $article->book->chaodai }}
            @endif
            @if($displayDynasty && $displayAuthor)
            ·
            @endif
            @if($article->book->author)
            <a class="link secondary" href="{{ route('author.show', $article->book->author->author_id) }}" id="poem-author">{{ $article->book->author->name }}</a>
            @elseif($displayAuthor)
            <span id="poem-author">{{ $displayAuthor }}</span>
            @endif
        </p>
        @endif
    </div>
</div>

<div class="card @if($article->supplements->count() > 0) mb-8 @endif">
    <div class="flex justify-between items-center mb-5">
        <h2 class="card-title text-xl !mb-0">
            <span id="poem-title">{{ $article->name }}</span>
            <span class="text-base font-normal text-gray-500">原文</span>
        </h2>
        <div class="flex gap-2">
            <span class="badge cursor-pointer" id="readAloudBtn" onclick="handleReadAloud('{{ route('book.audio', ['book_id' => $article->book->book_id, 'article_id' => $article->article_id]) }}')">朗读</span>
            <span class="badge cursor-pointer" onclick="togglePinyin()">拼音</span>
        </div>
    </div>
    
    <div class="card-content escape-html leading-10 [&>p]:mb-6" id="poem-content">
        {!! $article->content !!}
    </div>

    <!-- Audio Player Container -->
    <div id="audioPlayerContainer" class="mt-6 p-4 bg-slate-50 dark:bg-slate-700 rounded-md" style="display: none;">
        <div class="flex flex-col gap-3">
            {{-- <div class="flex items-center justify-between">
                <span class="text-sm font-medium">正在朗读...</span>
                <button onclick="closeAudioPlayer()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div> --}}
            <audio id="audioPlayer" controls class="w-full h-10"></audio>
        </div>
    </div>

    <script>
        window.poemData = {
            title: @json($article->name),
            dynasty: @json($displayDynasty ?: ''),
            author: @json($displayAuthor ?: ''),
            content: @json($article->content)
        };
    </script>
</div>

@foreach ($article->supplements as $metadata)
<div class="article-metadata card @if(!$loop->last) mb-8 @endif">
    <h2 class="article-metadata-title card-title">{{ $metadata->name }}</h2>
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
