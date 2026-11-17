@extends('layouts.app', ['title' => 'Lupa Kata Sandi | Jurnalin'])

@section('content')
<div class="max-w-md mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Lupa kata sandi</h1>
        <p class="text-sm text-muted mt-1">Masukkan email akun Anda. Kami kirim tautan untuk membuat kata sandi baru. Tidak punya akses ke email itu? Minta admin sekolah mereset kata sandi Anda.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="rounded-xl border border-border bg-stone p-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-semibold mb-1">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username" value="{{ old('email') }}" placeholder="email@sekolah.sch.id"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <button type="submit" class="w-full py-3 px-4 rounded-lg bg-navy hover:bg-navy-strong text-white font-semibold min-h-12 cursor-pointer">
            Kirim tautan reset
        </button>
        <div class="text-center text-sm"><a class="document-link" href="{{ route('login') }}">Kembali ke halaman masuk</a></div>
    </form>
</div>
@endsection
