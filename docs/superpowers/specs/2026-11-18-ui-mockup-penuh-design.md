# Desain: Penerapan Penuh Mockup UI Jurnalin

Tanggal: 2026-11-18. Status: menunggu persetujuan pemilik produk.

## 1. Maksud dan alasan

Pemilik produk memberikan tiga gambar rekomendasi UI (beranda siswa di HP, dashboard koordinator PKL di desktop, dan poster platform multi-peran) dan meminta **semuanya** diterapkan. Alasannya ada dua:

1. **Presentasi ke sekolah calon pengguna.** Tampilan harus terlihat lengkap, modern, dan meyakinkan saat didemokan.
2. **Sistem tetap berjalan lancar** untuk guru dan siswa yang memakainya setiap hari, termasuk di HP kelas bawah.

Acuan visual (sumber kebenaran tata letak, urutan kartu, dan kepadatan): `docs/desain/mockup/01-beranda-siswa-hp.png`, `02-dashboard-koordinator-desktop.png`, `03-poster-platform-multi-peran.png`. Setiap layar diperiksa visual terhadap gambar ini, dengan pengecualian warna (lihat di bawah) dan hal yang dikecualikan dokumen ini.

Ketentuan tetap dari pemilik produk:
- **Palet warna dan logo paraf yang ada tidak diubah.** Palet biru di poster (`#2563EB` dan seterusnya) diabaikan; seluruh warna tetap dari token di `resources/css/app.css` dan `DESIGN.md`.
- **Web harus tetap ringan, cepat, dan responsif.**
- **Grafik mengikuti mockup sepenuhnya** (grafik garis dan donat) dengan data asli. `DESIGN.md` ikut diperbarui.
- **Grafik memakai Chart.js** (pendekatan B, dipilih pemilik produk).
- **Maskot dan ilustrasi dicari dan ditambahkan oleh pembangun**, sesuai jenis kelamin: siswa laki-laki memakai ilustrasi laki-laki, siswa perempuan memakai ilustrasi perempuan.

## 2. Tujuan, bukan tujuan, dan anti-tujuan

### Tujuan
- Tiga panel yang sudah ada (Siswa, Guru Pembimbing, Admin Sekolah) tampil mengikuti mockup di HP dan desktop.
- Pusat kemitraan Hubin (`/admin/hubin/pusat`, modul add-on) tampil seperti "Dashboard Wubin (Kemitraan)" di poster.
- Landing page publik memuat isi poster yang relevan untuk presentasi: judul platform, daftar peran, dan peta modul.
- Setiap angka, grafik, dan kontrol yang terlihat **berfungsi dan berasal dari data asli**.

### Bukan tujuan
- **Tidak ada peran baru.** Kepala Sekolah, Wakasek, Orang Tua/Wali, dan Koordinator PKL tidak dibuatkan dashboard atau akun. "Dashboard Koordinator PKL" di mockup = panel **Admin Sekolah** yang sudah ada.
- Tidak mengubah warna, logo, atau font.
- Tidak ada mode gelap.
- Tidak ada unggah foto profil (avatar memakai ilustrasi atau inisial).
- Tidak menambah kolom jenis kelamin untuk guru.

### Anti-tujuan (gagal walau secara teknis jalan)
- Angka karangan atau tren tanpa data (misal "+12 siswa" padahal tidak ada periode pembanding).
- Kontrol mati: kotak pencarian yang tidak mencari, dropdown sekolah yang hanya berisi satu pilihan tapi berlagak dropdown, tombol tanpa tujuan.
- Pencarian atau grafik yang membocorkan data lintas sekolah.
- Beranda siswa (PWA) menjadi lebih berat atau lebih lambat dibanding sekarang.
- Teks UI yang mengunci durasi PKL 6 bulan atau mengasumsikan satu jurusan.

## 3. Batasan wajib

Semua aturan `AGENTS.md`, `CLAUDE.md`, dan `DESIGN.md` tetap berlaku, terutama:
- Isolasi multi-tenant: model memakai `BelongsToSchool`, tanpa `withoutGlobalScopes()` di panel sekolah/siswa, RLS PostgreSQL. Setiap fitur baru yang membaca data (pencarian, grafik, pemilih periode) wajib punya tes isolasi lintas sekolah.
- Warna hanya lewat kelas/token (`ColorContrastTest` gagal bila view memakai hex mentah). Kontras WCAG 2.2 AA.
- Tanpa karakter em-dash di teks, kode, maupun dokumen.
- Target sentuh minimal 44px, fokus keyboard terlihat, `prefers-reduced-motion` dihormati.
- Verifikasi: `php artisan test` dan `npm run build` lulus.

### Anggaran performa (batasan keras)
- **Beranda siswa dan semua halaman siswa: 0 KB JavaScript tambahan.** Chart.js tidak pernah dimuat di layout siswa. Cincin progres siswa memakai CSS (`conic-gradient`), bukan Chart.js.
- **Chart.js dimuat dengan `import()` dinamis** hanya bila halaman memiliki elemen `[data-chart]` (dashboard admin dan guru). Hanya modul yang dipakai yang didaftarkan (Line, Doughnut, elemen dan plugin minimum), target tambahan maksimal sekitar 70 KB gzip di halaman tersebut.
- Grafik juga menampilkan angka dalam HTML (legenda dan nilai), sehingga halaman tetap berguna sebelum Chart.js selesai dimuat dan bagi pembaca layar.
- Ilustrasi berupa SVG statis teroptimasi, masing-masing maksimal sekitar 15 KB, dengan `width`/`height` eksplisit; ilustrasi di bawah lipatan memakai `loading="lazy"`.
- Tidak ada font baru. Kueri dashboard tanpa N+1; agregasi memakai `count`/`groupBy` di database.
- Bila daftar precache `public/sw.js` berubah, naikkan `VERSION`.

## 4. Pendekatan yang dipertimbangkan

| | Pendekatan | Untung | Rugi |
|---|---|---|---|
| A | Render server, grafik SVG buatan sendiri | Paling ringan (0 KB JS tambahan) | Tooltip interaktif terbatas |
| **B (dipilih)** | Render server, grafik Chart.js dimuat dinamis hanya di dashboard admin/guru | Grafik interaktif (tooltip, animasi halus) seperti mockup | Tambahan sekitar 60-70 KB gzip di dashboard tersebut |
| C | Tulis ulang ke React/Inertia | - | Ditolak: menulis ulang semua halaman, paling berat, merusak PWA |

Pemilik produk memilih **B** dengan syarat web tetap ringan; syarat itu diterjemahkan menjadi anggaran performa di bagian 3.

## 5. Rancangan per bagian

### 5.1 Aset ilustrasi
- Sumber: **Microsoft Fluent Emoji** lewat paket npm `@iconify-json/fluent-emoji` (lisensi MIT, boleh komersial). Situs ilustrasi lain diblokir proxy di lingkungan pengembangan.
- Skrip build kecil (misal `scripts/extract-illustrations.mjs`, dijalankan manual, hasilnya di-commit) mengekstrak ikon terpilih ke `public/images/illustrations/*.svg`. Paket npm cukup sebagai `devDependency`; runtime tidak bergantung padanya.
- Sertakan `public/images/illustrations/LICENSE.txt` (teks MIT Fluent Emoji) dan sebut sumbernya di `README.md`.
- Pemetaan maskot siswa dari `students.gender`: `L` = siswa laki-laki, `P` = siswa perempuan, kosong = siswa netral.
- Guru tidak punya data jenis kelamin: pakai ilustrasi guru netral.
- Ilustrasi peran untuk landing page dipilih dari set yang sama (siswa, guru, pekerja kantor/industri, montir, koki, dan sejenisnya), mencerminkan lintas program keahlian.
- Avatar di header: siswa memakai versi kecil maskotnya; staf (admin, guru) memakai lingkaran inisial nama (contoh mockup "AK").

### 5.2 Kerangka panel (layout `layouts/app.blade.php`)
- **Desktop (lebar minimal 64rem): sidebar kiri** seperti mockup. Isi: logo, nama sekolah (teks statis, bukan dropdown, karena satu akun hanya punya satu sekolah), grup menu berlabel, dan kartu "Butuh bantuan?" yang menuju `/kontak`.
- Grup menu admin mengikuti mockup ("Utama", "Manajemen PKL", "Laporan") dengan isi dari rute yang sudah ada; menu bersyarat (fitur add-on, paket Pro) tetap mengikuti aturan yang ada di layout sekarang. Grup guru: "Utama" dan "Pendampingan". Super admin ikut memakai kerangka ini apa adanya.
- **Header desktop:** kotak pencarian (admin dan guru, lihat 5.6), lonceng notifikasi dengan jumlah belum dibaca, dan chip akun (avatar, nama, peran) yang membuka menu Ganti Sandi, Privasi saya, Keluar.
- **HP (di bawah 64rem):** tetap header atas + tombol "Menu panel" yang membuka daftar menu (perilaku sekarang). Tanpa sidebar.
- Semua halaman yang memakai layout ini (sekitar 52 view) ikut berubah kerangkanya; isi halaman tidak wajib diubah selain yang disebut di dokumen ini.

### 5.3 Panel siswa (layout `layouts/student-pwa.blade.php`)

**HP (mockup 1):**
- Header: logo + label "PKL", lonceng notifikasi, avatar maskot (tautan ke Profil).
- Sapaan "Halo, {nama depan}!" dengan maskot sesuai jenis kelamin dan satu kalimat pengingat.
- **Kartu "Presensi Hari Ini":** tanggal, lencana status (Sudah hadir / Belum absen / Sakit / Izin / Libur), jam Masuk dan Pulang.
- **Dua tombol besar:** "Presensi" (ke halaman presensi baru) dan "Tulis Jurnal".
- Satu baris "Sekarang: ..." di bawah tombol yang menyebut langkah berikutnya (pengganti penanda 3 langkah yang ada sekarang).
- **Kartu "Progres PKL":** "Minggu ke-X dari Y minggu" dihitung dari tanggal periode, persentase dan bar dari minggu yang sudah berjalan, plus satu baris "N dari M hari hadir" dari data presensi.
- Banner "Ada jurnal perlu diperbaiki" (sudah ada, disesuaikan gaya mockup).
- "Aktivitas terbaru" dengan ikon status dan tautan "Lihat semua" (sudah ada, disesuaikan gaya mockup).
- **Menu bawah 5 item:** Beranda, Presensi, Jurnal, Izin, Laporan. Siswa masa tambahan (`grace_placements` tanpa `full_placements`) tetap hanya melihat item Jurnal, sama seperti aturan sekarang.
- Fitur siswa yang tidak masuk menu bawah tidak boleh hilang: **Belajar, Kalender PKL, Paspor, Pembekalan, Latihan wawancara, Kilas balik** ditaruh sebagai daftar tautan "Belajar dan persiapan" di halaman Laporan. **Lapor ke Guru** tetap berupa tautan di beranda dan menjadi tujuan "Bantuan".

**Halaman baru:**
- `/student/presensi` (`student.attendance`): formulir absen masuk/pulang, sakit/izin yang sekarang ada di beranda dipindah ke sini **tanpa mengubah perilakunya** (rute POST `/student/check-in` dan `/student/check-out` tetap), ditambah riwayat presensi singkat dan tautan ke Kalender PKL.
- `/student/laporan` (`student.report`): progres PKL, nilai akhir (dari halaman Profil & nilai), tautan Laporan akhir, Kilas balik, dan unduhan buku jurnal PDF bila tersedia.

**Desktop siswa (poster):** sidebar (Beranda, Presensi, Jurnal kegiatan, Izin/Ketidakhadiran, Laporan, Bantuan ke `student.tickets`), empat ubin aksi (Presensi hari ini, Tulis jurnal, Ajukan izin, Laporan PKL), cincin progres PKL (CSS) beserta rincian: hari kegiatan (hari hadir dari target), jurnal disetujui, dan tugas (`LearningTarget` yang sudah dikumpulkan dari total; baris ini disembunyikan bila siswa tidak punya target), serta "Notifikasi terbaru" dari `UserNotification`.

### 5.4 Dashboard admin sekolah (mockup 2)
- Sapaan, judul "Dashboard Koordinator PKL", subjudul.
- **Pemilih periode** di kanan atas: daftar periode sekolah; memilih periode mengganti semua angka dan grafik lewat query string `?periode={id}` (default periode aktif dengan tanggal mulai terbaru). Tanpa JavaScript wajib (form GET dengan `<select>` dan tombol, ditingkatkan dengan auto-submit). Aturan:
  - Kartu angka, tugas prioritas, jurnal perlu diperiksa, grafik jurnal, dan donat **hanya menghitung penempatan di periode terpilih**.
  - Angka "hari ini" (Presensi hari ini, donat Status peserta) hanya tampil bila hari ini berada di dalam rentang periode terpilih. Untuk periode yang sudah selesai atau belum mulai, kartu itu menampilkan tingkat kehadiran keseluruhan periode, dan donat menampilkan komposisi presensi seluruh periode, dengan judul yang menyebut hal itu.
  - "Periode lalu" untuk tren = periode sekolah yang sama dengan tanggal selesai terbaru **sebelum** tanggal mulai periode terpilih. Bila tidak ada, baris tren disembunyikan. Periode yang berjalan bersamaan tidak dijadikan pembanding.
- **Empat kartu angka** dengan tren bila periode sebelumnya ada:
  - Peserta PKL: jumlah siswa berpenempatan di periode ini; tren "+N siswa dari periode lalu".
  - Penempatan: jumlah tempat PKL yang dipakai di periode ini; tren "+N mitra baru" (mitra yang tidak dipakai di periode sebelumnya).
  - Jurnal perlu diperiksa: jumlah jurnal menunggu; teks "Perlu tindakan Anda" bila lebih dari 0.
  - Presensi hari ini: hadir / peserta aktif, plus "Tingkat kehadiran: X%".
  - Tanpa periode pembanding: baris tren disembunyikan, bukan diisi angka nol.
- **Tugas prioritas** (4 baris, masing-masing jumlah + tautan): Periksa jurnal peserta (ke `/admin/jurnal-tertunda`), Konfirmasi presensi tidak hadir (ke `/pantau/peringatan`), Lengkapi data penempatan (siswa tanpa penempatan, ke `/admin/placements`), Input atau verifikasi penilaian (penempatan yang sudah bisa dinilai tetapi nilai belum final; lihat 6).
- **Aksi cepat** (6 ubin, sudah ada) dan **Periode PKL aktif** (sudah ada).
- **Perkembangan jurnal (7 hari terakhir):** grafik garis jumlah jurnal terkirim per tanggal jurnal, plus kotak "Total jurnal" periode ini dan perubahan persen terhadap 7 hari sebelumnya.
- **Status peserta:** donat dari presensi hari ini atas peserta aktif: Hadir (present/late), Izin/Sakit, Belum/Tidak hadir, dengan jumlah dan persen di legenda.
- Checklist persiapan (saat belum lengkap) dan tabel Penempatan terbaru tetap ada.

### 5.5 Dashboard guru pembimbing (poster)
- Sapaan + judul "Dashboard Guru Pembimbing".
- **Empat kartu angka:** Peserta binaan, Jurnal perlu review, Izin/ketidakhadiran menunggu, Perlu perhatian (jumlah peringatan `PklMonitor`).
- **"Jurnal terbaru (perlu tindakan)":** daftar ringkas dengan avatar siswa, tanggal, lencana status, menuju `/teacher/review`.
- **"Progres peserta":** donat atas penempatan guru di periode yang `is_active`, dengan tiga kelompok yang tidak saling tumpang tindih: **Selesai** = status `completed`; **Perlu perhatian** = status `active` yang punya peringatan `PklMonitor`; **Berjalan** = status `active` tanpa peringatan. Guru tidak punya pemilih periode.
- Kartu "Yang perlu Anda kerjakan" dan bagian keputusan yang sudah ada (izin, belum absen, jurnal, absen di luar lokasi) tetap di bawahnya, karena itu alur kerja harian.

### 5.6 Pencarian
- Rute `GET /cari?q=` untuk `school_admin` dan `teacher` (dengan `tenant.context`).
- Mencari: siswa (nama, NISN), guru (nama, khusus admin), tempat PKL (nama), jurnal (judul kegiatan). **Guru hanya melihat siswa dan jurnal dari penempatan bimbingannya.**
- Halaman hasil dikelompokkan per jenis, masing-masing menaut ke halaman yang sudah ada.
- Saran otomatis di header: `GET /cari/saran?q=` mengembalikan JSON maksimal 8 hasil; minimal 2 karakter, jeda ketik 250 ms, dibangun dengan Alpine yang sudah terpasang. Tombol `/` memfokuskan kotak pencarian (sesuai mockup). Tanpa JavaScript, form tetap bekerja ke halaman hasil.
- Kueri memakai `ILIKE` dengan batas hasil; tanpa lintas sekolah.

### 5.7 Pusat kemitraan Hubin (poster "Dashboard Wubin")
Hanya bila fitur `hubin` aktif. Tambahkan di bagian atas halaman yang ada:
- Kartu angka: **Mitra aktif** (`partnership_status = active`), **Dalam proses** (`prospect`), **Total penempatan** (penempatan non-batal di periode aktif), **Program aktif** (jumlah program di daftar bawah yang berstatus aktif).
- "Mitra terbaru": nama, kuota, jumlah siswa, bidang (`industry_type`).
- "Program kerja sama": **PKL siswa** (aktif bila ada penempatan aktif), **Magang guru** (aktif bila fitur `internship` aktif dan ada magang berjalan), **Kunjungan pembimbingan ke DUDI** (dari modul kunjungan guru yang sudah ada; aktif bila ada kunjungan di periode aktif). Label "Kunjungan industri" di mockup diganti nama ini karena tidak ada modul kunjungan industri terpisah.
Isi halaman di bawahnya tetap.

### 5.8 Landing page publik (poster)
- Hero: "Satu platform untuk PKL, Magang, dan Kolaborasi Industri" dengan empat poin centang dari poster, memakai palet yang ada.
- Bagian "Semua peran dalam satu platform": kartu berilustrasi **hanya untuk peran yang benar-benar ada**: Siswa, Guru Pembimbing, Admin Sekolah (koordinator PKL/Hubin), Pembimbing Industri (lewat tautan tanpa akun), Guru Magang (modul), Penguji UKK (lewat tautan), Alumni/Tracer (modul), Superadmin Jurnalin. **Tanpa** Kepala Sekolah, Wakasek, dan Orang Tua.
- Bagian "Modul utama Jurnalin": Jurnalin PKL, Hubin Partner Hub, Magang Guru, Mentor Access, Tracer Study, UKK, dengan label status yang **diverifikasi terhadap `docs/SPEC_V2_STATUS.md`** (label mockup seperti "MVP/V1/V2/Opsional" hanya dipakai bila sesuai).
- Bagian lain yang sudah ada (alur, harga, FAQ, keamanan data) tetap.

### 5.9 Pembaruan `DESIGN.md`
- Bagian 4: izinkan grafik garis dan donat di dashboard admin dan guru, dengan aturan: data asli, angka juga tampil sebagai teks, warna dari token, tanpa animasi bila `prefers-reduced-motion`, dimuat dinamis.
- Catat kerangka sidebar desktop, aturan ilustrasi (Fluent Emoji, pemetaan jenis kelamin), dan menu bawah siswa 5 item (mengganti aturan "maksimal 3 item").
- Catat anggaran performa dari bagian 3.

## 6. Keputusan yang diambil pembangun (dapat ditinjau pemilik)
- Nama sekolah di sidebar berupa teks statis, bukan dropdown (satu akun satu sekolah).
- Penanda 3 langkah siswa diganti baris "Sekarang: ..." agar sesuai mockup.
- Progres PKL siswa: minggu berjalan untuk persentase (seperti mockup), hari hadir sebagai baris pendukung.
- "Input atau verifikasi penilaian" = penempatan aktif atau selesai di periode terpilih yang belum punya nilai final, dan periodenya tinggal 14 hari atau kurang (atau sudah lewat), atau siswanya sudah memenuhi target hari hadir. Tautannya ke halaman periode (`admin.periods.show`).
- Cincin progres siswa memakai CSS, bukan Chart.js, demi anggaran 0 KB JS di halaman siswa.

- Aturan pemilih periode, kelompok donat guru, letak fitur siswa di luar menu bawah, dan pemetaan program Hubin di 5.3 sampai 5.7 adalah keputusan pembangun hasil tinjauan.

## 7. Diserahkan ke pembangun
- Urutan dan pembagian fase pengerjaan.
- Pilihan ikon menu dan ikon Fluent Emoji yang spesifik selama sesuai pemetaan di 5.1.
- Detail gaya grafik (ketebalan garis, radius donat) selama memakai token warna.
- Struktur komponen Blade (misal `<x-kpi-card>`, `<x-chart>`).

## 8. Pengujian
- Tes fitur: pencarian (admin melihat semua di sekolahnya, guru hanya bimbingannya, sekolah lain tidak pernah muncul), saran JSON, pemilih periode mengganti angka, dashboard admin/guru/siswa dan halaman baru siswa merender, absen tetap berjalan dari halaman presensi baru.
- `TenantIsolationTest` dan `ColorContrastTest` tetap lulus.
- Pemeriksaan manual di lebar 360px dan 1280px: tanpa gulir horizontal, tanpa error konsol, semua tautan dan kontrol diklik.
- Ukur ukuran bundel: halaman siswa tanpa JS tambahan; chunk Chart.js terpisah.
