<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Menjaga standar WCAG 2.2 AA pada token warna di resources/css/app.css.
 * Teks biasa minimal 4.5:1 (1.4.3), batas kontrol dan indikator fokus minimal 3:1 (1.4.11).
 * Jika token diubah dan pasangan di bawah turun di bawah ambang, test ini gagal.
 */
class ColorContrastTest extends TestCase
{
    private const TEXT = 4.5;

    private const UI = 3.0;

    /** @var array<string, string>|null */
    private static ?array $tokens = null;

    /** @return array<string, array{0: string, 1: string, 2: float, 3?: float}> */
    public static function pairs(): array
    {
        return [
            'ink di paper' => ['ink', 'paper', self::TEXT],
            'muted di paper' => ['muted', 'paper', self::TEXT],
            'muted di stone' => ['muted', 'stone', self::TEXT],
            'navy di paper' => ['navy', 'paper', self::TEXT],
            'emerald di paper' => ['emerald-check', 'paper', self::TEXT],
            'emerald di stone' => ['emerald-check', 'stone', self::TEXT],
            'amber di paper' => ['amber-warn', 'paper', self::TEXT],
            'danger di paper' => ['danger', 'paper', self::TEXT],
            'putih di navy' => ['#FFFFFF', 'navy', self::TEXT],
            'putih di emerald' => ['#FFFFFF', 'emerald-check', self::TEXT],
            'putih di amber' => ['#FFFFFF', 'amber-warn', self::TEXT],
            'putih di danger' => ['#FFFFFF', 'danger', self::TEXT],
            'on-dark di navy' => ['on-dark', 'navy', self::TEXT],
            'paper di navy' => ['paper', 'navy', self::TEXT],
            'emerald-ink di tint emerald/10' => ['emerald-ink', 'emerald-check', self::TEXT, 0.10],
            'emerald-strong di tint emerald/15' => ['emerald-strong', 'emerald-check', self::TEXT, 0.15],
            'amber-strong di tint amber/15' => ['amber-strong', 'amber-warn', self::TEXT, 0.15],
            'amber-ink di tint amber/10' => ['amber-ink', 'amber-warn', self::TEXT, 0.10],
            'danger-ink di tint danger/10' => ['danger-ink', 'danger', self::TEXT, 0.10],
            'danger-strong di tint danger/15' => ['danger-strong', 'danger', self::TEXT, 0.15],
            'batas field di paper' => ['field', 'paper', self::UI],
            'batas field di stone' => ['field', 'stone', self::UI],
            'fokus emerald di paper' => ['emerald-check', 'paper', self::UI],
            'fokus paper di navy' => ['paper', 'navy', self::UI],
            'emerald-strong di emerald-tint' => ['emerald-strong', 'emerald-tint', self::TEXT],
            'amber-ink di amber-tint' => ['amber-ink', 'amber-tint', self::TEXT],
            'danger-ink di danger-tint' => ['danger-ink', 'danger-tint', self::TEXT],
            'muted di paper (placeholder)' => ['placeholder', 'paper', self::TEXT],
            'on-dark-strong di navy' => ['on-dark-strong', 'navy', self::TEXT],
            'putih di navy-soft' => ['#FFFFFF', 'navy-soft', self::TEXT],
        ];
    }

    #[DataProvider('pairs')]
    public function test_token_pair_meets_wcag_aa(string $fg, string $bg, float $min, ?float $tintAlpha = null): void
    {
        $fgRgb = $this->rgb($fg);
        $bgRgb = $this->rgb($bg);

        // Tint seperti bg-danger/10 dicampur di atas latar paper.
        if ($tintAlpha !== null) {
            $paper = $this->rgb('paper');
            $bgRgb = array_map(fn ($c, $p) => (int) round($c * $tintAlpha + $p * (1 - $tintAlpha)), $bgRgb, $paper);
        }

        $ratio = $this->ratio($fgRgb, $bgRgb);
        $this->assertGreaterThanOrEqual($min, round($ratio, 2), sprintf('%s di atas %s hanya %.2f:1, minimal %.1f:1.', $fg, $bg, $ratio, $min));
    }

    public function test_views_do_not_use_raw_hex_colors(): void
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.blade.php') || str_contains($path, '/views/pdf/')) {
                continue;
            }
            if (preg_match_all('/-\[#[0-9A-Fa-f]{3,8}\]/', (string) file_get_contents($path), $m)) {
                $offenders[] = substr($path, strlen($root) + 1).': '.implode(', ', array_unique($m[0]));
            }
        }

        $this->assertSame([], $offenders, "Gunakan token warna dari app.css, bukan hex mentah:\n".implode("\n", $offenders));
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $tokenOrHex): array
    {
        $hex = str_starts_with($tokenOrHex, '#') ? $tokenOrHex : ($this->tokens()[$tokenOrHex] ?? null);
        $this->assertNotNull($hex, "Token --color-{$tokenOrHex} tidak ditemukan di app.css.");
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @return array<string, string> */
    private function tokens(): array
    {
        if (self::$tokens === null) {
            $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
            preg_match_all('/--color-([a-z-]+):\s*(#[0-9A-Fa-f]{6})/', $css, $m);
            self::$tokens = array_combine($m[1], $m[2]);
        }

        return self::$tokens;
    }

    /** @param array{0: int, 1: int, 2: int} $a @param array{0: int, 1: int, 2: int} $b */
    private function ratio(array $a, array $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @param array{0: int, 1: int, 2: int} $rgb */
    private function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
