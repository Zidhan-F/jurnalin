@extends('layouts.app', ['title' => 'Masuk Akun - Jurnalin'])

@section('content')
<div class="max-w-md mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Masuk ke Jurnalin</h1>
        <p class="text-sm text-muted mt-1">Gunakan email dan kata sandi akun sekolah, guru pembimbing, atau siswa PKL.</p>
    </div>

    <form method="POST" action="/login" class="rounded-xl border border-border bg-stone p-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-semibold mb-1">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username" value="{{ old('email') }}" placeholder="email@sekolah.sch.id"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold mb-1">Kata Sandi</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div class="text-right -mt-2"><a class="document-link text-sm" href="{{ route('password.request') }}">Lupa kata sandi?</a></div>
        <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" value="1" class="rounded border-border">
            <span>Tetap masuk di perangkat ini</span>
        </label>
        <button type="submit" class="w-full py-3 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold min-h-12 cursor-pointer">
            Masuk ke Akun
        </button>
        <div class="text-center text-xs text-muted pt-1">
            Sekolah belum terdaftar? <a href="/daftar-sekolah" class="text-navy font-semibold underline">Daftarkan via NPSN</a>
        </div>
    </form>

    <form method="POST" action="{{ route('magic.request') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Masuk tanpa kata sandi</h2>
        <p class="hint">Kirim tautan masuk sekali pakai ke email akun Anda. Siswa juga bisa menerima tautan ini dari guru lewat WhatsApp.</p>
        <label for="magic-email" class="label">Email akun</label>
        <input id="magic-email" name="email" type="email" required autocomplete="username" class="field" placeholder="email@sekolah.sch.id">
        <button type="submit" class="btn btn-outline btn-block">Kirim tautan masuk</button>
    </form>

    @if(count($demoAccounts) > 0)
        <div class="rounded-xl border border-amber-warn/40 bg-paper p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="px-2 py-0.5 rounded-md bg-amber-warn text-white text-xs font-semibold">MODE DEMO LOKAL</span>
                <span class="text-xs text-muted tabular-nums">password123</span>
            </div>
            <div class="space-y-2">
                @foreach($demoAccounts as $acc)
                    <form method="POST" action="/demo-login" class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-stone border border-border">
                        @csrf
                        <input type="hidden" name="email" value="{{ $acc['email'] }}">
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-ink">{{ $acc['label'] }}</div>
                            <div class="text-sm text-muted">{{ $acc['role'] }} ({{ $acc['school'] }})</div>
                        </div>
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-navy text-white text-xs font-semibold shrink-0 cursor-pointer">
                            Masuk<span class="sr-only"> sebagai {{ $acc['label'] }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
