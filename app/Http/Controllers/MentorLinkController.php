<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Guru pembimbing atau admin sekolah membuat / mencabut tautan tanda tangan DUDI untuk satu penempatan. */
final class MentorLinkController extends Controller
{
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        $this->authorizeFor($request, $placement);

        $token = Str::random(48);
        $placement->forceFill([
            'mentor_link_hash' => hash('sha256', $token),
            'mentor_link_token' => $token,
            'mentor_link_expires_at' => $placement->pklPeriod->end_date->copy()->endOfDay()->addDays(config('jurnalin.mentor_link_grace_days')),
        ])->save();
        AuditLog::record('mentor_link.created', $placement, ['student' => $placement->student->user->name]);

        return back()->with('status', "Tautan tanda tangan untuk pembimbing {$placement->student->user->name} siap dibagikan. Tautan lama, bila ada, tidak berlaku lagi.");
    }

    public function destroy(Request $request, Placement $placement): RedirectResponse
    {
        $this->authorizeFor($request, $placement);

        $placement->forceFill(['mentor_link_hash' => null, 'mentor_link_token' => null, 'mentor_link_expires_at' => null])->save();
        AuditLog::record('mentor_link.revoked', $placement, ['student' => $placement->student->user->name]);

        return back()->with('status', 'Tautan tanda tangan dicabut. Pembimbing DUDI tidak bisa membukanya lagi.');
    }

    private function authorizeFor(Request $request, Placement $placement): void
    {
        $user = $request->user();
        $allowed = $user->role === UserRole::SchoolAdmin
            || ($user->role === UserRole::Teacher && $user->teacher?->id === $placement->teacher_id);

        abort_unless($allowed, 403);
    }
}
