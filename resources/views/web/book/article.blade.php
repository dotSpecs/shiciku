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
            作者：<a class="link secondary" href="{{ route('author.show', $article->book->author->author_id) }}">{{ $article->book->author->name }}</a>
        </p>
        @endif
    </div>

    <!-- <div class="card-content">
        {!! $article->book->content !!}
    </div> -->
</div>

<div class="card @if($article->metadatas->count() > 0) mb-8 @endif">
    <h1 class="card-title text-xl">{{ $article->book->name . ' · ' . ($article->chapter && $article->chapter->name ? $article->chapter->name. ' · ' : '') . $article->name . '原文' }}</h1>
    <div class="card-content escape-html leading-10 [&>p]:mb-6">
        {!! $article->content !!}
    </div>
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