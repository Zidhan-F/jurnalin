<?php

declare(strict_types=1);

namespace App\Actions\Journal;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\Teacher;
use DomainException;

final class ValidateJournalAction
{
    public function handle(
        Journal $journal,
        Teacher $teacher,
        JournalStatus $decision,
        ?string $feedback = null,
    ): Journal {
        if ($journal->placement->teacher_id !== $teacher->id) {
            throw new DomainException('Anda hanya dapat memvalidasi jurnal siswa bimbingan Anda sendiri.');
        }

        if (! $journal->status->canTransitionTo($decision)) {
            throw new DomainException("Jurnal berstatus {$journal->status->label()} tidak dapat diubah ke {$decision->label()}.");
        }

        $cleanFeedback = trim((string) $feedback);
        if ($decision === JournalStatus::RevisionNeeded && $cleanFeedback === '') {
            throw new DomainException('Catatan perbaikan wajib diisi saat meminta revisi jurnal.');
        }

        $journal->update([
            'status' => $decision,
            'teacher_feedback' => $cleanFeedback !== '' ? $cleanFeedback : $journal->teacher_feedback,
            'validated_by_teacher_id' => $teacher->id,
            'validated_at' => now(),
        ]);

        $journal->refresh();
        $journal->recordVersion($decision === JournalStatus::Approved ? 'Disetujui guru' : 'Revisi diminta guru');
        \App\Support\Notify::user(
            $journal->student->user,
            'journal.'.$decision->value,
            'Jurnal '.$journal->journal_date->format('d/m/Y').($decision === JournalStatus::Approved ? ' disetujui' : ' perlu revisi'),
            $decision === JournalStatus::RevisionNeeded ? $cleanFeedback : null,
            $decision === JournalStatus::RevisionNeeded ? '/student/journals?edit='.$journal->id : '/student/journals#jurnal-'.$journal->id,
        );

        return $journal;
    }
}
