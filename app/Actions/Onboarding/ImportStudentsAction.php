<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Support\SpreadsheetHelper;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class ImportStudentsAction
{
    /**
     * @return array{imported:int, skipped:int, errors:list<string>}
     */
    public function handle(School $school, string $filePath, ?string $extension = null): array
    {
        $rows = SpreadsheetHelper::readAssociativeRows($filePath, $extension);

        return TenantContext::runInSchoolScope($school->id, function () use ($school, $rows): array {
            $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

            foreach ($rows as $idx => $row) {
                $error = $this->createOne($school, $row);
                if ($error === null) {
                    $result['imported']++;
                } else {
                    $result['skipped']++;
                    $result['errors'][] = 'Baris '.($idx + 2).": {$error}";
                }
            }

            return $result;
        });
    }

    /**
     * Satu-satunya jalur pembuatan akun siswa (form admin dan impor Excel).
     * Kunci kolom Excel (nama, kelas, ...) dan kunci form (name, class_name, ...) sama-sama diterima.
     * Harus dipanggil di dalam konteks sekolah.
     *
     * @param  array<string, mixed>  $row
     * @return string|null pesan galat, atau null bila berhasil
     */
    public function createOne(School $school, array $row, string $defaultPassword = 'password123'): ?string
    {
        $nisn = trim((string) ($row['nisn'] ?? ''));
        $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $className = trim((string) ($row['kelas'] ?? $row['class_name'] ?? ''));
        $major = trim((string) ($row['jurusan'] ?? $row['major'] ?? ''));

        if ($nisn === '' || $name === '' || $email === '' || $className === '' || $major === '') {
            return 'Kolom wajib (nisn, nama, email, kelas, jurusan) tidak lengkap.';
        }

        if (Student::query()->where('nisn', $nisn)->exists()) {
            return "NISN {$nisn} sudah terdaftar di sekolah ini.";
        }

        if (TenantContext::userEmailExists($email)) {
            return "Email {$email} sudah digunakan akun lain.";
        }

        $gender = strtoupper((string) ($row['jenis_kelamin'] ?? $row['gender'] ?? ''));

        $user = DB::transaction(function () use ($school, $row, $nisn, $name, $email, $className, $major, $gender, $defaultPassword): ?User {
            $school = School::query()->lockForUpdate()->findOrFail($school->id);
            if (Student::query()->count() >= $school->studentLimit()) {
                return null;
            }
            $user = User::query()->create([
                'school_id' => $school->id,
                'name' => $name,
                'email' => $email,
                'phone' => ($row['no_wa'] ?? $row['phone'] ?? null) ?: null,
                'password' => $defaultPassword,
                'role' => UserRole::Student,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            Student::query()->create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'nisn' => $nisn,
                'nis' => ($row['nis'] ?? null) ?: null,
                'class_name' => $className,
                'major' => $major,
                'gender' => in_array($gender, ['L', 'P'], true) ? $gender : null,
                'parent_phone' => ($row['no_wa_ortu'] ?? $row['parent_phone'] ?? null) ?: null,
            ]);

            return $user;
        });

        if ($user === null) {
            return "Kuota maksimal siswa ({$school->studentLimit()}) telah tercapai.";
        }

        // Gagal kirim email tidak boleh membatalkan akun yang sudah dibuat.
        try {
            $user->notify(new AccountCreated($school->name));
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
