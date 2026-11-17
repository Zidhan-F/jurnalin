<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\GuestAccessGrant;
use App\Models\PartnerContact;
use App\Models\Placement;
use App\Models\TeacherInternship;
use App\Support\Notify;
use App\Support\PublicToken;
use App\Support\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mentor Access (M01-M14): identitas pembimbing lapangan tanpa akun, tautan scoped berbatas waktu,
 * multi-peserta dalam satu tautan, pencabutan & rotasi, dan pengingat evaluasi tanpa data sensitif.
 */
final class MentorAccessController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->role === UserRole::SchoolAdmin;
        $placements = Placement::query()->with(['student.user', 'company', 'pklPeriod'])->where('status', PlacementStatus::Active)
            ->when(! $isAdmin, fn ($q) => $q->where('teacher_id', $user->teacher?->id))->get();

        $grants = GuestAccessGrant::query()->with('contact.company')->latest()->get()
            ->filter(fn ($g) => $isAdmin || $g->created_by_user_id === $user->id || array_intersect($g->scope['placement_ids'] ?? [], $placements->pluck('id')->all()));

        return view('spec.mentor-access', [
            'enabled' => $user->school->hasFeature('mentor_access'),
            'placements' => $placements,
            'contacts' => PartnerContact::query()->with('company')->where('is_active', true)->whereIn('kind', ['mentor', 'examiner', 'pic'])
                ->when(! $isAdmin, fn ($q) => $q->whereIn('company_id', $placements->pluck('company_id')))->orderBy('name')->get(),
            'companies' => $isAdmin ? Company::query()->orderBy('name')->get() : Company::query()->whereIn('id', $placements->pluck('company_id'))->get(),
            'internships' => $isAdmin ? TeacherInternship::query()->with(['teacher.user', 'company'])->where('status', 'active')->get() : collect(),
            'grants' => $grants,
            'studentNames' => $placements->mapWithKeys(fn ($p) => [$p->id => $p->student->user->name]),
            'approver' => $user->school->journal_approver,
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:24'],
            'email' => ['nullable', 'email', 'max:150'],
            'kind' => ['required', 'in:mentor,examiner,pic'],
            'valid_until' => ['nullable', 'date'],
        ]);
        Company::query()->findOrFail($data['company_id']);
        $contact = PartnerContact::query()->create([...$data, 'school_id' => $request->user()->school_id, 'valid_from' => now()->toDateString()]);
        AuditLog::record('contact.created', $contact, ['name' => $contact->name]);

        return back()->with('status', "Kontak {$contact->name} tersimpan sebagai entitas tanpa akun.");
    }

    public function issue(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->school->hasFeature('mentor_access'), 403, 'Akses pembimbing industri termasuk paket Sekolah Pro.');
        $data = $request->validate([
            'partner_contact_id' => ['required', 'uuid'],
            'placement_ids' => ['nullable', 'array'],
            'placement_ids.*' => ['uuid'],
            'internship_ids' => ['nullable', 'array'],
            'internship_ids.*' => ['uuid'],
            'days' => ['required', 'integer', 'min:1', 'max:180'],
            'can_review' => ['nullable', 'boolean'],
            'can_evaluate' => ['nullable', 'boolean'],
        ]);
        $contact = PartnerContact::query()->findOrFail($data['partner_contact_id']);
        $placements = Placement::query()->whereIn('id', $data['placement_ids'] ?? [])->where('status', PlacementStatus::Active)
            ->when($user->role === UserRole::Teacher, fn ($q) => $q->where('teacher_id', $user->teacher?->id))->get();
        $internships = $user->role === UserRole::SchoolAdmin ? TeacherInternship::query()->whereIn('id', $data['internship_ids'] ?? [])->pluck('id')->all() : [];
        if ($placements->isEmpty() && $internships === []) {
            return back()->withErrors(['placement_ids' => 'Pilih minimal satu peserta yang ditugaskan.']);
        }

        // Rotasi mentor (M11): tautan lama untuk kontak yang sama langsung dicabut.
        GuestAccessGrant::query()->where('partner_contact_id', $contact->id)->where('purpose', 'mentor')->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => 'Diganti tautan baru']);

        [$token, $hash] = PublicToken::make(48);
        $grant = GuestAccessGrant::query()->create([
            'school_id' => $user->school_id,
            'partner_contact_id' => $contact->id,
            'purpose' => 'mentor',
            'token_hash' => $hash,
            'token' => $token,
            'scope' => ['placement_ids' => $placements->pluck('id')->all(), 'internship_ids' => $internships],
            'can_review' => $request->boolean('can_review'),
            'can_evaluate' => $request->boolean('can_evaluate', true),
            'expires_at' => now()->addDays((int) $data['days']),
            'created_by_user_id' => $user->id,
        ]);
        foreach ($placements as $p) {
            $p->forceFill(['mentor_contact_id' => $contact->id])->save();
        }
        AuditLog::record('mentor_access.issued', $grant, ['contact' => $contact->name, 'participants' => $placements->count() + count($internships)]);

        $url = route('portal.hub', $token);
        $message = "Yth. {$contact->name}, berikut tautan aman Jurnalin dari sekolah untuk memantau dan menilai peserta PKL yang Anda bimbing (berlaku {$data['days']} hari). Mohon tidak diteruskan: {$url}";

        return back()->with('status', 'Tautan pembimbing industri dibuat. Tautan lama untuk kontak ini, bila ada, dicabut.')
            ->with('magic_link', ['url' => $url, 'name' => $contact->name, 'wa' => Notify::waShare($contact->phone, $message)]);
    }

    public function revoke(Request $request, GuestAccessGrant $grant): RedirectResponse
    {
        $this->authorizeGrant($request, $grant);
        $grant->update(['revoked_at' => now(), 'revoked_reason' => $request->input('reason') ?: 'Dicabut sekolah']);
        AuditLog::record('mentor_access.revoked', $grant, ['contact' => $grant->contact->name]);

        return back()->with('status', 'Tautan dicabut dan langsung tidak berlaku. Riwayat penilaian sebelumnya tetap tersimpan.');
    }

    public function remind(Request $request, GuestAccessGrant $grant): RedirectResponse
    {
        $this->authorizeGrant($request, $grant);
        $school = $request->user()->school;
        $status = WhatsAppGateway::send($school, null, $grant->contact->phone, 'mentor_evaluation_reminder');
        $message = "Yth. {$grant->contact->name}, pengingat dari sekolah: evaluasi peserta PKL menunggu. Silakan buka tautan Jurnalin yang sudah kami kirim. Terima kasih.";

        return back()->with('status', $status === 'sent' ? 'Pengingat WhatsApp otomatis terkirim.' : 'Pengingat siap dibagikan manual (tanpa nama atau nilai peserta).')
            ->with('magic_link', ['url' => $grant->token ? route('portal.hub', $grant->token) : '', 'name' => $grant->contact->name, 'wa' => Notify::waShare($grant->contact->phone, $message)]);
    }

    private function authorizeGrant(Request $request, GuestAccessGrant $grant): void
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::SchoolAdmin || $grant->created_by_user_id === $user->id, 403);
    }
}
