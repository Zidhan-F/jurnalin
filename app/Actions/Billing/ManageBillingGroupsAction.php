<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\SchoolPlan;
use App\Models\AuditLog;
use App\Models\BillingGroup;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\School;
use App\Models\SchoolPayment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\GroupAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageBillingGroupsAction
{
    public function available(PklPeriod $period): Builder
    {
        return Student::whereIn('id', $period->placements()->whereIn('status', ['scheduled', 'active'])->select('student_id'))
            ->whereNotIn('id', DB::table('billing_group_members')->where('pkl_period_id', $period->id)->select('student_id'));
    }

    /** Siswa tersedia per guru pembimbing pada periode ini (satu guru biasanya membimbing siswa dari beberapa kelas). */
    public function availableByTeacher(PklPeriod $period): \Illuminate\Support\Collection
    {
        return $period->placements()->whereIn('status', ['scheduled', 'active'])
            ->whereIn('student_id', $this->available($period)->select('id'))
            ->with('teacher.user')->get()->groupBy('teacher_id')
            ->map(fn ($rows) => ['id' => $rows->first()->teacher_id, 'name' => $rows->first()->teacher?->user?->name ?? 'Guru', 'students' => $rows->pluck('student_id')->unique()->count()])
            ->sortBy('name')->values();
    }

    public function syncPeriodGroup(School $school, PklPeriod $period): void
    {
        DB::transaction(function () use ($school, $period): void {
            $availableIds = $this->available($period)->pluck('id');
            if ($availableIds->isEmpty()) {
                return;
            }

            $existing = BillingGroup::where('pkl_period_id', $period->id)->orderBy('created_at')->get();
            if ($existing->isEmpty()) {
                $group = BillingGroup::create(['name' => $period->name, 'pkl_period_id' => $period->id]);
                foreach ($availableIds->chunk(500) as $chunk) {
                    $group->members()->attach($chunk->all(), ['school_id' => $school->id, 'pkl_period_id' => $period->id]);
                }
                if ($school->plan() === SchoolPlan::Free && ! $school->require_group_payment) {
                    $group->grant($period->start_date->toImmutable(), $period->end_date->toImmutable());
                }

                return;
            }

            $primary = $existing->firstWhere('name', $period->name);
            if ($primary && ! $primary->starts_on && ! $primary->payments()->whereIn('status', ['pending', 'verified'])->exists()) {
                foreach ($availableIds->chunk(500) as $chunk) {
                    $primary->members()->attach($chunk->all(), ['school_id' => $school->id, 'pkl_period_id' => $period->id]);
                }
            }
        });
    }

    public function create(User $admin, PklPeriod $period, string $name, ?string $class, ?string $teacherId = null): BillingGroup
    {
        return DB::transaction(function () use ($admin, $period, $name, $class, $teacherId) {
            $school = School::lockForUpdate()->findOrFail($admin->school_id);
            BillingGroup::where('pkl_period_id', $period->id)
                ->where('name', $period->name)
                ->whereNull('starts_on')
                ->whereDoesntHave('payments')
                ->get()
                ->each(function (BillingGroup $auto): void {
                    $auto->members()->detach();
                    $auto->delete();
                });
            $students = $this->available($period)
                ->when($class !== null, fn ($q) => $q->where('class_name', $class))
                ->when($teacherId !== null, fn ($q) => $q->whereIn('id', $period->placements()->whereIn('status', ['scheduled', 'active'])->where('teacher_id', $teacherId)->select('student_id')))
                ->pluck('id');
            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['class_name' => 'Tidak ada siswa dengan penempatan yang tersedia untuk grup ini.']);
            }
            if (BillingGroup::where('pkl_period_id', $period->id)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => 'Nama grup sudah digunakan pada periode ini.']);
            }
            $group = BillingGroup::create(['name' => $name, 'pkl_period_id' => $period->id]);
            foreach ($students->chunk(500) as $chunk) {
                $group->members()->attach($chunk->all(), ['school_id' => $school->id, 'pkl_period_id' => $period->id]);
            }
            if ($school->plan() === SchoolPlan::Free && ! $school->require_group_payment) {
                $group->grant($period->start_date->toImmutable(), $period->end_date->toImmutable());
            }
            AuditLog::record('group.created', $group, ['students' => $students->count()]);

            return $group;
        });
    }

    public function stop(User $admin, ?BillingGroup $target): void
    {
        DB::transaction(function () use ($admin, $target) {
            $school = School::lockForUpdate()->findOrFail($admin->school_id);
            $groups = BillingGroup::when($target, fn ($q) => $q->whereKey($target->id))->lockForUpdate()->get();
            $ids = $groups->modelKeys();
            $affected = GroupAccess::roster($school)->when($target, fn ($q) => $q->where('g.id', $target->id))->get();
            if (! $target) {
                $school->forceFill(['require_group_payment' => true])->save();
            }
            BillingGroup::whereKey($ids)->update(['stopped' => true]);
            SchoolPayment::whereIn('billing_group_id', $ids)->where('status', 'pending')->update(['status' => 'cancelled',
                'review_note' => 'Admin sekolah menonaktifkan akses sebelum verifikasi.']);
            Placement::whereKey($affected->pluck('id'))->update(['mentor_link_hash' => null, 'mentor_link_token' => null, 'mentor_link_expires_at' => null]);

            $other = collect();
            if ($target) {
                $today = now($school->timezone)->toDateString();
                $other = GroupAccess::roster($school)->where(fn ($q) => $q->whereNull('g.id')->orWhere('g.id', '!=', $target->id))
                    ->get()->filter(fn ($row) => GroupAccess::phase($row, $school, $today) !== 'off');
            }
            $users = User::where('school_id', $school->id)->whereIn('role', ['student', 'teacher']);
            if ($target) {
                $students = Student::whereKey($affected->pluck('student_id')->diff($other->pluck('student_id')))->select('user_id');
                $teachers = Teacher::whereKey($affected->pluck('teacher_id')->diff($other->pluck('teacher_id')))->select('user_id');
                $users->where(fn ($q) => $q->whereIn('id', $students)->orWhereIn('id', $teachers));
            }
            $revoked = $users->pluck('id');
            User::whereIn('id', $revoked)->increment('access_version', 1, ['remember_token' => null]);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table'))->whereIn('user_id', $revoked)->delete();
            }
            AuditLog::record('group.stopped', $target ?? $school, ['groups' => count($ids), 'accounts' => $revoked->count()]);
        });
    }
}
