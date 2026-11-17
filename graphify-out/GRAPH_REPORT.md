# Graph Report - jurnalin  (2026-11-17)

## Corpus Check
- 321 files · ~114,109 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 23 file(s) not represented in the graph (top: (none) 17, .example 1, .xml 1)

## Summary
- 2160 nodes · 4960 edges · 240 communities (71 shown, 169 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 154 edges (avg confidence: 0.89)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- SchoolPlan
- Illuminate\View\View
- Symfony\Component\HttpFoundation\Response
- SchoolAdminController.php
- Npsn
- Illuminate\Support\Facades\DB
- composer.json
- UserRole
- MentorSignoffController.php
- package.json
- ManageBillingGroupsAction.php
- web.php
- PublicController
- 3. Spesifikasi Detail 10 Tabel Inti
- Product Requirements Document (PRD) - Jurnalin
- TenantContext
- DESIGN.md - Arah Visual & Sistem Antarmuka Jurnalin
- manifest.json
- README.md
- Project Structure - Jurnalin
- companies.blade.php
- admin/index.blade.php
- periods.blade.php
- placements.blade.php
- settings.blade.php
- students.blade.php
- teachers.blade.php
- period-recap.blade.php
- student-logbook.blade.php
- public/home.blade.php
- login.blade.php
- register-school.blade.php
- student/home.blade.php
- journals.blade.php
- profile.blade.php
- super-admin/index.blade.php
- assessments.blade.php
- teacher/index.blade.php
- app.blade.php
- student-pwa.blade.php
- bootstrap/app.php
- MediaStorage
- Attendance
- JournalStatus
- Assessment
- UkkController.php
- password.blade.php
- MentorPortalController.php
- admin/payments.blade.php
- 403.blade.php
- render-logo.py
- 404.blade.php
- 419.blade.php
- 429.blade.php
- 500.blade.php
- 503.blade.php
- contact.blade.php
- forgot-password.blade.php
- privacy.blade.php
- reset-password.blade.php
- terms.blade.php
- UkkSession
- TestCase
- User
- AttendanceStatus
- BillingGroup
- Attendance
- SchoolPlansAndPaymentsTest.php
- AppServiceProvider.php
- CalculateFinalScoreAction
- PassportController
- BelongsToSchool
- School
- AppServiceProvider.php
- .handle
- ColorContrastTest
- super-admin/payments.blade.php
- GuestOtpCode
- activity.blade.php
- invalid.blade.php
- signoff.blade.php
- SaasFeaturesTest
- Illuminate\Http\RedirectResponse
- SaasFeaturesTest.php
- PlacementStatus
- Teacher
- SchoolPayment
- sw.js
- hubin.blade.php
- user-edit.blade.php
- Company
- .runInSchoolScope
- Illuminate\Database\Schema\Blueprint
- PklPeriodDurationTest
- AGENTS.md - Panduan Arsitektur & Konvensi Pengembangan Jurnalin
- Controller
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Illuminate\Http\Request
- StudentController.php
- PublicController.php
- SchoolPlanChangeTest.php
- .storeStudentUpload
- ReadinessController
- Illuminate\Database\Eloquent\Model
- PassportController.php
- JournalTemplates
- PklPeriod
- .request
- InternshipController.php
- TracerCampaign
- ImportController.php
- LearningTarget
- RegisterSchoolAction.php
- Student
- TracerController.php
- PlatformController.php
- 2026_11_16_000008_create_billing_groups.php
- TeacherInternship
- console.php
- .commit
- PartnerContact
- 2026_11_16_000006_add_saas_features.php
- 0001_01_01_000002_create_jobs_table.php
- PklMonitor.php
- DataRequest
- FinalReportBuilder.php
- scripts
- ImportLog
- .userEmailExists
- PartnerEvaluation
- config
- require
- require-dev
- 0001_01_01_000003_create_pkl_master_tables.php
- MentorAccessController.php
- 0001_01_01_000000_create_users_table.php
- Illuminate\Database\Eloquent\Concerns\HasUuids
- LearningController.php
- PartnerQuota
- VisitReport
- psr-4
- ImportStudentsAction
- pdf/passport.blade.php
- spec/participant.blade.php
- student/calendar.blade.php
- autoload-dev
- extra
- tenant
- pdf/final-report.blade.php
- industry-form.blade.php
- pdf/internship.blade.php
- ukk-minutes.blade.php
- visit.blade.php
- hub.blade.php
- portal/internship.blade.php
- portal/participant.blade.php
- public/passport.blade.php
- student/passport.blade.php
- certificate.blade.php
- certificates.blade.php
- import.blade.php
- admin/modules.blade.php
- objectives.blade.php
- admin/privacy.blade.php
- admin/readiness.blade.php
- alerts.blade.php
- archive.blade.php
- dudi-map.blade.php
- hubin/index.blade.php
- hubin/placements.blade.php
- hubin/show.blade.php
- admin.blade.php
- teacher.blade.php
- mentor-access.blade.php
- mentor-summary.blade.php
- guardian-consent.blade.php
- public/invalid.blade.php
- partner-form.blade.php
- tracer.blade.php
- verify.blade.php
- student/final-report.blade.php
- interview.blade.php
- journey.blade.php
- learning.blade.php
- leaves.blade.php
- student/readiness.blade.php
- tickets.blade.php
- super/modules.blade.php
- operations.blade.php
- review.blade.php
- tickets-inbox.blade.php
- tracer/index.blade.php
- tracer/show.blade.php
- ukk/index.blade.php
- ukk/show.blade.php
- visits.blade.php
- StudentLogbookPdfGenerator
- ManageBillingGroupsAction
- period.blade.php

## God Nodes (most connected - your core abstractions)
1. `Placement` - 104 edges
2. `BelongsToSchool` - 102 edges
3. `School` - 74 edges
4. `User` - 64 edges
5. `Controller` - 56 edges
6. `Journal` - 52 edges
7. `PklPeriod` - 48 edges
8. `TenantContext` - 44 edges
9. `AuditLog` - 37 edges
10. `UserRole` - 35 edges

## Surprising Connections (you probably didn't know these)
- `3.1 Validasi Absensi Geotagging & Kompresi Foto` --references--> `RecordCheckInAction`  [INFERRED]
  docs/TSD.md → app/Actions/Attendance/RecordCheckInAction.php
- `3. Empat Peran Pengguna & Rute Utama` --references--> `UserRole`  [INFERRED]
  AGENTS.md → app/Enums/UserRole.php
- `Stage 1: Fondasi Proyek, PostgreSQL 16 RLS & Tenant Onboarding (Minggu 2)` --references--> `Npsn`  [INFERRED]
  docs/DEVELOPMENT_STAGE.md → app/ValueObjects/Npsn.php
- `5. Strategi Pengujian Otomatis (`TenantIsolationTest`)` --references--> `TenantIsolationTest`  [INFERRED]
  docs/TSD.md → tests/Feature/TenantIsolationTest.php
- `Stage 4: Dashboard Guru, Penilaian Akhir & Laporan PDF (Minggu 5)` --references--> `CalculateFinalScoreAction`  [INFERRED]
  docs/DEVELOPMENT_STAGE.md → app/Actions/Assessment/CalculateFinalScoreAction.php

## Import Cycles
- None detected.

## Communities (240 total, 169 thin omitted)

### Community 0 - "SchoolPlan"
Cohesion: 0.17
Nodes (9): ChangeSchoolPlanAction, {closure#4}(), {closure#7}(), {closure#2}(), {closure#1}(), SchoolPlan, Free, Pro (+1 more)

### Community 1 - "Illuminate\View\View"
Cohesion: 0.07
Nodes (4): SchoolAdminController, AiController, MonitoringController, PlatformController

### Community 2 - "Symfony\Component\HttpFoundation\Response"
Cohesion: 0.19
Nodes (3): MentorSignoffController, MentorPortalController, PartnerPortalController

### Community 3 - "SchoolAdminController.php"
Cohesion: 0.05
Nodes (4): HubinReportController, MediaProxyController, ClassLogbookArchive, SpreadsheetHelper

### Community 4 - "Npsn"
Cohesion: 0.15
Nodes (5): 4. Aturan Penulisan Kode & Pola Desain, Npsn, CoordinatesTest, NpsnTest, PwaAssetsTest

### Community 5 - "Illuminate\Support\Facades\DB"
Cohesion: 0.08
Nodes (11): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}(), {closure#1}() (+3 more)

### Community 6 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 7 - "UserRole"
Cohesion: 0.17
Nodes (6): UserRole, SchoolAdmin, Student, SuperAdmin, Teacher, {closure#1}()

### Community 8 - "MentorSignoffController.php"
Cohesion: 0.10
Nodes (7): {closure#1}(), {closure#4}(), {closure#6}(), {closure#7}(), Placement, MentorSignoffPeriods, PklMonitor

### Community 9 - "package.json"
Cohesion: 0.06
Nodes (32): dependencies, alpinejs@^3.17.5, leaflet@^1.9.4, devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0 (+24 more)

### Community 10 - "ManageBillingGroupsAction.php"
Cohesion: 0.15
Nodes (5): {closure#5}(), {closure#9}(), {closure#9}(), {closure#1}(), GroupAccess

### Community 13 - "3. Spesifikasi Detail 10 Tabel Inti"
Cohesion: 0.11
Nodes (17): 1. Entity Relationship Diagram (ERD), 2. Prinsip Desain Skema, 3.10 `assessments`, 3.1 `schools` (Tabel Tenant), 3.2 `users`, 3.3 `teachers`, 3.4 `students`, 3.5 `companies` (Tempat PKL / DUDI Milik Sekolah) (+9 more)

### Community 14 - "Product Requirements Document (PRD) - Jurnalin"
Cohesion: 0.14
Nodes (13): 1.1 Latar Belakang Kurikulum, 1.2 Masalah Lapangan yang Ditemui, 1.3 Solusi MVP (Fokus Sekolah dengan 4 Peran), 1. Ringkasan Eksekutif & Konteks Masalah, 2. Profil Pengguna & Cakupan Akses (4 Peran), 3. Alur Kerja Inti End-to-End, 4. Spesifikasi Kebutuhan Fungsional (MVP), 5. Konfigurasi Fleksibel Per Sekolah (+5 more)

### Community 15 - "TenantContext"
Cohesion: 0.19
Nodes (6): {closure#2}(), SetTenantContextMiddleware, {closure#1}(), TenantContext, CLAUDE.md - Panduan Repositori Jurnalin, Ringkasan Aturan Wajib

### Community 16 - "DESIGN.md - Arah Visual & Sistem Antarmuka Jurnalin"
Cohesion: 0.14
Nodes (13): 1. Design Read & Liveliness Dials, 2. Identitas Visual & Palet Warna (Maksimal 3 Core + 1 Accent), 3. Tipografi & Hierarki Data, 4. Arsitektur Layar Berbasis Keputusan (Decision-Driven UI), 5. Kepatuhan State UI & Aksesibilitas (Hard Gates), 6. Standar Aksesibilitas WCAG 2.2 Level AA, A. Layar Utama Siswa (Mobile PWA `/student`), A. Token Warna (satu sumber di `resources/css/app.css`) (+5 more)

### Community 17 - "manifest.json"
Cohesion: 0.12
Nodes (16): background_color, categories, description, dir, display, display_override, icons, id (+8 more)

### Community 18 - "README.md"
Cohesion: 0.25
Nodes (7): About Laravel, Agentic Development, Code of Conduct, Contributing, Learning Laravel, License, Security Vulnerabilities

### Community 20 - "Project Structure - Jurnalin"
Cohesion: 0.40
Nodes (4): 1. Pohon Direktori Aktual, 2. Daftar Dependensi Minimal (*Lean Dependency List*), 3. Ponytail Audit pada Struktur Proyek (`/ponytail-audit`), Project Structure - Jurnalin

### Community 49 - "bootstrap/app.php"
Cohesion: 0.22
Nodes (4): EnsureRoleMiddleware, {closure#1}(), {closure#2}(), {closure#3}()

### Community 52 - "MediaStorage"
Cohesion: 0.13
Nodes (3): {closure#3}(), MediaStorage, {closure#9}()

### Community 53 - "Attendance"
Cohesion: 0.13
Nodes (4): {closure#1}(), {closure#2}(), Attendance, RespectsGroupAccess

### Community 54 - "JournalStatus"
Cohesion: 0.13
Nodes (7): SubmitJournalAction, JournalStatus, Approved, Draft, RevisionNeeded, Submitted, {closure#1}()

### Community 56 - "UkkController.php"
Cohesion: 0.05
Nodes (22): {closure#1}(), {closure#2}(), AssessmentRubric, {closure#2}(), {closure#3}(), 1.1 Komponen Teknologi, 1. Arsitektur Sistem & Pilihan Teknologi, 2.1 Prinsip Dual-Layer Tenant Isolation (+14 more)

### Community 59 - "MentorPortalController.php"
Cohesion: 0.19
Nodes (11): {closure#1}(), {closure#10}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}() (+3 more)

### Community 60 - "admin/payments.blade.php"
Cohesion: 0.50
Nodes (3): admin.group-stop, admin.plan-box, layouts.app

### Community 81 - "TestCase"
Cohesion: 0.08
Nodes (15): Development Stage & Roadmap - Jurnalin, Ringkasan Tahapan, Stage 5: Persiapan Produksi & Pilot Sekolah Pertama (Minggu 6), AssessmentCalculationTest, AttendanceGeofenceTest, AuthAndUploadSecurityTest, ExampleTest, JournalStateMachineTest (+7 more)

### Community 82 - "User"
Cohesion: 0.11
Nodes (5): {closure#1}(), {closure#2}(), {closure#5}(), User, AiAssistant

### Community 83 - "AttendanceStatus"
Cohesion: 0.19
Nodes (8): RecordCheckInAction, RecordCheckOutAction, {closure#1}(), Coordinates, Stage 3: Portal Mobile Siswa - Livewire PWA (Minggu 4), {closure#1}(), {closure#2}(), {closure#3}()

### Community 84 - "BillingGroup"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#2}(), {closure#1}(), SubmitPaymentAction, {closure#1}(), BillingGroup

### Community 85 - "Attendance"
Cohesion: 0.12
Nodes (5): Journal, {closure#2}(), {closure#3}(), {closure#5}(), {closure#7}()

### Community 88 - "CalculateFinalScoreAction"
Cohesion: 0.20
Nodes (5): CalculateFinalScoreAction, {closure#1}(), {closure#4}(), {closure#12}(), {closure#9}()

### Community 90 - "BelongsToSchool"
Cohesion: 0.12
Nodes (4): BelongsToSchool, PartnerRequest, ReadinessModule, UserNotification

### Community 92 - "AppServiceProvider.php"
Cohesion: 0.18
Nodes (4): AppServiceProvider, {closure#1}(), {closure#2}(), {closure#3}()

### Community 96 - "GuestOtpCode"
Cohesion: 0.12
Nodes (6): AccountCreated, GuestOtpCode, MagicLoginLink, SchoolApproved, {closure#20}(), {closure#25}()

### Community 102 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.09
Nodes (5): SchoolPaymentController, HubinController, InternshipController, MentorAccessController, Modul 02 Hubin, 03 Magang Guru, 04 Mentor Access, 05 Tracer, 06 UKK

### Community 104 - "PlacementStatus"
Cohesion: 0.11
Nodes (7): PlacementStatus, Active, Cancelled, Completed, Confirmed, Offered, Scheduled

### Community 113 - ".runInSchoolScope"
Cohesion: 0.14
Nodes (7): {closure#12}(), down(), {closure#1}(), {closure#10}(), {closure#5}(), SchoolPlansAndPaymentsTest, SpecV2FeaturesTest

### Community 114 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.06
Nodes (58): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+50 more)

### Community 116 - "AGENTS.md - Panduan Arsitektur & Konvensi Pengembangan Jurnalin"
Cohesion: 0.17
Nodes (10): 1. Identitas Produk & Stack Terimplementasi, 2. Arsitektur Multi-Tenant & Kebijakan Zero-Leak, 3. Empat Peran Pengguna & Rute Utama, 5. Aturan UI & Desain, 6. Menjalankan & Memverifikasi Proyek, AGENTS.md - Panduan Arsitektur & Konvensi Pengembangan Jurnalin, antislop, Fondasi SaaS (+2 more)

### Community 117 - "Controller"
Cohesion: 0.06
Nodes (9): Controller, CertificateController, ImportController, NotificationController, PassportController, SupportTicketController, VisitController, Notify (+1 more)

### Community 118 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.07
Nodes (6): Certificate, JournalVersion, LeaveRequest, MentorEvaluation, SupportTicket, UkkScore

### Community 129 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.11
Nodes (4): {closure#2}(), LearningObjective, MentorSignoff, PartnerAgreement

### Community 130 - "PassportController.php"
Cohesion: 0.08
Nodes (5): {closure#1}(), AuditLog, Passport, ReadinessResult, UkkParticipant

### Community 132 - "PklPeriod"
Cohesion: 0.14
Nodes (3): PklPeriod, PeriodRecapPdfGenerator, {closure#11}()

### Community 133 - ".request"
Cohesion: 0.10
Nodes (5): MagicLinkController, {closure#2}(), {closure#3}(), {closure#4}(), PrivacyController

### Community 134 - "InternshipController.php"
Cohesion: 0.11
Nodes (3): DisseminationRecord, InternshipJournal, TransferPlan

### Community 140 - "RegisterSchoolAction.php"
Cohesion: 0.15
Nodes (6): SchoolStatus, Active, PendingVerification, Suspended, {closure#1}(), DatabaseSeeder

### Community 142 - "TracerController.php"
Cohesion: 0.09
Nodes (4): {closure#14}(), {closure#15}(), TracerController, TracerResponse

### Community 143 - "PlatformController.php"
Cohesion: 0.18
Nodes (4): {closure#1}(), {closure#2}(), EnsureFeatureMiddleware, Features

### Community 144 - "2026_11_16_000008_create_billing_groups.php"
Cohesion: 0.15
Nodes (10): {closure#1}(), {closure#10}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+2 more)

### Community 146 - "console.php"
Cohesion: 0.18
Nodes (3): WhatsAppGateway, {closure#12}(), {closure#3}()

### Community 149 - "2026_11_16_000006_add_saas_features.php"
Cohesion: 0.18
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}()

### Community 150 - "0001_01_01_000002_create_jobs_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 151 - "PklMonitor.php"
Cohesion: 0.11
Nodes (6): AttendanceStatus, Absent, Late, Permission, Present, Sick

### Community 154 - "FinalReportBuilder.php"
Cohesion: 0.14
Nodes (3): FinalReportBuilder, LearningObjectives, {closure#4}()

### Community 155 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 160 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 161 - "require"
Cohesion: 0.29
Nodes (7): require, anthropic-ai/sdk, bacon/bacon-qr-code, barryvdh/laravel-dompdf, laravel/framework, laravel/tinker, php

### Community 162 - "require-dev"
Cohesion: 0.29
Nodes (7): require-dev, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 163 - "0001_01_01_000003_create_pkl_master_tables.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 166 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 167 - "Illuminate\Database\Eloquent\Concerns\HasUuids"
Cohesion: 0.11
Nodes (4): AiRequest, GuardianConsent, MessageLog, TenantFeature

### Community 168 - "LearningController.php"
Cohesion: 0.11
Nodes (3): MentoringNote, WeeklyReflection, WorkProof

### Community 173 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 175 - "ImportStudentsAction"
Cohesion: 0.22
Nodes (8): {closure#1}(), ImportStudentsAction, Rincian Pekerjaan Per Tahap, Stage 0: Validasi Masalah & Pengumpulan Artefak (Minggu 1), Stage 1: Fondasi Proyek, PostgreSQL 16 RLS & Tenant Onboarding (Minggu 2), Stage 2: Onboarding Data Sekolah & Penempatan (Minggu 3), Stage 4: Dashboard Guru, Penilaian Akhir & Laporan PDF (Minggu 5), Stage 6: Ekspansi V2 (Setelah Validasi Pasar MVP)

### Community 177 - "pdf/passport.blade.php"
Cohesion: 0.50
Nodes (3): pdf.partials.letterhead, pdf.partials.style, spec.partials.passport-body

### Community 178 - "spec/participant.blade.php"
Cohesion: 0.50
Nodes (3): layouts.app, spec.partials.calendar, spec.partials.readiness

### Community 179 - "student/calendar.blade.php"
Cohesion: 0.50
Nodes (3): layouts.student-pwa, spec.partials.calendar, spec.partials.readiness

### Community 180 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 181 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 242 - "ManageBillingGroupsAction"
Cohesion: 0.24
Nodes (3): {closure#2}(), {closure#4}(), ManageBillingGroupsAction

## Knowledge Gaps
- **136 isolated node(s):** `Present`, `Late`, `Sick`, `Permission`, `Absent` (+131 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 856 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **169 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Placement` connect `MentorSignoffController.php` to `Illuminate\View\View`, `PassportController.php`, `SchoolAdminController.php`, `Illuminate\Database\Eloquent\Model`, `HubinController.php`, `MonitoringController.php`, `ManageBillingGroupsAction.php`, `ImportController.php`, `web.php`, `RegisterSchoolAction.php`, `console.php`, `.commit`, `PklMonitor.php`, `FinalReportBuilder.php`, `MentorAccessController.php`, `Illuminate\Database\Eloquent\Concerns\HasUuids`, `LearningController.php`, `MediaStorage`, `Attendance`, `JournalStatus`, `MentorPortalController.php`, `TestCase`, `User`, `AttendanceStatus`, `Attendance`, `SchoolPlansAndPaymentsTest.php`, `CalculateFinalScoreAction`, `PassportController`, `BelongsToSchool`, `School`, `.handle`, `SaasFeaturesTest.php`, `PlacementStatus`, `Teacher`, `StudentLogbookPdfGenerator`, `.runInSchoolScope`, `Controller`, `Illuminate\Http\Request`, `StudentController.php`, `SpecV2FeaturesTest.php`?**
  _High betweenness centrality (0.079) - this node is a cross-community bridge._
- **What connects `Present`, `Late`, `Sick` to the rest of the system?**
  _136 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\View\View` be split into smaller, more focused modules?**
  _Cohesion score 0.07198228128460686 - nodes in this community are weakly interconnected._
- **Why does `School` connect `School` to `SchoolPlan`, `Illuminate\View\View`, `Illuminate\Database\Eloquent\Model`, `SchoolAdminController.php`, `Illuminate\Support\Facades\DB`, `UserRole`, `HubinController.php`, `ManageBillingGroupsAction.php`, `ImportController.php`, `web.php`, `RegisterSchoolAction.php`, `PlatformController.php`, `TenantContext`, `console.php`, `.commit`, `ReadinessController.php`, `FinalReportBuilder.php`, `.userEmailExists`, `Illuminate\Database\Eloquent\Concerns\HasUuids`, `ImportStudentsAction`, `JournalStatus`, `UkkController.php`, `TenantContext.php`, `TestCase`, `User`, `AttendanceStatus`, `BillingGroup`, `CalculateFinalScoreAction`, `GuestOtpCode`, `SaasFeaturesTest.php`, `.runInSchoolScope`, `PublicController.php`, `SchoolPlanChangeTest.php`, `SchoolPlanChangeTest.php`, `SpecV2FeaturesTest.php`, `ReadinessController`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Should `SchoolAdminController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.05279034690799397 - nodes in this community are weakly interconnected._
- **Why does `PklPeriod` connect `PklPeriod` to `Illuminate\View\View`, `Illuminate\Database\Eloquent\Model`, `SchoolAdminController.php`, `Illuminate\Database\Eloquent\Concerns\HasUuids`, `HubinController.php`, `MonitoringController.php`, `ManageBillingGroupsAction.php`, `ImportController.php`, `RegisterSchoolAction.php`, `StudentLogbookPdfGenerator`, `ManageBillingGroupsAction`, `PklPeriodDurationTest`, `BillingGroup`, `SchoolPlansAndPaymentsTest.php`, `UkkController.php`, `BelongsToSchool`, `SchoolPlanChangeTest.php`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Should `Npsn` be split into smaller, more focused modules?**
  _Cohesion score 0.14736842105263157 - nodes in this community are weakly interconnected._