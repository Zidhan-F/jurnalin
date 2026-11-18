<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\PklPeriod;
use App\Models\School;
use App\Support\AdminDashboard;
use App\Support\GlobalSearch;
use App\Support\StudentNavigation;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Temuan review akhir seluruh branch UI mockup penuh. */
class FinalReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function schoolA(): School
    {
        return School::query()->where('npsn', '20219101')->firstOrFail();
    }

    public function test_first_day_of_period_counts_as_running(): void
    {
        $this->seed();
        $school = $this->schoolA();
        $today = CarbonImmutable::now($school->timezone)->startOfDay();

        $d = TenantContext::runInSchoolScope($school->id, function () use ($school, $today) {
            $period = PklPeriod::query()->where('is_active', true)->firstOrFail();
            $period->update(['start_date' => $today->toDateString()]);

            return AdminDashboard::build($school, $period->fresh(), $today);
        });

        $this->assertTrue($d['isRunning'], 'Hari pertama periode harus dianggap berjalan di zona waktu sekolah.');
    }

    public function test_line_chart_numbers_visible_without_javascript(): void
    {
        $html = Blade::render('<x-chart type="line" :labels="[\'Sen\']" :values="[5]" series-label="Jurnal" caption="Jurnal 7 hari" />');

        $this->assertDoesNotMatchRegularExpression('/<table[^>]*class="[^"]*sr-only/', $html);
        $this->assertStringContainsString('chart-data-line', $html);
    }

    public function test_chart_legend_keeps_table_cell_semantics(): void
    {
        $html = Blade::render('<x-chart type="doughnut" :labels="[\'Hadir\']" :values="[3]" series-label="P" caption="S" />');

        $this->assertMatchesRegularExpression('/<th scope="row"><span class="chart-label">/', $html);
    }

    public function test_search_hides_draft_journals(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $found = TenantContext::runInSchoolScope($admin->school_id, function () use ($admin) {
            $journal = Journal::query()->where('status', JournalStatus::Submitted)->firstOrFail()->replicate();
            $journal->fill(['activity_title' => 'Rahasia draf belum dikirim', 'status' => JournalStatus::Draft, 'journal_date' => now()->subDays(20)->toDateString()]);
            $journal->save();

            return GlobalSearch::run($admin, 'Rahasia draf');
        });

        $this->assertCount(0, $found['journals']);
    }

    public function test_grace_teacher_has_no_search_box(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id'));
        request()->attributes->set('grace_placements', ['x']);
        request()->attributes->set('full_placements', []);

        $this->assertStringNotContainsString('role="search"', view('partials.panel-search')->render());
    }

    public function test_grace_student_has_no_help_link(): void
    {
        $request = Request::create('/student/journals');
        $request->attributes->set('grace_placements', true);
        $request->attributes->set('full_placements', false);

        $this->assertNull(StudentNavigation::help($request));
        $this->assertSame('student.tickets', StudentNavigation::help(Request::create('/student'))['route']);
    }

    public function test_search_suggestions_announce_active_option(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('admin@smkn1par-bdg.sch.id'));
        $html = view('partials.panel-search')->render();

        $this->assertStringContainsString(':aria-activedescendant', $html);
        $this->assertStringContainsString(":id=\"'saran-' + i\"", $html);
        $this->assertDoesNotMatchRegularExpression('/role="listbox"[^>]*>\s*<template[\s\S]*?<\/template>\s*<p/', $html);
    }

    public function test_student_on_shared_layout_gets_no_empty_sidebar(): void
    {
        $this->seed();
        $html = $this->actingAs($this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id'))->get('/password')->assertOk()->getContent();

        $this->assertStringNotContainsString('panel-sidebar', $html);
        $this->assertStringNotContainsString('panel-layout', $html);
    }
}
