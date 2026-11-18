<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\GlobalSearch;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(string $email, string $q): array
    {
        $user = $this->userByEmail($email);

        return TenantContext::runInSchoolScope($user->school_id, fn () => GlobalSearch::run($user, $q));
    }

    public function test_admin_finds_student_teacher_company_and_journal(): void
    {
        $this->seed();
        $admin = 'admin@smkn1par-bdg.sch.id';

        $this->assertSame('Bagas Pratama', $this->search($admin, 'Bagas')['students']->first()->user->name);
        $this->assertStringContainsString('Budi Prasetyo', $this->search($admin, 'Budi')['teachers']->first()->user->name);
        $this->assertSame('Padma Hotel Bandung', $this->search($admin, 'Padma')['companies']->first()->name);
        $this->assertStringContainsString('Prasmanan', $this->search($admin, 'Prasmanan')['journals']->first()->activity_title);
    }

    public function test_teacher_only_sees_own_students(): void
    {
        $this->seed();

        $this->assertCount(0, $this->search('guru.kuliner@smkn1par-bdg.sch.id', 'Bagas')['students']);
        $this->assertCount(0, $this->search('guru.kuliner@smkn1par-bdg.sch.id', 'Prasmanan')['journals']);
        $this->assertCount(1, $this->search('guru.perhotelan@smkn1par-bdg.sch.id', 'Bagas')['students']);
        $this->assertCount(0, $this->search('guru.perhotelan@smkn1par-bdg.sch.id', 'Sari')['teachers']);
    }

    public function test_other_school_never_appears(): void
    {
        $this->seed();
        $result = $this->search('admin@smkharapan-bali.sch.id', 'Bagas');

        $this->assertCount(0, $result['students']);
        $this->assertCount(0, $this->search('admin@smkharapan-bali.sch.id', 'Padma')['companies']);
    }

    public function test_wildcards_are_literal(): void
    {
        $this->seed();
        foreach (['%%', '_a', 'a\\'] as $q) {
            $result = $this->search('admin@smkn1par-bdg.sch.id', $q);
            foreach ($result as $type => $items) {
                $this->assertCount(0, $items, "Kata kunci {$q} tidak boleh menjadi wildcard ({$type})");
            }
        }
    }

    public function test_suggest_returns_json_and_short_query_is_empty(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->getJson('/cari/saran?q=B')->assertOk()->assertExactJson(['results' => []]);
        $this->actingAs($admin)->getJson('/cari/saran?q=Bag')->assertOk()
            ->assertJsonPath('results.0.type', 'student')
            ->assertJsonPath('results.0.label', 'Bagas Pratama')
            ->assertJsonStructure(['results' => [['type', 'label', 'hint', 'url']]]);
    }

    public function test_results_page_groups_hits(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('admin@smkn1par-bdg.sch.id'))->get('/cari?q=Padma')
            ->assertOk()->assertSee('Tempat PKL')->assertSee('Padma Hotel Bandung');
    }

    public function test_student_cannot_search(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id'))->get('/cari?q=ab')->assertForbidden();
    }

    public function test_header_search_box_only_for_admin_and_teacher(): void
    {
        $this->seed();

        foreach (['admin@smkn1par-bdg.sch.id' => '/admin', 'guru.perhotelan@smkn1par-bdg.sch.id' => '/teacher'] as $email => $url) {
            $this->actingAs($this->userByEmail($email))->get($url)->assertOk()
                ->assertSee('role="search"', false)->assertSee('/cari/saran', false);
        }
        $this->actingAs($this->userByEmail('superadmin@jurnalin.id'))->get('/super-admin')->assertOk()->assertDontSee('/cari/saran', false);
    }
}
