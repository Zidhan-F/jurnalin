<?php

/*
|--------------------------------------------------------------------------
| Katalog Program Keahlian SMK & Kategori DUDI
|--------------------------------------------------------------------------
|
| Jurnalin melayani seluruh program keahlian SMK (Kurikulum Merdeka), bukan
| hanya Perhotelan & Pariwisata. Daftar ini dipakai sebagai saran isian
| (datalist) di formulir admin. Kolom tetap berupa teks bebas, sehingga
| sekolah boleh mengisi nama program yang tidak tercantum di sini.
|
*/

return [

    // Alamat dukungan yang tampil di halaman Kontak, Privasi, dan email. Isi lewat .env.
    'support_email' => env('JURNALIN_SUPPORT_EMAIL'),
    'payment_instructions' => env('JURNALIN_PAYMENT_INSTRUCTIONS'),

    // Tanda tangan DUDI: masa berlaku tautan setelah periode PKL berakhir.
    'mentor_link_grace_days' => 30,

    'program_keahlian' => [
        'Teknologi Informasi' => [
            'Rekayasa Perangkat Lunak',
            'Teknik Komputer dan Jaringan',
            'Sistem Informatika, Jaringan dan Aplikasi',
            'Desain Komunikasi Visual',
            'Animasi',
            'Broadcasting dan Perfilman',
        ],
        'Teknologi Manufaktur dan Rekayasa' => [
            'Teknik Mesin',
            'Teknik Otomotif',
            'Teknik Elektronika',
            'Teknik Ketenagalistrikan',
            'Teknik Mekatronika',
            'Teknik Pengelasan dan Fabrikasi Logam',
            'Teknik Kimia Industri',
        ],
        'Teknologi Konstruksi dan Properti' => [
            'Desain Pemodelan dan Informasi Bangunan',
            'Teknik Konstruksi dan Perumahan',
            'Teknik Geospasial',
        ],
        'Bisnis dan Manajemen' => [
            'Akuntansi dan Keuangan Lembaga',
            'Manajemen Perkantoran dan Layanan Bisnis',
            'Pemasaran',
            'Layanan Perbankan',
            'Logistik',
        ],
        'Pariwisata' => [
            'Perhotelan',
            'Kuliner',
            'Usaha Layanan Pariwisata',
            'Kecantikan dan Spa',
            'Busana',
        ],
        'Kesehatan dan Pekerjaan Sosial' => [
            'Layanan Kesehatan',
            'Farmasi',
            'Teknologi Laboratorium Medik',
            'Pekerjaan Sosial',
        ],
        'Agribisnis dan Agroteknologi' => [
            'Agribisnis Tanaman',
            'Agribisnis Ternak',
            'Agriteknologi Pengolahan Hasil Pertanian',
            'Agribisnis Perikanan',
            'Kehutanan',
        ],
        'Kemaritiman' => [
            'Nautika Kapal Niaga',
            'Teknika Kapal Niaga',
            'Nautika Kapal Penangkap Ikan',
        ],
        'Seni dan Ekonomi Kreatif' => [
            'Kriya Kreatif',
            'Seni Musik',
            'Seni Pertunjukan',
        ],
        'Energi dan Pertambangan' => [
            'Teknik Energi Terbarukan',
            'Geologi Pertambangan',
            'Teknik Perminyakan',
        ],
    ],

    'industry_types' => [
        'Software House / Perusahaan IT',
        'Penyedia Internet / Telekomunikasi',
        'Agensi Kreatif / Media',
        'Pabrik / Manufaktur',
        'Bengkel / Dealer Otomotif',
        'Kontraktor / Konsultan Konstruksi',
        'Bank / Lembaga Keuangan',
        'Kantor Akuntan / Perkantoran',
        'Ritel / Perdagangan',
        'Logistik / Ekspedisi',
        'Hotel / Resort',
        'Restoran / Katering',
        'Biro Perjalanan Wisata',
        'Rumah Sakit / Klinik / Apotek',
        'Instansi Pemerintah',
        'Perkebunan / Peternakan / Perikanan',
        'Pelayaran / Pelabuhan',
        'Energi / Pertambangan',
    ],

];
