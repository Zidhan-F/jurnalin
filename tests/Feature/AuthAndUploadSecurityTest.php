<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Assessment\CalculateFinalScoreAction;
use App\Models\Attendance;
use App\Models\Placement;
use App\Models\School;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAndUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_login_and_session_restore_work_under_rls_with_mixed_case_email(): void
    {
        $this->seed();

        // Email ber-huruf kapital seperti hasil keyboard HP tetap harus cocok.
        $this->post('/login', ['email' => 'Guru.Perhotelan@smkn1par-bdg.sch.id', 'password' => 'password123'])
            ->assertRedirect('/teacher');

        // Lupakan user di memori: request berikut harus memuat ulang user dari sesi lewat provider RLS.
        $this->app['auth']->forgetGuards();
        $this->get('/teacher')->assertOk();
    }

    public function test_new_account_is_forced_to_change_default_password(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->post('/admin/teachers', [
            'name' => 'Guru Baru', 'email' => 'guru.baru@smkn1par-bdg.sch.id', 'max_mentees' => 10,
        ])->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->app['auth']->forgetGuards();

        $this->post('/login', ['email' => 'guru.baru@smkn1par-bdg.sch.id', 'password' => 'password123']);
        $this->get('/teacher')->assertRedirect('/password');

        $this->post('/password', [
            'current_password' => 'password123',
            'password' => 'sandiBaru2026',
            'password_confirmation' => 'sandiBaru2026',
        ])->assertRedirect('/teacher');

        $this->app['auth']->forgetGuards();
        $this->get('/teacher')->assertOk();
    }

    public function test_html_disguised_as_photo_is_never_stored(): void
    {
        $this->seed();
        Storage::fake('local');

        $school = School::query()->where('npsn', '20219101')->firstOrFail();
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $coords = TenantContext::runInSchoolScope($school->id, fn () => Placement::query()
            ->where('student_id', $nadia->student->id)->firstOrFail()->company->coordinates());
        $html = '<html><body><script>alert(document.cookie)</script></body></html>';
        $gps = ['latitude' => $coords->latitude, 'longitude' => $coords->longitude];

        // 1. Berkas "foto.png" berisi HTML ditolak validasi. UploadedFile asli (bukan fake),
        //    karena fake melaporkan MIME dari nama berkas, sedangkan upload sungguhan dibaca dari isi.
        $tmp = tempnam(sys_get_temp_dir(), 'xss');
        file_put_contents($tmp, $html);
        $this->actingAs($nadia)->post('/student/check-in', [
            ...$gps, 'photo' => new UploadedFile($tmp, 'foto.png', null, null, true),
        ])->assertSessionHasErrors('photo');

        // 2. Data URL berlabel image/png berisi HTML tidak disimpan sebagai berkas.
        $this->actingAs($nadia)->post('/student/check-in', [
            ...$gps, 'photo_data_url' => 'data:image/png;base64,'.base64_encode($html),
        ]);

        $this->assertSame([], Storage::disk('local')->allFiles());
        $photoPath = TenantContext::runInSchoolScope($school->id, fn () => Attendance::query()
            ->where('student_id', $nadia->student->id)->latest()->value('check_in_photo_path'));
        $this->assertNull($photoPath);
    }

    public function test_finalized_assessment_cannot_be_changed(): void
    {
        $this->seed();
        $school = School::query()->where('npsn', '20219101')->firstOrFail();
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $teacherUser = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        TenantContext::runInSchoolScope($school->id, function () use ($nadia, $teacherUser): void {
            $teacher = $teacherUser->teacher;
            $placement = Placement::query()->where('student_id', $nadia->student->id)->firstOrFail();
            $grade = fn (int $score, bool $finalize) => (new CalculateFinalScoreAction())->handle(
                placement: $placement,
                teacher: $teacher,
                industryScores: ['soft_skills' => $score, 'hard_skills' => $score, 'initiative' => $score],
                schoolScores: ['journal_quality' => $score, 'report_presentation' => $score],
                finalize: $finalize,
            );

            $final = $grade(90, true)->final_score;

            $this->expectException(DomainException::class);
            try {
                $grade(40, false);
            } finally {
                $this->assertEquals($final, $placement->assessment()->first()->final_score);
            }
        });
    }
}
