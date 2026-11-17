<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Menjaga syarat pemasangan PWA: manifest valid, ikon 192/512 + maskable ada, service worker tidak menyimpan halaman pribadi. */
class PwaAssetsTest extends TestCase
{
    private function publicPath(string $path): string
    {
        return dirname(__DIR__, 2).'/public/'.ltrim($path, '/');
    }

    public function test_manifest_has_installable_fields_and_existing_icons(): void
    {
        $manifest = json_decode((string) file_get_contents($this->publicPath('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['name', 'short_name', 'start_url', 'display', 'theme_color', 'background_color'] as $field) {
            $this->assertNotEmpty($manifest[$field] ?? null, "manifest.$field wajib ada");
        }

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists($this->publicPath($icon['src']));
            [$w, $h] = getimagesize($this->publicPath($icon['src']));
            $this->assertSame($icon['sizes'], "{$w}x{$h}", "Ukuran {$icon['src']} harus sesuai manifest");
            $sizes[] = $icon['sizes'].'/'.($icon['purpose'] ?? 'any');
        }
        $this->assertContains('192x192/any', $sizes);
        $this->assertContains('512x512/any', $sizes);
        $this->assertContains('512x512/maskable', $sizes);
    }

    public function test_service_worker_never_caches_private_routes_and_has_offline_page(): void
    {
        $sw = (string) file_get_contents($this->publicPath('sw.js'));

        $this->assertFileExists($this->publicPath('offline.html'));
        foreach (['/media/', '/ttd/', '/reports/', '/admin/export'] as $private) {
            $this->assertStringContainsString("'{$private}'", $sw, "{$private} harus ada di daftar NEVER_CACHE");
        }
        // Navigasi halaman selalu dari jaringan; cache hanya cadangan offline.
        $this->assertMatchesRegularExpression("/request\\.mode === 'navigate'[\\s\\S]*fetch\\(request\\)\\.catch/", $sw);
    }
}
