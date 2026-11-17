<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MagicLoginLink extends Notification
{
    public function __construct(private readonly string $url, private readonly int $minutes) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tautan masuk Jurnalin')
            ->greeting("Halo {$notifiable->name},")
            ->line("Ketuk tombol di bawah untuk masuk ke Jurnalin tanpa kata sandi. Tautan berlaku {$this->minutes} menit dan hanya bisa dipakai sekali.")
            ->action('Masuk ke Jurnalin', $this->url)
            ->line('Abaikan email ini bila Anda tidak meminta tautan masuk.');
    }
}
