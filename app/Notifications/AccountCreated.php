<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountCreated extends Notification
{
    public function __construct(private readonly string $schoolName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Sandi awal sengaja tidak ditulis di email; diberikan sekolah secara langsung.
        return (new MailMessage)
            ->subject("Akun Jurnalin Anda di {$this->schoolName}")
            ->greeting("Halo {$notifiable->name},")
            ->line("{$this->schoolName} membuatkan akun Jurnalin untuk Anda dengan email {$notifiable->email}.")
            ->line('Masuk dengan kata sandi awal dari sekolah. Anda akan diminta menggantinya saat pertama kali masuk.')
            ->action('Masuk ke Jurnalin', url('/login'))
            ->line('Lupa kata sandi awal? Gunakan menu "Lupa kata sandi" di halaman masuk.');
    }
}
