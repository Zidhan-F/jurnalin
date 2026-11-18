<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngBinary(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    public function test_every_role_can_upload_view_replace_and_delete_personal_profile_photo(): void
    {
        $this->seed();
        Storage::fake('local');

        $emails = [
            'superadmin@jurnalin.id',
            'admin@smkn1par-bdg.sch.id',
            'guru.perhotelan@smkn1par-bdg.sch.id',
            'siswa.nadia@smkn1par-bdg.sch.id',
        ];

        foreach ($emails as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user)->get('/profil/foto')
                ->assertOk()
                ->assertSee('Foto Profil Pribadi');

            $firstUpload = UploadedFile::fake()->image('avatar-pertama.png', 160, 160);
            $this->actingAs($user)
                ->post('/profil/foto', ['photo' => $firstUpload])
                ->assertRedirect('/profil/foto');

            $user = TenantContext::runForUser($user, fn () => $user->fresh());
            $firstPath = $user->avatar_path;
            $this->assertNotNull($firstPath);
            Storage::disk('local')->assertExists($firstPath);

            // Media endpoint dapat diakses oleh pemiliknya dengan nosniff
            $this->actingAs($user)
                ->get('/media/'.$firstPath)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            // Komponen avatar langsung menampilkan foto baru
            $avatarHtml = TenantContext::runForUser($user, fn () => Blade::render('<x-avatar :user="$u" />', ['u' => $user]));
            $this->assertStringContainsString('/media/'.$firstPath, $avatarHtml);
            $this->assertStringContainsString('avatar-photo', $avatarHtml);

            // Ganti foto kedua: berkas lama harus ikut dihapus
            $secondUpload = UploadedFile::fake()->image('avatar-kedua.jpg', 180, 180);
            $this->actingAs($user)
                ->post('/profil/foto', ['photo' => $secondUpload])
                ->assertRedirect('/profil/foto');

            $user = TenantContext::runForUser($user, fn () => $user->fresh());
            $secondPath = $user->avatar_path;
            $this->assertNotNull($secondPath);
            $this->assertNotSame($firstPath, $secondPath);
            Storage::disk('local')->assertMissing($firstPath);
            Storage::disk('local')->assertExists($secondPath);

            // Hapus foto: kembali ke avatar bawaan
            $this->actingAs($user)
                ->delete('/profil/foto')
                ->assertRedirect('/profil/foto');

            $user = TenantContext::runForUser($user, fn () => $user->fresh());
            $this->assertNull($user->avatar_path);
            Storage::disk('local')->assertMissing($secondPath);
        }
    }

    public function test_profile_photo_supports_compressed_data_url_upload(): void
    {
        $this->seed();
        Storage::fake('local');

        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $dataUrl = 'data:image/png;base64,'.base64_encode($this->tinyPngBinary());

        $this->actingAs($teacher)
            ->post('/profil/foto', ['photo_data_url' => $dataUrl])
            ->assertRedirect('/profil/foto');

        $teacher = TenantContext::runForUser($teacher, fn () => $teacher->fresh());
        $this->assertNotNull($teacher->avatar_path);
        $this->assertStringContainsString("schools/{$teacher->school_id}/avatars/", $teacher->avatar_path);
        Storage::disk('local')->assertExists($teacher->avatar_path);
    }

    public function test_cross_tenant_and_platform_avatar_access_is_blocked(): void
    {
        $this->seed();
        Storage::fake('local');

        $superAdmin = $this->userByEmail('superadmin@jurnalin.id');
        $adminA = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $adminB = $this->userByEmail('admin@smkharapan-bali.sch.id');

        $this->actingAs($superAdmin)->post('/profil/foto', [
            'photo' => UploadedFile::fake()->image('super.png', 100, 100),
        ])->assertRedirect('/profil/foto');

        $this->actingAs($adminA)->post('/profil/foto', [
            'photo' => UploadedFile::fake()->image('admin-a.png', 100, 100),
        ])->assertRedirect('/profil/foto');

        $superPath = TenantContext::runAsSuperAdmin(fn () => User::query()->findOrFail($superAdmin->id)->avatar_path);
        $adminAPath = TenantContext::runForUser($adminA, fn () => User::query()->findOrFail($adminA->id)->avatar_path);

        // Admin sekolah A tidak boleh melihat foto super admin; Admin sekolah B tidak boleh melihat foto sekolah A.
        $this->actingAs($adminA)->get('/media/'.$superPath)->assertForbidden();
        $this->actingAs($adminB)->get('/media/'.$adminAPath)->assertForbidden();
        $this->actingAs($adminB)->get('/media/'.$superPath)->assertForbidden();
    }

    public function test_disguised_html_is_rejected_on_profile_photo_upload(): void
    {
        $this->seed();
        Storage::fake('local');

        $student = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $html = '<html><body><script>alert(1)</script></body></html>';
        $tmp = tempnam(sys_get_temp_dir(), 'xss_avatar');
        file_put_contents($tmp, $html);

        try {
            $this->actingAs($student)->post('/profil/foto', [
                'photo' => new UploadedFile($tmp, 'avatar.png', null, null, true),
            ])->assertSessionHasErrors('photo');
        } finally {
            @unlink($tmp);
        }

        $this->actingAs($student)->post('/profil/foto', [
            'photo_data_url' => 'data:image/png;base64,'.base64_encode($html),
        ])->assertSessionHasErrors('photo');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $avatarPath = TenantContext::runForUser($student, fn () => $student->fresh()->avatar_path);
        $this->assertNull($avatarPath);
    }
}
