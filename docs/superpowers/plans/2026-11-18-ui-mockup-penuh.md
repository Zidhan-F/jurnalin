# Penerapan Penuh Mockup UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tiga panel (Siswa, Guru Pembimbing, Admin Sekolah), Pusat Hubin, dan landing page tampil mengikuti mockup di `docs/desain/mockup/`, dengan palet dan logo yang ada, data asli, dan web tetap ringan.

**Architecture:** Tetap monolit Laravel + Blade + Tailwind v4 + Alpine. Angka dashboard dihitung di kelas `App\Support\*Dashboard` (satu tanggung jawab per kelas) lalu dirender server. Grafik memakai Chart.js yang dimuat dengan `import()` dinamis hanya bila ada `[data-chart]`; HTML selalu memuat angka yang sama sebagai teks. Ilustrasi berupa SVG statis Fluent Emoji di `public/images/illustrations`.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL + RLS, Blade, Tailwind CSS v4, Alpine.js, Chart.js 4 (baru), `@iconify-json/fluent-emoji` (devDependency, baru), PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-18-ui-mockup-penuh-design.md` (baca bersama rencana ini; acuan visual di `docs/desain/mockup/01-beranda-siswa-hp.png`, `02-dashboard-koordinator-desktop.png`, `03-poster-platform-multi-peran.png`).

## Global Constraints

- Palet warna dan logo paraf tidak diubah; warna hanya lewat token/kelas di `resources/css/app.css` (tanpa hex mentah di view; `tests/Unit/ColorContrastTest.php` harus lulus). Palet biru poster diabaikan.
- Tanpa karakter em-dash (U+2014) di teks UI, kode, komentar, dan dokumen.
- Model tenant memakai `BelongsToSchool`; dilarang `withoutGlobalScopes()` di panel sekolah/siswa; setiap fitur baca data punya tes isolasi lintas sekolah (Sekolah A NPSN `20219101`, Sekolah B NPSN `50103822`, dari seeder).
- Halaman siswa: 0 KB JavaScript tambahan; Chart.js tidak pernah dimuat di `layouts/student-pwa.blade.php`.
- Chart.js dimuat via `import()` hanya bila ada `[data-chart]`; daftarkan hanya Line, Doughnut, dan elemen/plugin minimum; target chunk sekitar 70 KB gzip atau kurang.
- Ilustrasi SVG masing-masing sekitar 15 KB atau kurang, dengan `width`/`height` eksplisit; di bawah lipatan pakai `loading="lazy"`.
- Tanpa font baru. Kueri dashboard tanpa N+1.
- Target sentuh minimal 44px (`min-h-11`), fokus keyboard terlihat, `prefers-reduced-motion` mematikan animasi grafik.
- Tidak ada peran baru (Kepala Sekolah, Wakasek, Orang Tua, Koordinator terpisah). "Dashboard Koordinator PKL" = panel `school_admin`.
- Tidak ada angka karangan: tren tanpa periode pembanding disembunyikan, bukan diisi nol.
- Teks UI tidak mengunci durasi PKL 6 bulan dan tidak mengasumsikan satu jurusan.
- Bila precache `public/sw.js` berubah, naikkan `VERSION`.
- Verifikasi setiap task: `php artisan test` lulus. Akhir: `npm run build` lulus (di lingkungan tanpa akses `fonts.bunny.net`, build verifikasi boleh memakai config sementara tanpa plugin font, lalu dihapus).
- Commit diakhiri baris atribusi sesi yang berlaku.

## Review Focus

1. Kata kunci pencarian berisi `%`, `_`, atau `\` harus dicocokkan sebagai teks biasa, bukan wildcard. Tes di Task 4.
2. Sekolah baru tanpa periode, penempatan, atau jurnal: dashboard admin, guru, dan siswa tetap merender dengan pesan kosong, tanpa pembagian nol atau error. Tes di Task 5, 6, 7.
3. `?periode=` berisi UUID periode sekolah lain atau teks acak: jangan bocor, jangan 500. Jatuh ke periode default. Tes di Task 5.
4. Siswa dengan `gender` kosong atau huruf kecil (`l`/`p`) mendapat maskot yang benar (netral untuk kosong). Tes di Task 1.
5. Chart.js gagal dimuat atau JavaScript mati: angka grafik tetap terlihat sebagai teks. Tes di Task 2 (HTML memuat nilai).

---

### Task 1: Aset ilustrasi dan helper maskot/avatar

**Files:**
- Create: `scripts/extract-illustrations.mjs`, `public/images/illustrations/*.svg`, `public/images/illustrations/LICENSE.txt`, `app/Support/Mascot.php`, `resources/views/components/avatar.blade.php`
- Modify: `package.json` (devDependency `@iconify-json/fluent-emoji`), `README.md` (kredit Fluent Emoji MIT)
- Test: `tests/Unit/MascotTest.php`

**Interfaces:**
- Produces: `App\Support\Mascot::forStudent(?string $gender): string` (path publik, misal `/images/illustrations/student-male.svg`); `Mascot::teacher(): string`; `Mascot::initials(string $name): string` (maksimal 2 huruf kapital, abaikan gelar setelah koma: `"Budi Prasetyo, S.ST.Par"` -> `"BP"`); `Mascot::role(string $key): string` untuk kunci `student, teacher, admin, mentor, intern_teacher, examiner, alumni, superadmin`.
- Produces: `<x-avatar :user="$user" size="sm|md" />`: siswa = gambar maskot, selain siswa = lingkaran inisial (`bg-navy text-white`).

- [ ] **Step 1: Tulis tes yang gagal** di `tests/Unit/MascotTest.php`: `test_student_mascot_follows_gender` (`'L'` dan `'l'` -> berakhiran `student-male.svg`; `'P'`/`'p'` -> `student-female.svg`; `null` dan `''` -> `student-neutral.svg`), `test_initials_skip_academic_titles` (`"Budi Prasetyo, S.ST.Par"` -> `"BP"`, `"Nadia"` -> `"N"`, `"Rina Kartika Sari"` -> `"RK"`), `test_every_role_illustration_file_exists` (untuk setiap kunci role dan setiap maskot, `file_exists(public_path(ltrim($path,'/')))` true).
- [ ] **Step 2: Jalankan** `php artisan test --filter=MascotTest`. Harus FAIL (kelas belum ada).
- [ ] **Step 3: Tulis `scripts/extract-illustrations.mjs`** yang membaca `node_modules/@iconify-json/fluent-emoji/icons.json` dan menulis SVG mandiri (atribut `viewBox`, `width="128" height="128"`) untuk peta berikut, lalu jalankan `npm i -D @iconify-json/fluent-emoji && node scripts/extract-illustrations.mjs`:
  `student-male` = `man-student`, `student-female` = `woman-student`, `student-neutral` = `student`, `teacher` = `teacher`, `admin` = `office-worker`, `mentor` = `mechanic`, `intern_teacher` = `man-teacher` (atau yang paling dekat), `examiner` = `man-judge` atau `memo`, `alumni` = `graduation-cap`, `superadmin` = `gear`. Bila nama ikon berbeda di paket, pilih padanan terdekat dan catat di kepala skrip. Periksa tiap file 15 KB atau kurang.
- [ ] **Step 4: Implementasikan `app/Support/Mascot.php`** (`final class`, metode statis di atas) dan `components/avatar.blade.php`; tambahkan `LICENSE.txt` (teks MIT dari `node_modules/@iconify-json/fluent-emoji/license.txt` atau info lisensi paket) dan satu baris kredit di `README.md`.
- [ ] **Step 5: Jalankan** `php artisan test --filter=MascotTest`. Harus PASS.
- [ ] **Step 6: Commit** `feat: aset ilustrasi Fluent Emoji dan helper maskot`.

### Task 2: Infrastruktur grafik Chart.js

**Files:**
- Create: `resources/js/charts.js`, `resources/views/components/chart.blade.php`
- Modify: `resources/js/app.js` (pemicu muat dinamis), `package.json` (dependency `chart.js`)
- Test: `tests/Feature/ChartComponentTest.php`

**Interfaces:**
- Produces: `<x-chart type="line|doughnut" :labels="array<string>" :values="array<int|float>" :series-label="string" :caption="string" />`. Render: `<figure>` berisi `<canvas data-chart='{"type":..,"labels":..,"values":..}' role="img" aria-label="{caption}">` dan, selalu, `<table class="chart-data">` berisi pasangan label/nilai (visual tersembunyi untuk line chart via kelas `sr-only`, terlihat sebagai legenda untuk doughnut).
- Produces: `resources/js/charts.js` mengekspor `mountCharts(root: ParentNode): Promise<void>`; warna diambil dari CSS custom property (`--color-navy`, `--color-emerald-check`, `--color-amber-warn`, `--color-danger`, `--color-border`); `animation: false` bila `matchMedia('(prefers-reduced-motion: reduce)')`.

- [ ] **Step 1: Tulis tes yang gagal** `test_chart_component_renders_values_as_text`: render `Blade::render('<x-chart type="doughnut" :labels="[\'Hadir\',\'Izin\']" :values="[3,1]" series-label="Peserta" caption="Status peserta" />')`; assert berisi `data-chart`, `Hadir`, `3`, `Izin`, `1`, `aria-label="Status peserta"`.
- [ ] **Step 2: Jalankan** `php artisan test --filter=ChartComponentTest`. Harus FAIL.
- [ ] **Step 3: Implementasikan** komponen; `npm i chart.js`; di `app.js`: `if (document.querySelector('[data-chart]')) import('./charts.js').then(m => m.mountCharts(document))`. Di `charts.js` impor dari `chart.js` hanya `Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, DoughnutController, ArcElement`.
- [ ] **Step 4: Jalankan tes** (PASS) dan build; pastikan chunk `charts-*.js` terpisah dari `app-*.js` dan ukuran gzip-nya dicatat di pesan commit.
- [ ] **Step 5: Commit** `feat: komponen grafik Chart.js dimuat dinamis`.

### Task 3: Kerangka panel desktop (sidebar + header)

**Files:**
- Create: `app/Support/PanelNavigation.php`, `resources/views/partials/panel-sidebar.blade.php`, `resources/views/partials/panel-header.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (pindahkan array `$links` ke `PanelNavigation`; susun grid sidebar), `resources/css/app.css`
- Test: `tests/Feature/PanelLayoutTest.php`

**Interfaces:**
- Consumes: `<x-avatar>` (Task 1).
- Produces: `App\Support\PanelNavigation::for(\App\Models\User $user, \Illuminate\Http\Request $request): array<string, array<string, string>>` (label grup => [nama rute => label]). Grup admin: `Utama` (Dashboard `admin.index`, Notifikasi `notifications.index`, Jurnal tertunda `admin.pending-journals`), `Manajemen PKL` (Periode, Tempat PKL, Guru, Siswa, Penempatan, Impor Excel, Kunjungan, Laporan dari siswa, Akses pembimbing industri, Sertifikat, Pusat Hubin bila fitur, Rekap Hubin bila Pro), `Laporan` (Pengaturan & kop surat, Grup & pembayaran, Fitur tambahan, Privasi data, Riwayat aktivitas). Guru: `Utama` (Hari ini, Periksa jurnal, Nilai dan laporan), `Pendampingan` (seperti sekarang). Aturan `grace_placements` guru dan menu super admin dipertahankan persis.
- Produces: header berisi slot pencarian `@include('partials.panel-search')` (dibuat di Task 4; sampai Task 4 selesai, sertakan dengan `@includeIf`).

- [ ] **Step 1: Tulis tes yang gagal**: `test_admin_sees_sidebar_groups_and_every_link_resolves` (GET `/admin` sebagai admin Sekolah A: assert `Manajemen PKL`, `Laporan`, `Butuh bantuan?`, nama sekolah; setiap `href` internal di `<aside>` di-GET dan bukan 404/500), `test_teacher_sidebar_respects_grace_rule` (bila atribut grace aktif hanya `teacher.index`; cukup uji fungsi `PanelNavigation::for` dengan request beratribut), `test_school_name_is_not_a_dropdown` (assert tidak ada `<select` di `<aside>`).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan** `PanelNavigation`, partial sidebar (logo paraf, nama sekolah statis, grup `<details>` terbuka bila aktif, kartu "Butuh bantuan?" ke `route('contact')`), partial header (lonceng + jumlah belum dibaca, chip akun `<details>` dengan avatar, nama, peran, menu Ganti Sandi/Privasi saya/Keluar). CSS: pada `min-width: 64rem` body panel menjadi grid `16rem 1fr`, sidebar sticky; di bawah 64rem sidebar disembunyikan dan menu "Menu panel" lama tetap tampil.
- [ ] **Step 4: Jalankan** `php artisan test` (seluruh suite, karena 52 view memakai layout). Harus PASS.
- [ ] **Step 5: Periksa visual** 360px dan 1280px untuk `/admin`, `/teacher`, `/super-admin`: tanpa gulir horizontal.
- [ ] **Step 6: Commit** `feat: kerangka panel desktop dengan sidebar`.

### Task 4: Pencarian global

**Files:**
- Create: `app/Support/GlobalSearch.php`, `app/Http/Controllers/SearchController.php`, `resources/views/search/index.blade.php`, `resources/views/partials/panel-search.blade.php`
- Modify: `routes/web.php` (dalam grup `auth` + `tenant.context`)
- Test: `tests/Feature/GlobalSearchTest.php`

**Interfaces:**
- Produces: `App\Support\GlobalSearch::run(\App\Models\User $user, string $query, int $limitPerType = 5): array{students: Collection, teachers: Collection, companies: Collection, journals: Collection}`. Query kurang dari 2 karakter (setelah `trim`) mengembalikan semua koleksi kosong. Pencocokan `ILIKE '%'.$escaped.'%'` dengan `%`, `_`, `\` di-escape. Guru: siswa dan jurnal dibatasi ke penempatan dengan `teacher_id` miliknya; `teachers` selalu kosong untuk guru.
- Produces: rute `GET /cari` (`search.index`, view) dan `GET /cari/saran` (`search.suggest`, JSON `{"results":[{"type":"student|teacher|company|journal","label":string,"hint":string,"url":string}]}`, maksimal 8), middleware `role:school_admin,teacher`.
- Produces: partial kotak pencarian: `<form action="/cari">` + Alpine (debounce 250 ms, minimal 2 karakter, daftar saran dengan navigasi panah/Enter/Escape, tombol `/` memfokuskan input bila fokus tidak di field lain).

- [ ] **Step 1: Tulis tes yang gagal**: `test_admin_finds_student_teacher_company_and_journal` (q `Bagas` menemukan siswa; q `Budi` menemukan guru; q `Padma` menemukan tempat PKL; q `Prasmanan` menemukan jurnal), `test_teacher_only_sees_own_students` (guru `guru.kuliner@...` mencari `Bagas` -> 0 siswa; `guru.perhotelan@...` -> 1), `test_other_school_never_appears` (admin Sekolah B mencari `Bagas` -> 0), `test_wildcards_are_literal` (q `%%` dan `_a` -> semua koleksi kosong atau hanya nama yang benar-benar memuat karakter itu), `test_suggest_returns_json_and_short_query_is_empty` (q `B` -> `results` kosong; q `Bag` -> 1 hasil berjenis `student` dengan `url`), `test_student_cannot_search` (siswa GET `/cari?q=a` -> 403).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan** `GlobalSearch`, controller (`index(Request): View`, `suggest(Request): JsonResponse`), view hasil dikelompokkan (tautan: siswa admin ke `monitor.participant` penempatan terbarunya bila ada, jika tidak ke `admin.students`; guru ke `monitor.participant`; tempat PKL ke `admin.companies`; jurnal ke `monitor.participant`; guru ke `admin.users.edit`), dan partial header; ganti `@includeIf` Task 3 menjadi `@include` untuk admin dan guru.
- [ ] **Step 4: Jalankan** `php artisan test`. Harus PASS.
- [ ] **Step 5: Commit** `feat: pencarian global per sekolah dengan saran otomatis`.

### Task 5: Dashboard admin sekolah (mockup 2)

**Files:**
- Create: `app/Support/AdminDashboard.php`
- Modify: `app/Http/Controllers/SchoolAdminController.php` (`index`), `resources/views/admin/index.blade.php`
- Test: `tests/Feature/AdminDashboardTest.php`, sesuaikan `tests/Feature/PendingJournalsPageTest.php` bila label berubah

**Interfaces:**
- Consumes: `<x-chart>` (Task 2), `<x-avatar>` (Task 1).
- Produces: `App\Support\AdminDashboard::build(\App\Models\School $school, ?\App\Models\PklPeriod $period, \Carbon\CarbonImmutable $today): array` dengan kunci:
  `period`, `previousPeriod` (atau null), `isRunning` (bool, today di dalam rentang), `kpi` = `['participants' => int, 'participantsDelta' => ?int, 'companies' => int, 'newCompanies' => ?int, 'pendingJournals' => int, 'present' => int, 'presentBase' => int, 'attendanceRate' => ?int]`, `priorities` = list 4 item `['key','label','detail','count','href']` dengan kunci `journals`, `absent`, `unplaced`, `assessment`, `journalTrend` = `['labels' => string[7], 'values' => int[7], 'total' => int, 'changePercent' => ?int]`, `status` = `['labels' => ['Hadir','Izin/Sakit','Belum/Tidak hadir'], 'values' => int[3], 'scope' => 'today'|'period']`.
- Aturan dari spec 5.4 dan 6: hanya penempatan non-batal di periode terpilih; `previousPeriod` = periode dengan `end_date` terbesar yang `< period.start_date`; `assessment` = penempatan `active`/`completed` di periode tanpa `assessment.finalized_at`, dan (`period.end_date` 14 hari atau kurang dari today, atau sudah lewat, atau hari hadir siswa >= `min_required_days`); href `route('admin.periods.show', $period)`. Pemilih periode memakai `PklPeriod::query()->find($request->query('periode'))` (tenant scope otomatis); tidak ketemu = default `is_active` dengan `start_date` terbaru.

- [ ] **Step 1: Tulis tes yang gagal**: `test_dashboard_renders_mockup_sections` (assert `Dashboard Koordinator PKL`, `Tugas prioritas`, `Aksi cepat`, `Periode PKL aktif`, `Perkembangan jurnal`, `Status peserta`, `data-chart`), `test_trend_hidden_without_previous_period` (seed: tidak ada teks `dari periode lalu`), `test_trend_shown_with_previous_period` (buat periode selesai sebelum periode aktif dengan 1 penempatan; assert `+3 siswa dari periode lalu` sesuai selisih nyata), `test_period_from_other_school_falls_back` (`?periode=` UUID periode Sekolah B -> 200, menampilkan periode default Sekolah A, tanpa nama periode Sekolah B), `test_garbage_period_param_does_not_error` (`?periode=abc` -> 200), `test_past_period_uses_period_scope` (pilih periode selesai -> teks `seluruh periode`, tidak ada `Presensi hari ini`), `test_empty_school_renders_without_errors` (sekolah baru tanpa periode: 200 dan checklist persiapan tampil), `unit: test_assessment_ready_counts_rule` di `AdminDashboard` (penempatan dengan hari hadir >= target dan tanpa nilai final dihitung; yang sudah final tidak).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan** `AdminDashboard::build` dan view sesuai urutan mockup: sapaan + judul + pemilih periode (form GET, `<select name="periode">` + tombol "Tampilkan", Alpine `@change` submit), 4 kartu KPI dengan ikon, Tugas prioritas + Aksi cepat + Periode aktif, grafik jurnal + kotak Total jurnal, donat Status peserta, checklist persiapan bila belum lengkap, tabel Penempatan terbaru.
- [ ] **Step 4: Jalankan** `php artisan test`. Harus PASS.
- [ ] **Step 5: Periksa visual** 360px dan 1280px terhadap `02-dashboard-koordinator-desktop.png`.
- [ ] **Step 6: Commit** `feat: dashboard koordinator PKL sesuai mockup`.

### Task 6: Dashboard guru pembimbing (poster)

**Files:**
- Create: `app/Support/TeacherDashboard.php`
- Modify: `app/Http/Controllers/TeacherController.php` (`index`), `resources/views/teacher/index.blade.php`
- Test: `tests/Feature/TeacherDashboardTest.php`

**Interfaces:**
- Consumes: `PklMonitor::alerts(Collection $placements, string $today): Collection` (item berisi `placement`), `<x-chart>`, `<x-avatar>`.
- Produces: `App\Support\TeacherDashboard::progress(\App\Models\Teacher $teacher, string $today): array{done: int, running: int, attention: int}` atas penempatan guru di periode `is_active`: `done` = `completed`; `attention` = `active` dengan alert; `running` = `active` tanpa alert (tidak tumpang tindih).
- Produces: `TeacherDashboard::recentJournals(\App\Models\Teacher $teacher, int $limit = 5): Collection` (jurnal `submitted` dan `revision_needed` terbaru dari penempatan guru).

- [ ] **Step 1: Tulis tes yang gagal**: `test_progress_groups_do_not_overlap` (seed guru perhotelan: `done + running + attention` = jumlah penempatan active+completed di periode aktif; siswa beralert dihitung di `attention` saja), `test_dashboard_shows_kpi_recent_journals_and_chart` (assert `Peserta binaan`, `Jurnal perlu review`, `Perlu perhatian`, `Jurnal terbaru`, `Progres peserta`, `data-chart`, `Yang perlu Anda kerjakan`), `test_teacher_without_students_renders` (guru baru tanpa penempatan: 200, pesan kosong, tanpa `data-chart`).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan**: di atas bagian yang ada, tambahkan sapaan + judul `Dashboard Guru Pembimbing`, 4 kartu KPI, kartu "Jurnal terbaru (perlu tindakan)" dengan avatar dan lencana status (tautan `teacher.review`), donat "Progres peserta". Bagian keputusan yang ada tetap di bawah.
- [ ] **Step 4: Jalankan** `php artisan test`. Harus PASS.
- [ ] **Step 5: Commit** `feat: dashboard guru pembimbing sesuai poster`.

### Task 7: Panel siswa di HP (mockup 1): beranda, presensi, laporan, menu bawah

**Files:**
- Create: `app/Support/PklProgress.php`, `resources/views/student/attendance.blade.php`, `resources/views/student/partials/attendance-form.blade.php`, `resources/views/student/report.blade.php`
- Modify: `app/Http/Controllers/StudentController.php` (tambah `attendance(Request): View`, `report(Request): View`; `home` disesuaikan), `routes/web.php` (`GET /student/presensi` `student.attendance`, `GET /student/laporan` `student.report`), `resources/views/student/home.blade.php`, `resources/views/layouts/student-pwa.blade.php`, `resources/css/app.css`
- Test: `tests/Feature/StudentPanelTest.php`, `tests/Unit/PklProgressTest.php`

**Interfaces:**
- Consumes: `Mascot::forStudent`, `<x-avatar>` (Task 1).
- Produces: `App\Support\PklProgress::weeks(\App\Models\PklPeriod $period, \Carbon\CarbonImmutable $today): array{current: int, total: int, percent: int}`: `total = max(1, ceil((end - start + 1 hari) / 7))`; `current` = minggu ke berapa today berada, dijepit `0..total` (0 sebelum mulai, `total` setelah selesai); `percent = round(current / total * 100)`.

- [ ] **Step 1: Tulis tes yang gagal**: unit `test_weeks_progress` (periode 1 Jan sampai 25 Mar 2027 = 84 hari: total 12; today 25 Jan -> current 4, percent 33; sebelum mulai -> current 0; setelah selesai -> current 12, percent 100). Feature: `test_home_matches_mockup_sections` (siswa Nadia: `Halo, Nadia!`, `Presensi Hari Ini`, tautan `/student/presensi` dan tulis jurnal, `Progres PKL`, `Minggu ke-`, `Aktivitas terbaru`, maskot `student-female.svg`), `test_male_student_gets_male_mascot` (Bagas -> `student-male.svg`), `test_check_in_works_from_presensi_page` (GET `/student/presensi` 200 berisi form `action="/student/check-in"`; POST check-in mode `present` dengan koordinat dalam radius -> redirect dan presensi tercatat; ikuti pola `tests/Feature/AttendanceGeofenceTest.php`), `test_report_page_links_learning_features` (GET `/student/laporan`: tautan `student.learning`, `student.calendar`, `student.passport`, `student.readiness`, `student.interview`, `student.journey`, `student.final-report`), `test_bottom_nav_has_five_items` (Beranda, Presensi, Jurnal, Izin, Laporan), `test_grace_student_sees_only_journal_nav` (pola atribut grace yang ada), `test_student_without_placement_renders` (siswa tanpa penempatan aktif: 200, pesan "Penempatan PKL belum aktif"), `test_no_chart_script_on_student_pages` (HTML `/student` tidak memuat `data-chart`).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan**: pindahkan isi `<section id="absen" x-data=...>` dari beranda ke `attendance-form` partial tanpa mengubah perilaku, dipakai di `student/attendance.blade.php` bersama riwayat 7 presensi terakhir dan tautan Kalender. Beranda: header (logo + label PKL, lonceng, avatar ke Profil), sapaan + maskot, kartu Presensi Hari Ini (lencana status, jam Masuk/Pulang), dua tombol besar (Presensi, Tulis Jurnal), baris "Sekarang: ...", kartu Progres PKL (`PklProgress::weeks` + "N dari M hari hadir"), banner revisi, Aktivitas terbaru, tautan Lapor ke Guru. Hapus penanda 3 langkah. Laporan: progres, nilai akhir (data seperti `profile`), unduh buku jurnal PDF, dan daftar "Belajar dan persiapan". Menu bawah 5 item (CSS `repeat(5, 1fr)`, label 0.8125rem agar muat di 360px).
- [ ] **Step 4: Jalankan** `php artisan test`. Harus PASS.
- [ ] **Step 5: Periksa visual** 360px terhadap `01-beranda-siswa-hp.png`; ukur bahwa JS halaman siswa sama dengan sebelum task (hanya `app-*.js`).
- [ ] **Step 6: Commit** `feat: beranda siswa, halaman presensi dan laporan sesuai mockup`.

### Task 8: Tampilan desktop siswa (poster)

**Files:**
- Create: `resources/views/student/partials/desktop-sidebar.blade.php`
- Modify: `resources/views/layouts/student-pwa.blade.php`, `resources/views/student/home.blade.php`, `app/Http/Controllers/StudentController.php` (`home`: `notifications` 3 terbaru, `targets` = `['submitted' => int, 'total' => int]`, `approvedJournals` int), `resources/css/app.css`
- Test: tambah ke `tests/Feature/StudentPanelTest.php`

**Interfaces:**
- Consumes: `PklProgress::weeks` (Task 7), `Mascot` (Task 1).

- [ ] **Step 1: Tulis tes yang gagal**: `test_home_has_desktop_blocks` (HTML berisi sidebar `Bantuan` dengan href `student.tickets`, ubin `Ajukan izin`, `Notifikasi terbaru`, `Jurnal disetujui`), `test_targets_line_hidden_without_targets` (siswa tanpa `LearningTarget`: tidak ada teks `Tugas`).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan**: pada `min-width: 64rem` layout siswa menjadi grid sidebar + isi; menu bawah disembunyikan; beranda menampilkan 4 ubin aksi, cincin progres CSS `conic-gradient(var(--color-emerald-check) {percent}%, var(--color-border) 0)` dengan angka di tengah, rincian, dan Notifikasi terbaru. Di HP blok desktop tidak tampil.
- [ ] **Step 4: Jalankan** `php artisan test` dan periksa 1280px terhadap panel siswa di poster.
- [ ] **Step 5: Commit** `feat: tampilan desktop siswa`.

### Task 9: Pusat kemitraan Hubin (poster "Dashboard Wubin")

**Files:**
- Modify: `app/Http/Controllers/Spec/HubinController.php` (`index`), `resources/views/spec/hubin/index.blade.php`
- Test: `tests/Feature/HubinDashboardTest.php`

**Interfaces:**
- Produces (variabel view baru): `hubinKpi` = `['activePartners' => int, 'prospects' => int, 'placements' => int, 'activePrograms' => int]`, `latestPartners` (5 `Company` terbaru dengan `active_placements_count` dan kuota periode aktif), `programs` = list `['label' => string, 'active' => bool]` untuk `PKL siswa`, `Magang guru`, `Kunjungan pembimbingan ke DUDI` (dari `VisitReport` dengan `visit_date` dalam rentang periode aktif).

- [ ] **Step 1: Tulis tes yang gagal**: `test_hubin_center_shows_partner_kpis` (admin Sekolah A, fitur hubin aktif di seed: assert `Mitra aktif`, `Dalam proses`, `Total penempatan`, `Program aktif`, `Mitra terbaru`, `Kunjungan pembimbingan ke DUDI`, dan tidak ada teks `Kunjungan industri`), `test_program_active_count_matches_list` (jumlah `Program aktif` = jumlah program `active` di daftar).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan** kueri di controller dan blok atas view sesuai poster; isi lama tetap di bawah.
- [ ] **Step 4: Jalankan** `php artisan test`. Harus PASS.
- [ ] **Step 5: Commit** `feat: ringkasan kemitraan Hubin sesuai poster`.

### Task 10: Landing page (poster)

**Files:**
- Modify: `resources/views/public/home.blade.php`, `resources/css/app.css`
- Test: `tests/Feature/LandingPageTest.php`

**Interfaces:**
- Consumes: `Mascot::role` (Task 1).

- [ ] **Step 1: Tulis tes yang gagal**: `test_landing_shows_platform_roles_and_modules` (GET `/`: `Satu platform untuk PKL, Magang, dan Kolaborasi Industri`, kartu peran `Siswa`, `Guru Pembimbing`, `Admin Sekolah`, `Pembimbing Industri`, `Guru Magang`, `Penguji UKK`, `Alumni`, `Superadmin`; modul `Hubin Partner Hub`, `Magang Guru`, `Mentor Access`, `Tracer Study`, `UKK`), `test_landing_excludes_roles_not_in_product` (tidak ada `Kepala Sekolah`, `Wakasek`, `Orang Tua`).
- [ ] **Step 2: Jalankan**, harus FAIL.
- [ ] **Step 3: Implementasikan**: hero baru dengan 4 poin centang poster; ganti isi `.role-grid` menjadi kartu berilustrasi (`loading="lazy"`); tambah bagian "Modul utama Jurnalin" dengan label status yang dicocokkan ke `docs/SPEC_V2_STATUS.md` (tulis label hanya bila dokumen itu mendukungnya). Bagian lain tetap; sitemap dan meta tetap.
- [ ] **Step 4: Jalankan** `php artisan test`. Harus PASS.
- [ ] **Step 5: Commit** `feat: landing page menampilkan platform, peran, dan modul`.

### Task 11: Dokumentasi desain dan verifikasi akhir

**Files:**
- Modify: `DESIGN.md` (bagian 4 grafik, sidebar, ilustrasi, menu bawah 5 item, anggaran performa), `AGENTS.md` (sebut `App\Support\AdminDashboard`, `TeacherDashboard`, `GlobalSearch`, `PanelNavigation`, `Mascot`, `PklProgress`, rute `/cari`, `/student/presensi`, `/student/laporan`), `public/sw.js` (`VERSION` bila precache berubah)

- [ ] **Step 1: Perbarui** `DESIGN.md` dan `AGENTS.md` sesuai spec 5.9; cari em-dash: `grep -rnP '\x{2014}' DESIGN.md AGENTS.md resources app docs/superpowers` harus kosong.
- [ ] **Step 2: Jalankan** `php artisan test` (semua lulus) dan `npm run build` (lulus; catat ukuran `app-*.js` dan `charts-*.js` gzip).
- [ ] **Step 3: Klik menyeluruh** dengan Playwright di 360px dan 1280px untuk admin, guru, siswa, Hubin, landing: tanpa gulir horizontal, tanpa error konsol, setiap tautan internal di `<main>` dan sidebar bukan 4xx/5xx; simpan tangkapan layar di scratchpad dan bandingkan dengan tiga gambar mockup.
- [ ] **Step 4: Jalankan** Delivery Gate antislop (laporan PASS/FAIL per butir).
- [ ] **Step 5: Commit** `docs: perbarui DESIGN.md dan AGENTS.md untuk UI baru`.
