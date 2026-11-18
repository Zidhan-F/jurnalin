@extends('layouts.app', ['title' => 'Jurnalin | Jurnal PKL digital untuk siswa dan guru SMK', 'description' => 'Siswa SMK mencatat kehadiran dan kegiatan praktik kerja lapangan dari HP. Guru pembimbing memeriksa jurnal, memberi nilai, dan mencetak laporan PKL.'])

@php
    $steps = [
        ['icon' => 'pin', 'title' => 'Absen masuk', 'text' => 'Siswa membuka Jurnalin di browser HP. Lokasi GPS dicocokkan dengan radius tempat PKL, foto diambil langsung dari kamera.'],
        ['icon' => 'journal', 'title' => 'Tulis jurnal', 'text' => 'Unit kerja, kegiatan, alat yang dipakai, dan durasi dicatat. Bisa disimpan sebagai draf lalu dikirim saat siap.'],
        ['icon' => 'check', 'title' => 'Guru memvalidasi', 'text' => 'Guru pembimbing menyetujui jurnal atau meminta revisi dengan catatan. Siswa yang belum absen langsung terlihat di atas.'],
        ['icon' => 'print', 'title' => 'Nilai dan PDF', 'text' => 'Nilai dari form DUDI disalin beserta foto buktinya, lalu buku jurnal dan rekap nilai dicetak dengan kop sekolah.'],
    ];

    // Hanya peran yang benar-benar ada di Jurnalin. Tiga peran pertama berakun; lainnya lewat tautan atau modul tambahan.
    $roles = [
        ['img' => 'student', 'title' => 'Siswa / Peserta PKL', 'items' => ['Absen dengan GPS dan foto', 'Tulis jurnal dari HP', 'Pantau progres dan nilai']],
        ['img' => 'teacher', 'title' => 'Guru Pembimbing', 'items' => ['Lihat siapa yang belum absen', 'Setujui atau minta revisi jurnal', 'Isi nilai dan cetak laporan']],
        ['img' => 'admin', 'title' => 'Admin Sekolah (koordinator PKL)', 'items' => ['Impor siswa dan guru dari Excel', 'Atur periode dan penempatan', 'Pantau seluruh PKL dari satu dashboard']],
        ['img' => 'mentor', 'title' => 'Pembimbing Industri', 'items' => ['Tanpa akun, cukup tautan aman', 'Beri catatan dan tanda tangan', 'Penilaian lewat formulir']],
        ['img' => 'intern_teacher', 'title' => 'Guru Magang', 'items' => ['Rencana dan jurnal magang guru', 'Evaluasi dari industri', 'Modul tambahan']],
        ['img' => 'examiner', 'title' => 'Penguji UKK', 'items' => ['Nilai per penguji lewat tautan', 'Instrumen berversi', 'Modul tambahan']],
        ['img' => 'alumni', 'title' => 'Alumni (Tracer)', 'items' => ['Isi survei keterserapan kerja', 'Tanpa akun', 'Modul tambahan']],
        ['img' => 'superadmin', 'title' => 'Superadmin Jurnalin', 'items' => ['Verifikasi sekolah', 'Atur paket dan modul', 'Pantau operasional platform']],
    ];

    $modules = [
        ['img' => 'journal', 'title' => 'Jurnalin PKL', 'tag' => 'Inti', 'text' => 'Presensi, jurnal harian, validasi guru, nilai, dan PDF ber-kop sekolah.'],
        ['img' => 'partnership', 'title' => 'Hubin Partner Hub', 'tag' => 'Modul tambahan', 'text' => 'Kelola mitra industri, kuota, dokumen kerja sama, dan penempatan massal.'],
        ['img' => 'intern_teacher', 'title' => 'Magang Guru', 'tag' => 'Modul tambahan', 'text' => 'Program magang guru di industri, lengkap dengan jurnal dan evaluasi.'],
        ['img' => 'mentor', 'title' => 'Mentor Access', 'tag' => 'Modul tambahan', 'text' => 'Akses sederhana untuk pembimbing industri tanpa perlu membuat akun.'],
        ['img' => 'chart', 'title' => 'Tracer Study', 'tag' => 'Modul tambahan', 'text' => 'Pelacakan alumni dan keterserapan kerja lulusan.'],
        ['img' => 'examiner', 'title' => 'UKK', 'tag' => 'Modul tambahan', 'text' => 'Pengelolaan dan penilaian uji kompetensi keahlian.'],
    ];

    $examples = [
        ['program' => 'Rekayasa Perangkat Lunak', 'place' => 'Software house', 'title' => 'Membuat halaman login aplikasi klien', 'text' => 'Saya membuat form login dengan validasi, menyambungkannya ke API, lalu menulis unit test untuk kasus kata sandi salah.', 'duration' => '7 jam'],
        ['program' => 'Teknik Otomotif', 'place' => 'Bengkel resmi', 'title' => 'Servis berkala 10.000 km', 'text' => 'Saya mengganti oli mesin dan filter, memeriksa tekanan ban, lalu mencatat hasil pemeriksaan rem di lembar kerja bengkel.', 'duration' => '8 jam'],
        ['program' => 'Akuntansi dan Keuangan Lembaga', 'place' => 'Kantor akuntan', 'title' => 'Rekonsiliasi bank bulanan', 'text' => 'Saya mencocokkan mutasi rekening dengan buku kas, menandai selisih biaya administrasi, lalu menyiapkan jurnal penyesuaian.', 'duration' => '6 jam'],
        ['program' => 'Perhotelan', 'place' => 'Hotel', 'title' => 'Menangani kedatangan tamu', 'text' => 'Saya memeriksa reservasi grup, mencocokkan identitas tamu, lalu menyiapkan kartu kunci kamar.', 'duration' => '8 jam'],
        ['program' => 'Farmasi', 'place' => 'Apotek', 'title' => 'Menyiapkan resep racikan', 'text' => 'Saya menimbang bahan obat sesuai resep di bawah pengawasan apoteker, lalu menulis etiket dan aturan pakai.', 'duration' => '7 jam'],
    ];

    $safeguards = [
        ['title' => 'Data tiap sekolah terpisah', 'text' => 'Pemisahan dikunci di level database. Sekolah lain tidak bisa melihat siswa, jurnal, atau nilai Anda.'],
        ['title' => 'Foto tidak dibuka publik', 'text' => 'Foto absen dan jurnal hanya bisa dibuka akun dari sekolah yang sama, tidak lewat tautan publik.'],
        ['title' => 'Akun baru wajib ganti sandi', 'text' => 'Kata sandi awal dari sekolah harus diganti saat login pertama, dan percobaan login dibatasi.'],
    ];
@endphp

@section('content')
<div class="front-page">
    <section class="front-intro" aria-labelledby="judul-beranda">
        <div class="cover-copy">
            <h1 id="judul-beranda">Satu platform untuk PKL, Magang, dan Kolaborasi Industri</h1>
            <p>Mencatat kegiatan, memantau perkembangan, dan mengelola program PKL siswa SMK dengan lebih mudah, terstruktur, dan terintegrasi.</p>
            <ul class="front-checks">
                <li><x-icon name="check" /> Mudah digunakan semua peran</li>
                <li><x-icon name="check" /> Alur kerja yang jelas dan sederhana</li>
                <li><x-icon name="check" /> Lengkap untuk kebutuhan sekolah</li>
                <li><x-icon name="check" /> Siap berkembang: Magang Guru, Hubin, Tracer, UKK</li>
            </ul>
            <div class="front-actions">
                <a class="primary-link" href="{{ route('schools.register') }}">Daftarkan sekolah</a>
                <a class="document-link" href="#contoh-jurnal">Lihat contoh jurnal</a>
            </div>
            <ul class="front-facts">
                <li><x-icon name="phone" /> Tanpa instal aplikasi</li>
                <li><x-icon name="pin" /> Absen GPS dan foto</li>
                <li><x-icon name="print" /> PDF ber-kop sekolah</li>
            </ul>
        </div>

        <figure class="phone-mock">
            <div class="phone-screen" aria-hidden="true">
                <div class="phone-top" x-data="localClock" @visibilitychange.document="sync()" @pageshow.window="sync()">
                    <span x-text="dateLabel"></span>
                    <span class="tabular-nums" x-text="timeLabel"></span>
                </div>
                <div class="phone-card">
                    <small>Tempat PKL hari ini</small>
                    <strong>jurnalin.id</strong>
                    <span class="phone-unit">Tim Pengembangan Web</span>
                    <div class="phone-distance">
                        <x-icon name="check" />
                        <span><b class="tabular-nums">38 m</b> dari lokasi, dalam radius <span class="tabular-nums">200 m</span></span>
                    </div>
                    <span class="phone-button">Absen Masuk &amp; Ambil Foto</span>
                </div>
                <div class="phone-row">
                    <span>Jurnal kemarin</span>
                    <span class="phone-badge">Disetujui</span>
                </div>
                <div class="phone-row">
                    <span>Kehadiran periode ini</span>
                    <span class="tabular-nums">42 / 120 hari</span>
                </div>
            </div>
            <figcaption>Layar siswa saat tiba di tempat PKL</figcaption>
        </figure>
    </section>

    <section id="tentang" class="day-flow" aria-labelledby="judul-alur">
        <div class="section-head">
            <h2 id="judul-alur">Satu hari PKL di Jurnalin</h2>
            <p>Dari absen pagi sampai nilai akhir periode, semua tercatat di satu tempat.</p>
        </div>
        <ol class="flow-list">
            @foreach($steps as $i => $step)
                <li>
                    <x-icon :name="$step['icon']" />
                    <h3><span class="sr-only">Langkah {{ $i + 1 }}: </span>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="role-grid" aria-labelledby="judul-peran">
        <div class="section-head">
            <h2 id="judul-peran">Semua peran dalam satu platform</h2>
            <p>Tampilan disesuaikan dengan peran masing-masing, sehingga lebih fokus, sederhana, dan mudah digunakan.</p>
        </div>
        <ul class="role-list">
            @foreach($roles as $role)
                <li>
                    <img src="{{ \App\Support\Mascot::role($role['img']) }}" width="56" height="56" alt="" loading="lazy" class="role-illustration">
                    <h3>{{ $role['title'] }}</h3>
                    <ul>
                        @foreach($role['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="module-section" aria-labelledby="judul-modul">
        <div class="section-head">
            <h2 id="judul-modul">Modul utama Jurnalin</h2>
            <p>Mulai dari Jurnalin PKL, lalu aktifkan modul tambahan sesuai kebutuhan sekolah.</p>
        </div>
        <ul class="module-list">
            @foreach($modules as $module)
                <li>
                    <img src="/images/illustrations/{{ $module['img'] }}.svg" width="48" height="48" alt="" loading="lazy">
                    <div>
                        <h3>{{ $module['title'] }} <span @class(['badge', 'badge-navy' => $module['tag'] === 'Inti'])>{{ $module['tag'] }}</span></h3>
                        <p>{{ $module['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section id="contoh-jurnal" class="journal-spread" aria-labelledby="judul-contoh">
        <div class="spread-intro">
            <h2 id="judul-contoh">Isi satu halaman jurnal</h2>
            <p>Setelah jam kerja selesai, siswa mencatat pekerjaan yang dilakukan dan durasinya. Buka contoh dari beberapa program keahlian.</p>
            <a class="document-link" href="#tentang">Cara kerja Jurnalin</a>
        </div>
        <div class="entry-list">
            @foreach($examples as $i => $ex)
                <details @if($i === 0) open @endif name="contoh-unit">
                    <summary>{{ $ex['program'] }} <span class="entry-place">{{ $ex['place'] }}</span></summary>
                    <div>
                        <h3>{{ $ex['title'] }}</h3>
                        <p>{{ $ex['text'] }}</p>
                        <dl><dt>Durasi kegiatan</dt><dd>{{ $ex['duration'] }}</dd></dl>
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    <section class="program-strip" aria-labelledby="judul-program">
        <h2 id="judul-program">Dipakai lintas bidang keahlian</h2>
        <ul>
            @foreach(array_keys(config('jurnalin.program_keahlian', [])) as $bidang)
                <li>{{ $bidang }}</li>
            @endforeach
        </ul>
    </section>

    <section class="safe-grid" aria-labelledby="judul-aman">
        <div class="section-head">
            <h2 id="judul-aman">Data siswa tetap milik sekolah</h2>
            <p>Foto wajah, lokasi, dan nilai siswa adalah data pribadi. Jurnalin memperlakukannya seperti itu.</p>
        </div>
        <ul class="safe-list">
            @foreach($safeguards as $item)
                <li>
                    <x-icon name="shield" />
                    <div><h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></div>
                </li>
            @endforeach
        </ul>
    </section>

    <section id="harga" class="price-section" aria-labelledby="judul-harga">
        <div class="section-head">
            <h2 id="judul-harga">Harga untuk sekolah</h2>
            <p>Pilih paket sesuai kebutuhan sekolah. Paket Reguler dan Pro dibayar sekali di awal untuk setiap grup siswa.</p>
        </div>
        <div class="price-list">
            <div class="price-row">
                <div><h3>Free</h3><p class="price-amount tabular-nums">{{ \App\Enums\SchoolPlan::Free->formattedPrice() }}</p></div>
                <div>
                    <h4>Satu periode PKL penuh</h4>
                    <p>Mencakup maksimal 35 siswa aktif. Koordinator dapat mencoba alur penempatan, absensi, jurnal, dan penilaian bersama satu kelas.</p>
                    <a href="{{ route('schools.register') }}" class="document-link">Daftarkan sekolah untuk uji coba</a>
                </div>
            </div>
            <div class="price-row">
                <div><h3>Sekolah Reguler</h3><p class="price-amount tabular-nums">{{ \App\Enums\SchoolPlan::Regular->formattedPrice() }}</p><p>per siswa dalam grup per bulan</p></div>
                <div>
                    <h4>Pengelolaan PKL dari penempatan sampai laporan</h4>
                    <p>Impor siswa dan guru, atur penempatan, pantau absensi GPS dan foto, periksa jurnal, masukkan nilai, lalu unduh buku jurnal tiap siswa dengan kop sekolah.</p>
                    <p class="price-example">Untuk 100 siswa selama 6 bulan <strong>Rp1.800.000 per periode</strong></p>
                </div>
            </div>
            <div class="price-row">
                <div><h3>Sekolah Pro</h3><p class="price-amount tabular-nums">{{ \App\Enums\SchoolPlan::Pro->formattedPrice() }}</p><p>per siswa dalam grup per bulan</p></div>
                <div>
                    <h4>Semua fitur Reguler dan rekap untuk koordinator Hubin</h4>
                    <ul class="price-features">
                        <li>Lihat siswa yang perlu ditindaklanjuti lintas kelas beserta nama pembimbingnya.</li>
                        <li>Unduh buku jurnal satu kelas sekaligus sebagai ZIP berisi PDF siswa.</li>
                        <li>Ekspor rekap kehadiran, jurnal, dan nilai per jurusan, kelas, atau pembimbing.</li>
                    </ul>
                    <p class="price-example">Untuk 100 siswa selama 6 bulan <strong>Rp3.000.000 per periode</strong></p>
                </div>
            </div>
        </div>
        <p class="price-note">Tempat PKL dan pembimbing industri tidak dikenai biaya dan tidak perlu membuat akun.</p>
        <p class="price-note">Setelah periode berbayar, grup mendapat <strong>satu bulan gratis khusus jurnal</strong>. Siswa bisa menulis atau memperbaiki jurnal dan guru bisa memeriksanya.</p>
        <details class="billing-help"><summary>Kapan akses dimulai dan bagaimana sisa bulan dihitung?</summary><p>Admin Jurnalin memverifikasi bukti transfer. Grup mulai aktif saat pembayaran terverifikasi atau pada tanggal mulai PKL jika periode belum dimulai. Sisa durasi kurang dari sebulan dihitung satu bulan penuh untuk biaya.</p></details>
    </section>

    <section id="faq" class="faq-section" aria-labelledby="judul-faq">
        <div class="section-head">
            <h2 id="judul-faq">Pertanyaan umum</h2>
            <p>Informasi untuk sekolah, siswa, dan pembimbing yang mulai menggunakan Jurnalin.</p>
        </div>
        <div class="faq-list">
            <details><summary>Apa yang perlu disiapkan untuk mendaftarkan sekolah?</summary><p>Siapkan NPSN delapan digit, nama dan alamat resmi sekolah, serta nama, email, dan nomor WhatsApp koordinator. Setelah data sekolah diverifikasi, koordinator dapat masuk menggunakan akun yang didaftarkan. <a class="document-link" href="{{ route('schools.register') }}">Buka formulir pendaftaran</a></p></details>
            <details><summary>Berapa lama satu periode PKL?</summary><p>Admin sekolah menentukan sendiri durasinya: 1 bulan, 3 bulan, 6 bulan, 1 tahun, atau tanggal mulai dan selesai bebas. Target hari hadir siswa ikut disesuaikan dengan durasi tersebut dan bisa diubah.</p></details>
            <details><summary>Bagaimana siswa mendapat akses?</summary><p>Admin sekolah menambahkan atau mengimpor data siswa, lalu menetapkan periode, tempat PKL, dan guru pembimbing. Siswa menggunakan email dan kata sandi akun sekolahnya untuk masuk.</p></details>
            <details><summary>Bagaimana jika siswa bertugas di luar lokasi PKL?</summary><p>Saat jarak GPS berada di luar radius tempat PKL, siswa dapat memilih tugas luar dan menjelaskan lokasi serta tugasnya. Guru pembimbing memeriksa alasan tersebut sebelum menyetujui pengecualian absensi.</p></details>
            <details><summary>Apakah pembimbing industri perlu membuat akun?</summary><p>Pembimbing industri cukup mengisi formulir penilaian sekolah. Guru memasukkan nilainya ke Jurnalin dan mengunggah foto formulir sebagai bukti.</p></details>
            <details><summary>Berapa siswa yang bisa mengikuti uji coba?</summary><p>Satu sekolah dapat mencoba Jurnalin untuk maksimal 35 siswa aktif selama satu periode PKL. <a class="document-link" href="#harga">Lihat rincian paket sekolah</a></p></details>
        </div>
    </section>

    <section class="front-cta on-dark" aria-labelledby="judul-mulai">
        <div>
            <h2 id="judul-mulai">Mulai periode PKL berikutnya dengan rapi</h2>
            <p class="text-on-dark">Daftarkan sekolah dengan NPSN. Setelah diverifikasi, admin sekolah bisa langsung mengimpor siswa dan guru.</p>
        </div>
        <div class="front-actions">
            <a class="primary-link" href="{{ route('schools.register') }}">Daftarkan sekolah</a>
            <a class="document-link" href="{{ route('login') }}">Sudah punya akun? Masuk</a>
        </div>
    </section>

    @if(count($demoAccounts))
        <details class="demo-directory">
            <summary>Akun demo lokal</summary>
            <ul>
                @foreach($demoAccounts as $acc)
                    <li>
                        <div><strong>{{ $acc['label'] }}</strong><small>{{ $acc['role'] }} · {{ $acc['school'] }}</small></div>
                        <form method="POST" action="{{ route('demo.login') }}">
                            @csrf
                            <input type="hidden" name="email" value="{{ $acc['email'] }}">
                            <button class="document-link">Masuk<span class="sr-only"> sebagai {{ $acc['label'] }}</span></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
@endsection
