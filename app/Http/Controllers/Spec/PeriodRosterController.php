<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Actions\Placement\ActivatePlacementAction;
use App\Enums\PlacementStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PeriodClass;
use App\Models\Placement;
use App\Models\PlacementGroup;
use App\Models\PklPeriod;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kelas dan grup penempatan di dalam periode. Kelas = daftar siswa per kelas pada periode itu.
 * Grup = tempat PKL + guru pembimbing + jam kerja; menambah anggota membuat penempatan aktif,
 * mengubah guru atau jam kerja grup ikut mengubah semua penempatan anggotanya.
 */
final class PeriodRosterController extends Controller
{
    // ---------- Kelas ----------

    public function storeClass(Request $request, PklPeriod $period): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:64']]);
        $name = trim($data['name']);
        if (PeriodClass::query()->where('pkl_period_id', $period->id)->where('name', $name)->exists()) {
            return back()->withErrors(['name' => "Kelas {$name} sudah ada di periode ini."]);
        }
        $students = Student::query()->where('class_name', $name)->get();
        $class = PeriodClass::query()->create([
            'school_id' => $period->school_id, 'pkl_period_id' => $period->id, 'name' => $name,
            'major' => $students->pluck('major')->filter()->countBy()->sortDesc()->keys()->first(),
        ]);
        [$added, $skipped] = $this->addToClass($class, $students->pluck('id')->all());
        AuditLog::record('period.class_created', $class, ['students' => $added]);

        return $this->backTo($period, 'kelas')->with('status', "Kelas {$name} ditambahkan dengan {$added} siswa.".$this->skippedNote($skipped));
    }

    public function addClassMembers(Request $request, PklPeriod $period, PeriodClass $periodClass): RedirectResponse
    {
        $this->ensureSamePeriod($period, $periodClass->pkl_period_id);
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid']]);
        [$added, $skipped] = $this->addToClass($periodClass, $data['student_ids']);

        return $this->backTo($period, 'kelas')->with('status', "{$added} siswa ditambahkan ke {$periodClass->name}.".$this->skippedNote($skipped));
    }

    public function removeClassMember(PklPeriod $period, PeriodClass $periodClass, Student $student): RedirectResponse
    {
        $this->ensureSamePeriod($period, $periodClass->pkl_period_id);
        DB::table('period_class_members')->where('period_class_id', $periodClass->id)->where('student_id', $student->id)->delete();

        return $this->backTo($period, 'kelas')->with('status', "{$student->user->name} dikeluarkan dari {$periodClass->name}.");
    }

    public function destroyClass(PklPeriod $period, PeriodClass $periodClass): RedirectResponse
    {
        $this->ensureSamePeriod($period, $periodClass->pkl_period_id);
        AuditLog::record('period.class_deleted', $periodClass, ['name' => $periodClass->name]);
        $periodClass->delete();

        return $this->backTo($period, 'kelas')->with('status', "Kelas {$periodClass->name} dihapus dari periode. Data siswa dan penempatan tidak berubah.");
    }

    // ---------- Grup penempatan ----------

    public function storeGroup(Request $request, PklPeriod $period, ActivatePlacementAction $activate): RedirectResponse
    {
        $data = $this->validateGroup($request, $period, withCompany: true);
        $group = PlacementGroup::query()->create([
            'school_id' => $period->school_id, 'pkl_period_id' => $period->id, 'name' => trim($data['name']),
            'company_id' => $data['company_id'], 'teacher_id' => $data['teacher_id'], 'department_divisi' => $data['department_divisi'] ?? null,
            'work_start_time' => $data['work_start_time'].':00', 'work_end_time' => $data['work_end_time'].':00',
        ]);
        AuditLog::record('period.group_created', $group, ['company' => $group->company_id, 'teacher' => $group->teacher_id]);
        [$ok, $errors] = $this->placeInGroup($request, $group, $data['student_ids'] ?? [], $activate);

        return $this->backTo($period, 'grup')->with('status', "Grup {$group->name} dibuat dengan {$ok} siswa.")->with('import_errors', $errors);
    }

    public function addGroupMembers(Request $request, PklPeriod $period, PlacementGroup $placementGroup, ActivatePlacementAction $activate): RedirectResponse
    {
        $this->ensureSamePeriod($period, $placementGroup->pkl_period_id);
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid']]);
        [$ok, $errors] = $this->placeInGroup($request, $placementGroup, $data['student_ids'], $activate);

        return $this->backTo($period, 'grup')->with('status', "{$ok} siswa ditambahkan ke {$placementGroup->name}.")->with('import_errors', $errors);
    }

    public function updateGroup(Request $request, PklPeriod $period, PlacementGroup $placementGroup): RedirectResponse
    {
        $this->ensureSamePeriod($period, $placementGroup->pkl_period_id);
        $data = $this->validateGroup($request, $period, withCompany: false, ignore: $placementGroup);
        $teacher = Teacher::query()->with('user')->findOrFail($data['teacher_id']);
        $members = Placement::query()->where('placement_group_id', $placementGroup->id)->where('status', PlacementStatus::Active)->get();

        $moving = $members->where('teacher_id', '!=', $teacher->id)->count();
        if ($moving > 0) {
            $load = Placement::query()->where('teacher_id', $teacher->id)->where('status', PlacementStatus::Active)->count();
            if ($load + $moving > $teacher->max_mentees) {
                return back()->withErrors(['teacher_id' => "Beban {$teacher->user->name} akan menjadi ".($load + $moving)." siswa, melebihi batas {$teacher->max_mentees}."]);
            }
        }

        DB::transaction(function () use ($placementGroup, $data, $members): void {
            $from = $placementGroup->only(['teacher_id', 'department_divisi', 'work_start_time', 'work_end_time']);
            $placementGroup->update([
                'name' => trim($data['name']), 'teacher_id' => $data['teacher_id'], 'department_divisi' => $data['department_divisi'] ?? null,
                'work_start_time' => $data['work_start_time'].':00', 'work_end_time' => $data['work_end_time'].':00',
            ]);
            // Keputusan review lama tetap atas nama guru sebelumnya (validated_by_teacher_id tidak diubah).
            foreach ($members as $p) {
                $p->update($placementGroup->only(['teacher_id', 'department_divisi', 'work_start_time', 'work_end_time']));
            }
            AuditLog::record('period.group_updated', $placementGroup, ['from' => $from, 'members' => $members->count()]);
        });

        return $this->backTo($period, 'grup')->with('status', "Grup {$placementGroup->name} diperbarui untuk {$members->count()} siswa.");
    }

    public function removeGroupMember(PklPeriod $period, PlacementGroup $placementGroup, Placement $placement): RedirectResponse
    {
        $this->ensureSamePeriod($period, $placementGroup->pkl_period_id);
        abort_unless($placement->placement_group_id === $placementGroup->id, 404);
        $placement->update(['placement_group_id' => null]);
        AuditLog::record('period.group_member_removed', $placement, ['group' => $placementGroup->id]);

        return $this->backTo($period, 'grup')->with('status', "{$placement->student->user->name} dikeluarkan dari grup. Penempatannya tetap aktif; ubah atau akhiri di menu Penempatan siswa bila perlu.");
    }

    public function destroyGroup(PklPeriod $period, PlacementGroup $placementGroup): RedirectResponse
    {
        $this->ensureSamePeriod($period, $placementGroup->pkl_period_id);
        AuditLog::record('period.group_deleted', $placementGroup, ['name' => $placementGroup->name]);
        $placementGroup->delete();

        return $this->backTo($period, 'grup')->with('status', "Grup {$placementGroup->name} dihapus. Penempatan anggotanya tetap aktif tanpa grup.");
    }

    // ---------- Bantuan ----------

    /** @return array<string, mixed> */
    private function validateGroup(Request $request, PklPeriod $period, bool $withCompany, ?PlacementGroup $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('placement_groups', 'name')->where('pkl_period_id', $period->id)->ignore($ignore?->id)],
            'company_id' => $withCompany ? ['required', 'uuid', Rule::exists('companies', 'id')->where('school_id', $period->school_id)] : ['prohibited'],
            'teacher_id' => ['required', 'uuid', Rule::exists('teachers', 'id')->where('school_id', $period->school_id)],
            'department_divisi' => ['nullable', 'string', 'max:100'],
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid'],
        ], [
            'name.unique' => 'Nama grup sudah dipakai di periode ini.',
            'work_end_time.after' => 'Jam pulang harus setelah jam masuk.',
        ]);
    }

    /**
     * Membuat penempatan aktif untuk tiap siswa. Siswa yang sudah ditempatkan di tempat PKL yang sama
     * tanpa grup langsung digabungkan; yang sudah di tempat lain dilaporkan, tidak dipindah diam-diam.
     *
     * @param  list<string>  $studentIds
     * @return array{0:int, 1:list<string>}
     */
    private function placeInGroup(Request $request, PlacementGroup $group, array $studentIds, ActivatePlacementAction $activate): array
    {
        $group->loadMissing(['company', 'teacher.user']);
        $ok = 0;
        $errors = [];
        foreach (Student::query()->with('user')->whereIn('id', $studentIds)->get() as $student) {
            $existing = Placement::query()->with('company')->where('pkl_period_id', $group->pkl_period_id)
                ->where('student_id', $student->id)->where('status', PlacementStatus::Active)->first();
            try {
                if ($existing !== null) {
                    if ($existing->placement_group_id === $group->id) {
                        continue;
                    }
                    if ($existing->company_id !== $group->company_id) {
                        throw ValidationException::withMessages(['student_id' => "sudah ditempatkan di {$existing->company->name}. Akhiri penempatan itu dulu bila ingin dipindah."]);
                    }
                    if ($existing->teacher_id !== $group->teacher_id) {
                        $load = Placement::query()->where('teacher_id', $group->teacher_id)->where('status', PlacementStatus::Active)->count();
                        if ($load >= $group->teacher->max_mentees) {
                            throw ValidationException::withMessages(['teacher_id' => 'Kuota siswa aktif guru pembimbing sudah tercapai.']);
                        }
                    }
                    $existing->update(['placement_group_id' => $group->id] + $group->only(['teacher_id', 'department_divisi', 'work_start_time', 'work_end_time']));
                } else {
                    $activate->handle($request->user()->school, new Placement([
                        'pkl_period_id' => $group->pkl_period_id, 'student_id' => $student->id, 'company_id' => $group->company_id,
                        'teacher_id' => $group->teacher_id, 'department_divisi' => $group->department_divisi,
                        'work_start_time' => $group->work_start_time, 'work_end_time' => $group->work_end_time,
                        'placement_group_id' => $group->id,
                    ]));
                }
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = $student->user->name.': '.collect($e->errors())->flatten()->first();
            }
        }
        if ($ok > 0) {
            AuditLog::record('period.group_members_added', $group, ['count' => $ok]);
        }

        return [$ok, $errors];
    }

    /**
     * @param  list<string>  $studentIds
     * @return array{0:int, 1:int}
     */
    private function addToClass(PeriodClass $class, array $studentIds): array
    {
        $taken = DB::table('period_class_members')->where('pkl_period_id', $class->pkl_period_id)->whereIn('student_id', $studentIds)->pluck('student_id')->all();
        $valid = Student::query()->whereIn('id', array_diff($studentIds, $taken))->pluck('id');
        DB::table('period_class_members')->insert($valid->map(fn ($id) => [
            'period_class_id' => $class->id, 'school_id' => $class->school_id, 'pkl_period_id' => $class->pkl_period_id, 'student_id' => $id,
        ])->all());

        return [$valid->count(), count($taken)];
    }

    private function skippedNote(int $skipped): string
    {
        return $skipped > 0 ? " {$skipped} siswa dilewati karena sudah terdaftar di kelas lain pada periode ini." : '';
    }

    private function ensureSamePeriod(PklPeriod $period, string $periodId): void
    {
        abort_unless($period->id === $periodId, 404);
    }

    private function backTo(PklPeriod $period, string $tab): RedirectResponse
    {
        return redirect()->to(route('admin.periods.show', ['period' => $period, 'tab' => $tab]));
    }
}
