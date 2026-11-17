<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

final class TenantContext
{
    private static ?string $currentSchoolId = null;

    private static bool $isSuperAdmin = false;

    public static function setSchool(?string $schoolId): void
    {
        self::$currentSchoolId = $schoolId;
        self::$isSuperAdmin = false;
        self::syncPostgresSession();
    }

    public static function setSuperAdmin(bool $enabled = true): void
    {
        self::$isSuperAdmin = $enabled;
        if ($enabled) {
            self::$currentSchoolId = null;
        }
        self::syncPostgresSession();
    }

    public static function clear(): void
    {
        self::$currentSchoolId = null;
        self::$isSuperAdmin = false;
        self::syncPostgresSession();
    }

    public static function currentSchoolId(): ?string
    {
        return self::$currentSchoolId;
    }

    public static function isSuperAdmin(): bool
    {
        return self::$isSuperAdmin;
    }

    public static function runInSchoolScope(string $schoolId, Closure $callback): mixed
    {
        $prevSchoolId = self::$currentSchoolId;
        $prevSuperAdmin = self::$isSuperAdmin;

        return DB::transaction(function () use ($schoolId, $callback, $prevSchoolId, $prevSuperAdmin) {
            self::setSchool($schoolId);

            try {
                return $callback();
            } finally {
                self::$currentSchoolId = $prevSchoolId;
                self::$isSuperAdmin = $prevSuperAdmin;
                self::syncPostgresSession();
            }
        });
    }

    public static function runAsSuperAdmin(Closure $callback): mixed
    {
        $prevSchoolId = self::$currentSchoolId;
        $prevSuperAdmin = self::$isSuperAdmin;

        return DB::transaction(function () use ($callback, $prevSchoolId, $prevSuperAdmin) {
            self::setSuperAdmin(true);

            try {
                return $callback();
            } finally {
                self::$currentSchoolId = $prevSchoolId;
                self::$isSuperAdmin = $prevSuperAdmin;
                self::syncPostgresSession();
            }
        });
    }

    /**
     * Jalankan callback dalam konteks tenant milik user tersebut
     * (super admin bila school_id NULL).
     */
    public static function runForUser(User $user, Closure $callback): mixed
    {
        return $user->school_id === null
            ? self::runAsSuperAdmin($callback)
            : self::runInSchoolScope($user->school_id, $callback);
    }

    /**
     * Membuka visibilitas baris users secara sempit untuk proses autentikasi:
     * hanya baris dengan email atau id yang diberikan yang lolos policy RLS
     * `users_tenant_or_auth_policy`. Tidak memberi akses tulis.
     */
    public static function withAuthLookup(?string $email, ?string $userId, Closure $callback): mixed
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $callback();
        }

        self::setAuthLookup($email ?? '', $userId ?? '');
        $failed = false;

        try {
            return $callback();
        } catch (Throwable $e) {
            $failed = true;

            throw $e;
        } finally {
            try {
                self::setAuthLookup('', '');
            } catch (Throwable $resetError) {
                if (! $failed) {
                    throw $resetError;
                }
            }
        }
    }

    /**
     * Cek keunikan email lintas sekolah (kolom users.email unik global).
     */
    public static function userEmailExists(string $email): bool
    {
        return (bool) self::withAuthLookup($email, null, fn (): bool => User::query()->where('email', $email)->exists());
    }

    private static function setAuthLookup(string $email, string $userId): void
    {
        DB::statement(
            "SELECT set_config('app.auth_lookup_email', ?, false), set_config('app.auth_lookup_user_id', ?, false)",
            [$email, $userId],
        );
    }

    private static function syncPostgresSession(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $isLocal = DB::transactionLevel() > 0;
        DB::statement("SELECT set_config('app.is_super_admin', ?, ?)", [
            self::$isSuperAdmin ? 'true' : 'false',
            $isLocal,
        ]);
        DB::statement("SELECT set_config('app.current_school_id', ?, ?)", [
            self::$currentSchoolId ?? '',
            $isLocal,
        ]);
    }
}
