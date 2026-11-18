@extends('layouts.app', ['title' => 'Privasi & UU PDP - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Privasi & kepatuhan UU PDP</h1>
        <p>Perjanjian pemrosesan data, persetujuan wali sebelum lokasi dan swafoto aktif, masa simpan, dan permintaan subjek data. Kebijakan final tetap perlu ditinjau penasihat hukum sekolah.</p>
    </div>
</div>

<div class="stack">
    <section class="card {{ $school->dpa_accepted_at ? '' : 'card-attention' }} stack-sm">
        <h2 class="section-title">Perjanjian pemrosesan data</h2>
        @if($school->dpa_accepted_at && $school->dpa_version === $dpaVersion)
            <p class="callout callout-ok">Disetujui versi {{ $school->dpa_version }} pada {{ $school->dpa_accepted_at->format('d/m/Y H:i') }}.</p>
        @else
            <p>Sekolah sebagai pengendali data menunjuk Jurnalin sebagai pemroses data untuk jurnal, presensi, nilai, dan dokumen PKL. Jurnalin hanya memproses data atas instruksi sekolah, menyimpan data di server dengan isolasi per sekolah, dan tidak memakai data siswa untuk pelatihan AI pihak ketiga.</p>
            <form method="POST" action="{{ route('admin.privacy.dpa') }}" class="stack-sm">
                @csrf
                <label class="flex items-start gap-2"><input type="checkbox" name="agree" value="1" class="mt-1"> Saya berwenang mewakili sekolah dan menyetujui perjanjian pemrosesan data versi {{ $dpaVersion }}.</label>
                <button class="btn btn-navy">Setujui perjanjian</button>
            </form>
        @endif
    </section>

    <section class="card stack-sm">
        <div class="flex flex-wrap justify-between gap-2">
            <h2 class="section-title">Persetujuan wali ({{ $students->count() - $missing->count() }}/{{ $students->count() }} siswa siap)</h2>
            <span class="hint">Siswa tanpa tanggal lahir dianggap di bawah 18 tahun.</span>
        </div>
        @if($missing->isEmpty())
            <p class="callout callout-ok">Semua siswa sudah dewasa atau persetujuan walinya tercatat.</p>
        @else
            <div class="overflow-x-auto" role="region" aria-label="Siswa tanpa persetujuan wali" tabindex="0">
                <table class="data-table">
                    <thead><tr><th>Siswa</th><th>Kelas</th><th>Catat persetujuan kertas / WhatsApp</th><th>Tautan digital</th></tr></thead>
                    <tbody>
                        @foreach($missing as $s)
                            <tr>
                                <td>{{ $s->user->name }}</td>
                                <td>{{ $s->class_name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('consents.record', $s) }}" enctype="multipart/form-data" class="grid sm:grid-cols-4 gap-2 items-end">
                                        @csrf
                                        <input name="guardian_name" class="field" placeholder="Nama wali" required aria-label="Nama wali {{ $s->user->name }}" value="{{ $s->guardian_name }}">
                                        <input name="relation" class="field" placeholder="Hubungan" required aria-label="Hubungan wali">
                                        <select name="method" class="field" aria-label="Metode"><option value="paper">Kertas</option><option value="whatsapp">WhatsApp</option></select>
                                        <button class="btn btn-navy btn-sm">Catat</button>
                                        <input type="file" name="evidence" accept="image/*" class="sm:col-span-4 text-sm" aria-label="Foto bukti formulir">
                                    </form>
                                </td>
                                <td><form method="POST" action="{{ route('consents.link', $s) }}">@csrf<button class="btn btn-outline btn-sm">Buat tautan</button></form></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card stack-sm">
        <h2 class="section-title">Masa simpan swafoto</h2>
        <form method="POST" action="{{ route('admin.privacy.retention') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div><label class="label" for="selfie_retention_days">Hari setelah presensi</label><input id="selfie_retention_days" name="selfie_retention_days" type="number" min="1" max="1825" class="field tabular-nums" value="{{ $school->selfie_retention_days }}"></div>
            <button class="btn btn-outline">Simpan</button>
        </form>
        <p class="hint">Bawaan 7 hari: swafoto hanya bukti sementara untuk ditinjau guru, lalu dihapus otomatis setiap malam oleh tugas <code>jurnalin:retensi</code>. Swafoto yang tandanya belum ditinjau guru disimpan sampai ditinjau. Data presensi (jam, status, jarak) tetap tersimpan.</p>
    </section>

    <section class="stack-sm">
        <h2 class="section-title">Permintaan subjek data</h2>
        @forelse($requests as $r)
            <article class="card {{ $r->status === 'open' ? 'card-attention' : '' }}">
                <div class="flex flex-wrap justify-between gap-2">
                    <strong>{{ $r->user->name }} · {{ ['access' => 'Akses', 'correction' => 'Koreksi', 'deletion' => 'Penghapusan', 'withdraw' => 'Tarik persetujuan'][$r->kind] }}</strong>
                    <span class="hint tabular-nums">{{ $r->created_at->format('d/m/Y') }}</span>
                </div>
                <p class="mt-1">{{ $r->details }}</p>
                @if($r->status === 'open')
                    <form method="POST" action="{{ route('admin.privacy.answer', $r) }}" class="grid sm:grid-cols-4 gap-2 mt-2 items-end">
                        @csrf
                        <input name="response" class="field sm:col-span-2" required placeholder="Jawaban untuk pemohon" aria-label="Jawaban">
                        <select name="status" class="field" aria-label="Status"><option value="done">Selesai</option><option value="rejected">Ditolak</option></select>
                        <button class="btn btn-navy">Kirim</button>
                    </form>
                @else
                    <p class="callout mt-2">{{ $r->response }}</p>
                @endif
            </article>
        @empty
            <div class="empty">Belum ada permintaan akses, koreksi, atau penghapusan data.</div>
        @endforelse
    </section>
</div>
@endsection
