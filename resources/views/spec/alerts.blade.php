@extends('layouts.app', ['title' => 'Pemantauan Pintar - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Pemantauan pintar</h1>
        <p>Siswa yang mungkin perlu ditindaklanjuti: presensi kosong berturut-turut, presensi bertanda, review menumpuk, permintaan bantuan, atau target lewat tenggat. Izin dan libur sudah dikecualikan. Ini sinyal untuk dicek, bukan label untuk siswa.</p>
    </div>
</div>
@forelse($alerts as $a)
    <article class="card mb-3 {{ $a['urgency'] === 'tinggi' ? 'card-danger' : ($a['urgency'] === 'sedang' ? 'card-attention' : '') }} flex flex-wrap justify-between gap-3">
        <div>
            <h2 class="font-bold">{{ $a['placement']->student->user->name }} <span class="hint font-normal">· {{ $a['placement']->company->name }}</span></h2>
            <p class="mt-1">{{ $a['message'] }}</p>
        </div>
        <div class="flex items-start gap-2">
            <span class="badge {{ $a['urgency'] === 'tinggi' ? 'badge-danger' : ($a['urgency'] === 'sedang' ? 'badge-warn' : '') }}">Urgensi {{ $a['urgency'] }}</span>
            <a class="btn btn-outline btn-sm" href="{{ route('monitor.participant', $a['placement']) }}">Tindak lanjut</a>
        </div>
    </article>
@empty
    <div class="empty">Tidak ada peringatan. Semua siswa aktif tercatat sesuai jadwal dan tidak ada antrean yang menumpuk.</div>
@endforelse
@endsection
