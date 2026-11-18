@extends('layouts.app', ['title' => 'Pusat kemitraan Hubin - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Pusat kemitraan Hubin</h1>
        <p>Kemitraan sekolah dengan industri: status mitra, kuota periode {{ $period?->name ?? 'aktif' }}, dokumen kerja sama, pengingat, dan distribusi peserta. Angka terkonfirmasi dibedakan dari yang belum.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.hubin.placements') }}" class="btn btn-navy btn-sm">Penempatan massal</a>
        <a href="{{ route('admin.hubin.archive') }}" class="btn btn-outline btn-sm">Realisasi tahunan</a>
        <a href="{{ route('admin.dudi-map') }}" class="btn btn-outline btn-sm">Peta DUDI</a>
    </div>
</div>

<dl class="kpi-grid mb-6" aria-label="Ringkasan kemitraan">
    <div class="card kpi-card"><img src="/images/illustrations/partnership.svg" width="40" height="40" alt=""><div><dt>Mitra aktif</dt><dd>{{ $hubinKpi['activePartners'] }} <small>mitra</small></dd></div></div>
    <div class="card kpi-card"><img src="/images/illustrations/calendar.svg" width="40" height="40" alt=""><div><dt>Dalam proses</dt><dd>{{ $hubinKpi['prospects'] }} <small>prospek</small></dd></div></div>
    <div class="card kpi-card"><img src="{{ \App\Support\Mascot::role('student') }}" width="40" height="40" alt=""><div><dt>Total penempatan</dt><dd>{{ $hubinKpi['placements'] }} <small>siswa</small></dd></div></div>
    <div class="card kpi-card"><img src="/images/illustrations/chart.svg" width="40" height="40" alt=""><div><dt>Program aktif</dt><dd>{{ $hubinKpi['activePrograms'] }} <small>dari {{ count($programs) }}</small></dd></div></div>
</dl>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <section class="card" aria-labelledby="judul-mitra-terbaru">
        <h2 id="judul-mitra-terbaru" class="section-title">Mitra terbaru</h2>
        @forelse($latestPartners as $partner)
            @php $quota = $quotas->firstWhere('company_id', $partner->id); @endphp
            <a href="{{ route('admin.partners.show', $partner) }}" class="flex items-center justify-between gap-3 py-2.5 border-t border-border mt-2">
                <span class="min-w-0"><span class="block font-semibold text-ink truncate">{{ $partner->name }}</span>
                <span class="hint">{{ $quota ? 'Kuota: '.$quota->capacity.' siswa' : 'Kuota belum diisi' }} · {{ $partner->industry_type ?: 'Bidang belum diisi' }}</span></span>
                <span class="badge shrink-0">{{ $partner->active_placements_count }} siswa</span>
            </a>
        @empty
            <p class="hint mt-2">Belum ada mitra. Tambahkan dari halaman Tempat PKL.</p>
        @endforelse
    </section>
    <section class="card" aria-labelledby="judul-program">
        <h2 id="judul-program" class="section-title">Program kerja sama</h2>
        @foreach($programs as $program)
            <div class="flex items-center justify-between gap-3 py-2.5 border-t border-border mt-2">
                <span><span class="block font-semibold text-ink">{{ $program['label'] }}</span><span class="hint">{{ $program['detail'] }}</span></span>
                <span @class(['badge shrink-0', 'badge-ok' => $program['active']])>{{ $program['active'] ? 'Aktif' : 'Nonaktif' }}</span>
            </div>
        @endforeach
    </section>
</div>

<dl class="stat-row mb-6">
    @foreach(\App\Models\Company::PARTNERSHIP_LABELS as $key => $label)<div><dt>Mitra {{ strtolower($label) }}</dt><dd>{{ $statusCounts[$key] ?? 0 }}</dd></div>@endforeach
    <div><dt>Kuota terkonfirmasi</dt><dd>{{ $quotas->whereNotNull('confirmed_at')->sum('capacity') }}</dd></div>
    <div><dt>Kuota belum dikonfirmasi</dt><dd>{{ $quotas->whereNull('confirmed_at')->sum('capacity') }}</dd></div>
    <div><dt>Magang guru aktif</dt><dd>{{ $internships }}</dd></div>
</dl>

<div class="split">
    <div class="stack">
        <section class="card stack-sm">
            <h2 class="section-title">Pengingat ({{ $reminders->count() }})</h2>
            @forelse($reminders->take(12) as $r)
                <div class="flex justify-between gap-2 border-t border-border pt-2">
                    <span>{{ $r['text'] }}</span>
                    <span class="badge {{ $r['urgency'] === 'tinggi' ? 'badge-danger' : ($r['urgency'] === 'sedang' ? 'badge-warn' : '') }}">{{ $r['urgency'] }}</span>
                </div>
            @empty
                <p class="hint">Tidak ada dokumen yang akan habis, kunjungan terjadwal, atau kuota yang perlu dikonfirmasi.</p>
            @endforelse
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Distribusi siswa aktif per program keahlian</h2>
            @forelse($distribution as $major => $count)
                <div class="flex justify-between border-t border-border pt-2"><span>{{ $major }}</span><span class="tabular-nums">{{ $count }}</span></div>
            @empty
                <p class="hint">Belum ada siswa aktif PKL.</p>
            @endforelse
        </section>

        @if($duplicates->isNotEmpty())
            <section class="card card-attention stack-sm">
                <h2 class="section-title">Kemungkinan duplikat mitra</h2>
                @foreach($duplicates as $group)<p>{{ $group->pluck('name')->implode(' · ') }}</p>@endforeach
                <p class="hint">Periksa sebelum menambah mitra baru. Arsipkan entri yang dobel agar riwayat tetap utuh.</p>
            </section>
        @endif
    </div>

    <section class="stack-sm">
        <form method="GET" class="card-muted grid sm:grid-cols-3 gap-2 items-end">
            <div class="sm:col-span-2"><label class="label" for="q">Cari mitra</label><input id="q" name="q" class="field" value="{{ request('q') }}"></div>
            <div><label class="label" for="st">Status</label><select id="st" name="status" class="field"><option value="">Semua</option>@foreach(\App\Models\Company::PARTNERSHIP_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <button class="btn btn-navy btn-sm sm:col-span-3">Terapkan</button>
        </form>
        <p class="hint">Tambah mitra baru lewat menu <a class="link" href="{{ route('admin.companies') }}">Tempat PKL</a>.</p>
        @forelse($companies as $c)
            @php $q = $quotas->where('company_id', $c->id); @endphp
            <a href="{{ route('admin.partners.show', $c) }}" class="card flex flex-wrap justify-between gap-2">
                <span><strong>{{ $c->name }}</strong><span class="block hint">{{ $c->industry_type }} · {{ $c->city }}</span></span>
                <span class="flex flex-wrap gap-2 items-start">
                    <span class="badge {{ $c->partnership_status === 'active' ? 'badge-ok' : ($c->partnership_status === 'prospect' ? 'badge-warn' : '') }}">{{ $c->partnershipLabel() }}</span>
                    <span class="badge tabular-nums">{{ $filled[$c->id] ?? 0 }}/{{ $q->sum('capacity') ?: '?' }} kuota</span>
                    <span class="badge tabular-nums">{{ $c->active_placements_count }} aktif</span>
                </span>
            </a>
        @empty
            <div class="empty">Belum ada mitra yang cocok dengan filter.</div>
        @endforelse
    </section>
</div>
@endsection
