@extends('layouts.app', ['title' => 'Sertifikat PKL - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Sertifikat PKL ber-QR</h1>
        <p>Terbitkan sertifikat setelah nilai akhir final dan tidak ada jurnal yang menunggu. QR menuju halaman verifikasi publik minimal. Bukan sertifikat kompetensi BNSP/LSP.</p>
    </div>
    <a href="{{ route('verify.form') }}" class="btn btn-outline btn-sm" target="_blank">Halaman verifikasi publik</a>
</div>

@unless($enabled)
    <p class="callout callout-warn mb-6">Sertifikat QR termasuk paket Sekolah Pro. <a class="link" href="{{ route('admin.modules') }}">Lihat modul</a>.</p>
@endunless

<section class="stack-sm mb-8">
    <h2 class="section-title">Siswa dengan nilai final ({{ $placements->count() }})</h2>
    @if($placements->isEmpty())
        <div class="empty">Belum ada nilai akhir yang difinalkan guru pembimbing.</div>
    @else
        <div class="overflow-x-auto" role="region" aria-label="Siswa siap sertifikat" tabindex="0">
            <table class="data-table">
                <thead><tr><th>Siswa</th><th>Tempat PKL</th><th>Periode</th><th>Nilai</th><th>Sertifikat</th></tr></thead>
                <tbody>
                    @foreach($placements as $p)
                        @php $valid = $p->certificates()->where('status', 'valid')->first(); @endphp
                        <tr>
                            <td>{{ $p->student->user->name }}</td>
                            <td>{{ $p->company->name }}</td>
                            <td>{{ $p->pklPeriod->name }}</td>
                            <td class="tabular-nums">{{ number_format($p->assessment->final_score, 2) }} ({{ $p->assessment->predicate }})</td>
                            <td>
                                @if($valid)
                                    <a class="link" href="{{ route('reports.certificate', $valid) }}">{{ $valid->number }}</a>
                                @else
                                    <form method="POST" action="{{ route('admin.certificates.issue', $p) }}">@csrf<button class="btn btn-primary btn-sm" @disabled(! $enabled)>Terbitkan</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="stack-sm">
    <h2 class="section-title">Riwayat sertifikat & surat keterangan</h2>
    @forelse($certificates as $c)
        <article class="card flex flex-wrap justify-between gap-3 {{ $c->status === 'revoked' ? 'card-danger' : '' }}">
            <div>
                <strong>{{ $c->number }}</strong> · {{ $c->holder_name }} <span class="badge {{ $c->status === 'valid' ? 'badge-ok' : 'badge-danger' }}">{{ $c->status === 'valid' ? 'Berlaku' : 'Dicabut' }}</span>
                <p class="hint">{{ $c->kind === 'pkl' ? 'Sertifikat PKL' : 'Surat hasil UKK' }} · kode <span class="tabular-nums">{{ $c->verification_code }}</span> · {{ $c->issued_at->format('d/m/Y') }}</p>
                @if($c->revoked_reason)<p class="hint">Alasan dicabut: {{ $c->revoked_reason }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2 items-start">
                <a class="btn btn-outline btn-sm" href="{{ route('reports.certificate', $c) }}">PDF</a>
                @if($c->status === 'valid')
                    <form method="POST" action="{{ route('admin.certificates.revoke', $c) }}" class="flex gap-2">@csrf<input name="reason" class="field" required minlength="10" placeholder="Alasan pencabutan" aria-label="Alasan pencabutan {{ $c->number }}"><button class="btn btn-danger btn-sm">Cabut</button></form>
                @endif
            </div>
        </article>
    @empty
        <div class="empty">Belum ada sertifikat yang diterbitkan.</div>
    @endforelse
</section>
@endsection
