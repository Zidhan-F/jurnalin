<?php

declare(strict_types=1);

namespace App\Actions\Placement;

use App\Enums\PlacementStatus;
use App\Enums\SchoolPlan;
use App\Models\Placement;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActivatePlacementAction
{
    public function handle(School $school, Placement $placement): void
    {
        DB::transaction(function () use ($school, $placement): void {
            $school = School::query()->lockForUpdate()->findOrFail($school->id);
            if ($placement->exists) {
                $placement = Placement::query()->lockForUpdate()->findOrFail($placement->id);
                if (! $placement->status->canTransitionTo(PlacementStatus::Active)) {
                    throw ValidationException::withMessages(['status' => 'Penempatan ini tidak dapat diaktifkan.']);
                }
            }

            if ($school->plan() === SchoolPlan::Free) {
                $used = $school->placements()->whereNotIn('status', [PlacementStatus::Scheduled, PlacementStatus::Offered, PlacementStatus::Confirmed]);
                if ((clone $used)->where('pkl_period_id', '!=', $placement->pkl_period_id)->exists()) {
                    throw ValidationException::withMessages(['pkl_period_id' => 'Paket Free mencakup satu periode penempatan. Pilih periode uji coba atau hubungi admin Jurnalin untuk mengganti paket.']);
                }
                if (! (clone $used)->where('student_id', $placement->student_id)->exists()
                    && $used->distinct()->count('student_id') >= $school->studentLimit()) {
                    throw ValidationException::withMessages(['student_id' => 'Kuota siswa untuk periode uji coba sudah tercapai.']);
                }
            }

            $active = Placement::query()->where('status', PlacementStatus::Active);
            if ((clone $active)->where('pkl_period_id', $placement->pkl_period_id)->where('student_id', $placement->student_id)->exists()) {
                throw ValidationException::withMessages(['student_id' => 'Siswa ini sudah memiliki penempatan aktif pada periode tersebut.']);
            }
            if ($active->where('teacher_id', $placement->teacher_id)->count() >= $placement->teacher->max_mentees) {
                throw ValidationException::withMessages(['teacher_id' => 'Kuota siswa aktif guru pembimbing sudah tercapai.']);
            }
            $placement->status = PlacementStatus::Active;
            $placement->save();
        });
    }
}
