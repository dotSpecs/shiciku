@extends('web.layout')

@php
    $displayDynasty = $poem->dynasty?->name ?: $poem->chaodai;
    $displayAuthor = $poem->author?->name ?: $poem->author_name;
@endphp

@section('title', $poem->name . '的原文、注释、翻译、赏析、序' . ($displayDynasty ? ' - 【' . $displayDynasty .'】' : '') . ($displayAuthor ?: ''))

@section('keywords', $poem->name . ',' . ($displayDynasty ? $displayDynasty . ',' : '') . ($displayAuthor ? $displayAuthor . ',' : ''))
@section('description', $poem->name . '的原文、注释、翻译、赏析、序,' . ($displayDynasty ?: '') . ($displayAuthor ? $displayAuthor . '的' : '') . '诗词,')


@section('content')
<div class="poem card mb-8">
    <h1 class="poem-name card-title flex items-center justify-between">
        {{-- <a href="{{ route('poem.show', poem_slug($poem)) }}" class="link text-xl"> --}}
            <span id="poem-title">{{ $poem->name }}</span>
        {{-- </a> --}}

        <div class="flex gap-2">
            <span class="badge cursor-pointer" id="readAloudBtn" onclick="handleReadAloud('{{ route('poem.audio', $poem->poem_id) }}')">朗读</span>
            <span class="badge cursor-pointer" onclick="togglePinyin()">拼音</span>
            <span class="badge cursor-pointer @if(empty($poem->yizhu_content)) !hidden @endif" onclick="toggleYizhu()">译注</span>
        </div>
    </h1>
    <div class="card-content ">
        <div class="poem-info my-2 secondary mb-8">
            @if($poem->dynasty)
            <a href="{{ route('poem.index', ['dynasty_id' => $poem->dynasty->id]) }}" class="link secondary" id="poem-dynasty">
                {{ $poem->dynasty->name }}
            </a> ·
            @elseif($poem->chaodai)
            <span id="poem-dynasty">{{ $poem->chaodai }}</span> ·
            @endif
            @if($poem->author)
            <a href="{{ route('author.show', $poem->author->author_id) }}" class="link secondary" id="poem-author">
                {{ $poem->author->name }}
            </a>
            @elseif($poem->author_name)
            <span id="poem-author">{{ $poem->author_name }}</span>
            @endif
        </div>
        <div class="poem-content escape-html leading-10 [&>p]:mb-6" id="poem-content">{!! $poem->content !!}</div>
        <div class="poem-yizhu-content escape-html leading-10 [&>p]:mb-6 hidden">{!! $poem->yizhu_content !!}</div>

        <script>
            window.poemData = {
                title: @json($poem->name),
                dynasty: @json($displayDynasty ?: ''),
                author: @json($displayAuthor ?: ''),
                content: @json($poem->content)
            };
        </script>
        
        <!-- Audio Player Container -->
        <div id="audioPlayerContainer" class="mt-6 p-4 bg-slate-50 dark:bg-slate-700 rounded-md" style="display: none;">
            <audio id="audioPlayer" controls class="w-full h-10">
                您的浏览器不支持音频播放。
            </audio>
        </div>
    </div>
    <div class="card-content poem-tags mt-8" @if($poem->tags->isEmpty()) style="display: none;" @endif>
        @foreach ($poem->tags as $tag)
        <a href="{{ route('poem.index', ['tag_id' => $tag->id]) }}" class="link badge secondary !text-xs">{{ $tag->name }}</a>
        @endforeach
    </div>
</div>

@if ($poem->mingjus->count() > 0)
<div class="card mb-8">
    <h2 class="card-title">名句</h2>
    <div class="card-content">
        <ul class="marker:text-red-500 list-disc ps-5 space-y-2 ">
            @foreach ($poem->mingjus as $mingju)
            <li class="quote">
                @if ($mingju->mingju_id)
                    <a href="{{ route('mingju.show', $mingju->mingju_id) }}" class="link">{{ $mingju->name }}</a>
                @else
                    {{ $mingju->name }}
                @endif
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@php $metadatas = $poem->fanyis->concat($poem->shangxis); @endphp
@foreach ($metadatas as $metadata)
<div class="poem-metadata card @if(!$loop->last || $poem->author) mb-8 @endif">
    <h2 class="poem-metadata-title card-title">{{ $metadata->name }}</h2>
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
</script>
@endsection
