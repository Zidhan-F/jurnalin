@extends('layouts.app', ['title' => 'Panel Admin Sekolah - Jurnalin'])

@section('content')
<div class="space-y-8">
    @php
        $d = $dashboard;
        $period = $d['period'];
        $kpi = $d['kpi'];
        $todayLocal = now($school->timezone)->startOfDay();
        $signed = fn (int $n) => ($n >= 0 ? '+' : '').$n;
    @endphp
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="hint">Selamat datang, {{ auth()->user()->name }}</p>
            <h1 class="text-2xl font-bold text-ink">Dashboard Koordinator PKL</h1>
            <p class="text-sm text-muted mt-1">Kelola pelaksanaan PKL, pantau aktivitas peserta, dan pastikan semua berjalan lancar.</p>
            <p class="text-sm text-muted mt-1 tabular-nums">{{ $school->name }} · {{ $school->plan()->label() }} · {{ $counts['students'] }} dari {{ $school->studentLimit() }} tempat siswa terisi · <a class="link" href="{{ route('admin.payments') }}">Kelola grup dan pembayaran</a></p>
        </div>
        @if($periods->isNotEmpty())
            <form method="GET" action="{{ route('admin.index') }}" class="period-picker" x-data>
                <label for="pilih-periode"><x-icon name="calendar" /> Periode</label>
                <select id="pilih-periode" name="periode" class="field" @change="$el.form.submit()">
                    @foreach($periods as $option)
                        <option value="{{ $option->id }}" @selected($period?->id === $option->id)>{{ $option->name }} ({{ $option->academic_year }})</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-navy btn-sm">Tampilkan</button></noscript>
            </form>
        @endif
    </div>

    @if($kpi)
        <dl class="kpi-grid" aria-label="Angka PKL {{ $period->name }}">
            <div class="card kpi-card">
                <img src="{{ \App\Support\Mascot::role('student') }}" width="40" height="40" alt="">
                <div><dt>Peserta PKL</dt><dd>{{ $kpi['participants'] }} <small>siswa</small></dd>
                @if($kpi['participantsDelta'] !== null)<p class="kpi-trend {{ $kpi['participantsDelta'] >= 0 ? 'is-up' : 'is-down' }}">{{ $signed($kpi['participantsDelta']) }} siswa dari periode lalu</p>@endif</div>
            </div>
            <div class="card kpi-card">
                <img src="/images/illustrations/partnership.svg" width="40" height="40" alt="">
                <div><dt>Penempatan</dt><dd>{{ $kpi['companies'] }} <small>mitra</small></dd>
                @if($kpi['newCompanies'] !== null)<p class="kpi-trend is-up">+{{ $kpi['newCompanies'] }} mitra baru</p>@endif</div>
            </div>
            <div @class(['card kpi-card', 'card-attention' => $kpi['pendingJournals'] > 0])>
                <img src="/images/illustrations/journal.svg" width="40" height="40" alt="">
                <div><dt>Jurnal perlu diperiksa</dt><dd>{{ $kpi['pendingJournals'] }} <small>jurnal</small></dd>
                @if($kpi['pendingJournals'] > 0)<p class="kpi-trend is-warn">Perlu tindakan Anda</p>@endif</div>
            </div>
            <div class="card kpi-card">
                <img src="/images/illustrations/calendar.svg" width="40" height="40" alt="">
                @if($d['isRunning'])
                    <div><dt>Presensi hari ini</dt><dd>{{ $kpi['present'] }} <small>/ {{ $kpi['presentBase'] }} siswa</small></dd>
                    @if($kpi['attendanceRate'] !== null)<p class="kpi-trend">Tingkat kehadiran: {{ $kpi['attendanceRate'] }}%</p>@endif</div>
                @else
                    <div><dt>Kehadiran seluruh periode</dt><dd>{{ $kpi['attendanceRate'] ?? 0 }}<small>%</small></dd>
                    <p class="kpi-trend">{{ $kpi['present'] }} dari {{ $kpi['presentBase'] }} catatan presensi</p></div>
                @endif
            </div>
        </dl>
    @endif

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

    <div class="grid lg:grid-cols-5 gap-6 items-start">
        <section class="card lg:col-span-3" aria-labelledby="judul-prioritas">
            <h2 id="judul-prioritas" class="section-title">Tugas prioritas</h2>
            <p class="hint">Hal yang perlu segera Anda tindak lanjuti.</p>
            <div class="task-list mt-2">
                @foreach($d['priorities'] as $item)
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
                <p class="hint">Fitur yang paling sering digunakan.</p>
                <nav class="quick-grid mt-3" aria-label="Aksi cepat">
                    <a href="/admin/students"><x-icon name="profile" /> Tambah siswa</a>
                    <a href="/admin/placements"><x-icon name="pin" /> Atur penempatan</a>
                    <a href="/admin/periods"><x-icon name="calendar" /> Buat periode</a>
                    <a href="/admin/companies"><x-icon name="building" /> Kelola tempat PKL</a>
                    @if($period)
                        <a href="{{ route('admin.periods.show', $period) }}"><x-icon name="journal" /> Lihat rekap</a>
                        <a href="/reports/periods/{{ $period->id }}/recap.pdf"><x-icon name="print" /> Unduh laporan</a>
                    @else
                        <a href="{{ route('admin.import') }}"><x-icon name="journal" /> Impor Excel</a>
                        <a href="/admin/settings"><x-icon name="school" /> Kop surat</a>
                    @endif
                </nav>
            </section>

            <section class="card period-card" aria-labelledby="judul-periode">
                <h2 id="judul-periode" class="section-title">{{ $period && $period->is_active ? 'Periode PKL aktif' : 'Periode PKL' }}</h2>
                @if($period)
                    @php
                        $state = $todayLocal->lt($period->start_date) ? ['Belum mulai', ''] : ($todayLocal->gt($period->end_date) ? ['Selesai', 'badge-warn'] : ['Berjalan', 'badge-ok']);
                    @endphp
                    <div class="flex flex-wrap items-start justify-between gap-2 mt-2">
                        <div>
                            <p class="font-semibold text-ink">{{ $period->name }}</p>
                            <p class="hint tabular-nums">{{ $period->start_date->translatedFormat('d M Y') }} sampai {{ $period->end_date->translatedFormat('d M Y') }} ({{ (int) $period->start_date->diffInDays($period->end_date) + 1 }} hari)</p>
                        </div>
                        <span class="badge {{ $state[1] }}">{{ $state[0] }}</span>
                    </div>
                @else
                    <p class="hint mt-2">Belum ada periode. Siswa baru bisa absen setelah periode dibuat.</p>
                    <a href="/admin/periods" class="link text-sm">Buat periode PKL</a>
                @endif
            </section>
        </div>
    </div>

    @if($d['journalTrend'])
        @php $trend = $d['journalTrend']; $status = $d['status']; @endphp
        <div class="grid lg:grid-cols-5 gap-6 items-start">
            <section class="card lg:col-span-3" aria-labelledby="judul-tren">
                <h2 id="judul-tren" class="section-title">Perkembangan jurnal (7 hari terakhir)</h2>
                <p class="hint">Jumlah jurnal yang dikirim peserta per tanggal.</p>
                <div class="trend-layout mt-3">
                    <x-chart type="line" :labels="$trend['labels']" :values="$trend['values']" series-label="Jurnal" caption="Jumlah jurnal per hari, 7 hari terakhir" />
                    <div class="trend-total">
                        <p class="hint">Total jurnal</p>
                        <p class="trend-number">{{ number_format($trend['total'], 0, ',', '.') }}</p>
                        @if($trend['changePercent'] !== null)
                            <p class="kpi-trend {{ $trend['changePercent'] >= 0 ? 'is-up' : 'is-down' }}">{{ $signed($trend['changePercent']) }}% dari 7 hari sebelumnya</p>
                        @endif
                    </div>
                </div>
            </section>
            <section class="card lg:col-span-2" aria-labelledby="judul-status">
                <h2 id="judul-status" class="section-title">Status peserta</h2>
                <p class="hint">{{ $status['scope'] === 'today' ? 'Presensi hari ini dari peserta aktif.' : 'Komposisi presensi seluruh periode.' }}</p>
                <div class="mt-3">
                    <x-chart type="doughnut" :labels="$status['labels']" :values="$status['values']" series-label="Peserta" :caption="$status['scope'] === 'today' ? 'Status peserta hari ini' : 'Status presensi seluruh periode'" />
                </div>
            </section>
        </div>
    @endif

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
