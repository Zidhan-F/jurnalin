<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ChartComponentTest extends TestCase
{
    public function test_chart_component_renders_values_as_text(): void
    {
        $html = Blade::render('<x-chart type="doughnut" :labels="[\'Hadir\', \'Izin\']" :values="[3, 1]" series-label="Peserta" caption="Status peserta" />');

        $this->assertStringContainsString('data-chart=', $html);
        $this->assertStringContainsString('aria-label="Status peserta"', $html);
        $this->assertMatchesRegularExpression('/<th[^>]*>(?:<span[^>]*><\/span>)?Hadir<\/th>\s*<td[^>]*>3<\/td>/', $html);
        $this->assertMatchesRegularExpression('/<th[^>]*>(?:<span[^>]*><\/span>)?Izin<\/th>\s*<td[^>]*>1<\/td>/', $html);
    }

    public function test_line_chart_keeps_table_for_screen_readers_only(): void
    {
        $html = Blade::render('<x-chart type="line" :labels="[\'Sen\']" :values="[5]" series-label="Jurnal" caption="Jurnal 7 hari" />');

        $this->assertMatchesRegularExpression('/<table[^>]*class="[^"]*sr-only/', $html);
        $this->assertStringContainsString('"type":"line"', html_entity_decode($html));
    }
}
