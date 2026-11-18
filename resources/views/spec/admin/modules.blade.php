@extends('layouts.app', ['title' => 'Modul & Alur Sekolah - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Modul & alur sekolah</h1>
        <p>Jurnal, presensi, review, nilai akhir dasar, dan PDF standar selalu tersedia. Modul tambahan diaktifkan oleh tim Jurnalin sesuai paket. Menonaktifkan modul tidak menghapus data.</p>
    </div>
</div>

<div class="split">
    <div class="stack">
        <section class="card stack-sm">
            <h2 class="section-title">Modul inti (selalu aktif)</h2>
            <div class="menu-grid">
                <a href="{{ route('admin.import') }}">Impor data <small>Excel & Dapodik</small></a>
                <a href="{{ route('admin.objectives') }}">Tujuan Pembelajaran <small>Katalog TP sekolah</small></a>
                <a href="{{ route('visits.index') }}">Kunjungan <small>Monitoring PKL</small></a>
                <a href="{{ route('tickets.inbox') }}">Laporan siswa <small>Lapor ke Guru</small></a>
                <a href="{{ route('monitor.alerts') }}">Pemantauan pintar <small>Peringatan tindak lanjut</small></a>
                <a href="{{ route('admin.readiness') }}">Pembekalan pra-PKL <small>Modul & kuis</small></a>
                <a href="{{ route('admin.privacy') }}">Privasi & UU PDP <small>Persetujuan wali, DPA</small></a>
            </div>
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Paket Sekolah Pro</h2>
            @foreach($pro as $key => $f)
                <div class="flex justify-between gap-2 border-t border-border pt-2"><span>{{ $f['label'] }}</span><span class="badge {{ $f['enabled'] ? 'badge-ok' : '' }}">{{ $f['enabled'] ? 'Aktif' : 'Belum aktif' }}</span></div>
            @endforeach
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Add-on</h2>
            @foreach($addons as $key => $f)
                <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-2 items-center">
                    <span>{{ $f['label'] }}</span>
                    <span class="badge {{ $f['enabled'] ? 'badge-ok' : '' }}">{{ $f['enabled'] ? 'Aktif' : 'Belum aktif' }}</span>
                </div>
            @endforeach
            <p class="hint">Hubungi tim Jurnalin melalui halaman Kontak untuk mengaktifkan add-on atau uji coba.</p>
        </section>
    </div>

    <form method="POST" action="{{ route('admin.whatsapp') }}" class="card-muted stack-sm">
        @csrf
        <h2 class="section-title">Pengaturan alur</h2>
        <div>
            <label class="label" for="journal_approver">Penyetuju jurnal</label>
            <select id="journal_approver" name="journal_approver" class="field">
                <option value="teacher" @selected($school->journal_approver === 'teacher')>Guru pembimbing saja</option>
                <option value="either" @selected($school->journal_approver === 'either')>Guru atau pembimbing lapangan (akses pembimbing industri)</option>
                <option value="mentor" @selected($school->journal_approver === 'mentor')>Pembimbing lapangan (guru tetap bisa)</option>
            </select>
        </div>
        <div><label class="label" for="max_journal_photos">Maksimal foto per jurnal</label><input id="max_journal_photos" name="max_journal_photos" type="number" min="1" max="10" class="field tabular-nums" value="{{ $school->max_journal_photos }}"></div>
        <label class="flex items-start gap-2"><input type="checkbox" name="wa_notifications" value="1" class="mt-1" @checked($school->wa_notifications) @disabled(! $school->hasFeature('whatsapp'))> Kirim ringkasan harian ke guru dan pengingat jurnal ke siswa lewat WhatsApp otomatis (perlu add-on WhatsApp; penerima dapat berhenti berlangganan)</label>
        <button class="btn btn-navy">Simpan pengaturan</button>
    </form>
</div>
@endsection
