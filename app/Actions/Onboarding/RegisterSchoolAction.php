<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\SchoolStatus;
use App\Enums\SchoolPlan;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use App\ValueObjects\AssessmentRubric;
use App\ValueObjects\Npsn;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RegisterSchoolAction
{
    /**
     * @param  array{npsn:string, school_name:string, address:string, city:string, province:string, timezone?:string, admin_name:string, admin_email:string, admin_phone:string, password:string}  $data
     */
    public function handle(array $data): School
    {
        $npsn = new Npsn($data['npsn']);
        $data['admin_email'] = strtolower($data['admin_email']);

        if (School::query()->where('npsn', $npsn->value)->exists()) {
            throw new DomainException("Sekolah dengan NPSN {$npsn->value} sudah terdaftar.");
        }

        if (TenantContext::userEmailExists($data['admin_email'])) {
            throw new DomainException("Email {$data['admin_email']} sudah digunakan oleh akun lain.");
        }

        return DB::transaction(function () use ($data, $npsn): School {
            $school = School::query()->create([
                'npsn' => $npsn->value,
                'name' => $data['school_name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'province' => $data['province'],
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'letterhead_config' => [
                    'header_line_1' => 'PEMERINTAH DAERAH PROVINSI / YAYASAN PENDIDIKAN',
                    'header_line_2' => strtoupper($data['school_name']),
                    'sub_header' => $data['address'].', '.$data['city'],
                    'principal_name' => '',
                    'principal_nip' => '',
                    'coordinator_name' => $data['admin_name'],
                ],
                'default_geofence_radius_m' => 200,
                'late_tolerance_minutes' => 15,
                'require_attendance_photo' => true,
                'require_industry_form_photo' => true,
                'subscription_tier' => SchoolPlan::Free->value,
                'max_students_quota' => SchoolPlan::FREE_STUDENT_LIMIT,
                'status' => SchoolStatus::PendingVerification,
                'terms_accepted_at' => now(),
            ]);

            TenantContext::runInSchoolScope($school->id, function () use ($school, $data): void {
                User::query()->create([
                    'school_id' => $school->id,
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'phone' => $data['admin_phone'],
                    'password' => $data['password'],
                    'role' => UserRole::SchoolAdmin,
                    'is_active' => true,
                ]);

                // Buat 1 periode contoh default agar admin tidak memulai dari nol
                $school->pklPeriods()->create([
                    'school_id' => $school->id,
                    'name' => 'Periode PKL Gelombang 1',
                    'academic_year' => now()->format('Y').'/'.now()->addYear()->format('Y'),
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->addMonths(6)->endOfMonth()->toDateString(),
                    'min_required_days' => 120,
                    'assessment_template' => AssessmentRubric::defaultTemplate(),
                    'is_active' => true,
                ]);
            });

            return $school;
        });
    }
}
