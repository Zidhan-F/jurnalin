<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AvatarComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_avatar_shows_gendered_mascot(): void
    {
        $this->seed();
        $nadia = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $bagas = $this->userByEmail('siswa.bagas@smkn1par-bdg.sch.id');

        TenantContext::runInSchoolScope($nadia->school_id, function () use ($nadia, $bagas): void {
            $this->assertStringContainsString('student-female.svg', Blade::render('<x-avatar :user="$u" />', ['u' => $nadia]));
            $this->assertStringContainsString('student-male.svg', Blade::render('<x-avatar :user="$u" />', ['u' => $bagas]));
        });
    }

    public function test_staff_avatar_shows_initials(): void
    {
        $this->seed();
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        $html = Blade::render('<x-avatar :user="$u" size="md" />', ['u' => $teacher]);

        $this->assertStringContainsString('>BP<', str_replace([' ', "\n"], '', $html));
        $this->assertStringNotContainsString('<img', $html);
    }
}
