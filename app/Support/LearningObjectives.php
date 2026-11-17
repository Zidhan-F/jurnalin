<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Journal;
use App\Models\LearningObjective;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Katalog TP sekolah (P20). Template bawaan disalin ke sekolah saat pertama dipakai, lalu bebas diubah.
 * Penautan jurnal ke TP oleh siswa berstatus "diklaim"; status "terverifikasi" hanya lewat pemeriksaan manusia.
 */
final class LearningObjectives
{
    /** @return Collection<int, LearningObjective> */
    public static function forSchool(School $school, string $scope = 'pkl'): Collection
    {
        $query = LearningObjective::query()->where('school_id', $school->id)->where('scope', $scope);
        if (! (clone $query)->exists()) {
            $defaults = $scope === 'teacher' ? config('pkl.default_teacher_objectives') : config('pkl.default_objectives');
            foreach ($defaults as $i => $o) {
                LearningObjective::query()->create(['school_id' => $school->id, 'scope' => $scope, 'code' => $o['code'], 'label' => $o['label'], 'sort' => $i]);
            }
        }

        return $query->where('is_active', true)->orderBy('sort')->orderBy('code')->get();
    }

    /** @param list<string> $objectiveIds */
    public static function claim(Journal $journal, array $objectiveIds): void
    {
        $valid = LearningObjective::query()->whereIn('id', $objectiveIds)->where('scope', 'pkl')->pluck('id')->all();
        $existing = DB::table('journal_objectives')->where('journal_id', $journal->id)->pluck('status', 'learning_objective_id');

        DB::table('journal_objectives')->where('journal_id', $journal->id)->where('status', 'claimed')->whereNotIn('learning_objective_id', $valid)->delete();
        foreach ($valid as $id) {
            if (! $existing->has($id)) {
                DB::table('journal_objectives')->insert(['school_id' => $journal->school_id, 'journal_id' => $journal->id, 'learning_objective_id' => $id, 'status' => 'claimed']);
            }
        }
    }

    /**
     * Ringkasan capaian per TP untuk satu penempatan: jumlah bukti diklaim dan terverifikasi.
     *
     * @return Collection<int, array{objective: LearningObjective, claimed: int, verified: int}>
     */
    public static function progress(School $school, string $placementId): Collection
    {
        $counts = DB::table('journal_objectives as jo')->join('journals as j', 'j.id', '=', 'jo.journal_id')
            ->where('j.placement_id', $placementId)
            ->selectRaw("jo.learning_objective_id, count(*) as claimed, sum(case when jo.status = 'verified' then 1 else 0 end) as verified")
            ->groupBy('jo.learning_objective_id')->get()->keyBy('learning_objective_id');

        return self::forSchool($school)->map(fn ($o) => [
            'objective' => $o,
            'claimed' => (int) ($counts[$o->id]->claimed ?? 0),
            'verified' => (int) ($counts[$o->id]->verified ?? 0),
        ]);
    }
}
