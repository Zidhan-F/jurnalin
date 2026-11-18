<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PanelNavigation;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PanelLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function aside(string $html): string
    {
        preg_match('/<aside[^>]*class="[^"]*panel-sidebar[^"]*"[^>]*>(.*?)<\/aside>/s', $html, $m);

        return $m[1] ?? '';
    }

    public function test_admin_sees_sidebar_groups_and_every_link_resolves(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();
        $aside = $this->aside($html);

        $this->assertNotSame('', $aside, 'Sidebar panel tidak ditemukan');
        foreach (['Utama', 'Manajemen PKL', 'Program sekolah', 'Riwayat aktivitas', 'Butuh bantuan?', 'SMK Negeri 1 Pariwisata Bandung'] as $text) {
            $this->assertStringContainsString($text, $aside);
        }
        preg_match_all('/href="(http:\/\/localhost[^"#]*)"/', $aside, $links);
        $this->assertGreaterThan(10, count($links[1]));
        foreach (array_unique($links[1]) as $url) {
            $status = $this->actingAs($admin)->get($url)->getStatusCode();
            $this->assertLessThan(400, $status, "Tautan sidebar {$url} menghasilkan {$status}");
        }
    }

    public function test_school_name_is_not_a_dropdown(): void
    {
        $this->seed();
        $html = $this->actingAs($this->userByEmail('admin@smkn1par-bdg.sch.id'))->get('/admin')->getContent();

        $this->assertStringNotContainsString('<select', $this->aside($html));
    }

    public function test_teacher_sidebar_respects_grace_rule(): void
    {
        $this->seed();
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $request = Request::create('/teacher');
        $request->attributes->set('grace_placements', true);
        $request->attributes->set('full_placements', false);

        $groups = TenantContext::runInSchoolScope($teacher->school_id, fn () => PanelNavigation::for($teacher, $request));

        $this->assertSame(['teacher.index', 'teacher.internship'], array_keys(array_merge(...array_values($groups))));
    }

    public function test_header_has_account_chip_with_initials(): void
    {
        $this->seed();
        $html = $this->actingAs($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id'))->get('/teacher')->assertOk()->getContent();

        $this->assertStringContainsString('avatar-initials', $html);
        $this->assertStringContainsString('Guru Pembimbing', $html);
    }
}
