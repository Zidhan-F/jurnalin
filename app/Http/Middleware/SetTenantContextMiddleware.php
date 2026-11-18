<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\School;
use App\Support\TenantContext;
use App\Support\GroupAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            TenantContext::clear();

            return $next($request);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect('/login')->withErrors([
                'email' => 'Akun Anda sedang dinonaktifkan oleh admin.',
            ]);
        }

        if ($user->role === UserRole::SuperAdmin) {
            TenantContext::setSuperAdmin(true);

            try {
                return $this->runInsidePgsqlTransactionIfNeeded($request, $next);
            } finally {
                TenantContext::clear();
            }
        }

        $school = $user->school;
        if ($school === null || ! $school->isActive()) {
            $statusLabel = $school?->status->label() ?? 'Tidak ditemukan';
            Auth::logout();
            $request->session()->invalidate();

            return redirect('/login')->withErrors([
                'email' => "Akses sekolah ditahan. Status sekolah: {$statusLabel}. Hubungi administrator Jurnalin.",
            ]);
        }

        TenantContext::setSchool($school->id);

        try {
            return $this->runInsidePgsqlTransactionIfNeeded($request, $next);
        } finally {
            TenantContext::clear();
        }
    }

    private function withGroupAccess(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::Teacher, UserRole::Student)) {
            return $this->continueAuthenticated($request, $next);
        }

        $school = School::sharedLock()->findOrFail($user->school_id);
        $user->setRelation('school', $school);
        $version = (int) $user->newQuery()->whereKey($user->id)->value('access_version');
        $today = now($school->timezone)->toDateString();
        $student = $user->role === UserRole::Student;
        $rows = GroupAccess::roster($school)
            ->where($student ? 'p.student_id' : 'p.teacher_id', $student ? $user->student?->id : $user->teacher?->id)
            ->get()->groupBy(fn ($row) => GroupAccess::phase($row, $school, $today));
        $full = $rows->get('full', collect())->pluck('id')->all();
        $grace = $rows->get('journal', collect())->pluck('id')->all();

        if ((int) $request->session()->get('access_version', 0) !== $version
            || (! $full && ! $grace && ($rows->isNotEmpty() || $school->require_group_payment))) {
            Auth::logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'Akses grup berubah atau belum aktif. Hubungi admin sekolah untuk memeriksa akses Anda.']);
        }

        $request->attributes->set('full_placements', $full);
        $request->attributes->set('journal_placements', array_merge($full, $grace));
        $request->attributes->set('grace_placements', $grace);
        if (! $full && $grace && ! $request->routeIs('student.journals*', 'teacher.index', 'teacher.journals.*', 'media.show', 'password.*')) {
            if ($request->isMethod('get') && $request->routeIs('student.home', 'student.profile', 'student.attendance', 'student.report', 'teacher.assessments')) {
                return redirect()->route($student ? 'student.journals' : 'teacher.index')->with('status', 'Bulan tambahan hanya mencakup jurnal.');
            }
            abort(403, 'Bulan tambahan hanya mencakup jurnal.');
        }
        try {
            $response = $this->continueAuthenticated($request, $next);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        } finally {
            foreach (['full_placements', 'journal_placements', 'grace_placements'] as $attribute) {
                $request->attributes->remove($attribute);
            }
        }
    }

    private function continueAuthenticated(Request $request, Closure $next): Response
    {
        if ($request->user()->must_change_password && ! $request->routeIs('password.*')) {
            return redirect('/password');
        }

        return $next($request);
    }

    private function runInsidePgsqlTransactionIfNeeded(Request $request, Closure $next): Response
    {
        if (DB::getDriverName() === 'pgsql') {
            return DB::transaction(function () use ($request, $next): Response {
                if (TenantContext::isSuperAdmin()) {
                    TenantContext::setSuperAdmin(true);
                } elseif (TenantContext::currentSchoolId() !== null) {
                    TenantContext::setSchool(TenantContext::currentSchoolId());
                }

                return $this->withGroupAccess($request, $next);
            });
        }

        return $next($request);
    }
}
