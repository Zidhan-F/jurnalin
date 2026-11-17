@extends('layouts.app', ['title' => 'Kebijakan Privasi | Jurnalin', 'description' => 'Data apa yang disimpan Jurnalin, untuk apa, berapa lama, dan hak siswa, guru, serta sekolah atas data tersebut.'])

@section('content')
<article class="info-page">
    <header class="info-heading">
        <h1>Kebijakan Privasi</h1>
        <p>Berlaku sejak 16 November 2026. Halaman ini menjelaskan data yang disimpan Jurnalin dan hak Anda sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>
        <p class="legal-draft" role="note">Versi draf. Teks ini perlu ditinjau penasihat hukum sebelum dipakai sebagai kebijakan resmi.</p>
    </header>

    <section class="info-row">
        <h2>Peran masing-masing</h2>
        <p>Sekolah adalah pengendali data: sekolah menentukan siswa, guru, dan tempat PKL yang dicatat. Jurnalin adalah prosesor data yang menyimpan dan mengolah data tersebut atas nama sekolah.</p>
    </section>
    <section class="info-row">
        <h2>Data yang disimpan</h2>
        <ul class="legal-list">
            <li><strong>Akun:</strong> nama, email, nomor WhatsApp, peran, dan kata sandi dalam bentuk hash.</li>
            <li><strong>Siswa:</strong> NISN, NIS, kelas, program keahlian, jenis kelamin, dan nomor orang tua bila diisi sekolah.</li>
            <li><strong>Kehadiran:</strong> waktu, koordinat GPS dan jaraknya ke tempat PKL, serta foto saat absen.</li>
            <li><strong>Jurnal dan nilai:</strong> isi jurnal, foto kegiatan, catatan guru dan pembimbing DUDI, nilai, dan foto formulir penilaian.</li>
            <li><strong>Tanda tangan DUDI:</strong> nama, jabatan, gambar tanda tangan, waktu, alamat IP, dan jenis peramban.</li>
            <li><strong>Log aktivitas:</strong> siapa menyetujui, mengubah, atau mengunduh data, beserta waktunya.</li>
        </ul>
    </section>
    <section class="info-row">
        <h2>Untuk apa</h2>
        <p>Data dipakai hanya untuk menjalankan administrasi PKL: mencatat kehadiran dan kegiatan, validasi oleh guru dan pembimbing DUDI, penilaian, serta laporan PDF sekolah. Data tidak dijual dan tidak dipakai untuk iklan.</p>
    </section>
    <section class="info-row">
        <h2>Data anak</h2>
        <p>Sebagian besar siswa SMK berusia di bawah 18 tahun. Sekolah wajib memastikan ada persetujuan orang tua atau wali sebelum data siswa dimasukkan, sesuai Pasal 25 UU PDP.</p>
    </section>
    <section class="info-row">
        <h2>Siapa yang bisa melihat</h2>
        <p>Data tiap sekolah dipisah di tingkat database. Siswa melihat datanya sendiri, guru melihat siswa bimbingannya, admin sekolah melihat data sekolahnya. Pembimbing DUDI hanya melihat jurnal satu siswa lewat tautan khusus yang bisa dicabut sekolah. Foto tidak tersedia lewat tautan publik.</p>
    </section>
    <section class="info-row">
        <h2>Berapa lama disimpan</h2>
        <p>Data disimpan selama sekolah berlangganan. Setelah sekolah mengajukan penghapusan, data dihapus permanen dalam 30 hari. Sebelum itu sekolah dapat mengunduh ekspor lengkap.</p>
    </section>
    <section class="info-row">
        <h2>Hak Anda</h2>
        <p>Siswa, guru, dan pembimbing DUDI berhak meminta akses, perbaikan, atau penghapusan datanya. Ajukan lewat admin sekolah Anda, karena sekolah yang memegang kendali data. Bila sekolah tidak menanggapi, hubungi kami lewat halaman <a class="document-link" href="{{ route('contact') }}">Kontak</a>.</p>
    </section>
    <section class="info-row">
        <h2>Bila terjadi kebocoran</h2>
        <p>Bila terjadi kegagalan pelindungan data, kami memberi tahu sekolah yang terdampak paling lambat 3 x 24 jam sejak diketahui, sesuai Pasal 46 UU PDP, agar sekolah dapat memberi tahu siswa dan orang tua.</p>
    </section>
</article>
@endsection
