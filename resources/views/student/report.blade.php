@extends('layouts.student-pwa', ['title' => 'Laporan PKL - Jurnalin'])

@section('content')
<div>
    <h1 class="text-xl font-bold text-ink">Laporan PKL</h1>
    <p class="hint">Progres, nilai, unduhan, dan bahan belajar selama PKL.</p>
</div>

@if($placement)
    <section class="card stack-sm" aria-labelledby="judul-progres-laporan">
        <h2 id="judul-progres-laporan" class="section-title">Progres PKL</h2>
        <p class="hint">{{ $placement->company->name }} · {{ $placement->pklPeriod->name }}</p>
        <div class="flex items-center gap-4">
            <div class="progress-ring" style="--value: {{ $weeks['percent'] }}" role="img" aria-label="Progres {{ $weeks['percent'] }} persen"><span>{{ $weeks['percent'] }}%</span></div>
            <dl class="grid gap-1 text-sm">
                <div><dt class="inline text-muted">Minggu:</dt> <dd class="inline font-semibold tabular-nums">{{ $weeks['current'] }} dari {{ $weeks['total'] }}</dd></div>
                <div><dt class="inline text-muted">Hari hadir:</dt> <dd class="inline font-semibold tabular-nums">{{ $attendedCount }} dari {{ $placement->pklPeriod->min_required_days }}</dd></div>
                <div><dt class="inline text-muted">Jurnal disetujui:</dt> <dd class="inline font-semibold tabular-nums">{{ $approvedJournals }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card stack-sm" aria-labelledby="judul-nilai">
        <h2 id="judul-nilai" class="section-title">Nilai akhir</h2>
        @if($placement->assessment?->finalized_at)
            <p class="text-2xl font-bold text-emerald-strong tabular-nums">{{ number_format($placement->assessment->final_score, 2) }} <span class="text-base">({{ $placement->assessment->predicate }})</span></p>
        @else
            <p class="hint">Nilai muncul di sini setelah guru pembimbing menyelesaikan penilaian di akhir periode.</p>
        @endif
        <a href="/reports/placements/{{ $placement->id }}/logbook.pdf" class="btn btn-outline btn-block">Unduh buku jurnal (PDF)</a>
        <a href="{{ route('student.profile') }}" class="link text-sm">Lihat profil, sertifikat, dan rincian nilai</a>
    </section>
@else
    <p class="callout">Belum ada penempatan PKL. Laporan dan nilai muncul setelah penempatan dibuat oleh koordinator PKL.</p>
@endif

<section aria-labelledby="judul-belajar" class="stack-sm">
    <h2 id="judul-belajar" class="section-title">Belajar dan persiapan</h2>
    <nav class="menu-grid" aria-label="Belajar dan persiapan">
        <a href="{{ route('student.learning') }}">Target belajar <small>Apa yang sudah dan belum dikuasai</small></a>
        <a href="{{ route('student.calendar') }}">Kalender PKL <small>Hari tercatat, izin, libur</small></a>
        <a href="{{ route('student.final-report') }}">Laporan akhir <small>Disusun dari jurnal Anda</small></a>
        <a href="{{ route('student.journey') }}">Kilas balik <small>Perjalanan PKL Anda</small></a>
        <a href="{{ route('student.passport') }}">Paspor kompetensi <small>Bukti keahlian terverifikasi</small></a>
        <a href="{{ route('student.readiness') }}">Pembekalan <small>Materi sebelum berangkat</small></a>
        <a href="{{ route('student.interview') }}">Latihan wawancara <small>Bercerita tentang PKL Anda</small></a>
        <a href="{{ route('student.tickets') }}">Lapor ke guru <small>Bantuan dan jalur darurat</small></a>
    </nav>
</section>
@endsection
