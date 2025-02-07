<header class="sticky top-0 z-50 flex flex-wrap sm:justify-start sm:flex-nowrap w-full bg-white py-5 dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700">
    <nav class="max-w-[85rem] w-full mx-auto px-4 sm:flex sm:items-center sm:justify-between ">
        <div class="flex items-center justify-between">
            <a class="flex items-center text-xl font-semibold dark:text-white focus:outline-none focus:opacity-80" href="{{ route('index') }}" aria-label="古诗词文库">
                <span class="inline-flex items-center gap-x-2 text-xl font-semibold dark:text-white whitespace-nowrap">
                    <img class="w-8 h-auto" src="{{ asset('assets/images/logo/shi-red.png') }}" alt="Logo">
                    古诗词文库
                </span>
            </a>
            <div class="sm:hidden">
                <button type="button" class="hs-collapse-toggle relative size-7 flex justify-center items-center gap-x-2 rounded-lg border border-gray-200 bg-white text-slate-900 shadow-sm hover:bg-gray-50 focus:outline-none focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-transparent dark:border-slate-100 dark:text-white dark:hover:bg-white/10 dark:focus:bg-white/10" id="hs-navbar-top-collapse" aria-expanded="false" aria-controls="hs-navbar-top" aria-label="Toggle navigation" data-hs-collapse="#hs-navbar-top">
                    <svg class="hs-collapse-open:hidden shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" x2="21" y1="6" y2="6" />
                        <line x1="3" x2="21" y1="12" y2="12" />
                        <line x1="3" x2="21" y1="18" y2="18" />
                    </svg>
                    <svg class="hs-collapse-open:block hidden shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                    <span class="sr-only">Toggle navigation</span>
                </button>
            </div>
        </div>
        <div id="hs-navbar-top" class="hidden hs-collapse overflow-hidden transition-all duration-300 basis-full grow sm:block" aria-labelledby="hs-navbar-top-collapse">
            <div class="flex flex-col sm:flex-row justify-between w-full">
                <div class="flex flex-col gap-5 mt-5 sm:flex-row sm:items-center sm:mt-0 sm:ps-10">
                    <a class="font-medium @if(request()->segment(1) == '') active @endif link" href="{{ route('index') }}">首页</a>
                    <a class="font-medium @if(request()->segment(1) == 'poem') active @endif link" href="{{ route('poem.index') }}">诗词</a>
                    <a class="font-medium @if(request()->segment(1) == 'author') active @endif link" href="{{ route('author.index') }}">作者</a>
                    <a class="font-medium @if(request()->segment(1) == 'book') active @endif link" href="{{ route('book.index') }}">古籍</a>
                </div>
                <div class="flex flex-col gap-5 mt-5 sm:flex-row sm:items-center sm:mt-0 ">
                    <div class="relative flex rounded-lg shadow-sm w-full sm:w-auto">
                        <input type="text" @if(request()->segment(2) == 'search') value="{{ request()->input('query') }}" @endif id="search-input" name="search-input" class="py-2 px-3 ps-11 block w-full border border-slate-200 shadow-sm rounded-s-lg text-sm focus:z-10 focus:border-slate-300 focus:outline-none disabled:opacity-50 disabled:pointer-events-none dark:bg-slate-800 dark:border-slate-600 dark:text-slate-400 dark:focus:border-slate-500" placeholder="搜索诗词、作者..." @keydown.enter="handleSearch">
                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-4">
                            <svg class="shrink-0 size-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                        </div>
                        <button type="button" onclick="handleSearch()" class="-ms-px py-2 px-4 inline-flex whitespace-nowrap justify-center items-center text-sm font-medium rounded-e-lg border border-slate-200 bg-slate-50 text-slate-900 hover:bg-slate-100 hover:border-slate-200 focus:outline-none dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-700 dark:hover:border-slate-600 dark:focus:outline-none dark:focus:ring-offset-slate-800">搜索</button>
                    </div>

                    <script>
                        function handleSearch() {
                            const searchInput = document.getElementById('search-input');
                            const query = searchInput.value.trim();

                            if (query) {
                                window.location.href = `{{ route('search') }}?query=${encodeURIComponent(query)}`;
                            }
                        }

                        // 监听回车键
                        document.getElementById('search-input').addEventListener('keydown', function(e) {
                            if (e.key === 'Enter') {
                                handleSearch();
                            }
                        });
                    </script>

                    <div class="hs-dropdown">
                        <button id="hs-dropdown-dark-mode" type="button" class="hs-dropdown-toggle hs-dark-mode group flex items-center link" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                            <svg class="hs-dark-mode-active:hidden block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                            </svg>
                            <svg class="hs-dark-mode-active:block hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="4"></circle>
                                <path d="M12 2v2"></path>
                                <path d="M12 20v2"></path>
                                <path d="m4.93 4.93 1.41 1.41"></path>
                                <path d="m17.66 17.66 1.41 1.41"></path>
                                <path d="M2 12h2"></path>
                                <path d="M20 12h2"></path>
                                <path d="m6.34 17.66-1.41 1.41"></path>
                                <path d="m19.07 4.93-1.41 1.41"></path>
                            </svg>
                        </button>

                        <div id="selectThemeDropdown" class="hs-dropdown-menu hs-dropdown-open:opacity-100 mt-2 hidden z-10 transition-[margin,opacity] opacity-0 duration-300 mb-2 origin-bottom-left bg-white shadow-md rounded-lg p-1 space-y-0.5 dark:bg-slate-800 dark:border dark:border-slate-700 dark:divide-slate-700" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-dark-mode">
                            <button type="button" class="w-full flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm link" data-hs-theme-click-value="default">
                                默认（浅色）
                            </button>
                            <button type="button" class="w-full flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm link" data-hs-theme-click-value="dark">
                                深色
                            </button>
                            <button type="button" class="w-full flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm link" data-hs-theme-click-value="auto">
                                自动（系统）
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>