<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_a_cannot_query_or_download_school_b_data(): void
    {
        $this->seed();

        $schoolA = School::query()->where('npsn', '20219101')->firstOrFail();
        $schoolB = School::query()->where('npsn', '50103822')->firstOrFail();

        $teacherA = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        $journalB = TenantContext::runInSchoolScope($schoolB->id, function (): Journal {
            return Journal::query()->firstOrFail();
        });

        $placementB = TenantContext::runInSchoolScope($schoolB->id, function (): Placement {
            return Placement::query()->firstOrFail();
        });

        // 1. Query scope dalam konteks Sekolah A tidak boleh melihat jurnal Sekolah B
        TenantContext::runInSchoolScope($schoolA->id, function () use ($journalB): void {
            $this->assertSame(0, Journal::query()->whereKey($journalB->id)->count());
        });

        // 2. Guru Sekolah A mencoba menyetujui jurnal Sekolah B lewat HTTP -> 404/403
        $this->actingAs($teacherA)
            ->post("/teacher/journals/{$journalB->id}/approve")
            ->assertNotFound();

        // 3. Guru Sekolah A mencoba mengunduh PDF penempatan Sekolah B -> 404/403
        $this->actingAs($teacherA)
            ->get("/reports/placements/{$placementB->id}/logbook.pdf")
            ->assertNotFound();

        // 4. Verifikasi status jurnal Sekolah B tetap utuh (Submitted)
        TenantContext::runInSchoolScope($schoolB->id, function () use ($journalB): void {
            $this->assertSame(JournalStatus::Submitted, $journalB->fresh()->status);
        });
    }
}
