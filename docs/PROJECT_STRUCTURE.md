# Project Structure - Jurnalin

Dokumen ini memetakan tata letak direktori aktual proyek **Jurnalin** di `c:\Users\Zidhan\Downloads\jurnalin` berbasis **Laravel 13** yang ramping, terorganisir berdasarkan domain bisnis, dan bebas lapisan abstraksi berlebih.

---

## 1. Pohon Direktori Aktual

```text
jurnalin/
├── AGENTS.md                           # Panduan arsitektur & konvensi agen coding
├── CLAUDE.md                           # Pointer proyek untuk asisten AI
├── DESIGN.md                           # Sistem desain, token warna & aturan antislop-ui
├── start-jurnalin.bat                  # Peluncur lokal Windows (migrate, test, build, serve)
├── composer.json
├── package.json
├── vite.config.js
├── docs/
│   ├── PRD.md                          # Product Requirements Document
│   ├── TSD.md                          # Technical Specification Document
│   ├── DATABASE_SCHEMA.md              # DDL PostgreSQL, Indexes & RLS
│   ├── DEVELOPMENT_STAGE.md            # Roadmap eksekusi Minggu 1 s/d V2
│   └── PROJECT_STRUCTURE.md            # Dokumen ini
├── app/
│   ├── Actions/                        # Single-purpose transactional business logic
│   │   ├── Assessment/
│   │   │   └── CalculateFinalScoreAction.php
│   │   ├── Attendance/
│   │   │   ├── RecordCheckInAction.php
│   │   │   └── RecordCheckOutAction.php
│   │   ├── Journal/
│   │   │   ├── SubmitJournalAction.php
│   │   │   └── ValidateJournalAction.php
│   │   └── Onboarding/
│   │       ├── ImportStudentsAction.php
│   │       ├── ImportTeachersAction.php
│   │       └── RegisterSchoolAction.php
│   ├── Contracts/
│   │   └── ReportGenerator.php
│   ├── Enums/                          # State Machines & Domain Constants
│   │   ├── AttendanceStatus.php
│   │   ├── JournalStatus.php
│   │   ├── PlacementStatus.php
│   │   ├── SchoolStatus.php
│   │   └── UserRole.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── MediaProxyController.php
│   │   │   ├── PublicController.php
│   │   │   ├── ReportPdfController.php
│   │   │   ├── SchoolAdminController.php
│   │   │   ├── StudentController.php
│   │   │   ├── SuperAdminController.php
│   │   │   └── TeacherController.php
│   │   └── Middleware/
│   │       ├── EnsureRoleMiddleware.php
│   │       └── SetTenantContextMiddleware.php
│   ├── Models/
│   │   ├── Concerns/
│   │   │   └── BelongsToSchool.php     # Global Scope + auto-assign school_id
│   │   ├── Assessment.php
│   │   ├── Attendance.php
│   │   ├── Company.php
│   │   ├── Journal.php
│   │   ├── PklPeriod.php
│   │   ├── Placement.php
│   │   ├── School.php
│   │   ├── Student.php
│   │   ├── Teacher.php
│   │   └── User.php
│   ├── Services/
│   │   └── Export/
│   │       ├── PeriodRecapPdfGenerator.php
│   │       └── StudentLogbookPdfGenerator.php
│   ├── Support/
│   │   ├── MediaStorage.php            # Penyimpanan foto terisolasi per school_id
│   │   ├── SpreadsheetHelper.php       # Baca/tulis .xlsx & .csv via ZipArchive native
│   │   └── TenantContext.php           # Scoped helper set_config PostgreSQL RLS
│   └── ValueObjects/                   # Newtype wrappers (readonly class)
│       ├── AssessmentRubric.php
│       ├── Coordinates.php
│       └── Npsn.php
├── bootstrap/
│   └── app.php                         # Registrasi middleware priority sebelum SubstituteBindings
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 0001_01_01_000003_create_pkl_master_tables.php
│   │   ├── 0001_01_01_000004_create_pkl_operational_tables.php
│   │   └── 0001_01_01_000005_enable_postgresql_rls_policies.php
│   └── seeders/
│       └── DatabaseSeeder.php          # Super Admin + Sekolah A (Bandung) + Sekolah B (Bali) + Sekolah C (Pending)
├── public/
│   ├── manifest.json                   # Konfigurasi PWA Add-to-Home-Screen siswa
│   └── build/                          # Manifest & aset siap pakai (diperbarui penuh saat `npm run build`)
├── resources/
│   ├── css/
│   │   └── app.css                     # Token warna DESIGN.md + Tailwind v4
│   ├── js/
│   │   └── app.js                      # Alpine.js + Leaflet + Canvas WebP compression + Haversine
│   └── views/
│       ├── admin/                      # Panel Admin Sekolah (index, periods, companies, teachers, students, placements, settings)
│       ├── layouts/
│       │   ├── app.blade.php
│       │   └── student-pwa.blade.php
│       ├── pdf/
│       │   ├── partials/letterhead.blade.php
│       │   ├── period-recap.blade.php
│       │   └── student-logbook.blade.php
│       ├── public/                     # home, register-school, login
│       ├── student/                    # home, journals, profile
│       ├── super-admin/                # index
│       └── teacher/                    # index, assessments
├── routes/
│   ├── console.php
│   └── web.php
└── tests/
    ├── Feature/
    │   ├── AssessmentCalculationTest.php
    │   ├── AttendanceGeofenceTest.php
    │   ├── ExampleTest.php
    │   ├── JournalStateMachineTest.php
    │   ├── SchoolRegistrationAndPdfTest.php
    │   ├── StudentExcelImportTest.php
    │   └── TenantIsolationTest.php
    └── Unit/
        ├── CoordinatesTest.php
        ├── ExampleTest.php
        └── NpsnTest.php
```

---

## 2. Daftar Dependensi Minimal (*Lean Dependency List*)

| Paket | Kategori | Alasan Wajib Ada |
|---|---|---|
| `laravel/framework` (`^13.0`) | Core | Framework utama aplikasi |
| `barryvdh/laravel-dompdf` (`^3.1`) | Ekspor PDF | Cetak buku jurnal dan rekap nilai ber-kop surat sekolah |
| `phpunit/phpunit` (`^12.0`) | Testing (Dev) | Test runner untuk isolasi multi-tenant, geofence, rubrik, dan impor Excel |
| `tailwindcss` + `@tailwindcss/vite` | Frontend (NPM) | Styling sesuai token warna `DESIGN.md` |
| `alpinejs` + `leaflet` | Frontend (NPM) | Interaktivitas ringan mobile siswa dan pemilih pin koordinat tempat PKL OpenStreetMap |

---

## 3. Ponytail Audit pada Struktur Proyek (`/ponytail-audit`)

- `delete: app/Repositories/ dan seluruh Repository Interface. Eloquent Model + BelongsToSchool sudah mencukupi.`
- `delete: laravel/breeze, filament/filament, maatwebsite/excel, dan spatie/laravel-permission pada fase MVP agar proyek ringan, cepat di-install, dan 100% mengikuti DESIGN.md.`
- `native: openspout/phpspreadsheet diganti dengan App\Support\SpreadsheetHelper berbasis ZipArchive & SimpleXMLElement bawaan PHP untuk membaca dan membuat templat .xlsx serta .csv.`
- `native: stancl/tenancy diganti dengan SetTenantContextMiddleware + BelongsToSchool + migrasi PostgreSQL FORCE ROW LEVEL SECURITY.`
- `stdlib: library hitung jarak eksternal diganti metode Haversine 15 baris pada App\ValueObjects\Coordinates.`

Status struktur: `Lean already. Ship.`
