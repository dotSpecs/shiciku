@extends('web.layout')

@section('title', '关于' .($author->dynasty ? '【' . $author->dynasty->name . '】' : ''). $author->name . '的作者简介')

@section('keywords', $author->name . ',诗人简介,作者简介,')
@section('description', '关于' . ($author->dynasty ? $author->dynasty->name . '诗人' : '') . $author->name . '的作者简介,')

@section('content')
<div class="card py-8 mb-8">
    <div class="flex items-start">
        @if($author->pic)
        <img class="w-20 h-auto rounded-md mr-4" src="{{ $author->pic }}" alt="{{ $author->name }}">
        @endif
        <div>
            <h2 class="text-lg mb-3">
                <a class="link" href="{{ route('author.show', $author->author_id) }}">@if($author->dynasty)【{{ $author->dynasty->name }}】@endif{{ $author->name }}</a>
            </h2>
            <div class="author-content escape-html leading-10 [&>p]:mb-6">
                {!! $author->content !!} 
                <!-- @if($author->books_count > 0)
                <a href="{{ route('book.index', ['author_id' => $author->author_id]) }}" class="link primary">&raquo; {{ $author->books_count }}部作品</a>
                @endif -->
                @if($author->poems_count > 0)
                <a href="{{ route('poem.index', ['author_id' => $author->author_id]) }}" class="link primary">&raquo; {{ $author->poems_count }}首诗词</a>
                @endif
            </div>
        </div>
    </div>
</div>



@foreach ($author->metadatas as $metadata)
<div class="author-metadata card mb-8">
    <h2 class="author-metadata-title card-title">{{ $metadata->title }}</h2>
    <div class="author-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach

<div class="card text-center">
    <a href="{{ route('poem.index', ['author_id' => $author->author_id]) }}" class="link primary">&raquo; {{ $author->poems_count }}首诗词</a>
</div>

@endsection



@section('sidebar')
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-tag class="mb-8" />
<x-sidebar.hot-book />
@endsection