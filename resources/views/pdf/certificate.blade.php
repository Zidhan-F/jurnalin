<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $certificate->number }}</title>
@include('pdf.partials.style')
<style>
    .frame { border: 3px double #1E293B; padding: 26px 36px; height: 480px; position: relative; }
    .title { font-size: 26px; font-weight: bold; text-align: center; letter-spacing: 2px; color: #1E293B; margin-top: 6px; }
    .name { font-size: 22px; font-weight: bold; text-align: center; margin: 10px 0 4px; }
    .qr { position: absolute; right: 36px; bottom: 30px; text-align: center; font-size: 8px; }
</style></head>
<body>
@php $p = $certificate->payload; $cfg = $school->letterhead_config ?? []; @endphp
<div class="frame">
    <p class="center muted">{{ $school->name }} · NPSN {{ $school->npsn }}</p>
    @if($certificate->kind === 'pkl')
        <div class="title">SERTIFIKAT PRAKTIK KERJA LAPANGAN</div>
        <p class="center">Nomor: {{ $certificate->number }}</p>
        <p class="center" style="margin-top:14px">Diberikan kepada</p>
        <div class="name">{{ $certificate->holder_name }}</div>
        <p class="center">Program keahlian {{ $p['major'] ?? '-' }}</p>
        <p class="center" style="margin-top:10px">telah melaksanakan Praktik Kerja Lapangan di <strong>{{ $p['company'] }}</strong>@if(! empty($p['division'])) ({{ $p['division'] }})@endif<br>
            pada {{ \Carbon\Carbon::parse($p['from'])->translatedFormat('d F Y') }} sampai {{ \Carbon\Carbon::parse($p['until'])->translatedFormat('d F Y') }} dengan predikat <strong>{{ $p['predicate'] }}</strong>.</p>
    @else
        <div class="title">SURAT KETERANGAN HASIL UJI KOMPETENSI KEAHLIAN</div>
        <p class="center">Nomor: {{ $certificate->number }}</p>
        <p class="center" style="margin-top:14px">Menerangkan bahwa</p>
        <div class="name">{{ $certificate->holder_name }}</div>
        <p class="center">telah mengikuti UKK konsentrasi {{ $p['concentration'] ?? '-' }} ({{ $p['session'] ?? '' }})<br>dengan nilai <strong>{{ $p['score'] ?? '-' }}</strong> dan predikat <strong>{{ $p['predicate'] ?? '-' }}</strong>.</p>
        <p class="center small muted">Surat keterangan ini diterbitkan sekolah dan bukan sertifikat kompetensi BNSP/LSP.</p>
    @endif
    <table class="sign-table" style="width:60%; margin-left:0"><tr><td>
        {{ $school->city }}, {{ $certificate->issued_at->translatedFormat('d F Y') }}<br>Kepala Sekolah,<br><br><br><br>
        <strong>{{ $cfg['principal_name'] ?? '________________________' }}</strong><br>NIP. {{ $cfg['principal_nip'] ?? '________________' }}
    </td></tr></table>
    <div class="qr"><img src="{{ $qr }}" width="110" height="110" alt=""><br>Kode verifikasi: <strong>{{ $certificate->verification_code }}</strong><br>{{ $url }}</div>
</div>
</body></html>
