@extends('web.layout')

@php
    $pageHeading = '诗词列表' . ($tag ? ' - 所属合集：' . $tag->name .'的诗词' : '') . ($author ? ' - 作者：' . $author->name . '的诗词' : '') . ($dynasty ? ' - 朝代：' . $dynasty->name . '的诗词' : '');
    $pageTitle = $pageHeading . ($page > 1 ? ' - 第' . $page . '页' : '');
@endphp

@section('title', $pageTitle)

@section('keywords', '诗词列表,' . ($tag ? '合集：'.$tag->name.',' : '') . ($author ? $author->name.'的诗词,' : '') . ($dynasty ? $dynasty->name.'的诗词,' : ''))
@section('description', $pageTitle . ',')

@section('content')

<h1 class="card-title mb-4">{{ $pageHeading }}</h1>

<div class="card mb-8 ">

    <h2 class="card-title-sm">合集：</h2>

    <div class="card-content">
        <div class="flex flex-wrap gap-1">
            @if ($tag && !in_array($tag->id, $tags->pluck('id')->toArray()))
            <a href="{{ route('poem.index', ['tag_id' => $tag->id]) }}" class="link badge !text-xs primary">
                {{ $tag->name }}
            </a>
            @endif
            @foreach ($tags as $t)
            <a href="{{ $t->zhuanti ? route('poem.zhuanti', $t->zhuanti->alias) : route('poem.index', ['tag_id' => $t->id]) }}" class="link badge !text-xs {{ ($tag && $tag->id == $t->id) ? 'primary' : '' }}">
                {{ $t->name }}
            </a>
            @endforeach
        </div>
    </div>

    <h2 class="card-title-sm mt-2">作者：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-1">
            @if ($author && !in_array($author->author_id, $authors->pluck('author_id')->toArray()))
            <a href="{{ route('poem.index', ['author_id' => $author->author_id]) }}" class="link badge !text-xs primary">
                {{ $author->name }}
            </a>
            @endif
            @foreach ($authors as $a)
            <a href="{{ route('poem.index', ['author_id' => $a->author_id]) }}" class="link badge !text-xs {{ ($author && $author->author_id == $a->author_id) ? 'primary' : '' }}">
                {{ $a->name }}
            </a>
            @endforeach
        </div>
    </div>

    <h2 class="card-title-sm mt-2">朝代：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-1">
            @foreach ($dynasties as $d)
            <a data-id="{{ $d->id }}" href="{{ route('poem.index', ['dynasty_id' => $d->id]) }}" class="link badge !text-xs {{ ($dynasty && $dynasty->id == $d->id) ? 'primary' : '' }}">
                {{ $d->name }}
            </a>
            @endforeach
        </div>
    </div>

    <!-- <h2 class="card-title mt-2">形式：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-1">
            @foreach ($forms as $form)
            <a href="{{ route('poem.index', ['type' => $form['type']]) }}" class="link badge !text-xs">
                {{ $form['name'] }}
            </a>
            @endforeach
        </div>
    </div> -->
</div>

<div class="mx-auto">
    @foreach ($poems as $poem)
    <div class="poem card mb-8">
        <h2 class="poem-name card-title">
            <a href="{{ route('poem.show', poem_slug($poem)) }}" class="link">
                {{ $poem->name }}
            </a>
        </h2>
        <div class="card-content">
            <div class="poem-info my-2 secondary">
                @if($poem->dynasty)
                <a href="{{ route('poem.index', ['dynasty_id' => $poem->dynasty->id]) }}" class="link secondary">
                    {{ $poem->dynasty->name }}
                </a> ·
                @elseif($poem->chaodai)
                {{ $poem->chaodai }} ·
                @endif
                @if($poem->author)
                <a href="{{ route('author.show', $poem->author->author_id) }}" class="link secondary">
                    {{ $poem->author->name }}
                </a>
                @elseif($poem->author_name)
                {{ $poem->author_name }}
                @endif
            </div>
            <div class="poem-content escape-html  line-clamp-3">
                {!! $poem->content !!}
            </div>
        </div>
    </div>
    @endforeach
    <div class="pagination">
        {{ $poems->links() }}
    </div>
</div>
@endsection

@section('sidebar')
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-book />
@endsection
