{{-- Sidebar panel untuk layar 64rem ke atas. Di HP disembunyikan; menu "Menu panel" di header yang dipakai. --}}
<aside class="panel-sidebar" aria-label="Menu panel">
    <a class="sidebar-brand" href="{{ auth()->user()->role->homePath() }}">
        <img src="{{ asset('images/jurnalin-paraf.svg') }}" width="34" height="34" alt=""> Jurnalin
    </a>
    @if(auth()->user()->school)
        <p class="sidebar-school"><x-icon name="school" /> <span>{{ auth()->user()->school->name }}</span></p>
    @endif
    <nav class="sidebar-nav" aria-label="Navigasi utama panel">
        @foreach($groups as $groupLabel => $groupLinks)
            <p class="sidebar-group">{{ $groupLabel }}</p>
            @foreach($groupLinks as $route => $label)
                <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        @endforeach
    </nav>
    <a href="{{ route('contact') }}" class="sidebar-help">
        <strong>Butuh bantuan?</strong>
        <span>Lihat panduan atau hubungi tim dukungan Jurnalin.</span>
    </a>
</aside>
