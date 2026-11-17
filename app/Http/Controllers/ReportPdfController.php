<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Services\Export\PeriodRecapPdfGenerator;
use App\Services\Export\StudentLogbookPdfGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReportPdfController extends Controller
{
    public function studentLogbook(Request $request, Placement $placement): Response
    {
        $user = $request->user();

        if ($user->role === UserRole::Student) {
            abort_unless($user->student?->id === $placement->student_id, 403);
        } elseif ($user->role === UserRole::Teacher) {
            abort_unless($user->teacher?->id === $placement->teacher_id, 403);
        } elseif ($user->role !== UserRole::SchoolAdmin) {
            abort(403);
        }

        return (new StudentLogbookPdfGenerator($placement))->download();
    }

    public function periodRecap(Request $request, PklPeriod $period): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole(UserRole::SchoolAdmin, UserRole::Teacher), 403);

        $teacherId = $user->role === UserRole::Teacher ? $user->teacher?->id : null;

        return (new PeriodRecapPdfGenerator($period, $teacherId))->download();
    }
}
