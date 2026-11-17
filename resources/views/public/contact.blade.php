@extends('layouts.app', ['title' => 'Kontak | Jurnalin', 'description' => 'Cara menghubungi tim Jurnalin untuk pertanyaan pendaftaran sekolah, akun, dan data.'])

@php $email = config('jurnalin.support_email'); @endphp

@section('content')
<article class="info-page">
    <header class="info-heading">
        <h1>Kontak</h1>
        <p>Tulis ke kami bila ada pertanyaan pendaftaran, kendala akun, atau permintaan terkait data. Sertakan NPSN sekolah agar kami cepat menemukan datanya.</p>
    </header>

    <section class="info-row">
        <h2>Email dukungan</h2>
        <div>
            @if($email)
                <p><a class="document-link" href="mailto:{{ $email }}">{{ $email }}</a></p>
            @else
                {{-- TODO: isi JURNALIN_SUPPORT_EMAIL di .env sebelum rilis. --}}
                <p class="legal-draft" role="note">[EMAIL DUKUNGAN] belum diatur.</p>
            @endif
        </div>
    </section>
    <section class="info-row">
        <h2>Siswa atau guru</h2>
        <p>Untuk lupa kata sandi, gunakan <a class="document-link" href="{{ route('password.request') }}">Lupa kata sandi</a> atau minta admin sekolah meresetnya. Untuk koreksi data jurnal atau nilai, hubungi guru pembimbing atau admin sekolah lebih dulu.</p>
    </section>
    <section class="info-row">
        <h2>Pembimbing di tempat PKL</h2>
        <p>Bila tautan tanda tangan tidak bisa dibuka atau sudah kedaluwarsa, minta guru pembimbing siswa mengirim tautan baru.</p>
    </section>
</article>
@endsection
