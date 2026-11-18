@extends('layouts.app', ['title' => 'Periode PKL & Rubrik Penilaian - Jurnalin'])

@section('content')
<div class="grid lg:grid-cols-12 gap-8 items-start">
    <div class="lg:col-span-5 rounded-xl border border-border bg-stone p-6 space-y-4">
        <div>
            <h1 class="text-xl font-bold text-ink">Buat Periode PKL Baru</h1>
            <p class="text-xs text-muted mt-1">Sesuaikan bobot penilaian Kurikulum Merdeka (total bobot wajib tepat 100%).</p>
        </div>

        <form method="POST" action="/admin/periods" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1" for="name">Nama Periode</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}" placeholder="Contoh: PKL Gelombang 2 TA 2026/2027"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            @php
                $durationChoices = ['1' => '1 bulan', '3' => '3 bulan', '6' => '6 bulan', '12' => '1 tahun', 'custom' => 'Kustom'];
                $oldDuration = array_key_exists((string) old('duration_months'), $durationChoices) ? (string) old('duration_months') : '6';
            @endphp
            <div class="space-y-4" x-data="{
                    duration: @js($oldDuration),
                    start: @js((string) old('start_date', now()->toDateString())),
                    end: @js((string) old('end_date', now()->addMonths(6)->subDay()->toDateString())),
                    target: @js((int) old('min_required_days', 120)),
                    applyDuration(resetTarget = true) {
                        if (this.duration === 'custom' || !this.start) return;
                        const months = Number(this.duration);
                        this.end = window.jurnalinAddMonths(this.start, months);
                        if (resetTarget) this.target = months * 20;
                    },
                    get totalDays() { return window.jurnalinDaysInclusive(this.start, this.end); }
                }">
                <div>
                    <label class="block text-sm font-semibold mb-1" for="academic_year">Tahun Ajaran</label>
                    <input id="academic_year" name="academic_year" type="text" required value="{{ old('academic_year', '2026/2027') }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                </div>

                <div>
                    <label for="duration_months" class="block text-sm font-semibold mb-1">Durasi PKL</label>
                    <p id="bantuan-durasi" class="text-sm text-muted mb-2">Pilih durasi untuk mengisi tanggal selesai dan target hari hadir otomatis. Semua tetap bisa diubah.</p>
                    <select id="duration_months" name="duration_months" x-model="duration" :data-selected="duration"
                        @change="$nextTick(() => applyDuration())" aria-describedby="bantuan-durasi"
                        class="w-full rounded-md border border-field bg-paper px-3">
                        @foreach($durationChoices as $value => $label)
                            <option value="{{ $value }}" @selected((string) $value === $oldDuration)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="start_date">Tanggal Mulai</label>
                        <input id="start_date" name="start_date" type="date" required x-model="start" @change="applyDuration(false)"
                            class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="end_date">Tanggal Selesai</label>
                        <input id="end_date" name="end_date" type="date" required x-model="end" @input="duration = 'custom'"
                            class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1" for="min_required_days">Target hari hadir <span class="block font-normal text-muted text-xs mt-0.5">Jumlah hari hadir untuk nilai kehadiran penuh.</span></label>
                    <input id="min_required_days" name="min_required_days" type="number" min="1" :max="totalDays || 366" max="366" required x-model.number="target" aria-describedby="ringkasan-durasi"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    <p id="ringkasan-durasi" class="text-sm text-muted mt-1" aria-live="polite">
                        <span x-show="totalDays > 0">Periode <span class="tabular-nums" x-text="totalDays"></span> hari kalender.</span>
                        Saran kira-kira 20 hari hadir per bulan; ubah jika tempat PKL bekerja 6 hari seminggu.
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-paper p-3 space-y-2">
                <div class="text-xs font-bold text-navy">Komposisi Bobot Nilai Akhir (Total = 100%)</div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs text-muted" for="industry_percentage">Nilai DUDI (%)</label>
                        <input id="industry_percentage" name="industry_percentage" type="number" required value="{{ old('industry_percentage', 70) }}"
                            class="w-full rounded-md border border-field bg-white px-2 py-1.5 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-xs text-muted" for="school_percentage">Nilai Guru (%)</label>
                        <input id="school_percentage" name="school_percentage" type="number" required value="{{ old('school_percentage', 20) }}"
                            class="w-full rounded-md border border-field bg-white px-2 py-1.5 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-xs text-muted" for="attendance_percentage">Absensi (%)</label>
                        <input id="attendance_percentage" name="attendance_percentage" type="number" required value="{{ old('attendance_percentage', 10) }}"
                            class="w-full rounded-md border border-field bg-white px-2 py-1.5 text-sm tabular-nums">
                    </div>
                </div>
            </div>

            <div>
                <label class="label" for="template_kind">Format penilaian</label>
                <select id="template_kind" name="template_kind" class="field">
                    <option value="tp">Tujuan Pembelajaran PKL Kurikulum Merdeka (disarankan)</option>
                    <option value="classic">Aspek umum (soft skills, hard skills, inisiatif)</option>
                </select>
            </div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="require_location" value="1" checked class="mt-1"> Wajib lokasi saat presensi (dapat diubah per periode)</label>
            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                Simpan Periode PKL
            </button>
        </form>
    </div>

    <div class="lg:col-span-7 space-y-4">
        <h2 class="text-xl font-bold text-ink">Daftar Periode PKL</h2>
        @if($periods->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
                Belum ada periode PKL. Gunakan formulir di sebelah kiri untuk membuat periode pertama.
            </div>
        @else
            <div class="space-y-3">
                @foreach($periods as $period)
                    @php $w = $period->assessment_template['weights'] ?? []; @endphp
                    <div class="rounded-xl border border-border bg-paper p-5 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-base text-ink"><a class="document-link" href="{{ route('admin.periods.show', $period) }}">{{ $period->name }}</a></h3>
                                    @if($period->is_active)
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-check/15 text-emerald-strong text-xs font-semibold">Aktif</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md bg-stone text-muted text-xs font-semibold">Arsip</span>
                                    @endif
                                </div>
                                <div class="text-xs text-muted tabular-nums mt-1">
                                    TA {{ $period->academic_year }} | {{ $period->durationLabel() }}: {{ $period->start_date->format('d M Y') }} s/d {{ $period->end_date->format('d M Y') }} | Target {{ $period->min_required_days }} hari | {{ $period->placements_count }} siswa
                                    @if($period->is_active)
                                        | Otomatis diarsipkan {{ $period->autoArchiveDate()->format('d M Y') }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.periods.show', ['period' => $period, 'tab' => 'kelas']) }}" class="px-3 py-1.5 rounded-lg border border-border bg-white hover:bg-stone text-ink text-xs font-semibold">
                                    Kelas & grup
                                </a>
                                <a href="/reports/periods/{{ $period->id }}/recap.pdf" class="px-3 py-1.5 rounded-lg bg-navy text-white text-xs font-semibold">
                                    Rekap PDF
                                </a>
                                <form method="POST" action="/admin/periods/{{ $period->id }}/toggle" data-question="{{ $period->is_active ? 'Arsipkan' : 'Aktifkan' }} periode {{ $period->name }}?" onsubmit="return window.confirm(this.dataset.question)">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg border border-border bg-white hover:bg-stone text-xs font-semibold cursor-pointer">
                                        {{ $period->is_active ? 'Arsipkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="text-xs text-muted bg-stone rounded-lg px-3 py-2 tabular-nums">
                            Bobot Rubrik: Industri <strong>{{ $w['industry_percentage'] ?? 70 }}%</strong> | Sekolah <strong>{{ $w['school_percentage'] ?? 20 }}%</strong> | Kehadiran <strong>{{ $w['attendance_percentage'] ?? 10 }}%</strong>
                            · {{ ($period->assessment_template['kind'] ?? '') === 'tp' ? 'Template TP Kurikulum Merdeka' : 'Template aspek umum' }} · Status {{ $period->statusLabel() }}
                        </div>
                        <details class="card-muted">
                            <summary class="link">Kebijakan presensi, jadwal, libur, dan aspek nilai</summary>
                            <form method="POST" action="{{ route('admin.periods.policy', $period) }}" class="stack-sm mt-3">
                                @csrf
                                <div class="grid sm:grid-cols-3 gap-2">
                                    <div><label class="label" for="st-{{ $period->id }}">Status</label><select id="st-{{ $period->id }}" name="status" class="field">@foreach(\App\Models\PklPeriod::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected($period->status === $k)>{{ $l }}</option>@endforeach</select></div>
                                    <div><label class="label" for="lt-{{ $period->id }}">Toleransi terlambat (menit)</label><input id="lt-{{ $period->id }}" name="late_tolerance_minutes" type="number" min="0" max="180" class="field tabular-nums" value="{{ $period->late_tolerance_minutes }}" placeholder="Ikut sekolah"></div>
                                    <div><label class="label" for="bd-{{ $period->id }}">Jurnal mundur maks. (hari)</label><input id="bd-{{ $period->id }}" name="backdate_days" type="number" min="0" max="60" class="field tabular-nums" value="{{ $period->backdate_days }}"></div>
                                </div>
                                <fieldset><legend class="label">Hari kerja</legend>
                                    <div class="flex flex-wrap gap-3">@foreach([1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'] as $d => $l)<label class="flex items-center gap-1"><input type="checkbox" name="work_days[]" value="{{ $d }}" @checked(in_array($d, $period->workDays(), true))> {{ $l }}</label>@endforeach</div>
                                </fieldset>
                                <label class="flex items-start gap-2"><input type="checkbox" name="require_location" value="1" class="mt-1" @checked($period->require_location)> Wajib lokasi saat presensi (hanya untuk siswa dewasa atau yang persetujuan walinya tercatat)</label>
                                <label class="flex items-start gap-2"><input type="checkbox" name="require_selfie" value="1" class="mt-1" @checked($period->selfieRequired())> Wajib swafoto dari kamera saat presensi</label>
                                <div><label class="label" for="hl-{{ $period->id }}">Hari libur (satu per baris: YYYY-MM-DD | keterangan)</label>
                                    <textarea id="hl-{{ $period->id }}" name="holidays_text" class="field tabular-nums" rows="3">{{ collect($period->holidayList())->map(fn ($h) => $h['date'].' | '.$h['label'])->implode("\n") }}</textarea></div>
                                <div><label class="label" for="as-{{ $period->id }}">Aspek nilai (opsional, satu per baris: industri|kode|nama|bobot atau sekolah|kode|nama|bobot)</label>
                                    <textarea id="as-{{ $period->id }}" name="aspects_text" class="field" rows="4" placeholder="Kosongkan bila tidak diubah"></textarea>
                                    <p class="hint">Aspek saat ini: {{ collect($period->assessment_template['industry_aspects'] ?? [])->map(fn ($a) => 'industri|'.$a['code'].'|'.$a['label'].'|'.$a['weight'])->merge(collect($period->assessment_template['school_aspects'] ?? [])->map(fn ($a) => 'sekolah|'.$a['code'].'|'.$a['label'].'|'.$a['weight']))->implode('; ') }}</p></div>
                                <button class="btn btn-navy btn-sm">Simpan kebijakan</button>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
