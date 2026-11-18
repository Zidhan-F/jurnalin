{{-- Prefetch halaman berikutnya saat kursor diam di atas tautan atau jari mulai mengetuk (Speculation Rules, panduan web modern untuk aplikasi multi-halaman).
     Daftar izin: hanya halaman panel dan publik. Unduhan, ekspor, dan tautan bertoken (mis. /masuk/{token} yang habis dipakai saat GET) tidak pernah di-prefetch.
     Browser tanpa dukungan mengabaikan blok ini. --}}
<script type="speculationrules">
{
    "prefetch": [{
        "where": { "and": [
            { "href_matches": ["/", "/admin", "/admin/*", "/teacher", "/teacher/*", "/student", "/student/*", "/super-admin", "/super-admin/*", "/profil/foto", "/pantau/*", "/cari", "/notifikasi", "/kunjungan", "/kunjungan/*", "/laporan-siswa", "/laporan-siswa/*", "/akses-mentor", "/privasi-saya", "/syarat", "/privasi", "/kontak", "/login", "/daftar-sekolah"] },
            { "not": { "href_matches": ["/*.pdf", "/*.csv", "/admin/export", "/privasi-saya/ekspor", "/*/template", "/*/template/*"] } },
            { "not": { "selector_matches": "[download], [target=_blank], [rel~=nofollow], .no-prefetch" } }
        ] },
        "eagerness": "moderate"
    }]
}
</script>
