<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PklPeriod;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Durasi PKL bebas diatur admin sekolah: 1, 3, 6 bulan, 1 tahun, atau kustom. */
class PklPeriodDurationTest extends TestCase
{
    use RefreshDatabase;

    private function periodPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'PKL Kilat Desember',
            'academic_year' => '2026/2027',
            'duration_months' => '1',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'min_required_days' => 20,
            'industry_percentage' => 70,
            'school_percentage' => 20,
            'attendance_percentage' => 10,
        ], $overrides);
    }

    private function actAsSchoolAdmin(): string
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $admin->forceFill(['must_change_password' => false]);
        TenantContext::runInSchoolScope($admin->school_id, fn () => $admin->save());
        $this->actingAs($admin);

        return $admin->school_id;
    }

    private function periodNamed(string $schoolId, string $name): ?PklPeriod
    {
        return TenantContext::runInSchoolScope($schoolId, fn () => PklPeriod::query()->where('name', $name)->first());
    }

    public function test_one_month_period_with_twenty_target_days_can_be_created(): void
    {
        $schoolId = $this->actAsSchoolAdmin();

        $this->post('/admin/periods', $this->periodPayload())->assertSessionHasNoErrors();

        $period = $this->periodNamed($schoolId, 'PKL Kilat Desember');
        $this->assertNotNull($period);
        $this->assertSame(20, $period->min_required_days);
        $this->assertSame(31, $period->totalDays());
        $this->assertSame('1 bulan', $period->durationLabel());
    }

    public function test_one_year_period_is_labelled_in_years(): void
    {
        $schoolId = $this->actAsSchoolAdmin();

        $this->post('/admin/periods', $this->periodPayload([
            'name' => 'PKL Satu Tahun',
            'duration_months' => '12',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'min_required_days' => 240,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('1 tahun', $this->periodNamed($schoolId, 'PKL Satu Tahun')?->durationLabel());
    }

    public function test_target_days_longer_than_the_period_are_rejected(): void
    {
        $schoolId = $this->actAsSchoolAdmin();

        $this->post('/admin/periods', $this->periodPayload(['min_required_days' => 45]))
            ->assertSessionHasErrors('min_required_days');

        $this->assertNull($this->periodNamed($schoolId, 'PKL Kilat Desember'));
    }

    public function test_school_admin_can_open_the_period_form(): void
    {
        $this->actAsSchoolAdmin();

        $this->get('/admin/periods')
            ->assertOk()
            ->assertSee('Durasi PKL')
            ->assertSee('1 tahun');
    }

    public function test_archived_period_links_to_its_own_roster(): void
    {
        $schoolId = $this->actAsSchoolAdmin();
        $placement = TenantContext::runInSchoolScope($schoolId, fn () => \App\Models\Placement::with('student.user')->firstOrFail());
        $period = TenantContext::runInSchoolScope($schoolId, function () use ($placement) {
            $period = $placement->pklPeriod;
            $period->update(['is_active' => false]);
            return $period;
        });
        $detail = route('admin.periods.show', $period);
        $this->get('/admin/periods')->assertOk()->assertSee($detail);
        $this->get($detail)->assertOk()->assertSee($placement->student->user->name)
            ->assertSee(route('admin.payments', ['period' => $period->id]));
        $otherSchool = \App\Models\School::where('npsn', '50103822')->firstOrFail();
        $foreign = TenantContext::runInSchoolScope($otherSchool->id, fn () => PklPeriod::firstOrFail());
        $this->get(route('admin.periods.show', $foreign))->assertNotFound();
    }
}
