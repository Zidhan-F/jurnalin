@extends('layouts.app', ['title' => 'Operasional Platform - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Operasional platform</h1>
        <p>Kesehatan layanan dan metrik agregat 7 hari terakhir. Halaman ini sengaja tidak menampilkan isi jurnal atau data pribadi siswa.</p>
    </div>
</div>

<dl class="stat-row mb-6">
    <div><dt>Database</dt><dd>{{ $dbOk ? 'OK' : 'Gangguan' }}</dd></div>
    <div><dt>Antrean menunggu</dt><dd>{{ $queue['pending'] }}</dd></div>
    <div><dt>Job gagal</dt><dd>{{ $queue['failed'] }}</dd></div>
    <div><dt>Penyimpanan berkas</dt><dd>{{ $storageMb }} MB</dd></div>
    <div><dt>OTP gagal (7 hari)</dt><dd>{{ $errors }}</dd></div>
</dl>

<div class="grid-auto">
    <section class="card stack-sm">
        <h2 class="section-title">Pesan WhatsApp</h2>
        @forelse($messages as $m)
            <div class="flex justify-between border-t border-border pt-2"><span>{{ $m->status }}</span><span class="tabular-nums">{{ $m->total }} · Rp{{ number_format((int) $m->cost, 0, ',', '.') }}</span></div>
        @empty
            <p class="hint">Belum ada pesan.</p>
        @endforelse
    </section>
    <section class="card stack-sm">
        <h2 class="section-title">Pemakaian AI</h2>
        @forelse($ai as $a)
            <div class="flex justify-between border-t border-border pt-2"><span>{{ $a->feature }}</span><span class="tabular-nums">{{ $a->total }} permintaan · {{ number_format((int) $a->chars) }} karakter</span></div>
        @empty
            <p class="hint">Belum ada permintaan AI.</p>
        @endforelse
    </section>
</div>

<section class="card stack-sm mt-6">
    <h2 class="section-title">Sekolah terbesar</h2>
    <div class="overflow-x-auto" role="region" aria-label="Sekolah terbesar" tabindex="0">
        <table class="data-table">
            <thead><tr><th>Sekolah</th><th>Status</th><th>Siswa</th><th>Penempatan</th></tr></thead>
            <tbody>@foreach($schools as $s)<tr><td>{{ $s->name }}</td><td>{{ $s->status->label() }}</td><td class="tabular-nums">{{ $s->students_count }}</td><td class="tabular-nums">{{ $s->placements_count }}</td></tr>@endforeach</tbody>
        </table>
    </div>
</section>
@endsection
