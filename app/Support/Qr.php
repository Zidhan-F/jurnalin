<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Kode QR SVG lokal (tanpa layanan pihak ketiga) untuk sertifikat, surat UKK, dan paspor. */
final class Qr
{
    public static function svg(string $text, int $size = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString($text);
    }

    public static function dataUri(string $text, int $size = 160): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $size));
    }
}
