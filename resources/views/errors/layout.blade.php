{{-- Tata letak error mandiri: tidak menyentuh database atau sesi, jadi tetap tampil saat 500/503. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>@yield('code') {{ $__env->yieldContent('title') }} | Jurnalin</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <main id="isi-utama" class="error-page">
        <a class="wordmark" href="/">
            <img class="brand-mark" src="{{ asset('images/jurnalin-paraf.svg') }}" width="34" height="34" alt=""> Jurnalin
        </a>
        <p class="error-code tabular-nums">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="error-text">@yield('message')</p>
        <div class="front-actions">
            @yield('actions')
        </div>
    </main>
</body>
</html>
