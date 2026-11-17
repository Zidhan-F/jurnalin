<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=paraf-j" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>{{ $title ?? 'Jurnalin - Manajemen PKL SMK' }}</title>
    @php $publicPage = request()->routeIs('home', 'about', 'pricing', 'faq', 'terms', 'privacy', 'contact', 'login', 'schools.register', 'password.request', 'password.reset'); @endphp
    @if(request()->routeIs('home', 'about', 'pricing', 'faq', 'terms', 'privacy', 'contact', 'schools.register'))
        <meta name="description" content="{{ $description ?? 'Jurnalin: buku jurnal PKL digital untuk SMK semua program keahlian. Absen GPS dan foto, jurnal harian, validasi guru, tanda tangan pembimbing DUDI, dan PDF ber-kop sekolah.' }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Jurnalin">
        <meta property="og:title" content="{{ $title ?? 'Jurnalin' }}">
        <meta property="og:description" content="{{ $description ?? 'Buku jurnal PKL digital untuk SMK semua program keahlian.' }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="id_ID">
        @if(request()->routeIs('home'))
            <script type="application/ld+json">{!! \Illuminate\Support\Js::encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Jurnalin', 'url' => route('home'), 'inLanguage' => 'id', 'description' => $description]) !!}</script>
        @endif
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased flex flex-col">
    <a class="skip-link" href="#isi-utama">Langsung ke isi utama</a>
    <header class="site-header">
        <div class="shell masthead {{ $publicPage ? 'public-masthead' : '' }}">
            <a class="wordmark" href="{{ $publicPage ? route('home') : auth()->user()->role->homePath() }}">
                <img class="brand-mark" src="{{ asset('images/jurnalin-paraf.svg') }}" width="34" height="34" alt=""> Jurnalin
            </a>
            @if($publicPage)
                <nav class="public-menu" aria-label="Informasi Jurnalin">
                    @foreach(['tentang' => 'Tentang', 'harga' => 'Harga', 'faq' => 'FAQ'] as $section => $label)
                        <a href="{{ route('home') }}#{{ $section }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="public-nav">
                    @auth
                        <a class="header-action" href="{{ auth()->user()->role->homePath() }}">Buka panel</a>
                    @else
                        <a class="header-action" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Masuk</a>
                    @endauth
                </div>
            @else
                @php $unread = auth()->user()->school_id ? \App\Models\UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')->count() : 0; @endphp
                <div class="flex items-center gap-1 ml-auto">
                @if(auth()->user()->school_id)
                    <a href="{{ route('notifications.index') }}" class="notif-bell" @if(request()->routeIs('notifications.*')) aria-current="page" @endif>
                        <x-icon name="bell" /> <span class="sr-only sm:not-sr-only">Notifikasi</span>
                        @if($unread)<span class="notif-count">{{ $unread > 99 ? '99+' : $unread }}<span class="sr-only"> belum dibaca</span></span>@endif
                    </a>
                @endif
                <details class="panel-account" x-data @click.outside="$el.open = false" @keydown.escape="$el.open = false; $refs.account.focus()">
                    <summary x-ref="account"><x-icon name="profile" /> Akun saya</summary>
                    <div class="account-panel">
                    <div>{{ auth()->user()->name }}<small>{{ auth()->user()->role->label() }}@if(auth()->user()->school) · {{ auth()->user()->school->name }}@endif</small></div>
                    <a href="/password" class="header-action">Ganti Sandi</a>
                    @if(auth()->user()->school_id)<a href="{{ route('privacy.center') }}" class="header-action">Privasi saya</a>@endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="header-action">Keluar</button></form>
                    </div>
                </details>
                </div>
            @endif
        </div>
        @unless($publicPage)
            @php
                $links = match(auth()->user()->role) {
                    // Admin sekolah: menu dikelompokkan menurut urutan kerja (persiapan, selama PKL, urusan sekolah).
                    \App\Enums\UserRole::SchoolAdmin => [
                        '' => ['admin.index' => 'Ringkasan'],
                        'Persiapan PKL' => [
                            'admin.periods' => 'Periode & bobot nilai', 'admin.companies' => 'Tempat PKL',
                            'admin.teachers' => 'Guru pembimbing', 'admin.students' => 'Siswa',
                            'admin.placements' => 'Penempatan siswa', 'admin.import' => 'Impor dari Excel',
                        ],
                        'Selama PKL' => [
                            'visits.index' => 'Kunjungan guru', 'tickets.inbox' => 'Laporan dari siswa',
                            'mentor-access.index' => 'Akses pembimbing industri', 'admin.certificates' => 'Sertifikat',
                            ...(auth()->user()->school->hasFeature('hubin') ? ['admin.hubin.center' => 'Pusat Hubin'] : []),
                            ...(auth()->user()->school->plan() === \App\Enums\SchoolPlan::Pro ? ['admin.hubin' => 'Rekap Hubin'] : []),
                        ],
                        'Sekolah & akun' => [
                            'admin.settings' => 'Pengaturan & kop surat', 'admin.payments' => 'Grup & pembayaran',
                            'admin.modules' => 'Fitur tambahan', 'admin.privacy' => 'Privasi data', 'admin.activity' => 'Riwayat aktivitas',
                        ],
                    ],
                    \App\Enums\UserRole::Teacher => request()->attributes->get('grace_placements') && ! request()->attributes->get('full_placements')
                        ? ['teacher.index' => 'Validasi jurnal']
                        : [
                            '' => ['teacher.index' => 'Hari ini', 'teacher.review' => 'Periksa jurnal', 'teacher.assessments' => 'Nilai dan laporan'],
                            'Pendampingan' => [
                                'visits.index' => 'Kunjungan', 'tickets.inbox' => 'Laporan dari siswa', 'mentor-access.index' => 'Akses pembimbing industri',
                                ...(auth()->user()->school->hasFeature('internship') ? ['teacher.internship' => 'Magang guru'] : []),
                            ],
                        ],
                    \App\Enums\UserRole::SuperAdmin => [
                        'super-admin.index' => 'Verifikasi sekolah', 'super-admin.payments' => 'Verifikasi pembayaran',
                        'super-admin.modules' => 'Modul sekolah', 'super-admin.operations' => 'Operasional',
                    ],
                    default => [],
                };
            @endphp
            @if($links)
                <nav class="section-nav" aria-label="Navigasi utama" x-data="{ expanded: false }" :class="{ 'nav-ready': true, 'nav-expanded': expanded }" @keydown.escape="if (expanded) { expanded = false; $refs.menu.focus() }">
                    <div class="shell">
                        <button type="button" x-ref="menu" class="panel-menu-toggle" @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded" aria-controls="panel-menu-links">
                            <span><x-icon name="menu" /> Menu panel</span>
                            @php
                            // Peran lain tetap menu datar; dibungkus satu grup tanpa judul agar satu cara render.
                            $groups = is_array(reset($links)) ? $links : ['' => $links];
                            $currentLabel = collect($groups)->collapse()->get(request()->route()->getName(), '');
                        @endphp
                        <span class="text-muted font-normal">{{ $currentLabel }}</span>
                        </button>
                        <div id="panel-menu-links" class="panel-menu-links">
                        @foreach($groups as $groupLabel => $groupLinks)
                            @if($groupLabel === '')
                                @foreach($groupLinks as $route => $label)
                                    <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                                @endforeach
                            @else
                                @php $groupActive = collect(array_keys($groupLinks))->contains(fn ($r) => request()->routeIs($r)); @endphp
                                <details class="nav-group" @if($groupActive) data-active @endif x-data x-init="if ($el.hasAttribute('data-active') && window.matchMedia('(max-width: 48rem)').matches) $el.open = true"@click.outside="if (window.matchMedia('(min-width: 48.01rem)').matches) $el.open = false" @keydown.escape="$el.open = false; $el.querySelector('summary').focus()">
                                    <summary>{{ $groupLabel }}@if($groupActive)<span class="sr-only"> (halaman ini ada di sini)</span>@endif</summary>
                                    <div class="nav-group-links">
                                        @foreach($groupLinks as $route => $label)
                                            <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endforeach
                        </div>
                    </div>
                </nav>
            @endif
        @endunless
    </header>

    @if(auth()->check() || request()->routeIs('login'))
        <div class="shell">@include('partials.install-prompt')</div>
    @endif
    <main id="isi-utama" tabindex="-1" class="shell page-content flex-1">
        @if(session('status'))
            <div role="status" @if(session('status_popup')) x-data="{ visible: true }" x-show="visible" class="fixed bottom-4 inset-x-4 sm:left-auto sm:max-w-sm z-50 rounded-lg border border-field bg-paper p-4 shadow-lg" @else class="mb-6 rounded-xl border border-emerald-check/30 bg-emerald-check/10 px-4 py-3 text-sm text-emerald-ink font-medium" @endif>
                {{ session('status') }}
                @if(session('status_popup'))<button type="button" class="document-link px-2" @click="visible = false">Tutup</button>@endif
            </div>
        @endif

        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div role="alert" class="mb-6 rounded-xl border border-amber-warn/40 bg-amber-warn/10 px-4 py-3 text-sm text-amber-ink">
                <div class="font-semibold mb-1">Beberapa baris dilewati saat impor:</div>
                <ul class="list-disc pl-5 space-y-0.5 text-xs">
                    @foreach(session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('magic_link'))
            <div role="status" class="callout callout-ok mb-6 stack-sm">
                <div class="font-semibold">Tautan masuk untuk {{ session('magic_link.name') }}</div>
                <input class="field tabular-nums" readonly value="{{ session('magic_link.url') }}" aria-label="Tautan masuk" onfocus="this.select()">
                <a class="btn btn-primary btn-sm" href="{{ session('magic_link.wa') }}" target="_blank" rel="noopener">Bagikan lewat WhatsApp</a>
            </div>
        @endif

        <x-error-summary class="mb-6" />

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="border-t border-border bg-stone py-3 text-sm text-muted">
        <div class="shell flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>Jurnalin · Buku jurnal PKL sekolah</div>
            <nav aria-label="Tautan kaki halaman" class="footer-nav"><a href="{{ route('home') }}#tentang">Tentang</a><a href="{{ route('home') }}#harga">Harga</a><a href="{{ route('home') }}#faq">FAQ</a><a href="{{ route('contact') }}">Kontak</a><a href="{{ route('terms') }}">Syarat</a><a href="{{ route('privacy') }}">Privasi</a></nav>
        </div>
    </footer>
    <x-pkl-datalists />
    <x-choice-dialog />
</body>
</html>
