@extends('layouts.app', ['title' => 'Panel Admin Sekolah - Jurnalin'])

@section('content')
<div class="space-y-8">
    @php
        $activeCount = $counts['active_placements'];
        $notCheckedIn = max(0, $activeCount - $monitor['checked_in_today']);
        $todayLocal = now($school->timezone)->startOfDay();
    @endphp
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="hint">Selamat datang, {{ auth()->user()->name }}</p>
            <h1 class="text-2xl font-bold text-ink">Dashboard Koordinator PKL</h1>
            <p class="text-sm text-muted mt-1 tabular-nums">
                {{ $school->name }} · {{ $school->plan()->label() }} · {{ $counts['students'] }} dari {{ $school->studentLimit() }} tempat siswa terisi
            </p>
        </div>
        <a class="document-link" href="{{ route('admin.payments') }}">Kelola grup dan pembayaran</a>
    </div>

    <dl class="kpi-grid" aria-label="Angka PKL hari ini">
        <div class="card"><dt>Peserta aktif PKL</dt><dd>{{ $activeCount }} <small>dari {{ $counts['students'] }} siswa</small></dd></div>
        <div class="card"><dt>Tempat PKL aktif</dt><dd>{{ $counts['companies'] }} <small>mitra</small></dd></div>
        <div @class(['card', 'card-attention' => $monitor['pending_journals'] > 0])><dt>Jurnal menunggu guru</dt><dd>{{ $monitor['pending_journals'] }} <small>jurnal</small></dd></div>
        <div class="card"><dt>Presensi hari ini</dt><dd>{{ $monitor['checked_in_today'] }} <small>/ {{ $activeCount }} siswa</small></dd></div>
    </dl>

    @php
        $steps = [
            ['done' => $activePeriod !== null, 'label' => 'Buat periode PKL dan bobot nilai', 'detail' => $activePeriod?->name ?? 'Belum ada periode aktif', 'href' => '/admin/periods', 'cta' => 'Atur periode'],
            ['done' => $counts['companies'] > 0, 'label' => 'Tambahkan tempat PKL (DUDI)', 'detail' => $counts['companies'].' tempat PKL aktif', 'href' => '/admin/companies', 'cta' => 'Tambah tempat PKL'],
            ['done' => $counts['teachers'] > 0, 'label' => 'Masukkan guru pembimbing', 'detail' => $counts['teachers'].' guru', 'href' => '/admin/teachers', 'cta' => 'Impor atau tambah guru'],
            ['done' => $counts['students'] > 0, 'label' => 'Masukkan siswa peserta PKL', 'detail' => $counts['students'].' dari kuota '.$school->max_students_quota.' siswa', 'href' => '/admin/students', 'cta' => 'Impor atau tambah siswa'],
            ['done' => $counts['active_placements'] > 0, 'label' => 'Pasangkan siswa, tempat PKL, dan guru', 'detail' => $counts['active_placements'].' penempatan aktif', 'href' => '/admin/placements', 'cta' => 'Buat penempatan'],
            ['done' => filled($school->letterhead_config['principal_name'] ?? null), 'label' => 'Lengkapi kop surat dan nama kepala sekolah', 'detail' => 'Dipakai di PDF buku jurnal dan rekap nilai', 'href' => '/admin/settings', 'cta' => 'Buka pengaturan'],
        ];
        $doneCount = collect($steps)->where('done', true)->count();
    @endphp

    @if($doneCount < count($steps))
        <section class="onboarding" aria-labelledby="judul-persiapan">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="judul-persiapan" class="text-lg font-bold text-ink">Persiapan sebelum PKL dimulai</h2>
                <p class="text-sm text-muted tabular-nums">{{ $doneCount }} dari {{ count($steps) }} langkah selesai</p>
            </div>
            <div class="onboarding-bar" role="progressbar" aria-label="Kemajuan persiapan" aria-valuemin="0" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $doneCount }}">
                <span style="width: {{ round($doneCount / count($steps) * 100) }}%"></span>
            </div>
            <ol class="onboarding-list">
                @foreach($steps as $i => $step)
                    <li @class(['is-done' => $step['done']])>
                        <span class="onboarding-mark" aria-hidden="true">@if($step['done'])<x-icon name="check" />@else{{ $i + 1 }}@endif</span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink">{{ $step['label'] }} <span class="sr-only">{{ $step['done'] ? '(selesai)' : '(belum)' }}</span></div>
                            <div class="text-sm text-muted">{{ $step['detail'] }}</div>
                        </div>
                        @if($step['done'])
                            <span class="text-sm font-semibold text-emerald-strong">Selesai</span>
                        @else
                            <a href="{{ $step['href'] }}" class="document-link text-sm">{{ $step['cta'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ol>
            <div class="callout stack-sm">
                <p class="font-semibold">Setelah semua langkah selesai, PKL berjalan seperti ini:</p>
                <ol class="list-decimal pl-5 space-y-1 text-sm">
                    <li><strong>Siswa</strong> absen masuk dari HP di tempat PKL, menulis jurnal kegiatan, lalu absen pulang.</li>
                    <li><strong>Guru pembimbing</strong> memeriksa jurnal (setujui atau minta revisi), menyetujui izin, dan mengingatkan siswa yang belum absen.</li>
                    <li><strong>Di akhir periode</strong>, guru mengisi nilai, lalu Anda mengunduh buku jurnal dan rekap nilai ber-kop sekolah.</li>
                </ol>
                <p class="text-sm">Anda memantau semuanya dari halaman ini.</p>
            </div>
        </section>
    @endif

    @php
        $priorities = [
            ['href' => route('admin.pending-journals', $monitor['stale_journals'] ? ['filter' => 'lama'] : []), 'count' => $monitor['stale_journals'], 'label' => 'Jurnal tertunda lebih dari 3 hari', 'detail' => $monitor['pending_journals'].' jurnal menunggu guru. Lihat guru mana yang perlu diingatkan.'],
            ['href' => route('monitor.alerts'), 'count' => $notCheckedIn, 'label' => 'Siswa belum absen hari ini', 'detail' => 'Guru pembimbing mengingatkan siswanya. Pantau siswa yang tidak hadir berhari-hari.'],
            ['href' => '/admin/placements', 'count' => $monitor['unplaced_students'], 'label' => 'Siswa belum punya tempat PKL', 'detail' => 'Buat penempatan agar siswa bisa mulai absen dan menulis jurnal.'],
        ];
    @endphp
    <div class="grid lg:grid-cols-5 gap-6 items-start">
        <section class="card lg:col-span-3" aria-labelledby="judul-prioritas">
            <h2 id="judul-prioritas" class="section-title">Tugas prioritas</h2>
            <p class="hint">Hal yang perlu segera Anda tindak lanjuti.</p>
            <div class="task-list mt-2">
                @foreach($priorities as $item)
                    <a href="{{ $item['href'] }}">
                        <span>{{ $item['label'] }}<small>{{ $item['detail'] }}</small></span>
                        @if($item['count'] > 0)
                            <span class="task-count is-danger">{{ $item['count'] }}</span>
                        @else
                            <span class="badge badge-ok">Beres</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>

        <div class="lg:col-span-2 space-y-6">
            <section class="card" aria-labelledby="judul-aksi">
                <h2 id="judul-aksi" class="section-title">Aksi cepat</h2>
                <nav class="quick-grid mt-3" aria-label="Aksi cepat">
                    <a href="/admin/students"><x-icon name="profile" /> Tambah siswa</a>
                    <a href="/admin/placements"><x-icon name="pin" /> Atur penempatan</a>
                    <a href="/admin/periods"><x-icon name="calendar" /> Buat periode</a>
                    <a href="/admin/companies"><x-icon name="building" /> Kelola tempat PKL</a>
                    <a href="{{ route('admin.import') }}"><x-icon name="journal" /> Impor Excel</a>
                    @if($activePeriod)
                        <a href="/reports/periods/{{ $activePeriod->id }}/recap.pdf"><x-icon name="print" /> Unduh rekap nilai</a>
                    @else
                        <a href="/admin/settings"><x-icon name="school" /> Kop surat</a>
                    @endif
                </nav>
            </section>

            <section class="card" aria-labelledby="judul-periode">
                <h2 id="judul-periode" class="section-title">Periode PKL aktif</h2>
                @if($activePeriod)
                    @php
                        $state = $todayLocal->lt($activePeriod->start_date) ? ['Belum mulai', ''] : ($todayLocal->gt($activePeriod->end_date) ? ['Sudah lewat', 'badge-warn'] : ['Berjalan', 'badge-ok']);
                    @endphp
                    <div class="flex flex-wrap items-start justify-between gap-2 mt-2">
                        <div>
                            <p class="font-semibold text-ink">{{ $activePeriod->name }}</p>
                            <p class="hint tabular-nums">{{ $activePeriod->start_date->translatedFormat('d M Y') }} sampai {{ $activePeriod->end_date->translatedFormat('d M Y') }} ({{ (int) $activePeriod->start_date->diffInDays($activePeriod->end_date) + 1 }} hari)</p>
                        </div>
                        <span class="badge {{ $state[1] }}">{{ $state[0] }}</span>
                    </div>
                @else
                    <p class="hint mt-2">Belum ada periode aktif. Siswa baru bisa absen setelah periode dibuat.</p>
                    <a href="/admin/periods" class="link text-sm">Buat periode PKL</a>
                @endif
            </section>
        </div>
    </div>

    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-ink">Penempatan terbaru</h2>
            <a href="/admin/placements" class="px-4 py-2 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white text-sm font-semibold">
                Atur Penempatan Siswa
            </a>
        </div>

        @if($placements->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-8 text-center space-y-2">
                <div class="font-semibold text-ink">Belum ada penempatan PKL yang dibuat.</div>
                <p class="text-sm text-muted">Pastikan data guru, siswa, periode, dan tempat PKL sudah tersedia lalu buat penempatan pertama.</p>
                <a href="/admin/placements" class="inline-block mt-2 px-4 py-2 rounded-lg bg-emerald-check text-white text-sm font-semibold">Buat Penempatan Sekarang</a>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-border bg-paper">
                <table class="w-full text-sm text-left">
                    <thead class="bg-stone text-xs uppercase text-muted border-b border-border">
                        <tr>
                            <th class="px-4 py-3">Siswa</th>
                            <th class="px-4 py-3">Tempat PKL</th>
                            <th class="px-4 py-3">Guru Pembimbing</th>
                            <th class="px-4 py-3">Nilai Akhir</th>
                            <th class="px-4 py-3">Laporan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($placements as $p)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $p->student->user->name }}</div>
                                    <div class="text-xs text-muted tabular-nums">{{ $p->student->class_name }} | NISN {{ $p->student->nisn }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $p->company->name }}</div>
                                    <div class="text-xs text-muted">{{ $p->department_divisi ?: 'Umum' }} | Shift <span class="tabular-nums">{{ substr($p->work_start_time, 0, 5) }}-{{ substr($p->work_end_time, 0, 5) }}</span></div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $p->teacher->user->name }}</td>
                                <td class="px-4 py-3 tabular-nums text-xs">
                                    @if($p->assessment?->finalized_at)
                                        <span class="font-bold text-emerald-check">{{ number_format($p->assessment->final_score, 1) }} ({{ $p->assessment->predicate }})</span>
                                    @else
                                        <span class="text-muted">Belum difinalisasi</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="/reports/placements/{{ $p->id }}/logbook.pdf" class="text-xs font-semibold text-navy underline">
                                        Unduh Buku Jurnal PDF
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
