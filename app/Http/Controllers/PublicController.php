<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Onboarding\RegisterSchoolAction;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final class PublicController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'demoAccounts' => app()->environment('local', 'testing') ? $this->demoAccounts() : [],
        ]);
    }

    public function registerSchoolForm(): View
    {
        return view('public.register-school');
    }

    public function registerSchoolSubmit(Request $request, RegisterSchoolAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'npsn' => ['required', 'digits:8'],
            'school_name' => ['required', 'string', 'max:180'],
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_phone' => ['required', 'string', 'max:24'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'Centang persetujuan Syarat Layanan dan Kebijakan Privasi untuk melanjutkan.']);

        try {
            $school = $action->handle($validated);
        } catch (InvalidArgumentException|DomainException $e) {
            return back()->withInput()->withErrors(['npsn' => $e->getMessage()]);
        }

        return redirect('/login')->with(
            'status',
            "Pendaftaran {$school->name} (NPSN {$school->npsn}) berhasil diterima. Akun admin Anda akan aktif setelah verifikasi NPSN oleh tim Jurnalin."
        );
    }

    public function sitemap(): \Illuminate\Http\Response
    {
        $urls = collect(['home', 'schools.register', 'terms', 'privacy', 'contact'])->map(fn (string $r) => route($r));

        return response()->view('public.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            return redirect($user->role->homePath());
        }

        return view('public.login', [
            'demoAccounts' => app()->environment('local', 'testing') ? $this->demoAccounts() : [],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        // Email disimpan huruf kecil; PostgreSQL membandingkan teks secara case-sensitive.
        $credentials['email'] = strtolower($credentials['email']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau kata sandi tidak cocok.']);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Akun ini sedang dinonaktifkan.']);
        }

        if ($user->role !== UserRole::SuperAdmin) {
            $school = $user->school;
            if ($school === null || ! $school->isActive()) {
                $statusLabel = $school?->status->label() ?? 'Tidak ditemukan';
                Auth::logout();

                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => "Sekolah Anda berstatus {$statusLabel} dan belum dapat digunakan."]);
            }
        }

        TenantContext::runForUser($user, fn () => $user->forceFill(['last_login_at' => now()])->save());
        $request->session()->regenerate();

        return redirect()->intended($user->role->homePath());
    }

    public function demoLogin(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local', 'testing'), 404);

        $email = $request->string('email')->toString();
        $user = TenantContext::withAuthLookup($email, null, fn (): User => User::query()->where('email', $email)->firstOrFail());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->role->homePath());
    }

    public function passwordForm(Request $request): View
    {
        return view('public.password', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();
        TenantContext::runForUser($user, fn () => $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save());

        return redirect($user->role->homePath())->with('status', 'Kata sandi berhasil diganti.');
    }

    public function forgotPasswordForm(): View
    {
        return view('public.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $email = strtolower($request->validate(['email' => ['required', 'email']])['email']);

        // Pesan sama untuk email terdaftar atau tidak: tidak membocorkan siapa saja penggunanya.
        Password::sendResetLink(['email' => $email]);

        return back()->with('status', 'Bila email tersebut terdaftar, tautan reset kata sandi sudah dikirim. Tautan berlaku 60 menit.');
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('public.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $validated['email'] = strtolower($validated['email']);

        $status = Password::reset($validated, function (User $user, string $password): void {
            TenantContext::runForUser($user, fn () => $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'remember_token' => Str::random(60),
            ])->save());
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        return redirect('/login')->with('status', 'Kata sandi berhasil diganti. Silakan masuk dengan kata sandi baru.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        TenantContext::clear();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Anda telah keluar dari sesi Jurnalin.');
    }

    /**
     * @return list<array{label:string, role:string, school:string, email:string}>
     */
    private function demoAccounts(): array
    {
        $emails = [
            ['label' => 'Admin Platform Jurnalin', 'role' => 'Admin', 'school' => 'Lintas Semua Sekolah', 'email' => 'superadmin@jurnalin.id'],
            ['label' => 'Admin Hubin SMKN 1 Bandung', 'role' => 'Admin Sekolah', 'school' => 'SMKN 1 Pariwisata Bandung', 'email' => 'admin@smkn1par-bdg.sch.id'],
            ['label' => 'Guru Pembimbing (Perhotelan)', 'role' => 'Guru Pembimbing', 'school' => 'SMKN 1 Pariwisata Bandung', 'email' => 'guru.perhotelan@smkn1par-bdg.sch.id'],
            ['label' => 'Siswa Nadia (Belum Absen Hari Ini)', 'role' => 'Siswa PKL', 'school' => 'SMKN 1 Pariwisata Bandung', 'email' => 'siswa.nadia@smkn1par-bdg.sch.id'],
            ['label' => 'Siswa Bagas (Jurnal Menunggu Validasi)', 'role' => 'Siswa PKL', 'school' => 'SMKN 1 Pariwisata Bandung', 'email' => 'siswa.bagas@smkn1par-bdg.sch.id'],
            ['label' => 'Siswa Citra (Revisi Jurnal & Nilai Final)', 'role' => 'Siswa PKL', 'school' => 'SMKN 1 Pariwisata Bandung', 'email' => 'siswa.citra@smkn1par-bdg.sch.id'],
            ['label' => 'Admin SMK Teknologi Harapan Bali (Tenant B, RPL)', 'role' => 'Admin Sekolah', 'school' => 'SMK Teknologi Harapan Bali', 'email' => 'admin@smkharapan-bali.sch.id'],
        ];

        $existingEmails = TenantContext::runAsSuperAdmin(
            fn (): array => User::query()->whereIn('email', array_column($emails, 'email'))->pluck('email')->all()
        );

        return array_values(array_filter(
            $emails,
            fn (array $item): bool => in_array($item['email'], $existingEmails, true)
        ));
    }
}
