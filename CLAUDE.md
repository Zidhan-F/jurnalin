# CLAUDE.md - Panduan Repositori Jurnalin

Berkas ini menyelaraskan instruksi proyek untuk Claude Code dan asisten coding AI di repositori **Jurnalin** (Platform Manajemen PKL SMK Multi-Tenant).

Seluruh arsitektur, aturan keamanan multi-tenant, pola desain, dan daftar berkas terdokumentasi di:
- [AGENTS.md](file:///c:/Users/Zidhan/Downloads/jurnalin/AGENTS.md)
- [DESIGN.md](file:///c:/Users/Zidhan/Downloads/jurnalin/DESIGN.md)
- [PRD.md](file:///c:/Users/Zidhan/Downloads/jurnalin/docs/PRD.md)
- [TSD.md](file:///c:/Users/Zidhan/Downloads/jurnalin/docs/TSD.md)
- [DATABASE_SCHEMA.md](file:///c:/Users/Zidhan/Downloads/jurnalin/docs/DATABASE_SCHEMA.md)
- [PROJECT_STRUCTURE.md](file:///c:/Users/Zidhan/Downloads/jurnalin/docs/PROJECT_STRUCTURE.md)

## Ringkasan Aturan Wajib

1. **Isolasi Multi-Tenant**: Seluruh model operasional memakai `BelongsToSchool`, rute terotentikasi melewati `SetTenantContextMiddleware` sebelum `SubstituteBindings`, dan PostgreSQL menerapkan `FORCE ROW LEVEL SECURITY`.
2. **Keutuhan Desain (`DESIGN.md`)**: Gunakan palet `#FAF8F5`, `#F3EFEA`, `#1E293B`, `#B45309`, aksen `#047857`, dan `#B91C1C`. Jangan gunakan karakter em-dash (`\u2014`) di teks mana pun.
3. **Verifikasi Wajib**: Setelah perubahan kode, jalankan `php artisan test` (termasuk `TenantIsolationTest`) dan `npm run build`.
4. **Graphify**: Konsultasikan `graphify-out/` atau jalankan `python -m graphify update .` setelah refaktor.
