<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Assessment\CalculateFinalScoreAction;
use App\Models\Placement;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_weighted_final_score_and_generates_pdf(): void
    {
        $this->seed();

        $schoolA = School::query()->where('npsn', '20219101')->firstOrFail();
        $nadiaUser = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $teacherUser = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        $placementId = TenantContext::runInSchoolScope($schoolA->id, function () use ($nadiaUser, $teacherUser): string {
            $placement = Placement::query()->where('student_id', $nadiaUser->student->id)->firstOrFail();
            $teacher = $teacherUser->teacher;

            $assessment = (new CalculateFinalScoreAction())->handle(
                placement: $placement,
                teacher: $teacher,
                industryScores: ['soft_skills' => 95, 'hard_skills' => 90, 'initiative' => 90],
                schoolScores: ['journal_quality' => 92, 'report_presentation' => 88],
                signedByName: 'Arif Rahman',
                finalize: true,
            );

            // Industri avg = 95*0.4 + 90*0.45 + 90*0.15 = 38 + 40.5 + 13.5 = 92.0
            // Sekolah avg = 92*0.5 + 88*0.5 = 90.0
            // Kehadiran Nadia = 1 / 120 = 0.83%
            // Final = 92*0.7 + 90*0.2 + 0.83*0.1 = 64.4 + 18 + 0.08 = 82.48
            $this->assertEqualsWithDelta(92.0, $assessment->industry_scores['weighted_average'], 0.01);
            $this->assertEqualsWithDelta(90.0, $assessment->school_scores['weighted_average'], 0.01);
            $this->assertSame('Baik', $assessment->predicate);
            $this->assertNotNull($assessment->finalized_at);

            return $placement->id;
        });

        $response = $this->actingAs($teacherUser)->get("/reports/placements/{$placementId}/logbook.pdf");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
