@extends('web.layout')

@section('title', '首页')

@section('content')
@if ($daily && $daily->poem)
<div class="card mb-8">
    <div class="flex items-center justify-between mb-3">
        <span class="badge !text-xs primary">每日一诗</span>
        <span class="secondary text-xs">{{ $daily->date->format('Y-m-d') }}</span>
    </div>
    <h2 class="card-title">
        <a href="{{ route('poem.show', poem_slug($daily->poem)) }}" class="link">{{ $daily->poem->name }}</a>
    </h2>
    <div class="card-content">
        <div class="secondary text-sm mb-3">
            @if ($daily->poem->dynasty)
                {{ $daily->poem->dynasty->name }} ·
            @elseif ($daily->poem->chaodai)
                {{ $daily->poem->chaodai }} ·
            @endif
            @if ($daily->poem->author)
                <a href="{{ route('author.show', $daily->poem->author->author_id) }}" class="link secondary">{{ $daily->poem->author->name }}</a>
            @elseif ($daily->poem->author_name)
                {{ $daily->poem->author_name }}
            @endif
        </div>
        <div class="escape-html leading-10 [&>p]:mb-3 line-clamp-6">{!! $daily->poem->content !!}</div>
    </div>
</div>
@endif

<x-sidebar.hot-author class="mb-8" />

<x-sidebar.hot-poem />
@endsection

@section('sidebar')
<x-sidebar.hot-tag class="mb-8" />

<x-sidebar.hot-book />

@endsection
