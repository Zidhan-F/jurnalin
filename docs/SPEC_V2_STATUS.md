# Status Implementasi Spesifikasi Fitur v2.0

Pemetaan ID fitur di `Jurnalin_Master_Spesifikasi_Fitur_v2.docx` ke kode. Status: **Ada** (berjalan dan diuji), **Dasar** (berjalan dengan cakupan minimum), **Belum** (sengaja belum dibangun).

Semua tabel baru ada di migrasi `2026_11_17_000009_spec_v2_platform.php` dengan `school_id` + RLS FORCE. Rute baru di `routes/spec.php`, controller di `app/Http/Controllers/Spec/`, view di `resources/views/spec/`. Test penerimaan: `tests/Feature/SpecV2FeaturesTest.php`.

## Modul 01 Jurnalin PKL

| ID | Status | Lokasi utama |
|---|---|---|
| P01 Akun & magic link | Ada | `MagicLinkController`, tabel `login_tokens`, form di halaman masuk, tombol "Tautan masuk siswa" |
| P02 Mode Personal | Belum | Ditunda (V2): butuh tenant personal dan penautan data ke sekolah |
| P03 Periode, jadwal, libur, kebijakan presensi | Ada | `pkl_periods.work_days/holidays/require_location/require_selfie/backdate_days`, form di Periode & rubrik |
| P04 Penempatan + titik & radius | Ada | `placements.latitude/longitude/geofence_radius_m`, status Rencana/Ditawarkan/Dikonfirmasi/Aktif |
| P05-P07 Jurnal, QuickJournal, draf otomatis | Ada | `student/journals.blade.php` (template, aktivitas terakhir, autosave localStorage, pratinjau, konfirmasi kegiatan) |
| P08 Foto (maks. per sekolah, kompres) | Ada | `journals.photo_paths`, `schools.max_journal_photos` |
| P09 Izin/sakit resmi | Ada | `LeaveController`, tabel `leave_requests` |
| P10 Kalender | Ada | `PklMonitor::calendar`, `/student/kalender`, halaman pantau siswa |
| P11-P12 Review, Review Inbox, buka ulang beralasan | Ada | `/teacher/review`, `journal_versions`, batch hanya untuk jurnal yang dicentang |
| P13 Beranda siswa | Ada | `student/home.blade.php` (revisi, target, menu cepat) |
| P14 Dashboard guru | Ada | ringkasan, izin, presensi bertanda, peringatan |
| P15 Notifikasi + wa.me | Ada | `user_notifications`, `Notify`, lonceng di header |
| P16 Pencarian & filter | Ada | filter di jurnal siswa dan Review Inbox |
| P17 PDF standar | Ada | kode dokumen + penanda jurnal belum disetujui |
| P18 Cek kelengkapan | Ada | `PklMonitor::readiness` |
| P19 Pemantauan pintar | Ada | `PklMonitor::alerts`, `/pantau/peringatan` |
| P20 Peta TP | Ada | `learning_objectives`, `journal_objectives`, `/admin/tp` |
| P21-P23 Target, bimbingan, refleksi | Ada | `LearningController` |
| P24 Celah kompetensi | Dasar | daftar TP tanpa bukti + saran dari template |
| P25 / P29 Bukti Kerja & proyek | Ada | `work_proofs` (jenis hasil kerja / proyek) |
| P26 Asisten jurnal AI | Ada | `AiController::journalDraft`, `AiAssistant` (Claude, add-on AI; tanpa kunci memakai penyusun aturan) |
| P27 Laporan akhir otomatis | Ada | `FinalReportBuilder` + PDF (add-on AI) |
| P28 Ringkasan pembimbing | Ada | `/pantau/siswa/{id}/ringkasan-ai` (berbasis data, tanpa nilai otomatis) |
| P30 Paspor Kompetensi | Ada | `PassportController`, QR lokal (`bacon/bacon-qr-code`) |
| P31 Kilas balik | Ada | `/student/kilas-balik` |
| P32 Rubrik lanjutan | Dasar | aspek & bobot per periode dapat diubah; satu penilai, berversi lewat P39 |
| P33 Kunjungan | Ada | `VisitController` + PDF |
| P34 Lapor ke Guru | Ada | `SupportTicketController`, kategori sensitif langsung ke admin |
| P35 Offline draft | Dasar | draf lokal + penahan kirim saat offline; antrean sinkron penuh belum |
| P36 Latihan wawancara | Ada | `/student/latihan-wawancara` |
| P37 Presensi bertanda + persetujuan wali | Ada | `RecordCheckInAction` (tanda, perangkat, kebijakan, gating S13), tinjauan guru |
| P38 Impor Excel/Dapodik + pratinjau | Ada | `ImportController`, `import_logs` |
| P39 Nilai akhir TP, snapshot, revisi | Ada | `AssessmentRubric::tpTemplate`, `CalculateFinalScoreAction`, `assessment_versions` |
| P40 Template jurusan & perhotelan | Ada | `config/pkl.php`, `JournalTemplates` (dipilih otomatis dari program keahlian) |
| P41 Sertifikat QR + verifikasi publik | Ada | `CertificateController`, `/verifikasi/{kode}` |
| P42 WhatsApp otomatis | Dasar | `WhatsAppGateway` (driver `log` bawaan, `http` untuk penyedia BSP), `jurnalin:ringkasan-harian` |
| P43 Pembekalan pra-PKL | Ada | `ReadinessController` |

## Modul 02 Hubin, 03 Magang Guru, 04 Mentor Access, 05 Tracer, 06 UKK

| ID | Status | Catatan |
|---|---|---|
| H01-H10, H12, H13, H17 | Ada | `HubinController`: direktori + cek duplikat, profil 360, kontak, MoU/PKS + pengingat, kuota, penempatan & pemetaan massal, linimasa, pusat kendali, arsip tahunan CSV |
| H08 / H14 / H16 | Ada | `PartnerPortalController` (formulir PIC tanpa akun, ditinjau sekolah) |
| H11 | Ada | evaluasi kemitraan internal |
| H15 Industry Challenge | Belum | Vision; perlu aturan supervisi & kompensasi |
| H18 Peta DUDI | Ada | opt-in, agregat anonim, kelompok kecil disembunyikan |
| G01-G12 | Ada | `InternshipController`; guru tidak bisa meninjau jurnalnya sendiri; evaluasi industri via Mentor Access |
| M01-M11, M13, M14 | Ada | `MentorAccessController` + `MentorPortalController`; OTP email untuk pengesahan; "tidak dapat dinilai" |
| M12 | Ada | PDF formulir nilai industri + unggah foto |
| T01-T12 | Ada | `TracerController`; status ganda, tingkat respons, kelompok kecil disembunyikan |
| U01-U08 | Ada | `UkkController`; instrumen berversi, nilai per penguji, surat QR |

## Fondasi SaaS

| ID | Status | Catatan |
|---|---|---|
| S01-S04 | Ada | RLS untuk semua tabel baru; media proxy diperluas per kategori; zona waktu sesi DB disetel UTC |
| S05 | Dasar | penyetuju jurnal, maks. foto, kebijakan periode, aspek nilai |
| S06 | Dasar | paket & pembayaran manual yang sudah ada; gateway QRIS/VA belum |
| S07 | Ada | checklist onboarding lama + impor terpandu |
| S08 / S13 | Ada | Pusat privasi, DPA, persetujuan wali (kertas/WA/tautan digital), permintaan subjek data, `jurnalin:retensi` |
| S09 | Ada | `/super-admin/operasional` (agregat, tanpa isi jurnal) |
| S10 | Ada | PWA lama; semua layar baru diuji tanpa scroll horizontal di 375 px |
| S11 | Dasar | hash snapshot nilai, evaluasi mentor, sertifikat; bukan TTE tersertifikasi |
| S12 | Ada | `ai_requests` (tanpa isi), pagar perilaku di `AiAssistant::GUARDRAIL` |
| S14 | Ada | `tenant_features`, middleware `feature:*`, `/super-admin/modul` |

## Konfigurasi baru (.env)

- `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` (bawaan `claude-opus-5-5`): fitur AI. Tanpa kunci, fitur AI memakai penyusun berbasis aturan.
- `WHATSAPP_DRIVER` (`log`/`http`), `WHATSAPP_API_URL`, `WHATSAPP_API_TOKEN`, `WHATSAPP_COST_PER_MESSAGE`.
- `DB_TIMEZONE` (bawaan `UTC`).
- Jalankan `php artisan schedule:work` untuk ringkasan harian, pengingat MoU, dan retensi swafoto.
