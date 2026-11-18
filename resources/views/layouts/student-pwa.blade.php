<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=paraf-j" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>{{ $title ?? 'Jurnalin Siswa' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased student-page">
    <a href="#isi-utama" class="skip-link">Langsung ke isi utama</a>
    <header class="student-header">
        <div class="max-w-lg mx-auto flex items-center gap-2">
            <a href="{{ route('student.home') }}" class="student-brand">
                <img src="{{ asset('images/jurnalin-paraf.svg') }}" width="30" height="30" alt=""> Jurnalin <span class="badge badge-navy">PKL</span>
            </a>
            @php $unread = \App\Models\UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')->count(); @endphp
            <a href="{{ route('notifications.index') }}" class="student-icon-btn ml-auto">
                <x-icon name="bell" /><span class="sr-only">Notifikasi</span>
                @if($unread)<span class="notif-count">{{ $unread > 99 ? '99+' : $unread }}<span class="sr-only"> belum dibaca</span></span>@endif
            </a>
            <details class="student-account" x-data @click.outside="$el.open = false" @keydown.escape="$el.open = false; $refs.acc.focus()">
                <summary x-ref="acc"><x-avatar :user="auth()->user()" /><span class="sr-only">Akun saya</span></summary>
                <div class="account-panel">
                    <div>{{ auth()->user()->name }}<small>{{ auth()->user()->school?->name }}</small></div>
                    <a href="{{ route('student.profile') }}" class="header-action">Profil & nilai</a>
                    <a href="/password" class="header-action">Ganti sandi</a>
                    <form method="POST" action="/logout">@csrf<button type="submit" class="header-action">Keluar</button></form>
                </div>
            </details>
        </div>
    </header>

    <aside class="student-sidebar" aria-label="Menu siswa desktop">
        <a href="{{ route('student.home') }}" class="sidebar-brand"><img src="{{ asset('images/jurnalin-paraf.svg') }}" width="34" height="34" alt=""> Jurnalin</a>
        @if(auth()->user()->school)<p class="sidebar-school"><x-icon name="school" /> <span>{{ auth()->user()->school->name }}</span></p>@endif
        <nav class="sidebar-nav" aria-label="Navigasi siswa">
            @foreach(\App\Support\StudentNavigation::items(request()) as $item)
                <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
            @if($help = \App\Support\StudentNavigation::help(request()))
                <a href="{{ route($help['route']) }}" @if(request()->routeIs($help['route'])) aria-current="page" @endif>{{ $help['label'] }}</a>
            @endif
        </nav>
        <div class="sidebar-help"><strong>{{ auth()->user()->name }}</strong><span>Siswa PKL</span></div>
    </aside>
    @include('partials.install-prompt')
    <main id="isi-utama" tabindex="-1" class="student-main max-w-lg mx-auto px-4 py-5 space-y-5">
        @if(session('status'))
            <div role="status" class="rounded-xl border border-emerald-check/30 bg-emerald-check/10 px-4 py-3 text-sm text-emerald-ink font-medium">
                {{ session('status') }}
            </div>
        @endif

        <x-error-summary />

        @yield('content')
    </main>

    <nav aria-label="Menu siswa" class="student-nav">
        <div>
            @foreach(\App\Support\StudentNavigation::items(request()) as $item)
                <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
    <x-choice-dialog />
</body>
</html>
