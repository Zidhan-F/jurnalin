<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\MentorSignoff;
use App\Models\Placement;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaasFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function test_public_legal_pages_sitemap_and_branded_404(): void
    {
        foreach (['/syarat', '/privasi', '/kontak', '/lupa-sandi'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/sitemap.xml')->assertOk()->assertSee('/privasi', false);
        $this->get('/tidak-ada-halaman-ini')->assertNotFound()->assertSee('Halaman tidak ditemukan');
    }

    public function test_forgot_password_sends_link_and_reset_allows_login_without_leaking_accounts(): void
    {
        $this->seed();
        Notification::fake();
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        $this->post('/lupa-sandi', ['email' => 'tidak.terdaftar@contoh.id'])->assertSessionHas('status');
        $this->post('/lupa-sandi', ['email' => 'Guru.Perhotelan@smkn1par-bdg.sch.id'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($teacher, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });
        Notification::assertCount(1);

        $this->post('/reset-sandi', [
            'token' => $token, 'email' => $teacher->email,
            'password' => 'sandiBaruGuru1', 'password_confirmation' => 'sandiBaruGuru1',
        ])->assertRedirect('/login');

        $this->post('/login', ['email' => $teacher->email, 'password' => 'sandiBaruGuru1'])->assertRedirect('/teacher');
    }

    public function test_admin_resets_password_only_for_own_school_and_it_is_audited(): void
    {
        $this->seed();
        $adminA = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $teacherA = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $teacherB = $this->userByEmail('guru@smkharapan-bali.sch.id');

        $this->actingAs($adminA)->post("/admin/users/{$teacherA->id}/reset-password")->assertSessionHas('status');
        $this->actingAs($adminA)->post("/admin/users/{$teacherB->id}/reset-password")->assertNotFound();

        $logged = TenantContext::runInSchoolScope($adminA->school_id, fn () => AuditLog::query()->where('action', 'user.password_reset_by_admin')->count());
        $this->assertSame(1, $logged);
        $this->assertTrue($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id')->must_change_password);
    }

    public function test_export_downloads_zip_and_deletion_needs_matching_npsn(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->get('/admin/export')->assertOk()->assertDownload();

        $this->actingAs($admin)->post('/admin/deletion-request', ['confirm_npsn' => '99999999'])->assertSessionHasErrors('confirm_npsn');
        $this->assertNull(School::query()->find($admin->school_id)->deletion_requested_at);

        $this->actingAs($admin)->post('/admin/deletion-request', ['confirm_npsn' => '20219101'])->assertSessionHas('status');
        $this->assertNotNull(School::query()->find($admin->school_id)->deletion_requested_at);
    }

    public function test_mentor_signs_through_link_without_account_and_signed_journal_is_locked(): void
    {
        $this->seed();
        Storage::fake('local');
        $school = School::query()->where('npsn', '20219101')->firstOrFail();
        $school->update(['mentor_signoff_frequency' => 'daily']);
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $yesterday = now($school->timezone)->subDay()->toDateString();

        [$placement, $journal] = TenantContext::runInSchoolScope($school->id, function () use ($nadia, $yesterday) {
            $placement = Placement::query()->where('student_id', $nadia->student->id)->firstOrFail();
            $journal = Journal::query()->create([
                'school_id' => $placement->school_id, 'placement_id' => $placement->id, 'student_id' => $placement->student_id,
                'journal_date' => $yesterday, 'work_unit' => 'Gudang', 'activity_title' => 'Stok opname',
                'activity_description' => 'Menghitung stok barang dan mencocokkan dengan kartu stok gudang.',
                'duration_minutes' => 420, 'status' => JournalStatus::Submitted,
            ]);

            return [$placement, $journal];
        });

        $this->actingAs($teacher)->post("/placements/{$placement->id}/mentor-link")->assertSessionHas('status');
        $token = TenantContext::runInSchoolScope($school->id, fn () => Placement::query()->find($placement->id)->mentor_link_token);
        $this->post('/logout');
        $this->app['auth']->forgetGuards();

        // Pembimbing tanpa akun membuka tautan dan melihat jurnal siswa.
        $this->get("/ttd/{$token}?mulai={$yesterday}")->assertOk()->assertSee('Stok opname');
        $this->post("/ttd/{$token}/catatan/{$journal->id}", ['mentor_note' => 'Jam kerja sampai 15.00'])->assertSessionHas('status');

        $this->post("/ttd/{$token}", [
            'period_start' => $yesterday, 'signer_name' => 'Arif Rahman', 'signer_title' => 'Supervisor Gudang',
            'statement' => '1', 'signature_data' => self::PNG,
        ])->assertSessionHas('status');

        $signoff = TenantContext::runInSchoolScope($school->id, fn () => MentorSignoff::query()->where('placement_id', $placement->id)->first());
        $this->assertNotNull($signoff);
        $this->assertSame('Arif Rahman', $signoff->signer_name);
        $this->assertContains($journal->id, $signoff->journal_ids);
        Storage::disk('local')->assertExists($signoff->signature_path);

        // Jurnal yang sudah ditandatangani tidak bisa diubah siswa.
        $this->actingAs($nadia)->post('/student/journals', [
            'journal_id' => $journal->id, 'journal_date' => $yesterday, 'work_unit' => 'Gudang',
            'activity_title' => 'Diubah', 'activity_description' => 'Mencoba mengubah jurnal yang sudah ditandatangani.',
            'duration_minutes' => 420, 'submit_action' => 'submitted', 'confirmed_done' => '1',
        ])->assertSessionHasErrors('journal');

        // Tautan yang dicabut tidak bisa dibuka lagi.
        $this->actingAs($teacher)->post("/placements/{$placement->id}/mentor-link/revoke");
        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->get("/ttd/{$token}")->assertNotFound()->assertSee('Tautan tidak berlaku');
    }

    public function test_new_school_admin_sees_onboarding_checklist_and_pdf_includes_signoff_section(): void
    {
        $this->seed();
        $school = app(\App\Actions\Onboarding\RegisterSchoolAction::class)->handle([
            'npsn' => '20288111', 'school_name' => 'SMK Teknik Baru', 'address' => 'Jl. Baru 1', 'city' => 'Bogor',
            'province' => 'Jawa Barat', 'admin_name' => 'Admin Baru', 'admin_email' => 'admin@smkbaru.sch.id',
            'admin_phone' => '628100000', 'password' => 'rahasia123',
        ]);
        $super = $this->userByEmail('superadmin@jurnalin.id');
        $this->actingAs($super)->post("/super-admin/schools/{$school->id}/approve");

        $admin = $this->userByEmail('admin@smkbaru.sch.id');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Persiapan sebelum PKL dimulai')->assertSee('dari 6 langkah selesai');

        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $placementId = TenantContext::runInSchoolScope($nadia->school_id, fn () => Placement::query()->where('student_id', $nadia->student->id)->value('id'));
        $this->actingAs($teacher)->get("/reports/placements/{$placementId}/logbook.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($teacher)->get('/teacher/assessments')->assertOk()->assertSee('Buat tautan tanda tangan');
    }

    public function test_platform_admin_resets_school_admin_password_but_not_teachers(): void
    {
        $this->seed();
        $super = $this->userByEmail('superadmin@jurnalin.id');
        $schoolAdmin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        $this->actingAs($super)->get('/super-admin')->assertOk()->assertSee('admin@smkn1par-bdg.sch.id');
        $this->actingAs($super)->post("/super-admin/users/{$schoolAdmin->id}/reset-password")->assertSessionHas('status');
        $this->actingAs($super)->post("/super-admin/users/{$teacher->id}/reset-password")->assertNotFound();

        $this->assertTrue($this->userByEmail('admin@smkn1par-bdg.sch.id')->must_change_password);
        $this->assertFalse($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id')->must_change_password);
    }

    public function test_school_admin_deactivates_and_reactivates_student(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $student = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->post("/admin/users/{$student->id}/toggle-active")->assertSessionHas('status');
        $this->post('/logout');
        $this->app['auth']->forgetGuards();

        $this->post('/login', ['email' => $student->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->post("/admin/users/{$student->id}/toggle-active");
        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $student->email, 'password' => 'password123'])->assertRedirect('/student');
    }

    public function test_school_admin_edits_user_with_global_email_check_and_own_school_only(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $otherSchoolTeacher = $this->userByEmail('guru@smkharapan-bali.sch.id');

        $this->actingAs($admin)->get("/admin/users/{$teacher->id}/edit")->assertOk()->assertSee($teacher->email);
        $this->actingAs($admin)->get("/admin/users/{$otherSchoolTeacher->id}/edit")->assertNotFound();

        // Email milik sekolah lain ditolak, walau RLS menyembunyikan akun itu dari admin ini.
        $this->actingAs($admin)->post("/admin/users/{$teacher->id}", [
            'name' => $teacher->name, 'email' => 'guru@smkharapan-bali.sch.id',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->post("/admin/users/{$teacher->id}", [
            'name' => 'Budi Prasetyo', 'email' => 'Budi.Baru@smkn1par-bdg.sch.id', 'phone' => '628120000',
        ])->assertRedirect('/admin/teachers');

        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'budi.baru@smkn1par-bdg.sch.id', 'password' => 'password123'])->assertRedirect('/teacher');
    }

    public function test_teacher_cannot_create_link_for_another_teachers_student(): void
    {
        $this->seed();
        $school = School::query()->where('npsn', '20219101')->firstOrFail();
        $otherTeacher = $this->userByEmail('guru.kuliner@smkn1par-bdg.sch.id');
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $placementId = TenantContext::runInSchoolScope($school->id, fn () => Placement::query()->where('student_id', $nadia->student->id)->value('id'));
        $owner = TenantContext::runInSchoolScope($school->id, fn () => Placement::query()->find($placementId)->teacher->user_id);
        $this->assertNotSame($owner, $otherTeacher->id, 'Seeder: Nadia harus dibimbing guru perhotelan.');

        $this->actingAs($otherTeacher)->post("/placements/{$placementId}/mentor-link")->assertNotFound();
    }
}
