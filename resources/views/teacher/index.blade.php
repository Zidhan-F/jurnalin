@extends('layouts.app', ['title' => 'Dashboard Pantau Guru Pembimbing - Jurnalin'])

@section('content')
<div class="space-y-8">
    @php
        $journalOnly = request()->attributes->get('grace_placements') && ! request()->attributes->get('full_placements');
    @endphp
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="hint">Selamat datang, {{ auth()->user()->name }}</p>
            <h1 class="text-2xl font-bold text-ink">{{ $journalOnly ? 'Validasi jurnal siswa' : 'Dashboard Guru Pembimbing' }}</h1>
            <p class="text-sm text-muted mt-1 tabular-nums">
                @if($journalOnly)Bulan tambahan khusus pemeriksaan dan revisi jurnal.@else {{ $todayFormatted }} ({{ $school->timezone }}) · {{ $placements->count() }} siswa aktif @endif
            </p>
        </div>
        @unless($journalOnly)
        <a href="/teacher/assessments" class="px-4 py-2.5 rounded-lg bg-navy hover:bg-navy-strong text-white text-sm font-semibold min-h-11 inline-flex items-center">
            Isi nilai dan cetak laporan
        </a>
        @endunless
    </div>

    @unless($journalOnly)
        <dl class="kpi-grid" aria-label="Ringkasan siswa bimbingan">
            <div class="card kpi-card"><img src="{{ \App\Support\Mascot::role('student') }}" width="40" height="40" alt=""><div><dt>Peserta binaan</dt><dd>{{ $placements->count() }} <small>siswa</small></dd></div></div>
            <div @class(['card kpi-card', 'card-attention' => $pendingJournals->isNotEmpty()])><img src="/images/illustrations/journal.svg" width="40" height="40" alt=""><div><dt>Jurnal perlu review</dt><dd>{{ $pendingJournals->count() }} <small>jurnal</small></dd></div></div>
            <div class="card kpi-card"><img src="/images/illustrations/calendar.svg" width="40" height="40" alt=""><div><dt>Izin/ketidakhadiran</dt><dd>{{ $pendingLeaves->count() + $pendingExceptions->count() }} <small>menunggu</small></dd></div></div>
            <div @class(['card kpi-card', 'card-danger' => $progress['attention'] > 0])><img src="/images/illustrations/chart.svg" width="40" height="40" alt=""><div><dt>Perlu perhatian</dt><dd>{{ $progress['attention'] }} <small>siswa</small></dd></div></div>
        </dl>

        @if($placements->isNotEmpty())
        <div class="grid lg:grid-cols-5 gap-6 items-start">
            <section class="card lg:col-span-3" aria-labelledby="judul-jurnal-terbaru">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="judul-jurnal-terbaru" class="section-title">Jurnal terbaru (perlu tindakan)</h2>
                    <a href="{{ route('teacher.review') }}" class="link text-sm">Lihat semua</a>
                </div>
                @forelse($recentJournals as $j)
                    <a href="{{ route('teacher.review') }}" class="flex items-center gap-3 py-2.5 border-t border-border mt-2">
                        <x-avatar :user="$j->student->user" />
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-ink truncate">{{ $j->student->user->name }}</span><span class="hint block truncate">{{ $j->journal_date->translatedFormat('d M Y') }} · {{ $j->activity_title }}</span></span>
                        <span @class(['badge shrink-0', 'badge-warn' => $j->status === \App\Enums\JournalStatus::RevisionNeeded])>{{ $j->status === \App\Enums\JournalStatus::RevisionNeeded ? 'Perlu perbaikan' : 'Perlu review' }}</span>
                    </a>
                @empty
                    <p class="hint mt-2">Tidak ada jurnal yang menunggu tindakan Anda.</p>
                @endforelse
            </section>
            <section class="card lg:col-span-2" aria-labelledby="judul-progres-peserta">
                <h2 id="judul-progres-peserta" class="section-title">Progres peserta</h2>
                <p class="hint">Siswa bimbingan di periode aktif.</p>
                <div class="mt-3">
                    <x-chart type="doughnut" :labels="['Selesai', 'Berjalan', 'Perlu perhatian']" :values="[$progress['done'], $progress['running'], $progress['attention']]" :colors="['emerald-check', 'navy', 'amber-warn']" series-label="Siswa" caption="Progres siswa bimbingan" />
                </div>
            </section>
        </div>
        @endif
    @endunless

    @php
        // Ringkasan tugas di urutan bagian halaman: siapa yang butuh keputusan Anda sekarang.
        $overdueCount = $missingToday->where('is_overdue', true)->count();
        $tasks = collect($journalOnly ? [
            ['href' => '#jurnal-masuk', 'count' => $pendingJournals->count(), 'label' => 'Jurnal menunggu diperiksa', 'detail' => 'Setujui atau minta revisi dengan catatan', 'danger' => false],
        ] : [
            ['href' => '#peringatan', 'count' => $alerts->count(), 'label' => 'Siswa perlu tindak lanjut', 'detail' => 'Contoh: beberapa hari tidak absen atau jurnal tertunda', 'danger' => $alerts->contains('urgency', 'tinggi')],
            ['href' => '#izin', 'count' => $pendingLeaves->count(), 'label' => 'Izin dan sakit menunggu persetujuan', 'detail' => 'Setujui atau tolak dengan catatan', 'danger' => false],
            ['href' => '#belum-absen', 'count' => $missingToday->count(), 'label' => 'Siswa belum absen hari ini', 'detail' => $overdueCount ? $overdueCount.' sudah melewati jam masuk, ingatkan lewat WhatsApp' : 'Belum melewati jam masuk', 'danger' => $overdueCount > 0],
            ['href' => '#jurnal-masuk', 'count' => $pendingJournals->count(), 'label' => 'Jurnal menunggu diperiksa', 'detail' => 'Setujui atau minta revisi dengan catatan', 'danger' => false],
            ['href' => '#pengecualian', 'count' => $pendingExceptions->count(), 'label' => 'Absen di luar lokasi perlu dikonfirmasi', 'detail' => 'Periksa alasan dan foto, lalu konfirmasi', 'danger' => false],
        ])->where('count', '>', 0)->values();
    @endphp

    @if($journalOnly || $placements->isNotEmpty())
        <section class="card" aria-labelledby="judul-tugas">
            <h2 id="judul-tugas" class="text-lg font-bold text-ink">Yang perlu Anda kerjakan</h2>
            @if($tasks->isEmpty())
                <p class="mt-2 text-sm text-emerald-ink font-medium" role="status">Tidak ada yang menunggu keputusan Anda saat ini. Semua siswa sudah absen dan semua jurnal sudah diperiksa.</p>
            @else
                <p class="hint mt-1">Ketuk salah satu baris untuk langsung ke bagiannya.</p>
                <div class="task-list mt-2">
                    @foreach($tasks as $task)
                        <a href="{{ $task['href'] }}">
                            <span>{{ $task['label'] }}<small>{{ $task['detail'] }}</small></span>
                            <span @class(['task-count', 'is-danger' => $task['danger']])>{{ $task['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @unless($journalOnly)
    <details class="card">
    <summary class="cursor-pointer py-3 font-semibold">Ringkasan kehadiran dan jurnal</summary>
    <dl class="stat-row" aria-label="Ringkasan hari ini dan minggu ini">
        <div><dt>Siswa aktif</dt><dd>{{ $placements->count() }}</dd></div>
        @foreach($presentToday as $label => $count)<div><dt>{{ $label }} hari ini</dt><dd>{{ $count }}</dd></div>@endforeach
        <div><dt>Izin menunggu</dt><dd>{{ $pendingLeaves->count() }}</dd></div>
        <div><dt>Jurnal perlu diperiksa</dt><dd>{{ $pendingJournals->count() }}</dd></div>
        <div><dt>Kelengkapan jurnal minggu ini</dt><dd>{{ $weekly['percent'] }}%</dd></div>
    </dl>
    </details>

    @if($alerts->isNotEmpty())
        <section id="peringatan" class="stack-sm scroll-mt-4" aria-labelledby="judul-peringatan">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="judul-peringatan" class="text-lg font-bold text-ink">Perlu tindak lanjut ({{ $alerts->count() }})</h2>
                <a href="{{ route('monitor.alerts') }}" class="link text-sm">Semua peringatan</a>
            </div>
            @foreach($alerts as $a)
                <article class="card {{ $a['urgency'] === 'tinggi' ? 'card-danger' : 'card-attention' }} flex flex-wrap justify-between gap-2">
                    <div><strong>{{ $a['placement']->student->user->name }}</strong> <span class="badge {{ $a['urgency'] === 'tinggi' ? 'badge-danger' : 'badge-warn' }}">Urgensi {{ $a['urgency'] }}</span><p class="hint mt-1">{{ $a['message'] }}</p></div>
                    <a href="{{ route('monitor.participant', $a['placement']) }}" class="btn btn-outline btn-sm">Buka siswa</a>
                </article>
            @endforeach
        </section>
    @endif

    @if($pendingLeaves->isNotEmpty())
        <section id="izin" class="stack-sm scroll-mt-4" aria-labelledby="judul-izin">
            <h2 id="judul-izin" class="text-lg font-bold text-ink">Izin & sakit menunggu persetujuan ({{ $pendingLeaves->count() }})</h2>
            @foreach($pendingLeaves as $leave)
                <article class="card card-attention stack-sm">
                    <div class="flex flex-wrap justify-between gap-2">
                        <div><strong>{{ $leave->student->user->name }}</strong> · {{ $leave->type === 'sick' ? 'Sakit' : 'Izin' }}
                            <p class="hint tabular-nums">{{ $leave->start_date->format('d/m/Y') }}@if(! $leave->start_date->equalTo($leave->end_date)) s.d. {{ $leave->end_date->format('d/m/Y') }}@endif · {{ $leave->placement->company->name }}</p></div>
                        @if($leave->attachment_path)<a href="/media/{{ $leave->attachment_path }}" target="_blank" class="link text-sm">Lihat lampiran</a>@endif
                    </div>
                    <p>{{ $leave->reason }}</p>
                    <form method="POST" action="{{ route('teacher.leaves.decide', $leave) }}" class="grid sm:grid-cols-4 gap-2 items-end" x-data="{ d: 'approved' }">
                        @csrf
                        <div class="sm:col-span-2"><label class="label" for="ln-{{ $leave->id }}">Catatan (wajib bila ditolak)</label><input id="ln-{{ $leave->id }}" name="decision_note" class="field" :required="d === 'rejected'"></div>
                        <button name="decision" value="approved" @click="d = 'approved'" class="btn btn-primary">Setujui</button>
                        <button name="decision" value="rejected" @click="d = 'rejected'" class="btn btn-danger">Tolak</button>
                    </form>
                </article>
            @endforeach
        </section>
    @endif
    @endunless

    {{-- PRIORITAS 1: SISWA BELUM ABSEN HARI INI --}}
    @unless($journalOnly)
    <section id="belum-absen" class="space-y-3 scroll-mt-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-ink">Belum absen hari ini ({{ $missingToday->count() }})</h2>
        </div>

        @if($placements->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-6 text-sm text-muted">
                Anda belum memiliki siswa bimbingan yang berstatus Aktif PKL pada periode saat ini. Hubungi koordinator Hubin jika penempatan belum dibuat.
            </div>
        @elseif($missingToday->isEmpty())
            <div class="rounded-xl border border-emerald-check/30 bg-emerald-check/10 p-5 text-sm font-medium text-emerald-ink">
                Semua siswa bimbingan Anda ({{ $placements->count() }}/{{ $placements->count() }}) sudah mencatat kehadiran hari ini.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-border bg-paper">
                <table class="w-full text-sm text-left">
                    <thead class="bg-stone text-xs uppercase text-muted border-b border-border">
                        <tr>
                            <th class="px-4 py-3">Siswa &amp; Kelas</th>
                            <th class="px-4 py-3">Tempat PKL</th>
                            <th class="px-4 py-3">Batas Masuk</th>
                            <th class="px-4 py-3">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($missingToday as $item)
                            @php
                                $p = $item['placement'];
                                $waMsg = "Halo {$p->student->user->name}, silakan segera mencatat absen PKL hari ini di {$p->company->name} melalui Jurnalin.";
                                $waLink = $p->student->user->whatsappUrl($waMsg);
                            @endphp
                            <tr class="{{ $item['is_overdue'] ? 'border-l-4 border-l-danger' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-ink">{{ $p->student->user->name }}</div>
                                    <div class="text-xs text-muted">{{ $p->student->class_name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $p->company->name }}</div>
                                    <div class="text-xs text-muted">{{ $p->department_divisi ?: 'Umum' }}</div>
                                </td>
                                <td class="px-4 py-3 tabular-nums text-xs">
                                    <div>Maks. {{ $item['deadline_time'] }}</div>
                                    @if($item['is_overdue'])
                                        <span class="font-semibold text-danger">Melewati jam masuk</span>
                                    @else
                                        <span class="text-muted">Menunggu shift</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($waLink)
                                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white text-xs font-semibold">
                                            Kirim Pesan WA
                                        </a>
                                    @else
                                        <span class="text-xs text-muted">No. WA belum diisi</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- PRIORITAS 2: ANTREAN JURNAL MENUNGGU VALIDASI --}}
    @endunless
    <section id="jurnal-masuk" class="space-y-3 scroll-mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-ink">Jurnal yang perlu diperiksa ({{ $pendingJournals->count() }})</h2>
            @if($pendingJournals->isNotEmpty())
                {{-- Batch review hanya untuk jurnal yang sudah dibaca: tidak ada tombol setujui semua (P12, M07). --}}
                <form id="bulk-approve" method="POST" action="/teacher/journals/bulk-approve" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <span class="hint">Centang jurnal yang sudah Anda baca, lalu</span>
                    <button type="submit" class="btn btn-primary btn-sm">Setujui yang dicentang</button>
                    <a href="{{ route('teacher.review') }}" class="link text-sm">Buka semua jurnal yang perlu diperiksa</a>
                </form>
            @endif
        </div>

        @if($pendingJournals->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-6 text-sm text-muted">
                Tidak ada jurnal baru yang menunggu validasi Anda saat ini.
            </div>
        @else
            <div class="space-y-3">
                @foreach($pendingJournals as $journal)
                    <div x-data="{ showRevision: false }" class="rounded-xl border border-border border-l-4 border-l-amber-warn bg-paper p-5 space-y-3">
                        <div class="flex flex-wrap justify-between gap-3">
                            <div>
                                <div class="text-xs text-muted tabular-nums">
                                    {{ $journal->journal_date->format('d M Y') }} | {{ $journal->work_unit }} | {{ $journal->duration_minutes }} menit
                                </div>
                                <h3 class="font-bold text-base text-ink mt-0.5">{{ $journal->activity_title }}</h3>
                                <div class="text-xs font-medium text-navy">
                                    {{ $journal->student->user->name }} ({{ $journal->placement->company->name }})
                                </div>
                            </div>
                            <div class="flex flex-wrap items-start gap-2">
                                <form method="POST" action="/teacher/journals/{{ $journal->id }}/approve">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-2 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white text-xs font-semibold cursor-pointer">
                                        Setujui Jurnal
                                    </button>
                                </form>
                                <button type="button" x-ref="revisionToggle" :aria-expanded="showRevision" aria-controls="revision-{{ $journal->id }}" @click="showRevision = !showRevision"
                                    class="px-3.5 py-2 rounded-lg border border-amber-warn text-amber-strong hover:bg-amber-warn/10 text-xs font-semibold cursor-pointer">
                                    Minta Revisi
                                </button>
                            </div>
                        </div>

                        <p class="text-sm text-ink leading-relaxed">{{ $journal->activity_description }}</p>
                        @if($journal->mentor_note)
                            <p class="text-sm rounded-lg bg-stone border border-border px-3 py-2"><strong>Catatan pembimbing DUDI:</strong> {{ $journal->mentor_note }}</p>
                        @endif

                        @if($journal->tools_materials || $journal->learning_outcome)
                            <div class="grid sm:grid-cols-2 gap-3 text-xs text-muted bg-stone rounded-lg p-3">
                                <div><strong>Alat/Bahan:</strong> {{ $journal->tools_materials ?: '-' }}</div>
                                <div><strong>Capaian:</strong> {{ $journal->learning_outcome ?: '-' }}</div>
                            </div>
                        @endif

                        @if($journal->photos())
                            <div class="flex flex-wrap gap-2">
                                @foreach($journal->photos() as $photo)
                                    <a href="/media/{{ $photo }}" target="_blank" class="inline-block">
                                        <img src="/media/{{ $photo }}" alt="Foto kegiatan jurnal {{ $loop->iteration }}" class="h-28 rounded-lg border border-border object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        @if($journal->obstacles)<div class="text-xs"><strong>Kendala:</strong> {{ $journal->obstacles }}</div>@endif
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="journal_ids[]" value="{{ $journal->id }}" form="bulk-approve"> Sudah saya baca, sertakan pada setujui yang dicentang</label>

                        <form id="revision-{{ $journal->id }}" @keydown.escape="showRevision = false; $refs.revisionToggle.focus()" x-show="showRevision" x-cloak method="POST" action="/teacher/journals/{{ $journal->id }}/revision" class="pt-2 border-t border-border space-y-2">
                            @csrf
                            <label class="block text-xs font-semibold text-amber-strong" for="feedback-{{ $journal->id }}">Catatan Perbaikan untuk Siswa (Wajib diisi)</label>
                            <textarea id="feedback-{{ $journal->id }}" name="teacher_feedback" rows="2" required placeholder="Contoh: Jelaskan langkah kerja sesuai SOP perusahaan."
                                class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm"></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showRevision = false; $refs.revisionToggle.focus()" class="px-3 py-1.5 text-xs text-muted cursor-pointer">Batal</button>
                                <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-warn text-white text-xs font-semibold cursor-pointer">Kirim Revisi</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- PRIORITAS 3: PERSETUJUAN PENGECUALIAN ABSENSI --}}
    @if($pendingExceptions->isNotEmpty())
        <section id="pengecualian" class="space-y-3 scroll-mt-4">
            <h2 class="text-lg font-bold text-ink">Absen di luar lokasi, sakit, dan izin yang perlu diperiksa ({{ $pendingExceptions->count() }})</h2>
            <p class="hint">Tanda lokasi adalah sinyal pendukung, bukan bukti. Keputusan tetap pada Anda.</p>
            <div class="space-y-3">
                @foreach($pendingExceptions as $att)
                    <div class="rounded-xl border border-border border-l-4 border-l-danger bg-paper p-4 flex flex-wrap justify-between items-center gap-4">
                        <div class="space-y-1">
                            <div class="font-bold text-sm text-ink">
                                {{ $att->student->user->name }}
                                <span class="ml-2 px-2 py-0.5 rounded-md bg-danger/15 text-danger-strong text-xs font-semibold">
                                    {{ $att->is_within_radius ? $att->status->label() : 'Di Luar Radius (' . $att->check_in_distance_m . ' m)' }}
                                </span>
                            </div>
                            <div class="text-xs text-muted tabular-nums">
                                {{ $att->attendance_date->format('d M Y') }} | {{ $att->placement->company->name }}
                            </div>
                            @if($att->exception_reason)<div class="text-xs text-ink">Alasan: <strong>{{ $att->exception_reason }}</strong></div>@endif
                            @if($att->isFlagged())<div class="flex flex-wrap gap-1">@foreach($att->flagLabels() as $fl)<span class="badge badge-warn">{{ $fl }}</span>@endforeach</div>@endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if($att->check_in_photo_path)
                                <a href="/media/{{ $att->check_in_photo_path }}" target="_blank" class="text-xs underline font-semibold text-navy">Lihat Foto</a>
                            @endif
                            <form method="POST" action="{{ route('teacher.attendances.review', $att) }}" class="flex flex-wrap items-end gap-2" x-data="{ d: 'confirmed' }">
                                @csrf
                                <label class="sr-only" for="rn-{{ $att->id }}">Alasan bila tidak diakui</label>
                                <input id="rn-{{ $att->id }}" name="review_note" class="field" placeholder="Alasan bila tidak diakui" :required="d === 'rejected'">
                                <button name="decision" value="confirmed" @click="d = 'confirmed'" class="btn btn-primary btn-sm">Konfirmasi</button>
                                <button name="decision" value="rejected" @click="d = 'rejected'" class="btn btn-danger btn-sm">Tidak diakui</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
