<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Placement;
use App\Models\VisitReport;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kunjungan monitoring (P33) dan kunjungan kemitraan (H09). Laporan kunjungan bukan bukti kehadiran
 * seluruh peserta. Guru hanya melihat kunjungannya sendiri; laporan rahasia hanya penulis dan admin.
 */
final class VisitController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->role === UserRole::SchoolAdmin;
        $placements = Placement::query()->with(['student.user', 'company'])->where('status', PlacementStatus::Active)
            ->when(! $isAdmin, fn ($q) => $q->where('teacher_id', $user->teacher?->id))->get();

        $visits = VisitReport::query()->with(['company', 'author'])
            ->when(! $isAdmin, fn ($q) => $q->where('author_user_id', $user->id))
            ->latest('visit_date')->limit(100)->get();

        return view('spec.visits', [
            'placements' => $placements,
            'companies' => $isAdmin ? Company::query()->orderBy('name')->get() : Company::query()->whereIn('id', $placements->pluck('company_id'))->orderBy('name')->get(),
            'visits' => $visits,
            'studentNames' => Placement::query()->with('student.user')->whereIn('id', $visits->pluck('placement_ids')->flatten()->filter()->unique())->get()->mapWithKeys(fn ($p) => [$p->id => $p->student->user->name]),
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'kind' => ['required', 'in:monitoring,partnership'],
            'status' => ['required', 'in:planned,done'],
            'company_id' => ['required', 'uuid'],
            'visit_date' => ['required', 'date'],
            'placement_ids' => ['nullable', 'array'],
            'placement_ids.*' => ['uuid'],
            'student_condition' => ['nullable', 'string', 'max:2000'],
            'mentor_discussion' => ['nullable', 'string', 'max:2000'],
            'issues' => ['nullable', 'string', 'max:2000'],
            'follow_up' => ['nullable', 'string', 'max:1000'],
            'follow_up_owner' => ['nullable', 'string', 'max:120'],
            'follow_up_due' => ['nullable', 'date'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $allowedPlacements = Placement::query()->whereIn('id', $data['placement_ids'] ?? [])->where('company_id', $company->id)
            ->when($user->role !== UserRole::SchoolAdmin, fn ($q) => $q->where('teacher_id', $user->teacher?->id))->pluck('id')->all();

        $visit = VisitReport::query()->create([
            ...collect($data)->except(['photo', 'placement_ids'])->all(),
            'school_id' => $user->school_id,
            'author_user_id' => $user->id,
            'placement_ids' => $allowedPlacements,
            'photo_path' => MediaStorage::storeStudentUpload($user->school_id, 'visits', $request->file('photo')),
            'confidential' => $request->boolean('confidential'),
        ]);
        AuditLog::record('visit.recorded', $visit, ['company' => $company->name, 'date' => $data['visit_date']]);

        return back()->with('status', $data['status'] === 'planned' ? 'Agenda kunjungan tersimpan.' : 'Laporan kunjungan tersimpan.');
    }
}
