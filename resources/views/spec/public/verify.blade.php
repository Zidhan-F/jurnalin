@extends('spec.public.layout')
@section('title', 'Verifikasi dokumen')
@section('context', 'Verifikasi sertifikat & surat keterangan')
@section('footer', 'Halaman ini hanya menampilkan data minimum untuk memastikan keaslian dokumen.')

@section('content')
<div class="mentor-intro">
    <h1>Verifikasi dokumen Jurnalin</h1>
    <p class="hint">Masukkan kode verifikasi yang tercetak di bawah kode QR.</p>
</div>

<form method="GET" action="{{ route('verify.form') }}" class="mentor-card flex flex-wrap gap-2 items-end">
    <div class="flex-1 min-w-48"><label class="label" for="kode">Kode verifikasi</label><input id="kode" name="kode" class="field tabular-nums uppercase" required value="{{ $certificate?->verification_code }}" autocomplete="off"></div>
    <button class="btn btn-navy">Periksa</button>
</form>

@if($searched && $certificate === null)
    <div class="mentor-error" role="alert">Kode tidak ditemukan. Periksa kembali kode pada dokumen, atau hubungi sekolah penerbit.</div>
@elseif($certificate)
    @php $p = $certificate->payload; @endphp
    <section class="verify-seal {{ $certificate->status === 'revoked' ? 'is-revoked' : '' }}" role="status">
        <x-icon :name="$certificate->status === 'revoked' ? 'alert' : 'shield'" class="w-10 h-10" />
        <div>
            <p class="font-bold text-lg">{{ $certificate->status === 'revoked' ? 'Dokumen dicabut' : 'Dokumen asli dan berlaku' }}</p>
            <p>{{ $certificate->kind === 'ukk' ? 'Surat keterangan hasil Uji Kompetensi Keahlian' : 'Sertifikat Praktik Kerja Lapangan' }} · No. {{ $certificate->number }}</p>
        </div>
    </section>
    <section class="mentor-card">
        <dl class="kv">
            <dt>Nama</dt><dd>{{ $certificate->holder_name }}</dd>
            <dt>Penerbit</dt><dd>{{ $p['school'] ?? '-' }}</dd>
            @if($certificate->kind === 'pkl')
                <dt>Tempat PKL</dt><dd>{{ $p['company'] ?? '-' }}</dd>
                <dt>Periode</dt><dd class="tabular-nums">{{ \Carbon\Carbon::parse($p['from'])->format('d/m/Y') }} s.d. {{ \Carbon\Carbon::parse($p['until'])->format('d/m/Y') }}</dd>
            @else
                <dt>Konsentrasi</dt><dd>{{ $p['concentration'] ?? '-' }}</dd>
                <dt>Hasil</dt><dd>{{ $p['predicate'] ?? '-' }}</dd>
            @endif
            <dt>Diterbitkan</dt><dd class="tabular-nums">{{ $certificate->issued_at->format('d/m/Y') }}</dd>
            @if($certificate->status === 'revoked')<dt>Alasan dicabut</dt><dd>{{ $certificate->revoked_reason }}</dd>@endif
        </dl>
        <p class="hint mt-3">Dokumen ini diterbitkan sekolah melalui Jurnalin. Bukan sertifikat kompetensi BNSP/LSP.</p>
    </section>
@endif
@endsection
