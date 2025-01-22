@extends('web.layout')

@section('title', '古籍：' . $book->name . '的全文及简介' . ($book->author ? ' - 作者：' . $book->author->name : ''))

@section('keywords', $book->name . ',' . ($book->author ? $book->author->name . ',' : ''))
@section('description', $book->name . '古籍全文及简介,' . ($book->author ? $book->author->name . '的古籍,' : ''))

@section('content')
<div class="card mb-8">
    <h1 class="card-title">{{ $book->name }}</h1>
    <div class="card-content escape-html leading-10">
        @if($book->author)
        <p class="secondary">
            作者：<a class="link secondary" href="{{ route('author.show', $book->author->author_id) }}">{{ $book->author->name }}</a>
        </p>
        @endif
        <div class="escape-html leading-10 [&>p]:mb-6">
            {!! $book->content !!}
        </div>
    </div>
</div>

<div class="card">
    <h2 class="card-title">目录</h2>
    <div class="card-content">
        <!-- Tree Root -->
        <div class="hs-accordion-treeview-root" role="tree" aria-orientation="vertical">
            <!-- 1st Level Accordion Group -->
            <div class="hs-accordion-group"  role="group" data-hs-accordion-always-open="">
                @foreach ($book->chapters as $chapter)
                <!-- 1st Level Accordion -->
                <div class="hs-accordion active" role="treeitem" aria-expanded="true" id="hs-close-currently-opened-tree-heading-{{ $chapter->id }}">
                    <!-- 1st Level Accordion Heading -->
                    <div class="hs-accordion-heading py-0.5 flex items-center gap-x-0.5 w-full">
                        <button class="hs-accordion-toggle size-6 flex justify-center items-center hover:bg-gray-100 rounded-md focus:outline-none focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:hover:bg-slate-700 dark:focus:bg-slate-700" aria-expanded="false" aria-controls="hs-close-currently-opened-tree-collapse-{{ $chapter->id }}">
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"></path>
                                <path class="hs-accordion-active:hidden block" d="M12 5v14"></path>
                            </svg>
                        </button>

                        <div class=" px-1.5 rounded-md cursor-pointer">
                            <div class="flex items-center gap-x-3">
                                <svg class="shrink-0 size-4 text-gray-500 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"></path>
                                </svg>
                                <div class="grow">
                                    {{$chapter->name ?: '章节'}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End 1st Level Accordion Heading -->

                    <!-- 1st Level Collapse -->
                    <div id="hs-close-currently-opened-tree-collapse-{{ $chapter->id }}" class="hs-accordion-content w-full overflow-hidden transition-[height] duration-300" role="group" aria-labelledby="hs-close-currently-opened-tree-heading-{{ $chapter->id }}">
                        <div class="ms-3 ps-3 relative before:absolute before:top-0 before:start-0 before:w-0.5 before:-ms-px before:h-full ">
                            @foreach ($chapter->articles as $article)
                            <!-- 1st Level Item -->
                            <div class=" px-2 rounded-md cursor-pointer" role="treeitem">
                                <div class="flex items-center gap-x-3">
                                    <svg class="shrink-0 size-4 text-gray-500 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                    </svg>
                                    <div class="grow">
                                        <span class="text-sm">
                                            <a class="link " href="{{ route('book.article', ['book_id' => $book->book_id, 'article_id' => $article->article_id]) }}">
                                                {{ $article->name }}
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- End 1st Level Item -->
                            @endforeach
                        </div>
                    </div>
                    <!-- End 1st Level Collapse -->
                </div>
                <!-- End 1st Level Accordion -->
                @endforeach
            </div>
            <!-- End 1st Level Accordion Group -->
        </div>
        <!-- End Tree Root -->
    </div>
</div>




@endsection

@section('sidebar')
<x-sidebar.hot-book class="mb-8" />
<x-sidebar.hot-tag />
@endsection