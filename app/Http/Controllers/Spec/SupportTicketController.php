<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Placement;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lapor ke Guru (P34). Kategori sensitif (perlakuan tidak pantas, masalah dengan guru) tidak pernah
 * diteruskan ke guru pembimbing: langsung ke admin sekolah. Jalur darurat selalu ditampilkan.
 */
final class SupportTicketController extends Controller
{
    public function studentIndex(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        return view('spec.student.tickets', [
            'tickets' => SupportTicket::query()->where('student_id', $student->id)->latest()->get(),
            'categories' => config('pkl.support_categories'),
            'emergency' => config('pkl.emergency_lines'),
        ]);
    }

    public function studentStore(Request $request): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $categories = config('pkl.support_categories');
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys($categories))],
            'urgency' => ['required', 'in:normal,urgent,emergency'],
            'recipient' => ['required', 'in:teacher,school_admin'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $placement = Placement::query()->with('teacher.user')->where('student_id', $student->id)->where('status', PlacementStatus::Active)->first();
        $recipient = $categories[$data['category']]['sensitive'] || $placement === null ? 'school_admin' : $data['recipient'];

        $ticket = SupportTicket::query()->create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'placement_id' => $placement?->id,
            'category' => $data['category'],
            'urgency' => $data['urgency'],
            'recipient' => $recipient,
            'body' => $data['body'],
            'activity' => [['at' => now()->toIso8601String(), 'by' => 'Siswa', 'note' => 'Laporan dibuat']],
        ]);
        AuditLog::record('ticket.created', $ticket, ['category' => $data['category'], 'urgency' => $data['urgency']]);

        // Isi laporan tidak disertakan dalam notifikasi; penerima membukanya di aplikasi.
        $receivers = $recipient === 'teacher'
            ? collect([$placement->teacher->user])
            : User::query()->where('role', UserRole::SchoolAdmin)->where('is_active', true)->get();
        Notify::users($receivers, 'ticket.created', ($data['urgency'] === 'normal' ? 'Laporan baru' : 'Laporan MENDESAK').' dari siswa', null, route('tickets.inbox', [], false));

        return redirect()->route('student.tickets')->with('status', $recipient === 'school_admin'
            ? 'Laporan terkirim langsung ke admin sekolah secara rahasia.'
            : 'Laporan terkirim ke guru pembimbing.');
    }

    public function inbox(Request $request): View
    {
        return view('spec.tickets-inbox', [
            'tickets' => $this->visibleTickets($request)->with(['student.user', 'placement.company'])->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")->latest()->get(),
            'categories' => config('pkl.support_categories'),
        ]);
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($this->visibleTickets($request)->whereKey($ticket->id)->exists(), 404);
        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved'],
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $activity = $ticket->activity ?? [];
        $activity[] = ['at' => now()->toIso8601String(), 'by' => $request->user()->name, 'note' => $data['note']];
        $ticket->update([
            'status' => $data['status'],
            'activity' => $activity,
            'handled_by_user_id' => $request->user()->id,
            'resolution' => $data['status'] === 'resolved' ? $data['note'] : $ticket->resolution,
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ]);
        AuditLog::record('ticket.updated', $ticket, ['status' => $data['status']]);
        Notify::user($ticket->student->user, 'ticket.updated', 'Laporan Anda ditindaklanjuti', null, '/student/lapor');

        return back()->with('status', 'Tindak lanjut laporan tersimpan.');
    }

    private function visibleTickets(Request $request): Builder
    {
        $user = $request->user();
        $query = SupportTicket::query();

        return $user->role === UserRole::SchoolAdmin
            ? $query
            : $query->where('recipient', 'teacher')->whereHas('placement', fn ($q) => $q->where('teacher_id', $user->teacher?->id));
    }
}
