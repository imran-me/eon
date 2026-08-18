<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @yield('meta-information')
    <!-- Tailwind CSS CDN -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- <script src="https://cdn.tailwindcss.com"></script> --}}
    @endif
    <!-- Chart.js CDN -->
    @yield('import-script')
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script src="//unpkg.com/alpinejs" defer></script>
</head>

<body class="bg-gray-100">

    <div class="flex min-h-screen md:ml-64">
        <!-- Sidebar -->
        @include('layout.sidebar')

        <!-- Main content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->

            @include('layout.header', ['title' => $title ?? 'Dashboard'])

            @yield('main-content')

        </div>
    </div>

    @yield('raw-script')
    @if(session('success') || session('error'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 4000)"
            x-show="show"
            x-transition
            class="fixed bottom-4 right-4 px-4 py-3 rounded shadow-lg text-white text-sm z-50
                {{ session('success') ? 'bg-green-600' : 'bg-red-600' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif
</body>
</html>