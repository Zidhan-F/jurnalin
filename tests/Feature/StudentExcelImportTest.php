<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Onboarding\ImportStudentsAction;
use App\Models\School;
use App\Models\Student;
use App\Support\SpreadsheetHelper;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_students_from_xlsx_and_skips_duplicate_nisn(): void
    {
        $this->seed();

        $schoolA = School::query()->where('npsn', '20219101')->firstOrFail();
        $xlsxPath = storage_path('app/private/test-import-siswa.xlsx');
        if (! is_dir(dirname($xlsxPath))) {
            mkdir(dirname($xlsxPath), 0755, true);
        }

        SpreadsheetHelper::writeXlsx($xlsxPath, [
            ['nisn', 'nis', 'nama', 'email', 'no_wa', 'kelas', 'jurusan', 'jenis_kelamin', 'no_wa_ortu'],
            ['0089998881', '2425901', 'Aulia Zahra', 'aulia.zahra@siswa.sch.id', '62812340001', 'XII Perhotelan 3', 'Perhotelan', 'P', '62812340009'],
            ['0081110001', '24250101', 'Duplikat Nadia', 'dup.nadia@siswa.sch.id', '62812340002', 'XII Perhotelan 1', 'Perhotelan', 'P', '62812340008'],
        ]);

        try {
            $result = (new ImportStudentsAction())->handle($schoolA, $xlsxPath, 'xlsx');
            $this->assertSame(1, $result['imported']);
            $this->assertSame(1, $result['skipped']);

            TenantContext::runInSchoolScope($schoolA->id, function (): void {
                $this->assertTrue(Student::query()->where('nisn', '0089998881')->exists());
            });
        } finally {
            @unlink($xlsxPath);
        }
    }
}
