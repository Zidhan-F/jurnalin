<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case RevisionNeeded = 'revision_needed';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Menunggu Validasi',
            self::RevisionNeeded => 'Perlu Revisi',
            self::Approved => 'Disetujui',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Draft, self::Submitted], true),
            self::Submitted => in_array($next, [self::Approved, self::RevisionNeeded], true),
            self::RevisionNeeded => in_array($next, [self::RevisionNeeded, self::Submitted], true),
            self::Approved => false,
        };
    }

    public function isEditableByStudent(): bool
    {
        return $this === self::Draft || $this === self::RevisionNeeded;
    }
}
