@extends('layouts.app', ['title' => 'Data & Impor Guru Pembimbing - Jurnalin'])

@section('content')
<div class="grid lg:grid-cols-12 gap-8 items-start">
    <div class="lg:col-span-5 space-y-6">
        <div class="rounded-xl border border-border bg-stone p-6 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-lg font-bold text-ink">Masukkan banyak guru dari Excel</h1>
                <a href="/admin/teachers/template" class="text-sm font-semibold text-emerald-strong underline inline-flex items-center min-h-11">Unduh contoh berkas Excel</a>
            </div>
            <p class="text-xs text-muted">
                Kolom wajib: <span class="tabular-nums">nama, email</span>. Kolom opsional: <span class="tabular-nums">nip_nik, no_wa, spesialisasi, maks_bimbingan</span>. Sandi awal otomatis: <span class="tabular-nums">password123</span>.
            </p>
            <form method="POST" action="/admin/teachers/import" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label for="import-file" class="block text-sm font-semibold">Berkas guru (.xlsx atau .csv)</label>
                <input id="import-file" type="file" name="file" accept=".xlsx,.csv" required class="block w-full text-xs text-muted file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy file:text-white file:font-semibold">
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                    Unggah &amp; Impor Guru
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-border bg-stone p-6 space-y-4">
            <h2 class="text-lg font-bold text-ink">Tambah satu guru</h2>
            <form method="POST" action="/admin/teachers" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1" for="name">Nama Lengkap &amp; Gelar</label>
                    <input id="name" name="name" type="text" required value="{{ old('name') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="email">Email Login</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="nip_nik">NIP / NIK</label>
                        <input id="nip_nik" name="nip_nik" type="text" value="{{ old('nip_nik') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="phone">No. WhatsApp</label>
                        <input id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="off" value="{{ old('phone') }}" placeholder="628..." class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="specialization">Jurusan / Spesialisasi</label>
                        <input id="specialization" name="specialization" type="text" list="program-keahlian-list" value="{{ old('specialization') }}" placeholder="Pilih atau ketik program keahlian" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="max_mentees">Maks. Bimbingan</label>
                        <input id="max_mentees" name="max_mentees" type="number" min="1" max="100" required value="{{ old('max_mentees', 20) }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-navy hover:bg-navy-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                    Simpan Guru
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 space-y-4">
        <h2 class="text-xl font-bold text-ink">Daftar Guru Pembimbing ({{ $teachers->count() }})</h2>
        @if($teachers->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
                Belum ada guru pembimbing terdaftar. Unduh template Excel atau tambahkan secara manual.
            </div>
        @else
            <div role="region" aria-label="Daftar guru pembimbing" tabindex="0" class="overflow-x-auto rounded-xl border border-border bg-paper">
                <table class="w-full text-sm text-left">
                    <thead class="bg-stone text-xs uppercase text-muted border-b border-border">
                        <tr>
                            <th class="px-4 py-3">Nama &amp; Kontak</th>
                            <th class="px-4 py-3">NIP / Spesialisasi</th>
                            <th class="px-4 py-3">Bimbingan Aktif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($teachers as $teacher)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $teacher->user->name }}</div>
                                    <div class="text-xs text-muted">{{ $teacher->user->email }} | {{ $teacher->user->phone ?: '-' }}</div>
                                    @unless($teacher->user->is_active)<span class="inline-block mt-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-stone border border-field text-ink">Nonaktif</span>@endunless
                                </td>
                                <td class="px-4 py-3 text-xs text-muted">
                                    <div class="tabular-nums">{{ $teacher->nip_nik ?: '-' }}</div>
                                    <div>{{ $teacher->specialization ?: 'Umum' }}</div>
                                </td>
                                <td class="px-4 py-3 tabular-nums text-xs">
                                    <strong>{{ $teacher->placements_count }}</strong> / {{ $teacher->max_mentees }} siswa
                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                        <a href="{{ route('admin.users.edit', $teacher->user) }}" class="document-link text-xs">Ubah data<span class="sr-only"> {{ $teacher->user->name }}</span></a>
                                        <form method="POST" action="/admin/users/{{ $teacher->user->id }}/reset-password" onsubmit="return confirm('Reset kata sandi {{ $teacher->user->name }} ke sandi awal? Pengguna wajib menggantinya saat login.')">
                                            @csrf
                                            <button type="submit" class="document-link text-xs">Reset sandi<span class="sr-only"> {{ $teacher->user->name }}</span></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.toggle-active', $teacher->user) }}" data-question="{{ $teacher->user->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }} akun {{ $teacher->user->name }}?" onsubmit="return window.confirm(this.dataset.question)">
                                            @csrf
                                            <button type="submit" class="document-link text-xs @if($teacher->user->is_active) text-danger-strong @endif">{{ $teacher->user->is_active ? 'Nonaktifkan' : 'Aktifkan lagi' }}<span class="sr-only"> {{ $teacher->user->name }}</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
