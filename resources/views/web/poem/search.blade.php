@extends('web.layout')

@php
    $typeNames = ['poem' => '诗词', 'mingju' => '名句', 'author' => '作者', 'book' => '古籍'];
    $typeName = $typeNames[$type] ?? '诗词';
    $pageHeading = '与 “' . $query . '” 相关的' . $typeName . '搜索结果';
    $pageTitle = $pageHeading . ($page > 1 ? ' - 第 ' . $page . ' 页' : '');
@endphp

@section('title', $pageTitle)

@section('keywords', $typeName . '搜索,' . $query . ',')
@section('description', $pageTitle)

@section('seo')
<meta name="robots" content="noindex,follow">
@endsection

@section('content')

<h1 class="card-title mb-4">{{ $pageHeading }}</h1>

<div class="card text-center mb-8">
    @foreach ($typeNames as $t => $n)
    <a class="mx-5 link @if($type == $t) primary @endif" href="{{ route('search', ['query' => $query, 'type' => $t]) }}">
        {{ $n }}
    </a>
    @endforeach
</div>

@if ($type == 'author')
    @if ($authors && $authors->count() > 0)
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
@elseif ($type == 'mingju')
    @if ($mingjus && $mingjus->count() > 0)
    @foreach ($mingjus as $mingju)
    @php
        $displayAuthor = $mingju->author?->name ?: $mingju->author_name;
        $sourceUrl = null;
        if ($mingju->sourcePoem) {
            $sourceUrl = route('poem.show', $mingju->sourcePoem->poem_id);
        } elseif ($mingju->sourceBookArticle && $mingju->sourceBookArticle->book) {
            $sourceUrl = route('book.article', ['book_id' => $mingju->sourceBookArticle->book->book_id, 'article_id' => $mingju->sourceBookArticle->article_id]);
        }
    @endphp
    <div class="mingju card mb-8">
        <h2 class="card-title">
            <a href="{{ route('mingju.show', $mingju->mingju_id) }}" class="link">{{ $mingju->name }}</a>
        </h2>
        <div class="card-content secondary text-sm">
            @if ($mingju->author)
                <a href="{{ route('author.show', $mingju->author->author_id) }}" class="link secondary">{{ $mingju->author->name }}</a>
            @elseif ($displayAuthor)
                {{ $displayAuthor }}
            @endif
            @if ($mingju->source)
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
    @else
    <div class="card text-center">暂无内容</div>
    @endif
@elseif ($type == 'book')
    @if (!empty($books))
    <div class="card mb-8">
        <h2 class="card-title-sm">相关古籍</h2>
        <div class="card-content grid grid-cols-1 divide-y divide-gray-200 dark:divide-slate-600">
            @foreach ($books as $book)
            @php
                $displayDynasty = $book->dynasty?->name ?: $book->chaodai;
                $displayAuthor = $book->author?->name ?: $book->author_name;
            @endphp
            <div class="py-4 first:pt-0 last:pb-0">
                <h3 class="text-base font-bold mb-2">
                    <a href="{{ route('book.show', ['book_id' => $book->book_id]) }}" class="link">{{ $book->name }}</a>
                </h3>
                @if($displayDynasty || $displayAuthor)
                <div class="secondary text-sm">
                    @if($displayDynasty){{ $displayDynasty }}@endif
                    @if($displayDynasty && $displayAuthor) · @endif
                    @if($book->author)
                        <a class="link secondary" href="{{ route('author.show', $book->author->author_id) }}">{{ $book->author->name }}</a>
                    @elseif($displayAuthor)
                        {{ $displayAuthor }}
                    @endif
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if ($articles && $articles->count() > 0)
    @foreach ($articles as $article)
    @php
        $highlight = $article->search_highlight ?? [];
        $bookName = $highlight['book_name'][0] ?? ($article->book?->name ?? '');
        $articleName = $highlight['article_name'][0] ?? $article->name;
        $contentSnippets = $highlight['content'] ?? [];
        $book = $article->book;
        $displayDynasty = $book?->dynasty?->name ?: $book?->chaodai;
        $displayAuthor = $book?->author?->name ?: $book?->author_name;
    @endphp
    <div class="card mb-8">
        <h2 class="card-title">
            @if($book)
            <a href="{{ route('book.article', ['book_id' => $book->book_id, 'article_id' => $article->article_id]) }}" class="link">{!! $bookName !!} · {!! $articleName !!}</a>
            @else
            {!! $articleName !!}
            @endif
        </h2>
        <div class="card-content">
            @if($displayDynasty || $displayAuthor)
            <div class="secondary text-sm mb-3">
                @if($displayDynasty){{ $displayDynasty }}@endif
                @if($displayDynasty && $displayAuthor) · @endif
                @if($book?->author)
                    <a class="link secondary" href="{{ route('author.show', $book->author->author_id) }}">{{ $book->author->name }}</a>
                @elseif($displayAuthor)
                    {{ $displayAuthor }}
                @endif
            </div>
            @endif
            @if(!empty($contentSnippets))
            <div class="escape-html line-clamp-3 search-highlight">
                {!! implode(' …… ', $contentSnippets) !!}
            </div>
            @endif
        </div>
    </div>
    @endforeach
    <div class="pagination">
        {{ $articles->links() }}
    </div>
    @elseif(empty($books))
    <div class="card text-center">暂无内容</div>
    @endif
@else
    @if ($poems && $poems->count() > 0)
    @foreach ($poems as $poem)
    @php
        $highlight = $poem->search_highlight ?? [];
        $poemName = $highlight['name'][0] ?? $poem->name;
        $contentSnippets = $highlight['content'] ?? [];
        $poemSummary = !empty($contentSnippets) ? implode(' …… ', $contentSnippets) : $poem->content;
        $poemSummary = preg_replace('/^(?:\s|&nbsp;|<br\s*\/?>)+/iu', '', $poemSummary);
    @endphp
    <div class="poem card mb-8">
        <h2 class="poem-name card-title">
            <a href="{{ route('poem.show', poem_slug($poem)) }}" class="link search-highlight">
                {!! $poemName !!}
            </a>
        </h2>
        <div class="card-content">
            <div class="poem-info mt-2 mb-4 secondary">
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
            <div class="poem-content escape-html line-clamp-3 search-highlight">
                {!! $poemSummary !!}
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
@endif

@endsection

@section('sidebar')
<x-sidebar.hot-tag class="mb-8" />
<x-sidebar.hot-book />
@endsection
