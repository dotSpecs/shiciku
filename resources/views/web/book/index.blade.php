@extends('web.layout')

@section('title', '古籍列表 - 第' . $page . '页')

@section('keywords', '古籍列表,')
@section('description', '古籍列表 - 第' . $page . '页,')

@section('content')

@if ($types)
<div class="card mb-8">
    <h2 class="card-title">经部</h2>
    <div class="card-content">
        @foreach ($types["经部"] as $type)
        <a href="{{ route('book.index', ['type' => $type]) }}" class="link badge !text-xs">
            {{ $type }}
        </a>
        @endforeach
    </div>
    <h2 class="card-title mt-5">史部</h2>
    <div class="card-content">
        @foreach ($types["史部"] as $type)
        <a href="{{ route('book.index', ['type' => $type]) }}" class="link badge !text-xs">
            {{ $type }}
        </a>
        @endforeach
    </div>
    <h2 class="card-title mt-5">子部</h2>
    <div class="card-content">
        @foreach ($types["子部"] as $type)
        <a href="{{ route('book.index', ['type' => $type]) }}" class="link badge !text-xs">
            {{ $type }}
        </a>
        @endforeach
    </div>
    <h2 class="card-title mt-5">集部</h2>
    <div class="card-content">
        @foreach ($types["集部"] as $type)
        <a href="{{ route('book.index', ['type' => $type]) }}" class="link badge !text-xs">
            {{ $type }}
        </a>
        @endforeach
    </div>
</div>
@endif
@foreach ($books as $book)
<div class="card mb-8">
    <h1 class="card-title"><a href="{{ route('book.show', ['book_id' => $book->book_id]) }}" class="link">{{ $book->name }}</a></h1>
    <div class="card-content ">
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