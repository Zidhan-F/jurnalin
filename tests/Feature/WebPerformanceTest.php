<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Panduan web modern: prefetch navigasi, grafik tanpa JS bila bisa, font, kompresi, dan cache. */
class WebPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function rules(string $html): array
    {
        $this->assertMatchesRegularExpression('/<script type="speculationrules">/', $html);
        preg_match('/<script type="speculationrules">(.*?)<\/script>/s', $html, $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_panel_pages_prefetch_only_safe_links(): void
    {
        $this->seed();
        foreach (['admin@smkn1par-bdg.sch.id' => '/admin', 'siswa.nadia@smkn1par-bdg.sch.id' => '/student'] as $email => $url) {
            $json = json_encode(flags: JSON_UNESCAPED_SLASHES, value: $this->rules($this->actingAs($this->userByEmail($email))->get($url)->assertOk()->getContent()));
            foreach (['/admin/*', '/student/*', '/teacher/*', 'moderate'] as $needle) {
                $this->assertStringContainsString($needle, $json);
            }
            // Tautan bertoken, unduhan, dan ekspor tidak boleh di-prefetch (magic link habis dipakai saat GET).
            foreach (['/*.pdf', '/*.csv', '/admin/export', '/privasi-saya/ekspor', '/*/template'] as $excluded) {
                $this->assertStringContainsString($excluded, $json);
            }
            $this->assertStringNotContainsString('/masuk/', $json);
        }
    }

    public function test_doughnut_renders_with_css_only(): void
    {
        $html = Blade::render('<x-chart type="doughnut" :labels="[\'Hadir\', \'Izin\']" :values="[3, 1]" series-label="P" caption="Status" />');

        $this->assertStringNotContainsString('data-chart', $html);
        $this->assertStringContainsString('conic-gradient(', $html);
        $this->assertStringContainsString('role="img"', $html);
    }

    public function test_teacher_dashboard_loads_no_chart_library(): void
    {
        $this->seed();
        $this->actingAs($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id'))->get('/teacher')->assertOk()
            ->assertSee('Progres peserta')->assertDontSee('data-chart', false);
    }

    public function test_layouts_load_self_hosted_fonts(): void
    {
        foreach (['layouts/app', 'layouts/student-pwa', 'spec/public/layout', 'mentor/layout', 'errors/layout'] as $layout) {
            $this->assertStringContainsString('@fonts', file_get_contents(resource_path("views/{$layout}.blade.php")), $layout);
        }
    }

    public function test_server_config_compresses_and_caches_build_assets(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('mod_deflate', $htaccess);
        $this->assertStringContainsString('mod_brotli', $htaccess);
        $this->assertStringContainsString('immutable', $htaccess);
    }
}
