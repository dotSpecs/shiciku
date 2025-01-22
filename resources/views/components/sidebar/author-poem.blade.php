<div {{ $attributes->merge(['class' => 'card']) }}>
    <h2 class="card-title flex items-center justify-between">
        <span class="underline underline-offset-8">{{ $author->name }}的其他作品</span>
        <a href="{{ route('poem.index', ['author_id' => $author->author_id]) }}" class="link text-sm primary">更多 +</a>
    </h2>
    <div class="card-content">
        <ul class="marker:text-red-500 list-disc ps-5 space-y-2 ">
            @foreach ($poems as $poem)
            <li class="quote"><a href="{{ route('poem.show', $poem->poem_id) }}" class="link">{{ $poem->name }}</a></li>
            @endforeach
        </ul>
    </div>
</div>