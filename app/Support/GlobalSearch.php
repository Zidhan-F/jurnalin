<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\JournalStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pencarian panel per sekolah (tenant scope dari BelongsToSchool). Guru hanya melihat siswa dan
 * jurnal dari penempatan bimbingannya; daftar guru hanya untuk admin sekolah.
 */
final class GlobalSearch
{
    public const MIN_LENGTH = 2;

    /** @return array{students: Collection, teachers: Collection, companies: Collection, journals: Collection} */
    public static function run(User $user, string $query, int $limitPerType = 5): array
    {
        $empty = ['students' => collect(), 'teachers' => collect(), 'companies' => collect(), 'journals' => collect()];
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return $empty;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
        $teacherId = $user->role === UserRole::Teacher ? $user->teacher?->id : null;
        if ($user->role === UserRole::Teacher && $teacherId === null) {
            return $empty;
        }
        $ownPlacements = fn (Builder $q) => $teacherId ? $q->where('teacher_id', $teacherId) : $q;

        return [
            'students' => Student::query()->with(['user', 'placements' => fn ($q) => $q->when($teacherId, fn ($p) => $p->where('teacher_id', $teacherId))->latest()])
                ->where(fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'ilike', $like))->orWhere('nisn', 'ilike', $like))
                ->when($teacherId, fn ($q) => $q->whereHas('placements', $ownPlacements))
                ->limit($limitPerType)->get(),
            'teachers' => $user->role === UserRole::SchoolAdmin
                ? Teacher::query()->with('user')->whereHas('user', fn ($u) => $u->where('name', 'ilike', $like))->limit($limitPerType)->get()
                : collect(),
            'companies' => Company::query()->where('name', 'ilike', $like)
                ->when($teacherId, fn ($q) => $q->whereHas('placements', $ownPlacements)->with(['placements' => fn ($p) => $p->where('teacher_id', $teacherId)]))
                ->orderBy('name')->limit($limitPerType)->get(),
            'journals' => Journal::query()->with(['student.user', 'placement'])
                ->where('activity_title', 'ilike', $like)
                ->where('status', '!=', JournalStatus::Draft)
                ->when($teacherId, fn ($q) => $q->whereHas('placement', $ownPlacements))
                ->latest('journal_date')->limit($limitPerType)->get(),
        ];
    }
}
