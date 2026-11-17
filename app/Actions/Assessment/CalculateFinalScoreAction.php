<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\AttendanceStatus;
use App\Models\Assessment;
use App\Models\AssessmentVersion;
use App\Models\Placement;
use App\Models\Teacher;
use DomainException;

/**
 * Nilai akhir PKL (P39). Nilai tidak pernah disimpulkan dari jumlah jurnal. Finalisasi membuat snapshot
 * terkunci (hash SHA-256); perubahan setelah final hanya lewat revisi berversi dengan alasan (openRevision).
 */
final class CalculateFinalScoreAction
{
    /**
     * @param  array<string, float|int>  $industryScores
     * @param  array<string, float|int>  $schoolScores
     */
    public function handle(
        Placement $placement,
        Teacher $teacher,
        array $industryScores,
        array $schoolScores,
        ?string $industryFormPhotoPath = null,
        ?string $signedByName = null,
        ?string $industryNotes = null,
        ?string $teacherNotes = null,
        ?string $certificateNumber = null,
        bool $finalize = false,
        string $industrySource = 'manual_form',
    ): Assessment {
        if ($placement->teacher_id !== $teacher->id) {
            throw new DomainException('Anda hanya dapat menilai siswa bimbingan Anda sendiri.');
        }

        $existing = Assessment::query()->where('placement_id', $placement->id)->first();
        if ($existing?->finalized_at !== null) {
            throw new DomainException('Nilai sudah difinalisasi dan terkunci. Buka revisi berversi dengan alasan bila perlu koreksi.');
        }

        $resolvedPhotoPath = $industryFormPhotoPath ?? $existing?->industry_form_photo_path;

        if ($finalize && $industrySource === 'manual_form' && $placement->school->require_industry_form_photo && empty($resolvedPhotoPath)) {
            throw new DomainException('Foto bukti formulir penilaian kertas dari DUDI wajib diunggah sebelum finalisasi nilai.');
        }

        $period = $placement->pklPeriod;
        $rubric = $period->rubric();

        $industryAverage = $rubric->calculateGroupAverage($industryScores, $rubric->industryAspects);
        $schoolAverage = $rubric->calculateGroupAverage($schoolScores, $rubric->schoolAspects);

        $attendedDays = $placement->attendances()
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->count();

        $requiredDays = max(1, (int) $period->min_required_days);
        $attendanceScore = round(min(100.0, ($attendedDays / $requiredDays) * 100.0), 2);

        $finalScore = $rubric->calculateFinalScore($industryAverage, $schoolAverage, $attendanceScore);
        $predicate = $rubric->resolvePredicate($finalScore);
        $description = $rubric->describeAchievement($placement->student->user->name, $industryScores, $schoolScores);

        $assessment = Assessment::query()->updateOrCreate(
            [
                'school_id' => $placement->school_id,
                'placement_id' => $placement->id,
            ],
            [
                'student_id' => $placement->student_id,
                'teacher_id' => $teacher->id,
                'industry_scores' => [
                    'scores' => $industryScores,
                    'weighted_average' => $industryAverage,
                    'signed_by_name' => $signedByName ?? $placement->industry_mentor_name,
                ],
                'school_scores' => [
                    'scores' => $schoolScores,
                    'weighted_average' => $schoolAverage,
                ],
                'industry_form_photo_path' => $resolvedPhotoPath,
                'industry_source' => $industrySource,
                'attendance_score' => $attendanceScore,
                'final_score' => $finalScore,
                'predicate' => $predicate,
                'achievement_description' => $description,
                'industry_notes' => $industryNotes,
                'teacher_notes' => $teacherNotes,
                'certificate_number' => $certificateNumber,
                'finalized_at' => $finalize ? now() : null,
            ]
        );

        $assessment->refresh();
        if ($finalize) {
            $this->lockSnapshot($assessment, $rubric->industryAspects, $rubric->schoolAspects, $period->assessment_template);
        }

        return $assessment->refresh();
    }

    /** Membuka nilai final menjadi versi baru. Snapshot versi lama tetap tersimpan di assessment_versions. */
    public function openRevision(Assessment $assessment, string $reason): Assessment
    {
        $reason = trim($reason);
        if ($assessment->finalized_at === null) {
            throw new DomainException('Nilai belum final, langsung ubah drafnya.');
        }
        if (mb_strlen($reason) < 10) {
            throw new DomainException('Tulis alasan revisi minimal 10 karakter.');
        }

        $assessment->update([
            'finalized_at' => null,
            'version' => $assessment->version + 1,
            'revision_reason' => $reason,
        ]);

        return $assessment->refresh();
    }

    private function lockSnapshot(Assessment $assessment, array $industryAspects, array $schoolAspects, ?array $template): void
    {
        $snapshot = [
            'version' => $assessment->version,
            'rubric' => $template,
            'industry' => $assessment->industry_scores,
            'school' => $assessment->school_scores,
            'industry_source' => $assessment->industry_source,
            'attendance_score' => $assessment->attendance_score,
            'final_score' => $assessment->final_score,
            'predicate' => $assessment->predicate,
            'description' => $assessment->achievement_description,
            'finalized_at' => $assessment->finalized_at?->toIso8601String(),
            'aspects' => ['industry' => $industryAspects, 'school' => $schoolAspects],
        ];
        $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE));

        $assessment->forceFill(['snapshot' => $snapshot, 'snapshot_hash' => $hash])->save();
        AssessmentVersion::query()->create([
            'school_id' => $assessment->school_id,
            'assessment_id' => $assessment->id,
            'version' => $assessment->version,
            'snapshot' => $snapshot,
            'snapshot_hash' => $hash,
            'reason' => $assessment->version > 1 ? $assessment->revision_reason : 'Finalisasi pertama',
            'actor_user_id' => auth()->id(),
        ]);
    }
}
