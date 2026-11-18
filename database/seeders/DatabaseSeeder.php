<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\SchoolStatus;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Journal;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TenantContext;
use App\ValueObjects\AssessmentRubric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // RLS FORCE aktif: seluruh seeding berjalan sebagai super admin,
        // lalu runInSchoolScope mempersempit konteks per sekolah.
        TenantContext::runAsSuperAdmin(function (): void {
            $this->seedAll();
        });
    }

    private function seedAll(): void
    {
        $defaultPassword = Hash::make('password123');

        // 1. SUPER ADMIN
        User::query()->create([
            'school_id' => null,
            'name' => 'Zidhan (Admin Jurnalin)',
            'email' => 'superadmin@jurnalin.id',
            'phone' => '628110000001',
            'password' => $defaultPassword,
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        // 2. SEKOLAH A (AKTIF - SMK NEGERI 1 PARIWISATA BANDUNG)
        $schoolA = School::query()->create([
            'npsn' => '20219101',
            'name' => 'SMK Negeri 1 Pariwisata Bandung',
            'address' => 'Jl. Wastukencana No. 3, Tamansari',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'timezone' => 'Asia/Jakarta',
            'letterhead_config' => [
                'header_line_1' => 'PEMERINTAH DAERAH PROVINSI JAWA BARAT - DINAS PENDIDIKAN',
                'header_line_2' => 'SMK NEGERI 1 PARIWISATA BANDUNG',
                'sub_header' => 'Jl. Wastukencana No. 3, Kota Bandung 40117 | Telp. (022) 4201234',
                'principal_name' => 'Drs. Hendra Wijaya, M.Pd',
                'principal_nip' => '196905121994121002',
                'coordinator_name' => 'Rina Kartika, S.Par',
            ],
            'default_geofence_radius_m' => 200,
            'late_tolerance_minutes' => 15,
            'require_attendance_photo' => false,
            'require_industry_form_photo' => false,
            'subscription_tier' => 'per_student',
            'max_students_quota' => 250,
            'status' => SchoolStatus::Active,
            'verified_at' => now()->subMonths(2),
        ]);

        User::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'Rina Kartika, S.Par (Hubin)',
            'email' => 'admin@smkn1par-bdg.sch.id',
            'phone' => '6281220001001',
            'password' => $defaultPassword,
            'role' => UserRole::SchoolAdmin,
            'is_active' => true,
        ]);

        TenantContext::runInSchoolScope($schoolA->id, function () use ($schoolA, $defaultPassword): void {
            $teacherUser1 = User::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Budi Prasetyo, S.ST.Par',
                'email' => 'guru.perhotelan@smkn1par-bdg.sch.id',
                'phone' => '6281220002001',
                'password' => $defaultPassword,
                'role' => UserRole::Teacher,
                'is_active' => true,
            ]);
            $teacher1 = Teacher::query()->create([
                'school_id' => $schoolA->id,
                'user_id' => $teacherUser1->id,
                'nip_nik' => '198407152009021004',
                'specialization' => 'Perhotelan (Front Office & Housekeeping)',
                'max_mentees' => 25,
            ]);

            $teacherUser2 = User::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Sari Wulandari, M.Par',
                'email' => 'guru.kuliner@smkn1par-bdg.sch.id',
                'phone' => '6281220002002',
                'password' => $defaultPassword,
                'role' => UserRole::Teacher,
                'is_active' => true,
            ]);
            $teacher2 = Teacher::query()->create([
                'school_id' => $schoolA->id,
                'user_id' => $teacherUser2->id,
                'nip_nik' => '198811202014032001',
                'specialization' => 'Tata Boga & F&B Service',
                'max_mentees' => 20,
            ]);

            $hotelTrans = Company::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'The Trans Luxury Hotel Bandung',
                'industry_type' => 'Hotel Bintang 5',
                'address' => 'Jl. Gatot Subroto No. 289, Cibangkong',
                'city' => 'Kota Bandung',
                'contact_person_name' => 'Ibu Ratna Kusuma (HRD)',
                'contact_person_phone' => '628112200331',
                'latitude' => -6.9255000,
                'longitude' => 107.6365000,
                'geofence_radius_m' => 250,
            ]);

            $hotelPadma = Company::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Padma Hotel Bandung',
                'industry_type' => 'Resort Hotel',
                'address' => 'Jl. Rancabentang No. 56-58, Ciumbuleuit',
                'city' => 'Kota Bandung',
                'contact_person_name' => 'Pak Hendro (Training Coordinator)',
                'contact_person_phone' => '628112200332',
                'latitude' => -6.8669000,
                'longitude' => 107.6054000,
                'geofence_radius_m' => 300,
            ]);

            $hotelHilton = Company::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Hilton Bandung',
                'industry_type' => 'Hotel Bintang 5',
                'address' => 'Jl. HOS Tjokroaminoto No. 41-43',
                'city' => 'Kota Bandung',
                'contact_person_name' => 'Ibu Maya (L&D Manager)',
                'contact_person_phone' => '628112200333',
                'latitude' => -6.9114000,
                'longitude' => 107.5977000,
                'geofence_radius_m' => 200,
            ]);

            $periodA = PklPeriod::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'PKL Kelas XII Gelombang 1',
                'academic_year' => '2026/2027',
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'min_required_days' => 120,
                'assessment_template' => AssessmentRubric::defaultTemplate(),
                'is_active' => true,
            ]);

            // Siswa 1: Nadia (belum absen hari ini -> tampil di daftar Belum Absen di dashboard guru)
            $uNadia = User::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Nadia Putri Azzahra',
                'email' => 'siswa.nadia@smkn1par-bdg.sch.id',
                'phone' => '6285712345001',
                'password' => $defaultPassword,
                'role' => UserRole::Student,
            ]);
            $sNadia = Student::query()->create([
                'school_id' => $schoolA->id,
                'user_id' => $uNadia->id,
                'nisn' => '0081110001',
                'nis' => '24250101',
                'class_name' => 'XII Perhotelan 1',
                'major' => 'Perhotelan',
                'gender' => 'P',
                'parent_phone' => '628121110001',
            ]);
            $pNadia = Placement::query()->create([
                'school_id' => $schoolA->id,
                'pkl_period_id' => $periodA->id,
                'student_id' => $sNadia->id,
                'company_id' => $hotelTrans->id,
                'teacher_id' => $teacher1->id,
                'department_divisi' => 'Front Office',
                'industry_mentor_name' => 'Bapak Arif Rahman (FO Manager)',
                'industry_mentor_phone' => '628112200881',
                'work_start_time' => '07:00:00',
                'work_end_time' => '15:00:00',
                'status' => PlacementStatus::Active,
            ]);

            // Riwayat kehadiran & jurnal lampau Nadia
            Attendance::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pNadia->id,
                'student_id' => $sNadia->id,
                'attendance_date' => now()->subDay()->toDateString(),
                'status' => AttendanceStatus::Present,
                'check_in_at' => now()->subDay()->setTime(6, 52),
                'check_in_lat' => -6.9255100,
                'check_in_lng' => 107.6365200,
                'check_in_distance_m' => 14,
                'is_within_radius' => true,
                'check_out_at' => now()->subDay()->setTime(15, 10),
            ]);
            Journal::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pNadia->id,
                'student_id' => $sNadia->id,
                'journal_date' => now()->subDay()->toDateString(),
                'work_unit' => 'Front Office - Reception',
                'activity_title' => 'Penanganan Check-in Tamu Korporat dan Pembuatan Key Card',
                'activity_description' => 'Memeriksa reservasi grup di sistem Opera PMS, melakukan verifikasi KTP tamu, mencetak registration card, dan mengkodekan kartu kunci kamar lantai 9.',
                'tools_materials' => 'Opera PMS, Key Card Encoder, EDC Machine',
                'learning_outcome' => 'Memahami standar greeting 3 langkah dan kecepatan proses check-in di bawah 3 menit per tamu.',
                'duration_minutes' => 480,
                'status' => JournalStatus::Approved,
                'validated_by_teacher_id' => $teacher1->id,
                'validated_at' => now()->subHours(18),
            ]);

            // Siswa 2: Bagas (absen di luar radius today + jurnal submitted menunggu validasi)
            $uBagas = User::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Bagas Pratama',
                'email' => 'siswa.bagas@smkn1par-bdg.sch.id',
                'phone' => '6285712345002',
                'password' => $defaultPassword,
                'role' => UserRole::Student,
            ]);
            $sBagas = Student::query()->create([
                'school_id' => $schoolA->id,
                'user_id' => $uBagas->id,
                'nisn' => '0081110002',
                'nis' => '24250102',
                'class_name' => 'XII Perhotelan 1',
                'major' => 'Perhotelan',
                'gender' => 'L',
                'parent_phone' => '628121110002',
            ]);
            $pBagas = Placement::query()->create([
                'school_id' => $schoolA->id,
                'pkl_period_id' => $periodA->id,
                'student_id' => $sBagas->id,
                'company_id' => $hotelPadma->id,
                'teacher_id' => $teacher1->id,
                'department_divisi' => 'F&B Banquet Service',
                'industry_mentor_name' => 'Ibu Dinda (Banquet Captain)',
                'industry_mentor_phone' => '628112200882',
                'work_start_time' => '08:00:00',
                'work_end_time' => '16:00:00',
                'status' => PlacementStatus::Active,
            ]);
            $attBagasToday = Attendance::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pBagas->id,
                'student_id' => $sBagas->id,
                'attendance_date' => now('Asia/Jakarta')->toDateString(),
                'status' => AttendanceStatus::Present,
                'check_in_at' => now()->subHours(3),
                'check_in_lat' => -6.8900000,
                'check_in_lng' => 107.6100000,
                'check_in_distance_m' => 2615,
                'is_within_radius' => false,
                'exception_reason' => 'Ditugaskan Banquet Captain untuk outside catering acara pemerintah di Gedung Sate.',
            ]);
            Journal::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pBagas->id,
                'student_id' => $sBagas->id,
                'attendance_id' => $attBagasToday->id,
                'journal_date' => now('Asia/Jakarta')->toDateString(),
                'work_unit' => 'Banquet Outside Catering',
                'activity_title' => 'Setup Meja Prasmanan dan Coffee Break 150 Pax',
                'activity_description' => 'Menyiapkan chafing dish, menata taplak meja skirting, menyusun peralatan makan sesuai urutan menu, serta melayani tamu selama sesi coffee break pagi.',
                'tools_materials' => 'Chafing dish, round table, water goblet, chafing fuel',
                'learning_outcome' => 'Mengetahui urutan pemasangan table setting dan menjaga suhu penyajian makanan di acara luar hotel.',
                'duration_minutes' => 480,
                'status' => JournalStatus::Submitted,
            ]);

            // Siswa 3: Citra (punya jurnal revision_needed + nilai akhir sudah difinalisasi untuk uji PDF)
            $uCitra = User::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Citra Lestari',
                'email' => 'siswa.citra@smkn1par-bdg.sch.id',
                'phone' => '6285712345003',
                'password' => $defaultPassword,
                'role' => UserRole::Student,
            ]);
            $sCitra = Student::query()->create([
                'school_id' => $schoolA->id,
                'user_id' => $uCitra->id,
                'nisn' => '0081110003',
                'nis' => '24250103',
                'class_name' => 'XII Perhotelan 2',
                'major' => 'Perhotelan',
                'gender' => 'P',
                'parent_phone' => '628121110003',
            ]);
            $pCitra = Placement::query()->create([
                'school_id' => $schoolA->id,
                'pkl_period_id' => $periodA->id,
                'student_id' => $sCitra->id,
                'company_id' => $hotelHilton->id,
                'teacher_id' => $teacher1->id,
                'department_divisi' => 'Housekeeping',
                'industry_mentor_name' => 'Bapak Teguh (Executive Housekeeper)',
                'industry_mentor_phone' => '628112200883',
                'work_start_time' => '08:00:00',
                'work_end_time' => '16:00:00',
                'status' => PlacementStatus::Active,
            ]);
            Attendance::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pCitra->id,
                'student_id' => $sCitra->id,
                'attendance_date' => now('Asia/Jakarta')->toDateString(),
                'status' => AttendanceStatus::Present,
                'check_in_at' => now()->subHours(2),
                'check_in_lat' => -6.9114100,
                'check_in_lng' => 107.5977100,
                'check_in_distance_m' => 11,
                'is_within_radius' => true,
            ]);
            Journal::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pCitra->id,
                'student_id' => $sCitra->id,
                'journal_date' => now()->subDays(2)->toDateString(),
                'work_unit' => 'Housekeeping - Room Section',
                'activity_title' => 'Make Up Room Kamar Deluxe',
                'activity_description' => 'Membersihkan kamar tamu yang sudah check-out.',
                'duration_minutes' => 480,
                'status' => JournalStatus::RevisionNeeded,
                'teacher_feedback' => 'Uraian terlalu singkat. Tambahkan jenis chemical pembersih kamar mandi yang dipakai dan proses pengecekan minibar.',
                'validated_by_teacher_id' => $teacher1->id,
                'validated_at' => now()->subDay(),
            ]);
            Journal::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pCitra->id,
                'student_id' => $sCitra->id,
                'journal_date' => now()->subDays(3)->toDateString(),
                'work_unit' => 'Housekeeping - Linen & Laundry',
                'activity_title' => 'Sortir Linen Kotor dan Pengisian Trolley Lantai 5',
                'activity_description' => 'Memisahkan bed sheet, duvet cover, dan bath towel bernoda berat untuk proses spotting di laundry, lalu menata stok linen bersih di pantry lantai 5.',
                'tools_materials' => 'Linen trolley, spotting chemical, laundry bag',
                'learning_outcome' => 'Memahami standar par stock linen dan cara pelabelan barang tertinggal tamu (Lost and Found).',
                'duration_minutes' => 480,
                'status' => JournalStatus::Approved,
                'validated_by_teacher_id' => $teacher1->id,
                'validated_at' => now()->subDays(2),
            ]);
            Assessment::query()->create([
                'school_id' => $schoolA->id,
                'placement_id' => $pCitra->id,
                'student_id' => $sCitra->id,
                'teacher_id' => $teacher1->id,
                'industry_scores' => [
                    'scores' => ['soft_skills' => 92, 'hard_skills' => 88, 'initiative' => 90],
                    'weighted_average' => 89.90,
                    'signed_by_name' => 'Bapak Teguh (Executive Housekeeper)',
                ],
                'school_scores' => [
                    'scores' => ['journal_quality' => 86, 'report_presentation' => 88],
                    'weighted_average' => 87.00,
                ],
                'attendance_score' => 95.00,
                'final_score' => 89.83,
                'predicate' => 'Baik',
                'industry_notes' => 'Cepat beradaptasi dan rapi dalam penataan bed making.',
                'teacher_notes' => 'Perlu lebih teliti mendokumentasikan nama bahan pembersih di jurnal.',
                'certificate_number' => 'PKL/2026/XII-PH/003',
                'finalized_at' => now()->subHour(),
            ]);

            // Siswa 4 di bimbingan Guru 2 (Kuliner)
            $uDimas = User::query()->create([
                'school_id' => $schoolA->id,
                'name' => 'Dimas Nugraha',
                'email' => 'siswa.dimas@smkn1par-bdg.sch.id',
                'phone' => '6285712345004',
                'password' => $defaultPassword,
                'role' => UserRole::Student,
            ]);
            $sDimas = Student::query()->create([
                'school_id' => $schoolA->id,
                'user_id' => $uDimas->id,
                'nisn' => '0081110004',
                'nis' => '24250104',
                'class_name' => 'XII Kuliner 1',
                'major' => 'Tata Boga / Kuliner',
                'gender' => 'L',
                'parent_phone' => '628121110004',
            ]);
            Placement::query()->create([
                'school_id' => $schoolA->id,
                'pkl_period_id' => $periodA->id,
                'student_id' => $sDimas->id,
                'company_id' => $hotelTrans->id,
                'teacher_id' => $teacher2->id,
                'department_divisi' => 'Pastry & Bakery Kitchen',
                'industry_mentor_name' => 'Chef Rendra',
                'work_start_time' => '06:00:00',
                'work_end_time' => '14:00:00',
                'status' => PlacementStatus::Active,
            ]);
            $periodA->placements()->update(['legacy_access' => true]);

            // Kelas dan grup penempatan demo: kelas dari data siswa, satu grup per tempat PKL + divisi + guru.
            foreach (Student::query()->get()->groupBy('class_name') as $className => $members) {
                $class = \App\Models\PeriodClass::query()->create(['school_id' => $schoolA->id, 'pkl_period_id' => $periodA->id, 'name' => $className, 'major' => $members->first()->major]);
                foreach ($members as $m) {
                    \Illuminate\Support\Facades\DB::table('period_class_members')->insert(['period_class_id' => $class->id, 'school_id' => $schoolA->id, 'pkl_period_id' => $periodA->id, 'student_id' => $m->id]);
                }
            }
            foreach ($periodA->placements()->with('company')->get()->groupBy(fn ($p) => $p->company_id.'|'.$p->department_divisi.'|'.$p->teacher_id) as $members) {
                $first = $members->first();
                $group = \App\Models\PlacementGroup::query()->create([
                    'school_id' => $schoolA->id, 'pkl_period_id' => $periodA->id, 'name' => $first->company->name.' - '.$first->department_divisi,
                    'company_id' => $first->company_id, 'teacher_id' => $first->teacher_id, 'department_divisi' => $first->department_divisi,
                    'work_start_time' => $first->work_start_time, 'work_end_time' => $first->work_end_time,
                ]);
                Placement::query()->whereIn('id', $members->pluck('id'))->update(['placement_group_id' => $group->id]);
            }
        });

        // 3. SEKOLAH B (TENANT KEDUA - ISOLATED)
        $schoolB = School::query()->create([
            'npsn' => '50103822',
            'name' => 'SMK Teknologi Harapan Bali',
            'address' => 'Jl. Bypass Ngurah Rai No. 88',
            'city' => 'Kabupaten Badung',
            'province' => 'Bali',
            'timezone' => 'Asia/Makassar',
            'letterhead_config' => [
                'header_line_1' => 'YAYASAN PENDIDIKAN PARIWISATA HARAPAN BALI',
                'header_line_2' => 'SMK TEKNOLOGI HARAPAN BALI',
                'sub_header' => 'Jl. Bypass Ngurah Rai No. 88, Badung, Bali',
                'principal_name' => 'I Made Sudarma, S.Kom., M.T',
                'principal_nip' => '-',
                'coordinator_name' => 'Ni Luh Sari, S.Pd',
            ],
            'default_geofence_radius_m' => 250,
            'late_tolerance_minutes' => 15,
            'require_attendance_photo' => true,
            'require_industry_form_photo' => true,
            'subscription_tier' => 'free_pilot',
            'max_students_quota' => 35,
            'status' => SchoolStatus::Active,
            'verified_at' => now()->subMonth(),
        ]);

        User::query()->create([
            'school_id' => $schoolB->id,
            'name' => 'Ni Luh Sari, S.Pd (Admin Bali)',
            'email' => 'admin@smkharapan-bali.sch.id',
            'phone' => '6281337001001',
            'password' => $defaultPassword,
            'role' => UserRole::SchoolAdmin,
            'is_active' => true,
        ]);

        TenantContext::runInSchoolScope($schoolB->id, function () use ($schoolB, $defaultPassword): void {
            $uTeacherB = User::query()->create([
                'school_id' => $schoolB->id,
                'name' => 'Putu Mahendra, S.Kom',
                'email' => 'guru@smkharapan-bali.sch.id',
                'phone' => '6281337002001',
                'password' => $defaultPassword,
                'role' => UserRole::Teacher,
            ]);
            $teacherB = Teacher::query()->create([
                'school_id' => $schoolB->id,
                'user_id' => $uTeacherB->id,
                'specialization' => 'Rekayasa Perangkat Lunak',
            ]);
            $companyB = Company::query()->create([
                'school_id' => $schoolB->id,
                'name' => 'PT Bali Digital Solusi',
                'industry_type' => 'Software House / Perusahaan IT',
                'address' => 'Jl. Raya Nusa Dua Selatan No. 12, Sawangan',
                'city' => 'Kabupaten Badung',
                'latitude' => -8.8287000,
                'longitude' => 115.2155000,
                'geofence_radius_m' => 350,
            ]);
            $periodB = PklPeriod::query()->create([
                'school_id' => $schoolB->id,
                'name' => 'PKL Semester Ganjil Bali',
                'academic_year' => '2026/2027',
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => now()->addMonths(4)->toDateString(),
                'min_required_days' => 120,
                'assessment_template' => AssessmentRubric::defaultTemplate(),
                'is_active' => true,
            ]);
            $uStudentB = User::query()->create([
                'school_id' => $schoolB->id,
                'name' => 'Kadek Ayu Permata',
                'email' => 'siswa.ayu@smkharapan-bali.sch.id',
                'password' => $defaultPassword,
                'role' => UserRole::Student,
            ]);
            $studentB = Student::query()->create([
                'school_id' => $schoolB->id,
                'user_id' => $uStudentB->id,
                'nisn' => '0089990001',
                'class_name' => 'XII RPL 1',
                'major' => 'Rekayasa Perangkat Lunak',
            ]);
            $placementB = Placement::query()->create([
                'school_id' => $schoolB->id,
                'pkl_period_id' => $periodB->id,
                'student_id' => $studentB->id,
                'company_id' => $companyB->id,
                'teacher_id' => $teacherB->id,
                'department_divisi' => 'Tim Pengembangan Web',
                'status' => PlacementStatus::Active,
            ]);
            Journal::query()->create([
                'school_id' => $schoolB->id,
                'placement_id' => $placementB->id,
                'student_id' => $studentB->id,
                'journal_date' => now()->toDateString(),
                'work_unit' => 'Tim Pengembangan Web',
                'activity_title' => 'Data Rahasia Tenant Sekolah Bali',
                'activity_description' => 'Membuat halaman login aplikasi reservasi klien memakai Laravel dan menulis unit test.',
                'status' => JournalStatus::Submitted,
            ]);
        });

        // 4. SEKOLAH C (MENUNGGU VERIFIKASI NPSN OLEH SUPER ADMIN)
        $schoolC = School::query()->create([
            'npsn' => '20404199',
            'name' => 'SMK Kesehatan Nusantara Yogyakarta',
            'address' => 'Jl. Kaliurang KM 6.5, Sleman',
            'city' => 'Kabupaten Sleman',
            'province' => 'DI Yogyakarta',
            'timezone' => 'Asia/Jakarta',
            'letterhead_config' => [
                'header_line_2' => 'SMK KESEHATAN NUSANTARA YOGYAKARTA',
            ],
            'status' => SchoolStatus::PendingVerification,
        ]);

        User::query()->create([
            'school_id' => $schoolC->id,
            'name' => 'Agus Santoso (Calon Admin Yogya)',
            'email' => 'admin@smkparnus-yk.sch.id',
            'phone' => '628156800111',
            'password' => $defaultPassword,
            'role' => UserRole::SchoolAdmin,
            'is_active' => true,
        ]);

        $this->seedSpecV2($schoolA, $schoolB);
    }

    /**
     * Data demo Spesifikasi v2: perjanjian pemrosesan data disetujui, persetujuan wali (formulir kertas)
     * untuk siswa demo agar lokasi & swafoto aktif, dan seluruh modul uji coba aktif di Sekolah A.
     */
    private function seedSpecV2(School $schoolA, School $schoolB): void
    {
        foreach ([$schoolA, $schoolB] as $school) {
            $school->forceFill(['dpa_accepted_at' => now(), 'dpa_version' => \App\Http\Controllers\Spec\PrivacyController::DPA_VERSION])->save();
            $school->refresh();
            TenantContext::runInSchoolScope($school->id, function () use ($school): void {
                $recorder = User::query()->where('role', UserRole::SchoolAdmin)->first();
                foreach (Student::query()->with('user')->get() as $student) {
                    \App\Models\GuardianConsent::query()->create([
                        'school_id' => $school->id,
                        'student_id' => $student->id,
                        'guardian_name' => 'Orang tua '.$student->user->name,
                        'relation' => 'Orang tua',
                        'method' => 'paper',
                        'notice_version' => $school->privacy_notice_version ?? '2026-11',
                        'recorded_by_user_id' => $recorder?->id,
                        'given_at' => now(),
                    ]);
                }
                \App\Support\LearningObjectives::forSchool($school);
            });
        }

        foreach ([...array_keys(\App\Support\Features::ADDONS), ...array_keys(\App\Support\Features::PRO)] as $feature) {
            \App\Support\Features::set($schoolA, $feature, true, 'pilot');
        }
    }
}
