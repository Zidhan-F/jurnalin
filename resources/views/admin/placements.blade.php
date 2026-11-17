@extends('layouts.app', ['title' => 'Penempatan PKL Siswa - Jurnalin'])

@section('content')
<div class="grid lg:grid-cols-12 gap-8 items-start">
    <div class="lg:col-span-5 rounded-xl border border-border bg-stone p-6 space-y-4">
        <div>
            <h1 class="text-xl font-bold text-ink">Tempatkan siswa PKL</h1>
            <p class="text-sm text-muted mt-1">Pilih tempat kerja dan guru pembimbing untuk satu siswa. Setiap siswa memiliki satu penempatan aktif per periode.</p>
        </div>

        @if($periods->isEmpty() || $students->isEmpty() || $companies->isEmpty() || $teachers->isEmpty())
            <div class="border-t border-border pt-4">
                <h2 class="font-semibold">Lengkapi data untuk membuat penempatan</h2>
                <ul>
                    @if($periods->isEmpty())<li><a class="document-link" href="{{ route('admin.periods') }}">Siapkan periode PKL aktif</a></li>@endif
                    @if($students->isEmpty())<li><a class="document-link" href="{{ route('admin.students') }}">Tambahkan siswa</a></li>@endif
                    @if($companies->isEmpty())<li><a class="document-link" href="{{ route('admin.companies') }}">Tambahkan tempat PKL</a></li>@endif
                    @if($teachers->isEmpty())<li><a class="document-link" href="{{ route('admin.teachers') }}">Tambahkan guru pembimbing</a></li>@endif
                </ul>
            </div>
        @else
        <form method="POST" action="/admin/placements" class="space-y-3.5">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1" for="pkl_period_id">Periode PKL</label>
                <select id="pkl_period_id" name="pkl_period_id" required class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected(old('pkl_period_id') === $period->id)>{{ $period->name }} ({{ $period->academic_year }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="student_id">Siswa</label>
                <select id="student_id" name="student_id" data-search-hint="Cari nama, kelas, atau NISN" required class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    <option value="" disabled @selected(!old('student_id'))>Pilih siswa</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id') === $student->id)>{{ $student->user->name }} ({{ $student->class_name }} - {{ $student->nisn }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="company_id">Tempat PKL</label>
                <select id="company_id" name="company_id" required class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    <option value="" disabled @selected(!old('company_id'))>Pilih tempat PKL</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id') === $company->id)>{{ $company->name }} ({{ $company->city }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="teacher_id">Guru Pembimbing</label>
                <select id="teacher_id" name="teacher_id" required class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    <option value="" disabled @selected(!old('teacher_id'))>Pilih guru pembimbing</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->user->name }} ({{ $teacher->specialization }})</option>
                    @endforeach
                </select>
            </div>
            <details class="border-y border-border" @if(old('department_divisi') || old('industry_mentor_name') || old('industry_mentor_phone') || $errors->hasAny(['department_divisi','industry_mentor_name','industry_mentor_phone'])) open @endif>
                <summary class="cursor-pointer py-4 font-semibold text-sm">Divisi dan pembimbing industri (opsional)</summary>
                <div class="grid gap-3 pb-4">
            <div>
                <label class="block text-sm font-semibold mb-1" for="department_divisi">Divisi tempat kerja</label>
                <input id="department_divisi" name="department_divisi" type="text" value="{{ old('department_divisi') }}" placeholder="Contoh: IT Support, Service Advisor, Front Office, Gudang"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1" for="industry_mentor_name">Nama Pembimbing Industri</label>
                    <input id="industry_mentor_name" name="industry_mentor_name" type="text" value="{{ old('industry_mentor_name') }}" placeholder="Opsional (teks)"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="industry_mentor_phone">WhatsApp pembimbing industri</label>
                    <input id="industry_mentor_phone" name="industry_mentor_phone" type="tel" inputmode="numeric" autocomplete="off" value="{{ old('industry_mentor_phone') }}" placeholder="628..."
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                </div>
            </div>
                </div>
            </details>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1" for="work_start_time">Jam masuk kerja</label>
                    <input id="work_start_time" name="work_start_time" type="time" required value="{{ old('work_start_time', '08:00') }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="work_end_time">Jam pulang kerja</label>
                    <input id="work_end_time" name="work_end_time" type="time" required value="{{ old('work_end_time', '16:00') }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                </div>
            </div>
            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                Simpan penempatan
            </button>
        </form>
        @endif
    </div>

    <div class="lg:col-span-7 space-y-4">
        <h2 class="text-xl font-bold text-ink">Seluruh Penempatan ({{ $placements->count() }})</h2>
        @if($placements->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
                Belum ada penempatan siswa. Penempatan yang disimpan akan muncul di sini.
            </div>
        @else
            <div class="space-y-3">
                @foreach($placements as $p)
                    <div class="rounded-xl border border-border bg-paper p-4 flex flex-wrap justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-ink">{{ $p->student->user->name }}</span>
                                <span class="px-2 py-0.5 rounded-md text-xs font-semibold {{ $p->status === \App\Enums\PlacementStatus::Active ? 'bg-emerald-check/15 text-emerald-strong' : 'bg-stone text-muted' }}">
                                    {{ $p->status->label() }}
                                </span>
                            </div>
                            <div class="text-xs text-muted">{{ $p->company->name }} | Divisi: {{ $p->department_divisi ?: '-' }}</div>
                            <div class="text-xs text-muted">Guru: {{ $p->teacher->user->name }} | Supervisor: {{ $p->industry_mentor_name ?: '-' }}</div>
                            <div class="text-xs text-muted tabular-nums">Periode: {{ $p->pklPeriod->name }} | Shift {{ substr($p->work_start_time, 0, 5) }}-{{ substr($p->work_end_time, 0, 5) }}</div>
                        </div>
                        <div class="flex flex-col items-end justify-between gap-2">
                            <a href="/reports/placements/{{ $p->id }}/logbook.pdf" class="text-xs font-semibold text-navy underline">
                                Unduh Buku Jurnal PDF
                            </a>
                            @if($p->status === \App\Enums\PlacementStatus::Active)
                                <form method="POST" action="/admin/placements/{{ $p->id }}/status" class="flex gap-1">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="px-2.5 py-1 rounded-md border border-field bg-white text-xs font-semibold hover:bg-stone cursor-pointer">
                                        Tandai Selesai
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
