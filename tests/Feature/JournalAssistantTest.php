<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\School;
use App\Models\TenantFeature;
use App\Models\User;
use App\Support\Features;
use App\Support\JournalExamples;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithAi(): User
    {
        $this->seed();
        $school = School::query()->where('npsn', '20219101')->firstOrFail();
        TenantContext::runInSchoolScope($school->id, fn () => TenantFeature::query()->updateOrCreate(['school_id' => $school->id, 'feature' => 'ai'], ['enabled' => true]));
        Features::flush();

        return $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
    }

    public function test_rules_draft_orders_points_fixes_typos_and_splits_obstacles(): void
    {
        config(['services.anthropic.key' => null]);
        $student = $this->studentWithAi();

        $res = $this->actingAs($student)->postJson('/student/ai/draf-jurnal', [
            'points' => "cleaning room\nmake up romm\nbed meking\nvacuum rusak",
            'work_unit' => 'Housekeeping',
        ])->assertOk()->json();

        $this->assertSame('rules', $res['source']);
        $this->assertSame('Hari ini di Housekeeping saya mengerjakan 3 kegiatan. Pertama, cleaning room. Setelah itu, make-up room. Terakhir, bed making. Kendala yang saya hadapi: vacuum rusak.', $res['draft']);
        $this->assertSame('Vacuum rusak', $res['obstacles']);
        $this->assertSame('Cleaning room', $res['title']);
        $this->assertNotEmpty($res['suggested_objectives']);
    }

    public function test_examples_follow_the_work_unit(): void
    {
        $this->assertSame('banquet', JournalExamples::keyFor('F&B Banquet Service'));
        $this->assertSame('fb', JournalExamples::keyFor('Restoran'));
        $this->assertSame('hk', JournalExamples::keyFor('Housekeeping'));
        $this->assertSame('kitchen', JournalExamples::keyFor('Pastry'));
        $this->assertSame('it', JournalExamples::keyFor('IT Support'));
        $this->assertNull(JournalExamples::keyFor('Gudang'));
        $this->assertSame('fo', JournalExamples::defaultFor('Perhotelan'));
        $this->assertSame('umum', JournalExamples::defaultFor(null));
        $this->assertStringNotContainsString('LAN', JournalExamples::SETS['banquet']['title']);
    }

    public function test_journal_form_no_longer_hardcodes_it_examples(): void
    {
        $student = $this->studentWithAi();
        $this->actingAs($student)->get('/student/journals')->assertOk()
            ->assertDontSee('placeholder="Contoh: Instalasi jaringan LAN ruang staf"', false)
            ->assertSee('Pakai draf ini');
    }
}
