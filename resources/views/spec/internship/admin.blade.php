@extends('layouts.app', ['title' => 'Magang Guru Industri - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Magang Guru Industri</h1>
        <p>Program magang untuk guru dengan akun yang sama. Jurnal guru ditinjau guru lain, dievaluasi pembimbing industri lewat tautan khusus, dan berakhir dengan laporan serta Rencana Penerapan. Setiap magang tercatat di linimasa mitra.</p>
    </div>
    <a href="{{ route('mentor-access.index') }}" class="btn btn-outline btn-sm">Tautan evaluasi pembimbing</a>
</div>
<div class="split">
    <form method="POST" action="{{ route('admin.internships.store') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Daftarkan magang guru</h2>
        <div><label class="label" for="it">Guru peserta</label><select id="it" name="teacher_id" class="field" required>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->user->name }}</option>@endforeach</select></div>
        <div><label class="label" for="ir">Peninjau jurnal (guru lain)</label><select id="ir" name="reviewer_teacher_id" class="field" required>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->user->name }}</option>@endforeach</select></div>
        <div><label class="label" for="ic">Instansi mitra</label><select id="ic" name="company_id" class="field" required>@foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div><label class="label" for="ip">Pembimbing industri</label><select id="ip" name="partner_contact_id" class="field"><option value="">Belum ditentukan</option>@foreach($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->company?->name }}</option>@endforeach</select></div>
        <div><label class="label" for="itl">Judul program</label><input id="itl" name="title" class="field" required></div>
        <div><label class="label" for="io">Tujuan & target kompetensi</label><textarea id="io" name="objectives" class="field" required></textarea></div>
        <div><label class="label" for="idv">Unit kerja</label><input id="idv" name="division" class="field"></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label" for="is">Mulai</label><input id="is" name="start_date" type="date" class="field" required></div>
            <div><label class="label" for="ie">Selesai</label><input id="ie" name="end_date" type="date" class="field" required></div>
        </div>
        <button class="btn btn-navy">Simpan program</button>
    </form>
    <section class="stack-sm">
        @forelse($internships as $i)
            @php $approved = $i->journals->where('status', 'approved')->count(); @endphp
            <article class="card stack-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <div><strong>{{ $i->teacher->user->name }}</strong> · {{ $i->title }}<p class="hint">{{ $i->company->name }} · {{ $i->start_date->format('d/m/Y') }} s.d. {{ $i->end_date->format('d/m/Y') }} · peninjau {{ $i->reviewer?->user->name ?? 'admin' }}</p></div>
                    <form method="POST" action="{{ route('admin.internships.status', $i) }}" class="flex gap-2">@csrf<select name="status" class="field" aria-label="Status magang">@foreach(['planned' => 'Rencana', 'active' => 'Berjalan', 'completed' => 'Selesai'] as $k => $l)<option value="{{ $k }}" @selected($i->status === $k)>{{ $l }}</option>@endforeach</select><button class="btn btn-outline btn-sm">Simpan</button></form>
                </div>
                <ul class="check-list">
                    <li class="{{ $approved > 0 ? 'is-done' : '' }}"><span class="check-mark">1</span><span>Jurnal disetujui</span><span class="hint">{{ $approved }}/{{ $i->journals->count() }}</span></li>
                    <li class="{{ $i->evaluations->where('status', 'final')->isNotEmpty() ? 'is-done' : '' }}"><span class="check-mark">2</span><span>Evaluasi pembimbing industri</span><span class="hint">{{ $i->evaluations->where('status', 'final')->isNotEmpty() ? 'Disahkan' : 'Belum' }}</span></li>
                    <li class="{{ $i->report_summary ? 'is-done' : '' }}"><span class="check-mark">3</span><span>Laporan magang</span><span class="hint">{{ $i->report_summary ? 'Ada' : 'Belum' }}</span></li>
                    <li class="{{ $i->transferPlans->isNotEmpty() ? 'is-done' : '' }}"><span class="check-mark">4</span><span>Rencana Penerapan</span><span class="hint">{{ $i->transferPlans->where('status', 'implemented')->count() }}/{{ $i->transferPlans->count() }} terlaksana</span></li>
                </ul>
                <a class="link text-sm" href="{{ route('reports.internship', $i) }}">Unduh laporan PDF</a>
            </article>
        @empty
            <div class="empty">Belum ada program magang guru.</div>
        @endforelse
    </section>
</div>
@endsection
