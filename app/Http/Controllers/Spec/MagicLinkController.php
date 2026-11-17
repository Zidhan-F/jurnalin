<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use App\Support\Notify;
use App\Support\PublicToken;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Masuk dengan magic link (P01). Akun hanya yang diundang/diimpor sekolah; tidak ada pendaftaran mandiri.
 * Tautan sekali pakai, hash disimpan, kedaluwarsa singkat. Admin/guru dapat membagikannya lewat WhatsApp.
 */
final class MagicLinkController extends Controller
{
    private const EMAIL_MINUTES = 30;

    private const SHARE_HOURS = 72;

    public function request(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email', 'max:150']])['email']));
        $user = TenantContext::withAuthLookup($email, null, fn () => User::query()->whereRaw('lower(email) = ?', [$email])->first());

        if ($user !== null && $user->is_active) {
            $user->notify(new MagicLoginLink($this->createLink($user, now()->addMinutes(self::EMAIL_MINUTES)), self::EMAIL_MINUTES));
        }

        // Pesan sama untuk email terdaftar atau tidak, agar daftar akun tidak bocor.
        return back()->with('status', 'Bila email terdaftar, tautan masuk sudah dikirim. Periksa kotak masuk atau folder spam.');
    }

    public function issue(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($user->school_id === $actor->school_id && $user->role !== UserRole::SuperAdmin, 404);
        if ($actor->role === UserRole::Teacher) {
            $isMentee = $user->student && $user->student->placements()->where('teacher_id', $actor->teacher?->id)->exists();
            abort_unless($isMentee, 403);
        }

        $url = $this->createLink($user, now()->addHours(self::SHARE_HOURS));
        AuditLog::record('user.magic_link_issued', $user, ['name' => $user->name]);
        $message = "Halo {$user->name}, ini tautan masuk Jurnalin dari sekolah (berlaku 3 hari, sekali pakai): {$url}";

        return back()->with('status', "Tautan masuk untuk {$user->name} siap dibagikan.")
            ->with('magic_link', ['url' => $url, 'wa' => Notify::waShare($user->phone, $message), 'name' => $user->name]);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $hash = PublicToken::hash($token);
        $row = DB::table('login_tokens')->where('token_hash', $hash)->first();
        if ($row === null || $row->used_at !== null || now()->greaterThan($row->expires_at)) {
            return redirect('/login')->withErrors(['email' => 'Tautan masuk sudah dipakai atau kedaluwarsa. Minta tautan baru dari halaman masuk atau dari sekolah.']);
        }

        $user = TenantContext::withAuthLookup(null, $row->user_id, fn () => User::query()->find($row->user_id));
        if ($user === null || ! $user->is_active) {
            return redirect('/login')->withErrors(['email' => 'Akun tidak aktif. Hubungi admin sekolah.']);
        }
        if ($user->role !== UserRole::SuperAdmin && ! TenantContext::runForUser($user, fn () => (bool) $user->school?->isActive())) {
            return redirect('/login')->withErrors(['email' => 'Sekolah Anda belum aktif di Jurnalin.']);
        }

        $updated = DB::table('login_tokens')->where('id', $row->id)->whereNull('used_at')->update(['used_at' => now()]);
        if ($updated !== 1) {
            return redirect('/login')->withErrors(['email' => 'Tautan masuk sudah dipakai.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        TenantContext::runForUser($user, fn () => $user->forceFill(['last_login_at' => now()])->save());

        return redirect($user->role->homePath())->with('status', 'Selamat datang, '.$user->name.'.');
    }

    private function createLink(User $user, \DateTimeInterface $expires): string
    {
        [$token, $hash] = PublicToken::make(48);
        DB::table('login_tokens')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'token_hash' => $hash,
            'expires_at' => $expires,
            'created_at' => now(),
        ]);

        return route('magic.consume', $token);
    }
}
