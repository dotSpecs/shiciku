@extends('web.layout')

@section('title', '与 “' . $query . '” 相关的' . ($type == 'author' ? '作者' : '诗词') . '的搜索结果 - 第 ' . $page . ' 页')

@section('keywords', ($type == 'author' ? '作者' : '诗词') . '搜索,' . $query .',')
@section('description', '与 “' . $query . '” 相关的' . ($type == 'author' ? '作者' : '诗词') . '的搜索结果 - 第 ' . $page . ' 页')


@section('content')

<div class="card text-center mb-8">
    @foreach (['poem' => '诗词', 'author' => '作者'] as $t => $n)
    <a class="mx-5 link @if($type == $t) primary @endif" href="{{ route('search', ['query' => $query, 'type' => $t]) }}">
        {{ $n }}
    </a>
    @endforeach
</div>

@if ($type == 'author')
<!-- 作者开始 -->
@if ($authors->count() > 0)
<div class="card mb-8 !py-0 grid grid-cols-1 divide-y divide-gray-200 dark:divide-slate-600">
    @foreach ($authors as $author)
    <div class="author-card card-content py-8">
        <div class="flex items-start">
            @if($author->pic)
            <img class="w-20 h-auto rounded-md mr-4" src="{{ $author->pic }}" alt="{{ $author->name }}">
            @endif
            <div>
                <h2 class="text-lg mb-3">
                    <a class="link" href="{{ route('author.show', $author->author_id) }}">@if($author->dynasty)【{{ $author->dynasty->name }}】@endif{{ $author->name }}</a>
                </h2>
                <div class="author-content escape-html line-clamp-3">
                    {!! $author->content !!}
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="pagination">
    {{ $authors->links() }}
</div>
@else
<div class="card text-center">暂无内容</div>
@endif
<!-- 作者结束 -->

@else
<!-- 诗词开始 -->
@if ($poems->count() > 0)
@foreach ($poems as $poem)
<div class="poem card mb-8">
    <h1 class="poem-name card-title">
        <a href="{{ route('poem.show', $poem->poem_id) }}" class="link">
            {{ $poem->name }}
        </a>
    </h1>
    <div class="card-content">
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
        <div class="poem-content escape-html  line-clamp-3">
            {!! $poem->content !!}
        </div>
    </div>
</div>
@endforeach
<div class="pagination">
    {!! $poems->links('vendor.pagination.simple-tailwind') !!}
</div>
@else
<div class="card text-center">暂无内容</div>
@endif
<!-- 诗词结束 -->
@endif


@endsection

@section('sidebar')
<x-sidebar.hot-tag class="mb-8" />
<x-sidebar.hot-book />
@endsection