@extends('web.layout')

@section('title', '古籍列表' . ($class ? ' - ' . $class : '') . ($type ? '·' . $type : '') . ' - 第' . $page . '页')

@section('keywords', '古籍列表,' . ($class ? $class . ',' : '') . ($type ? $type . ',' : ''))
@section('description', '古籍列表' . ($class ? ' - ' . $class : '') . ($type ? '·' . $type : '') . ' - 第' . $page . '页,')

@section('content')

@if ($types)
<div class="card mb-8">
    @foreach ($types as $c => $typeList)
    @if (!empty($typeList))
    <h2 class="card-title-sm @if (!$loop->first) mt-2 @endif">
        <a href="{{ route('book.index', ['class' => $c]) }}" class="link {{ ($class === $c && !$type) ? 'primary' : '' }}">
            {{ $c }}
        </a>：
    </h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-1">
            @foreach ($typeList as $t)
            <a href="{{ route('book.index', ['class' => $c, 'type' => $t]) }}" class="link badge !text-xs {{ ($class === $c && $type === $t) ? 'primary' : '' }}">
                {{ $t }}
            </a>
            @endforeach
        </div>
    </div>
    @endif
    @endforeach
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