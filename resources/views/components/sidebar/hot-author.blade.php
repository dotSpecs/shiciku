<div {{ $attributes->merge(['class' => 'card']) }}>
    <h2 class="card-title  flex items-center justify-between">
        <span class="underline underline-offset-8 ">热门作者</span>
        <a href="{{ route('author.index') }}" class="link text-sm primary">更多 +</a>
    </h2>
    <div class="card-content grid grid-cols-1 divide-y divide-gray-200 dark:divide-slate-600">
        @foreach ($authors as $author)
        <div class="author-card py-8">
            <div class="flex items-center">
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
</div>