@extends('layouts.student-pwa', ['title' => 'Laporan Akhir PKL - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Draf laporan akhir</h1>
        <p>Disusun dari jurnal yang sudah disetujui, Bukti Kerja, dan TP terverifikasi. Bagian bertanda [Lengkapi] wajib Anda tulis sendiri. Sesuaikan dengan format sekolah sebelum dikumpulkan.</p>
    </div>
</div>
@if(! $enabled)
    <p class="callout callout-warn">Laporan Akhir Otomatis termasuk add-on AI dan belum aktif di sekolah Anda.</p>
@elseif(! $placement)
    <div class="empty">Belum ada penempatan PKL.</div>
@else
    <p class="callout">{{ $report['sources'] }} sumber tercatat · {{ $report['placeholders'] }} bagian perlu dilengkapi.</p>
    @foreach($report['chapters'] as $ch)
        <section class="card stack-sm">
            <h2 class="section-title">{{ $ch['title'] }}</h2>
            @foreach($ch['paragraphs'] as $p)
                <p class="{{ str_contains($p, '[Lengkapi:') ? 'callout callout-warn' : '' }}">{{ $p }}</p>
            @endforeach
        </section>
    @endforeach
    <a href="{{ route('reports.final-report', $placement) }}" class="btn btn-primary btn-block">Unduh draf PDF</a>
@endif
@endsection
