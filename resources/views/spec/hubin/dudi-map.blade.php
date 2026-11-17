@extends('layouts.app', ['title' => 'Peta DUDI - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Peta DUDI lintas sekolah</h1>
        <p>Agregat anonim dari sekolah yang ikut serta: bidang, kota, jumlah sekolah dan peserta yang pernah diterima. Tidak ada kontak PIC, dokumen MoU, nama siswa, atau data privat sekolah. Mitra yang baru dipakai kurang dari {{ \App\Http\Controllers\Spec\HubinController::DUDI_MIN_SCHOOLS }} sekolah disembunyikan; ringkasan ulasan baru tampil bila ada minimal {{ \App\Http\Controllers\Spec\HubinController::DUDI_MIN_REVIEWS }} respons.</p>
    </div>
    <form method="POST" action="{{ route('admin.dudi-map.toggle') }}">@csrf<button class="btn {{ $school->peta_dudi_opt_in ? 'btn-danger' : 'btn-primary' }} btn-sm">{{ $school->peta_dudi_opt_in ? 'Keluar dari Peta DUDI' : 'Ikut serta (opt-in)' }}</button></form>
</div>

@unless($school->peta_dudi_opt_in)
    <div class="empty">Sekolah belum ikut serta. Tanpa opt-in, tidak ada data sekolah ini yang dibagikan dan peta tidak ditampilkan.</div>
@else
    <form method="GET" class="card-muted flex gap-2 items-end mb-4"><div class="flex-1"><label class="label" for="q">Cari bidang, kota, atau nama</label><input id="q" name="q" class="field" value="{{ request('q') }}"></div><button class="btn btn-navy btn-sm">Cari</button></form>
    @if($rows->isEmpty())
        <div class="empty">Belum ada mitra yang memenuhi batas agregat minimum.</div>
    @else
        <div class="overflow-x-auto" role="region" aria-label="Peta DUDI" tabindex="0">
            <table class="data-table">
                <thead><tr><th>Mitra</th><th>Bidang</th><th>Kota</th><th>Sekolah</th><th>Peserta diterima</th><th>Ulasan siswa</th></tr></thead>
                <tbody>@foreach($rows as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->sector }}</td><td>{{ $r->city }}</td><td class="tabular-nums">{{ $r->schools }}</td><td class="tabular-nums">{{ $r->students }}</td><td>{{ $r->avg !== null ? $r->avg.'/5 dari '.$r->reviews.' respons' : 'Belum cukup respons' }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    @endif
@endunless
@endsection
