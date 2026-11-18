<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SchoolPlan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Menu panel per peran: label grup => [nama rute => label]. Dipakai sidebar desktop dan menu HP.
 */
final class PanelNavigation
{
    /** @return array<string, array<string, string>> */
    public static function for(User $user, Request $request): array
    {
        return match ($user->role) {
            UserRole::SchoolAdmin => self::schoolAdmin($user),
            UserRole::Teacher => self::teacher($user, $request),
            UserRole::SuperAdmin => ['Utama' => [
                'super-admin.index' => 'Verifikasi sekolah', 'super-admin.payments' => 'Verifikasi pembayaran',
                'super-admin.modules' => 'Modul sekolah', 'super-admin.operations' => 'Operasional',
            ]],
            default => [],
        };
    }

    /** @return array<string, array<string, string>> */
    private static function schoolAdmin(User $user): array
    {
        $school = $user->school;

        return [
            'Utama' => [
                'admin.index' => 'Dashboard', 'notifications.index' => 'Notifikasi', 'admin.pending-journals' => 'Jurnal tertunda',
            ],
            'Manajemen PKL' => [
                'admin.periods' => 'Periode & bobot nilai', 'admin.companies' => 'Tempat PKL',
                'admin.teachers' => 'Guru pembimbing', 'admin.students' => 'Siswa',
                'admin.placements' => 'Penempatan siswa', 'admin.import' => 'Impor dari Excel',
                'visits.index' => 'Kunjungan guru', 'tickets.inbox' => 'Laporan dari siswa',
                'mentor-access.index' => 'Akses pembimbing industri',
                ...($school->hasFeature('hubin') ? ['admin.hubin.center' => 'Pusat Hubin'] : []),
                ...($school->plan() === SchoolPlan::Pro ? ['admin.hubin' => 'Rekap Hubin'] : []),
            ],
            'Program sekolah' => array_filter([
                'admin.internships' => $school->hasFeature('internship') ? 'Magang guru' : null,
                'admin.ukk' => $school->hasFeature('ukk') ? 'Uji Kompetensi (UKK)' : null,
                'admin.tracer' => $school->hasFeature('tracer') ? 'Alumni (tracer study)' : null,
                'admin.readiness' => 'Pembekalan pra-PKL',
                'admin.objectives' => 'Tujuan Pembelajaran (TP)',
                'admin.dudi-map' => $school->hasFeature('hubin') ? 'Peta DUDI' : null,
            ]),
            'Sekolah & akun' => [
                'admin.settings' => 'Pengaturan & kop surat', 'admin.payments' => 'Pembayaran',
                'admin.modules' => 'Fitur tambahan', 'admin.privacy' => 'Privasi data',
                'admin.activity' => 'Riwayat aktivitas',
            ],
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function teacher(User $user, Request $request): array
    {
        if ($request->attributes->get('grace_placements') && ! $request->attributes->get('full_placements')) {
            return ['Utama' => [
                'teacher.index' => 'Validasi jurnal',
                ...($user->school->hasFeature('internship') ? ['teacher.internship' => 'Magang guru'] : []),
            ]];
        }

        return [
            'Utama' => ['teacher.index' => 'Hari ini', 'teacher.review' => 'Periksa jurnal', 'teacher.assessments' => 'Nilai dan laporan'],
            'Pendampingan' => [
                'visits.index' => 'Kunjungan', 'tickets.inbox' => 'Laporan dari siswa', 'mentor-access.index' => 'Akses pembimbing industri',
                ...($user->school->hasFeature('internship') ? ['teacher.internship' => 'Magang guru'] : []),
            ],
        ];
    }
}
