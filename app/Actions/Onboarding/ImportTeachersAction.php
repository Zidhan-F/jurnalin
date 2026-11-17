<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Support\SpreadsheetHelper;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class ImportTeachersAction
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
     * Satu-satunya jalur pembuatan akun guru (form admin dan impor Excel).
     * Kunci kolom Excel (nama, no_wa, ...) dan kunci form (name, phone, ...) sama-sama diterima.
     * Harus dipanggil di dalam konteks sekolah.
     *
     * @param  array<string, mixed>  $row
     * @return string|null pesan galat, atau null bila berhasil
     */
    public function createOne(School $school, array $row, string $defaultPassword = 'password123'): ?string
    {
        $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        if ($name === '' || $email === '') {
            return 'Kolom wajib (nama, email) tidak lengkap.';
        }

        if (TenantContext::userEmailExists($email)) {
            return "Email {$email} sudah digunakan akun lain.";
        }

        $user = DB::transaction(function () use ($school, $row, $name, $email, $defaultPassword): User {
            $user = User::query()->create([
                'school_id' => $school->id,
                'name' => $name,
                'email' => $email,
                'phone' => ($row['no_wa'] ?? $row['phone'] ?? null) ?: null,
                'password' => $defaultPassword,
                'role' => UserRole::Teacher,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            Teacher::query()->create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'nip_nik' => ($row['nip_nik'] ?? $row['nip'] ?? null) ?: null,
                'specialization' => ($row['spesialisasi'] ?? $row['specialization'] ?? null) ?: null,
                'max_mentees' => max(1, (int) ($row['maks_bimbingan'] ?? $row['max_mentees'] ?? 20)),
            ]);

            return $user;
        });

        // Gagal kirim email tidak boleh membatalkan akun yang sudah dibuat.
        try {
            $user->notify(new AccountCreated($school->name));
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
