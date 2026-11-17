@extends('layouts.app', ['title' => 'Data & Impor Siswa PKL - Jurnalin'])

@section('content')
<div class="grid lg:grid-cols-12 gap-8 items-start">
    <div class="lg:col-span-5 space-y-6">
        <div class="rounded-xl border border-border bg-stone p-6 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-lg font-bold text-ink">Masukkan banyak siswa dari Excel</h1>
                <a href="/admin/students/template" class="text-sm font-semibold text-emerald-strong underline inline-flex items-center min-h-11">Unduh contoh berkas Excel</a>
            </div>
            <p class="text-xs text-muted">
                Kuota terpakai: <strong class="tabular-nums">{{ $students->count() }} / {{ $school->studentLimit() }}</strong> siswa. Kolom wajib: <span class="tabular-nums">nisn, nama, email, kelas</span>. Sandi awal: <span class="tabular-nums">password123</span>.
            </p>
            <form method="POST" action="/admin/students/import" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label for="import-file" class="block text-sm font-semibold">Berkas siswa (.xlsx atau .csv)</label>
                <input id="import-file" type="file" name="file" accept=".xlsx,.csv" required class="block w-full text-xs text-muted file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy file:text-white file:font-semibold">
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                    Unggah &amp; Impor Siswa
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-border bg-stone p-6 space-y-4">
            <h2 class="text-lg font-bold text-ink">Tambah satu siswa</h2>
            <form method="POST" action="/admin/students" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="nisn">NISN</label>
                        <input id="nisn" name="nisn" type="text" required value="{{ old('nisn') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="nis">NIS</label>
                        <input id="nis" name="nis" type="text" value="{{ old('nis') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="name">Nama Lengkap Siswa</label>
                    <input id="name" name="name" type="text" required value="{{ old('name') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="email">Email Login</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="class_name">Kelas</label>
                        <input id="class_name" name="class_name" type="text" required value="{{ old('class_name') }}" placeholder="Contoh: XII RPL 1" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="major">Program keahlian</label>
                        <input id="major" name="major" type="text" required list="program-keahlian-list" value="{{ old('major') }}" placeholder="Pilih atau ketik program keahlian" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="phone">No. WA Siswa</label>
                        <input id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="off" value="{{ old('phone') }}" placeholder="628..." class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="parent_phone">No. WA Orang Tua</label>
                        <input id="parent_phone" name="parent_phone" type="tel" inputmode="numeric" autocomplete="off" value="{{ old('parent_phone') }}" placeholder="628..." class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-navy hover:bg-navy-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                    Simpan Siswa
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 space-y-4">
        <h2 class="text-xl font-bold text-ink">Daftar Siswa Terdaftar ({{ $students->count() }})</h2>
        @if($students->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
                Belum ada siswa terdaftar. Impor data Excel siswa untuk memulai penempatan PKL.
            </div>
        @else
            <div role="region" aria-label="Daftar siswa terdaftar" tabindex="0" class="overflow-x-auto rounded-xl border border-border bg-paper">
                <table class="w-full text-sm text-left">
                    <thead class="bg-stone text-xs uppercase text-muted border-b border-border">
                        <tr>
                            <th class="px-4 py-3">NISN &amp; Nama</th>
                            <th class="px-4 py-3">Kelas &amp; Jurusan</th>
                            <th class="px-4 py-3">Kontak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($students as $student)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $student->user->name }}</div>
                                    <div class="text-xs text-muted tabular-nums">NISN: {{ $student->nisn }} | {{ $student->user->email }}</div>
                                    @unless($student->user->is_active)<span class="inline-block mt-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-stone border border-field text-ink">Nonaktif</span>@endunless
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div class="font-medium">{{ $student->class_name }}</div>
                                    <div class="text-muted">{{ $student->major }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs tabular-nums text-muted">
                                    <div>Siswa: {{ $student->user->phone ?: '-' }}</div>
                                    <div>Ortu: {{ $student->parent_phone ?: '-' }}</div>
                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                        <a href="{{ route('admin.users.edit', $student->user) }}" class="document-link text-xs">Ubah data<span class="sr-only"> {{ $student->user->name }}</span></a>
                                        <form method="POST" action="/admin/users/{{ $student->user->id }}/reset-password" onsubmit="return confirm('Reset kata sandi {{ $student->user->name }} ke sandi awal? Pengguna wajib menggantinya saat login.')">
                                            @csrf
                                            <button type="submit" class="document-link text-xs">Reset sandi<span class="sr-only"> {{ $student->user->name }}</span></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.toggle-active', $student->user) }}" data-question="{{ $student->user->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }} akun {{ $student->user->name }}?" onsubmit="return window.confirm(this.dataset.question)">
                                            @csrf
                                            <button type="submit" class="document-link text-xs @if($student->user->is_active) text-danger-strong @endif">{{ $student->user->is_active ? 'Nonaktifkan' : 'Aktifkan lagi' }}<span class="sr-only"> {{ $student->user->name }}</span></button>
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
