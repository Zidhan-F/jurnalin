# AGENTS.md - Panduan Arsitektur & Konvensi Pengembangan Jurnalin

Dokumen ini menjadi sumber kebenaran utama bagi agen coding AI dan pengembang manusia yang bekerja di repositori **Jurnalin** (Platform Manajemen PKL SMK Multi-Tenant untuk seluruh program keahlian SMK: teknologi informasi, teknik, bisnis, pariwisata, kesehatan, agribisnis, kemaritiman, dan lainnya).

## 1. Identitas Produk & Stack Terimplementasi

- **Nama Produk**: Jurnalin
- **Domain**: Manajemen Praktik Kerja Lapangan (PKL) siswa SMK berbasis multi-tenant (durasi periode diatur admin sekolah: 1, 3, 6 bulan, 1 tahun, atau tanggal kustom; acuan Kurikulum Merdeka umumnya 6 bulan / 792 JP). Jangan menulis teks UI atau validasi yang mengunci durasi 6 bulan.
- **Pendekatan MVP (4 Peran)**: Fokus penuh pada kemudahan sekolah. Pihak DUDI / tempat PKL (`companies`: perusahaan IT, pabrik, bengkel, bank, hotel, rumah sakit, instansi, dan sebagainya) dikelola sebagai data milik masing-masing sekolah tanpa kewajiban akun login bagi pembimbing industri. Nilai dari industri diinput oleh guru pembimbing beserta unggahan foto formulir penilaian fisik/kertas dari hotel sebagai bukti audit.
- **4 Peran Saja**: Admin (kode `super_admin`, pemilik platform), Admin Sekolah, Guru Pembimbing, Siswa. Pembimbing di tempat PKL (DUDI) tidak punya role atau akun; namanya disimpan sebagai teks di `placements.industry_mentor_name`.
- **Standar SaaS yang sudah ada**: Syarat Layanan (`/syarat`), Kebijakan Privasi UU PDP (`/privasi`), Kontak (`/kontak`, email dari `JURNALIN_SUPPORT_EMAIL`), persetujuan saat daftar sekolah (`schools.terms_accepted_at`), lupa sandi via email (`/lupa-sandi`) + reset oleh admin sekolah, halaman error bermerek (`resources/views/errors`), email notifikasi (`app/Notifications`), log aktivitas (`AuditLog::record`, halaman `/admin/activity`), checklist onboarding admin, ekspor ZIP CSV + pengajuan hapus data, SEO (meta, Open Graph, `sitemap.xml`, `robots.txt`).
- **Tanda tangan DUDI**: `MentorLinkController` (buat/cabut tautan), `MentorSignoffController` (halaman publik `/ttd/{token}`, tanpa akun), periode di `App\Support\MentorSignoffPeriods`. Pencarian token adalah satu-satunya query lintas sekolah (`runAsSuperAdmin` lalu langsung `runInSchoolScope`). Aksi penting baru wajib memanggil `AuditLog::record`.
- **Spesifikasi Fitur v2.0**: status per ID fitur ada di [docs/SPEC_V2_STATUS.md](docs/SPEC_V2_STATUS.md). Rute baru di `routes/spec.php`, controller di `app/Http/Controllers/Spec/`, view di `resources/views/spec/`. Akses tanpa akun (mentor, penguji UKK, wali, PIC mitra, alumni, paspor) selalu lewat `App\Support\PublicToken::within` (hash token, lalu langsung dipersempit ke sekolah pemilik). Modul add-on dibuka per tenant lewat `App\Support\Features` dan middleware `feature:*`. Lokasi & swafoto hanya diambil bila `Student::trackingAllowed()` (dewasa atau persetujuan wali tercatat). Halaman baru memakai kelas komponen bersama di `app.css` (`.card`, `.btn-*`, `.badge-*`, `.field`, `.callout-*`), bukan hex mentah.
- **PWA & mobile**: `public/manifest.json` (ikon di `public/icons`, dibuat dari logo paraf), `public/sw.js` (hanya aset statis + `offline.html`; halaman login, `/media`, `/ttd`, PDF tidak pernah di-cache), tag bersama di `partials/pwa-head`, ajakan pasang di `partials/install-prompt`. Tabel di dalam `.overflow-x-auto` otomatis jadi kartu di layar <= 40rem (`app.js`, label dari `<th>`). Naikkan `VERSION` di `sw.js` bila daftar precache berubah.
- **Lintas Program Keahlian**: Katalog saran program keahlian SMK dan kategori DUDI ada di `config/jurnalin.php` dan dirender sebagai `<datalist>` lewat komponen `<x-pkl-datalists />`. Kolom `students.major`, `teachers.specialization`, dan `companies.industry_type` tetap teks bebas. Jangan menulis default atau teks UI yang mengasumsikan satu jurusan (misal "hotel"); gunakan istilah netral "tempat PKL", "DUDI", "pembimbing industri".
- **Stack Teknologi**:
  - **Backend & Monolith**: Laravel 13 (`laravel/framework v13.42.0`, PHP 8.3+)
  - **Database**: PostgreSQL 16/18 dengan **Row-Level Security (RLS)** untuk lokal, testing (database `jurnalin_test`), dan produksi. SQLite tidak dipakai.
  - **UI Web & Mobile PWA**: Blade Templates + Tailwind CSS v4 (Vite) + Alpine.js + Leaflet.js (OpenStreetMap)
  - **Ekspor Laporan PDF**: `barryvdh/laravel-dompdf` (`v3.1.2`)
  - **Impor / Ekspor Excel**: `App\Support\SpreadsheetHelper` berbasis `ZipArchive` & `SimpleXMLElement` native PHP (mendukung `.xlsx` dan `.csv`)
  - **Testing**: PHPUnit 12 (`tests/Unit/*` dan `tests/Feature/*`)

## 2. Arsitektur Multi-Tenant & Kebijakan Zero-Leak

Jurnalin menggunakan model **Single Database, Shared Schema** dengan isolasi dua lapis:

1. **Lapisan Database (PostgreSQL RLS)**:
   - Tabel `schools` menyimpan entitas tenant. Tabel `users` menyimpan akun pengguna dengan `school_id` (`NULL` khusus untuk `super_admin`).
   - Seluruh tabel operasional (`teachers`, `students`, `companies`, `pkl_periods`, `placements`, `attendances`, `journals`, `assessments`) memiliki kolom `school_id UUID NOT NULL` dan dilindungi oleh migrasi `0001_01_01_000005_enable_postgresql_rls_policies.php` (`ENABLE ROW LEVEL SECURITY` dan `FORCE ROW LEVEL SECURITY` saat memakai driver `pgsql`).
   - Middleware `SetTenantContextMiddleware` diprioritaskan sebelum `SubstituteBindings` di `bootstrap/app.php` agar Route Model Binding selalu dievaluasi sesudah konteks `TenantContext` aktif, serta selalu dibersihkan di blok `finally`.

2. **Lapisan Aplikasi (Eloquent Global Scope)**:
   - Setiap model milik tenant memakai trait `App\Models\Concerns\BelongsToSchool` untuk menyaring query lewat Global Scope `school_tenant` (mengembalikan `1 = 0` jika konteks sekolah kosong dan bukan super admin) dan mengisi `school_id` otomatis pada event `creating`.
   - Jangan pernah menonaktifkan Global Scope (`withoutGlobalScopes()`) di kode panel sekolah atau portal siswa.

3. **Tes Isolasi Lintas Tenant**:
   - Berkas `tests/Feature/TenantIsolationTest.php` menguji skenario guru Sekolah A mencoba membaca, menyetujui jurnal, atau mengunduh PDF penempatan milik Sekolah B via `TenantContext` dan HTTP. Semua percobaan wajib mengembalikan 0 baris dan HTTP `404`.

## 3. Empat Peran Pengguna & Rute Utama

| Peran (`UserRole`) | Rute Utama | Tanggung Jawab Utama |
|---|---|---|
| `super_admin` (label "Admin") | `/super-admin` | Verifikasi pendaftaran sekolah via NPSN Kemendikdasmen, atur paket langganan & kuota siswa |
| `school_admin` | `/admin/*` | Impor Excel siswa & guru, kelola periode PKL, rubrik penilaian JSON, tempat PKL (Leaflet), penempatan, pengaturan kop surat |
| `teacher` | `/teacher/*` | Pantau siswa belum absen hari ini (+ tombol WhatsApp), setujui/revisi jurnal, setujui pengecualian absensi, input nilai & bukti form penilaian DUDI, cetak PDF |
| `student` | `/student/*` | Absen masuk/pulang ber-geotag (Haversine + kompresi foto Canvas WebP), tulis & revisi jurnal harian, lihat progres & nilai akhir |

## 4. Aturan Penulisan Kode & Pola Desain

- **Tanpa Repository Pattern**: Eloquent Model dan `BelongsToSchool` sudah cukup.
- **Action Classes untuk Logika Transaksional**: Letakkan di `app/Actions/{Domain}/{NamaAksi}Action.php` dengan metode publik `handle(...)`.
- **Value Objects (`readonly class`)**: `Coordinates`, `Npsn`, dan `AssessmentRubric`.
- **State Machine via Backed Enum**: `SchoolStatus`, `PlacementStatus`, `AttendanceStatus`, `JournalStatus` dengan `canTransitionTo(...)`.
- **Tanpa Em-Dash di Teks & UI**: Dilarang menggunakan karakter em-dash (`\u2014`) pada seluruh teks UI, label, dokumen, maupun komentar kode.

## 5. Aturan UI & Desain

- Seluruh pembuatan atau pembaruan tampilan antarmuka wajib mematuhi [DESIGN.md](file:///c:/Users/Zidhan/Downloads/jurnalin/DESIGN.md).
- Dashboard dan layar siswa berorientasi keputusan (*decision-driven*), memiliki Empty/Loading/Error State yang informatif, serta tap target mobile minimal `48px`.

## 6. Menjalankan & Memverifikasi Proyek

- Jalankan `start-jurnalin.bat` atau jalankan di terminal:
  `php artisan migrate:fresh --seed --ansi && php artisan test && npm run build && php artisan serve --host=127.0.0.1 --port=8000`
- Setelah struktur kode berubah, jalankan `python -m graphify update .` (atau `python -m graphify .` saat inisialisasi awal) untuk memperbarui knowledge graph.

<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, read `antislop.md` (core) and then the skill for the task:
- UI / visual: `skills/antislop-ui/SKILL.md`
- Copy & text: `skills/antislop-copywriting/SKILL.md`
- People: `skills/antislop-human/SKILL.md`
- Mobile / responsive: `skills/antislop-layoutmobile/SKILL.md`
- Code comments: `skills/antislop-code/SKILL.md`
Before starting, follow the core's "Two Usage Modes" section in strict order: explicit session instruction first, then global preference, then ask. A session instruction always wins. For a resolved mode, say `antislop active: <mode> (session override).` or `antislop active: <mode> (global preference).` once before presenting findings or making edits, using the actual mode and source. Acknowledging the user's request without naming the source does not replace this notice.
Only an explicit choice of antislop during or after selects a session mode. A request to review, audit, or avoid file edits does not select a mode; read the global preference in that case. Another skill's mode does not select antislop's mode.
If the mode is unresolved, ask during/after and end the response; wait for the answer before any UI review, planning, or concept. For read-only tasks, put the active-mode notice only at the start of the final answer, never in progress messages. For editing tasks, announce before the first edit and omit it from the final answer.
To update antislop later: download `antislop.md` again, or run `npx antislop-ai --update` if it was installed as skill folders.
<!-- antislop:end -->
