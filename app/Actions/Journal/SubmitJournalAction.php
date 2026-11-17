<?php

declare(strict_types=1);

namespace App\Actions\Journal;

use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\Placement;
use App\Support\MentorSignoffPeriods;
use DomainException;

final class SubmitJournalAction
{
    /**
     * @param  array{journal_date:string, work_unit:string, activity_title:string, activity_description:string, tools_materials?:?string, learning_outcome?:?string, duration_minutes?:int, photo_path?:?string}  $data
     */
    public function handle(
        Placement $placement,
        array $data,
        JournalStatus $targetStatus = JournalStatus::Submitted,
        ?Journal $existingJournal = null,
    ): Journal {
        $finishingJournal = $placement->status === PlacementStatus::Completed
            && in_array($placement->id, request()->attributes->get('grace_placements', []), true);
        if ($placement->status !== PlacementStatus::Active && ! $finishingJournal) {
            throw new DomainException('Jurnal hanya dapat diisi pada penempatan PKL yang berstatus aktif.');
        }

        $signed = MentorSignoffPeriods::signoffCovering($placement, (string) $data['journal_date']);
        $revising = $existingJournal?->status === JournalStatus::RevisionNeeded;
        if ($signed !== null && ! $revising) {
            throw new DomainException('Jurnal pada tanggal ini sudah ditandatangani pembimbing DUDI dan tidak bisa diubah. Minta guru pembimbing bila perlu koreksi.');
        }

        if ($existingJournal !== null) {
            if (! $existingJournal->status->isEditableByStudent()) {
                throw new DomainException('Jurnal yang sudah disetujui atau terkunci tidak dapat diubah lagi.');
            }

            if (! $existingJournal->status->canTransitionTo($targetStatus)) {
                throw new DomainException('Perubahan status jurnal tidak diizinkan.');
            }

            $photos = $this->mergePhotos($existingJournal->photos(), $data);
            $existingJournal->update([
                'journal_date' => $data['journal_date'],
                'work_unit' => $data['work_unit'],
                'activity_title' => $data['activity_title'],
                'activity_description' => $data['activity_description'],
                'tools_materials' => $data['tools_materials'] ?? null,
                'learning_outcome' => $data['learning_outcome'] ?? null,
                'duration_minutes' => (int) ($data['duration_minutes'] ?? 480),
                'photo_path' => $photos[0] ?? null,
                'photo_paths' => array_slice($photos, 1) ?: null,
                'obstacles' => $data['obstacles'] ?? null,
                'template_key' => $data['template_key'] ?? $existingJournal->template_key,
                'confirmed_done' => (bool) ($data['confirmed_done'] ?? $existingJournal->confirmed_done),
                'version' => $targetStatus === JournalStatus::Submitted && $existingJournal->status === JournalStatus::RevisionNeeded ? $existingJournal->version + 1 : $existingJournal->version,
                'status' => $targetStatus,
            ]);
            $existingJournal->refresh();
            if ($targetStatus === JournalStatus::Submitted) {
                $existingJournal->recordVersion('Diajukan ulang oleh siswa');
            }

            return $existingJournal;
        }

        $linkedAttendance = Attendance::query()
            ->where('placement_id', $placement->id)
            ->whereDate('attendance_date', $data['journal_date'])
            ->first();

        $photos = $this->mergePhotos([], $data);
        $journal = Journal::query()->create([
            'school_id' => $placement->school_id,
            'placement_id' => $placement->id,
            'student_id' => $placement->student_id,
            'attendance_id' => $linkedAttendance?->id,
            'journal_date' => $data['journal_date'],
            'work_unit' => $data['work_unit'],
            'activity_title' => $data['activity_title'],
            'activity_description' => $data['activity_description'],
            'tools_materials' => $data['tools_materials'] ?? null,
            'learning_outcome' => $data['learning_outcome'] ?? null,
            'photo_path' => $photos[0] ?? null,
            'photo_paths' => array_slice($photos, 1) ?: null,
            'obstacles' => $data['obstacles'] ?? null,
            'template_key' => $data['template_key'] ?? null,
            'confirmed_done' => (bool) ($data['confirmed_done'] ?? false),
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 480),
            'status' => $targetStatus,
        ]);
        if ($targetStatus === JournalStatus::Submitted) {
            $journal->recordVersion('Diajukan siswa');
        }

        return $journal;
    }

    /**
     * Foto lama dipertahankan, foto baru ditambahkan, foto yang dihapus siswa dibuang.
     *
     * @param  list<string>  $current
     * @return list<string>
     */
    private function mergePhotos(array $current, array $data): array
    {
        $remove = $data['remove_photos'] ?? [];
        $kept = array_values(array_filter($current, fn ($p) => ! in_array($p, $remove, true)));
        $new = array_filter([$data['photo_path'] ?? null, ...($data['extra_photo_paths'] ?? [])]);

        return array_values(array_unique([...$kept, ...$new]));
    }
}
