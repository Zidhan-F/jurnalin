<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\DataRequest;
use App\Models\GuardianConsent;
use App\Models\Journal;
use App\Models\Student;
use App\Models\User;
use App\Support\MediaStorage;
use App\Support\Notify;
use App\Support\PublicToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pusat privasi (S08) dan kepatuhan UU No. 27 Tahun 2022 (S13): perjanjian pemrosesan data sekolah,
 * pemberitahuan privasi berversi, persetujuan wali sebelum lokasi/swafoto aktif, dan permintaan subjek data.
 */
final class PrivacyController extends Controller
{
    public const DPA_VERSION = '2026-11';

    public function center(Request $request): View
    {
        $user = $request->user();

        return view('spec.privacy-center', [
            'user' => $user,
            'student' => $user->student,
            'consent' => $user->student?->activeConsent(),
            'requests' => DataRequest::query()->where('user_id', $user->id)->latest()->get(),
            'layout' => $user->role === UserRole::Student ? 'layouts.student-pwa' : 'layouts.app',
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:access,correction,deletion,withdraw'],
            'details' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $dr = DataRequest::query()->create([...$data, 'school_id' => $request->user()->school_id, 'user_id' => $request->user()->id]);
        AuditLog::record('privacy.request', $dr, ['kind' => $data['kind']]);
        Notify::users(User::query()->where('role', UserRole::SchoolAdmin)->where('is_active', true)->get(), 'privacy.request', 'Permintaan data pribadi baru', null, route('admin.privacy', [], false));

        return back()->with('status', 'Permintaan tercatat dan diteruskan ke admin sekolah sebagai pengendali data.');
    }

    /** Portabilitas: ekspor data milik sendiri tanpa membeli paket (10.6). */
    public function exportMine(Request $request): StreamedResponse
    {
        $user = $request->user();
        $student = $user->student;
        AuditLog::record('privacy.exported', $user);

        return response()->streamDownload(function () use ($user, $student): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Bagian', 'Tanggal', 'Judul / Status', 'Isi']);
            fputcsv($out, ['Profil', '', $user->name, $user->email.' / '.($user->phone ?? '-')]);
            if ($student) {
                foreach (Journal::query()->where('student_id', $student->id)->orderBy('journal_date')->get() as $j) {
                    fputcsv($out, ['Jurnal', $j->journal_date->toDateString(), $j->activity_title.' ('.$j->status->label().')', $j->activity_description]);
                }
                foreach (Attendance::query()->where('student_id', $student->id)->orderBy('attendance_date')->get() as $a) {
                    fputcsv($out, ['Presensi', $a->attendance_date->toDateString(), $a->status->label(), $a->exception_reason ?? '']);
                }
            }
            fclose($out);
        }, 'data-saya-jurnalin.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function toggleWhatsapp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['wa_opt_out' => ! $request->boolean('subscribe')])->save();

        return back()->with('status', $user->wa_opt_out ? 'Anda berhenti menerima pesan WhatsApp otomatis.' : 'Pesan WhatsApp otomatis diaktifkan kembali.');
    }

    public function admin(Request $request): View
    {
        $students = Student::query()->with(['user', 'consents'])->get();

        return view('spec.admin.privacy', [
            'school' => $request->user()->school,
            'dpaVersion' => self::DPA_VERSION,
            'students' => $students,
            'missing' => $students->filter(fn (Student $s) => ! $s->trackingAllowed()),
            'requests' => DataRequest::query()->with('user')->latest()->get(),
        ]);
    }

    public function acceptDpa(Request $request): RedirectResponse
    {
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'Centang pernyataan persetujuan perjanjian pemrosesan data.']);
        $school = $request->user()->school;
        $school->forceFill(['dpa_accepted_at' => now(), 'dpa_version' => self::DPA_VERSION])->save();
        AuditLog::record('privacy.dpa_accepted', $school, ['version' => self::DPA_VERSION], $school->id);

        return back()->with('status', 'Perjanjian pemrosesan data versi '.self::DPA_VERSION.' disetujui atas nama sekolah.');
    }

    public function updateRetention(Request $request): RedirectResponse
    {
        $data = $request->validate(['selfie_retention_days' => ['required', 'integer', 'min:1', 'max:1825']]);
        $request->user()->school->forceFill($data)->save();

        return back()->with('status', 'Masa simpan swafoto diperbarui.');
    }

    public function answerRequest(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:done,rejected'], 'response' => ['required', 'string', 'min:5', 'max:1000']]);
        $dataRequest->update([...$data, 'handled_by_user_id' => $request->user()->id, 'handled_at' => now()]);
        AuditLog::record('privacy.answered', $dataRequest, ['status' => $data['status']]);
        Notify::user($dataRequest->user, 'privacy.answered', 'Permintaan data pribadi Anda dijawab', null, '/privasi-saya');

        return back()->with('status', 'Jawaban permintaan data tersimpan.');
    }

    public function recordConsent(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $data = $request->validate([
            'guardian_name' => ['required', 'string', 'max:120'],
            'relation' => ['required', 'string', 'max:40'],
            'method' => ['required', 'in:paper,whatsapp'],
            'covers_location' => ['nullable', 'boolean'],
            'covers_selfie' => ['nullable', 'boolean'],
            'evidence' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $consent = GuardianConsent::query()->create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'guardian_name' => $data['guardian_name'],
            'relation' => $data['relation'],
            'method' => $data['method'],
            'covers_location' => $request->boolean('covers_location', true),
            'covers_selfie' => $request->boolean('covers_selfie', true),
            'notice_version' => $request->user()->school->privacy_notice_version,
            'recorded_by_user_id' => $request->user()->id,
            'evidence_path' => MediaStorage::storeStudentUpload($student->school_id, 'consents', $request->file('evidence')),
            'given_at' => now(),
        ]);
        AuditLog::record('consent.recorded', $consent, ['student' => $student->user->name, 'method' => $data['method']]);

        return back()->with('status', 'Persetujuan wali '.$student->user->name.' tercatat. Lokasi dan swafoto kini boleh dipakai sesuai kebijakan periode.');
    }

    public function consentLink(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        [$token, $hash] = PublicToken::make(40);
        GuardianConsent::query()->create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'method' => 'digital',
            'notice_version' => $request->user()->school->privacy_notice_version,
            'recorded_by_user_id' => $request->user()->id,
            'token_hash' => $hash,
            'token_expires_at' => now()->addDays(14),
        ]);
        $url = route('consent.form', $token);
        $message = "Yth. Orang tua/wali {$student->user->name}, mohon baca pemberitahuan privasi PKL dan berikan persetujuan melalui tautan berikut (berlaku 14 hari): {$url}";

        return back()->with('status', 'Tautan persetujuan wali dibuat.')->with('magic_link', [
            'url' => $url, 'name' => 'wali '.$student->user->name, 'wa' => Notify::waShare($student->parent_phone, $message),
        ]);
    }

    public function withdrawConsent(Request $request, GuardianConsent $consent): RedirectResponse
    {
        $this->authorizeStudent($request, $consent->student);
        $consent->update(['withdrawn_at' => now()]);
        AuditLog::record('consent.withdrawn', $consent, ['student' => $consent->student->user->name]);

        return back()->with('status', 'Persetujuan dicabut. Lokasi dan swafoto tidak lagi diambil untuk siswa ini.');
    }

    public function guardianForm(string $token): Response
    {
        $result = PublicToken::within(GuardianConsent::class, $token, function (GuardianConsent $consent) {
            $decided = $consent->given_at !== null || $consent->withdrawn_at !== null;
            if (! $decided && $consent->token_expires_at?->isPast()) {
                return null;
            }
            $consent->load(['student.user', 'school']);

            return response(view('spec.public.guardian-consent', ['consent' => $consent, 'token' => request()->route('token'), 'done' => $decided])->render());
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Tautan persetujuan tidak dikenal atau sudah kedaluwarsa. Minta tautan baru ke guru pembimbing.'], 404);
    }

    public function guardianSubmit(Request $request, string $token): Response
    {
        $data = $request->validate([
            'guardian_name' => ['required', 'string', 'max:120'],
            'relation' => ['required', 'string', 'max:40'],
            'decision' => ['required', 'in:agree,decline'],
            'covers_location' => ['nullable', 'boolean'],
            'covers_selfie' => ['nullable', 'boolean'],
        ]);

        $result = PublicToken::within(GuardianConsent::class, $token, function (GuardianConsent $consent) use ($data, $request, $token) {
            if ($consent->given_at !== null || $consent->withdrawn_at !== null || $consent->token_expires_at?->isPast()) {
                return null;
            }
            $agree = $data['decision'] === 'agree';
            $consent->update([
                'guardian_name' => $data['guardian_name'],
                'relation' => $data['relation'],
                'covers_location' => $agree && $request->boolean('covers_location'),
                'covers_selfie' => $agree && $request->boolean('covers_selfie'),
                'given_at' => $agree ? now() : null,
                'withdrawn_at' => $agree ? null : now(),
                'token_expires_at' => now(),
            ]);
            AuditLog::record($agree ? 'consent.given_digital' : 'consent.declined', $consent, ['student' => $consent->student->user->name], $consent->school_id, $data['guardian_name']);

            $consent->load(['student.user', 'school']);

            return response(view('spec.public.guardian-consent', ['consent' => $consent, 'token' => $token, 'done' => true])->render());
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Tautan persetujuan sudah dipakai atau kedaluwarsa. Terima kasih.'], 404);
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        $user = $request->user();
        if ($user->role === UserRole::Teacher) {
            abort_unless($student->placements()->where('teacher_id', $user->teacher?->id)->exists(), 403);
        }
    }
}
