<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Placement;
use App\Models\School;
use App\Models\UserNotification;
use App\Support\ReviewSchedule;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Guru memilih jadwal periksa jurnal: harian, mingguan, bulanan, atau di akhir PKL. */
class ReviewScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_count_from_pkl_start_not_calendar_weeks(): void
    {
        // PKL mulai hari Kamis: "Minggu ke-1" = Kamis s.d. Rabu, tidak terikat Senin-Minggu.
        $start = CarbonImmutable::parse('2026-11-12');
        $this->assertSame(ReviewSchedule::bucket(CarbonImmutable::parse('2026-11-15'), 'weekly', $start)['key'], ReviewSchedule::bucket(CarbonImmutable::parse('2026-11-18'), 'weekly', $start)['key']);
        $this->assertStringContainsString('Minggu ke-1', ReviewSchedule::bucket(CarbonImmutable::parse('2026-11-18'), 'weekly', $start)['label']);
        $this->assertStringContainsString('Minggu ke-2', ReviewSchedule::bucket(CarbonImmutable::parse('2026-11-19'), 'weekly', $start)['label']);
        $this->assertStringContainsString('Bulan ke-2', ReviewSchedule::bucket(CarbonImmutable::parse('2026-12-12'), 'monthly', $start)['label']);
        $this->assertSame('all', ReviewSchedule::bucket($start, 'end')['key']);
    }

    public function test_rolling_interval_counts_from_last_review_on_any_weekday(): void
    {
        $this->seed();
        $teacherUser = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $schoolId = School::query()->where('npsn', '20219101')->value('id');

        TenantContext::runInSchoolScope($schoolId, function () use ($teacherUser): void {
            $teacher = $teacherUser->teacher;
            $saturday = CarbonImmutable::parse('2026-11-21');
            $teacher->forceFill(['review_frequency' => 'weekly', 'last_reviewed_at' => $saturday])->save();
            $s = ReviewSchedule::forTeacher($teacher->fresh(), $saturday->addDays(3));
            $this->assertSame('2026-11-28', $s['next']->toDateString()); // Sabtu lagi, bukan Senin
            $this->assertFalse($s['due']);

            $later = ReviewSchedule::forTeacher($teacher->fresh(), $saturday->addDays(8));
            $this->assertSame($later['pending'] > 0, $later['due']);
        });
    }

    public function test_teacher_sets_schedule_and_weekly_teacher_gets_no_per_journal_notification(): void
    {
        $this->seed();
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $student = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $schoolId = School::query()->where('npsn', '20219101')->value('id');

        $this->actingAs($teacher)->post('/teacher/review/jadwal', ['review_frequency' => 'tahunan'])->assertSessionHasErrors('review_frequency');
        $this->actingAs($teacher)->post('/teacher/review/jadwal', ['review_frequency' => 'monthly'])->assertSessionHasNoErrors();
        $this->assertSame('monthly', TenantContext::runInSchoolScope($schoolId, fn () => $teacher->teacher->fresh()->review_frequency));

        $this->actingAs($teacher)->get('/teacher/review')->assertOk()->assertSee('Setiap 30 hari')->assertSee('Pemeriksaan berikutnya');
        $this->actingAs($student)->get('/student/journals')->assertOk()->assertSee('Guru pembimbing memeriksa jurnal setiap 30 hari');

        $placementId = TenantContext::runInSchoolScope($schoolId, fn () => Placement::query()->where('student_id', $student->student->id)->value('id'));
        $before = TenantContext::runInSchoolScope($schoolId, fn () => UserNotification::query()->where('user_id', $teacher->id)->where('kind', 'journal.submitted')->count());
        $this->actingAs($student)->post('/student/journals', ['placement_id' => $placementId, 'journal_date' => now('Asia/Jakarta')->toDateString(), 'work_unit' => 'FO',
            'activity_title' => 'Melayani check-in tamu', 'activity_description' => 'Melayani check-in tamu rombongan sesuai SOP.', 'duration_minutes' => 300,
            'submit_action' => 'submitted', 'confirmed_done' => '1'])->assertSessionHasNoErrors();
        $after = TenantContext::runInSchoolScope($schoolId, fn () => UserNotification::query()->where('user_id', $teacher->id)->where('kind', 'journal.submitted')->count());
        $this->assertSame($before, $after);
        $this->assertTrue(TenantContext::runInSchoolScope($schoolId, fn () => Journal::query()->where('activity_title', 'Melayani check-in tamu')->exists()));
    }
}
