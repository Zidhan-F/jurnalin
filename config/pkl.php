<?php

/*
|--------------------------------------------------------------------------
| Katalog bawaan Spesifikasi Fitur v2
|--------------------------------------------------------------------------
| Semua isi di sini adalah contoh bawaan yang bisa disesuaikan sekolah.
| Template jurnal hanya contoh: peserta tetap mengonfirmasi bahwa kegiatan
| benar-benar dilakukan (aturan P06). TP mengacu Capaian Pembelajaran PKL
| Kurikulum Merdeka dan bukan pengganti dokumen kurikulum sekolah.
*/

return [

    // P39/P20: Tujuan Pembelajaran PKL bawaan (dapat diubah per sekolah).
    'default_objectives' => [
        ['code' => 'TP1', 'label' => 'Menerapkan norma, prosedur operasional standar, dan budaya kerja (disiplin, komunikasi, tanggung jawab, kerja sama)'],
        ['code' => 'TP2', 'label' => 'Menerapkan keselamatan, kesehatan kerja, dan lingkungan hidup (K3LH) sesuai standar tempat PKL'],
        ['code' => 'TP3', 'label' => 'Menerapkan kompetensi teknis yang sudah dipelajari di sekolah sesuai pekerjaan di tempat PKL'],
        ['code' => 'TP4', 'label' => 'Menerapkan kompetensi teknis baru atau yang belum dipelajari di sekolah'],
        ['code' => 'TP5', 'label' => 'Memahami alur bisnis dan peluang wirausaha di tempat PKL'],
    ],

    // G06: target kompetensi bawaan magang guru.
    'default_teacher_objectives' => [
        ['code' => 'MG1', 'label' => 'Memahami proses kerja dan standar mutu industri terkini'],
        ['code' => 'MG2', 'label' => 'Menguasai teknologi atau peralatan yang dipakai industri'],
        ['code' => 'MG3', 'label' => 'Menyusun bahan ajar berbasis kasus nyata industri'],
    ],

    // P06/P40: template aktivitas. Kunci = kelompok program keahlian; 'umum' selalu tersedia.
    'journal_templates' => [
        'umum' => [
            'label' => 'Umum (semua jurusan)',
            'items' => [
                ['key' => 'umum-briefing', 'title' => 'Mengikuti briefing pagi dan pembagian tugas', 'description' => 'Mengikuti briefing bersama tim, mencatat tugas hari ini dari pembimbing, lalu menyiapkan alat dan bahan yang dibutuhkan.', 'objective' => 'TP1'],
                ['key' => 'umum-k3', 'title' => 'Menerapkan prosedur K3 di area kerja', 'description' => 'Memakai alat pelindung diri sesuai area, memeriksa kondisi alat sebelum dipakai, dan merapikan area kerja setelah selesai.', 'objective' => 'TP2'],
                ['key' => 'umum-administrasi', 'title' => 'Membantu administrasi dan dokumentasi pekerjaan', 'description' => 'Mencatat hasil pekerjaan pada formulir unit, memeriksa kelengkapan data, dan menyerahkan rekap kepada pembimbing.', 'objective' => 'TP3'],
            ],
        ],
        'perhotelan-fo' => [
            'label' => 'Perhotelan: Front Office',
            'items' => [
                ['key' => 'fo-checkin', 'title' => 'Melayani check-in tamu', 'description' => 'Menyapa tamu, memeriksa reservasi dan identitas, mengisi registrasi di sistem, menjelaskan fasilitas, lalu menyerahkan kunci kamar.', 'objective' => 'TP3'],
                ['key' => 'fo-checkout', 'title' => 'Melayani check-out dan pembayaran tamu', 'description' => 'Memeriksa tagihan kamar dan minibar, mencetak folio, memproses pembayaran, dan memperbarui status kamar.', 'objective' => 'TP3'],
                ['key' => 'fo-telepon', 'title' => 'Menangani telepon dan permintaan tamu', 'description' => 'Menerima telepon dengan salam standar, mencatat permintaan tamu, dan meneruskannya ke departemen terkait.', 'objective' => 'TP1'],
            ],
        ],
        'perhotelan-hk' => [
            'label' => 'Perhotelan: Housekeeping',
            'items' => [
                ['key' => 'hk-mur', 'title' => 'Make-up room kamar tamu', 'description' => 'Menyiapkan trolley, membersihkan kamar dan kamar mandi, mengganti linen, melengkapi amenities, lalu melapor status kamar.', 'objective' => 'TP3'],
                ['key' => 'hk-bedmaking', 'title' => 'Bed making sesuai standar', 'description' => 'Melepas linen kotor, memasang sprei, duvet, dan sarung bantal sesuai SOP dengan target waktu dari supervisor.', 'objective' => 'TP3'],
                ['key' => 'hk-public', 'title' => 'Membersihkan public area', 'description' => 'Membersihkan lobi dan koridor dengan bahan kimia sesuai label, memasang tanda lantai basah, dan mencatat temuan kerusakan.', 'objective' => 'TP2'],
            ],
        ],
        'perhotelan-fb' => [
            'label' => 'Perhotelan: Food & Beverage Service',
            'items' => [
                ['key' => 'fb-setup', 'title' => 'Table set-up restoran', 'description' => 'Menata meja sesuai jenis layanan (breakfast, a la carte), memeriksa kebersihan peralatan makan, dan menyiapkan station.', 'objective' => 'TP3'],
                ['key' => 'fb-order', 'title' => 'Mengambil dan menyajikan pesanan', 'description' => 'Menyambut tamu, mencatat pesanan, mengonfirmasi ulang, menyajikan makanan dan minuman, lalu melakukan clearing.', 'objective' => 'TP1'],
            ],
        ],
        'kuliner' => [
            'label' => 'Kuliner / Tata Boga',
            'items' => [
                ['key' => 'kul-mise', 'title' => 'Mise en place bahan dapur', 'description' => 'Menimbang, mencuci, dan memotong bahan sesuai resep standar, menyimpan bahan pada suhu yang tepat, dan memberi label tanggal.', 'objective' => 'TP3'],
                ['key' => 'kul-sanitasi', 'title' => 'Sanitasi dan higiene dapur', 'description' => 'Membersihkan peralatan dan area kerja dengan prosedur sanitasi, memisahkan talenan bahan mentah dan matang, serta mencatat suhu chiller.', 'objective' => 'TP2'],
            ],
        ],
        'tkj-rpl' => [
            'label' => 'Teknologi Informasi (TKJ / RPL)',
            'items' => [
                ['key' => 'it-lan', 'title' => 'Instalasi dan pengujian jaringan LAN', 'description' => 'Menyiapkan kabel UTP, crimping konektor RJ45, menguji dengan LAN tester, dan mencatat port yang dipakai.', 'objective' => 'TP3'],
                ['key' => 'it-support', 'title' => 'Menangani keluhan perangkat pengguna', 'description' => 'Mencatat keluhan, mendiagnosis masalah perangkat atau aplikasi, melakukan perbaikan, dan mengonfirmasi ke pengguna.', 'objective' => 'TP3'],
                ['key' => 'it-dev', 'title' => 'Mengerjakan fitur atau perbaikan aplikasi', 'description' => 'Membaca tiket tugas, menulis kode pada branch terpisah, menguji fungsi, lalu mengajukan review ke pembimbing.', 'objective' => 'TP4'],
            ],
        ],
        'otomotif' => [
            'label' => 'Teknik Otomotif',
            'items' => [
                ['key' => 'oto-servis', 'title' => 'Servis berkala kendaraan', 'description' => 'Memeriksa oli, filter, rem, dan tekanan ban sesuai checklist servis, lalu mencatat hasil pada work order.', 'objective' => 'TP3'],
            ],
        ],
        'akuntansi' => [
            'label' => 'Akuntansi & Perkantoran',
            'items' => [
                ['key' => 'ak-jurnal', 'title' => 'Mencatat transaksi ke jurnal umum', 'description' => 'Memeriksa bukti transaksi, mencatat ke jurnal umum atau aplikasi akuntansi, dan mencocokkan saldo dengan pembimbing.', 'objective' => 'TP3'],
            ],
        ],
    ],

    // P34: kategori Lapor ke Guru. 'sensitive' = tidak diteruskan ke pihak terlapor, langsung ke admin sekolah.
    'support_categories' => [
        'pekerjaan' => ['label' => 'Kendala pekerjaan atau tugas', 'sensitive' => false],
        'k3' => ['label' => 'Keselamatan kerja atau kecelakaan', 'sensitive' => false],
        'kesehatan' => ['label' => 'Kesehatan', 'sensitive' => false],
        'perlakuan' => ['label' => 'Perlakuan tidak pantas, perundungan, atau pelecehan', 'sensitive' => true],
        'guru' => ['label' => 'Masalah dengan guru pembimbing', 'sensitive' => true],
        'lainnya' => ['label' => 'Lainnya', 'sensitive' => false],
    ],

    // Jalur darurat nasional ditampilkan di layar Lapor ke Guru.
    'emergency_lines' => [
        ['label' => 'Darurat umum', 'number' => '112'],
        ['label' => 'SAPA 129 (perlindungan perempuan & anak)', 'number' => '129'],
        ['label' => 'Ambulans', 'number' => '118'],
    ],

    // T04/T08: status lulusan boleh lebih dari satu sekaligus.
    'tracer_statuses' => [
        'bekerja' => 'Bekerja',
        'kuliah' => 'Melanjutkan studi',
        'wirausaha' => 'Wirausaha',
        'mencari' => 'Sedang mencari kerja',
        'lainnya' => 'Lainnya',
    ],

    // P43: modul pembekalan pra-PKL bawaan.
    'readiness_modules' => [
        [
            'title' => 'K3 dasar di tempat kerja',
            'summary' => 'Alat pelindung diri, tanda bahaya, dan cara melapor kejadian.',
            'content' => "Keselamatan kerja adalah tanggung jawab bersama. Selalu pakai alat pelindung diri (APD) yang ditentukan area kerja, kenali tanda bahaya dan jalur evakuasi, dan laporkan kejadian atau nyaris celaka kepada pembimbing pada hari yang sama.\n\nSebelum memakai alat, periksa kondisinya. Jangan memakai alat yang belum pernah diajarkan tanpa pendampingan.",
            'quiz' => [
                ['q' => 'Apa yang dilakukan bila menemukan alat rusak?', 'options' => ['Tetap dipakai hati-hati', 'Lapor pembimbing dan tidak dipakai', 'Diperbaiki sendiri'], 'answer' => 1],
                ['q' => 'Kapan kejadian nyaris celaka dilaporkan?', 'options' => ['Hari yang sama', 'Akhir minggu', 'Tidak perlu'], 'answer' => 0],
            ],
        ],
        [
            'title' => 'Etika dan komunikasi kerja',
            'summary' => 'Disiplin waktu, sikap profesional, dan menjaga kerahasiaan perusahaan.',
            'content' => "Datang tepat waktu, berpakaian sesuai aturan tempat PKL, dan beri kabar sebelum jam masuk bila berhalangan.\n\nData pelanggan, dokumen internal, dan kode sumber perusahaan bersifat rahasia. Jangan memotret atau membagikannya tanpa izin, termasuk di jurnal.",
            'quiz' => [
                ['q' => 'Bolehkah memotret data pelanggan untuk jurnal?', 'options' => ['Boleh', 'Tidak, kecuali ada izin', 'Boleh jika diburamkan sedikit'], 'answer' => 1],
                ['q' => 'Jika sakit, kapan mengabari tempat PKL?', 'options' => ['Sebelum jam masuk', 'Setelah jam pulang', 'Esok harinya'], 'answer' => 0],
            ],
        ],
    ],
];
