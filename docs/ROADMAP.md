# Roadmap Batch — MO (MyOffice)

Batch 0 (perencanaan) selesai. Implementasi berurutan mengikuti dependensi.
Definisi selesai berlaku untuk **setiap** batch (BAGIAN 17.6):

1. Business logic berjalan.
2. UI terhubung ke backend.
3. Database menyimpan data dengan benar.
4. Validasi frontend dan backend tersedia.
5. Otorisasi backend diterapkan.
6. Isolasi tenant diuji.
7. Error handling tersedia.
8. Loading, empty, dan error state tersedia.
9. Bahasa melalui mekanisme translasi.
10. Dokumentasi diperbarui.
11. Uji relevan lulus atau kegagalannya dicatat jujur.

| # | Batch | Dependensi | Ruang lingkup |
|---|---|---|---|
| 0 | Analisis & perancangan | — | 11 dokumen perencanaan + audit lingkungan |
| 1 | Fondasi aplikasi | 0 | Struktur folder, `.env`, `Config`, autoload, `ErrorHandler`, `Logger`, `Request`, `Response`, `Router`, `Session`, helper |
| 2 | Lapisan database | 1 | `Database` PDO, `Query`, `Migrator`, `Seeder`, `SqliteDialect` (tooling), CLI dasar |
| 3 | Skema & seed inti | 2 | 15 migrasi, seed plans/permissions/roles/platform admin, `bin/mo migrate|seed|db:fresh` |
| 4 | Autentikasi | 3 | Registrasi tenant, login, throttle, verifikasi email, reset password, manajemen sesi, logout |
| 5 | Tenant context & otorisasi | 4 | `TenantContext`, `Authorization`, Policy, middleware auth/csrf/tenant/permission/feature |
| 6 | Design system & layout | 5 | CSS, komponen, sidebar, topbar, pemilih perusahaan/bahasa, `Translator`, `lang/id`, `lang/en` |
| 7 | Dashboard | 6 | Kartu statistik nyata, distribusi departemen, cuti menunggu, aktivitas terbaru, pengumuman |
| 8 | Company management | 6 | CRUD perusahaan, logo, pengaturan perusahaan, pemilih perusahaan, arsip |
| 9 | Organization management | 8 | 3 panel (unit/departemen/jabatan), hierarki, validasi siklus, arsip, pindah unit |
| 10 | Employee management | 9 | Profil, penempatan kerja, daftar, detail, riwayat, filter, pagination |
| 11 | Import/export & dokumen | 10 | Import/export CSV, unggah/unduh dokumen aman, catatan |
| 12 | Users & access | 5 | Undangan, pembuatan akun, role, permission, cakupan perusahaan, sesi, riwayat login |
| 13 | Onboarding workflow | 10 | Template, proses, checklist, penanggung jawab, tenggat, riwayat |
| 14 | Announcements | 8 | Pengumuman, target, jadwal, status, riwayat |
| 15 | Leave management | 10 | Jenis, kebijakan, saldo, pengajuan, persetujuan, kalender, idempotensi |
| 16 | Attendance & scheduling | 10 | Jadwal, shift, hari libur, check-in/out, koreksi, rekap |
| 17 | Recruitment | 10 | Lowongan, kandidat, tahapan, interview, konversi karyawan |
| 18 | Payroll | 10 | Komponen, periode, perhitungan, slip gaji |
| 19 | Finance | 18 | Reimbursement, cash advance, loan, angsuran |
| 20 | Reports & analytics | 15–19 | Laporan lintas modul, filter, export CSV, permission laporan |
| 21 | Billing & feature gating | 3 | Paket, pemakaian, invoice manual, pembatasan modul dan kuota |
| 22 | Platform administration | 3 | Tenant, paket, langganan, audit platform, support access grant |
| 23 | Notifikasi & cron | 5 | Pusat notifikasi, antrean job, `bin/mo jobs:run` |
| 24 | System settings | 12 | Pengaturan tenant/perusahaan, visibilitas informasi, keamanan, modul |
| 25 | i18n lengkap | semua UI | Kelengkapan id/en, alat `bin/mo i18n:check`, fallback |
| 26 | Pengujian lanjutan | semua | Isolasi tenant, keamanan, migrasi dari nol, regresi |
| 27 | Hardening & deployment | semua | Dokumen instalasi/upgrade/backup/restore, hardening, `.env.example` final |
| 28 | Final QA & release | semua | Pemeriksaan definisi siap rilis, perbaikan, catatan keterbatasan |

Catatan:
- Batch 21 (billing) dan 22 (platform) tidak bergantung pada modul HR, sehingga dapat
  dikerjakan lebih awal bila diperlukan; posisinya sengaja ditempatkan setelah modul inti
  agar feature gating diuji terhadap fitur yang sudah ada.
- Setiap batch memperbarui `docs/PROGRESS.md` dan `docs/CHANGELOG.md`.
