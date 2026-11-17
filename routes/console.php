<?php

use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\PartnerAgreement;
use App\Models\Placement;
use App\Models\School;
use App\Models\User;
use App\Support\MediaStorage;
use App\Support\Notify;
use App\Support\TenantContext;
use App\Support\WhatsAppGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schedule;

/*
| Tugas terjadwal (php artisan schedule:work). Setiap sekolah diproses di konteks tenant-nya sendiri.
*/

// P15/P42: ringkasan harian guru dan pengingat jurnal siswa. Tidak aktif pada hari libur atau izin yang disahkan.
Artisan::command('jurnalin:ringkasan-harian', function (): void {
    $schools = TenantContext::runAsSuperAdmin(fn () => School::query()->where('status', 'active')->get());
    foreach ($schools as $school) {
        TenantContext::runInSchoolScope($school->id, function () use ($school): void {
            $today = CarbonImmutable::now($school->timezone);
            $placements = Placement::query()->with(['student.user', 'teacher.user', 'pklPeriod'])->where('status', PlacementStatus::Active)->get()
                ->filter(fn ($p) => $p->pklPeriod->isScheduledDay($today));
            foreach ($placements->groupBy('teacher_id') as $items) {
                $teacher = $items->first()->teacher->user;
                $ids = $items->pluck('id');
                $vars = [
                    'pending_attendance' => $items->count() - Attendance::query()->whereIn('placement_id', $ids)->whereDate('attendance_date', $today->toDateString())->count(),
                    'pending_journals' => Journal::query()->whereIn('placement_id', $ids)->where('status', 'submitted')->count(),
                    'pending_leaves' => LeaveRequest::query()->whereIn('placement_id', $ids)->where('status', 'submitted')->count(),
                ];
                Notify::user($teacher, 'digest.daily', "Ringkasan hari ini: {$vars['pending_attendance']} belum presensi, {$vars['pending_journals']} jurnal menunggu, {$vars['pending_leaves']} izin menunggu", null, '/teacher');
                WhatsAppGateway::send($school, $teacher, $teacher->phone, 'teacher_daily_digest', $vars);
            }
            foreach ($placements as $p) {
                $onLeave = Attendance::query()->where('placement_id', $p->id)->whereDate('attendance_date', $today->toDateString())->whereIn('status', ['sick', 'permission'])->exists();
                $hasJournal = Journal::query()->where('placement_id', $p->id)->whereDate('journal_date', $today->toDateString())->exists();
                if (! $onLeave && ! $hasJournal) {
                    Notify::user($p->student->user, 'reminder.journal', 'Jangan lupa menulis jurnal kegiatan hari ini', null, '/student/journals');
                    WhatsAppGateway::send($school, $p->student->user, $p->student->user->phone, 'student_journal_reminder');
                }
            }
        });
    }
    $this->info('Ringkasan harian selesai untuk '.$schools->count().' sekolah.');
})->purpose('Kirim ringkasan harian guru dan pengingat jurnal siswa');

// S08/10.5: hapus swafoto presensi yang melewati masa simpan sekolah.
Artisan::command('jurnalin:retensi', function (): void {
    $total = 0;
    foreach (TenantContext::runAsSuperAdmin(fn () => School::query()->get()) as $school) {
        $total += TenantContext::runInSchoolScope($school->id, function () use ($school): int {
            $cutoff = now()->subDays($school->selfie_retention_days)->toDateString();
            $count = 0;
            Attendance::query()->whereDate('attendance_date', '<', $cutoff)->where(fn ($q) => $q->whereNotNull('check_in_photo_path')->orWhereNotNull('check_out_photo_path'))
                ->whereNull('leave_request_id')->whereNotIn('status', ['sick', 'permission'])
                ->each(function (Attendance $a) use (&$count): void {
                    MediaStorage::discard($a->check_in_photo_path);
                    MediaStorage::discard($a->check_out_photo_path);
                    $a->forceFill(['check_in_photo_path' => null, 'check_out_photo_path' => null])->saveQuietly();
                    $count++;
                });

            return $count;
        });
    }
    $this->info("Swafoto dihapus dari {$total} presensi lama.");
})->purpose('Hapus swafoto presensi sesuai masa simpan sekolah');

// H12: pengingat dokumen kerja sama yang akan habis untuk admin sekolah.
Artisan::command('jurnalin:pengingat-hubin', function (): void {
    foreach (TenantContext::runAsSuperAdmin(fn () => School::query()->where('status', 'active')->get()) as $school) {
        if (! $school->hasFeature('hubin')) {
            continue;
        }
        TenantContext::runInSchoolScope($school->id, function () use ($school): void {
            $admins = User::query()->where('role', UserRole::SchoolAdmin)->where('is_active', true)->get();
            foreach (PartnerAgreement::query()->with('company')->whereNotNull('expires_on')->where('status', 'active')->get() as $a) {
                $daysLeft = (int) now($school->timezone)->startOfDay()->diffInDays($a->expires_on, false);
                if (in_array($daysLeft, [$a->reminder_days, 30, 7, 0], true)) {
                    Notify::users($admins, 'hubin.agreement', strtoupper($a->kind)." {$a->company->name} berakhir dalam {$daysLeft} hari", null, '/admin/mitra/'.$a->company_id.'#dokumen');
                }
                if ($daysLeft < 0) {
                    $a->update(['status' => 'expired']);
                }
            }
        });
    }
    $this->info('Pengingat Hubin selesai.');
})->purpose('Pengingat MoU/PKS yang akan berakhir');

Schedule::command('jurnalin:ringkasan-harian')->dailyAt('15:00')->timezone('Asia/Jakarta');
Schedule::command('jurnalin:pengingat-hubin')->dailyAt('07:00')->timezone('Asia/Jakarta');
Schedule::command('jurnalin:retensi')->weeklyOn(0, '02:00')->timezone('Asia/Jakarta');
