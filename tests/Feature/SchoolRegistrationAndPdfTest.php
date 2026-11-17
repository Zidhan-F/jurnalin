<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolRegistrationAndPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_school_cannot_login_until_super_admin_approves(): void
    {
        $this->seed();

        $this->post('/daftar-sekolah', [
            'npsn' => '20299887',
            'school_name' => 'SMK Pariwisata Merdeka',
            'address' => 'Jl. Asia Afrika No. 12',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'timezone' => 'Asia/Jakarta',
            'admin_name' => 'Admin Merdeka',
            'admin_email' => 'hubin@smkmerdeka.sch.id',
            'admin_phone' => '6281199887766',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'accept_terms' => '1',
        ])->assertRedirect('/login');

        $school = School::query()->where('npsn', '20299887')->firstOrFail();
        $this->assertSame(SchoolStatus::PendingVerification, $school->status);
        $this->assertNotNull($school->terms_accepted_at);

        // Login gagal sebelum verifikasi
        $this->post('/login', [
            'email' => 'hubin@smkmerdeka.sch.id',
            'password' => 'secret1234',
        ])->assertSessionHasErrors('email');

        // Super Admin aktifkan sekolah
        $superAdmin = $this->userByEmail('superadmin@jurnalin.id');
        $this->actingAs($superAdmin)
            ->post("/super-admin/schools/{$school->id}/approve")
            ->assertRedirect();

        $this->assertSame(SchoolStatus::Active, $school->fresh()->status);

        // Sekarang Admin sekolah baru dapat login dan masuk ke /admin
        $this->post('/login', [
            'email' => 'hubin@smkmerdeka.sch.id',
            'password' => 'secret1234',
        ])->assertRedirect('/admin');
    }
}
