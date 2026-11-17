@extends('layouts.app', ['title' => 'Daftarkan Sekolah - Jurnalin'])

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Pendaftaran Sekolah Mandiri</h1>
        <p class="text-sm text-muted mt-1">
            Masukkan data resmi sekolah dan koordinator Hubin/PKL. Akun akan aktif setelah pencocokan NPSN pada data referensi Kemendikdasmen oleh Admin Jurnalin.
        </p>
    </div>

    <form method="POST" action="/daftar-sekolah" class="rounded-xl border border-border bg-stone p-6 space-y-5">
        @csrf

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="npsn" class="block text-sm font-semibold mb-1">NPSN (8 Digit)</label>
                <input id="npsn" name="npsn" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required value="{{ old('npsn') }}" placeholder="Contoh: 20219101"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm tabular-nums">
            </div>
            <div>
                <label for="timezone" class="block text-sm font-semibold mb-1">Zona Waktu Sekolah</label>
                <select id="timezone" name="timezone" class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
                    <option value="Asia/Jakarta" @selected(old('timezone') === 'Asia/Jakarta')>WIB (Asia/Jakarta)</option>
                    <option value="Asia/Makassar" @selected(old('timezone') === 'Asia/Makassar')>WITA (Asia/Makassar)</option>
                    <option value="Asia/Jayapura" @selected(old('timezone') === 'Asia/Jayapura')>WIT (Asia/Jayapura)</option>
                </select>
            </div>
        </div>

        <div>
            <label for="school_name" class="block text-sm font-semibold mb-1">Nama Resmi SMK</label>
            <input id="school_name" autocomplete="organization" name="school_name" type="text" required value="{{ old('school_name') }}" placeholder="Nama sekolah sesuai Dapodik"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>

        <div>
            <label for="address" class="block text-sm font-semibold mb-1">Alamat Lengkap Sekolah</label>
            <textarea id="address" autocomplete="street-address" name="address" rows="2" required placeholder="Jalan, nomor, kecamatan"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">{{ old('address') }}</textarea>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="city" class="block text-sm font-semibold mb-1">Kabupaten / Kota</label>
                <input id="city" autocomplete="address-level2" name="city" type="text" required value="{{ old('city') }}" placeholder="Contoh: Kota Bandung"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="province" class="block text-sm font-semibold mb-1">Provinsi</label>
                <input id="province" autocomplete="address-level1" name="province" type="text" required value="{{ old('province') }}" placeholder="Contoh: Jawa Barat"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
            </div>
        </div>

        <hr class="border-border">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="admin_name" class="block text-sm font-semibold mb-1">Nama Koordinator Hubin / Admin</label>
                <input id="admin_name" autocomplete="name" name="admin_name" type="text" required value="{{ old('admin_name') }}" placeholder="Nama lengkap admin"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="admin_phone" class="block text-sm font-semibold mb-1">Nomor WhatsApp Admin</label>
                <input id="admin_phone" autocomplete="tel" name="admin_phone" type="tel" inputmode="numeric" required value="{{ old('admin_phone') }}" placeholder="62812..."
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm tabular-nums">
            </div>
        </div>

        <div>
            <label for="admin_email" class="block text-sm font-semibold mb-1">Email Login Admin Sekolah</label>
            <input id="admin_email" autocomplete="email" name="admin_email" type="email" required value="{{ old('admin_email') }}" placeholder="email@sekolah.sch.id"
                class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-sm font-semibold mb-1">Kata Sandi (Min. 8 Karakter)</label>
                <input id="password" autocomplete="new-password" name="password" type="password" required minlength="8"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold mb-1">Ulangi Kata Sandi</label>
                <input id="password_confirmation" autocomplete="new-password" name="password_confirmation" type="password" required minlength="8"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2.5 text-sm">
            </div>
        </div>

        <label class="flex gap-3 items-start text-sm text-ink">
            <input type="checkbox" name="accept_terms" value="1" required @checked(old('accept_terms')) class="mt-1">
            <span>Saya menyetujui <a class="document-link" href="{{ route('terms') }}" target="_blank">Syarat Layanan</a> dan <a class="document-link" href="{{ route('privacy') }}" target="_blank">Kebijakan Privasi</a>, dan menyatakan sekolah berwenang memproses data siswa, termasuk persetujuan orang tua atau wali bagi siswa di bawah umur.</span>
        </label>
        @error('accept_terms')<p class="field-error">{{ $message }}</p>@enderror

        <button type="submit" class="w-full py-3 px-5 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold min-h-12 cursor-pointer">
            Kirim Pendaftaran Sekolah
        </button>
    </form>
</div>
@endsection
