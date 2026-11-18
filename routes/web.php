<?php

use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\HubinReportController;
use App\Http\Controllers\MentorLinkController;
use App\Http\Controllers\MentorSignoffController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\SchoolAdminController;
use App\Http\Controllers\SchoolPaymentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

// Publik & Autentikasi
Route::get('/', [PublicController::class, 'home'])->name('home');
// Tentang, Harga, dan FAQ kini berupa section di landing page; URL lama tetap hidup lewat redirect permanen.
Route::redirect('/tentang', '/#tentang', 301)->name('about');
Route::redirect('/harga', '/#harga', 301)->name('pricing');
Route::redirect('/faq', '/#faq', 301)->name('faq');
Route::view('/syarat', 'public.terms')->name('terms');
Route::view('/privasi', 'public.privacy')->name('privacy');
Route::view('/kontak', 'public.contact')->name('contact');
// Pembimbing DUDI (tanpa akun): akses lewat token rahasia per penempatan.
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/ttd/{token}', [MentorSignoffController::class, 'show'])->name('mentor.show');
    Route::post('/ttd/{token}', [MentorSignoffController::class, 'sign'])->name('mentor.sign');
    Route::post('/ttd/{token}/catatan/{journal}', [MentorSignoffController::class, 'note'])->name('mentor.note');
});
Route::get('/sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/daftar-sekolah', [PublicController::class, 'registerSchoolForm'])->name('schools.register');
Route::post('/daftar-sekolah', [PublicController::class, 'registerSchoolSubmit'])->name('schools.register.store');
Route::get('/login', [PublicController::class, 'loginForm'])->name('login');
Route::post('/login', [PublicController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
Route::post('/demo-login', [PublicController::class, 'demoLogin'])->name('demo.login');
Route::post('/logout', [PublicController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('guest')->group(function (): void {
    Route::get('/lupa-sandi', [PublicController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/lupa-sandi', [PublicController::class, 'sendResetLink'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-sandi/{token}', [PublicController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-sandi', [PublicController::class, 'resetPassword'])->middleware('throttle:login')->name('password.store');
});

// Rute terotentikasi + Penegakan Konteks Tenant RLS
Route::middleware(['auth', 'tenant.context'])->group(function (): void {
    Route::get('/password', [PublicController::class, 'passwordForm'])->name('password.edit');
    Route::post('/password', [PublicController::class, 'updatePassword'])->middleware('throttle:login')->name('password.update');
    // Media proxy privat berbasis tenant
    Route::get('/media/{path}', [MediaProxyController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');

    // Tautan tanda tangan DUDI (guru pembimbing penempatan atau admin sekolah)
    Route::post('/placements/{placement}/mentor-link', [MentorLinkController::class, 'store'])->name('mentor-link.store');
    Route::post('/placements/{placement}/mentor-link/revoke', [MentorLinkController::class, 'destroy'])->name('mentor-link.destroy');

    // Generator Laporan PDF Ber-kop Sekolah
    Route::get('/reports/placements/{placement}/logbook.pdf', [ReportPdfController::class, 'studentLogbook'])
        ->name('reports.student-logbook');
    Route::get('/reports/periods/{period}/recap.pdf', [ReportPdfController::class, 'periodRecap'])
        ->name('reports.period-recap');

    // 1. SUPER ADMIN
    Route::prefix('super-admin')->middleware('role:super_admin')->name('super-admin.')->group(function (): void {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::get('/payments', [SchoolPaymentController::class, 'queue'])->name('payments');
        Route::get('/payments/{payment}/proof', [SchoolPaymentController::class, 'proof'])->name('payments.proof');
        Route::post('/payments/{payment}/review', [SchoolPaymentController::class, 'review'])->name('payments.review');
        Route::post('/schools/{school}/approve', [SuperAdminController::class, 'approve'])->name('schools.approve');
        Route::post('/users/{user}/reset-password', [SuperAdminController::class, 'resetSchoolAdminPassword'])->name('users.reset-password');
        Route::post('/schools/{school}/suspend', [SuperAdminController::class, 'suspend'])->name('schools.suspend');
        Route::post('/schools/{school}/quota', [SuperAdminController::class, 'updateQuota'])->name('schools.quota');
    });

    // 2. ADMIN SEKOLAH (KOORDINATOR HUBIN)
    Route::prefix('admin')->middleware('role:school_admin')->name('admin.')->group(function (): void {
        Route::get('/', [SchoolAdminController::class, 'index'])->name('index');
        Route::get('/hubin', [HubinReportController::class, 'show'])->name('hubin');
        Route::get('/payments', [SchoolPaymentController::class, 'index'])->name('payments');
        Route::get('/payments/{payment}/proof', [SchoolPaymentController::class, 'proof'])->name('payments.proof');
        Route::post('/payments/periods/{period}/groups', [SchoolPaymentController::class, 'createGroup'])->name('payments.groups.create');
        Route::post('/payments/groups/{group}/proof', [SchoolPaymentController::class, 'submit'])->name('payments.submit');
        Route::post('/payments/groups/{group}/stop', [SchoolPaymentController::class, 'stop'])->name('payments.groups.stop');
        Route::post('/payments/stop-school', [SchoolPaymentController::class, 'stop'])->name('payments.stop-school');
        Route::post('/plan/upgrade', [SchoolPaymentController::class, 'upgradePlan'])->name('plan.upgrade');
        Route::post('/plan/downgrade', [SchoolPaymentController::class, 'downgradePlan'])->name('plan.downgrade');
        Route::get('/periods', [SchoolAdminController::class, 'periods'])->name('periods');
        Route::post('/periods', [SchoolAdminController::class, 'storePeriod'])->name('periods.store');
        Route::post('/periods/{period}/toggle', [SchoolAdminController::class, 'togglePeriod'])->name('periods.toggle');

        Route::get('/companies', [SchoolAdminController::class, 'companies'])->name('companies');
        Route::post('/companies', [SchoolAdminController::class, 'storeCompany'])->name('companies.store');

        Route::get('/teachers', [SchoolAdminController::class, 'teachers'])->name('teachers');
        Route::post('/teachers', [SchoolAdminController::class, 'storeTeacher'])->name('teachers.store');
        Route::post('/teachers/import', [SchoolAdminController::class, 'importTeachers'])->name('teachers.import');
        Route::get('/teachers/template', [SchoolAdminController::class, 'downloadTeacherTemplate'])->name('teachers.template');

        Route::get('/students', [SchoolAdminController::class, 'students'])->name('students');
        Route::post('/students', [SchoolAdminController::class, 'storeStudent'])->name('students.store');
        Route::post('/students/import', [SchoolAdminController::class, 'importStudents'])->name('students.import');
        Route::get('/students/template', [SchoolAdminController::class, 'downloadStudentTemplate'])->name('students.template');

        Route::get('/placements', [SchoolAdminController::class, 'placements'])->name('placements');
        Route::post('/placements', [SchoolAdminController::class, 'storePlacement'])->name('placements.store');
        Route::post('/placements/{placement}/status', [SchoolAdminController::class, 'updatePlacementStatus'])->name('placements.status');

        Route::get('/jurnal-tertunda', [SchoolAdminController::class, 'pendingJournals'])->name('pending-journals');
        Route::get('/activity', [SchoolAdminController::class, 'activity'])->name('activity');
        Route::get('/export', [SchoolAdminController::class, 'exportData'])->name('export');
        Route::post('/deletion-request', [SchoolAdminController::class, 'requestDeletion'])->name('deletion.request');
        Route::post('/deletion-request/cancel', [SchoolAdminController::class, 'cancelDeletion'])->name('deletion.cancel');
        Route::get('/settings', [SchoolAdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [SchoolAdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/users/{user}/reset-password', [SchoolAdminController::class, 'resetUserPassword'])->name('users.reset-password');
        Route::get('/users/{user}/edit', [SchoolAdminController::class, 'editUser'])->name('users.edit');
        Route::post('/users/{user}', [SchoolAdminController::class, 'updateUser'])->name('users.update');
        Route::post('/users/{user}/toggle-active', [SchoolAdminController::class, 'toggleUserActive'])->name('users.toggle-active');
    });

    // 3. GURU PEMBIMBING
    Route::prefix('teacher')->middleware('role:teacher')->name('teacher.')->group(function (): void {
        Route::get('/', [TeacherController::class, 'index'])->name('index');
        Route::post('/journals/{journal}/approve', [TeacherController::class, 'approveJournal'])->name('journals.approve');
        Route::post('/journals/bulk-approve', [TeacherController::class, 'bulkApproveJournals'])->name('journals.bulk-approve');
        Route::post('/journals/{journal}/revision', [TeacherController::class, 'requestRevisionJournal'])->name('journals.revision');
        Route::post('/attendances/{attendance}/approve-exception', [TeacherController::class, 'approveAttendanceException'])->name('attendances.approve-exception');

        Route::get('/assessments', [TeacherController::class, 'assessments'])->name('assessments');
        Route::post('/assessments/{placement}', [TeacherController::class, 'saveAssessment'])->name('assessments.save');
    });

    // 4. SISWA PKL (MOBILE PWA)
    Route::prefix('student')->middleware('role:student')->name('student.')->group(function (): void {
        Route::get('/', [StudentController::class, 'home'])->name('home');
        Route::post('/check-in', [StudentController::class, 'checkIn'])->name('check-in');
        Route::post('/check-out', [StudentController::class, 'checkOut'])->name('check-out');
        Route::get('/journals', [StudentController::class, 'journals'])->name('journals');
        Route::post('/journals', [StudentController::class, 'storeJournal'])->name('journals.store');
        Route::get('/profile', [StudentController::class, 'profile'])->name('profile');
    });
});

require __DIR__.'/spec.php';
