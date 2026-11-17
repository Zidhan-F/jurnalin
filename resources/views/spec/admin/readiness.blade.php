@extends('layouts.app', ['title' => 'Pembekalan pra-PKL - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Pembekalan pra-PKL</h1>
        <p>Modul singkat dan kuis sebelum siswa berangkat PKL. Hasil lulus dapat ditampilkan siswa di Paspor Kompetensi.</p>
    </div>
</div>
<div class="split">
    <form method="POST" action="{{ route('admin.readiness.store') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Tambah modul</h2>
        <div><label class="label" for="title">Judul</label><input id="title" name="title" class="field" required></div>
        <div><label class="label" for="summary">Ringkasan</label><input id="summary" name="summary" class="field"></div>
        <div><label class="label" for="content">Materi</label><textarea id="content" name="content" class="field" rows="6" required></textarea></div>
        <div><label class="label" for="video_url">Tautan video (opsional, berlisensi)</label><input id="video_url" name="video_url" type="url" class="field"></div>
        <div><label class="label" for="quiz_text">Kuis</label><textarea id="quiz_text" name="quiz_text" class="field" rows="4" placeholder="Pertanyaan | Opsi 1 | Opsi 2 | Opsi 3 | 2"></textarea>
            <p class="hint">Satu pertanyaan per baris. Angka terakhir = nomor opsi yang benar.</p></div>
        <div><label class="label" for="pass_score">Batas lulus</label><input id="pass_score" name="pass_score" type="number" min="0" max="100" value="70" class="field tabular-nums"></div>
        <button class="btn btn-navy">Simpan modul</button>
    </form>
    <section class="stack-sm">
        @foreach($modules as $m)
            <article class="card flex flex-wrap justify-between gap-2">
                <div>
                    <strong>{{ $m->title }}</strong> <span class="badge {{ $m->is_active ? 'badge-ok' : '' }}">{{ $m->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    <p class="hint">{{ count($m->quiz ?? []) }} soal · lulus {{ $m->results->where('passed', true)->count() }}/{{ $total }} siswa</p>
                </div>
                <form method="POST" action="{{ route('admin.readiness.toggle', $m) }}">@csrf<button class="btn btn-outline btn-sm">{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            </article>
        @endforeach
    </section>
</div>
@endsection
