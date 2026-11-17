<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\School;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SchoolApproved extends Notification
{
    public function __construct(private readonly School $school) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->school->name} sudah aktif di Jurnalin")
            ->greeting("Halo {$notifiable->name},")
            ->line("NPSN {$this->school->npsn} sudah kami cocokkan, dan akun {$this->school->name} kini aktif.")
            ->line('Langkah pertama: buat periode PKL, tambahkan tempat PKL, lalu impor guru dan siswa dari Excel.')
            ->action('Buka panel admin sekolah', url('/admin'));
    }
}
