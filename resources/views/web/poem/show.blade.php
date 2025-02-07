@extends('web.layout')

@section('title', $poem->name . '的原文、注释、翻译、赏析、序' . ($poem->dynasty ? ' - 【' . $poem->dynasty->name .'】' : '') . ($poem->author ? $poem->author->name : ''))

@section('keywords', $poem->name . ',' . ($poem->dynasty ? $poem->dynasty->name . ',' : '') . ($poem->author ? $poem->author->name . ',' : ''))
@section('description', $poem->name . '的原文、注释、翻译、赏析、序,' . ($poem->dynasty ? $poem->dynasty->name  : '') . ($poem->author ? $poem->author->name . '的' : '') . '诗词,')


@section('content')
<div class="poem card mb-8">
    <h1 class="poem-name card-title flex items-center justify-between">
        <a href="{{ route('poem.show', $poem->poem_id) }}" class="link text-xl">
            {{ $poem->name }}
        </a>

        <span class="badge cursor-pointer @if(empty($poem->yizhu)) !hidden @endif" onclick="toggleYizhu()">译注</span>
    </h1>
    <div class="card-content ">
        <div class="poem-info my-2 secondary">
            @if($poem->dynasty)
            <a href="{{ route('poem.index', ['dynasty_id' => $poem->dynasty->id]) }}" class="link secondary">
                {{ $poem->dynasty->name }}
            </a> ·
            @endif
            @if($poem->author)
            <a href="{{ route('author.show', $poem->author->author_id) }}" class="link secondary">
                {{ $poem->author->name }}
            </a>
            @else
            佚名
            @endif
        </div>
        <div class="poem-content escape-html leading-10 [&>p]:mb-6">{!! $poem->content !!}</div>
        <div class="poem-yizhu-content escape-html leading-10 [&>p]:mb-6 hidden">{!! $poem->yizhu !!}</div>
    </div>
    <div class="card-content poem-tags mt-4" @if($poem->tags->isEmpty()) style="display: none;" @endif>
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
<div class="poem-metadata card @if(!$loop->last) mb-8 @endif">
    <h2 class="poem-metadata-title card-title">{{ $metadata->title }}</h2>
    <div class="poem-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach
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
</script>
@endsection