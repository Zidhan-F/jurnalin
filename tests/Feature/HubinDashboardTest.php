<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubinDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hubin_center_shows_partner_kpis(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('admin@smkn1par-bdg.sch.id'))->get('/admin/hubin/pusat')->assertOk()
            ->assertSee('Mitra aktif')->assertSee('Dalam proses')->assertSee('Total penempatan')->assertSee('Program aktif')
            ->assertSee('Mitra terbaru')->assertSee('Program kerja sama')->assertSee('Kunjungan pembimbingan ke DUDI')
            ->assertDontSee('Kunjungan industri');
    }

    public function test_program_active_count_matches_list(): void
    {
        $this->seed();
        $response = $this->actingAs($this->userByEmail('admin@smkn1par-bdg.sch.id'))->get('/admin/hubin/pusat')->assertOk();

        $programs = $response->viewData('programs');
        $this->assertCount(3, $programs);
        $this->assertSame(collect($programs)->where('active', true)->count(), $response->viewData('hubinKpi')['activePrograms']);
        $this->assertTrue(collect($programs)->firstWhere('label', 'PKL siswa')['active'], 'Seeder punya penempatan aktif.');
    }
}
