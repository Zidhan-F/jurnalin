{{-- Formulir absen masuk/pulang dan sakit/izin. Dipakai di halaman Presensi; perilakunya sama dengan versi beranda lama. --}}
@php
    $company = $placement->company;
    $radius = $placement->attendanceRadius();
    $point = $placement->attendancePoint();
    $useLocation = $placement->pklPeriod->require_location && $trackingAllowed;
    $selfieRequired = $trackingAllowed && $placement->pklPeriod->selfieRequired();
@endphp
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
            gpsMessage: 'Menyiapkan lokasi...',
            useLocation: {{ $useLocation ? 'true' : 'false' }},
            needReason: false,
            sending: false,
            waiters: [],
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
                        this.waiters.splice(0).forEach((w) => w());
                    },
                    () => {
                        this.gpsStatus = 'error';
                        this.gpsMessage = 'Izin lokasi belum aktif atau sinyal GPS gagal. Ketuk ikon gembok di bilah alamat browser, izinkan Lokasi, lalu ketuk Perbarui lokasi.';
                        this.waiters.splice(0).forEach((w) => w());
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
            waitForGps() {
                if (this.gpsStatus === 'ready' || this.gpsStatus === 'error') return Promise.resolve();
                if (this.gpsStatus !== 'loading') this.locate();
                return new Promise((resolve) => this.waiters.push(resolve));
            },
            camOpen: false,
            camStream: null,
            camError: '',
            shot: '',
            async openCamera() {
                this.camError = '';
                this.shot = '';
                this.photoDataUrl = '';
                this.camOpen = true;
                if (! navigator.mediaDevices?.getUserMedia) { this.$refs.camera.click(); return; }
                try {
                    this.camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 960 } }, audio: false });
                    this.$nextTick(() => { this.$refs.video.srcObject = this.camStream; this.$refs.video.play().catch(() => {}); });
                } catch (e) {
                    this.camError = 'Kamera belum diizinkan. Ketuk ikon gembok di bilah alamat, izinkan Kamera, lalu ketuk Buka kamera lagi.';
                }
            },
            stopCamera() {
                this.camStream?.getTracks().forEach((t) => t.stop());
                this.camStream = null;
            },
            async capture() {
                const v = this.$refs.video;
                if (! v.videoWidth) return;
                const c = document.createElement('canvas');
                c.width = v.videoWidth;
                c.height = v.videoHeight;
                const ctx = c.getContext('2d');
                ctx.translate(c.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(v, 0, 0, c.width, c.height);
                const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.92));
                this.stopCamera();
                await this.useFile(new File([blob], 'swafoto.jpg', { type: 'image/jpeg' }));
            },
            async fromFile(e) {
                const file = e.target.files?.[0];
                e.target.value = '';
                if (file) await this.useFile(file);
            },
            async useFile(file) {
                this.compressing = true;
                this.photoInfo = 'Mengompresi swafoto...';
                try {
                    this.shot = await window.jurnalinCompressSelfie(file);
                    this.photoInfo = `Swafoto siap (${Math.round((this.shot.length * 3) / 4 / 1024)} KB). Periksa wajah terlihat jelas, lalu kirim.`;
                } catch (err) {
                    this.photoInfo = err.message || 'Gagal memproses swafoto. Coba ambil ulang.';
                } finally {
                    this.compressing = false;
                }
            },
            retake() {
                this.openCamera();
            },
            cancelCamera() {
                this.stopCamera();
                this.camOpen = false;
                this.shot = '';
                this.photoDataUrl = '';
                this.photoInfo = '';
            },
            confirmShot() {
                this.photoDataUrl = this.shot;
                this.submitPresent();
            },
            async submitPresent() {
                if (this.useLocation) {
                    await this.waitForGps();
                    if (this.gpsStatus !== 'ready') return;
                    if (! this.withinRadius && ! (this.outsideDuty && this.$refs.reason?.value.trim())) {
                        this.needReason = true;
                        this.outsideDuty = true;
                        this.$nextTick(() => this.$refs.reason?.focus());
                        return;
                    }
                }
                this.sending = true;
                this.$nextTick(() => this.$refs.checkinForm.requestSubmit(this.$refs.presentSubmit));
            },
            simulate(offsetMeters) {
                const offsetLat = offsetMeters / 111320;
                this.lat = (this.companyLat + offsetLat).toFixed(7);
                this.lng = this.companyLng.toFixed(7);
                this.accuracy = 12;
                this.recalc();
            }
        }"
        x-init="companyName = $el.dataset.companyName; recalc(); if (useLocation && @js($todayAttendance === null || $todayAttendance->check_out_at === null) && ! lat) locate()"
        @pagehide.window="stopCamera()"
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

            <form method="POST" action="/student/check-in" enctype="multipart/form-data" class="space-y-4" x-ref="checkinForm">
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
                            <button type="button" @click="locate()" x-show="gpsStatus !== 'loading'" class="px-3.5 py-2 rounded-lg border border-navy text-navy bg-paper text-xs font-semibold min-h-11 cursor-pointer">
                                Perbarui lokasi
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

                    <div x-show="gpsStatus === 'ready' && !withinRadius" class="rounded-lg border border-amber-warn/40 bg-paper p-3.5 space-y-2" :class="needReason ? 'card-attention' : ''">
                        <label class="flex items-start gap-2 text-xs font-semibold text-amber-strong">
                            <input type="checkbox" :disabled="withinRadius || gpsStatus !== 'ready'" name="is_outside_duty" value="1" x-model="outsideDuty" class="mt-0.5">
                            <span>Saya sedang Tugas Luar Area (misal: kunjungan klien, instalasi di lokasi pelanggan, event luar)</span>
                        </label>
                        <div x-show="outsideDuty">
                            <label for="outside-reason" class="block text-sm font-semibold">Lokasi dan tugas di luar area</label>
                            <textarea id="outside-reason" x-ref="reason" :disabled="withinRadius || !outsideDuty" name="exception_reason" rows="2" placeholder="Tuliskan lokasi dan tugas luar dari pembimbing industri..."
                                class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>

                    <button type="submit" name="mode" value="present" x-ref="presentSubmit" class="sr-only" tabindex="-1" aria-hidden="true">Kirim presensi</button>

                    @if($trackingAllowed)
                        <input id="arrival-photo" x-ref="camera" type="file" accept="image/*" capture="user" class="sr-only" tabindex="-1" @change="fromFile($event)">
                        <button type="button" x-show="! camOpen" @click="openCamera()" :disabled="sending"
                            class="w-full py-3.5 px-5 rounded-lg bg-emerald-check hover:bg-emerald-strong disabled:opacity-60 text-white font-bold text-base min-h-12 cursor-pointer inline-flex items-center justify-center gap-2">
                            <x-icon name="camera" /> Absen Masuk: Buka Kamera
                        </button>

                        <div x-show="camOpen" x-cloak class="selfie-panel" role="group" aria-label="Swafoto presensi" @keydown.escape="cancelCamera()">
                            <div class="selfie-frame">
                                <video x-ref="video" x-show="! shot && camStream" autoplay playsinline muted class="selfie-live" aria-label="Pratinjau kamera depan"></video>
                                <img x-show="shot" :src="shot" alt="Swafoto yang akan dikirim" class="selfie-shot">
                                <div x-show="! shot && ! camStream" class="selfie-empty">
                                    <p x-text="camError || 'Membuka kamera depan...'"></p>
                                    <button type="button" x-show="camError" @click="openCamera()" class="btn btn-outline btn-sm">Buka kamera lagi</button>
                                    <button type="button" x-show="camError" @click="$refs.camera.click()" class="link text-sm">Pakai aplikasi kamera HP</button>
                                </div>
                                <span x-show="! shot && camStream" class="selfie-guide" aria-hidden="true"></span>
                            </div>
                            <p role="status" class="hint" x-text="photoInfo || (camStream ? 'Posisikan wajah di dalam bingkai, lalu ketuk Ambil foto.' : '')"></p>
                            <div class="grid grid-cols-2 gap-2" x-show="! shot">
                                <button type="button" @click="cancelCamera()" class="btn btn-outline">Batal</button>
                                <button type="button" @click="capture()" :disabled="! camStream || compressing" class="btn btn-primary"><x-icon name="camera" /> Ambil foto</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2" x-show="shot">
                                <button type="button" @click="retake()" :disabled="sending" class="btn btn-outline">Ulangi</button>
                                <button type="button" @click="confirmShot()" :disabled="sending || compressing" class="btn btn-primary"
                                    x-text="sending ? 'Mengirim...' : (needReason ? 'Kirim dengan alasan' : 'Kirim absen')">Kirim absen</button>
                            </div>
                        </div>
                        <p class="hint">Swafoto diambil dari kamera depan, dikompres di HP (di bawah 150 KB), dan dikirim bersama lokasi. Dihapus otomatis setelah {{ $school->selfie_retention_days }} hari dan tidak dipakai untuk pengenalan wajah.</p>
                    @else
                        <button type="button" @click="submitPresent()" :disabled="sending"
                            class="w-full py-3.5 px-5 rounded-lg bg-emerald-check hover:bg-emerald-strong disabled:opacity-60 text-white font-bold text-base min-h-12 cursor-pointer">
                            Absen Masuk Sekarang
                        </button>
                    @endif
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
