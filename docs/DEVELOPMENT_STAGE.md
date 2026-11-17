# Development Stage & Roadmap - Jurnalin

Rencana eksekusi ini dirancang agar dapat dikerjakan secara realistis oleh satu orang pengembang (*solo full-stack developer*) dalam waktu 6 minggu hingga siap diujicobakan (*pilot*) di sekolah pertama.

---

## Ringkasan Tahapan

| Tahap | Fokus Utama | Target Waktu | Kriteria Lulus Tahap (*Exit Gate*) |
|---|---|---|---|
| **Stage 0** | Validasi Lapangan & Artefak Sekolah | Minggu 1 (Tanpa Coding) | Terkumpul format asli buku jurnal, form nilai hotel, rubrik Kurikulum Merdeka, dan contoh file Excel siswa dari 2 SMK & 1 HRD Hotel |
| **Stage 1** | Fondasi Laravel 12, PostgreSQL RLS & Auth Sekolah | Minggu 2 | Migrasi 10 tabel berjalan, `TenantIsolationTest` lulus 100%, alur daftar sekolah NPSN & approval Super Admin berfungsi |
| **Stage 2** | Panel Admin Sekolah: Impor Excel & Setup Penempatan | Minggu 3 | Admin bisa mengimpor 1 kelas siswa + guru dari `.xlsx`, memetakan koordinat hotel via Leaflet, dan menugaskan penempatan aktif |
| **Stage 3** | Aplikasi Harian Siswa (Mobile PWA Livewire) | Minggu 4 | Siswa bisa login dari HP Android, cek radius Haversine real-time, kompres foto kamera ke WebP, absen masuk/pulang, dan kirim jurnal |
| **Stage 4** | Dashboard Guru, Penilaian Akhir & Generator PDF | Minggu 5 | Guru melihat siswa belum absen hari ini + tombol WA, validasi/revisi jurnal, input nilai + foto bukti hotel, dan unduh PDF ber-kop sekolah |
| **Stage 5** | Deployment Produksi & Pilot di 1-2 SMK | Minggu 6 | Aplikasi live di VPS dengan SSL + S3/R2 + backup harian; 1 rombel siswa Perhotelan mulai memakai sistem nyata |
| **Stage 6** | Ekspansi V2: Magic Link Hotel & Talent Pool | Pasca-Pilot (V2) | Hanya dikerjakan setelah sekolah pilot menyelesaikan minimal 1 siklus penilaian dan meminta akses supervisor hotel |

---

## Rincian Pekerjaan Per Tahap

### Stage 0: Validasi Masalah & Pengumpulan Artefak (Minggu 1)
**Jangan menulis satu baris kode pun di minggu ini.**
1. Wawancarai 2 Guru Pembimbing / Koordinator Hubin SMK Perhotelan dan 1 HRD / Supervisor Hotel yang rutin menerima anak PKL.
2. Minta foto atau salinan dokumen asli yang mereka pakai sekarang:
   - Halaman absensi dan halaman catatan harian di buku jurnal PKL cetak.
   - Lembar penilaian kertas yang biasa diisi supervisor hotel (perhatikan skala nilai: apakah 0-100 atau A/B/C/D, serta aspek apa saja yang dinilai).
   - Format file Excel ekspor siswa dari Dapodik atau bagian Kurikulum (untuk memastikan kolom template impor Jurnalin sesuai kebiasaan admin sekolah).
3. Verifikasi desain `DESIGN.md` dan penyesuaian item JSONB rubrik berdasarkan dokumen nyata yang diperoleh.

### Stage 1: Fondasi Proyek, PostgreSQL 16 RLS & Tenant Onboarding (Minggu 2)
1. Inisialisasi proyek Laravel 12 di `c:\Users\Zidhan\Downloads\jurnalin` dengan PostgreSQL 16 (bukan MySQL atau SQLite).
2. Buat role PostgreSQL non-superuser (`jurnalin_app`) tanpa `BYPASSRLS` untuk koneksi aplikasi.
3. Buat migrasi untuk seluruh 10 tabel + skrip DDL `ENABLE / FORCE ROW LEVEL SECURITY` sesuai `docs/DATABASE_SCHEMA.md`.
4. Implementasikan:
   - `App\Support\TenantContext`
   - `App\Http\Middleware\SetTenantContextMiddleware`
   - `App\Models\Concerns\BelongsToSchool`
   - Custom authentication lookup yang aman terhadap RLS `users`.
5. Pasang Filament v3 dengan 2 panel terpisah (`SuperAdminPanelProvider` di `/super-admin` dan `SchoolPanelProvider` di `/app`).
6. Buat halaman publik pendaftaran sekolah mandiri (`/daftar-sekolah`) dengan validasi Value Object `Npsn`.
7. **Wajib**: Tulis `tests/Feature/TenantIsolationTest.php` (menguji isolasi lintas tenant di level SQL/Eloquent maupun HTTP) dan pastikan seluruh tes lulus sebelum lanjut ke Stage 2.

### Stage 2: Onboarding Data Sekolah & Penempatan (Minggu 3)
1. Implementasi CRUD `PklPeriodResource` lengkap dengan editor visual `assessment_template` (validasi total bobot wajib `100%`).
2. Implementasi CRUD `CompanyResource` (tempat PKL) dilengkapi komponen pemilih titik koordinat peta interaktif menggunakan **Leaflet.js + OpenStreetMap** dan input radius geofence khusus.
3. Implementasi fitur **Impor Excel** (`ImportStudentsAction` & `ImportTeachersAction`) berbasis `openspout/openspout` beserta tombol unduh template `.xlsx` contoh.
4. Implementasi `PlacementResource` (penempatan siswa ke perusahaan dan guru pembimbing) dengan validasi pencegahan penempatan ganda aktif di periode yang sama.
5. Implementasi halaman Pengaturan Profil Sekolah (unggah logo, kop surat PDF, jam toleransi terlambat, dan aturan foto absen).

### Stage 3: Portal Mobile Siswa - Livewire PWA (Minggu 4)
1. Bangun antarmuka mobile-first di rute `/student` mengikuti `DESIGN.md` (`#FAF8F5` warm paper, satu titik fokus utama, tap target tombol minimal `48px`).
2. Implementasi komponen Alpine.js untuk:
   - Permintaan izin Geolocation API (`enableHighAccuracy: true`) lengkap dengan pesan error pemulihan langkah demi langkah jika izin GPS diblokir (R-27).
   - Kalkulasi estimasi jarak di sisi klien.
   - Penangkapan foto kamera (`capture="user"` / `capture="environment"`) + kompresi HTML5 Canvas ke maksimal lebar `1280px` sebelum diunggah.
3. Implementasi `RecordCheckInAction` dan `RecordCheckOutAction` dengan perhitungan ulang jarak Haversine di server (`Coordinates::distanceMetersTo()`), pencatatan status `present` / `late`, serta dukungan opsi `Tugas Luar Area` (`is_within_radius = false` + `exception_reason` wajib).
4. Implementasi `SubmitJournalAction` (simpan `draft`, kirim `submitted`, edit hanya saat `draft` atau `revision_needed`) dan halaman Riwayat Jurnal dengan filter status.
5. Konfigurasi Web App Manifest (`manifest.json`) agar siswa dapat menekan *"Tambahkan ke Layar Utama"* (*Add to Home Screen*) di Chrome Android.

### Stage 4: Dashboard Guru, Penilaian Akhir & Laporan PDF (Minggu 5)
1. Bangun halaman beranda khusus Guru Pembimbing di panel `/app`:
   - Widget 1: **"Siswa Belum Absen Hari Ini"** (hanya siswa bimbingan guru tersebut yang penempatannya aktif dan melewati `work_start_time + late_tolerance_minutes`) dengan tombol langsung buka WhatsApp (`wa.me`).
   - Widget 2: **"Antrean Jurnal Menunggu Validasi"** dengan aksi satu klik `Setujui` dan aksi `Minta Revisi` (modal catatan wajib), plus tombol *Bulk Approve*.
2. Bangun fitur persetujuan absensi pengecualian (tugas luar radius / sakit / izin).
3. Implementasi `AssessmentResource` & `CalculateFinalScoreAction`:
   - Form dinamis yang dirender otomatis berdasarkan JSONB `pkl_periods.assessment_template`.
   - Upload wajib foto bukti lembar nilai kertas dari hotel.
   - Kalkulasi otomatis `attendance_score`, `final_score`, dan `predicate`.
4. Buat template cetak Blade untuk `barryvdh/laravel-dompdf`:
   - `StudentLogbookPdfGenerator`: Buku Jurnal PKL lengkap siswa (cover identitas, daftar kehadiran, riwayat kegiatan disetujui).
   - `PeriodRecapPdfGenerator`: Rekap Nilai Akhir PKL satu periode/kelas ber-kop surat resmi sekolah.

### Stage 5: Persiapan Produksi & Pilot Sekolah Pertama (Minggu 6)
1. Siapkan environment produksi:
   - VPS Linux (Ubuntu 24.04) dengan Nginx + PHP-FPM 8.4 (atau FrankenPHP).
   - PostgreSQL 16 dengan jadwal backup otomatis `pg_dump` terkompresi harian ke bucket S3 terpisah.
   - Cloudflare R2 untuk object storage media foto siswa & kop sekolah.
   - Laravel Supervisor untuk menjalankan queue worker (`database` driver) dan cron scheduler.
2. Jalankan audit keamanan & kualitas akhir:
   - Jalankan `php artisan test` (termasuk `TenantIsolationTest`, `AttendanceGeofenceTest`, `AssessmentCalculationTest`).
   - Jalankan Delivery Gate `antislop-ui` pada seluruh halaman desktop & mobile (viewport `375px` dan `1280px`).
3. Dampingi 1 sekolah perhotelan percontohan mengimpor data 1 kelas dan memantau 7 hari pertama absensi siswa di hotel.

### Stage 6: Ekspansi V2 (Setelah Validasi Pasar MVP)
Fitur yang sengaja ditunda agar MVP cepat rilis, dan baru dibangun apabila sekolah pengguna aktif memintanya:
1. **Magic Link WhatsApp untuk Supervisor Hotel**: Tautan bertanda tangan (*signed token*) berbatas waktu bagi supervisor hotel untuk menyetujui jurnal mingguan dan mengisi nilai langsung tanpa membuat akun/password.
2. **Global DUDI & Deduplikasi Hotel**: Migrasi `companies` menjadi data bersama lintas sekolah (menggunakan kolom `npwp`/`google_place_id` yang sudah disiapkan) dan tabel `partnerships` (MoU).
3. **Portal Talent Pool / Rekrutmen Lulusan Hospitality**: Menghubungkan hotel mitra dengan profil alumni PKL SMK yang berpredikat `Amat Baik` dan `Baik`.
