@extends('web.layout')

@section('title', '名句列表' . ($tag ? ' - 合集：' . $tag->name : '') . ' - 第' . $page . '页')

@section('keywords', '名句列表,' . ($tag ? '合集：' . $tag->name . ',' : ''))
@section('description', '名句列表' . ($tag ? ' - 合集：' . $tag->name : '') . ' - 第' . $page . '页,')

@section('content')

<div class="card mb-8">
    <h2 class="card-title-sm">合集：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-1">
            @if ($tag && !in_array($tag->id, $tags->pluck('id')->toArray()))
            <a href="{{ route('mingju.index', ['tag_id' => $tag->id]) }}" class="link badge !text-xs primary">
                {{ $tag->name }}
            </a>
            @endif
            @foreach ($tags as $t)
            <a href="{{ route('mingju.index', ['tag_id' => $t->id]) }}" class="link badge !text-xs {{ ($tag && $tag->id == $t->id) ? 'primary' : '' }}">
                {{ $t->name }}
            </a>
            @endforeach
        </div>
    </div>
</div>

<div class="mx-auto">
    @foreach ($mingjus as $mingju)
    <div class="mingju card mb-8">
        <h2 class="card-title">
            <a href="{{ route('mingju.show', $mingju->mingju_id) }}" class="link">{{ $mingju->name }}</a>
        </h2>
        <div class="card-content secondary text-sm">
            @if ($mingju->author)
                <a href="{{ route('author.show', $mingju->author->author_id) }}" class="link secondary">{{ $mingju->author->name }}</a>
            @else
                佚名
            @endif
            @if ($mingju->source)
                @php
                    $sourceUrl = null;
                    // if ($mingju->guishu == 1 && $mingju->sourcePoem) {
                    if ($mingju->sourcePoem) {
                        $sourceUrl = route('poem.show', $mingju->sourcePoem->poem_id);
                    // } elseif ($mingju->guishu == 2 && $mingju->sourceBookArticle && $mingju->sourceBookArticle->book) {
                    } elseif ($mingju->sourceBookArticle && $mingju->sourceBookArticle->book) {
                        $sourceUrl = route('book.article', ['book_id' => $mingju->sourceBookArticle->book->book_id, 'article_id' => $mingju->sourceBookArticle->article_id]);
                    }
                @endphp
                ·
                @if ($sourceUrl)
                    <a href="{{ $sourceUrl }}" class="link secondary">《{{ $mingju->source }}》</a>
                @else
                    《{{ $mingju->source }}》
                @endif
            @endif
        </div>
    </div>
    @endforeach
    <div class="pagination">
        {{ $mingjus->links() }}
    </div>
</div>
@endsection

@section('sidebar')
<x-sidebar.hot-tag class="mb-8" />
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-book />
@endsection
