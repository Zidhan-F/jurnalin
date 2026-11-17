<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SchoolStatus;
use App\Enums\SchoolPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Notifications\SchoolApproved;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SuperAdminController extends Controller
{
    public function index(): View
    {
        $schools = School::query()
            ->withCount(['students', 'teachers', 'placements'])
            ->with(['users' => fn ($q) => $q->where('role', UserRole::SchoolAdmin)->orderBy('name')])
            ->orderByRaw("CASE status WHEN 'pending_verification' THEN 0 WHEN 'active' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return view('super-admin.index', [
            'schools' => $schools,
            'pendingCount' => $schools->where('status', SchoolStatus::PendingVerification)->count(),
            'activeCount' => $schools->where('status', SchoolStatus::Active)->count(),
        ]);
    }

    public function approve(School $school): RedirectResponse
    {
        if (! $school->status->canTransitionTo(SchoolStatus::Active)) {
            return back()->withErrors(['school' => 'Status sekolah tidak dapat diaktifkan.']);
        }

        $school->update([
            'status' => SchoolStatus::Active,
            'verified_at' => $school->verified_at ?? now(),
        ]);
        AuditLog::record('school.approved', $school, ['npsn' => $school->npsn], $school->id);

        foreach ($school->users()->where('role', UserRole::SchoolAdmin)->get() as $admin) {
            try {
                $admin->notify(new SchoolApproved($school));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', "Sekolah {$school->name} (NPSN {$school->npsn}) telah diverifikasi dan diaktifkan.");
    }

    public function suspend(School $school): RedirectResponse
    {
        $nextStatus = $school->status === SchoolStatus::Suspended
            ? SchoolStatus::Active
            : SchoolStatus::Suspended;

        // Sekolah yang belum pernah diverifikasi hanya boleh aktif lewat tombol Verifikasi.
        if ($nextStatus === SchoolStatus::Active && $school->verified_at === null) {
            return back()->withErrors(['school' => "Sekolah {$school->name} belum pernah diverifikasi. Gunakan tombol Verifikasi."]);
        }

        $school->update([
            'status' => $nextStatus,
        ]);
        AuditLog::record('school.status_changed', $school, ['status' => $nextStatus->value], $school->id);

        return back()->with('status', "Status sekolah {$school->name} diubah menjadi {$nextStatus->label()}.");
    }

    public function resetSchoolAdminPassword(User $user): RedirectResponse
    {
        // Hanya admin sekolah: guru dan siswa direset oleh admin sekolahnya sendiri.
        abort_unless($user->role === UserRole::SchoolAdmin, 404);

        $user->forceFill(['password' => 'password123', 'must_change_password' => true, 'remember_token' => null])->save();
        AuditLog::record('user.password_reset_by_platform', $user, ['name' => $user->name], $user->school_id);

        return back()->with('status', "Kata sandi admin {$user->name} ({$user->email}) direset ke sandi awal (password123). Sampaikan langsung ke admin tersebut; ia wajib menggantinya saat login.");
    }

    public function updateQuota(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_tier' => ['required', Rule::enum(SchoolPlan::class)],
            'max_students_quota' => ['required', 'integer', 'min:10', 'max:'.($request->input('subscription_tier') === SchoolPlan::Free->value ? SchoolPlan::FREE_STUDENT_LIMIT : 5000)],
        ]);

        $school->update($validated);

        $tierLabel = $school->plan()->label();

        return back()->with('status', "Kuota paket {$school->name} diperbarui menjadi {$validated['max_students_quota']} siswa ({$tierLabel}).");
    }
}
