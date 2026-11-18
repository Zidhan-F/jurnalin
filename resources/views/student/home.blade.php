@extends('layouts.student-pwa', ['title' => 'Absen & Hari Ini - Jurnalin'])

@section('content')
@if($placement === null)
    <div class="rounded-xl border border-border bg-stone p-6 space-y-2 text-center">
        <h1 class="font-bold text-base text-ink">Penempatan PKL belum aktif</h1>
        <p class="text-sm text-muted">Tempat PKL, guru pembimbing, dan jam kerja Anda diatur oleh koordinator PKL (Hubin) sekolah. Setelah penempatan aktif, halaman ini menampilkan tombol Absen Masuk.</p>
        <p class="text-sm text-muted">Sudah tahu tempat PKL Anda tetapi halaman ini masih kosong? Tanyakan ke koordinator Hubin atau guru pembimbing.</p>
    </div>
@else
    @php
        $company = $placement->company;
        $radius = $placement->attendanceRadius();
        $point = $placement->attendancePoint();
        $useLocation = $placement->pklPeriod->require_location && $trackingAllowed;
        $selfieRequired = $trackingAllowed && $placement->pklPeriod->selfieRequired();
        $firstName = \Illuminate\Support\Str::of($student->user->name)->before(' ');
    @endphp

    <div>
        <h1 class="text-xl font-bold text-ink">Halo, {{ $firstName }}!</h1>
        <p class="hint tabular-nums">{{ \Carbon\Carbon::parse($todayDate)->translatedFormat('l, d M Y') }} · Jangan lupa absen dan catat kegiatanmu hari ini.</p>
    </div>

    @if($holidayToday)
        <p class="callout">Hari ini libur: {{ $holidayToday['label'] ?? 'hari libur periode' }}. Presensi dan jurnal tidak wajib.</p>
    @elseif(! $isScheduledToday)
        <p class="callout">Hari ini bukan jadwal kerja PKL. Anda tetap bisa absen bila sedang bertugas.</p>
    @endif

    @php
        // Urutan kerja harian siswa: absen masuk, tulis jurnal, absen pulang.
        $isLeaveDay = $todayAttendance !== null && ! $todayAttendance->status->countsTowardAttendanceScore();
        $journalSent = $todayJournal !== null && ! $todayJournal->status->isEditableByStudent();
        $endTime = substr($placement->work_end_time, 0, 5);
        $daySteps = [
            [
                'label' => 'Absen masuk',
                'done' => $todayAttendance !== null,
                'next' => $useLocation ? 'Ketuk Ambil Lokasi GPS, lalu Absen Masuk Sekarang.' : 'Ketuk Absen Masuk Sekarang di bawah.',
            ],
            [
                'label' => 'Tulis jurnal',
                'done' => $journalSent,
                'next' => match ($todayJournal?->status) {
                    \App\Enums\JournalStatus::Draft => 'Draf jurnal sudah tersimpan tetapi belum dikirim ke guru. Buka lalu kirim.',
                    \App\Enums\JournalStatus::RevisionNeeded => 'Guru meminta perbaikan jurnal hari ini. Baca catatannya lalu kirim ulang.',
                    default => 'Ceritakan pekerjaan dan hal yang Anda pelajari hari ini, lalu kirim ke guru.',
                },
            ],
            [
                'label' => 'Absen pulang',
                'done' => $todayAttendance?->check_out_at !== null,
                'next' => 'Setelah jam kerja selesai (pukul '.$endTime.'), ketuk Catat Absen Pulang.',
            ],
        ];
        $currentStep = collect($daySteps)->search(fn ($s) => ! $s['done']);
    @endphp

    @if(! $holidayToday)
        @if($isLeaveDay)
            <p class="callout callout-warn">Hari ini tercatat <strong>{{ $todayAttendance->status->label() }}</strong>. Jurnal dan absen pulang tidak perlu diisi. Guru pembimbing akan memeriksa pengajuan Anda.</p>
        @else
            <section class="card stack-sm" aria-labelledby="judul-langkah">
                <h2 id="judul-langkah" class="text-sm font-semibold text-muted">Langkah PKL hari ini</h2>
                <ol class="day-steps">
                    @foreach($daySteps as $i => $step)
                        <li @class(['is-done' => $step['done'], 'is-current' => $i === $currentStep])>
                            <span class="onboarding-mark" aria-hidden="true">@if($step['done'])<x-icon name="check" />@else{{ $i + 1 }}@endif</span>
                            <span>{{ $step['label'] }}</span>
                            <span class="sr-only">{{ $step['done'] ? '(selesai)' : ($i === $currentStep ? '(langkah sekarang)' : '(belum)') }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="text-sm text-ink" role="status">
                    @if($currentStep === false)
                        <strong>Semua langkah hari ini selesai.</strong> Sampai jumpa besok.
                    @else
                        <strong>Sekarang:</strong> {{ $daySteps[$currentStep]['next'] }}
                    @endif
                </p>
            </section>
        @endif
    @endif

    <section id="absen"
        data-company-name="{{ $company->name }}"
        x-data="{
            mode: 'present',
            leaveType: 'sick',
            lat: @js((string) old('latitude', '')),
            lng: @js((string) old('longitude', '')),
            accuracy: @js((string) old('accuracy', '')),
            distance: null,
            withinRadius: false,
            outsideDuty: {{ old('is_outside_duty') ? 'true' : 'false' }},
            gpsStatus: 'idle',
            gpsMessage: 'Ketuk tombol Ambil Lokasi GPS untuk memeriksa jarak ke tempat PKL.',
            compressing: false,
            photoDataUrl: '',
            photoInfo: '',
            companyName: '',
            companyLat: {{ $point->latitude }},
            companyLng: {{ $point->longitude }},
            deviceId: (() => { try { let d = localStorage.getItem('jurnalin-device'); if (! d) { d = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random()); localStorage.setItem('jurnalin-device', d); } return d; } catch (e) { return ''; } })(),
            allowedRadius: {{ $radius }},
            locate() {
                if (!navigator.geolocation) {
                    this.gpsStatus = 'error';
                    this.gpsMessage = 'Perangkat atau browser ini tidak mendukung GPS.';
                    return;
                }
                this.gpsStatus = 'loading';
                this.gpsMessage = 'Mengambil koordinat GPS perangkat...';
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.lat = pos.coords.latitude.toFixed(7);
                        this.lng = pos.coords.longitude.toFixed(7);
                        this.accuracy = Math.round(pos.coords.accuracy || 0);
                        this.recalc();
                    },
                    () => {
                        this.gpsStatus = 'error';
                        this.gpsMessage = 'Izin lokasi belum aktif atau sinyal GPS gagal. Ketuk ikon gembok di bilah alamat browser, izinkan Lokasi, lalu ketuk ulang Ambil Lokasi GPS.';
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            },
            recalc() {
                if (!this.lat || !this.lng || !window.jurnalinHaversineMeters) return;
                this.distance = window.jurnalinHaversineMeters(parseFloat(this.lat), parseFloat(this.lng), this.companyLat, this.companyLng);
                this.withinRadius = this.distance <= this.allowedRadius;
                this.gpsStatus = 'ready';
                this.gpsMessage = this.withinRadius
                    ? `Berjarak ${this.distance} m dari ${this.companyName} (dalam radius ${this.allowedRadius} m).`
                    : `Berjarak ${this.distance} m dari ${this.companyName} (di luar batas ${this.allowedRadius} m).`;
            },
            simulate(offsetMeters) {
                const offsetLat = offsetMeters / 111320;
                this.lat = (this.companyLat + offsetLat).toFixed(7);
                this.lng = this.companyLng.toFixed(7);
                this.accuracy = 12;
                this.recalc();
            },
            async handlePhoto(e) {
                const file = e.target.files?.[0];
                if (!file) return;
                this.compressing = true;
                this.photoInfo = 'Mengompresi foto sebelum unggah...';
                try {
                    this.photoDataUrl = await window.jurnalinCompressImage(file, 1280, 0.75);
                    const kb = Math.round((this.photoDataUrl.length * 3) / 4 / 1024);
                    this.photoInfo = `Foto siap dikirim (${kb} KB).`;
                } catch (err) {
                    this.photoInfo = err.message || 'Gagal memproses foto.';
                } finally {
                    this.compressing = false;
                }
            }
        }"
        x-init="companyName = $el.dataset.companyName; recalc()"
        class="rounded-xl border border-border bg-stone p-5 space-y-4"
    >
        <div class="border-b border-border pb-3">
            <div class="text-sm font-semibold text-muted">Tempat PKL hari ini</div>
            <h2 class="text-lg font-bold text-ink mt-0.5">{{ $company->name }}</h2>
            <div class="text-xs text-muted mt-1 tabular-nums">
                @if($placement->department_divisi){{ $placement->department_divisi }} · @endif
                Jam kerja {{ substr($placement->work_start_time, 0, 5) }} sampai {{ substr($placement->work_end_time, 0, 5) }}
                @if($useLocation) · Batas jarak absen {{ $radius }} m @endif
            </div>
        </div>

        @if($todayAttendance === null)
            <div role="group" aria-label="Jenis kehadiran hari ini" class="flex rounded-lg bg-paper border border-field p-1 gap-1 text-xs font-semibold">
                <button type="button" @click="mode = 'present'" :aria-pressed="mode === 'present'" :class="mode === 'present' ? 'bg-navy text-white' : 'text-muted'" class="flex-1 py-2.5 rounded-md min-h-11 cursor-pointer">
                    Hadir di Lokasi
                </button>
                <button type="button" @click="mode = 'leave'" :aria-pressed="mode === 'leave'" :class="mode === 'leave' ? 'bg-navy text-white' : 'text-muted'" class="flex-1 py-2.5 rounded-md min-h-11 cursor-pointer">
                    Sakit / Izin
                </button>
            </div>

            <form method="POST" action="/student/check-in" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <input type="hidden" name="device_id" :value="deviceId">
                <fieldset :disabled="mode !== 'present'" x-show="mode === 'present'" class="space-y-4">
                    @if(! $useLocation)
                        <p class="callout">
                            @if(! $trackingAllowed)
                                Lokasi dan swafoto tidak diambil karena persetujuan orang tua/wali belum tercatat. Presensi cukup dengan tombol di bawah.
                            @else
                                Sekolah tidak mewajibkan lokasi untuk periode ini. Presensi cukup dengan tombol di bawah.
                            @endif
                        </p>
                    @endif
                    @if($useLocation)
                    <div class="rounded-lg border p-3.5 text-sm space-y-2"
                        :class="gpsStatus === 'error' ? 'border-danger/40 bg-danger/10 text-danger-ink' : (gpsStatus === 'ready' && withinRadius ? 'border-emerald-check/40 bg-emerald-check/10 text-emerald-ink' : 'border-border bg-paper text-ink')">
                        <div class="font-semibold" role="status" x-text="gpsMessage"></div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="locate()" class="px-3.5 py-2 rounded-lg bg-navy text-white text-xs font-semibold min-h-11 cursor-pointer">
                                Ambil Lokasi GPS
                            </button>
                            @if(app()->environment('local', 'testing'))
                                <button type="button" @click="simulate(35)" class="px-3 py-2 rounded-lg border border-emerald-check text-emerald-check bg-white text-xs font-semibold min-h-11 cursor-pointer">
                                    [Simulasi Demo] 35 m
                                </button>
                                <button type="button" @click="simulate(450)" class="px-3 py-2 rounded-lg border border-amber-warn text-amber-strong bg-white text-xs font-semibold min-h-11 cursor-pointer">
                                    [Simulasi Demo] 450 m
                                </button>
                            @endif
                        </div>
                        <div x-show="lat && lng" class="text-xs text-muted tabular-nums">
                            GPS: <span x-text="lat"></span>, <span x-text="lng"></span>
                        </div>
                    </div>

                    @endif
                    <input type="hidden" name="latitude" :value="lat">
                    <input type="hidden" name="longitude" :value="lng">
                    <input type="hidden" name="accuracy" :value="accuracy">
                    <input type="hidden" name="photo_data_url" :value="photoDataUrl">

                    <div x-show="gpsStatus === 'ready' && !withinRadius" class="rounded-lg border border-amber-warn/40 bg-paper p-3.5 space-y-2">
                        <label class="flex items-start gap-2 text-xs font-semibold text-amber-strong">
                            <input type="checkbox" :disabled="withinRadius || gpsStatus !== 'ready'" name="is_outside_duty" value="1" x-model="outsideDuty" class="mt-0.5">
                            <span>Saya sedang Tugas Luar Area (misal: kunjungan klien, instalasi di lokasi pelanggan, event luar)</span>
                        </label>
                        <div x-show="outsideDuty">
                            <label for="outside-reason" class="block text-sm font-semibold">Lokasi dan tugas di luar area</label>
                            <textarea id="outside-reason" :disabled="withinRadius || !outsideDuty" name="exception_reason" rows="2" placeholder="Tuliskan lokasi dan tugas luar dari pembimbing industri..."
                                class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>

                    @if($trackingAllowed)
                    <div class="space-y-2">
                        <label for="arrival-photo" class="block text-sm font-semibold text-ink">
                            Swafoto kehadiran dari kamera {{ $selfieRequired ? '(wajib)' : '(opsional)' }}
                        </label>
                        <p class="hint">Diambil langsung dari kamera depan. Tidak dipakai untuk pengenalan wajah.</p>
                        <input id="arrival-photo" type="file" name="photo" accept="image/*" capture="user" @change="handlePhoto($event)"
                            class="block w-full text-xs text-muted file:mr-3 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-navy file:text-white file:font-semibold">
                        <div role="status" class="text-xs text-muted" x-text="photoInfo"></div>
                        <img x-show="photoDataUrl" :src="photoDataUrl" alt="Pratinjau foto absen" class="h-32 rounded-lg border border-border object-cover">
                    </div>
                    @endif

                    <button type="submit" name="mode" value="present" :disabled="compressing"
                        class="w-full py-3.5 px-5 rounded-lg bg-emerald-check hover:bg-emerald-strong disabled:opacity-60 text-white font-bold text-base min-h-12 cursor-pointer">
                        Absen Masuk Sekarang
                    </button>
                </fieldset>

                <fieldset :disabled="mode !== 'leave'" x-show="mode === 'leave'" x-cloak class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="leave-type">Jenis Ketidakhadiran</label>
                        <select id="leave-type" x-model="leaveType" :data-selected="leaveType"
                            class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
                            <option value="sick">Sakit</option>
                            <option value="permission">Izin Resmi / Keperluan Keluarga</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="leave-reason">Keterangan / Alasan (Wajib)</label>
                        <textarea id="leave-reason" required name="exception_reason" rows="2" placeholder="Jelaskan alasan sakit atau izin..."
                            class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="leave-photo">Foto Surat Dokter / Izin (Opsional)</label>
                        <input id="leave-photo" type="file" name="photo" accept="image/*" class="block w-full text-xs text-muted">
                    </div>
                    <button type="submit" name="mode" :value="leaveType"
                        class="w-full py-3.5 px-5 rounded-lg bg-amber-warn hover:bg-amber-strong text-white font-bold text-base min-h-12 cursor-pointer">
                        Kirim Pengajuan Sakit / Izin
                    </button>
                    <a href="{{ route('student.leaves') }}" class="link text-sm">Izin lebih dari satu hari? Ajukan di halaman Izin & sakit</a>
                </fieldset>
            </form>
        @else
            <div class="rounded-lg border border-emerald-check/30 bg-emerald-check/10 p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-emerald-ink">Absen Hari Ini Tercatat: {{ $todayAttendance->status->label() }}</span>
                    @if($todayAttendance->check_in_at)
                        <span class="text-xs tabular-nums text-emerald-ink">{{ $todayAttendance->check_in_at->timezone($school->timezone)->format('H:i') }}</span>
                    @endif
                </div>
                @if($todayAttendance->isFlagged())
                    <div class="text-xs text-emerald-ink">Tercatat dengan tanda: {{ implode(', ', $todayAttendance->flagLabels()) }}. Guru pembimbing akan meninjau; tanda bukan sanksi.</div>
                @endif
                @if($todayAttendance->check_in_distance_m !== null)
                    <div class="text-xs text-emerald-ink tabular-nums">
                        Jarak tercatat: {{ $todayAttendance->check_in_distance_m }} m
                        @if(! $todayAttendance->is_within_radius)
                            (Di luar radius - {{ $todayAttendance->approved_by_teacher_id ? 'Disetujui Guru' : 'Menunggu Persetujuan Guru' }})
                        @endif
                    </div>
                @endif
            </div>

            @if($todayAttendance->status->countsTowardAttendanceScore() && $todayAttendance->check_out_at === null)
                <form method="POST" action="/student/check-out" class="space-y-3 pt-1">
                    @csrf
                    @if($useLocation)
                    <div class="rounded-lg border border-border bg-paper p-3 space-y-2 text-xs">
                        <div class="font-semibold text-ink" role="status" x-text="gpsMessage"></div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="locate()" class="px-3 py-2 rounded-lg bg-navy text-white font-semibold cursor-pointer">
                                Ambil GPS Pulang
                            </button>
                            @if(app()->environment('local', 'testing'))
                                <button type="button" @click="simulate(35)" class="px-3 py-2 rounded-lg border border-emerald-check text-emerald-check bg-white font-semibold cursor-pointer">
                                    [Simulasi Demo] 35 m
                                </button>
                            @endif
                        </div>
                    </div>
                    @endif
                    <input type="hidden" name="latitude" :value="lat">
                    <input type="hidden" name="longitude" :value="lng">
                    <button type="submit" class="w-full py-3.5 px-5 rounded-lg bg-navy hover:bg-navy-strong text-white font-bold text-sm min-h-12 cursor-pointer">
                        Catat Absen Pulang
                    </button>
                </form>
            @elseif($todayAttendance->check_out_at !== null)
                <div class="text-xs text-muted tabular-nums">
                    Absen pulang tersimpan pukul {{ $todayAttendance->check_out_at->timezone($school->timezone)->format('H:i') }}.
                </div>
            @endif
        @endif
    </section>

    @php $targetDays = max(1, (int) $placement->pklPeriod->min_required_days); $progress = min(100, (int) round($attendedDays / $targetDays * 100)); @endphp
    <section class="card stack-sm" aria-labelledby="judul-progres">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="judul-progres" class="section-title">Progres PKL</h2>
            <span class="font-mono tabular-nums font-bold text-lg text-navy">{{ $progress }}%</span>
        </div>
        <p class="hint tabular-nums">{{ $attendedDays }} dari {{ $targetDays }} hari hadir yang ditargetkan sekolah</p>
        <div class="onboarding-bar" role="progressbar" aria-label="Progres hari hadir PKL" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><span style="width: {{ $progress }}%"></span></div>
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

    <section id="jurnal-hari-ini" class="rounded-xl border border-border bg-paper p-5 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-base text-ink">Jurnal hari ini</h2>
            @if($todayJournal)
                <span class="px-2 py-0.5 rounded-md text-xs font-semibold bg-stone text-ink">{{ $todayJournal->status->label() }}</span>
            @endif
        </div>
        @if($todayJournal)
            <p class="text-sm font-medium text-ink">{{ $todayJournal->activity_title }}</p>
            <p class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($todayJournal->activity_description, 120) }}</p>
            <a href="{{ $todayJournal->status->isEditableByStudent() ? route('student.journals', ['edit' => $todayJournal->id]) : route('student.journals').'#jurnal-'.$todayJournal->id }}" class="inline-flex items-center justify-center w-full py-2.5 rounded-lg border border-navy text-navy text-sm font-semibold min-h-12">
                {{ match ($todayJournal->status) {
                    \App\Enums\JournalStatus::Draft => 'Lanjutkan draf jurnal',
                    \App\Enums\JournalStatus::RevisionNeeded => 'Perbaiki jurnal',
                    default => 'Lihat jurnal hari ini',
                } }}
            </a>
        @else
            <p class="text-sm text-muted">Catat pekerjaan yang Anda lakukan dan hal yang dipelajari selama PKL hari ini.</p>
            <a href="/student/journals" class="inline-flex items-center justify-center w-full py-3 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white text-sm font-semibold min-h-12">
                Tulis Jurnal Kegiatan Hari Ini
            </a>
        @endif
    </section>


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

<nav aria-label="Menu cepat siswa" class="menu-grid">
    <a href="{{ route('student.calendar') }}">Kalender PKL <small>Hari tercatat, izin, libur</small></a>
    <a href="{{ route('student.learning') }}">Belajar <small>Lihat target dan kumpulkan bukti kerja</small></a>
    <a href="{{ route('student.tickets') }}">Lapor ke Guru <small>Bantuan & jalur darurat</small></a>
</nav>
@endsection
