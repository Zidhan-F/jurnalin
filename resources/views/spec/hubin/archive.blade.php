@extends('layouts.app', ['title' => 'Realisasi Kemitraan - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Realisasi kemitraan & arsip tahunan</h1>
        <p>Rekap kegiatan yang tercatat per tahun ajaran: PKL siswa, magang guru, kunjungan, dokumen aktif, dan UKK di tempat mitra. Hanya kejadian tercatat; bukan pelaporan pemerintah otomatis.</p>
    </div>
    <div class="flex flex-wrap gap-2 items-end">
        <form method="GET" class="flex gap-2 items-end"><div><label class="label" for="tahun">Tahun ajaran</label><select id="tahun" name="tahun" class="field">@foreach($years as $y)<option @selected($y === $year)>{{ $y }}</option>@endforeach</select></div><button class="btn btn-outline btn-sm">Tampilkan</button></form>
        <a class="btn btn-navy btn-sm" href="{{ route('admin.hubin.archive-csv', ['tahun' => $year]) }}">Unduh CSV</a>
    </div>
</div>
@if($rows->isEmpty())
    <div class="empty">Belum ada kegiatan kemitraan tercatat pada tahun ajaran {{ $year }}.</div>
@else
    <div class="overflow-x-auto" role="region" aria-label="Realisasi kemitraan" tabindex="0">
        <table class="data-table">
            <thead><tr><th>Mitra</th><th>Status</th><th>Siswa PKL</th><th>Magang guru</th><th>Kunjungan</th><th>Dokumen aktif</th><th>UKK</th></tr></thead>
            <tbody>@foreach($rows as $r)<tr><td><a class="link" href="{{ route('admin.partners.show', $r['company']) }}">{{ $r['company']->name }}</a></td><td>{{ $r['company']->partnershipLabel() }}</td><td class="tabular-nums">{{ $r['placements'] }}</td><td class="tabular-nums">{{ $r['internships'] }}</td><td class="tabular-nums">{{ $r['visits'] }}</td><td class="tabular-nums">{{ $r['agreements'] }}</td><td class="tabular-nums">{{ $r['ukk'] }}</td></tr>@endforeach</tbody>
        </table>
    </div>
@endif
@endsection
