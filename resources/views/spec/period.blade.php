@extends('layouts.app', ['title' => $period->name.' | Jurnalin'])
@section('content')
@php
    $tabs = ['kelas' => 'Kelas ('.$classes->count().')', 'grup' => 'Grup penempatan ('.$placementGroups->count().')', 'penempatan' => 'Semua penempatan ('.$placements->total().')'];
    $fmt = fn ($t) => substr((string) $t, 0, 5);
@endphp
<div class="stack">
    <header class="stack-sm">
        <a class="link text-sm" href="{{ route('admin.periods') }}">Kembali ke daftar periode</a>
        <h1 class="text-2xl font-bold text-ink">{{ $period->name }}</h1>
        <p class="hint">{{ $period->is_active ? 'Aktif' : 'Arsip' }} · TA {{ $period->academic_year }} · {{ $period->start_date->format('d/m/Y') }} sampai {{ $period->end_date->format('d/m/Y') }} · Target {{ $period->min_required_days }} hari · <a class="link" href="{{ route('admin.payments', ['period' => $period->id]) }}">Pembayaran</a></p>
    </header>

    <ol class="grid sm:grid-cols-3 gap-2 text-sm">
        <li class="card-muted"><strong>1. Kelas</strong><br><span class="hint">Masukkan kelas yang ikut PKL. Siswanya ikut otomatis.</span></li>
        <li class="card-muted"><strong>2. Grup penempatan</strong><br><span class="hint">Kelompokkan siswa per tempat PKL dan guru pembimbing.</span></li>
        <li class="card-muted"><strong>3. Berjalan</strong><br><span class="hint">Siswa langsung bisa presensi dan menulis jurnal.</span></li>
    </ol>

    <nav class="flex flex-wrap gap-2" aria-label="Bagian periode">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.periods.show', ['period' => $period, 'tab' => $key]) }}" class="btn btn-sm {{ $tab === $key ? 'btn-navy' : 'btn-outline' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if($tab === 'kelas')
        <section class="split">
            <form method="POST" action="{{ route('admin.periods.classes.store', $period) }}" class="card stack-sm self-start">
                @csrf
                <h2 class="section-title">Tambah kelas ke periode</h2>
                <label class="label" for="class-name">Nama kelas</label>
                <input id="class-name" name="name" list="class-options" required maxlength="64" class="field" placeholder="Contoh: XII Perhotelan 1" value="{{ old('name') }}">
                <datalist id="class-options">@foreach($classOptions as $c)<option value="{{ $c }}">@endforeach</datalist>
                <p class="hint">Semua siswa yang kelasnya tercatat sama akan ikut masuk. Kelas tersedia: {{ $classOptions->isEmpty() ? 'semua sudah ditambahkan' : $classOptions->implode(', ') }}.</p>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn btn-primary btn-sm">Tambah kelas</button>
                @if($unclassed->isNotEmpty() && $classes->isNotEmpty())
                    <p class="callout callout-warn text-sm">{{ $unclassed->count() }} siswa belum masuk kelas mana pun di periode ini.</p>
                @endif
            </form>

            <div class="stack-sm">
                @forelse($classes as $class)
                    <details class="card" @if($loop->first) open @endif>
                        <summary class="flex flex-wrap justify-between gap-2 min-h-11 items-center">
                            <span><strong>{{ $class->name }}</strong> <span class="hint">{{ $class->major }}</span></span>
                            <span class="badge">{{ $class->members->count() }} siswa</span>
                        </summary>
                        <ul class="divide-y divide-rule mt-2">
                            @foreach($class->members->sortBy('user.name') as $s)
                                <li class="flex justify-between items-center gap-2 py-2 text-sm">
                                    <span>{{ $s->user->name }} <span class="hint">· NIS {{ $s->nis ?? '-' }}</span></span>
                                    <form method="POST" action="{{ route('admin.periods.classes.remove-member', [$period, $class, $s]) }}">@csrf<button class="link text-sm" aria-label="Keluarkan {{ $s->user->name }} dari {{ $class->name }}">Keluarkan</button></form>
                                </li>
                            @endforeach
                        </ul>
                        @if($unclassed->isNotEmpty())
                            <form method="POST" action="{{ route('admin.periods.classes.members', [$period, $class]) }}" class="flex flex-wrap gap-2 mt-3 items-end">
                                @csrf
                                <div class="grow"><label class="label" for="add-{{ $class->id }}">Tambah siswa pindahan / lainnya</label>
                                    <select id="add-{{ $class->id }}" name="student_ids[]" class="field" required>
                                        <option value="">Pilih siswa</option>
                                        @foreach($unclassed as $s)<option value="{{ $s->id }}">{{ $s->user->name }} · {{ $s->class_name }}</option>@endforeach
                                    </select></div>
                                <button class="btn btn-outline btn-sm">Tambahkan</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.periods.classes.destroy', [$period, $class]) }}" class="mt-3" onsubmit="return confirm('Hapus kelas {{ $class->name }} dari periode ini? Data siswa dan penempatan tidak berubah.')">@csrf<button class="link text-sm text-danger">Hapus kelas dari periode</button></form>
                    </details>
                @empty
                    <div class="empty">Belum ada kelas di periode ini. Tambahkan kelas di samping, misalnya kelas XII yang berangkat PKL.</div>
                @endforelse
            </div>
        </section>
    @endif

    @if($tab === 'grup')
        <section class="split">
            <form method="POST" action="{{ route('admin.periods.groups.store', $period) }}" class="card stack-sm self-start" x-data="{ cls: '' }">
                @csrf
                <h2 class="section-title">Buat grup penempatan</h2>
                <div><label class="label" for="g-name">Nama grup</label><input id="g-name" name="name" required maxlength="100" class="field" placeholder="Contoh: Hotel Savoy - Banquet" value="{{ old('name') }}"></div>
                <div><label class="label" for="g-company">Tempat PKL</label>
                    <select id="g-company" name="company_id" required class="field"><option value="">Pilih tempat PKL</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="g-div">Divisi / departemen (opsional)</label><input id="g-div" name="department_divisi" maxlength="100" class="field" placeholder="Contoh: F&B Banquet, Housekeeping" value="{{ old('department_divisi') }}"></div>
                <div><label class="label" for="g-teacher">Guru pembimbing</label>
                    <select id="g-teacher" name="teacher_id" required class="field"><option value="">Pilih guru</option>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id') === $t->id)>{{ $t->user->name }} · {{ $t->active_load }}/{{ $t->max_mentees }} siswa</option>@endforeach</select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="g-start">Jam masuk</label><input id="g-start" name="work_start_time" type="time" required class="field" value="{{ old('work_start_time', '08:00') }}"></div>
                    <div><label class="label" for="g-end">Jam pulang</label><input id="g-end" name="work_end_time" type="time" required class="field" value="{{ old('work_end_time', '16:00') }}"></div>
                </div>
                <fieldset>
                    <legend class="label">Anggota (siswa yang belum punya grup)</legend>
                    @if($poolClasses->count() > 1)
                        <select x-model="cls" class="field mb-2" aria-label="Saring per kelas"><option value="">Semua kelas</option>@foreach($poolClasses as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                    @endif
                    <div class="grid gap-1 max-h-64 overflow-y-auto">
                        @forelse($ungrouped as $p)
                            <label class="flex items-start gap-2 text-sm" x-show="cls === '' || cls === @js($p['class'])"><input type="checkbox" name="student_ids[]" value="{{ $p['id'] }}" class="mt-1"> <span>{{ $p['name'] }} <span class="hint">· {{ $p['class'] }}@if($p['placement']) · sudah di {{ $p['placement']->company->name }}@endif</span></span></label>
                        @empty
                            <p class="hint">Semua siswa sudah punya grup.</p>
                        @endforelse
                    </div>
                </fieldset>
                <p class="hint">Anggota langsung mendapat penempatan aktif dengan tempat, guru, dan jam kerja grup ini. Anggota bisa ditambah nanti.</p>
                <button class="btn btn-primary btn-sm">Buat grup</button>
            </form>

            <div class="stack-sm">
                @if($ungrouped->isNotEmpty())
                    <p class="callout callout-warn text-sm"><strong>{{ $ungrouped->count() }} siswa belum punya grup:</strong> {{ $ungrouped->take(8)->pluck('name')->implode(', ') }}{{ $ungrouped->count() > 8 ? ', dan lainnya' : '' }}.</p>
                @endif
                @forelse($placementGroups as $g)
                    <details class="card" @if($loop->first) open @endif>
                        <summary class="flex flex-wrap justify-between gap-2 min-h-11 items-center">
                            <span><strong>{{ $g->name }}</strong><br><span class="hint">{{ $g->company->name }}{{ $g->department_divisi ? ' · '.$g->department_divisi : '' }} · {{ $g->teacher->user->name }} · {{ $fmt($g->work_start_time) }}-{{ $fmt($g->work_end_time) }}</span></span>
                            <span class="badge badge-navy">{{ $g->placements->count() }} siswa</span>
                        </summary>
                        <ul class="divide-y divide-rule mt-2">
                            @forelse($g->placements->sortBy('student.user.name') as $p)
                                <li class="flex justify-between items-center gap-2 py-2 text-sm">
                                    <a class="link" href="{{ route('monitor.participant', $p) }}">{{ $p->student->user->name }}</a>
                                    <span class="flex items-center gap-3"><span class="hint">{{ $pool->firstWhere('id', $p->student_id)['class'] ?? $p->student->class_name }}</span>
                                    <form method="POST" action="{{ route('admin.periods.groups.remove-member', [$period, $g, $p]) }}">@csrf<button class="link text-sm" aria-label="Keluarkan {{ $p->student->user->name }} dari grup">Keluarkan</button></form></span>
                                </li>
                            @empty
                                <li class="hint py-2">Belum ada anggota.</li>
                            @endforelse
                        </ul>

                        @if($ungrouped->isNotEmpty())
                            <form method="POST" action="{{ route('admin.periods.groups.members', [$period, $g]) }}" class="stack-sm mt-3" x-data="{ cls: '' }">
                                @csrf
                                <p class="label">Tambah anggota</p>
                                @if($poolClasses->count() > 1)
                                    <select x-model="cls" class="field" aria-label="Saring per kelas"><option value="">Semua kelas</option>@foreach($poolClasses as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                                @endif
                                <div class="grid gap-1 max-h-48 overflow-y-auto">
                                    @foreach($ungrouped as $p)
                                        <label class="flex items-start gap-2 text-sm" x-show="cls === '' || cls === @js($p['class'])"><input type="checkbox" name="student_ids[]" value="{{ $p['id'] }}" class="mt-1"> <span>{{ $p['name'] }} <span class="hint">· {{ $p['class'] }}@if($p['placement']) · sudah di {{ $p['placement']->company->name }}@endif</span></span></label>
                                    @endforeach
                                </div>
                                <button class="btn btn-outline btn-sm self-start">Tambahkan ke grup</button>
                            </form>
                        @endif

                        <details class="mt-3">
                            <summary class="link text-sm min-h-11 flex items-center">Ubah guru, divisi, atau jam kerja grup</summary>
                            <form method="POST" action="{{ route('admin.periods.groups.update', [$period, $g]) }}" class="grid sm:grid-cols-2 gap-2 mt-2">
                                @csrf
                                <div class="sm:col-span-2"><label class="label" for="e-name-{{ $g->id }}">Nama grup</label><input id="e-name-{{ $g->id }}" name="name" required maxlength="100" class="field" value="{{ $g->name }}"></div>
                                <div><label class="label" for="e-teacher-{{ $g->id }}">Guru pembimbing</label>
                                    <select id="e-teacher-{{ $g->id }}" name="teacher_id" class="field">@foreach($teachers as $t)<option value="{{ $t->id }}" @selected($g->teacher_id === $t->id)>{{ $t->user->name }} · {{ $t->active_load }}/{{ $t->max_mentees }}</option>@endforeach</select></div>
                                <div><label class="label" for="e-div-{{ $g->id }}">Divisi</label><input id="e-div-{{ $g->id }}" name="department_divisi" maxlength="100" class="field" value="{{ $g->department_divisi }}"></div>
                                <div><label class="label" for="e-start-{{ $g->id }}">Jam masuk</label><input id="e-start-{{ $g->id }}" name="work_start_time" type="time" required class="field" value="{{ $fmt($g->work_start_time) }}"></div>
                                <div><label class="label" for="e-end-{{ $g->id }}">Jam pulang</label><input id="e-end-{{ $g->id }}" name="work_end_time" type="time" required class="field" value="{{ $fmt($g->work_end_time) }}"></div>
                                <p class="hint sm:col-span-2">Perubahan berlaku untuk semua anggota grup. Jurnal yang sudah diperiksa tetap tercatat atas nama guru sebelumnya. Tempat PKL tidak bisa diganti; buat grup baru bila tempatnya berbeda.</p>
                                <button class="btn btn-navy btn-sm sm:col-span-2">Simpan perubahan grup</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.periods.groups.destroy', [$period, $g]) }}" class="mt-2" onsubmit="return confirm('Hapus grup {{ $g->name }}? Penempatan anggotanya tetap aktif tanpa grup.')">@csrf<button class="link text-sm text-danger">Hapus grup</button></form>
                    </details>
                @empty
                    <div class="empty">Belum ada grup penempatan. Buat grup untuk setiap tempat PKL dan guru pembimbing.</div>
                @endforelse
            </div>
        </section>
    @endif

    @if($tab === 'penempatan')
        <section class="stack-sm">
            @forelse($placements as $placement)
                <article class="card">
                    <h3 class="font-semibold"><a class="link" href="{{ route('monitor.participant', $placement) }}">{{ $placement->student->user->name }}</a></h3>
                    <p class="text-sm">{{ $placement->student->class_name }} · {{ $placement->company->name }} · {{ $placement->status->label() }}{{ $placement->group ? ' · Grup '.$placement->group->name : '' }}</p>
                    <p class="text-sm">Guru pembimbing: {{ $placement->teacher->user->name }}</p>
                    <p class="hint">{{ $placement->attendances_count }} catatan kehadiran · {{ $placement->journals_count }} jurnal · Nilai akhir: {{ $placement->assessment?->final_score ?? 'Belum tersedia' }}</p>
                </article>
            @empty
                <div class="empty">Belum ada penempatan siswa untuk periode ini. Buat grup penempatan atau <a class="link" href="{{ route('admin.placements') }}">atur penempatan per siswa</a>.</div>
            @endforelse
            {{ $placements->links() }}

            @if($groups->isNotEmpty())
                <div class="card stack-sm">
                    <h2 class="section-title">Pembayaran</h2>
                    <p class="hint">Status tagihan langganan untuk periode ini.</p>
                    @foreach($groups as $group)
                        <p class="text-sm">{{ $group->name }} · {{ $group->members->count() }} siswa</p>
                    @endforeach
                    <a class="link text-sm" href="{{ route('admin.payments', ['period' => $period->id]) }}">Kelola pembayaran</a>
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
