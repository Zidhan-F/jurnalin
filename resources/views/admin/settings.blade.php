@extends('layouts.app', ['title' => 'Pengaturan Sekolah & Kop Surat PDF - Jurnalin'])

@section('content')
<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Pengaturan Kebijakan PKL &amp; Kop Surat PDF</h1>
        <p class="text-sm text-muted mt-1">Aturan absensi dan identitas resmi yang dicetak di bagian atas Buku Jurnal serta Rekap Nilai PKL.</p>
    </div>

    <form method="POST" action="/admin/settings" enctype="multipart/form-data" class="rounded-xl border border-border bg-stone p-6 space-y-5">
        @csrf
        @php $cfg = $school->letterhead_config ?? []; @endphp

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1" for="name">Nama Resmi Sekolah</label>
                <input id="name" name="name" type="text" required value="{{ old('name', $school->name) }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="timezone">Zona Waktu</label>
                <select id="timezone" name="timezone" class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                    <option value="Asia/Jakarta" @selected($school->timezone === 'Asia/Jakarta')>WIB (Asia/Jakarta)</option>
                    <option value="Asia/Makassar" @selected($school->timezone === 'Asia/Makassar')>WITA (Asia/Makassar)</option>
                    <option value="Asia/Jayapura" @selected($school->timezone === 'Asia/Jayapura')>WIT (Asia/Jayapura)</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1" for="address">Alamat Sekolah</label>
            <input id="address" name="address" type="text" required value="{{ old('address', $school->address) }}"
                class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1" for="city">Kabupaten / Kota</label>
                <input id="city" name="city" type="text" required value="{{ old('city', $school->city) }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="province">Provinsi</label>
                <input id="province" name="province" type="text" required value="{{ old('province', $school->province) }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
        </div>

        <hr class="border-border">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1" for="default_geofence_radius_m">Jarak absen bawaan (meter) <span class="block font-normal text-muted text-xs mt-0.5">Dipakai bila tempat PKL tidak punya jarak sendiri.</span></label>
                <input id="default_geofence_radius_m" name="default_geofence_radius_m" type="number" min="30" max="2000" required value="{{ old('default_geofence_radius_m', $school->default_geofence_radius_m) }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="late_tolerance_minutes">Batas terlambat (menit) <span class="block font-normal text-muted text-xs mt-0.5">Absen setelah jam masuk ditambah menit ini dihitung terlambat.</span></label>
                <input id="late_tolerance_minutes" name="late_tolerance_minutes" type="number" min="0" max="120" required value="{{ old('late_tolerance_minutes', $school->late_tolerance_minutes) }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
            </div>
        </div>

        <div class="space-y-2 text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="require_attendance_photo" value="1" @checked($school->require_attendance_photo)>
                <span>Wajibkan foto kamera saat siswa melakukan absen masuk</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="require_industry_form_photo" value="1" @checked($school->require_industry_form_photo)>
                <span>Wajibkan foto bukti lembar penilaian dari DUDI sebelum guru memfinalisasi nilai akhir</span>
            </label>
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1" for="mentor_signoff_frequency">Tanda tangan pembimbing DUDI</label>
            <select id="mentor_signoff_frequency" name="mentor_signoff_frequency" class="w-full sm:w-auto rounded-md border border-field bg-paper px-3 py-2 text-sm">
                @foreach(['daily' => 'Per hari', 'weekly' => 'Per minggu (Senin sampai Minggu)', 'monthly' => 'Per bulan'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('mentor_signoff_frequency', $school->mentor_signoff_frequency) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-muted mt-1">Pembimbing di tempat PKL menandatangani jurnal siswa sekali untuk tiap rentang ini, lewat tautan yang dikirim guru.</p>
        </div>

        <hr class="border-border">

        <div class="space-y-3">
            <h2 class="text-base font-bold text-ink">Kop Surat &amp; Pejabat Penandatangan PDF</h2>
            <div>
                <label class="block text-sm font-semibold mb-1" for="header_line_1">Baris 1 Kop (Dinas / Yayasan)</label>
                <input id="header_line_1" name="header_line_1" type="text" value="{{ old('header_line_1', $cfg['header_line_1'] ?? '') }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="header_line_2">Baris 2 Kop (Nama Sekolah)</label>
                <input id="header_line_2" name="header_line_2" type="text" value="{{ old('header_line_2', $cfg['header_line_2'] ?? '') }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="sub_header">Baris Alamat / Telepon Kop</label>
                <input id="sub_header" name="sub_header" type="text" value="{{ old('sub_header', $cfg['sub_header'] ?? '') }}"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1" for="principal_name">Nama Kepala Sekolah</label>
                    <input id="principal_name" name="principal_name" type="text" value="{{ old('principal_name', $cfg['principal_name'] ?? '') }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="principal_nip">NIP Kepala Sekolah</label>
                    <input id="principal_nip" name="principal_nip" type="text" value="{{ old('principal_nip', $cfg['principal_nip'] ?? '') }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="coordinator_name">Koordinator PKL / Hubin</label>
                    <input id="coordinator_name" name="coordinator_name" type="text" value="{{ old('coordinator_name', $cfg['coordinator_name'] ?? '') }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="logo">Logo Sekolah (PNG/JPG, maks 2 MB)</label>
                @if($school->logo_path)
                    <img src="/media/{{ $school->logo_path }}" alt="Logo sekolah" class="h-14 mb-2 rounded-md border border-field bg-white p-1">
                @endif
                <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-xs text-muted">
            </div>
        </div>

        <button type="submit" class="px-6 py-3 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
            Simpan Pengaturan Sekolah
        </button>
    </form>

    <section class="rounded-xl border border-border bg-stone p-5 space-y-4" aria-labelledby="judul-data">
        <div>
            <h2 id="judul-data" class="text-lg font-bold text-ink">Data &amp; privasi</h2>
            <p class="text-sm text-muted mt-1">Data milik sekolah. Unduh semuanya kapan saja dalam bentuk CSV (bisa dibuka di Excel), atau ajukan penghapusan.</p>
        </div>
        <a href="{{ route('admin.export') }}" class="inline-flex items-center px-4 py-2.5 rounded-lg bg-navy hover:bg-navy-strong text-white text-sm font-semibold min-h-11">Unduh semua data (ZIP)</a>

        <div class="border-t border-border pt-4">
            @if($school->deletion_requested_at)
                <p class="text-sm font-semibold text-danger-strong">Penghapusan diajukan pada {{ $school->deletion_requested_at->timezone($school->timezone)->format('d/m/Y H:i') }}. Data dihapus permanen paling lambat {{ $school->deletion_requested_at->addDays(30)->timezone($school->timezone)->format('d/m/Y') }}.</p>
                <form method="POST" action="{{ route('admin.deletion.cancel') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="document-link text-sm">Batalkan pengajuan penghapusan</button>
                </form>
            @else
                <h3 class="font-semibold text-ink">Hapus data sekolah</h3>
                <p class="text-sm text-muted mt-1">Semua akun, jurnal, foto, dan nilai sekolah ini dihapus permanen dalam 30 hari dan tidak bisa dipulihkan. Ketik NPSN <span class="tabular-nums font-semibold">{{ $school->npsn }}</span> untuk mengonfirmasi.</p>
                <form method="POST" action="{{ route('admin.deletion.request') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="confirm_npsn" class="block text-sm font-semibold mb-1">NPSN sekolah</label>
                        <input id="confirm_npsn" name="confirm_npsn" inputmode="numeric" autocomplete="off" required class="rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                    </div>
                    <button type="submit" class="px-4 py-2.5 rounded-lg border-2 border-danger text-danger-strong hover:bg-danger hover:text-white text-sm font-semibold min-h-11">Ajukan penghapusan</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
