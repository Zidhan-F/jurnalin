{{-- Sidebar panel untuk layar 64rem ke atas. Di HP disembunyikan; menu "Menu panel" di header yang dipakai.
     Setiap grup adalah menu induk yang bisa dibuka-tutup; grup halaman yang sedang dibuka terbuka otomatis. --}}
<aside class="panel-sidebar" aria-label="Menu panel">
    <a class="sidebar-brand" href="{{ auth()->user()->role->homePath() }}">
        <img src="{{ asset('images/jurnalin-paraf.svg') }}" width="34" height="34" alt=""> Jurnalin
    </a>
    @if(auth()->user()->school)
        <p class="sidebar-school"><x-icon name="school" /> <span>{{ auth()->user()->school->name }}</span></p>
    @endif
    @php
        $groupIcons = ['Utama' => 'calendar', 'Manajemen PKL' => 'journal', 'Laporan' => 'print', 'Sekolah & akun' => 'school', 'Pendampingan' => 'teacher', 'Program sekolah' => 'award'];
        $activeGroup = collect($groups)->search(fn ($links) => collect(array_keys($links))->contains(fn ($r) => request()->routeIs($r)));
        $activeGroup = $activeGroup === false ? array_key_first($groups) : $activeGroup;
    @endphp
    <nav class="sidebar-nav" aria-label="Navigasi utama panel">
        @foreach($groups as $groupLabel => $groupLinks)
            <details class="sidebar-group" @if($groupLabel === $activeGroup || count($groups) === 1) open @endif @if($groupLabel === $activeGroup) data-active @endif>
                <summary><x-icon :name="$groupIcons[$groupLabel] ?? 'menu'" /> <span>{{ $groupLabel }}</span></summary>
                <div class="sidebar-sub">
                    @foreach($groupLinks as $route => $label)
                        <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </details>
        @endforeach
    </nav>
    <a href="{{ route('contact') }}" class="sidebar-help">
        <strong>Butuh bantuan?</strong>
        <span>Lihat panduan atau hubungi tim dukungan Jurnalin.</span>
    </a>
</aside>
