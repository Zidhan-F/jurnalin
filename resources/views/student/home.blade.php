@extends('layouts.student-pwa', ['title' => 'Beranda - Jurnalin'])

@section('content')
@if($placement === null)
    <div class="rounded-xl border border-border bg-stone p-6 space-y-2 text-center">
        <h1 class="font-bold text-base text-ink">Penempatan PKL belum aktif</h1>
        <p class="text-sm text-muted">Tempat PKL, guru pembimbing, dan jam kerja Anda diatur oleh koordinator PKL (Hubin) sekolah. Setelah penempatan aktif, halaman ini menampilkan tombol Absen Masuk.</p>
        <p class="text-sm text-muted">Sudah tahu tempat PKL Anda tetapi halaman ini masih kosong? Tanyakan ke koordinator Hubin atau guru pembimbing.</p>
    </div>
@else
    @php
        $firstName = \Illuminate\Support\Str::of($student->user->name)->before(' ');
        $isLeaveDay = $todayAttendance !== null && ! $todayAttendance->status->countsTowardAttendanceScore();
        $endTime = substr($placement->work_end_time, 0, 5);
        $tz = $school->timezone;
        $statusBadge = match (true) {
            (bool) $holidayToday => ['Libur', ''],
            $todayAttendance === null => ['Belum absen', 'badge-warn'],
            $isLeaveDay => [$todayAttendance->status->label(), 'badge-warn'],
            default => ['Sudah hadir', 'badge-ok'],
        };
        $nextStep = match (true) {
            (bool) $holidayToday => 'Hari ini libur. Presensi dan jurnal tidak wajib.',
            $todayAttendance === null => 'Ketuk Presensi untuk absen masuk dari tempat PKL.',
            $isLeaveDay => 'Pengajuan '.strtolower($todayAttendance->status->label()).' tercatat. Jurnal dan absen pulang tidak perlu diisi.',
            $todayJournal === null => 'Ketuk Tulis Jurnal dan ceritakan pekerjaan hari ini.',
            $todayJournal->status === \App\Enums\JournalStatus::Draft => 'Draf jurnal sudah tersimpan tetapi belum dikirim. Buka lalu kirim ke guru.',
            $todayJournal->status === \App\Enums\JournalStatus::RevisionNeeded => 'Guru meminta perbaikan jurnal hari ini. Baca catatannya lalu kirim ulang.',
            $todayAttendance->check_out_at === null => 'Setelah jam kerja selesai (pukul '.$endTime.'), buka Presensi lalu catat absen pulang.',
            default => 'Semua langkah hari ini selesai. Sampai jumpa besok.',
        };
        $journalHref = $todayJournal && $todayJournal->status->isEditableByStudent() ? route('student.journals', ['edit' => $todayJournal->id]) : route('student.journals');
        $weeks = \App\Support\PklProgress::weeks($placement->pklPeriod, \Carbon\CarbonImmutable::parse($todayDate));
        $targetDays = max(1, (int) $placement->pklPeriod->min_required_days);
    @endphp

    <section class="student-hero" aria-labelledby="judul-sapaan">
        <div>
            <h1 id="judul-sapaan" class="text-2xl font-bold text-ink">Halo, {{ $firstName }}!</h1>
            <p class="hint mt-1">Selamat menjalankan PKL hari ini. Jangan lupa presensi dan catat kegiatanmu, ya!</p>
        </div>
        <img src="{{ \App\Support\Mascot::forStudent($student->gender) }}" width="96" height="96" alt="" class="student-hero-mascot">
    </section>

    {{-- Blok desktop (poster): empat ubin aksi, cincin progres, dan notifikasi terbaru. Disembunyikan di HP. --}}
    <div class="student-desktop">
        <nav class="desktop-tiles" aria-label="Aksi cepat siswa">
            <a href="{{ route('student.attendance') }}" @class(['is-primary' => $todayAttendance === null])><x-icon name="pin" /><strong>Presensi hari ini</strong><small>{{ $statusBadge[0] }}{{ $todayAttendance?->check_in_at ? ' · '.$todayAttendance->check_in_at->timezone($tz)->format('H.i') : '' }}</small></a>
            <a href="{{ $journalHref }}"><x-icon name="journal" /><strong>Tulis jurnal</strong><small>Catat kegiatan hari ini</small></a>
            <a href="{{ route('student.leaves') }}"><x-icon name="alert" /><strong>Ajukan izin</strong><small>Jika berhalangan hadir</small></a>
            <a href="{{ route('student.report') }}"><x-icon name="award" /><strong>Laporan PKL</strong><small>Lihat progres dan unduh laporan</small></a>
        </nav>
        <div class="grid lg:grid-cols-2 gap-6">
            <section class="card" aria-labelledby="judul-progres-desktop">
                <div class="flex items-baseline justify-between gap-2"><h2 id="judul-progres-desktop" class="section-title">Progres PKL</h2><a href="{{ route('student.report') }}" class="link text-sm">Lihat detail</a></div>
                <div class="flex items-center gap-5 mt-3">
                    <div class="progress-ring" style="--value: {{ $weeks['percent'] }}" role="img" aria-label="Progres {{ $weeks['percent'] }} persen"><span>{{ $weeks['percent'] }}%</span></div>
                    <dl class="grid gap-2 text-sm">
                        <div><dt class="text-muted">Hari kegiatan</dt><dd class="font-semibold tabular-nums">{{ $attendedDays }} dari {{ $targetDays }} hari</dd></div>
                        <div><dt class="text-muted">Jurnal disetujui</dt><dd class="font-semibold tabular-nums">{{ $approvedJournals }} jurnal</dd></div>
                        @if($targets['total'] > 0)<div><dt class="text-muted">Tugas dikumpulkan</dt><dd class="font-semibold tabular-nums">{{ $targets['submitted'] }} dari {{ $targets['total'] }}</dd></div>@endif
                    </dl>
                </div>
            </section>
            <section class="card" aria-labelledby="judul-notif">
                <div class="flex items-baseline justify-between gap-2"><h2 id="judul-notif" class="section-title">Notifikasi terbaru</h2><a href="{{ route('notifications.index') }}" class="link text-sm">Lihat semua</a></div>
                @forelse($notifications as $n)
                    <div class="py-2 border-t border-border mt-2"><span class="block font-semibold text-ink">{{ $n->title }}</span><span class="hint">{{ $n->created_at->diffForHumans() }}</span></div>
                @empty
                    <p class="hint mt-2">Belum ada notifikasi.</p>
                @endforelse
            </section>
        </div>
    </div>

    @if(! $holidayToday && ! $isScheduledToday)
        <p class="callout">Hari ini bukan jadwal kerja PKL. Anda tetap bisa absen bila sedang bertugas.</p>
    @endif

    <section class="card stack-sm mobile-only" aria-labelledby="judul-presensi">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <span class="icon-tile" aria-hidden="true"><x-icon name="calendar" /></span>
                <div class="min-w-0">
                    <h2 id="judul-presensi" class="font-bold text-ink">Presensi Hari Ini</h2>
                    <p class="hint">{{ \Carbon\Carbon::parse($todayDate)->translatedFormat('l, d M Y') }}</p>
                </div>
            </div>
            <span class="badge {{ $statusBadge[1] }} shrink-0">{{ $statusBadge[0] }}</span>
        </div>
        <div class="presence-times">
            <div><x-icon name="check" /> Masuk: <strong class="tabular-nums">{{ $todayAttendance?->check_in_at?->timezone($tz)->format('H.i') ?? '--.--' }}</strong></div>
            <div>Pulang: <strong class="tabular-nums">{{ $todayAttendance?->check_out_at?->timezone($tz)->format('H.i') ?? '--.--' }}</strong></div>
        </div>
        <p class="hint">{{ $placement->company->name }} · jam kerja {{ substr($placement->work_start_time, 0, 5) }} sampai {{ $endTime }}</p>
    </section>

    <div class="student-actions mobile-only">
        <a href="{{ route('student.attendance') }}" class="btn btn-primary"><x-icon name="pin" /> Presensi</a>
        <a href="{{ $journalHref }}" class="btn btn-outline"><x-icon name="journal" /> Tulis Jurnal</a>
    </div>
    <p class="text-sm text-ink" role="status"><strong>Sekarang:</strong> {{ $nextStep }}</p>

    <section class="card stack-sm mobile-only" aria-labelledby="judul-progres">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="judul-progres" class="font-bold text-ink">Progres PKL</h2>
                <p class="hint">Minggu ke-{{ $weeks['current'] }} dari {{ $weeks['total'] }} minggu</p>
            </div>
            <span class="font-mono tabular-nums font-bold text-2xl text-navy">{{ $weeks['percent'] }}%</span>
        </div>
        <div class="onboarding-bar" role="progressbar" aria-label="Progres minggu PKL" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $weeks['percent'] }}"><span style="width: {{ $weeks['percent'] }}%"></span></div>
        <p class="hint tabular-nums">{{ $attendedDays }} dari {{ $targetDays }} hari hadir yang ditargetkan sekolah</p>
    </section>

    @if($revisions->isNotEmpty())
        <a href="{{ route('student.journals', ['edit' => $revisions->first()->id]) }}" class="card card-attention flex items-center justify-between gap-3 hover:bg-stone">
            <span>
                <span class="block font-bold text-ink">Ada jurnal perlu diperbaiki</span>
                <span class="hint">{{ $revisions->count() }} jurnal diminta revisi oleh guru pembimbing. Ketuk untuk memperbaiki{{ $revisions->count() > 1 ? ' yang paling lama' : '' }}.</span>
            </span>
            <span class="link text-sm shrink-0">Perbaiki</span>
        </a>
    @endif

    <section class="card stack-sm" aria-labelledby="judul-aktivitas">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="judul-aktivitas" class="section-title">Aktivitas terbaru</h2>
            @if($recentJournals->isNotEmpty())<a href="{{ route('student.journals') }}" class="link text-sm">Lihat semua</a>@endif
        </div>
        @forelse($recentJournals as $j)
            <a href="{{ route('student.journals') }}#jurnal-{{ $j->id }}" class="flex items-center justify-between gap-3 py-2.5 border-t border-border">
                <span class="min-w-0">
                    <span class="block font-semibold text-ink truncate">{{ $j->activity_title }}</span>
                    <span class="hint tabular-nums">{{ $j->journal_date->translatedFormat('l, d M Y') }}</span>
                </span>
                <span @class(['badge shrink-0', 'badge-ok' => $j->status === \App\Enums\JournalStatus::Approved, 'badge-warn' => $j->status === \App\Enums\JournalStatus::RevisionNeeded])>{{ $j->status->label() }}</span>
            </a>
        @empty
            <p class="hint">Belum ada jurnal yang dikirim. Jurnal yang Anda kirim dan hasil pemeriksaan guru akan muncul di sini.</p>
        @endforelse
    </section>

    @if($mentorNotes->isNotEmpty() || $openTargets->isNotEmpty())
        <section class="card stack-sm">
            <h2 class="section-title">Pesan & target dari pembimbing</h2>
            @foreach($mentorNotes as $note)
                <div class="py-2 border-t border-border"><strong>{{ $note->topic }}</strong><p class="hint">{{ \Illuminate\Support\Str::limit($note->note, 120) }}</p></div>
            @endforeach
            @foreach($openTargets as $target)
                <div class="py-2 border-t border-border flex justify-between gap-2"><span>{{ $target->title }}</span>@if($target->due_date)<span class="hint tabular-nums">s.d. {{ $target->due_date->format('d/m') }}</span>@endif</div>
            @endforeach
            <a href="{{ route('student.learning') }}" class="link text-sm">Buka halaman Belajar</a>
        </section>
    @endif
@endif

<a href="{{ route('student.tickets') }}" class="card flex items-center justify-between gap-3 hover:bg-stone">
    <span><span class="block font-semibold text-ink">Lapor ke guru</span><span class="hint">Bantuan, kendala di tempat PKL, dan jalur darurat.</span></span>
    <span class="link text-sm shrink-0">Buka</span>
</a>
@endsection
