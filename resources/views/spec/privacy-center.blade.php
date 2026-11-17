@extends($layout, ['title' => 'Privasi Saya - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Privasi saya</h1>
        <p>Data apa yang dipakai, untuk apa, dan bagaimana meminta salinan, koreksi, atau penghapusan. Sekolah adalah pengendali data; Jurnalin pemroses data (UU No. 27 Tahun 2022).</p>
    </div>
</div>

<div class="stack">
    <section class="card stack-sm">
        <h2 class="section-title">Tujuan pemrosesan</h2>
        <ul class="legal-list">
            <li><strong>Jurnal & presensi</strong>: bukti kegiatan PKL untuk bimbingan, nilai, dan laporan sekolah.</li>
            <li><strong>Lokasi & swafoto</strong>: hanya saat presensi, bila sekolah mengaktifkan dan (untuk siswa di bawah 18 tahun) wali menyetujui. Tidak ada pengenalan wajah dan pelacakan terus-menerus.</li>
            <li><strong>Foto kegiatan & Bukti Kerja</strong>: privat secara bawaan, hanya dibagikan bila Anda memilihnya.</li>
        </ul>
    </section>

    @if($student)
        <section class="card stack-sm">
            <h2 class="section-title">Persetujuan orang tua/wali</h2>
            @if(! $student->isMinor())
                <p class="callout callout-ok">Anda berusia 18 tahun atau lebih. Persetujuan wali tidak diperlukan.</p>
            @elseif($consent)
                <p class="callout callout-ok">Tercatat: {{ $consent->guardian_name ?: 'wali' }} ({{ ['paper' => 'formulir kertas', 'whatsapp' => 'WhatsApp', 'digital' => 'tautan digital'][$consent->method] ?? $consent->method }}) pada {{ $consent->given_at->format('d/m/Y') }}.</p>
            @else
                <p class="callout callout-warn">Belum ada persetujuan wali. Absen tetap bisa dengan tombol biasa; lokasi dan swafoto tidak diambil.</p>
            @endif
        </section>
    @endif

    <section class="card stack-sm">
        <h2 class="section-title">Salinan data saya</h2>
        <p class="hint">Unduh profil, jurnal, dan presensi Anda dalam format CSV. Gratis, tanpa paket berbayar.</p>
        <a href="{{ route('privacy.export') }}" class="btn btn-outline">Unduh data saya (CSV)</a>
    </section>

    <section class="card stack-sm">
        <h2 class="section-title">Pesan WhatsApp otomatis</h2>
        <form method="POST" action="{{ route('privacy.whatsapp') }}" class="flex flex-wrap items-center gap-3">
            @csrf
            <span>{{ $user->wa_opt_out ? 'Saat ini Anda berhenti berlangganan.' : 'Saat ini Anda menerima pengingat WhatsApp bila sekolah mengaktifkannya.' }}</span>
            <button name="subscribe" value="{{ $user->wa_opt_out ? 1 : 0 }}" class="btn btn-outline btn-sm">{{ $user->wa_opt_out ? 'Aktifkan lagi' : 'Berhenti berlangganan' }}</button>
        </form>
    </section>

    <form method="POST" action="{{ route('privacy.request') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Ajukan permintaan</h2>
        <div>
            <label class="label" for="kind">Jenis permintaan</label>
            <select id="kind" name="kind" class="field">
                <option value="access">Akses data</option>
                <option value="correction">Koreksi data</option>
                <option value="deletion">Penghapusan data</option>
                <option value="withdraw">Tarik persetujuan</option>
            </select>
        </div>
        <div><label class="label" for="details">Keterangan</label><textarea id="details" name="details" class="field" required minlength="10"></textarea></div>
        <button class="btn btn-navy">Kirim ke admin sekolah</button>
    </form>

    @if($requests->isNotEmpty())
        <section class="stack-sm">
            <h2 class="section-title">Riwayat permintaan</h2>
            @foreach($requests as $r)
                <article class="card">
                    <div class="flex justify-between gap-2"><span class="font-semibold">{{ ['access' => 'Akses', 'correction' => 'Koreksi', 'deletion' => 'Penghapusan', 'withdraw' => 'Tarik persetujuan'][$r->kind] }}</span>
                        <span class="badge {{ $r->status === 'open' ? 'badge-warn' : ($r->status === 'done' ? 'badge-ok' : 'badge-danger') }}">{{ ['open' => 'Diproses', 'done' => 'Selesai', 'rejected' => 'Ditolak'][$r->status] }}</span></div>
                    <p class="hint">{{ $r->details }}</p>
                    @if($r->response)<p class="callout mt-2">{{ $r->response }}</p>@endif
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
