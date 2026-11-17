@extends('layouts.app', ['title' => 'Kunjungan Monitoring - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Kunjungan monitoring & kemitraan</h1>
        <p>Catat agenda dan hasil kunjungan ke tempat PKL: kondisi siswa, diskusi dengan pembimbing industri, kendala, dan tindak lanjut. Laporan kunjungan bukan bukti kehadiran seluruh siswa.</p>
    </div>
</div>

<div class="split">
    <form method="POST" action="{{ route('visits.store') }}" enctype="multipart/form-data" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Catat kunjungan</h2>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="kind">Jenis</label>
                <select id="kind" name="kind" class="field">
                    <option value="monitoring">Monitoring siswa</option>
                    @if($isAdmin)<option value="partnership">Kemitraan (tanpa siswa)</option>@endif
                </select>
            </div>
            <div>
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="field"><option value="done">Sudah dilakukan</option><option value="planned">Agenda</option></select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="company_id">Tempat PKL</label>
                <select id="company_id" name="company_id" class="field" required>
                    @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="visit_date">Tanggal</label>
                <input id="visit_date" name="visit_date" type="date" class="field tabular-nums" required value="{{ now()->toDateString() }}">
            </div>
        </div>
        @if($placements->isNotEmpty())
            <fieldset>
                <legend class="label">Siswa yang ditemui (sesuai tempat PKL yang dipilih)</legend>
                <div class="grid gap-1 max-h-48 overflow-y-auto">
                    @foreach($placements as $p)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="placement_ids[]" value="{{ $p->id }}"> {{ $p->student->user->name }} <span class="hint">· {{ $p->company->name }}</span></label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <div><label class="label" for="student_condition">Kondisi siswa</label><textarea id="student_condition" name="student_condition" class="field" rows="2"></textarea></div>
        <div><label class="label" for="mentor_discussion">Diskusi dengan pembimbing industri</label><textarea id="mentor_discussion" name="mentor_discussion" class="field" rows="2"></textarea></div>
        <div><label class="label" for="issues">Kendala</label><textarea id="issues" name="issues" class="field" rows="2"></textarea></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="follow_up">Tindak lanjut</label><input id="follow_up" name="follow_up" class="field"></div>
            <div><label class="label" for="follow_up_owner">Penanggung jawab</label><input id="follow_up_owner" name="follow_up_owner" class="field"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="follow_up_due">Tenggat tindak lanjut</label><input id="follow_up_due" name="follow_up_due" type="date" class="field tabular-nums"></div>
            <div><label class="label" for="photo">Foto kunjungan</label><input id="photo" name="photo" type="file" accept="image/*" class="block w-full text-sm"></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confidential" value="1"> Rahasia (hanya penulis dan admin sekolah)</label>
        <button class="btn btn-primary btn-block">Simpan kunjungan</button>
    </form>

    <section class="stack-sm">
        <h2 class="section-title">Riwayat kunjungan ({{ $visits->count() }})</h2>
        @forelse($visits as $v)
            <article class="card {{ $v->status === 'planned' ? 'card-attention' : '' }}">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <h3 class="font-bold">{{ $v->company?->name ?? 'Tanpa tempat PKL' }}</h3>
                        <p class="hint"><span class="tabular-nums">{{ $v->visit_date->format('d/m/Y') }}</span> · {{ $v->kind === 'monitoring' ? 'Monitoring' : 'Kemitraan' }} · {{ $v->author?->name }}</p>
                    </div>
                    <div class="flex gap-2 items-start">
                        @if($v->confidential)<span class="badge badge-danger">Rahasia</span>@endif
                        <span class="badge {{ $v->status === 'planned' ? 'badge-warn' : 'badge-ok' }}">{{ $v->status === 'planned' ? 'Agenda' : 'Selesai' }}</span>
                    </div>
                </div>
                @if($v->placement_ids)<p class="hint mt-1">Siswa: {{ collect($v->placement_ids)->map(fn ($id) => $studentNames[$id] ?? null)->filter()->implode(', ') }}</p>@endif
                <dl class="kv mt-2">
                    @foreach(['student_condition' => 'Kondisi', 'mentor_discussion' => 'Diskusi', 'issues' => 'Kendala', 'follow_up' => 'Tindak lanjut'] as $f => $l)
                        @if($v->$f)<dt>{{ $l }}</dt><dd class="font-normal">{{ $v->$f }}</dd>@endif
                    @endforeach
                    @if($v->follow_up_due)<dt>Tenggat</dt><dd class="tabular-nums">{{ $v->follow_up_due->format('d/m/Y') }} ({{ $v->follow_up_owner ?: '-' }})</dd>@endif
                </dl>
                <a class="link text-sm mt-2 inline-flex" href="{{ route('reports.visit', $v) }}">Unduh laporan kunjungan PDF</a>
            </article>
        @empty
            <div class="empty">Belum ada kunjungan tercatat. Catat agenda atau hasil kunjungan pertama dengan formulir di samping.</div>
        @endforelse
    </section>
</div>
@endsection
