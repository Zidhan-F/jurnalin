@extends('layouts.app', ['title' => 'Penempatan Massal - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Pengelola penempatan & pembimbing</h1>
        <p>Tempatkan beberapa siswa sekaligus ke satu mitra, lalu bagi pembimbing sesuai beban. Sistem memperingatkan kuota dan beban guru; keputusan akhir tetap pada Anda. Perubahan pembimbing tidak mengubah keputusan review sebelumnya.</p>
    </div>
</div>

<div class="split">
    <form method="POST" action="{{ route('admin.hubin.bulk-place') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Penempatan massal</h2>
        <div><label class="label" for="bp-period">Periode</label><select id="bp-period" name="pkl_period_id" class="field" required>@foreach($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
        <div><label class="label" for="bp-company">Mitra</label>
            <select id="bp-company" name="company_id" class="field" required>@foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $filled[$c->id] ?? 0 }}/{{ $quotas[$c->id] ?? '?' }} kuota)</option>@endforeach</select></div>
        <div><label class="label" for="bp-teacher">Guru pembimbing</label>
            <select id="bp-teacher" name="teacher_id" class="field" required>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->user->name }} (beban {{ $t->load }}/{{ $t->max_mentees }})</option>@endforeach</select></div>
        <div class="grid grid-cols-3 gap-2">
            <div class="col-span-3"><label class="label" for="bp-div">Divisi</label><input id="bp-div" name="department_divisi" class="field"></div>
            <div><label class="label" for="bp-in">Jam masuk</label><input id="bp-in" name="work_start_time" type="time" value="08:00" class="field"></div>
            <div><label class="label" for="bp-out">Jam pulang</label><input id="bp-out" name="work_end_time" type="time" value="16:00" class="field"></div>
        </div>
        <fieldset><legend class="label">Siswa belum ditempatkan ({{ $students->count() }})</legend>
            <div class="grid gap-1 max-h-72 overflow-y-auto">
                @foreach($students as $s)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="student_ids[]" value="{{ $s->id }}"> {{ $s->user->name }} <span class="hint">· {{ $s->class_name }} · {{ $s->major }}</span></label>@endforeach
            </div>
        </fieldset>
        <button class="btn btn-primary">Tempatkan siswa terpilih</button>
    </form>

    <form method="POST" action="{{ route('admin.hubin.bulk-assign') }}" class="card stack-sm">
        @csrf
        <h2 class="section-title">Pemetaan pembimbing</h2>
        <div><label class="label" for="ba-teacher">Pindahkan ke guru</label>
            <select id="ba-teacher" name="teacher_id" class="field" required>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->user->name }} (beban {{ $t->load }}/{{ $t->max_mentees }})</option>@endforeach</select></div>
        <div class="overflow-x-auto max-h-96" role="region" aria-label="Penempatan aktif" tabindex="0">
            <table class="data-table">
                <thead><tr><th>Pilih</th><th>Siswa</th><th>Mitra</th><th>Guru saat ini</th></tr></thead>
                <tbody>@foreach($active as $p)<tr><td><input type="checkbox" name="placement_ids[]" value="{{ $p->id }}" aria-label="Pilih {{ $p->student->user->name }}"></td><td>{{ $p->student->user->name }}</td><td>{{ $p->company->name }}</td><td>{{ $p->teacher->user->name }}</td></tr>@endforeach</tbody>
            </table>
        </div>
        <button class="btn btn-navy">Simpan pemetaan</button>
    </form>
</div>
@endsection
