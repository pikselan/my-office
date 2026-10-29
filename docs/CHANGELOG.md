# Catatan Perubahan

Format: `[Batch] YYYY-MM-DD — deskripsi`.

## 2026-10-29

- `[Batch 0]` Audit lingkungan kerja: PHP tidak tersedia (diganti PHP 8.5.10 via
  php-wasm CLI), MySQL & Apache tidak dapat dipasang (network terbatas, tanpa dev headers).
  Strategi pengujian ditetapkan: lint PHP, unit test, uji skema/integrasi pada SQLite
  memakai `SqliteDialect`, validasi sintaks DDL MySQL memakai parser, serta live preview
  memakai built-in server PHP.
- `[Batch 0]` Dokumen perencanaan dibuat: `PROJECT_SPEC`, `ARCHITECTURE`, `DATABASE`,
  `ACCESS_CONTROL`, `USER_FLOWS`, `UI_GUIDELINES`, `SECURITY`, `PRICING`, `ROADMAP`,
  `ENVIRONMENT`, `PROGRESS`, `CHANGELOG`.
- `[Batch 0]` Struktur folder awal: `app`, `modules`, `resources`, `lang`, `database`,
  `routes`, `storage`, `public`, `tests`, `bin`, `docs`.

- `[Batch 1]` Fondasi aplikasi: `Config` (`.env` + `config/*.php`), autoload PSR-4
  tanpa Composer, `Request`, `Response`, `Router` (grup, middleware, nama route),
  `Session` (idle & umur absolut, regenerasi), `Csrf`, `View`, `ErrorHandler`,
  `Logger`, `App`, helper global, `public/index.php`, `bin/mo`, `.htaccess`
  untuk proteksi direktori internal. Terverifikasi: 27 berkas lolos `lint`,
  endpoint `/health` dan `/api/ping` merespons, 404/405 berstatus benar.
- `[Batch 2]` Lapisan database: `Database` (PDO, transaksi, identifier whitelist),
  `Migrator` + `schema_migrations`, `Seeder`, `SqliteDialect` (DDL MySQL → SQLite,
  khusus tooling/test), `Linter`, `Console`, serta perintah CLI
  `migrate`, `migrate:status`, `seed`, `db:fresh`, `db:stats`, `sql:check`.
- `[Batch 3]` Skema lengkap: 17 berkas migrasi, 64 tabel + `schema_migrations`.
  Terverifikasi: sintaks MySQL valid (parser sqlglot, 17/17), seluruh migrasi
  berjalan berurutan dari nol pada SQLite, `PRAGMA foreign_key_check` = 0
  pelanggaran, 106 indeks terbentuk.
- `[Batch 3]` Seed: 4 paket + fitur, 71 permission, 8 role sistem beserta
  matriks permission, 7 jenis cuti bawaan, akun platform admin (kredensial
  dari environment; bawaan dev hanya pada `APP_ENV=development`).
- `[Batch 3]` Perubahan skema: `user_roles.tenant_id` menjadi nullable untuk
  menampung penugasan role platform (sebelum rilis, sehingga cukup `db:fresh`).
