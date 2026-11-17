<?php

namespace Tests;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ambil user lewat jalur lookup autentikasi, karena FORCE RLS menyembunyikan
     * baris users saat belum ada konteks tenant.
     */
    protected function userByEmail(string $email): User
    {
        return TenantContext::withAuthLookup(
            $email,
            null,
            fn (): User => User::query()->where('email', $email)->firstOrFail(),
        );
    }
}
