{{-- Halaman tanpa akun (mentor, penguji, wali, PIC mitra, alumni, verifikasi): tidak diindeks, tautan tidak bocor lewat Referer. --}}
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <a class="skip-link" href="#isi-utama">Langsung ke isi utama</a>
    <header class="mentor-top">
        <div class="mentor-shell">
            <span class="wordmark"><img class="brand-mark" src="{{ asset('images/jurnalin-paraf.svg') }}" width="28" height="28" alt=""> Jurnalin</span>
            <span class="text-sm text-on-dark">@yield('context', 'Akses aman tanpa akun')</span>
        </div>
    </header>
    <main id="isi-utama" tabindex="-1" class="mentor-shell mentor-main">
        @if(session('status'))<div role="status" class="mentor-flash">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div role="alert" class="mentor-error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
    <footer class="mentor-shell mentor-foot text-sm text-muted">
        @yield('footer', 'Tautan ini khusus untuk Anda. Jangan diteruskan ke orang lain.') <a class="document-link" href="{{ route('privacy') }}">Kebijakan Privasi</a>
    </footer>
</body>
</html>
