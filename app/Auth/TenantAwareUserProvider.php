<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Provider autentikasi yang tetap bekerja di bawah FORCE ROW LEVEL SECURITY.
 *
 * Saat login dan pemulihan sesi belum ada konteks tenant, sehingga baris users
 * tidak terlihat. Provider ini membuka baris user yang dicari saja (berdasarkan
 * email atau id), dan menulis perubahan dalam konteks tenant milik user itu.
 */
final class TenantAwareUserProvider extends EloquentUserProvider
{
    /** @param mixed $identifier */
    public function retrieveById($identifier): ?Authenticatable
    {
        return TenantContext::withAuthLookup(null, (string) $identifier, fn () => parent::retrieveById($identifier));
    }

    /**
     * @param mixed $identifier
     * @param mixed $token
     */
    public function retrieveByToken($identifier, #[SensitiveParameter] $token): ?Authenticatable
    {
        return TenantContext::withAuthLookup(null, (string) $identifier, fn () => parent::retrieveByToken($identifier, $token));
    }

    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        return TenantContext::withAuthLookup($email, null, fn () => parent::retrieveByCredentials($credentials));
    }

    /** @param mixed $token */
    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] $token): void
    {
        $this->runForUser($user, fn () => parent::updateRememberToken($user, $token));
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        $this->runForUser($user, fn () => parent::rehashPasswordIfRequired($user, $credentials, $force));
    }

    private function runForUser(Authenticatable $user, Closure $callback): void
    {
        if ($user instanceof User) {
            TenantContext::runForUser($user, $callback);

            return;
        }

        $callback();
    }
}
