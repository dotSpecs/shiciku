@extends('web.layout')

@php
    $displayDynasty = $article->book->dynasty?->name ?: $article->book->chaodai;
    $displayAuthor = $article->book->author?->name ?: $article->book->author_name;
    $inlineSupplementNames = ['段译', '注释', '段赏'];

    $splitHtmlParagraphs = static function (?string $html): array {
        $html = trim((string) $html);

        if ($html === '') {
            return [];
        }

        if (preg_match_all('/<p\b[^>]*>.*?<\/p>/isu', $html, $matches) && count($matches[0]) > 0) {
            return $matches[0];
        }

        return [$html];
    };

    $hasVisibleParagraphContent = static function (?string $html): bool {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00a0}\x{3000}]+/u', '', $text) ?? '';

        return $text !== '' && !preg_match('/^[~～]+$/u', $text);
    };

    $trimParagraphStartWhitespace = static function (?string $html): string {
        $html = (string) $html;
        $html = preg_replace('/(<p\b[^>]*>)[\s\x{00a0}\x{3000}]+/iu', '$1', $html) ?? $html;

        return preg_replace('/^[\s\x{00a0}\x{3000}]+/u', '', $html) ?? $html;
    };

    $toSuperscriptNumber = static function (int $number): string {
        return strtr((string) $number, [
            '0' => '⁰',
            '1' => '¹',
            '2' => '²',
            '3' => '³',
            '4' => '⁴',
            '5' => '⁵',
            '6' => '⁶',
            '7' => '⁷',
            '8' => '⁸',
            '9' => '⁹',
        ]);
    };

    $parseZhushiItems = static function (?string $html) use ($hasVisibleParagraphContent): array {
        if (!$hasVisibleParagraphContent($html)) {
            return [];
        }

        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/^[\s\x{00a0}\x{3000}]+/u', '', $text) ?? $text;
        $items = [];

        foreach (preg_split('/[~～]+/u', $text) ?: [] as $part) {
            $part = preg_replace('/^[\s\x{00a0}\x{3000}]+|[\s\x{00a0}\x{3000}]+$/u', '', $part) ?? trim($part);

            if ($part === '' || !preg_match('/^(.+?)[：:](.+)$/u', $part, $matches)) {
                continue;
            }

            $term = preg_replace('/^[\s\x{00a0}\x{3000}]+|[\s\x{00a0}\x{3000}]+$/u', '', $matches[1]) ?? trim($matches[1]);
            $definition = preg_replace('/^[\s\x{00a0}\x{3000}]+|[\s\x{00a0}\x{3000}]+$/u', '', $matches[2]) ?? trim($matches[2]);

            if ($term !== '' && $definition !== '') {
                $items[] = [
                    'term' => $term,
                    'definition' => $definition,
                ];
            }
        }

        return $items;
    };

    $annotateParagraph = static function (?string $html, array $items) use ($toSuperscriptNumber): string {
        $result = (string) $html;

        foreach ($items as $index => $item) {
            $term = $item['term'] ?? '';

            if ($term === '') {
                continue;
            }

            $sup = '<span class="mx-0.5 text-base text-[#15559a]">' . $toSuperscriptNumber($index + 1) . '</span>';
            $pattern = '/' . preg_quote($term, '/') . '(?![^<]*>)/u';
            $result = preg_replace($pattern, '$0' . $sup, $result, 1) ?? $result;
        }

        return $result;
    };

    $findInlineSupplement = static function (string $name) use ($article) {
        return $article->supplements->first(function ($supplement) use ($name) {
            return trim((string) $supplement->name) === $name;
        });
    };

    $inlineSupplements = [
        'duanyi' => $findInlineSupplement('段译'),
        'zhushi' => $findInlineSupplement('注释'),
        'duanshang' => $findInlineSupplement('段赏'),
    ];
    $inlineParagraphs = [
        'duanyi' => $inlineSupplements['duanyi'] ? $splitHtmlParagraphs($inlineSupplements['duanyi']->content) : [],
        'zhushi' => $inlineSupplements['zhushi'] ? $splitHtmlParagraphs($inlineSupplements['zhushi']->content) : [],
        'duanshang' => $inlineSupplements['duanshang'] ? $splitHtmlParagraphs($inlineSupplements['duanshang']->content) : [],
    ];
    $articleParagraphs = $splitHtmlParagraphs($article->content);
    $hasInlineYizhu = collect($inlineSupplements)->filter()->isNotEmpty();
    $bottomSupplements = $article->supplements
        ->reject(fn ($metadata) => in_array(trim((string) $metadata->name), $inlineSupplementNames, true))
        ->values();
    $articleFullTitle = $article->book->name . '·' . ($article->chapter && $article->chapter->name ? $article->chapter->name . '·' : '') . $article->name;
    $plainContent = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($article->content), ENT_QUOTES, 'UTF-8')));
    $seoDescription = $articleFullTitle . ($displayAuthor ? '，' . $displayAuthor . '作品' : '') . '。' . mb_substr($plainContent, 0, 90);
    $articleUrl = route('book.article', ['book_id' => $article->book->book_id, 'article_id' => $article->article_id]);
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => route('index')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => '古籍', 'item' => route('book.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $article->book->name, 'item' => route('book.show', $article->book->book_id)],
                    ['@type' => 'ListItem', 'position' => 4, 'name' => $article->name, 'item' => $articleUrl],
                ],
            ],
            [
                '@type' => 'Article',
                'name' => $articleFullTitle,
                'headline' => $articleFullTitle,
                'author' => $displayAuthor ? ['@type' => 'Person', 'name' => $displayAuthor] : null,
                'isPartOf' => [
                    '@type' => 'Book',
                    'name' => $article->book->name,
                    'url' => route('book.show', $article->book->book_id),
                ],
                'inLanguage' => 'zh-CN',
                'description' => $seoDescription,
                'articleBody' => $plainContent,
                'url' => $articleUrl,
            ],
        ],
    ];
@endphp

@section('title', $articleFullTitle . '的原文、注释、翻译、赏析')

@section('keywords', $article->book->name . ',' . ($displayAuthor ? $displayAuthor . ',' : '') . ($article->chapter && $article->chapter->name ? $article->chapter->name. ',' : '') . $article->name . ',')
@section('description', $seoDescription)
@section('og_description', $seoDescription)

@section('seo')
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@section('content')
<div class="card mb-8">
    <div class="card-title flex items-center justify-between @if(!$displayDynasty && !$displayAuthor) !mb-0 @endif">
        <h1 class="text-xl">{{ $article->book->name }}</h1>
        <a href="{{ route('book.show', $article->book->book_id) }}" class="link text-sm primary">返回目录</a>
    </div>

    <div class="card-content">
        @if($displayDynasty || $displayAuthor)
        <p class="secondary">
            作者：
            @if($article->book->dynasty)
            {{ $article->book->dynasty->name }}
            @elseif($article->book->chaodai)
            {{ $article->book->chaodai }}
            @endif
            @if($displayDynasty && $displayAuthor)
            ·
            @endif
            @if($article->book->author)
            <a class="link secondary" href="{{ route('author.show', $article->book->author->author_id) }}" id="poem-author">{{ $article->book->author->name }}</a>
            @elseif($displayAuthor)
            <span id="poem-author">{{ $displayAuthor }}</span>
            @endif
        </p>
        @endif
    </div>
</div>

<div class="card @if($bottomSupplements->count() > 0) mb-8 @endif">
    <div class="flex justify-between items-center mb-5">
        <h2 class="card-title text-xl !mb-0">
            <span id="poem-title">{{ $article->name }}</span>
            <span class="text-base font-normal text-gray-500">原文</span>
        </h2>
        <div class="flex gap-2">
            <span class="badge cursor-pointer" id="readAloudBtn" onclick="handleReadAloud('{{ route('book.audio', ['book_id' => $article->book->book_id, 'article_id' => $article->article_id]) }}')">朗读</span>
            <span class="badge cursor-pointer" onclick="toggleBookPinyin()">拼音</span>
            @if($hasInlineYizhu)
            <span class="badge cursor-pointer" onclick="toggleYizhu()">译注</span>
            @endif
        </div>
    </div>
    
    <div class="card-content escape-html leading-10 [&>p]:mb-6" id="poem-content">
        {!! $article->content !!}
    </div>
    @if($hasInlineYizhu)
    <div class="card-content escape-html leading-10 hidden" id="book-yizhu-content">
        @foreach($articleParagraphs as $paragraphIndex => $paragraph)
        @php
            $duanyiParagraph = $inlineParagraphs['duanyi'][$paragraphIndex] ?? null;
            $zhushiParagraph = $inlineParagraphs['zhushi'][$paragraphIndex] ?? null;
            $duanshangParagraph = $inlineParagraphs['duanshang'][$paragraphIndex] ?? null;
            $zhushiItems = $parseZhushiItems($zhushiParagraph);
            $annotatedParagraph = $annotateParagraph($paragraph, $zhushiItems);
        @endphp
        <div class="@if(!$loop->last) mb-6 @endif">
            <div class="[&>p]:mb-2">{!! $annotatedParagraph !!}</div>
            @if($duanyiParagraph && $hasVisibleParagraphContent($duanyiParagraph))
            <div class="text-[#835e1d] [&>p]:mb-2">{!! $duanyiParagraph !!}</div>
            @endif
            @if(count($zhushiItems) > 0)
            <div class="text-[#15559a]">
                @foreach($zhushiItems as $zhushiIndex => $zhushiItem)
                <span class="inline">
                    <span class="mr-0.5 text-base">{{ $toSuperscriptNumber($zhushiIndex + 1) }}</span>{{ $zhushiItem['term'] }}：{{ $zhushiItem['definition'] }}
                </span>
                @endforeach
            </div>
            @elseif($zhushiParagraph && $hasVisibleParagraphContent($zhushiParagraph))
            <div class="text-[#15559a] [&>p]:mb-2">{!! $zhushiParagraph !!}</div>
            @endif
            @if($duanshangParagraph && $hasVisibleParagraphContent($duanshangParagraph))
            <div class="my-4 bg-[#f1efe4] px-8 py-5 text-slate-900 dark:bg-slate-700 dark:text-slate-100 [&>p]:m-0 [&>p]:indent-0">{!! $trimParagraphStartWhitespace($duanshangParagraph) !!}</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Audio Player Container -->
    <div id="audioPlayerContainer" class="mt-6 p-4 bg-slate-50 dark:bg-slate-700 rounded-md" style="display: none;">
        <div class="flex flex-col gap-3">
            {{-- <div class="flex items-center justify-between">
                <span class="text-sm font-medium">正在朗读...</span>
                <button onclick="closeAudioPlayer()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div> --}}
            <audio id="audioPlayer" controls class="w-full h-10"></audio>
        </div>
    </div>

    <script>
        window.poemData = {
            title: @json($article->name),
            dynasty: @json($displayDynasty ?: ''),
            author: @json($displayAuthor ?: ''),
            content: @json($article->content)
        };
    </script>
</div>

@foreach ($bottomSupplements as $metadata)
<div class="article-metadata card @if(!$loop->last) mb-8 @endif">
    <h2 class="article-metadata-title card-title">{{ $metadata->name }}</h2>
    <div class="article-metadata-content card-content leading-10 [&>p]:mb-6">{!! $metadata->content !!}</div>
</div>
@endforeach

@if($article->previous || $article->next)
<div class="card mt-8">
    <div class="flex justify-between items-center">
        @if($article->previous)
        <a href="{{ route('book.article', ['book_id' => $article->book->book_id, 'article_id' => $article->previous->article_id]) }}" class="link primary">上一篇：{{ $article->previous->name }}</a>
        @else
        <span></span>
        @endif

        @if($article->next)
        <a href="{{ route('book.article', ['book_id' => $article->book->book_id, 'article_id' => $article->next->article_id]) }}" class="link primary">下一篇：{{ $article->next->name }}</a>
        @else
        <span></span>
        @endif
    </div>
</div>
@endif

@endsection


@section('sidebar')
<x-sidebar.book-article :book-id="$article->book->book_id" :article-id="$article->article_id" class="mb-8" />

<x-sidebar.hot-book class="mb-8" />

<x-sidebar.hot-tag />
@endsection

@section('script')
<script>
    function resetBookPinyin() {
        const titleEl = document.getElementById('poem-title');
        const dynastyEl = document.getElementById('poem-dynasty');
        const authorEl = document.getElementById('poem-author');
        const articleContent = document.getElementById('poem-content');

        if (!window.poemData || !articleContent || articleContent.dataset.pinyinActive !== 'true') {
            return;
        }

        if (titleEl) titleEl.textContent = window.poemData.title;
        if (dynastyEl) dynastyEl.textContent = window.poemData.dynasty;
        if (authorEl) authorEl.textContent = window.poemData.author;
        articleContent.innerHTML = window.poemData.content;
        delete articleContent.dataset.pinyinActive;
    }

    function toggleBookPinyin() {
        const articleContent = document.getElementById('poem-content');
        const yizhuContent = document.getElementById('book-yizhu-content');

        if (articleContent && yizhuContent && !yizhuContent.classList.contains('hidden')) {
            resetBookPinyin();
            yizhuContent.classList.add('hidden');
            articleContent.classList.remove('hidden');
        }

        togglePinyin();
    }

    function toggleYizhu() {
        const articleContent = document.getElementById('poem-content');
        const yizhuContent = document.getElementById('book-yizhu-content');

        if (!articleContent || !yizhuContent) {
            return;
        }

        resetBookPinyin();
        articleContent.classList.toggle('hidden');
        yizhuContent.classList.toggle('hidden');
    }
</script>
@endsection
