@extends('layouts.app', ['title' => 'Syarat Layanan | Jurnalin', 'description' => 'Syarat penggunaan Jurnalin bagi sekolah, guru pembimbing, siswa PKL, dan pembimbing di tempat PKL.'])

@section('content')
<article class="info-page">
    <header class="info-heading">
        <h1>Syarat Layanan</h1>
        <p>Berlaku sejak 16 November 2026. Syarat ini mengatur penggunaan Jurnalin oleh sekolah dan semua akun yang dibuat sekolah.</p>
        <p class="legal-draft" role="note">Versi draf. Teks ini perlu ditinjau penasihat hukum sebelum dipakai sebagai perjanjian resmi.</p>
    </header>

    <section class="info-row">
        <h2>Siapa pihaknya</h2>
        <p>Jurnalin menyediakan aplikasi jurnal PKL. Sekolah yang mendaftar menjadi pelanggan dan bertanggung jawab atas akun admin sekolah, guru pembimbing, dan siswa yang dibuatnya. Pembimbing di tempat PKL tidak membuat akun; mereka hanya membuka tautan yang dikirim sekolah.</p>
    </section>
    <section class="info-row">
        <h2>Pendaftaran dan verifikasi</h2>
        <p>Sekolah mendaftar dengan NPSN dan data koordinator yang benar. Akun baru aktif setelah tim Jurnalin mencocokkan NPSN. Kami dapat menolak atau menangguhkan pendaftaran yang datanya tidak sesuai.</p>
    </section>
    <section class="info-row">
        <h2>Kewajiban sekolah</h2>
        <div>
            <p>Sekolah memastikan memiliki dasar hukum untuk memasukkan data siswa, termasuk persetujuan orang tua atau wali bila diperlukan, karena sebagian besar siswa SMK masih di bawah umur.</p>
            <p>Sekolah menjaga kerahasiaan akun, segera menonaktifkan akun yang tidak lagi berhak, dan memastikan data yang dimasukkan benar.</p>
        </div>
    </section>
    <section class="info-row">
        <h2>Penggunaan yang dilarang</h2>
        <p>Akun tidak boleh dipakai untuk memalsukan kehadiran atau jurnal, mengakses data sekolah lain, mengganggu layanan, atau mengunggah berkas selain foto kegiatan dan dokumen penilaian.</p>
    </section>
    <section class="info-row">
        <h2>Tanda tangan pembimbing DUDI</h2>
        <p>Tanda tangan pembimbing di tempat PKL dibuat lewat tautan dan disimpan beserta waktu, alamat IP, dan sidik isi jurnal. Ini adalah tanda tangan elektronik tidak tersertifikasi menurut UU ITE dan PP 71/2019: dapat menjadi alat bukti, tetapi bukan tanda tangan elektronik tersertifikasi dari penyelenggara sertifikasi elektronik.</p>
    </section>
    <section class="info-row">
        <h2>Biaya</h2>
        <p>Biaya mengikuti paket di halaman <a class="document-link" href="{{ route('pricing') }}">Harga</a>. Perubahan harga diberitahukan sebelum periode PKL berikutnya dan tidak berlaku surut untuk periode yang sedang berjalan.</p>
    </section>
    <section class="info-row">
        <h2>Data dan penghentian</h2>
        <p>Data milik sekolah. Admin sekolah dapat mengunduh seluruh data kapan saja dan mengajukan penghapusan. Rincian penyimpanan dan penghapusan ada di <a class="document-link" href="{{ route('privacy') }}">Kebijakan Privasi</a>.</p>
    </section>
    <section class="info-row">
        <h2>Batas tanggung jawab</h2>
        <p>Kami berusaha menjaga layanan tetap tersedia dan data tetap aman, tetapi tidak menjamin layanan bebas gangguan. Sekolah tetap bertanggung jawab atas keputusan penilaian siswa.</p>
    </section>
    <section class="info-row">
        <h2>Perubahan syarat</h2>
        <p>Perubahan penting diberitahukan lewat email admin sekolah paling lambat 14 hari sebelum berlaku. Pertanyaan dapat dikirim lewat halaman <a class="document-link" href="{{ route('contact') }}">Kontak</a>.</p>
    </section>
</article>
@endsection
