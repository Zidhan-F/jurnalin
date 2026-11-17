@extends('layouts.app', ['title' => 'Uji Kompetensi Keahlian - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Uji Kompetensi Keahlian (UKK)</h1>
        <p>Atur sesi UKK per konsentrasi, pilih peserta dari data siswa, undang penguji eksternal tanpa akun, lalu rekap, finalkan, dan terbitkan surat keterangan ber-QR. Bukan sertifikat kompetensi BNSP/LSP.</p>
    </div>
</div>
<div class="split">
    <form method="POST" action="{{ route('admin.ukk.store') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Sesi baru</h2>
        <div><label class="label" for="un">Nama sesi</label><input id="un" name="name" class="field" required placeholder="UKK Perhotelan Sesi 1"></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label" for="uy">Tahun ajaran</label><input id="uy" name="academic_year" class="field" required value="{{ now()->year - (now()->month < 7 ? 1 : 0) }}/{{ now()->year + (now()->month < 7 ? 0 : 1) }}"></div>
            <div><label class="label" for="uq">Kuota peserta</label><input id="uq" name="quota" type="number" min="1" value="30" class="field tabular-nums"></div>
        </div>
        <div><label class="label" for="uc">Konsentrasi keahlian</label><input id="uc" name="concentration" class="field" required list="program-keahlian-list"></div>
        <div><label class="label" for="uv">Tempat uji</label><input id="uv" name="venue" class="field" required placeholder="Lab sekolah atau nama mitra"></div>
        <div><label class="label" for="uvc">Di industri mitra (opsional)</label><select id="uvc" name="venue_company_id" class="field"><option value="">Tidak</option>@foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div><label class="label" for="us">Waktu</label><input id="us" name="scheduled_at" type="datetime-local" class="field" required></div>
        <button class="btn btn-navy">Buat sesi</button>
    </form>
    <section class="stack-sm">
        @forelse($sessions as $s)
            <a href="{{ route('admin.ukk.show', $s) }}" class="card flex flex-wrap justify-between gap-2">
                <span><strong>{{ $s->name }}</strong><span class="block hint">{{ $s->concentration }} · {{ $s->venue }} · {{ $s->scheduled_at->format('d/m/Y H:i') }}</span></span>
                <span class="flex gap-2"><span class="badge tabular-nums">{{ $s->participants_count }}/{{ $s->quota }}</span><span class="badge {{ $s->status === 'final' ? 'badge-ok' : 'badge-warn' }}">{{ ['prepared' => 'Disiapkan', 'ongoing' => 'Berlangsung', 'final' => 'Final'][$s->status] }}</span></span>
            </a>
        @empty
            <div class="empty">Belum ada sesi UKK.</div>
        @endforelse
    </section>
</div>
@endsection
