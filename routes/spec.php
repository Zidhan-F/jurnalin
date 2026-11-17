<?php

/*
| Rute Spesifikasi Fitur v2.0. Dimuat dari routes/web.php.
| Rute publik tanpa akun selalu berbasis token acak (hash disimpan) dan dibatasi throttle.
| Rute terotentikasi selalu melewati 'tenant.context' sebelum route model binding.
*/

use App\Http\Controllers\Spec\AiController;
use App\Http\Controllers\Spec\CertificateController;
use App\Http\Controllers\Spec\HubinController;
use App\Http\Controllers\Spec\ImportController;
use App\Http\Controllers\Spec\InternshipController;
use App\Http\Controllers\Spec\LearningController;
use App\Http\Controllers\Spec\LeaveController;
use App\Http\Controllers\Spec\MagicLinkController;
use App\Http\Controllers\Spec\MentorAccessController;
use App\Http\Controllers\Spec\MentorPortalController;
use App\Http\Controllers\Spec\MonitoringController;
use App\Http\Controllers\Spec\NotificationController;
use App\Http\Controllers\Spec\PartnerPortalController;
use App\Http\Controllers\Spec\PassportController;
use App\Http\Controllers\Spec\PlatformController;
use App\Http\Controllers\Spec\PrivacyController;
use App\Http\Controllers\Spec\ReadinessController;
use App\Http\Controllers\Spec\SpecReportController;
use App\Http\Controllers\Spec\SupportTicketController;
use App\Http\Controllers\Spec\TracerController;
use App\Http\Controllers\Spec\UkkController;
use App\Http\Controllers\Spec\VisitController;
use Illuminate\Support\Facades\Route;

// ---------- Publik tanpa akun ----------
Route::middleware('throttle:30,1')->group(function (): void {
    Route::post('/masuk-link', [MagicLinkController::class, 'request'])->middleware('guest')->name('magic.request');
    Route::get('/masuk/{token}', [MagicLinkController::class, 'consume'])->name('magic.consume');

    Route::get('/verifikasi', [CertificateController::class, 'verifyForm'])->name('verify.form');
    Route::get('/verifikasi/{code}', [CertificateController::class, 'verify'])->name('verify.show');

    Route::get('/persetujuan/{token}', [PrivacyController::class, 'guardianForm'])->name('consent.form');
    Route::post('/persetujuan/{token}', [PrivacyController::class, 'guardianSubmit'])->name('consent.submit');

    Route::get('/akses/{token}', [MentorPortalController::class, 'hub'])->name('portal.hub');
    Route::get('/akses/{token}/peserta/{placement}', [MentorPortalController::class, 'participant'])->name('portal.participant');
    Route::post('/akses/{token}/jurnal/{journal}', [MentorPortalController::class, 'reviewJournal'])->name('portal.journal');
    Route::post('/akses/{token}/evaluasi/{placement}', [MentorPortalController::class, 'saveEvaluation'])->name('portal.evaluation');
    Route::post('/akses/{token}/otp', [MentorPortalController::class, 'sendOtp'])->name('portal.otp');
    Route::post('/akses/{token}/sahkan/{evaluation}', [MentorPortalController::class, 'confirmEvaluation'])->name('portal.confirm');
    Route::get('/akses/{token}/magang/{internship}', [MentorPortalController::class, 'internship'])->name('portal.internship');
    Route::post('/akses/{token}/magang/{internship}', [MentorPortalController::class, 'evaluateInternship'])->name('portal.internship.evaluate');
    Route::post('/akses/{token}/ukk/{participant}', [MentorPortalController::class, 'scoreUkk'])->name('portal.ukk.score');

    Route::get('/mitra/{token}', [PartnerPortalController::class, 'show'])->name('partner.form');
    Route::post('/mitra/{token}', [PartnerPortalController::class, 'submit'])->name('partner.submit');

    Route::get('/paspor/{token}', [PassportController::class, 'publicView'])->name('passport.public');

    Route::get('/tracer/{token}', [TracerController::class, 'form'])->name('tracer.form');
    Route::post('/tracer/{token}', [TracerController::class, 'submit'])->name('tracer.submit');
});

// ---------- Terotentikasi ----------
Route::get('/robots.txt', fn () => response()->view('public.robots')->header('Content-Type', 'text/plain; charset=UTF-8'));

Route::middleware(['auth', 'tenant.context'])->group(function (): void {
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifikasi/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/privasi-saya', [PrivacyController::class, 'center'])->name('privacy.center');
    Route::post('/privasi-saya/permintaan', [PrivacyController::class, 'storeRequest'])->name('privacy.request');
    Route::get('/privasi-saya/ekspor', [PrivacyController::class, 'exportMine'])->name('privacy.export');
    Route::post('/pengaturan/whatsapp', [PrivacyController::class, 'toggleWhatsapp'])->name('privacy.whatsapp');

    // Laporan & dokumen PDF tambahan (P17, M12, P41, G09, U06, U07, P27, P30)
    Route::get('/reports/placements/{placement}/form-industri.pdf', [SpecReportController::class, 'industryForm'])->name('reports.industry-form');
    Route::get('/reports/placements/{placement}/laporan-akhir.pdf', [SpecReportController::class, 'finalReport'])->name('reports.final-report');
    Route::get('/reports/sertifikat/{certificate}.pdf', [SpecReportController::class, 'certificate'])->name('reports.certificate');
    Route::get('/reports/magang/{internship}.pdf', [SpecReportController::class, 'internship'])->name('reports.internship');
    Route::get('/reports/ukk/{session}/berita-acara.pdf', [SpecReportController::class, 'ukkMinutes'])->name('reports.ukk-minutes');
    Route::get('/reports/paspor.pdf', [SpecReportController::class, 'passport'])->name('reports.passport');
    Route::get('/reports/kunjungan/{visit}.pdf', [SpecReportController::class, 'visit'])->name('reports.visit');

    // Persetujuan wali (S13) dicatat guru pembimbing atau admin sekolah.
    Route::middleware('role:school_admin,teacher')->group(function (): void {
        Route::post('/consents/{student}', [PrivacyController::class, 'recordConsent'])->name('consents.record');
        Route::post('/consents/{student}/link', [PrivacyController::class, 'consentLink'])->name('consents.link');
        Route::post('/consents/item/{consent}/withdraw', [PrivacyController::class, 'withdrawConsent'])->name('consents.withdraw');
        Route::post('/users/{user}/magic-link', [MagicLinkController::class, 'issue'])->name('magic.issue');

        // Mentor Access (M01-M14) dikelola guru (untuk binaannya) dan admin.
        Route::get('/akses-mentor', [MentorAccessController::class, 'index'])->name('mentor-access.index');
        Route::post('/akses-mentor/kontak', [MentorAccessController::class, 'storeContact'])->name('mentor-access.contact');
        Route::post('/akses-mentor/tautan', [MentorAccessController::class, 'issue'])->name('mentor-access.issue');
        Route::post('/akses-mentor/tautan/{grant}/cabut', [MentorAccessController::class, 'revoke'])->name('mentor-access.revoke');
        Route::post('/akses-mentor/tautan/{grant}/pengingat', [MentorAccessController::class, 'remind'])->name('mentor-access.remind');

        Route::get('/kunjungan', [VisitController::class, 'index'])->name('visits.index');
        Route::post('/kunjungan', [VisitController::class, 'store'])->name('visits.store');

        Route::get('/laporan-siswa', [SupportTicketController::class, 'inbox'])->name('tickets.inbox');
        Route::post('/laporan-siswa/{ticket}', [SupportTicketController::class, 'update'])->name('tickets.update');

        Route::get('/pantau/siswa/{placement}', [MonitoringController::class, 'participant'])->name('monitor.participant');
        Route::get('/pantau/peringatan', [MonitoringController::class, 'alerts'])->name('monitor.alerts');
        Route::post('/pantau/siswa/{placement}/target', [LearningController::class, 'storeTarget'])->name('learning.target.store');
        Route::post('/pantau/target/{target}/selesai', [LearningController::class, 'closeTarget'])->name('learning.target.close');
        Route::post('/pantau/siswa/{placement}/bimbingan', [LearningController::class, 'storeMentoring'])->name('learning.mentoring.store');
        Route::post('/pantau/bimbingan/{note}/tutup', [LearningController::class, 'closeMentoring'])->name('learning.mentoring.close');
        Route::post('/pantau/refleksi/{reflection}/komentar', [LearningController::class, 'commentReflection'])->name('learning.reflection.comment');
        Route::post('/pantau/bukti-kerja/{proof}/tinjau', [LearningController::class, 'reviewProof'])->name('learning.proof.review');
        Route::post('/pantau/jurnal/{journal}/tp', [LearningController::class, 'verifyObjectives'])->name('learning.objectives.verify');
        Route::post('/pantau/jurnal/{journal}/buka-ulang', [LeaveController::class, 'reopenJournal'])->name('journals.reopen');
        Route::get('/pantau/siswa/{placement}/ringkasan-ai', [AiController::class, 'mentorSummary'])->name('ai.mentor-summary');
    });

    // ---------- SISWA ----------
    Route::prefix('student')->middleware('role:student')->name('student.')->group(function (): void {
        Route::get('/izin', [LeaveController::class, 'studentIndex'])->name('leaves');
        Route::post('/izin', [LeaveController::class, 'studentStore'])->name('leaves.store');
        Route::get('/lapor', [SupportTicketController::class, 'studentIndex'])->name('tickets');
        Route::post('/lapor', [SupportTicketController::class, 'studentStore'])->name('tickets.store');
        Route::get('/kalender', [MonitoringController::class, 'studentCalendar'])->name('calendar');
        Route::get('/belajar', [LearningController::class, 'studentHub'])->name('learning');
        Route::post('/refleksi', [LearningController::class, 'storeReflection'])->name('reflections.store');
        Route::post('/bukti-kerja', [LearningController::class, 'storeProof'])->name('proofs.store');
        Route::post('/target/{target}', [LearningController::class, 'submitTarget'])->name('targets.submit');
        Route::post('/bimbingan/{note}', [LearningController::class, 'respondMentoring'])->name('mentoring.respond');
        Route::post('/journals/{journal}/tp', [LearningController::class, 'claimObjectives'])->name('journals.objectives');
        Route::get('/paspor', [PassportController::class, 'edit'])->name('passport');
        Route::post('/paspor', [PassportController::class, 'update'])->name('passport.update');
        Route::post('/paspor/cabut', [PassportController::class, 'revoke'])->name('passport.revoke');
        Route::get('/pembekalan', [ReadinessController::class, 'studentIndex'])->name('readiness');
        Route::post('/pembekalan/{module}', [ReadinessController::class, 'submitQuiz'])->name('readiness.submit');
        Route::post('/ai/draf-jurnal', [AiController::class, 'journalDraft'])->middleware('throttle:20,1')->name('ai.journal-draft');
        Route::get('/laporan-akhir', [AiController::class, 'finalReport'])->name('final-report');
        Route::get('/latihan-wawancara', [AiController::class, 'interview'])->name('interview');
        Route::post('/latihan-wawancara', [AiController::class, 'interviewFeedback'])->middleware('throttle:20,1')->name('interview.feedback');
        Route::get('/kilas-balik', [LearningController::class, 'journey'])->name('journey');
    });

    // ---------- GURU ----------
    Route::prefix('teacher')->middleware('role:teacher')->name('teacher.')->group(function (): void {
        Route::get('/review', [MonitoringController::class, 'reviewInbox'])->name('review');
        Route::post('/attendances/{attendance}/review', [LeaveController::class, 'reviewAttendance'])->name('attendances.review');
        Route::post('/leaves/{leave}/decide', [LeaveController::class, 'decide'])->name('leaves.decide');
        Route::post('/assessments/{placement}/revision', [LeaveController::class, 'openAssessmentRevision'])->name('assessments.revision');

        Route::get('/magang', [InternshipController::class, 'teacherIndex'])->name('internship');
        Route::post('/magang/{internship}/jurnal', [InternshipController::class, 'storeJournal'])->name('internship.journal');
        Route::post('/magang/{internship}/rencana', [InternshipController::class, 'storePlan'])->name('internship.plan');
        Route::post('/magang/rencana/{plan}', [InternshipController::class, 'updatePlan'])->name('internship.plan.update');
        Route::post('/magang/{internship}/diseminasi', [InternshipController::class, 'storeDissemination'])->name('internship.dissemination');
        Route::post('/magang/{internship}/laporan', [InternshipController::class, 'saveReport'])->name('internship.report');
        Route::post('/magang/jurnal/{journal}/tinjau', [InternshipController::class, 'reviewJournal'])->name('internship.review');
    });

    // ---------- ADMIN SEKOLAH ----------
    Route::prefix('admin')->middleware('role:school_admin')->name('admin.')->group(function (): void {
        Route::get('/periods/{period}', [MonitoringController::class, 'period'])->name('periods.show');
        Route::post('/periods/{period}/policy', [MonitoringController::class, 'updatePeriodPolicy'])->name('periods.policy');
        Route::post('/placements/{placement}/location', [MonitoringController::class, 'updatePlacementLocation'])->name('placements.location');

        Route::get('/impor', [ImportController::class, 'index'])->name('import');
        Route::post('/impor/pratinjau', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/impor/proses', [ImportController::class, 'commit'])->name('import.commit');
        Route::get('/impor/template/{kind}', [ImportController::class, 'template'])->name('import.template');

        Route::get('/privasi', [PrivacyController::class, 'admin'])->name('privacy');
        Route::post('/privasi/dpa', [PrivacyController::class, 'acceptDpa'])->name('privacy.dpa');
        Route::post('/privasi/retensi', [PrivacyController::class, 'updateRetention'])->name('privacy.retention');
        Route::post('/privasi/permintaan/{dataRequest}', [PrivacyController::class, 'answerRequest'])->name('privacy.answer');

        Route::get('/tp', [LearningController::class, 'objectives'])->name('objectives');
        Route::post('/tp', [LearningController::class, 'storeObjective'])->name('objectives.store');
        Route::post('/tp/{objective}', [LearningController::class, 'updateObjective'])->name('objectives.update');

        Route::get('/sertifikat', [CertificateController::class, 'index'])->name('certificates');
        Route::post('/sertifikat/{placement}', [CertificateController::class, 'issue'])->name('certificates.issue');
        Route::post('/sertifikat/item/{certificate}/cabut', [CertificateController::class, 'revoke'])->name('certificates.revoke');

        Route::get('/pembekalan', [ReadinessController::class, 'admin'])->name('readiness');
        Route::post('/pembekalan', [ReadinessController::class, 'store'])->name('readiness.store');
        Route::post('/pembekalan/{module}/toggle', [ReadinessController::class, 'toggle'])->name('readiness.toggle');

        Route::get('/modul', [PlatformController::class, 'schoolModules'])->name('modules');
        Route::post('/whatsapp', [PlatformController::class, 'schoolWhatsapp'])->name('whatsapp');

        Route::middleware('feature:hubin')->group(function (): void {
            Route::get('/hubin/pusat', [HubinController::class, 'index'])->name('hubin.center');
            Route::get('/mitra/{company}', [HubinController::class, 'show'])->name('partners.show');
            Route::post('/mitra/{company}/status', [HubinController::class, 'updateStatus'])->name('partners.status');
            Route::post('/mitra/{company}/kontak', [HubinController::class, 'storeContact'])->name('partners.contact');
            Route::post('/mitra/{company}/dokumen', [HubinController::class, 'storeAgreement'])->name('partners.agreement');
            Route::post('/mitra/{company}/kuota', [HubinController::class, 'storeQuota'])->name('partners.quota');
            Route::post('/mitra/{company}/undangan', [HubinController::class, 'storeRequest'])->name('partners.request');
            Route::post('/mitra/permintaan/{partnerRequest}', [HubinController::class, 'reviewRequest'])->name('partners.request.review');
            Route::post('/mitra/{company}/evaluasi', [HubinController::class, 'storeEvaluation'])->name('partners.evaluation');
            Route::get('/hubin/penempatan', [HubinController::class, 'placements'])->name('hubin.placements');
            Route::post('/hubin/penempatan', [HubinController::class, 'bulkPlace'])->name('hubin.bulk-place');
            Route::post('/hubin/pembimbing', [HubinController::class, 'bulkAssign'])->name('hubin.bulk-assign');
            Route::get('/hubin/arsip', [HubinController::class, 'archive'])->name('hubin.archive');
            Route::get('/hubin/arsip.csv', [HubinController::class, 'archiveCsv'])->name('hubin.archive-csv');
            Route::get('/peta-dudi', [HubinController::class, 'dudiMap'])->name('dudi-map');
            Route::post('/peta-dudi', [HubinController::class, 'toggleDudiOptIn'])->name('dudi-map.toggle');
        });

        Route::middleware('feature:internship')->group(function (): void {
            Route::get('/magang-guru', [InternshipController::class, 'admin'])->name('internships');
            Route::post('/magang-guru', [InternshipController::class, 'store'])->name('internships.store');
            Route::post('/magang-guru/{internship}/status', [InternshipController::class, 'updateStatus'])->name('internships.status');
        });

        Route::middleware('feature:ukk')->group(function (): void {
            Route::get('/ukk', [UkkController::class, 'index'])->name('ukk');
            Route::post('/ukk', [UkkController::class, 'store'])->name('ukk.store');
            Route::get('/ukk/{session}', [UkkController::class, 'show'])->name('ukk.show');
            Route::post('/ukk/{session}/peserta', [UkkController::class, 'addParticipants'])->name('ukk.participants');
            Route::post('/ukk/{session}/instrumen', [UkkController::class, 'updateInstrument'])->name('ukk.instrument');
            Route::post('/ukk/{session}/penguji', [UkkController::class, 'inviteExaminer'])->name('ukk.examiner');
            Route::post('/ukk/{session}/nilai/{participant}', [UkkController::class, 'scoreInternal'])->name('ukk.score');
            Route::post('/ukk/{session}/final', [UkkController::class, 'finalize'])->name('ukk.finalize');
            Route::post('/ukk/{session}/surat', [UkkController::class, 'issueCertificates'])->name('ukk.certificates');
            Route::get('/ukk/{session}/rekap.csv', [UkkController::class, 'recapCsv'])->name('ukk.csv');
        });

        Route::middleware('feature:tracer')->group(function (): void {
            Route::get('/tracer', [TracerController::class, 'index'])->name('tracer');
            Route::post('/tracer', [TracerController::class, 'store'])->name('tracer.store');
            Route::post('/tracer/lulusan', [TracerController::class, 'markGraduates'])->name('tracer.graduates');
            Route::get('/tracer/{campaign}', [TracerController::class, 'show'])->name('tracer.show');
            Route::get('/tracer/{campaign}/rekap.csv', [TracerController::class, 'exportCsv'])->name('tracer.csv');
        });
    });

    // ---------- SUPER ADMIN ----------
    Route::prefix('super-admin')->middleware('role:super_admin')->name('super-admin.')->group(function (): void {
        Route::get('/modul', [PlatformController::class, 'modules'])->name('modules');
        Route::post('/modul/{school}', [PlatformController::class, 'toggleModule'])->name('modules.toggle');
        Route::get('/operasional', [PlatformController::class, 'operations'])->name('operations');
    });
});
