@extends('layouts.app', ['title' => 'Buat Kata Sandi Baru | Jurnalin'])

@section('content')
<div class="max-w-md mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Buat kata sandi baru</h1>
        <p class="text-sm text-muted mt-1">Minimal 8 karakter. Setelah disimpan, masuk dengan kata sandi baru ini.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="rounded-xl border border-border bg-stone p-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="block text-sm font-semibold mb-1">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username" value="{{ old('email', $email) }}"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold mb-1">Kata sandi baru</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold mb-1">Ulangi kata sandi baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <button type="submit" class="w-full py-3 px-4 rounded-lg bg-navy hover:bg-navy-strong text-white font-semibold min-h-12 cursor-pointer">
            Simpan kata sandi baru
        </button>
    </form>
</div>
@endsection
