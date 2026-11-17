<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\MediaStorage;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaProxyController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $normalized = ltrim(str_replace('..', '', $path), '/');
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->role !== UserRole::SuperAdmin) {
            $schoolId = TenantContext::currentSchoolId() ?? $user->school_id;
            if ($schoolId === null || ! str_starts_with($normalized, "schools/{$schoolId}/")) {
                abort(403, 'Akses berkas lintas sekolah ditolak.');
            }
        }

        $contentType = MediaStorage::IMAGE_TYPES[strtolower(pathinfo($normalized, PATHINFO_EXTENSION))] ?? null;
        // Dokumen kerja sama (MoU/PKS) boleh PDF, hanya untuk admin sekolah.
        if ($contentType === null && str_ends_with(strtolower($normalized), '.pdf') && (explode('/', $normalized)[2] ?? '') === 'agreements' && $user->role === UserRole::SchoolAdmin) {
            $contentType = 'application/pdf';
        }
        if ($request->attributes->has('full_placements') && $normalized !== $user->school->logo_path) {
            $visible = match (explode('/', $normalized)[2] ?? '') {
                'journals' => \App\Models\Journal::where(fn ($q) => $q->where('photo_path', $normalized)->orWhereJsonContains('photo_paths', $normalized))->exists(),
                'leaves' => \App\Models\LeaveRequest::where('attachment_path', $normalized)->exists(),
                'workproofs' => \App\Models\WorkProof::where('photo_path', $normalized)->exists(),
                'visits' => \App\Models\VisitReport::where('photo_path', $normalized)->where('author_user_id', $user->id)->exists(),
                'consents' => $user->role === UserRole::Teacher && \App\Models\GuardianConsent::where('evidence_path', $normalized)->exists(),
                'internships' => \App\Models\InternshipJournal::where('photo_path', $normalized)->exists(),
                'attendances' => \App\Models\Attendance::where(fn ($q) => $q->where('check_in_photo_path', $normalized)->orWhere('check_out_photo_path', $normalized))->exists(),
                'assessments' => \App\Models\Assessment::where('industry_form_photo_path', $normalized)->exists(),
                'signatures' => \App\Models\MentorSignoff::where('signature_path', $normalized)->exists(),
                default => false,
            };
            abort_unless($visible, 404);
        }
        $absolutePath = MediaStorage::absolutePath($normalized);
        abort_if($absolutePath === null || $contentType === null, 404, 'Berkas tidak ditemukan.');

        // Content-Type tetap + nosniff: browser tidak boleh menafsirkan unggahan sebagai HTML/JS.
        return response()->file($absolutePath, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
