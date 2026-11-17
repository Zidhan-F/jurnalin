<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\JournalStatus;
use App\Models\Placement;
use App\Support\MediaStorage;
use App\Support\MentorSignoffPeriods;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class StudentLogbookPdfGenerator
{
    public function __construct(
        private Placement $placement,
    ) {}

    public function download(): Response
    {
        $pdf = $this->document();
        $slug = Str::slug($this->placement->student->user->name);

        return $pdf->download("buku-jurnal-pkl-{$slug}.pdf");
    }

    public function save(string $path): void
    {
        $this->document()->save($path);
    }

    private function document(): \Barryvdh\DomPDF\PDF
    {
        $this->placement->load([
            'school',
            'pklPeriod',
            'student.user',
            'company',
            'teacher.user',
            'attendances' => fn ($q) => $q->orderBy('attendance_date'),
            'journals' => fn ($q) => $q->where('status', JournalStatus::Approved)->orderBy('journal_date'),
            'assessment',
            'mentorSignoffs',
        ]);

        // DomPDF tidak bisa membuka /media (butuh sesi), jadi gambar tanda tangan disisipkan sebagai data URI.
        $signoffs = $this->placement->mentorSignoffs->sortBy('period_start')->map(function ($signoff) {
            $path = MediaStorage::absolutePath($signoff->signature_path);

            return [
                'signoff' => $signoff,
                'image' => $path ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null,
                'changed' => MentorSignoffPeriods::isChangedSinceSigning($signoff, $this->placement),
            ];
        })->values();

        return Pdf::loadView('pdf.student-logbook', [
            'placement' => $this->placement,
            'school' => $this->placement->school,
            'student' => $this->placement->student,
            'attendances' => $this->placement->attendances,
            'journals' => $this->placement->journals,
            'assessment' => $this->placement->assessment,
            'signoffs' => $signoffs,
        ])->setPaper('a4', 'portrait');
    }
}
