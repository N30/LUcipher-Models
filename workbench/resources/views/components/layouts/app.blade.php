<!doctype html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>lucipher/models · examples</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="min-h-full text-zinc-800">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3 text-sm">
            <a href="/web/posts" class="font-semibold">lucipher/models</a>
            <nav class="flex flex-wrap gap-3 text-zinc-600">
                <a href="/web/posts" class="hover:text-zinc-900">/web</a>
                <a href="/spa/posts" class="hover:text-zinc-900">/spa (Livewire)</a>
                <a href="/api/posts" class="hover:text-zinc-900">/api</a>
                <a href="/api/openapi.json" class="hover:text-zinc-900">openapi.json</a>
            </nav>
            <span class="ml-auto text-zinc-500">{{ auth()->user()?->name ?? 'guest' }}</span>
        </div>
    </header>
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-6 md:grid-cols-[14rem_1fr]">
        <aside>
            <x-lu_models::navlist :type="request()->is('spa/*') ? 'spa' : 'web'" />
        </aside>
        <main>
            @yield('header')
            @yield('content')
        </main>
    </div>
    @livewireScripts
</body>
</html>
