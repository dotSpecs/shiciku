@extends('web.layout')

@php
    $pageHeading = '古籍列表' . ($class ? ' - ' . $class : '') . ($type ? '·' . $type : '');
    $pageTitle = $pageHeading . ($page > 1 ? ' - 第' . $page . '页' : '');
@endphp

@section('title', $pageTitle)

@section('keywords', '古籍列表,' . ($class ? $class . ',' : '') . ($type ? $type . ',' : ''))
@section('description', $pageTitle . ',')

@section('content')

<div class="card mb-8">
    <h1 class="card-title text-2xl mb-3">{{ $pageHeading }}</h1>

    @if ($types)
    @foreach ($types as $c => $typeList)
    @if (!empty($typeList))
    <div class="flex flex-wrap items-start gap-x-3 gap-y-1 border-t border-dashed border-slate-200 pt-2 @if (!$loop->first) mt-2 @endif dark:border-slate-700">
        <h2 class="shrink-0 w-14 text-sm font-medium secondary leading-7">
            <a href="{{ route('book.index', ['class' => $c]) }}" class="link secondary {{ ($class === $c && !$type) ? 'primary' : '' }}">{{ $c }}</a>：
        </h2>
        <div class="flex flex-1 flex-wrap gap-x-4 gap-y-1">
            @foreach ($typeList as $t)
            <a href="{{ route('book.index', ['class' => $c, 'type' => $t]) }}" class="link text-sm leading-7 {{ ($class === $c && $type === $t) ? 'primary' : '' }}">
                {{ $t }}
            </a>
            @endforeach
        </div>
    </div>
    @endif
    @endforeach
    @endif
</div>

@foreach ($books as $book)
@php
    $displayDynasty = $book->dynasty?->name ?: $book->chaodai;
    $displayAuthor = $book->author?->name ?: $book->author_name;
@endphp
<div class="card mb-8">
    <h2 class="card-title"><a href="{{ route('book.show', ['book_id' => $book->book_id]) }}" class="link">{{ $book->name }}</a></h2>
    <div class="card-content ">
        @if($displayDynasty || $displayAuthor)
        <div class="secondary text-sm mb-3">
            作者：
            @if($book->dynasty)
            {{ $book->dynasty->name }}
            @elseif($book->chaodai)
            {{ $book->chaodai }}
            @endif
            @if($displayDynasty && $displayAuthor)
            ·
            @endif
            @if($book->author)
            <a class="link secondary" href="{{ route('author.show', $book->author->author_id) }}">{{ $book->author->name }}</a>
            @elseif($displayAuthor)
            {{ $displayAuthor }}
            @endif
        </div>
        @endif
        <div class="escape-html line-clamp-3">
            {!! $book->content !!}
        </div>
    </div>
</div>
@endforeach
<div class="pagination">
    {{ $books->links() }}
</div>
@endsection

@section('sidebar')
<x-sidebar.hot-book class="mb-8" />
<x-sidebar.hot-tag />
@endsection
