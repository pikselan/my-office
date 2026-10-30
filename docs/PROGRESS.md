# Status Implementasi

Terakhir diperbarui: 2026-10-29

## Status batch

| Batch | Nama | Status |
|---|---|---|
| 0 | Analisis & perancangan | ✅ Selesai |
| 1 | Fondasi aplikasi | ✅ Selesai |
| 2 | Lapisan database | ✅ Selesai |
| 3 | Skema & seed inti | ✅ Selesai |
| 4 | Autentikasi | ✅ Selesai |
| 5 | Tenant context & otorisasi | ⏳ Sedang dikerjakan |
| 6 | Design system & layout | ⬜ |
| 7 | Dashboard | ⬜ |
| 8 | Company management | ⬜ |
| 9 | Organization management | ⬜ |
| 10 | Employee management | ⬜ |
| 11 | Import/export & dokumen | ⬜ |
| 12 | Users & access | ⬜ |
| 13–28 | (lihat `docs/ROADMAP.md`) | ⬜ |

## Batch terakhir selesai

Batch 4 — Autentikasi. Registrasi tenant, masuk, verifikasi email, reset kata
sandi, throttle, penguncian akun, audit log, validator, dan translasi siap.
Rangkaian uji berjalan: 38 uji lulus, 0 gagal, 265 assertion.

## Batch selesai

Batch 5 — Tenant context & otorisasi. `TenantContext` (tenant & perusahaan aktif,
validasi keanggotaan, dukungan akses bantuan), `Authorization` (permission,
role, `can/cannot/require`, pembatasan perusahaan, deteksi admin platform), lima
middleware (`Auth`, `Csrf`, `Tenant`, `Permission`, `Platform`), dukungan argumen
middleware di router (`Kelas:arg1,arg2`), serta 8 uji keamanan isolasi tenant.

Batch 6 — Design system & autentikasi antarmuka. `public/assets/css/mo.css`
(token warna, layout sidebar 248px + topbar 64px, komponen, responsif),
`public/assets/js/mo.js` (sidebar, dropdown, modal, konfirmasi, toast, fetch,
pencarian debounce, panel), komponen PHP yang dapat dipakai ulang
(`app/Support/components.php`), `Paginator`, layout `app` & `auth`, menu sidebar
berbasis permission + route, halaman masuk/daftar/pilih-tenant/lupa & reset kata
sandi, beranda dengan angka nyata, dan halaman profil. Seluruh teks antarmuka
melalui `lang/{id,en}.php`.

## Batch sedang dikerjakan

Batch 7 — Modul Perusahaan & Struktur Organisasi (tiga panel: unit → departemen
→ jabatan).

## File yang sudah dibuat (Batch 0)

```
docs/ENVIRONMENT.md  docs/PROJECT_SPEC.md  docs/ARCHITECTURE.md  docs/DATABASE.md
docs/ACCESS_CONTROL.md  docs/USER_FLOWS.md  docs/UI_GUIDELINES.md  docs/SECURITY.md
docs/PRICING.md  docs/ROADMAP.md  docs/CHANGELOG.md  docs/PROGRESS.md
```

## Skema database yang telah diterapkan

Diterapkan: 17 berkas migrasi, 64 tabel + `schema_migrations`, 106 indeks.
Rincian pada `docs/DATABASE.md` bagian 12.

## Pengujian

| Perintah | Hasil terakhir |
|---|---|
| `bin/mo lint` | 76 berkas PHP valid |
| `bin/mo sql:check` | 17 migrasi, 0 bermasalah (parser MySQL) |
| `bin/mo test` | 61 uji lulus, 0 gagal, 328 assertion |
| `bin/mo migrate` | 17 migrasi diterapkan pada SQLite |
| `PRAGMA foreign_key_check` | 0 pelanggaran |

Pengujian memakai SQLite in-memory yang dibentuk dari migrasi yang sama dengan
produksi. Uji terhadap MySQL 8 belum dapat dilakukan di sandbox.

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
