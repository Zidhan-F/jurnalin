@extends('layouts.student-pwa', ['title' => 'Profil & Nilai PKL - Jurnalin'])

@section('content')
<div class="space-y-5">
    <section class="rounded-xl border border-border bg-stone p-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3.5 min-w-0">
            <x-avatar :user="auth()->user()" size="lg" />
            <div class="min-w-0 space-y-1">
                <h1 class="text-lg font-bold text-ink truncate">{{ auth()->user()->name }}</h1>
                <div class="text-xs text-muted tabular-nums">
                    NISN: {{ $student->nisn }} | {{ $student->class_name }} ({{ $student->major }})
                </div>
                <div class="text-xs text-muted truncate">Sekolah: {{ $school->name }}</div>
            </div>
        </div>
        <a href="{{ route('profile.photo.edit') }}" class="btn btn-outline btn-sm shrink-0">Ganti Foto</a>
    </section>

    @if($placement)
        <section class="rounded-xl border border-border bg-paper p-5 space-y-3">
            <h2 class="font-bold text-base text-ink">Detail Penempatan &amp; Progres</h2>
            <div class="text-sm space-y-1">
                <div>Tempat PKL: <strong>{{ $placement->company->name }}</strong></div>
                <div>Divisi: {{ $placement->department_divisi ?: '-' }}</div>
                <div>Guru Pembimbing: {{ $placement->teacher->user->name }}</div>
                <div>Pembimbing Industri: {{ $placement->industry_mentor_name ?: '-' }}</div>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-2">
                <div class="rounded-lg bg-stone border border-border p-3">
                    <div class="text-xs text-muted">Kehadiran Terhitung</div>
                    <div class="text-lg font-bold tabular-nums text-ink mt-0.5">
                        {{ $attendedCount }} / {{ $placement->pklPeriod->min_required_days }} hari
                    </div>
                </div>
                <div class="rounded-lg bg-stone border border-border p-3">
                    <div class="text-xs text-muted">Jurnal Disetujui</div>
                    <div class="text-lg font-bold tabular-nums text-emerald-check mt-0.5">{{ $approvedJournalsCount }} jurnal</div>
                </div>
            </div>
            <a href="/reports/placements/{{ $placement->id }}/logbook.pdf"
                class="inline-flex items-center justify-center w-full py-3 rounded-lg bg-navy hover:bg-navy-strong text-white text-sm font-semibold min-h-12">
                Unduh Buku Jurnal PDF Saya
            </a>
        </section>

        <section class="rounded-xl border border-border bg-paper p-5 space-y-3">
            <h2 class="font-bold text-base text-ink">Rekap Nilai Akhir PKL</h2>
            @if($placement->assessment?->finalized_at)
                <div class="rounded-lg border border-emerald-check/30 bg-emerald-check/10 p-4 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-emerald-ink">Nilai Akhir &amp; Predikat</div>
                        <div class="text-2xl font-bold tabular-nums text-emerald-ink">
                            {{ number_format($placement->assessment->final_score, 2) }} ({{ $placement->assessment->predicate }})
                        </div>
                    </div>
                    <div class="text-right text-xs tabular-nums text-emerald-ink">
                        <div>Industri: {{ number_format($placement->assessment->industry_scores['weighted_average'] ?? 0, 1) }}</div>
                        <div>Sekolah: {{ number_format($placement->assessment->school_scores['weighted_average'] ?? 0, 1) }}</div>
                        <div>Absensi: {{ number_format($placement->assessment->attendance_score, 1) }}</div>
                    </div>
                </div>
                @if($placement->assessment->teacher_notes || $placement->assessment->industry_notes)
                    <div class="text-xs text-muted space-y-1">
                        @if($placement->assessment->industry_notes)
                            <div><strong>Catatan pembimbing di tempat PKL:</strong> {{ $placement->assessment->industry_notes }}</div>
                        @endif
                        @if($placement->assessment->teacher_notes)
                            <div><strong>Catatan Guru:</strong> {{ $placement->assessment->teacher_notes }}</div>
                        @endif
                    </div>
                @endif
                @if($placement->assessment->achievement_description)
                    <p class="callout">{{ $placement->assessment->achievement_description }}</p>
                @endif
            @else
                <p class="text-sm text-muted">Nilai akhir PKL belum difinalisasi oleh guru pembimbing Anda.</p>
            @endif
        </section>
    @else
        <div class="rounded-xl border border-border bg-stone p-6 text-center text-sm text-muted">
            Belum ada penempatan PKL untuk akun Anda.
        </div>
    @endif

    <nav aria-label="Menu lainnya" class="stack-sm">
        <h2 class="section-title">Menu lainnya</h2>
        {{-- Dikelompokkan menurut kapan siswa membutuhkannya: sehari-hari, belajar, akhir PKL. --}}
        <h3 class="text-sm font-semibold text-muted">Sehari-hari</h3>
        <div class="menu-grid">
            <a href="{{ route('student.leaves') }}">Izin & sakit <small>Kirim surat izin ke guru</small></a>
            <a href="{{ route('student.tickets') }}">Lapor ke guru <small>Ada masalah di tempat PKL</small></a>
            <a href="{{ route('student.calendar') }}">Kalender <small>Hari yang masih kurang</small></a>
        </div>
        <h3 class="text-sm font-semibold text-muted">Belajar</h3>
        <div class="menu-grid">
            <a href="{{ route('student.learning') }}">Target belajar <small>Apa yang sudah dan belum dikuasai</small></a>
            <a href="{{ route('student.readiness') }}">Pembekalan <small>Materi singkat sebelum berangkat</small></a>
            <a href="{{ route('student.interview') }}">Latihan wawancara <small>Bercerita tentang PKL Anda</small></a>
        </div>
        <h3 class="text-sm font-semibold text-muted">Akhir PKL</h3>
        <div class="menu-grid">
            <a href="{{ route('student.final-report') }}">Laporan akhir <small>Disusun dari jurnal Anda</small></a>
            <a href="{{ route('student.journey') }}">Kilas balik <small>Perjalanan PKL Anda</small></a>
            <a href="{{ route('student.passport') }}">Paspor Kompetensi <small>Bukti keahlian yang sudah diverifikasi</small></a>
            <a href="{{ route('privacy.center') }}">Privasi saya <small>Data dan izin Anda</small></a>
        </div>
    </nav>
</div>
@endsection
