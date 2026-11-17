@extends('layouts.app', ['title' => 'Ganti Kata Sandi - Jurnalin'])

@section('content')
<div class="max-w-md mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Ganti Kata Sandi</h1>
        <p class="text-sm text-muted mt-1">
            @if($forced)
                Akun Anda masih memakai kata sandi awal dari sekolah. Ganti dulu sebelum melanjutkan.
            @else
                Masukkan kata sandi lama, lalu kata sandi baru minimal 8 karakter.
            @endif
        </p>
    </div>

    <form method="POST" action="/password" class="rounded-xl border border-border bg-stone p-6 space-y-4">
        @csrf
        <div>
            <label for="current_password" class="block text-sm font-semibold mb-1">Kata Sandi Lama</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold mb-1">Kata Sandi Baru</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold mb-1">Ulangi Kata Sandi Baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <button type="submit" class="w-full py-3 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold min-h-12 cursor-pointer">
            Simpan Kata Sandi Baru
        </button>
    </form>
</div>
@endsection
