@extends('layouts.student-pwa', ['title' => 'Paspor Kompetensi - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Paspor Kompetensi</h1>
        <p>E-portofolio dari PKL: pengalaman, Bukti Kerja, capaian TP, nilai, sertifikat, dan hasil UKK. Privat sampai Anda menerbitkannya. Anda bisa mencabut tautan kapan saja.</p>
    </div>
</div>

@unless($enabled)
    <p class="callout callout-warn">Paspor Kompetensi belum diaktifkan sekolah Anda.</p>
@else
    @if($url)
        <section class="card stack-sm text-center">
            <img src="{{ $qr }}" alt="Kode QR paspor kompetensi" class="mx-auto" width="140" height="140">
            <input class="field tabular-nums" readonly value="{{ $url }}" aria-label="Tautan paspor" onfocus="this.select()">
            <p class="hint">Berlaku sampai {{ $passport->expires_at?->format('d/m/Y') }}.</p>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('reports.passport') }}" class="btn btn-outline">Unduh PDF</a>
                <form method="POST" action="{{ route('student.passport.revoke') }}">@csrf<button class="btn btn-danger btn-block">Cabut tautan</button></form>
            </div>
        </section>
    @endif

    <form method="POST" action="{{ route('student.passport.update') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Pilih yang ditampilkan</h2>
        @foreach(['proofs' => 'Bukti Kerja yang sudah ditinjau dan Anda izinkan', 'objectives' => 'Capaian TP terverifikasi', 'grade' => 'Nilai akhir PKL (hanya bila final)', 'certificates' => 'Sertifikat PKL dan surat UKK', 'ukk' => 'Hasil UKK final', 'journals' => 'Contoh judul jurnal yang disetujui', 'readiness' => 'Pembekalan pra-PKL yang lulus'] as $key => $label)
            <label class="flex items-center gap-2"><input type="checkbox" name="show_{{ $key }}" value="1" @checked($settings[$key] ?? false)> {{ $label }}</label>
        @endforeach
        <div><label class="label" for="about">Tentang saya (klaim pribadi)</label><textarea id="about" name="about" class="field" maxlength="600">{{ $settings['about'] ?? '' }}</textarea></div>
        <div><label class="label" for="expires_days">Masa berlaku tautan (hari)</label><input id="expires_days" name="expires_days" type="number" min="7" max="365" value="90" class="field tabular-nums"></div>
        <p class="hint">Refleksi pribadi, foto, dan dokumen perusahaan tidak pernah ditampilkan.</p>
        <div class="grid grid-cols-2 gap-2">
            <button class="btn btn-outline">Simpan pengaturan</button>
            <button name="publish" value="1" class="btn btn-primary">{{ $url ? 'Simpan & perbarui' : 'Terbitkan tautan' }}</button>
        </div>
    </form>

    <h2 class="section-title">Pratinjau</h2>
    @include('spec.partials.passport-body', ['data' => $data])
@endunless
@endsection
