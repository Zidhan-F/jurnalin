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
    <header class="on-dark px-4 py-3.5 border-b border-navy-strong">
        <div class="max-w-lg mx-auto flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-xs text-on-dark">{{ auth()->user()->school?->name }}</div>
                <div class="font-semibold text-base">{{ auth()->user()->name }}</div>
            </div>
            @php $unread = \App\Models\UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')->count(); @endphp
            <a href="{{ route('notifications.index') }}" class="ml-auto px-3 py-2 rounded-lg border border-on-dark text-sm text-white hover:bg-white/10 min-h-11 inline-flex items-center gap-1.5">
                <x-icon name="bell" /><span class="sr-only">Notifikasi</span>
                @if($unread)<span class="notif-count">{{ $unread > 99 ? '99+' : $unread }}<span class="sr-only"> belum dibaca</span></span>@endif
            </a>
            <a href="/password" class="px-3 py-2 rounded-lg border border-on-dark text-sm text-white hover:bg-white/10 min-h-11 inline-flex items-center">Sandi</a>
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-lg border border-on-dark text-sm text-white hover:bg-white/10 min-h-11 cursor-pointer">
                    Keluar
                </button>
            </form>
        </div>
    </header>

    @include('partials.install-prompt')
    <main id="isi-utama" tabindex="-1" class="max-w-lg mx-auto px-4 py-5 space-y-5">
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
            @foreach(['student.home' => ['calendar', 'Hari ini'], 'student.journals' => ['journal', 'Jurnal'], 'student.profile' => ['profile', 'Profil & nilai']] as $route => [$icon, $label])
                @continue($route !== 'student.journals' && request()->attributes->get('grace_placements') && ! request()->attributes->get('full_placements'))
                <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>
                    <x-icon :name="$icon" /> {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>
    <x-choice-dialog />
</body>
</html>
