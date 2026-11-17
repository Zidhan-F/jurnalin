<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\UserNotification;
use Throwable;

/**
 * Notifikasi dalam aplikasi (P15). Isi singkat, tanpa nilai atau data sensitif.
 * Kanal WhatsApp otomatis (P42) memakai WhatsAppGateway dan hanya untuk ringkasan terjadwal.
 */
final class Notify
{
    public static function user(?User $user, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        if ($user === null || $user->school_id === null) {
            return;
        }

        try {
            UserNotification::query()->create([
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'kind' => $kind,
                'title' => mb_substr($title, 0, 200),
                'body' => $body,
                'url' => $url,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param iterable<User|null> $users */
    public static function users(iterable $users, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        foreach ($users as $user) {
            self::user($user, $kind, $title, $body, $url);
        }
    }

    /** Tautan bagikan WhatsApp (wa.me) tanpa biaya API. Nomor dinormalisasi ke format 62. */
    public static function waShare(?string $phone, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return 'https://wa.me/?text='.rawurlencode($message);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return "https://wa.me/{$digits}?text=".rawurlencode($message);
    }
}
