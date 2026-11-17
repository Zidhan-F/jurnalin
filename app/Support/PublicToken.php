<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token acak untuk akses tanpa akun (mentor, penguji, wali, alumni, PIC mitra, paspor).
 * Hanya hash SHA-256 yang disimpan; data peserta tidak pernah masuk URL (10.3).
 */
final class PublicToken
{
    /** @return array{0:string, 1:string} [token polos, hash] */
    public static function make(int $length = 48): array
    {
        $token = Str::random($length);

        return [$token, self::hash($token)];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Satu-satunya pencarian lintas sekolah: berdasarkan hash unik, lalu callback dijalankan
     * dalam konteks sekolah pemilik baris itu saja. Mengembalikan null bila token tidak dikenal.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     */
    public static function within(string $modelClass, string $token, Closure $callback, string $column = 'token_hash'): mixed
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }
        $hash = self::hash($token);
        $found = TenantContext::runAsSuperAdmin(fn () => $modelClass::query()->where($column, $hash)->first(['id', 'school_id']));
        if ($found === null) {
            return null;
        }

        return TenantContext::runInSchoolScope($found->school_id, function () use ($modelClass, $column, $hash, $found, $callback) {
            $model = $modelClass::query()->where($column, $hash)->find($found->id);

            return $model ? $callback($model) : null;
        });
    }
}
