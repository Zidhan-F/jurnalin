@php
    $journalAccessOnly = request()->attributes->get('grace_placements') && ! request()->attributes->get('full_placements');
    $usageSteps = match (auth()->user()->role) {
        \App\Enums\UserRole::SchoolAdmin => [
            ['admin.periods', 'Atur periode PKL', 'Isi tanggal mulai, tanggal selesai, dan bobot nilai.'],
            ['admin.import', 'Masukkan guru dan siswa', 'Impor dari Excel atau tambahkan melalui menu Guru pembimbing dan Siswa.'],
            ['admin.companies', 'Tambahkan tempat PKL', 'Lengkapi alamat dan kontak pembimbing industri.'],
            ['admin.placements', 'Buat penempatan', 'Pasangkan siswa dengan periode, tempat PKL, dan guru pembimbing.'],
            ['admin.payments', 'Kelola pembayaran periode', 'Pilih periode PKL dan periksa rincian tagihan sesuai jumlah siswa.'],
            ['admin.periods', 'Pantau pelaksanaan PKL', 'Klik nama periode untuk melihat grup dan penempatan. Klik nama siswa untuk melihat perkembangannya.'],
        ],
        \App\Enums\UserRole::Teacher => $journalAccessOnly ? [
            ['teacher.index', 'Periksa jurnal siswa', 'Baca kegiatan, setujui jurnal yang sesuai, atau kirim catatan perbaikan.'],
        ] : [
            ['teacher.index', 'Cek siswa hari ini', 'Lihat siapa yang belum absen dan tangani izin yang menunggu.'],
            ['teacher.review', 'Periksa jurnal', 'Baca kegiatan siswa. Setujui yang sesuai atau minta perbaikan dengan catatan.'],
            ['teacher.assessments', 'Isi nilai dan cetak laporan', 'Masukkan penilaian sekolah dan industri, unggah bukti penilaian, lalu cetak laporan.'],
        ],
        \App\Enums\UserRole::Student => $journalAccessOnly ? [
            ['student.journals', 'Lengkapi jurnal', 'Perbaiki jurnal sesuai catatan guru, lalu kirim kembali.'],
        ] : [
            ['student.home', 'Absen mulai dan selesai kerja', 'Buka Hari ini. Ikuti petunjuk lokasi dan foto dari sekolah.'],
            ['student.journals', 'Catat kegiatan harian', 'Tulis pekerjaan yang dilakukan, lalu kirim jurnal untuk diperiksa guru.'],
            ['student.journals', 'Periksa tanggapan guru', 'Jika diminta revisi, perbaiki jurnal sesuai catatan lalu kirim kembali.'],
            ['student.profile', 'Lihat progres dan nilai', 'Periksa kehadiran, jurnal, dan penilaian pada Profil & nilai.'],
        ],
        \App\Enums\UserRole::SuperAdmin => [
            ['super-admin.index', 'Periksa pendaftaran sekolah', 'Cocokkan identitas dan NPSN, lalu tentukan status sekolah dan kuota siswa.'],
            ['super-admin.payments', 'Periksa pembayaran', 'Cocokkan bukti dengan tagihan. Verifikasi yang sesuai atau beri alasan pengembalian.'],
            ['super-admin.modules', 'Atur fitur sekolah', 'Pilih sekolah dan sesuaikan fitur yang dapat digunakan.'],
            ['super-admin.operations', 'Pantau operasional', 'Periksa pekerjaan dan layanan yang membutuhkan tindak lanjut.'],
        ],
    };
@endphp
<details class="rounded-lg border border-border bg-paper px-4">
    <summary class="cursor-pointer min-h-12 flex items-center font-semibold">Cara menggunakan Jurnalin</summary>
    <ol class="list-decimal pl-5 pb-4 space-y-3">
        @foreach($usageSteps as [$destination, $label, $instruction])
            <li>
                <a class="document-link min-h-12 text-left" href="{{ route($destination) }}">{{ $label }}</a>
                <p class="text-sm text-muted">{{ $instruction }}</p>
            </li>
        @endforeach
    </ol>
</details>
