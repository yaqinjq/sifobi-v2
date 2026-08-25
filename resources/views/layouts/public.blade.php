<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pesan Menu') - SIFOBI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-full flex flex-col">
        <div class="sticky top-0 z-30 bg-primary-800 safe-top">
            <div class="px-4 py-3">
                <p class="font-heading font-semibold text-white text-base truncate">{{ $table->outlet->name ?? 'SIFOBI' }}</p>
                @hasSection('subtitle')
                    <p class="text-primary-300 text-xs truncate mt-0.5">@yield('subtitle')</p>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mx-4 mt-4 rounded-2xl bg-green-50 border border-green-100 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-4 mt-4 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <main class="flex-1 pb-8">
            @yield('content')
        </main>
    </div>
</body>
</html>
