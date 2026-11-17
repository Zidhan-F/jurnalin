<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Support\Notify;
use App\Support\PublicToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formulir mitra tanpa akun (H08, H14, H16). Penerima tautan hanya bisa mengisi permintaan itu;
 * tidak ada akses ke data sekolah. Tanggapan ditinjau sekolah sebelum disimpan.
 */
final class PartnerPortalController extends Controller
{
    public function show(string $token): Response
    {
        $result = PublicToken::within(PartnerRequest::class, $token, function (PartnerRequest $req) use ($token) {
            if ($req->expires_at->isPast() && $req->status === 'sent') {
                return null;
            }
            $req->load(['company', 'school']);

            return response(view('spec.public.partner-form', ['req' => $req, 'token' => $token])->render());
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Tautan formulir tidak dikenal atau sudah kedaluwarsa.'], 404);
    }

    public function submit(Request $request, string $token): Response
    {
        $data = $request->validate([
            'responder_name' => ['required', 'string', 'max:120'],
            'responder_position' => ['required', 'string', 'max:120'],
            'pic_name' => ['nullable', 'string', 'max:120'],
            'pic_position' => ['nullable', 'string', 'max:120'],
            'pic_phone' => ['nullable', 'string', 'max:24'],
            'pic_email' => ['nullable', 'email', 'max:150'],
            'division' => ['nullable', 'string', 'max:120'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:500'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Centang pernyataan bahwa informasi ini benar.']);

        $result = PublicToken::within(PartnerRequest::class, $token, function (PartnerRequest $req) use ($data, $token) {
            if ($req->status !== 'sent' || $req->expires_at->isPast()) {
                return null;
            }
            $req->update([
                'response' => collect($data)->except(['responder_name', 'responder_position', 'confirm'])->filter(fn ($v) => $v !== null && $v !== '')->all(),
                'responder_name' => $data['responder_name'],
                'responder_position' => $data['responder_position'],
                'responded_at' => now(),
                'status' => 'responded',
            ]);
            AuditLog::record('partner.responded', $req, ['company' => $req->company->name], $req->school_id, $data['responder_name']);
            Notify::users(User::query()->where('role', UserRole::SchoolAdmin)->where('is_active', true)->get(), 'partner.responded', 'Tanggapan mitra dari '.$req->company->name, null, '/admin/mitra/'.$req->company_id.'#undangan');

            return response(view('spec.public.partner-form', ['req' => $req->load(['company', 'school']), 'token' => $token, 'done' => true])->render());
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Formulir ini sudah diisi atau kedaluwarsa. Terima kasih.'], 404);
    }
}
