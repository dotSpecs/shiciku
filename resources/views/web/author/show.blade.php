@extends('web.layout')

@php
    $plainContent = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($author->content), ENT_QUOTES, 'UTF-8')));
    $displayDynasty = $author->dynasty?->name;
    $seoDescription = $author->name . ($displayDynasty ? '，' . $displayDynasty . '诗人' : '') . '。' . mb_substr($plainContent, 0, 100);
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => route('index')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => '作者', 'item' => route('author.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $author->name, 'item' => route('author.show', $author->author_id)],
                ],
            ],
            [
                '@type' => 'Person',
                'name' => $author->name,
                'description' => $seoDescription,
                'url' => route('author.show', $author->author_id),
                'image' => $author->pic ?: null,
                'knowsAbout' => ['古诗词', '中国古典文学'],
            ],
        ],
    ];
@endphp

@section('title', '关于' .($author->dynasty ? '【' . $author->dynasty->name . '】' : ''). $author->name . '的作者简介')

@section('keywords', $author->name . ',诗人简介,作者简介,')
@section('description', $seoDescription)
@section('og_description', $seoDescription)

@section('seo')
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@section('content')
<div class="card py-8 mb-8">
    @if($author->pic)
    <img class="w-20 h-auto rounded-md mr-4 float-left" src="{{ $author->pic }}" alt="{{ $author->name }}">
    @endif

    <h1 class="text-lg mb-3">
        <a class="link" href="{{ route('author.show', $author->author_id) }}">@if($author->dynasty)【{{ $author->dynasty->name }}】@endif{{ $author->name }}</a>
    </h1>
    <div class="author-content escape-html leading-10 [&>p]:mb-6">
        {!! $author->content !!}
        <!-- @if($author->books_count > 0)
                <a href="{{ route('book.index', ['author_id' => $author->author_id]) }}" class="link primary">&raquo; {{ $author->books_count }}部作品</a>
                @endif -->
        @if($author->shiwen_num > 0)
        <a href="{{ route('poem.index', ['author_id' => $author->author_id]) }}" class="link primary">&raquo; {{ $author->shiwen_num }}首诗词</a>
        @endif
    </div>
</div>



@foreach ($author->ziliaos as $metadata)
<div class="author-metadata card mb-8">
    <h2 class="author-metadata-title card-title">{{ $metadata->name }}</h2>
    <div class="author-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach

<div class="card text-center">
    <a href="{{ route('poem.index', ['author_id' => $author->author_id]) }}" class="link primary">&raquo; {{ $author->shiwen_num }}首诗词</a>
</div>

@endsection



@section('sidebar')
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-tag class="mb-8" />
<x-sidebar.hot-book />
@endsection
