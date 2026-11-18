<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\SchoolStatus;
use App\Models\Attendance;
use App\Models\Placement;
use App\Models\PklPeriod;
use App\Models\School;
use App\Support\AdminDashboard;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'admin@smkn1par-bdg.sch.id';

    private function schoolA(): School
    {
        return School::query()->where('npsn', '20219101')->firstOrFail();
    }

    /** Periode selesai sebelum periode aktif, berisi satu penempatan Nadia yang sudah selesai. */
    private function previousPeriod(): PklPeriod
    {
        return TenantContext::runInSchoolScope($this->schoolA()->id, function () {
            $active = PklPeriod::query()->where('is_active', true)->firstOrFail();
            $prev = $active->replicate();
            $prev->fill(['name' => 'PKL Gelombang Lalu', 'start_date' => $active->start_date->copy()->subMonths(8), 'end_date' => $active->start_date->copy()->subMonths(2), 'is_active' => false]);
            $prev->save();
            $template = Placement::query()->where('pkl_period_id', $active->id)->firstOrFail();
            $old = $template->replicate();
            $old->fill(['pkl_period_id' => $prev->id, 'status' => PlacementStatus::Completed]);
            $old->save();

            return $prev;
        });
    }

    public function test_dashboard_renders_mockup_sections(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin')->assertOk()
            ->assertSee('Dashboard Koordinator PKL')->assertSee('Tugas prioritas')->assertSee('Aksi cepat')
            ->assertSee('Periode PKL aktif')->assertSee('Perkembangan jurnal')->assertSee('Status peserta')
            ->assertSee('Input atau verifikasi penilaian')->assertSee('data-chart', false)->assertSee('name="periode"', false);
    }

    public function test_trend_hidden_without_previous_period(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin')->assertOk()->assertDontSee('dari periode lalu');
    }

    public function test_trend_shown_with_previous_period(): void
    {
        $this->seed();
        $this->previousPeriod();

        // Periode aktif 4 peserta, periode lalu 1 peserta.
        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin')->assertOk()->assertSee('+3 siswa dari periode lalu');
    }

    public function test_period_from_other_school_falls_back(): void
    {
        $this->seed();
        $foreign = TenantContext::runInSchoolScope(School::query()->where('npsn', '50103822')->value('id') ?? TenantContext::runAsSuperAdmin(fn () => School::query()->where('npsn', '50103822')->value('id')), fn () => PklPeriod::query()->firstOrFail());

        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin?periode='.$foreign->id)->assertOk()
            ->assertSee('PKL Kelas XII Gelombang 1')->assertDontSee($foreign->name);
    }

    public function test_garbage_period_param_does_not_error(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin?periode=abc')->assertOk();
        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin?periode[]=x')->assertOk();
    }

    public function test_past_period_uses_period_scope(): void
    {
        $this->seed();
        $prev = $this->previousPeriod();

        $this->actingAs($this->userByEmail(self::ADMIN))->get('/admin?periode='.$prev->id)->assertOk()
            ->assertSee('PKL Gelombang Lalu')->assertSee('seluruh periode')->assertDontSee('Presensi hari ini');
    }

    public function test_empty_school_renders_without_errors(): void
    {
        $this->seed();
        TenantContext::runAsSuperAdmin(fn () => School::query()->where('npsn', '20404199')->update(['status' => SchoolStatus::Active->value]));

        $this->actingAs($this->userByEmail('admin@smkparnus-yk.sch.id'))->get('/admin')->assertOk()
            ->assertSee('Persiapan sebelum PKL dimulai')->assertSee('Belum ada periode');
    }

    public function test_assessment_ready_counts_rule(): void
    {
        $this->seed();
        $school = $this->schoolA();
        $today = CarbonImmutable::now($school->timezone)->startOfDay();

        $data = TenantContext::runInSchoolScope($school->id, function () use ($school, $today) {
            $period = PklPeriod::query()->where('is_active', true)->firstOrFail();
            $period->update(['min_required_days' => 1]);
            $before = AdminDashboard::build($school, $period->fresh(), $today);
            // Satu siswa memenuhi target hadir (1 hari) dan belum punya nilai final: wajib dihitung.
            $placement = Placement::query()->where('pkl_period_id', $period->id)->where('status', PlacementStatus::Active)
                ->whereDoesntHave('assessment', fn ($q) => $q->whereNotNull('finalized_at'))
                ->whereDoesntHave('attendances')->firstOrFail();
            Attendance::query()->create(['placement_id' => $placement->id, 'student_id' => $placement->student_id,
                'attendance_date' => $today->subDay()->toDateString(), 'status' => AttendanceStatus::Present, 'is_within_radius' => true]);

            return [$before, AdminDashboard::build($school, $period->fresh(), $today)];
        });

        $count = fn (array $d) => collect($d['priorities'])->firstWhere('key', 'assessment')['count'];
        $this->assertSame($count($data[0]) + 1, $count($data[1]));
    }

    public function test_journal_priority_counts_every_pending_journal(): void
    {
        $this->seed();
        $school = $this->schoolA();

        $d = TenantContext::runInSchoolScope($school->id, fn () => AdminDashboard::build($school, PklPeriod::query()->where('is_active', true)->first(), CarbonImmutable::now($school->timezone)));

        // Seeder punya 1 jurnal menunggu bertanggal hari ini: baris tugas tidak boleh "Beres".
        $this->assertSame($d['kpi']['pendingJournals'], collect($d['priorities'])->firstWhere('key', 'journals')['count']);
        $this->assertGreaterThan(0, $d['kpi']['pendingJournals']);
    }
}
