<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title') - 古诗词文库</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, minimal-ui" />
    <meta name="referrer" content="always">
    <meta http-equiv="content-type" content="text/html;charset=utf-8">
    <meta name="theme-color" content="#FFFFFF" />
    <meta name="author" content="Specs">
    <meta name="baidu-site-verification" content="codeva-T3HNzwpUTs" />
    <meta name="baidu_union_verify" content="fdc73309de255e6b8c25cbf4db0e52de">

    <meta name="keywords" content="@yield('keywords')古诗词文库,古诗,诗词,古文,唐诗,宋词,古文,诗,词,曲,赋,文,诗人" />
    <meta name="description" content="@yield('description')古诗词文库是一个古诗词、古文收录网站，目前已收录古诗词超60万首，作者2万余人。其中包含唐诗/宋词/元曲/诸子百家等多种著作，内容持续优化更新中。" />

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="shortcut icon" href="https://cdn.meirishici.com/meirishici-favicon.ico" />
    <link rel="apple-touch-icon" href="https://cdn.meirishici.com/assets/images/logo/shiciwenku-mini.png" sizes="144x144" />

    <!-- Facebook & LinkedIn Open Graph Tags -->
    <meta property="og:title" content="@yield('title') - 古诗词文库" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="古诗词文库" />
    <meta property="og:image" content="https://cdn.meirishici.com/assets/images/logo/shiciwenku.png" />
    <meta property="og:description" content="古诗词文库是一个古诗词、古文收录网站，目前已收录古诗词超60万首，作者2万余人。其中包含唐诗/宋词/元曲/诸子百家等多种著作，内容持续优化更新中。" />
    <!-- Twitter Card Tags -->
    <meta property="twitter:title" content="@yield('title') - 古诗词文库" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:site" content="@NeverMore2oo8" />
    <meta name="twitter:image" content="https://cdn.meirishici.com/assets/images/logo/shiciwenku.png" />
    <meta name="twitter:description" content="古诗词文库是一个古诗词、古文收录网站，目前已收录古诗词超60万首，作者2万余人。其中包含唐诗/宋词/元曲/诸子百家等多种著作，内容持续优化更新中。" />
    <!-- Weibo Meta Tags -->
    <meta itemprop="name" content="@yield('title') - 古诗词文库" />
    <meta itemprop="image" content="https://cdn.meirishici.com/assets/images/logo/shiciwenku.png" />
    <meta itemprop="description" content="古诗词文库是一个古诗词、古文收录网站，目前已收录古诗词超60万首，作者2万余人。其中包含唐诗/宋词/元曲/诸子百家等多种著作，内容持续优化更新中。" />

    <script async src="https://www.googletagmanager.com/gtag/js?id=G-BGEFGH5VHW"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-BGEFGH5VHW');
    </script>

    @yield('seo')

    <!-- Pinterest Tags -->
    <meta name="pinterest-rich-pin" content="true" />
</head>

<body class="text-base bg-gray-50 dark:bg-slate-900 dark:text-slate-100">
    <x-navbar></x-navbar>
    <div class="max-w-[85rem] w-full mx-auto px-4 py-8 md:flex md:space-x-4">
        <!-- 主体内容 占比 80% -->
        <div class="main sm:w-full md:w-8/12">
            @yield('content')
        </div>

        <!-- 侧边栏 占比 20% -->
        <div class="sidebar sm:w-full md:w-4/12 relative pt-8 md:pt-0">
            <div class="sticky top-24">
                @yield('sidebar')

                <!-- <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6074386496019881" crossorigin="anonymous"></script> -->
                <!-- <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-6074386496019881" data-ad-slot="6822112578" data-ad-format="auto" data-full-width-responsive="true"></ins>
                <script>
                    (adsbygoogle = window.adsbygoogle || []).push({});
                </script> -->
            </div>
        </div>
    </div>
    <footer class="max-w-[85rem] w-full mx-auto px-4 py-8 flex justify-between items-center text-sm text-gray-500 dark:text-gray-400">
        <p class="">
            &copy; {{date('Y')}} ku.meirishici.com. 古诗词文库. All Rights Reserved. <a href="http://beian.miit.gov.cn" target="_blank" rel="nofollow" class="link secondary">冀ICP备14020811号-5</a>
        </p>
        <p>
            <a href="https://meirishici.com" target="_blank" class="link secondary underline">每日诗词</a>
        </p>
    </footer>

    @yield('script')

    <script>
        var _hmt = _hmt || [];
        (function() {
            var hm = document.createElement("script");
            hm.src = "https://hm.baidu.com/hm.js?556d44992450c9c3effc976d52979983";
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(hm, s);
        })();
    </script>
</body>

</html>