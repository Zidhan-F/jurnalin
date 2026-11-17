<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MessageLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pengirim WhatsApp Business API (P42). Driver dipilih lewat env:
 * - WHATSAPP_DRIVER=log (default): tidak mengirim, hanya mencatat di message_logs (aman untuk lokal & pilot).
 * - WHATSAPP_DRIVER=http: POST ke WHATSAPP_API_URL dengan WHATSAPP_API_TOKEN (penyedia resmi BSP).
 * Isi pesan memakai templat yang disetujui, tanpa nilai/data sensitif. Biaya dicatat per tenant.
 */
final class WhatsAppGateway
{
    public const TEMPLATES = [
        'teacher_daily_digest' => 'Ringkasan harian Jurnalin: {pending_attendance} siswa belum presensi, {pending_journals} jurnal menunggu review, {pending_leaves} izin menunggu. Buka Jurnalin untuk detail.',
        'student_journal_reminder' => 'Pengingat Jurnalin: jurnal kegiatan PKL hari ini belum tercatat. Isi sebelum pulang ya.',
        'mentor_evaluation_reminder' => 'Pengingat Jurnalin dari sekolah: evaluasi akhir peserta PKL menunggu. Buka tautan yang dikirim guru pembimbing.',
    ];

    /** @param array<string, string|int> $vars */
    public static function send(School $school, ?User $user, ?string $phone, string $template, array $vars = []): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $masked = $digits !== '' ? substr($digits, 0, 4).str_repeat('*', max(0, strlen($digits) - 7)).substr($digits, -3) : '-';
        $log = fn (string $status, ?string $error = null, ?string $ref = null, int $cost = 0) => MessageLog::query()->create([
            'school_id' => $school->id,
            'recipient_user_id' => $user?->id,
            'channel' => 'whatsapp',
            'recipient' => $masked,
            'template' => $template,
            'status' => $status,
            'provider_ref' => $ref,
            'cost' => $cost,
            'error' => $error,
        ]);

        if (! $school->hasFeature('whatsapp') || ! $school->wa_notifications) {
            $log('skipped', 'Modul WhatsApp otomatis belum aktif untuk sekolah ini.');

            return 'skipped';
        }
        if ($user?->wa_opt_out) {
            $log('skipped', 'Penerima memilih berhenti berlangganan.');

            return 'skipped';
        }
        if ($digits === '') {
            $log('skipped', 'Nomor WhatsApp kosong.');

            return 'skipped';
        }

        $body = strtr(self::TEMPLATES[$template] ?? $template, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
        if (config('services.whatsapp.driver', 'log') !== 'http') {
            $log('logged');

            return 'logged';
        }

        try {
            $response = Http::withToken((string) config('services.whatsapp.token'))->timeout(10)
                ->post((string) config('services.whatsapp.url'), ['to' => $digits, 'template' => $template, 'body' => $body]);
            $log($response->successful() ? 'sent' : 'failed', $response->successful() ? null : 'HTTP '.$response->status(), (string) $response->json('id'), (int) config('services.whatsapp.cost_per_message', 0));

            return $response->successful() ? 'sent' : 'failed';
        } catch (Throwable $e) {
            $log('failed', mb_substr($e->getMessage(), 0, 300));

            return 'failed';
        }
    }
}
