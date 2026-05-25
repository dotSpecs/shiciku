@extends('web.layout')

@section('title', $zhuanti->name . '全集 - 原文、翻译及赏析')

@section('keywords', $zhuanti->name . ',' . $zhuanti->name . '全集,')
@section('description', $zhuanti->name . '收录的全部诗词列表，包含原文、翻译及赏析。')

@section('content')
<div class="card mb-8">
    <h1 class="card-title">{{ $zhuanti->name }}</h1>
</div>

@foreach ($zhuanti->chapters as $chapter)
<div class="card mb-8">
    <h2 class="card-title">
        {{ $chapter->name }}
        @if ($chapter->sub_title)
        <span class="secondary text-sm ml-2">{{ $chapter->sub_title }}</span>
        @endif
    </h2>
    <div class="card-content">
        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2">
            @foreach ($chapter->poems as $poem)
            <li class="flex items-baseline gap-2 min-w-0">
                <a href="{{ route('poem.show', poem_slug($poem)) }}" class="link truncate">{{ $poem->name }}</a>
                <span class="secondary text-xs shrink-0">
                    @if ($poem->author)
                        {{ $poem->author->name }}
                    @else
                        佚名
                    @endif
                </span>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endforeach
@endsection

@section('sidebar')
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-book />
@endsection
