<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\JournalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Journal;
use App\Models\Passport;
use App\Models\Placement;
use App\Models\TeacherInternship;
use App\Models\UkkSession;
use App\Models\VisitReport;
use App\Support\FinalReportBuilder;
use App\Support\MediaStorage;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Dokumen PDF tambahan. Setiap unduhan melewati pemeriksaan peran + penugasan yang sama dengan layar. */
final class SpecReportController extends Controller
{
    /** M12: formulir nilai industri kosong untuk diisi dan ditandatangani pembimbing lapangan. */
    public function industryForm(Request $request, Placement $placement): Response
    {
        $this->authorizePlacement($request, $placement, allowStudent: false);
        $placement->load(['school', 'student.user', 'company', 'pklPeriod', 'teacher.user']);

        return Pdf::loadView('pdf.industry-form', ['placement' => $placement, 'school' => $placement->school, 'aspects' => $placement->pklPeriod->rubric()->industryAspects])
            ->setPaper('a4')->download('formulir-nilai-industri-'.Str::slug($placement->student->user->name).'.pdf');
    }

    /** P27: draf laporan akhir dari jurnal disetujui. Bagian yang belum diketahui menjadi placeholder. */
    public function finalReport(Request $request, Placement $placement): Response
    {
        $this->authorizePlacement($request, $placement, allowStudent: true);
        abort_unless($placement->school->hasFeature('ai'), 403, 'Laporan Akhir Otomatis termasuk add-on AI.');
        $report = FinalReportBuilder::build($placement);

        return Pdf::loadView('pdf.final-report', ['placement' => $placement, 'school' => $placement->school, 'report' => $report])
            ->setPaper('a4')->download('draf-laporan-akhir-pkl-'.Str::slug($placement->student->user->name).'.pdf');
    }

    public function certificate(Request $request, Certificate $certificate): Response
    {
        $user = $request->user();
        $allowed = $user->role === UserRole::SchoolAdmin
            || $user->student?->id === $certificate->student_id
            || ($certificate->placement && $certificate->placement->teacher_id === $user->teacher?->id);
        abort_unless($allowed, 403);
        $certificate->load('school');
        $url = route('verify.show', $certificate->verification_code);

        return Pdf::loadView('pdf.certificate', ['certificate' => $certificate, 'school' => $certificate->school, 'qr' => Qr::dataUri($url, 150), 'url' => $url])
            ->setPaper('a4', 'landscape')->download(Str::slug($certificate->number).'.pdf');
    }

    /** G09: laporan magang guru dari jurnal bersumber, dapat diedit sebelum dicetak. */
    public function internship(Request $request, TeacherInternship $internship): Response
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::SchoolAdmin || in_array($user->teacher?->id, [$internship->teacher_id, $internship->reviewer_teacher_id], true), 403);
        $internship->load(['teacher.user', 'company', 'school', 'journals' => fn ($q) => $q->orderBy('journal_date'), 'transferPlans', 'disseminations', 'evaluations.contact']);

        return Pdf::loadView('pdf.internship', ['internship' => $internship, 'school' => $internship->school])
            ->setPaper('a4')->download('laporan-magang-guru-'.Str::slug($internship->teacher->user->name).'.pdf');
    }

    /** U06: rekap & berita acara sesi UKK. */
    public function ukkMinutes(Request $request, UkkSession $session): Response
    {
        abort_unless($request->user()->role === UserRole::SchoolAdmin, 403);
        $session->load(['participants.student.user', 'participants.scores.examinerContact', 'participants.scores.examinerUser', 'school']);

        return Pdf::loadView('pdf.ukk-minutes', ['session' => $session, 'school' => $session->school])
            ->setPaper('a4')->download('berita-acara-ukk-'.Str::slug($session->name).'.pdf');
    }

    /** P30: PDF Paspor Kompetensi dengan QR menuju tautan paspor yang masih berlaku. */
    public function passport(Request $request): Response
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        abort_unless($request->user()->school->hasFeature('passport'), 403, 'Paspor Kompetensi termasuk paket Sekolah Pro.');
        $passport = Passport::query()->where('student_id', $student->id)->first();
        $data = PassportController::compile($student, $passport?->settings ?? PassportController::DEFAULT_SETTINGS);
        $url = $passport?->token && ! $passport->revoked_at && (! $passport->expires_at || $passport->expires_at->isFuture()) ? route('passport.public', $passport->token) : null;

        return Pdf::loadView('pdf.passport', ['data' => $data, 'student' => $student->load('user'), 'school' => $request->user()->school, 'qr' => $url ? Qr::dataUri($url, 130) : null])
            ->setPaper('a4')->download('paspor-kompetensi-'.Str::slug($request->user()->name).'.pdf');
    }

    public function visit(Request $request, VisitReport $visit): Response
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::SchoolAdmin || $visit->author_user_id === $user->id, 403);
        $visit->load(['company', 'author', 'school']);
        $names = Placement::query()->with('student.user')->whereIn('id', $visit->placement_ids ?? [])->get()->map(fn ($p) => $p->student->user->name);
        $photo = MediaStorage::absolutePath($visit->photo_path);

        return Pdf::loadView('pdf.visit', ['visit' => $visit, 'school' => $visit->school, 'names' => $names,
            'photo' => $photo ? 'data:'.mime_content_type($photo).';base64,'.base64_encode((string) file_get_contents($photo)) : null])
            ->setPaper('a4')->download('laporan-kunjungan-'.$visit->visit_date->format('Ymd').'.pdf');
    }

    private function authorizePlacement(Request $request, Placement $placement, bool $allowStudent): void
    {
        $user = $request->user();
        $allowed = $user->role === UserRole::SchoolAdmin
            || ($user->role === UserRole::Teacher && $placement->teacher_id === $user->teacher?->id)
            || ($allowStudent && $user->student?->id === $placement->student_id);
        abort_unless($allowed, 403);
    }
}
