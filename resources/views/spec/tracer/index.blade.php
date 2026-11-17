@extends('layouts.app', ['title' => 'Tracer Study Internal - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Penelusuran alumni (tracer study)</h1>
        <p>Survei alumni untuk evaluasi internal sekolah. Bukan BKK, bukan lowongan, dan bukan pelaporan otomatis ke platform pemerintah. Pelaporan resmi tetap dilakukan di portal resmi.</p>
    </div>
</div>
<div class="split">
    <div class="stack">
        <form method="POST" action="{{ route('admin.tracer.graduates') }}" class="card-muted stack-sm">
            @csrf
            <h2 class="section-title">1. Konfirmasi kelulusan</h2>
            <div><label class="label" for="cl">Kelas</label><select id="cl" name="class_name" class="field">@foreach($classes as $c)<option>{{ $c }}</option>@endforeach</select></div>
            <div><label class="label" for="gd">Tanggal lulus</label><input id="gd" name="graduated_at" type="date" class="field" required></div>
            <button class="btn btn-outline btn-sm">Tandai lulus</button>
            <p class="hint">{{ $graduates }} alumni tercatat. @foreach($cohorts as $y => $c) Angkatan {{ $y }}: {{ $c }}. @endforeach</p>
        </form>
        <form method="POST" action="{{ route('admin.tracer.store') }}" class="card-muted stack-sm">
            @csrf
            <h2 class="section-title">2. Buat kampanye</h2>
            <div><label class="label" for="tn">Nama</label><input id="tn" name="name" class="field" required></div>
            <div><label class="label" for="ty">Angkatan lulus</label><input id="ty" name="cohort_year" type="number" class="field" value="{{ now()->year - 1 }}" required></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="to">Dibuka</label><input id="to" name="opens_on" type="date" class="field" required value="{{ now()->toDateString() }}"></div>
                <div><label class="label" for="tc">Ditutup</label><input id="tc" name="closes_on" type="date" class="field" required></div>
            </div>
            <div><label class="label" for="ti">Pengantar & tujuan survei</label><textarea id="ti" name="intro" class="field"></textarea></div>
            <button class="btn btn-navy">Buat kampanye</button>
        </form>
    </div>
    <section class="stack-sm">
        @forelse($campaigns as $c)
            <a href="{{ route('admin.tracer.show', $c) }}" class="card flex flex-wrap justify-between gap-2">
                <span><strong>{{ $c->name }}</strong><span class="block hint">Angkatan {{ $c->cohort_year }} · {{ $c->opens_on->format('d/m/Y') }} s.d. {{ $c->closes_on->format('d/m/Y') }}</span></span>
                <span class="badge tabular-nums">{{ $c->submitted_count }}/{{ $c->responses_count }} respons</span>
            </a>
        @empty
            <div class="empty">Belum ada kampanye tracer.</div>
        @endforelse
    </section>
</div>
@endsection
