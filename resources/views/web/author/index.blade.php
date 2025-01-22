@extends('web.layout')

@section('title', '作者列表' . ($dynasty ? ' - ' . $dynasty->name : '') . ' - 第' . $page . '页')

@section('keywords', '作者列表,')
@section('description', '作者列表' . ($dynasty ? ' - ' . $dynasty->name : '') . ' - 第' . $page . '页,')

@section('seo')

@endsection

@section('content')

<div class="card mb-8">
    <h2 class="card-title">朝代：</h2>
    <div class="card-content">
        @foreach ($dynasties as $d)
        <a href="{{ route('author.index', ['dynasty_id' => $d->id]) }}" class="link badge {{ ($dynasty && $dynasty->id == $d->id) ? 'primary' : '' }}">
            {{ $d->name }}
        </a>
        @endforeach
    </div>
</div>

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
@endsection

@section('sidebar')
<x-sidebar.hot-tag class="mb-5" />
<x-sidebar.hot-poem />
@endsection