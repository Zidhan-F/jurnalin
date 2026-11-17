<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DisseminationRecord;
use App\Models\InternshipJournal;
use App\Models\PartnerContact;
use App\Models\Teacher;
use App\Models\TeacherInternship;
use App\Models\TransferPlan;
use App\Support\LearningObjectives;
use App\Support\MediaStorage;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Magang Guru Industri (G01-G12). Memakai akun guru yang sama (tanpa akun kedua) dengan konteks terpisah.
 * Guru tidak dapat menyetujui jurnal magangnya sendiri: peninjau adalah guru lain atau admin sekolah.
 */
final class InternshipController extends Controller
{
    public function admin(Request $request): View
    {
        return view('spec.internship.admin', [
            'internships' => TeacherInternship::query()->with(['teacher.user', 'company', 'reviewer.user', 'journals', 'transferPlans', 'evaluations'])->latest('start_date')->get(),
            'teachers' => Teacher::query()->with('user')->get()->sortBy(fn ($t) => $t->user->name),
            'companies' => Company::query()->orderBy('name')->get(),
            'contacts' => PartnerContact::query()->where('kind', 'mentor')->with('company')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'], 'company_id' => ['required', 'uuid'], 'reviewer_teacher_id' => ['required', 'uuid', 'different:teacher_id'],
            'partner_contact_id' => ['nullable', 'uuid'], 'title' => ['required', 'string', 'max:200'], 'objectives' => ['required', 'string', 'max:3000'],
            'division' => ['nullable', 'string', 'max:120'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], ['reviewer_teacher_id.different' => 'Peninjau harus guru lain: guru tidak boleh menyetujui jurnal magangnya sendiri.']);
        Teacher::query()->findOrFail($data['teacher_id']);
        Company::query()->findOrFail($data['company_id']);
        $internship = TeacherInternship::query()->create([...$data, 'school_id' => $request->user()->school_id, 'status' => 'active']);
        AuditLog::record('internship.created', $internship, ['title' => $internship->title]);
        Notify::user($internship->teacher->user, 'internship.created', 'Anda terdaftar sebagai peserta magang guru: '.$internship->title, null, '/teacher/magang');

        return back()->with('status', 'Program magang guru dibuat dan tercatat sebagai aktivitas kemitraan.');
    }

    public function updateStatus(Request $request, TeacherInternship $internship): RedirectResponse
    {
        $internship->update($request->validate(['status' => ['required', 'in:planned,active,completed']]));

        return back()->with('status', 'Status magang guru diperbarui.');
    }

    public function teacherIndex(Request $request): View
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403);

        return view('spec.internship.teacher', [
            'internships' => TeacherInternship::query()->with(['company', 'journals' => fn ($q) => $q->latest('journal_date'), 'transferPlans', 'disseminations', 'evaluations.contact'])
                ->where('teacher_id', $teacher->id)->latest('start_date')->get(),
            'toReview' => InternshipJournal::query()->with('internship.teacher.user')->where('status', 'submitted')
                ->whereHas('internship', fn ($q) => $q->where('reviewer_teacher_id', $teacher->id))->orderBy('journal_date')->get(),
            'objectives' => LearningObjectives::forSchool($request->user()->school, 'teacher'),
        ]);
    }

    public function storeJournal(Request $request, TeacherInternship $internship): RedirectResponse
    {
        $this->own($request, $internship);
        $data = $request->validate([
            'journal_date' => ['required', 'date', 'after_or_equal:'.$internship->start_date->toDateString(), 'before_or_equal:today'],
            'work_done' => ['required', 'string', 'min:10', 'max:3000'], 'industry_process' => ['nullable', 'string', 'max:2000'], 'technology' => ['nullable', 'string', 'max:300'],
            'learning' => ['nullable', 'string', 'max:2000'], 'obstacles' => ['nullable', 'string', 'max:1000'], 'potential_application' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        InternshipJournal::query()->create([...collect($data)->except('photo')->all(), 'school_id' => $internship->school_id, 'teacher_internship_id' => $internship->id,
            'photo_path' => MediaStorage::storeStudentUpload($internship->school_id, 'internships', $request->file('photo')), 'status' => 'submitted']);
        Notify::user($internship->reviewer?->user, 'internship.journal', 'Jurnal magang guru baru dari '.$request->user()->name, null, '/teacher/magang');

        return back()->with('status', 'Jurnal magang tersimpan dan diajukan ke peninjau.');
    }

    public function reviewJournal(Request $request, InternshipJournal $journal): RedirectResponse
    {
        $user = $request->user();
        $internship = $journal->internship;
        // Guru tidak boleh menyetujui jurnal magangnya sendiri (G02).
        abort_if($internship->teacher_id === $user->teacher?->id, 403, 'Anda tidak dapat meninjau jurnal magang milik sendiri.');
        abort_unless($user->role === UserRole::SchoolAdmin || $internship->reviewer_teacher_id === $user->teacher?->id, 403);
        $data = $request->validate(['status' => ['required', 'in:approved,revision_needed'], 'feedback' => ['required_if:status,revision_needed', 'nullable', 'string', 'max:1000']]);
        $journal->update([...$data, 'reviewed_by_user_id' => $user->id, 'reviewed_at' => now()]);
        AuditLog::record('internship.journal_reviewed', $journal, ['status' => $data['status']]);

        return back()->with('status', 'Tinjauan jurnal magang tersimpan.');
    }

    public function storePlan(Request $request, TeacherInternship $internship): RedirectResponse
    {
        $this->own($request, $internship);
        TransferPlan::query()->create([...$request->validate([
            'practice_learned' => ['required', 'string', 'max:2000'], 'plan' => ['required', 'string', 'max:3000'], 'materials' => ['nullable', 'string', 'max:1000'],
            'schedule_date' => ['nullable', 'date'], 'indicators' => ['nullable', 'string', 'max:300'], 'owner_name' => ['nullable', 'string', 'max:120'],
        ]), 'school_id' => $internship->school_id, 'teacher_internship_id' => $internship->id]);

        return back()->with('status', 'Rencana Penerapan tersimpan. Rencana belum dihitung sebagai dampak sebelum terlaksana dan dibuktikan.');
    }

    public function updatePlan(Request $request, TransferPlan $plan): RedirectResponse
    {
        $this->own($request, $plan->internship);
        $data = $request->validate(['status' => ['required', 'in:planned,in_progress,implemented'], 'evidence' => ['required_if:status,implemented', 'nullable', 'string', 'max:2000']],
            ['evidence.required_if' => 'Tuliskan bukti pelaksanaan sebelum menandai terlaksana.']);
        $plan->update([...$data, 'implemented_at' => $data['status'] === 'implemented' ? now() : null]);

        return back()->with('status', 'Status Rencana Penerapan diperbarui.');
    }

    public function storeDissemination(Request $request, TeacherInternship $internship): RedirectResponse
    {
        $this->own($request, $internship);
        DisseminationRecord::query()->create([...$request->validate([
            'title' => ['required', 'string', 'max:200'], 'held_on' => ['required', 'date'], 'participants_count' => ['required', 'integer', 'min:0', 'max:1000'],
            'material' => ['nullable', 'string', 'max:2000'], 'feedback' => ['nullable', 'string', 'max:2000'], 'follow_up' => ['nullable', 'string', 'max:1000'],
        ]), 'school_id' => $internship->school_id, 'teacher_internship_id' => $internship->id]);

        return back()->with('status', 'Kegiatan diseminasi tercatat. Jangan membagikan dokumen industri rahasia tanpa izin.');
    }

    public function saveReport(Request $request, TeacherInternship $internship): RedirectResponse
    {
        $this->own($request, $internship);
        $internship->update($request->validate(['report_summary' => ['nullable', 'string', 'max:5000'], 'report_reflection' => ['nullable', 'string', 'max:5000']]));

        return back()->with('status', 'Isi laporan magang disimpan. Unduh PDF untuk diperiksa.');
    }

    private function own(Request $request, TeacherInternship $internship): void
    {
        abort_unless($internship->teacher_id === $request->user()->teacher?->id, 403);
    }
}
