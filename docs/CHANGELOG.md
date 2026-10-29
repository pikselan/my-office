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

- `[Batch 4]` Autentikasi: `Auth` (sesi), `UserRepository`, `AuthService` (masuk,
  daftar, verifikasi email, reset kata sandi, throttle & penguncian akun),
  `TenantService` (provisioning tenant + perusahaan + langganan trial + data
  awal), `Tokens` (token acak tersimpan sebagai hash), `Mailer` (driver log),
  `AuditService`, `Validator`, `Translator` + `lang/id.php` & `lang/en.php`.
- `[Batch 4]` Rangkaian uji: `tests/bootstrap.php` (SQLite in-memory dari
  migrasi), `TestCase`, `TestRunner`, perintah `bin/mo test`.
  Hasil: **38 uji lulus, 0 gagal, 265 assertion** (translator, validator,
  token, skema, alur autentikasi, throttling, reset & verifikasi).
- `[Batch 4]` Perbaikan: `min`/`max` pada validator membandingkan nilai untuk
  input numerik (sesuai semantik umum), sehingga kata sandi memakai aturan
  baru `min_chars`/`max_chars` yang selalu mengukur panjang teks.
- `[Batch 4]` Deprecation PHP tidak lagi dijadikan exception, hanya dicatat.

- `[Batch 5]` Konteks tenant & otorisasi: `TenantContext` (tenant/perusahaan aktif
  hanya dari sesi, divalidasi ulang terhadap keanggotaan setiap request;
  perusahaan dibatasi oleh `user_company_access`, `user_roles.company_id`, atau
  seluruh perusahaan aktif), `Authorization` (permission & role **bercakup tenant**,
  penugasan global tidak pernah memberi permission tenant, `withinCompanyScope()`,
  `isPlatformAdmin()`), middleware `Auth`, `Csrf`, `Tenant`, `Permission`,
  `Platform`, serta argumen middleware `Kelas:arg1,arg2` pada `Router`.
  Ditambahkan 8 uji keamanan isolasi tenant (`tests/Security/TenantIsolationTest`).
- `[Batch 6]` Design system & layar autentikasi: `public/assets/css/mo.css`
  (token, sidebar 248px dapat diciutkan, topbar 64px, kartu, tabel, formulir,
  badge, modal, toast, panel tiga kolom, responsif hingga 576px),
  `public/assets/js/mo.js` (tanpa dependensi), komponen view ter-escape
  (`app/Support/components.php`), `Paginator`, layout `layouts/app` &
  `layouts/auth`, menu sidebar yang hanya muncul bila route terdaftar dan izin
  terpenuhi, halaman masuk/daftar/pilih tenant/lupa & reset kata sandi, beranda
  berangka nyata, profil (data, bahasa, kata sandi), dan 90+ kunci terjemahan
  baru pada `lang/id.php` & `lang/en.php` dengan persistensi pilihan bahasa.
