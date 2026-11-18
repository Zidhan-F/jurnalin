@extends('layouts.app', ['title' => 'Panel Admin Sekolah - Jurnalin'])

@section('content')
<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Ringkasan PKL</h1>
            <p class="mt-1 font-semibold">{{ $school->name }}</p>
            <p class="text-sm text-muted mt-1 tabular-nums">
                {{ $school->plan()->label() }} · {{ $counts['students'] }} dari {{ $school->studentLimit() }} tempat siswa terisi
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            <a class="document-link" href="{{ route('admin.payments') }}">Kelola grup dan pembayaran</a>
        @if($activePeriod)
            <a href="/reports/periods/{{ $activePeriod->id }}/recap.pdf" class="document-link">
                Unduh rekap nilai <span class="sr-only">{{ $activePeriod->name }}</span> (PDF)
            </a>
        @endif
        </div>
    </div>

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

    @if($monitor)
        @php
            $activeCount = $counts['active_placements'];
            $notCheckedIn = max(0, $activeCount - $monitor['checked_in_today']);
            $watch = collect([
                ['href' => '/admin/placements', 'count' => $monitor['unplaced_students'], 'label' => 'Siswa belum punya tempat PKL', 'detail' => 'Buat penempatan agar siswa bisa mulai absen', 'danger' => false],
                ['href' => null, 'count' => $monitor['stale_journals'], 'label' => 'Jurnal menunggu guru lebih dari 3 hari', 'detail' => 'Ingatkan guru pembimbing yang bersangkutan', 'danger' => true],
            ])->where('count', '>', 0)->values();
        @endphp
        <section class="card space-y-3" aria-labelledby="judul-pantauan">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="judul-pantauan" class="text-lg font-bold text-ink">Pantauan PKL hari ini</h2>
                @if($activePeriod)<p class="hint">{{ $activePeriod->name }}</p>@endif
            </div>
            <dl class="stat-row" aria-label="Angka hari ini">
                <div><dt>Siswa aktif PKL</dt><dd>{{ $activeCount }}</dd></div>
                <div><dt>Sudah absen hari ini</dt><dd>{{ $monitor['checked_in_today'] }}</dd></div>
                <div><dt>Belum absen hari ini</dt><dd>{{ $notCheckedIn }}</dd></div>
                <div><dt>Jurnal menunggu guru</dt><dd>{{ $monitor['pending_journals'] }}</dd></div>
            </dl>
            @if($watch->isEmpty())
                <p class="text-sm text-emerald-ink font-medium" role="status">Tidak ada masalah yang perlu Anda tangani. Siswa yang belum absen diingatkan langsung oleh guru pembimbingnya.</p>
            @else
                <div class="task-list">
                    @foreach($watch as $item)
                        @if($item['href'])
                            <a href="{{ $item['href'] }}">
                                <span>{{ $item['label'] }}<small>{{ $item['detail'] }}</small></span>
                                <span @class(['task-count', 'is-danger' => $item['danger']])>{{ $item['count'] }}</span>
                            </a>
                        @else
                            <div class="flex items-center justify-between gap-3 py-2.5 px-1 border-t border-border">
                                <span class="font-semibold">{{ $item['label'] }}<small class="block font-medium text-sm text-muted">{{ $item['detail'] }}</small></span>
                                <span @class(['task-count', 'is-danger' => $item['danger']])>{{ $item['count'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
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
