<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Assessment\CalculateFinalScoreAction;
use App\Actions\Attendance\RecordCheckInAction;
use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\GuardianConsent;
use App\Models\GuestAccessGrant;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\MentorEvaluation;
use App\Models\PartnerContact;
use App\Models\Placement;
use App\Models\School;
use App\Models\SupportTicket;
use App\Models\UkkParticipant;
use App\Models\UkkSession;
use App\Notifications\GuestOtpCode;
use App\Support\TenantContext;
use App\ValueObjects\Coordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Kriteria penerimaan Spesifikasi Fitur v2 (bagian XII). */
class SpecV2FeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function schoolA(): School
    {
        return School::query()->where('npsn', '20219101')->firstOrFail();
    }

    private function schoolB(): School
    {
        return TenantContext::runAsSuperAdmin(fn () => School::query()->where('npsn', '50103822')->firstOrFail());
    }

    private function nadiaPlacement(): Placement
    {
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');

        return TenantContext::runInSchoolScope($this->schoolA()->id, fn () => Placement::query()->with(['company', 'pklPeriod', 'student'])->where('student_id', $nadia->student->id)->firstOrFail());
    }

    public function test_every_new_page_renders_for_each_role(): void
    {
        $this->seed();
        $placement = $this->nadiaPlacement();
        $pages = [
            'admin@smkn1par-bdg.sch.id' => ['/admin/impor', '/admin/privasi', '/admin/tp', '/admin/sertifikat', '/admin/pembekalan', '/admin/modul', '/admin/hubin/pusat',
                '/admin/mitra/'.$placement->company_id, '/admin/hubin/penempatan', '/admin/hubin/arsip', '/admin/peta-dudi', '/admin/magang-guru', '/admin/ukk', '/admin/tracer',
                '/kunjungan', '/laporan-siswa', '/akses-mentor', '/pantau/peringatan', '/pantau/siswa/'.$placement->id, '/notifikasi', '/privasi-saya', '/admin/periods'],
            'guru.perhotelan@smkn1par-bdg.sch.id' => ['/teacher', '/teacher/review', '/teacher/assessments', '/teacher/magang', '/kunjungan', '/laporan-siswa', '/akses-mentor',
                '/pantau/siswa/'.$placement->id, '/pantau/siswa/'.$placement->id.'/ringkasan-ai'],
            'siswa.nadia@smkn1par-bdg.sch.id' => ['/student', '/student/journals', '/student/izin', '/student/lapor', '/student/kalender', '/student/belajar', '/student/paspor',
                '/student/pembekalan', '/student/laporan-akhir', '/student/latihan-wawancara', '/student/kilas-balik', '/student/profile', '/privasi-saya'],
            'superadmin@jurnalin.id' => ['/super-admin/modul', '/super-admin/operasional'],
        ];
        foreach ($pages as $email => $urls) {
            $user = $this->userByEmail($email);
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
        $this->actingAs($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id'))->get('/reports/placements/'.$placement->id.'/form-industri.pdf')->assertOk();
        $this->get('/verifikasi')->assertOk();
    }

    public function test_location_and_selfie_are_never_stored_without_guardian_consent(): void
    {
        $this->seed();
        $placement = $this->nadiaPlacement();
        TenantContext::runInSchoolScope($placement->school_id, function () use ($placement): void {
            GuardianConsent::query()->where('student_id', $placement->student_id)->update(['withdrawn_at' => now()]);
            $placement->student->refresh();
            $this->assertFalse($placement->student->trackingAllowed());

            $far = new Coordinates($placement->company->latitude + 0.05, $placement->company->longitude);
            $attendance = (new RecordCheckInAction)->handle($placement->fresh(['company', 'pklPeriod', 'student', 'school']), $far, 10, 'schools/x/attendances/selfie.jpg');

            $this->assertNull($attendance->check_in_lat);
            $this->assertNull($attendance->check_in_photo_path);
            $this->assertNull($attendance->check_in_distance_m);
            $this->assertTrue($attendance->is_within_radius);
        });
    }

    public function test_leave_request_is_approved_by_assigned_teacher_and_rejection_needs_reason(): void
    {
        $this->seed();
        $placement = $this->nadiaPlacement();
        $student = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $date = TenantContext::runInSchoolScope($placement->school_id, fn () => collect($placement->pklPeriod->scheduledDates(now()->addDays(10)))->first(fn ($d) => $d >= now()->toDateString()));
        $this->assertNotNull($date);

        $this->actingAs($student)->post('/student/izin', ['type' => 'sick', 'start_date' => $date, 'end_date' => $date, 'reason' => 'Demam tinggi sejak malam'])->assertSessionHasNoErrors();
        $leave = TenantContext::runInSchoolScope($placement->school_id, fn () => LeaveRequest::query()->firstOrFail());

        $this->actingAs($teacher)->post("/teacher/leaves/{$leave->id}/decide", ['decision' => 'rejected'])->assertSessionHasErrors('decision_note');
        $this->actingAs($teacher)->post("/teacher/leaves/{$leave->id}/decide", ['decision' => 'approved'])->assertSessionHasNoErrors();

        TenantContext::runInSchoolScope($placement->school_id, function () use ($placement, $date, $leave): void {
            $this->assertSame('approved', $leave->fresh()->status);
            $att = Attendance::query()->where('placement_id', $placement->id)->whereDate('attendance_date', $date)->firstOrFail();
            $this->assertSame(AttendanceStatus::Sick, $att->status);
            $this->assertSame($leave->id, $att->leave_request_id);
        });
    }

    public function test_journal_versions_reopen_with_reason_and_assessment_revision_keeps_old_snapshot(): void
    {
        $this->seed();
        $placement = $this->nadiaPlacement();
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $journal = TenantContext::runInSchoolScope($placement->school_id, fn () => Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::Submitted)->first()
            ?? Journal::query()->create(['school_id' => $placement->school_id, 'placement_id' => $placement->id, 'student_id' => $placement->student_id, 'journal_date' => now()->toDateString(),
                'work_unit' => 'FO', 'activity_title' => 'Check-in tamu', 'activity_description' => 'Melayani check-in tamu rombongan.', 'status' => JournalStatus::Submitted]));

        $this->actingAs($teacher)->post("/teacher/journals/{$journal->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($teacher)->post("/pantau/jurnal/{$journal->id}/buka-ulang", ['reason' => 'pendek'])->assertSessionHasErrors('reason');
        $this->actingAs($teacher)->post("/pantau/jurnal/{$journal->id}/buka-ulang", ['reason' => 'Tanggal kegiatan keliru, mohon diperbaiki'])->assertSessionHasNoErrors();

        TenantContext::runInSchoolScope($placement->school_id, function () use ($journal, $placement, $teacher): void {
            $fresh = $journal->fresh();
            $this->assertSame(JournalStatus::RevisionNeeded, $fresh->status);
            $this->assertGreaterThanOrEqual(2, $fresh->versions()->count());

            $action = new CalculateFinalScoreAction;
            $codes = fn ($aspects) => collect($aspects)->mapWithKeys(fn ($a) => [$a['code'] => 85])->all();
            $rubric = $placement->pklPeriod->rubric();
            $a = $action->handle($placement->fresh(), $teacher->teacher, $codes($rubric->industryAspects), $codes($rubric->schoolAspects), finalize: true);
            $this->assertNotNull($a->snapshot_hash);
            $this->assertNotEmpty($a->achievement_description);

            $this->expectExceptionMessage('terkunci');
            try {
                $action->handle($placement->fresh(), $teacher->teacher, $codes($rubric->industryAspects), $codes($rubric->schoolAspects));
            } finally {
                $revised = $action->openRevision($a->fresh(), 'Nilai industri salah salin dari formulir');
                $this->assertSame(2, $revised->version);
                $this->assertNull($revised->finalized_at);
                $this->assertSame(1, Assessment::query()->find($a->id)->versions()->count());
            }
        });
    }

    public function test_certificate_is_issued_after_final_grade_and_public_verification_shows_revocation(): void
    {
        $this->seed();
        $placement = $this->nadiaPlacement();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->post("/admin/sertifikat/{$placement->id}")->assertSessionHasErrors('certificate');

        TenantContext::runInSchoolScope($placement->school_id, function () use ($placement, $teacher): void {
            Journal::query()->where('placement_id', $placement->id)->whereIn('status', ['submitted', 'revision_needed'])->update(['status' => 'approved']);
            $rubric = $placement->pklPeriod->rubric();
            $s = fn ($aspects) => collect($aspects)->mapWithKeys(fn ($a) => [$a['code'] => 90])->all();
            (new CalculateFinalScoreAction)->handle($placement->fresh(), $teacher->teacher, $s($rubric->industryAspects), $s($rubric->schoolAspects), finalize: true);
        });
        $this->actingAs($admin)->post("/admin/sertifikat/{$placement->id}")->assertSessionHasNoErrors();
        $cert = TenantContext::runInSchoolScope($placement->school_id, fn () => Certificate::query()->firstOrFail());

        $this->get('/verifikasi/'.$cert->verification_code)->assertOk()->assertSee('Dokumen asli dan berlaku')->assertDontSee('NISN');
        $this->actingAs($admin)->post("/admin/sertifikat/item/{$cert->id}/cabut", ['reason' => 'Diterbitkan untuk periode yang salah'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/verifikasi/'.$cert->verification_code)->assertOk()->assertSee('Dokumen dicabut');
        $this->get('/verifikasi/TIDAKADA123')->assertNotFound();
    }

    public function test_mentor_access_is_scoped_revocable_and_signing_requires_otp(): void
    {
        $this->seed();
        Notification::fake();
        $placement = $this->nadiaPlacement();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $contact = TenantContext::runInSchoolScope($placement->school_id, fn () => PartnerContact::query()->create(['school_id' => $placement->school_id, 'company_id' => $placement->company_id,
            'name' => 'Ibu Rina', 'email' => 'rina@hotel.test', 'kind' => 'mentor']));

        $this->actingAs($admin)->post('/akses-mentor/tautan', ['partner_contact_id' => $contact->id, 'placement_ids' => [$placement->id], 'days' => 30, 'can_evaluate' => 1])->assertSessionHasNoErrors();
        $url = session('magic_link.url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        auth()->logout();

        $this->get("/akses/{$token}")->assertOk()->assertSee('Ibu Rina');
        $other = TenantContext::runAsSuperAdmin(fn () => Placement::query()->where('id', '!=', $placement->id)->firstOrFail());
        $this->get("/akses/{$token}/peserta/{$other->id}")->assertNotFound();

        $aspects = $placement->pklPeriod->rubric()->industryAspects;
        $scores = collect($aspects)->mapWithKeys(fn ($a, $i) => [$a['code'] => $i === 0 ? 'NA' : '88'])->all();
        $this->post("/akses/{$token}/evaluasi/{$placement->id}", ['scores' => $scores])->assertSessionHasNoErrors();
        $ev = TenantContext::runInSchoolScope($placement->school_id, fn () => MentorEvaluation::query()->firstOrFail());
        $this->assertSame('NA', $ev->scores[$aspects[0]['code']]);

        $this->post("/akses/{$token}/sahkan/{$ev->id}", ['statement' => 1])->assertSessionHasErrors('otp');
        $this->post("/akses/{$token}/otp");
        $code = null;
        Notification::assertSentOnDemand(GuestOtpCode::class, function (GuestOtpCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->post("/akses/{$token}/otp", ['otp' => '000000'])->assertSessionHasErrors('otp');
        $this->post("/akses/{$token}/otp", ['otp' => $code])->assertSessionHasNoErrors();
        $this->post("/akses/{$token}/sahkan/{$ev->id}", ['statement' => 1])->assertSessionHasNoErrors();
        $this->assertSame('final', TenantContext::runInSchoolScope($placement->school_id, fn () => $ev->fresh()->status));

        $grant = TenantContext::runInSchoolScope($placement->school_id, fn () => GuestAccessGrant::query()->firstOrFail());
        $this->actingAs($admin)->post("/akses-mentor/tautan/{$grant->id}/cabut")->assertSessionHasNoErrors();
        auth()->logout();
        $this->get("/akses/{$token}")->assertNotFound();
    }

    public function test_ukk_examiner_scores_after_identity_check_and_session_finalizes(): void
    {
        $this->seed();
        Notification::fake();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $placement = $this->nadiaPlacement();

        $this->actingAs($admin)->post('/admin/ukk', ['name' => 'UKK Perhotelan 1', 'academic_year' => '2026/2027', 'concentration' => 'Perhotelan', 'venue' => 'Lab FO',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'quota' => 10])->assertRedirect();
        $session = TenantContext::runInSchoolScope($placement->school_id, fn () => UkkSession::query()->firstOrFail());
        $this->actingAs($admin)->post("/admin/ukk/{$session->id}/peserta", ['student_ids' => [$placement->student_id]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/ukk/{$session->id}/penguji", ['name' => 'Pak Budi', 'email' => 'budi@hotel.test'])->assertSessionHasNoErrors();
        $token = basename(parse_url(session('magic_link.url'), PHP_URL_PATH));
        $participant = TenantContext::runInSchoolScope($placement->school_id, fn () => UkkParticipant::query()->firstOrFail());
        auth()->logout();

        $scores = ['persiapan' => 80, 'proses' => 90, 'hasil' => 85, 'sikap' => 95];
        $this->post("/akses/{$token}/ukk/{$participant->id}", ['scores' => $scores])->assertSessionHasErrors('otp');
        $this->post("/akses/{$token}/otp");
        $code = null;
        Notification::assertSentOnDemand(GuestOtpCode::class, function (GuestOtpCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->post("/akses/{$token}/otp", ['otp' => $code]);
        $this->post("/akses/{$token}/ukk/{$participant->id}", ['scores' => $scores])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post("/admin/ukk/{$session->id}/final")->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/ukk/{$session->id}/surat")->assertSessionHasNoErrors();
        TenantContext::runInSchoolScope($placement->school_id, function () use ($participant): void {
            $p = $participant->fresh();
            $this->assertEqualsWithDelta(87.75, $p->final_score, 0.01); // 12 + 36 + 25.5 + 14.25
            $this->assertSame(1, Certificate::query()->where('kind', 'ukk')->count());
        });
    }

    public function test_sensitive_report_goes_only_to_school_admin(): void
    {
        $this->seed();
        $student = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $this->actingAs($student)->post('/student/lapor', ['category' => 'guru', 'urgency' => 'urgent', 'recipient' => 'teacher', 'body' => 'Ada masalah dengan guru pembimbing saya.'])->assertSessionHasNoErrors();
        $ticket = TenantContext::runInSchoolScope($this->schoolA()->id, fn () => SupportTicket::query()->firstOrFail());
        $this->assertSame('school_admin', $ticket->recipient);
        $this->actingAs($teacher)->get('/laporan-siswa')->assertOk()->assertDontSee('Ada masalah dengan guru pembimbing saya.');
        $this->actingAs($teacher)->post("/laporan-siswa/{$ticket->id}", ['status' => 'resolved', 'note' => 'coba'])->assertNotFound();
        $this->actingAs($admin)->get('/laporan-siswa')->assertOk()->assertSee('Ada masalah dengan guru pembimbing saya.');
    }

    public function test_modules_are_gated_per_tenant_and_new_tables_are_isolated(): void
    {
        $this->seed();
        $adminB = $this->userByEmail('admin@smkharapan-bali.sch.id');
        $this->actingAs($adminB)->get('/admin/hubin/pusat')->assertRedirect('/admin/modul');
        $this->actingAs($adminB)->get('/admin/ukk')->assertRedirect('/admin/modul');

        $schoolA = $this->schoolA();
        $schoolB = $this->schoolB();
        $consentB = TenantContext::runInSchoolScope($schoolB->id, fn () => GuardianConsent::query()->firstOrFail());
        TenantContext::runInSchoolScope($schoolA->id, function () use ($consentB): void {
            $this->assertSame(0, GuardianConsent::query()->whereKey($consentB->id)->count());
            // RLS juga menolak di tingkat SQL mentah, tanpa global scope Eloquent.
            $this->assertSame(0, DB::table('guardian_consents')->where('id', $consentB->id)->count());
        });
        $teacherA = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $placementB = TenantContext::runInSchoolScope($schoolB->id, fn () => Placement::query()->firstOrFail());
        $this->actingAs($teacherA)->get('/pantau/siswa/'.$placementB->id)->assertNotFound();
    }

    public function test_magic_link_logs_in_once(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $student = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $this->actingAs($admin)->post("/users/{$student->id}/magic-link")->assertSessionHasNoErrors();
        $url = session('magic_link.url');
        auth()->logout();
        $this->flushSession();

        $this->get(parse_url($url, PHP_URL_PATH))->assertRedirect('/student');
        $this->assertAuthenticatedAs($student);
        auth()->logout();
        $this->get(parse_url($url, PHP_URL_PATH))->assertRedirect('/login');
        $this->assertGuest();
    }
}
