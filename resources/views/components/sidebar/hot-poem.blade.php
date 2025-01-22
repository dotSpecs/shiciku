<div  {{ $attributes->merge(['class' => 'card']) }}>
    <h2 class="card-title flex items-center justify-between">
        <span class="underline underline-offset-8 ">热门诗词</span>
        <a href="{{ route('poem.index') }}" class="link text-sm primary">更多 +</a>
    </h2>
    <div class="card-content grid grid-cols-1 divide-y divide-gray-200 dark:divide-slate-600">
        @foreach ($poems as $poem)
        <div class="poem-card py-8">
            <h2 class="text-lg"><a class="link" href="{{ route('poem.show', $poem->poem_id) }}">{{ $poem->name }}</a></h2>
            <div class="secondary my-3">
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
            <div class="poem-content escape-html line-clamp-3">
                {!! $poem->content !!}
            </div>
        </div>
        @endforeach
    </div>
</div>