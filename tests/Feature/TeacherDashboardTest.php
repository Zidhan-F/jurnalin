<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlacementStatus;
use App\Models\Placement;
use App\Support\PklMonitor;
use App\Support\TeacherDashboard;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_groups_do_not_overlap(): void
    {
        $this->seed();
        $user = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $today = CarbonImmutable::now($user->school->timezone)->toDateString();

        [$progress, $total, $alerted] = TenantContext::runInSchoolScope($user->school_id, function () use ($user, $today) {
            $placements = Placement::query()->with('pklPeriod')->where('teacher_id', $user->teacher->id)
                ->whereIn('status', [PlacementStatus::Active, PlacementStatus::Completed])
                ->whereHas('pklPeriod', fn ($q) => $q->where('is_active', true))->get();
            $active = $placements->where('status', PlacementStatus::Active);

            return [TeacherDashboard::progress($user->teacher, $today), $placements->count(),
                PklMonitor::alerts($active, $today)->pluck('placement.id')->unique()->count()];
        });

        $this->assertSame($total, $progress['done'] + $progress['running'] + $progress['attention']);
        $this->assertSame($alerted, $progress['attention']);
        $this->assertGreaterThan(0, $progress['attention'], 'Seeder punya siswa beralert (Bagas, Citra).');
    }

    public function test_dashboard_shows_kpi_recent_journals_and_chart(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id'))->get('/teacher')->assertOk()
            ->assertSee('Dashboard Guru Pembimbing')->assertSee('Peserta binaan')->assertSee('Jurnal perlu review')
            ->assertSee('Perlu perhatian')->assertSee('Jurnal terbaru')->assertSee('Progres peserta')
            ->assertSee('data-chart', false)->assertSee('Yang perlu Anda kerjakan')->assertSee('Setup Meja Prasmanan');
    }

    public function test_teacher_without_students_gets_empty_numbers(): void
    {
        $this->seed();
        $user = $this->userByEmail('guru.kuliner@smkn1par-bdg.sch.id');

        [$progress, $recent] = TenantContext::runInSchoolScope($user->school_id, function () use ($user) {
            Placement::query()->where('teacher_id', $user->teacher->id)->update(['status' => PlacementStatus::Cancelled->value]);

            return [TeacherDashboard::progress($user->teacher, now()->toDateString()), TeacherDashboard::recentJournals($user->teacher)];
        });

        $this->assertSame(['done' => 0, 'running' => 0, 'attention' => 0], $progress);
        $this->assertCount(0, $recent);
    }
}
