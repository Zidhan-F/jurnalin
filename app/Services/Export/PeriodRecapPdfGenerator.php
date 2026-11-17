<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\PklPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class PeriodRecapPdfGenerator
{
    public function __construct(
        private PklPeriod $period,
        private ?string $teacherId = null,
    ) {}

    public function download(): Response
    {
        $this->period->load('school');

        $placements = $this->period->placements()
            ->with(['student.user', 'company', 'teacher.user', 'assessment'])
            ->when($this->teacherId !== null, fn ($q) => $q->where('teacher_id', $this->teacherId))
            ->get()
            ->sortBy(fn ($p) => $p->student->class_name.'-'.$p->student->user->name);

        $pdf = Pdf::loadView('pdf.period-recap', [
            'period' => $this->period,
            'school' => $this->period->school,
            'placements' => $placements,
        ])->setPaper('a4', 'landscape');

        $slug = Str::slug($this->period->name);

        return $pdf->download("rekap-nilai-pkl-{$slug}.pdf");
    }
}
