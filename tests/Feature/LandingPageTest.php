<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_shows_platform_roles_and_modules(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('Satu platform untuk PKL, Magang, dan Kolaborasi Industri');
        foreach (['Siswa', 'Guru Pembimbing', 'Admin Sekolah', 'Pembimbing Industri', 'Guru Magang', 'Penguji UKK', 'Alumni', 'Superadmin'] as $role) {
            $response->assertSee($role);
        }
        foreach (['Jurnalin PKL', 'Hubin Partner Hub', 'Magang Guru', 'Mentor Access', 'Tracer Study', 'UKK'] as $module) {
            $response->assertSee($module);
        }
        $response->assertSee('/images/illustrations/', false);
    }

    public function test_landing_excludes_roles_not_in_product(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Kepala Sekolah')->assertDontSee('Wakasek')->assertDontSee('Orang Tua');
    }
}
