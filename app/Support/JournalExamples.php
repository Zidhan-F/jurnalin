<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contoh isian (placeholder) form jurnal yang menyesuaikan unit kerja siswa. Kunci dipilih dari isi kolom
 * "Unit kerja" saat siswa mengetik, dengan cadangan dari program keahlian. Hanya contoh, tidak pernah tersimpan.
 */
final class JournalExamples
{
    /** Urutan penting: yang paling spesifik dulu (banquet sebelum F&B umum). */
    public const SETS = [
        'banquet' => [
            'match' => ['banquet', 'event', 'ballroom', 'meeting room', 'mice'],
            'work_unit' => 'F&B Banquet', 'title' => 'Set-up ballroom untuk acara resepsi',
            'description' => 'Menata round table sesuai denah acara, memasang taplak dan skirting, menyiapkan buffet line, lalu clearing setelah acara selesai.',
            'points' => "set-up round table 20 meja\nmenata buffet line\nclearing setelah acara\ntaplak kurang 3 lembar",
            'learning' => 'Set-up round table untuk 10 pax sesuai denah', 'obstacles' => 'Taplak kurang 3 lembar, diambil dari gudang linen',
            'tools' => 'Round table, taplak, chafing dish, cutlery',
        ],
        'fb' => [
            'match' => ['f&b', 'fnb', 'food', 'beverage', 'restoran', 'restaurant', 'resto', 'waiter', 'waitress', 'bar', 'cafe', 'kafe', 'room service', 'coffee'],
            'work_unit' => 'F&B Service (Restoran)', 'title' => 'Melayani breakfast tamu di restoran',
            'description' => 'Menyiapkan station dan table set-up, menyambut tamu, mencatat pesanan, menyajikan makanan dan minuman, lalu clearing meja.',
            'points' => "table set-up breakfast\nmelayani 30 tamu\nclearing dan polish cutlery\nmesin kopi sempat error",
            'learning' => 'Urutan service breakfast buffet dan a la carte', 'obstacles' => 'Mesin kopi sempat error saat jam ramai',
            'tools' => 'Tray, cutlery, mesin kopi, order pad',
        ],
        'kitchen' => [
            'match' => ['kitchen', 'dapur', 'pastry', 'bakery', 'cook', 'koki', 'culinary', 'kuliner', 'boga', 'steward'],
            'work_unit' => 'Kitchen (Hot Kitchen)', 'title' => 'Mise en place bahan untuk menu makan siang',
            'description' => 'Menimbang dan memotong bahan sesuai resep standar, menyimpan bahan di chiller dengan label tanggal, lalu membantu plating.',
            'points' => "mise en place sayur dan daging\nlabel tanggal bahan di chiller\nbantu plating 40 porsi",
            'learning' => 'Teknik potong brunoise dan julienne', 'obstacles' => 'Stok bawang bombai habis, menunggu dari purchasing',
            'tools' => 'Pisau chef, talenan berwarna, timbangan digital',
        ],
        'hk' => [
            'match' => ['housekeeping', 'room attendant', 'laundry', 'public area', 'roomboy', 'room boy', 'hk'],
            'work_unit' => 'Housekeeping', 'title' => 'Make-up room kamar tamu',
            'description' => 'Menyiapkan trolley, membersihkan kamar dan kamar mandi, mengganti linen, melengkapi amenities, lalu melapor status kamar.',
            'points' => "make up room 8 kamar\nbed making\nisi amenities\nvacuum cleaner rusak",
            'learning' => 'Bed making sesuai standar dalam 15 menit', 'obstacles' => 'Vacuum cleaner rusak, dipinjam dari lantai lain',
            'tools' => 'Trolley, vacuum cleaner, linen, chemical pembersih',
        ],
        'fo' => [
            'match' => ['front office', 'front desk', 'receptionist', 'resepsionis', 'reservasi', 'reservation', 'concierge', 'bellboy', 'fo'],
            'work_unit' => 'Front Office', 'title' => 'Melayani check-in tamu',
            'description' => 'Memeriksa reservasi dan identitas tamu, mengisi registrasi di sistem, menjelaskan fasilitas, lalu menyerahkan kunci kamar.',
            'points' => "cek reservasi tamu\ncheck-in 4 tamu\nterima telepon tamu\nprinter folio macet",
            'learning' => 'Prosedur check-in dan pengecekan deposit', 'obstacles' => 'Printer folio sempat macet',
            'tools' => 'PMS hotel, key card encoder, printer folio',
        ],
        'travel' => [
            'match' => ['travel', 'tour', 'tiket', 'ticketing', 'wisata', 'guide'],
            'work_unit' => 'Travel Agent (Ticketing)', 'title' => 'Membantu reservasi tiket pelanggan',
            'description' => 'Menerima permintaan pelanggan, mengecek jadwal dan harga di sistem, membuat reservasi, lalu mengirim konfirmasi.',
            'points' => "cek jadwal penerbangan\nbuat 5 reservasi tiket\nkirim itinerary ke pelanggan",
            'learning' => 'Membaca kode maskapai dan kelas tiket', 'obstacles' => 'Sistem reservasi sempat lambat',
            'tools' => 'Sistem reservasi, email, WhatsApp kantor',
        ],
        'it' => [
            'match' => ['it ', 'it support', 'jaringan', 'network', 'server', 'programmer', 'developer', 'software', 'helpdesk', 'tkj', 'rpl', 'komputer', 'teknisi komputer'],
            'work_unit' => 'IT Support', 'title' => 'Instalasi jaringan LAN ruang staf',
            'description' => 'Menyiapkan kabel UTP, crimping konektor RJ45, menguji dengan LAN tester, lalu mencatat port yang dipakai.',
            'points' => "crimping kabel UTP 6 titik\ntes dengan LAN tester\nsetting IP printer\nsatu kabel putus",
            'learning' => 'Urutan warna kabel straight T568B', 'obstacles' => 'Satu kabel putus di dalam plafon',
            'tools' => 'Crimping tool, kabel UTP, LAN tester',
        ],
        'oto' => [
            'match' => ['bengkel', 'servis', 'service center', 'otomotif', 'mekanik', 'motor', 'mobil', 'alat berat'],
            'work_unit' => 'Bengkel Servis', 'title' => 'Servis berkala sepeda motor',
            'description' => 'Memeriksa oli, filter, rem, dan tekanan ban sesuai checklist servis, lalu mencatat hasil pada work order.',
            'points' => "ganti oli 3 motor\ncek rem dan rantai\nisi work order",
            'learning' => 'Penyetelan rantai sesuai spesifikasi', 'obstacles' => 'Kunci torsi dipakai bergantian',
            'tools' => 'Kunci torsi, kunci ring, tire gauge',
        ],
        'office' => [
            'match' => ['akuntansi', 'accounting', 'keuangan', 'finance', 'admin', 'kantor', 'perkantoran', 'hrd', 'personalia', 'purchasing', 'kasir'],
            'work_unit' => 'Accounting', 'title' => 'Mencatat transaksi harian ke jurnal umum',
            'description' => 'Memeriksa bukti transaksi, mencatat ke aplikasi akuntansi, lalu mencocokkan saldo dengan pembimbing.',
            'points' => "cek bukti transaksi\ninput 25 transaksi\ncocokkan saldo kas",
            'learning' => 'Perbedaan jurnal umum dan buku besar', 'obstacles' => 'Dua nota tidak ada tanggalnya',
            'tools' => 'Aplikasi akuntansi, Excel, kalkulator',
        ],
        'umum' => [
            'match' => [],
            'work_unit' => 'Unit tempat Anda bertugas hari ini', 'title' => 'Pekerjaan utama yang Anda lakukan hari ini',
            'description' => 'Urutan pekerjaan yang dilakukan dan hasilnya',
            'points' => "kegiatan pertama\nkegiatan kedua\nkendala bila ada",
            'learning' => 'Hal baru yang Anda pelajari hari ini', 'obstacles' => 'Kendala yang muncul dan cara mengatasinya',
            'tools' => 'Alat, bahan, atau sistem yang dipakai',
        ],
    ];

    /** Kunci contoh dari teks bebas (unit kerja atau program keahlian). */
    public static function keyFor(?string $text): ?string
    {
        $text = ' '.mb_strtolower(trim((string) $text)).' ';
        if (trim($text) === '') {
            return null;
        }
        foreach (self::SETS as $key => $set) {
            foreach ($set['match'] as $word) {
                $pattern = '/(?<![a-z])'.preg_quote(trim($word), '/').'(?![a-z])/u';
                if (preg_match($pattern, $text)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Contoh bawaan untuk siswa (dari program keahlian), sebelum mengisi unit kerja. */
    public static function defaultFor(?string $major): string
    {
        $major = mb_strtolower((string) $major);
        if (str_contains($major, 'perhotelan') || str_contains($major, 'hotel') || str_contains($major, 'akomodasi')) {
            return 'fo';
        }

        return self::keyFor($major) ?? 'umum';
    }
}
