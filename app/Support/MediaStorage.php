<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MediaStorage
{
    /** Ekstensi yang boleh disimpan dan disajikan, beserta Content-Type tetapnya. */
    public const IMAGE_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public static function storeStudentUpload(
        string $schoolId,
        string $category,
        ?UploadedFile $file = null,
        ?string $base64DataUrl = null,
    ): ?string {
        return self::storeImageAtPrefix("schools/{$schoolId}", $category, $file, $base64DataUrl);
    }

    public static function storeUserAvatar(
        \App\Models\User $user,
        ?UploadedFile $file = null,
        ?string $base64DataUrl = null,
    ): ?string {
        $prefix = $user->school_id !== null
            ? "schools/{$user->school_id}"
            : "platform/users/{$user->id}";

        return self::storeImageAtPrefix($prefix, 'avatars', $file, $base64DataUrl);
    }

    private static function storeImageAtPrefix(
        string $prefix,
        string $category,
        ?UploadedFile $file = null,
        ?string $base64DataUrl = null,
    ): ?string {
        if ($base64DataUrl !== null && preg_match('/^data:image\/[a-z]+;base64,(.+)$/', $base64DataUrl, $matches)) {
            $binary = base64_decode($matches[1], true);
            // Tipe ditentukan dari isi byte, bukan dari label data URL kiriman klien.
            $ext = $binary !== false ? self::extensionForMime(@getimagesizefromstring($binary)['mime'] ?? null) : null;
            if ($ext !== null) {
                $path = self::newPath($prefix, $category, $ext);
                Storage::disk('local')->put($path, $binary);

                return $path;
            }
        }

        if ($file !== null && $file->isValid()) {
            // MIME dibaca dari isi berkas (finfo), jadi "foto.png" berisi HTML ditolak.
            $ext = self::extensionForMime($file->getMimeType());
            if ($ext !== null && @getimagesize($file->getRealPath()) !== false) {
                $path = self::newPath($prefix, $category, $ext);
                Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

                return $path;
            }
        }

        return null;
    }

    public static function discard(?string $relativePath): void
    {
        if (! empty($relativePath)) {
            Storage::disk('local')->delete($relativePath);
        }
    }

    public static function absolutePath(?string $relativePath): ?string
    {
        if (empty($relativePath) || ! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        return Storage::disk('local')->path($relativePath);
    }

    private static function extensionForMime(?string $mime): ?string
    {
        $ext = array_search($mime, self::IMAGE_TYPES, true);

        return $ext === false ? null : $ext;
    }

    private static function newPath(string $prefix, string $category, string $ext): string
    {
        return sprintf('%s/%s/%s/%s.%s', $prefix, $category, now()->format('Y-m'), Str::uuid(), $ext);
    }
}
