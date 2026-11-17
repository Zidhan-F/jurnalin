<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Kode OTP untuk pengesahan oleh pembimbing lapangan / penguji tanpa akun (M03, M09, U03). */
final class GuestOtpCode extends Notification
{
    public function __construct(public readonly string $code, private readonly string $schoolName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode verifikasi Jurnalin')
            ->line("Kode verifikasi untuk mengesahkan penilaian di {$this->schoolName}:")
            ->line($this->code)
            ->line('Kode berlaku 10 menit. Jangan bagikan kode ini kepada siapa pun, termasuk siswa.');
    }
}
