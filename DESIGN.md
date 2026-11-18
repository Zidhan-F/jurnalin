# DESIGN.md - Arah Visual & Sistem Antarmuka Jurnalin

Dokumen ini menetapkan identitas visual, token desain, dan aturan arsitektur layar untuk **Jurnalin** sesuai standar `antislop` dan `antislop-ui`. Seluruh keputusan desain di bawah ini memiliki alasan eksplisit yang tertulis.

## 1. Design Read & Liveliness Dials

> Reading this as: Field-first vocational internship logbook & school supervision workspace for Indonesian SMK students and teachers, in a warm utilitarian editorial style, dial ENERGY 2 / RHYTHM 2 / MOTION 1.

```text
Dial: ENERGY 2 / RHYTHM 2 / MOTION 1
```

- **ENERGY 2 (Balanced)**: Tampilan harus terasa rapi, hangat, dan dapat dipercaya oleh kepala sekolah serta praktisi HRD dunia industri tanpa hiasan efek visual berlebihan.
- **RHYTHM 2 (Consistent with intentional breaks)**: Panel kerja guru dan admin menggunakan tata letak struktural yang konsisten, dengan pemecah fokus visual yang tegas pada area keputusan mendesak (daftar siswa belum absen dan antrean validasi).
- **MOTION 1 (Hover & state feedback only)**: Tidak ada animasi loop, floating, atau transisi lambat. Siswa melakukan absen di sela jam kerja di tempat PKL (bengkel, kantor, dapur, lantai produksi) menggunakan HP kelas menengah ke bawah; respons antarmuka harus instan.

## 2. Identitas Visual & Palet Warna (Maksimal 3 Core + 1 Accent)

Seluruh warna dipilih untuk menciptakan nuansa "buku catatan lapangan modern yang rapi dan resmi", menjauhi gradien biru-ungu generik maupun mode gelap paksa.

| Peran Token | Kode Hex | Nama & Penggunaan | Alasan Pemilihan (R-31) |
|---|---|---|---|
| **Base Background** | `#FAF8F5` | Warm Paper: latar belakang utama halaman & area kerja | Mengurangi silau layar HP dan laptop dibandingkan putih murni `#FFFFFF`, memberi kesan kertas buku jurnal PKL yang akrab bagi guru dan siswa |
| **Surface Secondary** | `#F3EFEA` | Stone Cream: latar kartu sekunder, baris tabel bergantian, area preview foto | Memisahkan hierarki permukaan secara halus tanpa memerlukan shadow besar atau border tebal |
| **Text Primary** | `#18181B` | Deep Ink: seluruh judul, isi jurnal, dan data utama | Memberikan rasio kontras 16.71:1 terhadap `#FAF8F5`, jauh melampaui standar WCAG AA agar tetap terbaca di bawah sinar matahari luar ruangan |
| **Text Secondary** | `#52525B` | Muted Slate: label form, tanggal, keterangan bantuan | Menjaga kontras 7.29:1 pada latar `#FAF8F5` dan 6.75:1 pada `#F3EFEA` sambil mempertahankan hierarki informasi sekunder |
| **Core 1 (Primary)** | `#1E293B` | Institutional Navy: top bar, navigasi samping panel sekolah, tombol sekunder | Merepresentasikan seragam formal dunia kerja dan ketertiban administrasi sekolah kejuruan |
| **Core 2 (Attention)** | `#B45309` | Warm Amber: badge status menunggu validasi, peringatan keterlambatan, status `revision_needed` | Membedakan item yang butuh perhatian guru dari item normal; kontras teks putih 5.02:1 dan pada `#FAF8F5` 4.74:1 (memenuhi WCAG AA) |
| **Deliberate Accent** | `#047857` | Emerald Field Check: **hanya** untuk tombol CTA utama harian siswa (`Absen Masuk`, `Kirim Jurnal`) dan status `approved` / dalam radius | Menarik perhatian siswa langsung ke satu tombol aksi terpenting setiap hari; kontras teks putih 5.48:1, sebagai teks di `#FAF8F5` 5.17:1 |
| **Semantic Danger** | `#B91C1C` | Alert Red: status di luar radius GPS, error validasi form, siswa alpa | Memberikan sinyal tegas saat syarat lokasi atau pengisian gagal tanpa merusak harmoni palet hangat; kontras teks putih 6.47:1 |

**Aturan Ketat Warna**:
- Dilarang menggunakan gradien biru-ungu, glow neon, atau efek kaca (glassmorphism) pada kartu dan navbar (R-01, R-10, R-13).
- Warna aksen `#047857` tidak boleh dipakai sebagai warna latar blok dekoratif; fungsinya dibatasi hanya untuk tombol aksi utama dan penanda validasi berhasil (Core Part 3).
- Border standar menggunakan `#E4DFD7` (1px solid) sebagai pemisah struktural yang datar dan bersih tanpa mengandalkan drop shadow berat (R-12). Garis ini dekoratif (1.25:1); batas kontrol form wajib memakai token `field` `#79746E` (4.37:1) agar memenuhi WCAG 1.4.11.

## 3. Tipografi & Hierarki Data

| Peran Tipografi | Font Family | Penggunaan | Alasan Pemilihan (R-31) |
|---|---|---|---|
| **UI & Heading** | `Plus Jakarta Sans` | Judul halaman, navigasi, form, isi jurnal siswa | Memiliki proporsi huruf terbuka (*x-height* lega) dan dukungan karakter Bahasa Indonesia yang sangat jelas pada ukuran kecil di layar HP murah |
| **Data Numerik & Kode** | `JetBrains Mono` (`font-variant-numeric: tabular-nums`) | Jarak meter GPS (`42 m`), koordinat (`-6.2088, 106.8456`), jam check-in (`07:54`), NPSN, NISN, dan kolom nilai rekap | Memastikan angka pada tabel rekap absensi dan kolom nilai sejajar vertikal sempurna untuk pemindaian cepat oleh guru (R-06) |

**Skala Ukuran & Jarak**:
- Teks isi minimum `15px` (`0.9375rem`) di aplikasi mobile siswa dan `14px` di panel desktop guru.
- **Identity Motif**: Garis tepi kiri selebar `3px` **hanya** muncul pada item yang memiliki status aktif membutuhkan aksi (`#B45309` untuk jurnal perlu revisi atau menunggu validasi, `#B91C1C` untuk absensi di luar radius). Kartu normal tidak menggunakan garis warna tepi (mematuhi aturan Colored Left Stripe `antislop-ui`).

## 4. Arsitektur Layar Berbasis Keputusan (Decision-Driven UI)

Setiap layar dirancang menjawab satu pertanyaan dan satu keputusan utama pengguna. Sejak penerapan mockup UI (`docs/desain/mockup/`, spesifikasi `docs/superpowers/specs/2026-11-18-ui-mockup-penuh-design.md`), dashboard memakai sidebar, kartu angka, dan grafik, dengan syarat: setiap angka berasal dari data asli, setiap kartu tugas menaut ke halaman tindakannya, dan tren tanpa data pembanding disembunyikan (bukan diisi nol). Yang tetap ditolak adalah kartu angka acak dan grafik hiasan tanpa keputusan di baliknya.

### A. Layar Utama Siswa (Mobile PWA `/student`)
- **Pertanyaan yang dijawab**: *"Apakah saya sudah berada di lokasi tempat PKL dan sudah mencatat kegiatan hari ini?"*
- **Urutan beranda (mockup 01)**: sapaan + maskot sesuai jenis kelamin, **Kartu Presensi Hari Ini** (lencana status, jam Masuk dan Pulang), dua tombol besar **Presensi** dan **Tulis Jurnal** (minimal `56px`), satu baris "Sekarang: ..." yang menyebut langkah berikutnya, kartu **Progres PKL** (minggu ke-X dari Y dan hari hadir), banner jurnal perlu diperbaiki, lalu Aktivitas terbaru.
- **Halaman Presensi (`/student/presensi`)**: formulir absen masuk/pulang ber-GPS dan sakit/izin, plus riwayat singkat. Status jarak GPS dan pesan galat tetap seperti aturan state di bagian 5.
- **Navigasi Bawah Mobile**: 5 item nyata (tidak ada menu mati, R-24): `Beranda`, `Presensi`, `Jurnal`, `Izin`, `Laporan` (`App\Support\StudentNavigation`). Fitur belajar lain ditautkan dari halaman Laporan. Siswa masa tambahan hanya melihat `Jurnal`.
- **Desktop (64rem ke atas)**: sidebar kiri, empat ubin aksi, cincin progres CSS, dan notifikasi terbaru. Menu bawah disembunyikan.

### B. Dashboard Guru Pembimbing (`/teacher`)
- **Pertanyaan yang dijawab**: *"Siswa mana yang bermasalah hari ini, dan jurnal mana yang perlu saya periksa sekarang?"*
- **Urutan Komposisi Layar**:
  1. **Prioritas 1 - Daftar Siswa Belum Absen Hari Ini**: Tabel ringkas hanya menampilkan siswa bimbingan yang sudah melewati `work_start_time + toleransi` namun belum tercatat absen. Setiap baris memiliki tombol aksi nyata **`Kirim Pesan WA`** (membuka `https://wa.me/{phone}?text=...` dengan template pesan siap kirim).
  2. **Prioritas 2 - Antrean Jurnal Menunggu Validasi**: Daftar jurnal berstatus `submitted` diurutkan dari yang terlama, dilengkapi cuplikan foto kegiatan, tombol **`Setujui`**, dan tombol **`Minta Revisi`** (membuka input catatan feedback wajib).
  3. **Bilah Informasi Ringkas**: Ringkasan progres periode aktif (jumlah siswa aktif bimbingan dan persentase kelengkapan jurnal minggu berjalan) dihitung langsung dari query database real, bukan angka placeholder.

- **Kepala layar (poster)**: empat kartu angka (Peserta binaan, Jurnal perlu review, Izin/ketidakhadiran, Perlu perhatian), daftar Jurnal terbaru, dan donat Progres peserta (Selesai, Berjalan, Perlu perhatian; tidak tumpang tindih). Kartu "Yang perlu Anda kerjakan" dan bagian keputusan di atas tetap di bawahnya.

### C. Dashboard Koordinator PKL / Admin Sekolah (`/admin`)
- **Urutan (mockup 02)**: sapaan dan pemilih periode, empat kartu angka dengan tren dari periode sebelumnya, Tugas prioritas (empat baris bertaut), Aksi cepat, Periode PKL aktif, grafik garis Perkembangan jurnal 7 hari, donat Status peserta, lalu tabel Penempatan terbaru. Checklist persiapan tampil selama setup belum lengkap.
- Angka "hari ini" hanya dipakai bila periode terpilih sedang berjalan; selain itu memakai komposisi seluruh periode.

### D. Kerangka Panel Desktop
- Pada lebar `64rem` ke atas, panel admin, guru, dan super admin memakai **sidebar kiri** (logo paraf, nama sekolah statis, grup menu dari `App\Support\PanelNavigation`, kartu "Butuh bantuan?"). Header berisi kotak pencarian (admin dan guru), lonceng, dan chip akun berinisial. Di bawah `64rem` tetap header + "Menu panel".
- Pencarian (`/cari`) selalu dalam lingkup sekolah sendiri; guru hanya melihat data bimbingannya.

### E. Grafik, Ilustrasi, dan Anggaran Performa
- **Grafik** hanya di dashboard admin dan guru: garis dan donat lewat komponen `<x-chart>` (Chart.js, dimuat dinamis hanya bila ada `[data-chart]`). Angka yang sama selalu tampil sebagai teks/tabel, warna dari token, tanpa animasi bila `prefers-reduced-motion`.
- **Ilustrasi**: karakter kartun gaya mockup di `public/images/illustrations/`, lewat `App\Support\Mascot`. Avatar peran memakai kepala Avataaars (DiceBear); maskot sapaan beranda siswa seluruh badan (`Mascot::fullBody`: seragam putih abu-abu, ransel, buku jurnal berlogo paraf, melambai). Keduanya mengikuti `students.gender` (L laki-laki, P perempuan, kosong netral). Ikon pendukung dari Fluent Emoji flat (MIT). Avatar staf di header berupa inisial. Buat ulang karakter dengan `node scripts/generate-characters.mjs`. Tidak membuat ilustrasi baru tanpa persetujuan pemilik produk.
- **Anggaran**: halaman siswa 0 KB JavaScript tambahan (Chart.js tidak pernah dimuat di layout siswa); chunk grafik sekitar 60 KB gzip hanya di dashboard; ilustrasi maksimal sekitar 15 KB per berkas; tanpa font baru.

## 5. Kepatuhan State UI & Aksesibilitas (Hard Gates)

1. **Ketiga State Wajib Ada di Setiap Komponen Data (R-27)**:
   - **Empty State**: Harus menjelaskan alasan kosong dan memberikan tombol aksi berikutnya.
     - *Contoh Jurnal Kosong*: `"Belum ada jurnal kegiatan untuk minggu ini. Pastikan Anda sudah melakukan Absen Masuk hari ini sebelum mengisi jurnal."` + tombol `[Tulis Jurnal Pertama]`.
     - *Contoh Siswa Belum Absen Kosong*: `"Semua siswa bimbingan Anda (18/18) sudah mencatat kehadiran hari ini."`
   - **Loading State**: Menyebutkan proses spesifik, misalnya `"Mengambil koordinat GPS perangkat..."` atau `"Mengompresi foto sebelum unggah..."` (bukan spinner kosong tanpa keterangan).
   - **Error State**: Menjelaskan penyebab dan langkah pemulihan konkret:
     - *Contoh Izin GPS Ditolak*: `"Akses lokasi diblokir oleh browser. Ketuk ikon kunci di bilah alamat, pilih Izinkan Lokasi, lalu tekan tombol Muat Ulang GPS."`
     - *Contoh Di Luar Radius*: `"Jarak Anda 420 m dari titik tempat PKL (batas 200 m). Jika Anda sedang bertugas di luar area tempat PKL, centang opsi Tugas Luar dan tulis alasan untuk persetujuan guru pembimbing."`
2. **Aksesibilitas Keyboard & Kontras (R-25, R-32)**:
   - Fokus keyboard diatur global di `app.css`: outline `3px` emerald dengan offset `2px` dan cincin putih. Di permukaan gelap (header navy, `.on-dark`, tombol berlatar navy/emerald/amber) outline berganti `#FAF8F5` karena emerald di atas navy hanya 2.67:1.
   - Tidak ada penghapusan outline fokus (`outline-none`) tanpa pengganti yang terlihat jelas.
   - Modal dan drawer wajib bisa ditutup dengan tombol `Escape`.
3. **Radius & Elevasi (R-11, R-12)**:
   - Radius terstandar pada 3 level saja: `rounded-md` (`6px`) untuk input dan badge status, `rounded-lg` (`8px`) untuk tombol aksi, dan `rounded-xl` (`12px`) untuk kartu utama. Tidak membuat seluruh elemen berbentuk kapsul/pill penuh.
   - Shadow hanya digunakan untuk elemen mengambang nyata (modal dialog, dropdown select, toast notifikasi); kartu konten dan panel tetap datar dengan border `1px solid #E4DFD7`.

## 6. Standar Aksesibilitas WCAG 2.2 Level AA

Target resmi Jurnalin adalah **WCAG 2.2 Level AA**. Standar ini dijaga otomatis oleh `tests/Unit/ColorContrastTest.php` (ikut `php artisan test`): test gagal jika pasangan token di bawah ambang atau jika view memakai hex mentah.

### A. Token Warna (satu sumber di `resources/css/app.css`)

Komponen **wajib** memakai kelas token (`bg-paper`, `text-muted`, `border-field`, `bg-danger/10`), bukan hex mentah seperti `bg-[#FAF8F5]`. Nilai warna tidak berubah; tokennya yang menjadi kontrak.

| Token | Hex | Peran |
|---|---|---|
| `paper` / `stone` | `#FAF8F5` / `#F3EFEA` | Latar halaman / latar kartu sekunder |
| `border` | `#E4DFD7` | Garis dekoratif saja |
| `field` | `#79746E` | Batas input, select, textarea, grup toggle |
| `ink` / `muted` | `#18181B` / `#52525B` | Teks utama / teks sekunder |
| `navy` / `navy-strong` | `#1E293B` / `#0F172A` | Primer / hover primer |
| `emerald-check` / `-strong` / `-ink` | `#047857` / `#065F46` / `#064E3B` | CTA & approved / hover / teks di atas tint emerald |
| `amber-warn` / `-strong` / `-ink` | `#B45309` / `#92400E` / `#78350F` | Perhatian / hover & teks di tint / teks pesan peringatan |
| `danger` / `-strong` / `-ink` | `#B91C1C` / `#991B1B` / `#7F1D1D` | Error / teks di tint / teks pesan error |
| `on-dark` | `#CBD5E1` | Teks sekunder di atas navy |

### B. Matriks Kontras Terukur

| Pasangan | Rasio | Ambang |
|---|---|---|
| `ink` di `paper` | 16.71:1 | 4.5 teks |
| `muted` di `paper` / `stone` | 7.29 / 6.75:1 | 4.5 teks |
| `emerald-check` di `paper` / `stone` | 5.17 / 4.79:1 | 4.5 teks |
| `amber-warn` di `paper` | 4.74:1 | 4.5 teks |
| `danger` di `paper` | 6.10:1 | 4.5 teks |
| Putih di `navy` / `emerald-check` / `amber-warn` / `danger` | 14.63 / 5.48 / 5.02 / 6.47:1 | 4.5 teks |
| `on-dark` di `navy` | 9.85:1 | 4.5 teks |
| `emerald-ink` di tint `emerald/10` | 7.97:1 | 4.5 teks |
| `amber-ink` di tint `amber/10` | 6.99:1 | 4.5 teks |
| `danger-ink` di tint `danger/10` | 8.01:1 | 4.5 teks |
| `field` di `paper` | 4.37:1 | 3.0 non-teks |
| Fokus emerald di `paper` / fokus `paper` di `navy` | 5.17 / 13.80:1 | 3.0 non-teks |

**Larangan kombinasi** (gagal AA): teks `amber-warn` di tint `amber/10` (4.16:1), teks `emerald-check` di tint `emerald/10` (4.50:1, terlalu tipis), outline emerald di atas navy (2.67:1), dan `border` `#E4DFD7` sebagai satu-satunya batas kontrol (1.25:1). Untuk teks di atas tint, pakai varian `-ink` atau `-strong`.

### C. Checklist Per Kriteria

| Kriteria | Aturan di Jurnalin |
|---|---|
| 1.1.1 Konten non-teks | Foto memakai `alt` deskriptif; logo dekoratif di samping teks memakai `alt=""`; ikon SVG `aria-hidden`. |
| 1.3.1 Info & relasi | Setiap kontrol punya `<label for>` atau label pembungkus; grup tombol toggle memakai `role="group"` + `aria-label`. |
| 1.3.5 Tujuan input | Data milik pengguna memakai `autocomplete` (`username`, `current-password`, `tel`); nomor milik orang lain memakai `type="tel"` + `autocomplete="off"`. |
| 1.4.1 Penggunaan warna | Status tidak hanya warna: selalu ada teks label (`Disetujui`, `Perlu revisi`), garis tepi status, atau ikon `!` pada error field. |
| 1.4.3 / 1.4.11 Kontras | Lihat matriks di atas; diuji otomatis. |
| 1.4.4 / 1.4.10 Ubah ukuran & reflow | Tidak ada `maximum-scale`; tata letak satu kolom di 320 px; tabel lebar dibungkus `role="region"` + `tabindex="0"` agar bisa digulir keyboard. |
| 2.1.1 Keyboard | Semua aksi berupa `<button>`/`<a>` asli; modal dan drawer tertutup dengan `Escape`. |
| 2.4.1 Lewati blok | Tautan `Langsung ke isi utama` di setiap layout. |
| 2.4.7 / 2.4.11 Fokus terlihat & tidak tertutup | Outline global 3 px; `scroll-padding-bottom` menjaga field tidak tertutup menu bawah siswa. |
| 2.5.7 Gerakan seret | Peta tempat PKL bisa diatur lewat kolom Latitude/Longitude; penanda ikut berpindah. |
| 2.5.8 Ukuran target | Target minimal `44px` (`min-h-11`), CTA siswa `48-52px`. |
| 3.1.1 Bahasa | `<html lang="id">`. |
| 3.3.1 / 3.3.3 Identifikasi & saran error | Komponen `<x-error-summary />` menerima fokus dan menautkan tiap error ke field-nya; `app.js` memberi `aria-invalid`, `aria-describedby`, dan pesan di bawah field. |
| 3.3.8 Autentikasi | Login tanpa teka-teki; tempel kata sandi dan pengelola sandi diizinkan. |
| 4.1.3 Pesan status | Notifikasi sukses `role="status"`, error `role="alert"`, status GPS dan kompresi foto `role="status"`. |

