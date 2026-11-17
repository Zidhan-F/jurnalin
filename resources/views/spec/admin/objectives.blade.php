@extends('layouts.app', ['title' => 'Tujuan Pembelajaran - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Tujuan Pembelajaran (TP)</h1>
        <p>Katalog TP PKL Kurikulum Merdeka bawaan yang bisa disesuaikan sekolah, ditambah target kompetensi magang guru. Dipakai untuk penautan jurnal dan nilai akhir berbasis TP.</p>
    </div>
</div>
<div class="split">
    <form method="POST" action="{{ route('admin.objectives.store') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Tambah TP</h2>
        <div><label class="label" for="scope">Untuk</label><select id="scope" name="scope" class="field"><option value="pkl">PKL siswa</option><option value="teacher">Magang guru</option></select></div>
        <div><label class="label" for="code">Kode</label><input id="code" name="code" class="field" required placeholder="TP6"></div>
        <div><label class="label" for="label">Rumusan TP</label><textarea id="label" name="label" class="field" required></textarea></div>
        <div><label class="label" for="major">Khusus program keahlian (opsional)</label><input id="major" name="major" class="field" list="program-keahlian-list"></div>
        <button class="btn btn-navy">Tambah</button>
    </form>
    <section class="stack-sm">
        @foreach($objectives->groupBy('scope') as $scope => $items)
            <h2 class="section-title">{{ $scope === 'pkl' ? 'PKL siswa' : 'Magang guru' }}</h2>
            @foreach($items as $o)
                <form method="POST" action="{{ route('admin.objectives.update', $o) }}" class="card grid sm:grid-cols-6 gap-2 items-center">
                    @csrf
                    <strong class="tabular-nums">{{ $o->code }}</strong>
                    <input name="label" class="field sm:col-span-3" value="{{ $o->label }}" aria-label="Rumusan {{ $o->code }}">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($o->is_active)> Aktif</label>
                    <button class="btn btn-outline btn-sm">Simpan</button>
                </form>
            @endforeach
        @endforeach
    </section>
</div>
@endsection
