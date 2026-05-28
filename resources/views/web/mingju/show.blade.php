@extends('web.layout')

@php
    $displayDynasty = $mingju->dynasty?->name ?: $mingju->chaodai;
    $displayAuthor = $mingju->author?->name ?: $mingju->author_name;
@endphp

@section('title', $mingju->name . '的出处、译文、注释、赏析' . ($displayDynasty ? ' - 【' . $displayDynasty . '】' : '') . ($displayAuthor ?: ''))

@section('keywords', $mingju->name . ',' . ($displayAuthor ? $displayAuthor . ',' : '') . ($mingju->source ? $mingju->source . ',' : ''))
@section('description', $mingju->name . '的出处、译文、注释、赏析,' . ($displayAuthor ? $displayAuthor . ',' : '') . ($mingju->source ? '出自' . $mingju->source : ''))

@section('content')

@php
    $poem = $mingju->sourcePoem;
    $article = $mingju->sourceBookArticle;
    $poemDisplayDynasty = $poem ? ($poem->dynasty?->name ?: $poem->chaodai) : null;
    $poemDisplayAuthor = $poem ? ($poem->author?->name ?: $poem->author_name) : null;
    if ($mingju->guishu == 1 && $poem) {
        $sourceUrl = route('poem.show', poem_slug($poem));
    } elseif ($mingju->guishu == 2 && $article && $article->book) {
        $sourceUrl = route('book.article', ['book_id' => $article->book->book_id, 'article_id' => $article->article_id]);
    } else {
        $sourceUrl = null;
    }
@endphp

<div class="mingju card mb-8">
    <h1 class="card-title text-xl">{{ $mingju->name }}</h1>
    <div class="card-content">
        @if ($displayDynasty || $displayAuthor || $mingju->source)
        <div class="secondary mb-6">
            出自
            @if ($mingju->dynasty)
                <a href="{{ route('poem.index', ['dynasty_id' => $mingju->dynasty->id]) }}" class="link secondary">{{ $mingju->dynasty->name }}</a>
            @elseif ($mingju->chaodai)
                {{ $mingju->chaodai }}
            @endif
            @if ($mingju->author)
                <a href="{{ route('author.show', $mingju->author->author_id) }}" class="link secondary">{{ $mingju->author->name }}</a>的
            @elseif ($displayAuthor)
                {{ $displayAuthor }}的
            @endif
            @if ($mingju->source)
                @if ($sourceUrl)
                    <a href="{{ $sourceUrl }}" class="link secondary">《{{ $mingju->source }}》</a>
                @else
                    《{{ $mingju->source }}》
                @endif
            @endif
        </div>
        @endif

        @if ($mingju->yiwen)
        <div class="mb-4 leading-10">
            <span class="inline-block px-2 py-0.5 mr-2 text-xs bg-slate-200 dark:bg-slate-700 rounded">译文</span>
            <span class="escape-html [&>p]:inline">{!! $mingju->yiwen !!}</span>
        </div>
        @endif

        @if ($mingju->zhushi)
        <div class="mb-4 leading-10">
            <span class="inline-block px-2 py-0.5 mr-2 text-xs bg-slate-200 dark:bg-slate-700 rounded">注释</span>
            <span class="escape-html [&>p]:inline">{!! $mingju->zhushi !!}</span>
        </div>
        @endif

        @if ($mingju->shangxi)
        <div class="mb-4 leading-10">
            <span class="inline-block px-2 py-0.5 mr-2 text-xs bg-slate-200 dark:bg-slate-700 rounded">赏析</span>
            <span class="escape-html [&>p]:inline">{!! $mingju->shangxi !!}</span>
        </div>
        @endif
    </div>
    @if (!$mingju->tags->isEmpty())
    <div class="card-content mt-4">
        @foreach ($mingju->tags as $t)
        <a href="{{ route('mingju.index', ['tag_id' => $t->id]) }}" class="link badge secondary !text-xs">{{ $t->name }}</a>
        @endforeach
    </div>
    @endif
</div>

@if ($poem)
<div class="poem card mb-8">
    <h2 class="poem-name card-title flex items-center justify-between">
        <a href="{{ route('poem.show', poem_slug($poem)) }}" class="link text-xl">{{ $poem->name }}</a>
    </h2>
    <div class="card-content">
        <div class="poem-info my-2 secondary mb-8">
            @if($poem->dynasty)
            <a href="{{ route('poem.index', ['dynasty_id' => $poem->dynasty->id]) }}" class="link secondary">
                {{ $poem->dynasty->name }}
            </a> ·
            @elseif($poem->chaodai)
            {{ $poem->chaodai }} ·
            @endif
            @if($poem->author)
            <a href="{{ route('author.show', $poem->author->author_id) }}" class="link secondary">{{ $poem->author->name }}</a>
            @elseif($poemDisplayAuthor)
            {{ $poemDisplayAuthor }}
            @endif
        </div>
        <div class="poem-content escape-html leading-10 [&>p]:mb-6">{!! $poem->content !!}</div>
    </div>
    @if (!$poem->tags->isEmpty())
    <div class="card-content poem-tags mt-8">
        @foreach ($poem->tags as $t)
        <a href="{{ route('poem.index', ['tag_id' => $t->id]) }}" class="link badge secondary !text-xs">{{ $t->name }}</a>
        @endforeach
    </div>
    @endif
</div>

@php $poemMetadatas = $poem->fanyis->concat($poem->shangxis); @endphp
@foreach ($poemMetadatas as $metadata)
<div class="poem-metadata card @if(!$loop->last || $poem->author) mb-8 @endif">
    <h2 class="poem-metadata-title card-title">{{ $metadata->name }}</h2>
    <div class="poem-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach

@if ($poem->author)
<div class="card py-8">
    @if ($poem->author->pic)
    <img class="w-20 h-auto rounded-md mr-4 float-left" src="{{ $poem->author->pic }}" alt="{{ $poem->author->name }}">
    @endif
    <h2 class="text-lg mb-3">
        <a class="link" href="{{ route('author.show', $poem->author->author_id) }}">{{ $poem->author->name }}</a>
    </h2>
    <div class="author-content escape-html leading-10 [&>p]:mb-6">{!! $poem->author->content !!}</div>
</div>
@endif
@endif

@endsection

@section('sidebar')
@if ($mingju->author)
<x-sidebar.author-poem :author-id="$mingju->author->author_id" class="mb-8" />
@endif
<x-sidebar.hot-tag class="mb-8" />
<x-sidebar.hot-book />
@endsection
