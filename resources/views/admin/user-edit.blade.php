@extends('layouts.app', ['title' => 'Ubah Data '.$managed->name.' - Jurnalin'])

@section('content')
<div class="max-w-xl space-y-6">
    <div>
        <a href="{{ $managed->role === \App\Enums\UserRole::Teacher ? '/admin/teachers' : '/admin/students' }}" class="document-link text-sm">Kembali ke daftar {{ $managed->role === \App\Enums\UserRole::Teacher ? 'guru' : 'siswa' }}</a>
        <h1 class="text-2xl font-bold text-ink mt-2">Ubah data {{ $managed->name }}</h1>
        <p class="text-sm text-muted mt-1">{{ $managed->role->label() }} · Status: {{ $managed->is_active ? 'Aktif' : 'Nonaktif' }}. Perubahan email langsung berlaku untuk login berikutnya.</p>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $managed) }}" class="rounded-xl border border-border bg-stone p-6 space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-semibold mb-1">Nama lengkap</label>
            <input id="name" name="name" required maxlength="120" value="{{ old('name', $managed->name) }}" class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <div>
            <label for="email" class="block text-sm font-semibold mb-1">Email (dipakai untuk login)</label>
            <input id="email" name="email" type="email" required maxlength="150" value="{{ old('email', $managed->email) }}" class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="block text-sm font-semibold mb-1">Nomor WhatsApp <span class="font-normal text-muted">(opsional)</span></label>
            <input id="phone" name="phone" inputmode="tel" maxlength="24" value="{{ old('phone', $managed->phone) }}" class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>
        <button type="submit" class="px-5 py-3 rounded-lg bg-navy hover:bg-navy-strong text-white font-semibold text-sm min-h-11">Simpan perubahan</button>
    </form>
</div>
@endsection
