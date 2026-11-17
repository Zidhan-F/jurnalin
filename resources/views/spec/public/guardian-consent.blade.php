@extends('spec.public.layout')
@section('title', 'Persetujuan orang tua/wali')
@section('context', 'Persetujuan orang tua/wali')

@section('content')
<div class="mentor-intro">
    <p class="hint">{{ $consent->school->name }}</p>
    <h1>Persetujuan data PKL untuk {{ $consent->student->user->name }}</h1>
</div>

@if($done)
    <div class="mentor-card {{ $consent->given_at ? 'callout-ok' : '' }}">
        @if($consent->given_at)
            <p class="font-semibold">Terima kasih. Persetujuan tercatat pada {{ $consent->given_at->timezone($consent->school->timezone)->format('d/m/Y H:i') }}.</p>
            <p class="mt-2">Anda dapat mencabut persetujuan kapan saja dengan menghubungi guru pembimbing atau admin sekolah.</p>
        @else
            <p class="font-semibold">Terima kasih. Pilihan Anda untuk tidak memberi persetujuan sudah tercatat.</p>
            <p class="mt-2">Siswa tetap dapat presensi dengan tombol biasa tanpa lokasi dan swafoto.</p>
        @endif
    </div>
@else
    <section class="mentor-card stack-sm">
        <h2 class="section-title">Pemberitahuan privasi (versi {{ $consent->notice_version }})</h2>
        <p>Sekolah memakai Jurnalin untuk mencatat jurnal dan presensi PKL. Sekolah adalah pengendali data, Jurnalin adalah pemroses data, sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>
        <ul class="legal-list">
            <li><strong>Lokasi</strong> hanya diambil saat siswa menekan tombol presensi, tidak dilacak terus-menerus, dan hanya untuk memeriksa jarak ke tempat PKL.</li>
            <li><strong>Swafoto</strong> diambil dari kamera saat presensi, tidak diproses untuk pengenalan wajah, dan dihapus sesuai masa simpan sekolah.</li>
            <li>Tanda lokasi tidak otomatis menjadi sanksi. Keputusan tetap pada guru pembimbing.</li>
            <li>Tanpa persetujuan, siswa tetap bisa presensi dengan tombol biasa.</li>
        </ul>
    </section>

    <form method="POST" action="{{ route('consent.submit', $token) }}" class="mentor-card mentor-sign">
        @csrf
        <div><label class="label" for="guardian_name">Nama orang tua/wali</label><input id="guardian_name" name="guardian_name" class="field" required value="{{ old('guardian_name', $consent->student->guardian_name) }}" autocomplete="name"></div>
        <div><label class="label" for="relation">Hubungan dengan siswa</label><input id="relation" name="relation" class="field" required placeholder="Contoh: Ayah, Ibu, Wali" value="{{ old('relation') }}"></div>
        <label class="mentor-check"><input type="checkbox" name="covers_location" value="1" checked> Saya mengizinkan lokasi saat presensi</label>
        <label class="mentor-check"><input type="checkbox" name="covers_selfie" value="1" checked> Saya mengizinkan swafoto saat presensi</label>
        <button name="decision" value="agree" class="mentor-submit">Saya setuju</button>
        <button name="decision" value="decline" class="btn btn-outline">Saya tidak setuju</button>
    </form>
@endif
@endsection
