<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Mascot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MascotTest extends TestCase
{
    public static function genders(): array
    {
        return [
            'L' => ['L', 'student-male.svg'],
            'l kecil' => ['l', 'student-male.svg'],
            'P' => ['P', 'student-female.svg'],
            'p kecil' => ['p', 'student-female.svg'],
            'kosong' => [null, 'student-neutral.svg'],
            'string kosong' => ['', 'student-neutral.svg'],
        ];
    }

    #[DataProvider('genders')]
    public function test_student_mascot_follows_gender(?string $gender, string $file): void
    {
        $this->assertStringEndsWith($file, Mascot::forStudent($gender));
    }

    public function test_initials_skip_academic_titles(): void
    {
        $this->assertSame('BP', Mascot::initials('Budi Prasetyo, S.ST.Par'));
        $this->assertSame('N', Mascot::initials('Nadia'));
        $this->assertSame('RK', Mascot::initials('Rina Kartika Sari'));
    }

    public function test_every_role_illustration_file_exists(): void
    {
        $paths = [Mascot::forStudent('L'), Mascot::forStudent('P'), Mascot::forStudent(null), Mascot::teacher()];
        foreach (Mascot::ROLES as $role) {
            $paths[] = Mascot::role($role);
        }
        $public = dirname(__DIR__, 2).'/public/';
        foreach ($paths as $path) {
            $this->assertFileExists($public.ltrim($path, '/'));
            $this->assertLessThanOrEqual(15 * 1024, filesize($public.ltrim($path, '/')), $path.' melebihi 15 KB');
        }
    }
}
