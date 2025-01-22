<div  {{ $attributes->merge(['class' => 'card']) }}>
    <h2 class="card-title flex items-center justify-between mb-8">
        <span class="underline underline-offset-8 ">热门古籍</span>
        <a href="{{ route('book.index') }}" class="link text-sm primary">更多 +</a>
    </h2>
    <div class="card-content flex flex-wrap justify-between gap-2">
        @foreach ($books as $book)
        <a href="{{ route('book.show', ['book_id' => $book->book_id]) }}" class="link badge w-28">{{ $book->name }}</a>
        @endforeach
    </div>
</div>