<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlacementStatus;
use App\Models\Attendance;
use App\Models\Placement;
use App\Models\School;
use App\Support\StudentNavigation;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class StudentPanelTest extends TestCase
{
    use RefreshDatabase;

    private const NADIA = 'siswa.nadia@smkn1par-bdg.sch.id';

    public function test_home_matches_mockup_sections(): void
    {
        $this->seed();
        $html = $this->actingAs($this->userByEmail(self::NADIA))->get('/student')->assertOk()
            ->assertSee('Halo, Nadia!')->assertSee('Presensi Hari Ini')->assertSee('Progres PKL')->assertSee('Minggu ke-')
            ->assertSee('Aktivitas terbaru')->assertSee('student-female.svg', false)->assertSee('Tulis Jurnal')
            ->assertSee('href="'.route('student.attendance').'"', false)->getContent();

        $this->assertStringNotContainsString('action="/student/check-in"', $html, 'Formulir absen pindah ke halaman Presensi.');
    }

    public function test_male_student_gets_male_mascot(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('siswa.bagas@smkn1par-bdg.sch.id'))->get('/student')->assertOk()->assertSee('student-male.svg', false);
    }

    public function test_check_in_works_from_presensi_page(): void
    {
        $this->seed();
        $nadia = $this->userByEmail(self::NADIA);
        $school = School::query()->where('npsn', '20219101')->firstOrFail();
        $coords = TenantContext::runInSchoolScope($school->id, fn () => Placement::query()->where('student_id', $nadia->student->id)->firstOrFail()->attendancePoint());

        $this->actingAs($nadia)->get('/student/presensi')->assertOk()->assertSee('action="/student/check-in"', false)->assertSee('Riwayat presensi');
        $this->actingAs($nadia)->post('/student/check-in', ['mode' => 'present', 'latitude' => $coords->latitude, 'longitude' => $coords->longitude, 'accuracy' => 10])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(TenantContext::runInSchoolScope($school->id, fn () => Attendance::query()->where('student_id', $nadia->student->id)->whereDate('attendance_date', now($school->timezone)->toDateString())->exists()));
    }

    public function test_report_page_links_learning_features(): void
    {
        $this->seed();
        $response = $this->actingAs($this->userByEmail(self::NADIA))->get('/student/laporan')->assertOk()->assertSee('Belajar dan persiapan');
        foreach (['student.learning', 'student.calendar', 'student.passport', 'student.readiness', 'student.interview', 'student.journey', 'student.final-report'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }

    public function test_bottom_nav_has_five_items(): void
    {
        $this->assertSame(['Beranda', 'Presensi', 'Jurnal', 'Izin', 'Laporan'], array_column(StudentNavigation::items(Request::create('/student')), 'label'));
    }

    public function test_grace_student_sees_only_journal_nav(): void
    {
        $request = Request::create('/student/journals');
        $request->attributes->set('grace_placements', true);
        $request->attributes->set('full_placements', false);

        $this->assertSame(['student.journals'], array_column(StudentNavigation::items($request), 'route'));
    }

    public function test_student_without_placement_renders(): void
    {
        $this->seed();
        $nadia = $this->userByEmail(self::NADIA);
        TenantContext::runInSchoolScope($nadia->school_id, fn () => Placement::query()->where('student_id', $nadia->student->id)->update(['status' => PlacementStatus::Completed->value]));

        $this->actingAs($nadia)->get('/student')->assertOk()->assertSee('Penempatan PKL belum aktif');
    }

    public function test_no_chart_script_on_student_pages(): void
    {
        $this->seed();
        foreach (['/student', '/student/presensi', '/student/laporan'] as $url) {
            $this->actingAs($this->userByEmail(self::NADIA))->get($url)->assertOk()->assertDontSee('data-chart', false);
        }
    }
}
