<div  {{ $attributes->merge(['class' => 'card']) }}>
    <h2 class="card-title flex items-center justify-between mb-8">
        <span class="underline underline-offset-8 ">热门标签</span>
        <a href="{{ route('poem.index') }}" class="link text-sm primary">更多 +</a>
    </h2>
    <div class="card-content flex flex-wrap justify-between gap-2">
        @foreach ($tags as $tag)
        <a href="{{ route('poem.index', ['tag_id' => $tag->id]) }}" class="link badge w-28">{{ $tag->name }}</a>
        @endforeach
    </div>
</div>