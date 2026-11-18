{{-- Halaman pembimbing DUDI: mandiri, tanpa akun, tidak diindeks, tautan tidak dibocorkan lewat Referer. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>@yield('title') | Jurnalin</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <header class="mentor-top">
        <div class="mentor-shell">
            <span class="wordmark"><img class="brand-mark" src="{{ asset('images/jurnalin-paraf.svg') }}" width="28" height="28" alt=""> Jurnalin</span>
            <span class="text-sm text-on-dark">Tanda tangan pembimbing DUDI</span>
        </div>
    </header>
    <main id="isi-utama" class="mentor-shell mentor-main">
        @yield('content')
    </main>
    <footer class="mentor-shell mentor-foot text-sm text-muted">
        Tautan ini khusus untuk Anda. Jangan diteruskan ke orang lain. <a class="document-link" href="{{ route('privacy') }}">Kebijakan Privasi</a>
    </footer>
</body>
</html>
