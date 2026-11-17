<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event): void {
            if (request()->hasSession()) {
                request()->session()->put('access_version', (int) $event->user->access_version);
            }
        });

        Auth::provider('tenant-eloquent', fn ($app, array $config) => new TenantAwareUserProvider($app['hash'], $config['model']));

        // Per email + IP, bukan per IP saja: satu sekolah sering berbagi satu IP WiFi.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        ResetPassword::toMailUsing(fn (object $user, string $token) => (new MailMessage)
            ->subject('Reset kata sandi Jurnalin')
            ->greeting("Halo {$user->name},")
            ->line('Kami menerima permintaan untuk membuat kata sandi baru bagi akun Anda.')
            ->action('Buat kata sandi baru', url(route('password.reset', ['token' => $token, 'email' => $user->email], false)))
            ->line('Tautan ini berlaku 60 menit dan hanya bisa dipakai sekali.')
            ->line('Bila Anda tidak meminta reset, abaikan email ini. Kata sandi Anda tidak berubah.'));
    }
}
