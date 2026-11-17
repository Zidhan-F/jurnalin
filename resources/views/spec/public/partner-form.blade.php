@extends('spec.public.layout')
@section('title', 'Formulir mitra')
@section('context', 'Formulir mitra industri')

@section('content')
<div class="mentor-intro">
    <p class="hint">{{ $req->school->name }}</p>
    <h1>{{ $req->kind === 'feedback' ? 'Masukan keterampilan untuk sekolah' : 'Konfirmasi kontak & kuota PKL' }}</h1>
    <p class="hint">Untuk {{ $req->company->name }}. Formulir ini tidak membuka akses ke data sekolah.</p>
</div>
@if(($done ?? false) || $req->status !== 'sent')
    <div class="mentor-flash">Terima kasih. Tanggapan Anda sudah diterima sekolah dan akan ditinjau.</div>
@else
    <form method="POST" action="{{ route('partner.submit', $token) }}" class="mentor-card mentor-sign">
        @csrf
        <div><label class="label" for="rn">Nama Anda</label><input id="rn" name="responder_name" class="field" required autocomplete="name"></div>
        <div><label class="label" for="rp">Jabatan</label><input id="rp" name="responder_position" class="field" required></div>
        @if($req->kind === 'feedback')
            <div><label class="label" for="sk">Keterampilan yang paling dibutuhkan dari lulusan</label><textarea id="sk" name="skills"></textarea></div>
            <div><label class="label" for="ob">Pengamatan umum terhadap peserta PKL (tanpa menyebut nama)</label><textarea id="ob" name="observations"></textarea></div>
        @else
            <div><label class="label" for="pn">Nama PIC untuk urusan PKL</label><input id="pn" name="pic_name" class="field"></div>
            <div><label class="label" for="pp">Jabatan PIC</label><input id="pp" name="pic_position" class="field"></div>
            <div><label class="label" for="ph">WhatsApp PIC</label><input id="ph" name="pic_phone" type="tel" class="field"></div>
            <div><label class="label" for="pe">Email PIC</label><input id="pe" name="pic_email" type="email" class="field"></div>
            <div><label class="label" for="dv">Divisi penerima</label><input id="dv" name="division" class="field"></div>
            <div><label class="label" for="cp">Jumlah peserta yang bisa diterima periode ini</label><input id="cp" name="capacity" type="number" min="0" class="field tabular-nums"></div>
        @endif
        <label class="mentor-check"><input type="checkbox" name="confirm" value="1"> Saya menyatakan informasi ini benar dan boleh dipakai sekolah untuk keperluan PKL.</label>
        <button class="mentor-submit">Kirim</button>
    </form>
@endif
@endsection
