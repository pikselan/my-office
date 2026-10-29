# Status Implementasi

Terakhir diperbarui: 2026-10-29

## Status batch

| Batch | Nama | Status |
|---|---|---|
| 0 | Analisis & perancangan | ✅ Selesai |
| 1 | Fondasi aplikasi | ⏳ Berikutnya |
| 2 | Lapisan database | ⬜ |
| 3 | Skema & seed inti | ⬜ |
| 4 | Autentikasi | ⬜ |
| 5 | Tenant context & otorisasi | ⬜ |
| 6 | Design system & layout | ⬜ |
| 7 | Dashboard | ⬜ |
| 8 | Company management | ⬜ |
| 9 | Organization management | ⬜ |
| 10 | Employee management | ⬜ |
| 11 | Import/export & dokumen | ⬜ |
| 12 | Users & access | ⬜ |
| 13–28 | (lihat `docs/ROADMAP.md`) | ⬜ |

## Batch terakhir selesai

Batch 0 — Analisis & perancangan. Seluruh dokumen perencanaan selesai dan menjadi acuan
wajib bagi batch berikutnya.

## Batch sedang dikerjakan

Batch 1 — Fondasi aplikasi (struktur direktori sudah dibuat).

## File yang sudah dibuat (Batch 0)

```
docs/ENVIRONMENT.md  docs/PROJECT_SPEC.md  docs/ARCHITECTURE.md  docs/DATABASE.md
docs/ACCESS_CONTROL.md  docs/USER_FLOWS.md  docs/UI_GUIDELINES.md  docs/SECURITY.md
docs/PRICING.md  docs/ROADMAP.md  docs/CHANGELOG.md  docs/PROGRESS.md
```

## Skema database yang telah diterapkan

Belum ada. Rencana: 15 berkas migrasi pada `database/migrations/` (lihat `docs/DATABASE.md`
bagian 12).

## Pengujian

Belum ada kode yang diuji. Perkakas uji direncanakan pada Batch 2–3 (`bin/mo test`).

## Bug & keterbatasan terbuka

1. **MySQL tidak tersedia di sandbox** → seluruh uji terotomatisasi memakai SQLite.
   Uji dengan MySQL 8 wajib dilakukan pemilik proyek sebelum rilis.
2. **Apache tidak tersedia** → live preview memakai built-in server PHP; konfigurasi
   Apache/HTTPS hanya terdokumentasi, belum teruji.
3. PHP 8.5 di sandbox bukan instalasi native; ekstensi yang tersedia terbatas pada yang
   dibawa php-wasm (cukup untuk fitur yang direncanakan).

## Keputusan teknis penting

1. SQL aplikasi ditulis dalam subset portabel MySQL/SQLite (lihat `docs/ARCHITECTURE.md`
   bagian 6). Alasan: memungkinkan pengujian nyata & preview tanpa MySQL.
2. Migrasi tetap DDL MySQL 8 sebagai sumber kebenaran; `SqliteDialect` menerjemahkannya
   hanya untuk tooling/test.
3. Nilai enum disimpan sebagai `VARCHAR` dan divalidasi aplikasi, bukan `ENUM()` MySQL.
4. `updated_at` diisi oleh aplikasi, bukan `ON UPDATE CURRENT_TIMESTAMP` (portabilitas).
5. Penyimpanan berkas di `storage/private` (di luar document root).

## Catatan integrasi untuk batch berikutnya

- Batch 1 harus membuat `tools/php` (pembungkus php-wasm CLI) agar seluruh perintah
  tetap berbentuk `php ...`, serta `bin/mo` sebagai CLI aplikasi.
- Autoload ditulis sendiri (PSR-4 sederhana), tanpa Composer.
- `public/index.php` adalah satu-satunya entry point; `public/` menjadi document root.
