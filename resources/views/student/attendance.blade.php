@extends('layouts.student-pwa', ['title' => 'Presensi - Jurnalin'])

@section('content')
<div>
    <h1 class="text-xl font-bold text-ink">Presensi</h1>
    <p class="hint">Absen masuk saat tiba di tempat PKL, absen pulang setelah jam kerja selesai. Sakit atau izin juga diajukan di sini.</p>
</div>

@if($placement === null)
    <div class="rounded-xl border border-border bg-stone p-6 space-y-2 text-center">
        <h2 class="font-bold text-base text-ink">Penempatan PKL belum aktif</h2>
        <p class="text-sm text-muted">Presensi bisa dicatat setelah koordinator PKL (Hubin) mengaktifkan penempatan Anda.</p>
    </div>
@else
    @include('student.partials.attendance-form')

    <section class="card stack-sm" aria-labelledby="judul-riwayat">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="judul-riwayat" class="section-title">Riwayat presensi</h2>
            <a href="{{ route('student.calendar') }}" class="link text-sm">Kalender PKL</a>
        </div>
        @forelse($history as $row)
            <div class="flex items-center justify-between gap-3 py-2 border-t border-border">
                <span>
                    <span class="block font-semibold text-ink">{{ $row->attendance_date->translatedFormat('l, d M Y') }}</span>
                    <span class="hint tabular-nums">Masuk {{ $row->check_in_at?->timezone($school->timezone)->format('H.i') ?? '--.--' }} · Pulang {{ $row->check_out_at?->timezone($school->timezone)->format('H.i') ?? '--.--' }}</span>
                </span>
                <span @class(['badge shrink-0', 'badge-ok' => $row->status->countsTowardAttendanceScore(), 'badge-warn' => ! $row->status->countsTowardAttendanceScore()])>{{ $row->status->label() }}</span>
            </div>
        @empty
            <p class="hint">Belum ada presensi tercatat. Riwayat muncul di sini setelah absen pertama Anda.</p>
        @endforelse
    </section>
@endif
@endsection
