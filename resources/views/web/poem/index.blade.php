@extends('web.layout')

@section('title', '诗词列表' . ($tag ? ' - 所属合集：' . $tag->name .'的诗词' : '') . ($author ? ' - 作者：' . $author->name . '的诗词' : '') . ($dynasty ? ' - 朝代：' . $dynasty->name . '的诗词' : '') . ' - 第' . $page . '页')

@section('keywords', '诗词列表,' . ($tag ? '合集：'.$tag->name.',' : '') . ($author ? $author->name.'的诗词,' : '') . ($dynasty ? $dynasty->name.'的诗词,' : ''))
@section('description', '诗词列表' . ($tag ? ' - 合集：' . $tag->name : '') . ($author ? ' - 作者：' . $author->name : '') . ($dynasty ? ' - 朝代：' . $dynasty->name : '') . ' - 第' . $page . '页,')

@section('content')

<div class="card mb-8 ">

    <h2 class="card-title">合集：</h2>

    <div class="card-content">
        <div class="flex flex-wrap gap-2">
            @if ($tag && !in_array($tag->id, $tags->pluck('id')->toArray()))
            <a href="{{ route('poem.index', ['tag_id' => $tag->id]) }}" class="link badge !text-xs primary">
                {{ $tag->name }}
            </a>
            @endif
            @foreach ($tags as $t)
            <a href="{{ route('poem.index', ['tag_id' => $t->id]) }}" class="link badge !text-xs {{ ($tag && $tag->id == $t->id) ? 'primary' : '' }}">
                {{ $t->name }}
            </a>
            @endforeach
        </div>
    </div>

    <h2 class="card-title mt-5">作者：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-2">
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

    <h2 class="card-title mt-5">朝代：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-2">
            @foreach ($dynasties as $d)
            <a data-id="{{ $d->id }}" href="{{ route('poem.index', ['dynasty_id' => $d->id]) }}" class="link badge !text-xs {{ ($dynasty && $dynasty->id == $d->id) ? 'primary' : '' }}">
                {{ $d->name }}
            </a>
            @endforeach
        </div>
    </div>

    <!-- <h2 class="card-title mt-2">形式：</h2>
    <div class="card-content">
        <div class="flex flex-wrap gap-2">
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
        {{ $poems->links() }}
    </div>
</div>
@endsection

@section('sidebar')
<x-sidebar.hot-author limit="5" class="mb-8" />
<x-sidebar.hot-book />
@endsection