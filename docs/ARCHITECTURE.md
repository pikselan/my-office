# Arsitektur — MO (MyOffice)

## 1. Keputusan arsitektur utama

| # | Keputusan | Alasan |
|---|---|---|
| A1 | PHP native tanpa framework, PDO, modular per domain | Ketentuan stack; menghindari ketergantungan framework |
| A2 | Shared database + shared application, isolasi dengan `tenant_id` | Paling sederhana untuk solo developer; seluruh query bisnis wajib membawa filter tenant |
| A3 | Front controller tunggal `public/index.php` | Document root = `public/`, file konfigurasi tidak dapat diakses browser |
| A4 | Pola Controller → Service → Repository → View | Memisahkan HTTP, business logic, akses data, dan presentasi |
| A5 | Otorisasi terpusat di `Authorization` + `Policy`, diperiksa di backend | Menghindari aturan role tersebar di banyak file |
| A6 | Migrasi SQL berurutan + tabel `schema_migrations` | Upgrade dapat diulang dan diaudit |
| A7 | Translasi terpusat `lang/id.php` & `lang/en.php` | Tidak ada label UI yang ditulis langsung di banyak view |
| A8 | SQL ditulis dalam subset portabel MySQL/SQLite | Memungkinkan pengujian nyata & preview di sandbox tanpa MySQL (lihat `docs/ENVIRONMENT.md`) |
| A9 | Tanggal/waktu disimpan UTC (`DATETIME`), konversi saat ditampilkan | Konsistensi lintas timezone perusahaan |
| A10 | Tanpa cache aplikasi pada tahap awal | Belum ada kebutuhan nyata; menghindari cache basi lintas tenant |
| A11 | Job terjadwal melalui CLI + cron (`bin/mo jobs:run`) | Sederhana, idempoten, tanpa queue server |

## 2. Siklus hidup request

```
Browser → public/index.php
        → bootstrap (autoload, config, error handler, session)
        → Router (routes/web.php, routes/api.php)
        → Middleware (Auth, Csrf, Tenant, Permission, FeatureGate)
        → Controller
             → Validator (input)
             → Service (business logic, transaksi, audit)
                  → Repository (SQL + filter tenant wajib)
             → View / JSON
        → Response
```

## 3. Struktur folder

```
app/Core            kernel: Config, Database, Router, Request, Response, Session,
                    Csrf, Auth, Authorization, TenantContext, Validator, View,
                    Translator, Logger, Audit, Paginator, Upload, Mailer,
                    FeatureGate, HttpException
app/Support         helper global (e(), url(), asset(), old(), csrf_field(), ...)
app/Middleware      AuthMiddleware, CsrfMiddleware, TenantMiddleware,
                    PermissionMiddleware, FeatureMiddleware, PlatformMiddleware
app/Services        layanan lintas modul (PlanService, AuditService, ...)
modules/<Nama>/     Controllers, Services, Repositories, views
resources/views     layout & komponen bersama
lang                id.php, en.php
database/migrations *.sql  |  database/seeds *.php
routes              web.php, api.php
storage             logs, cache, private (dokumen), tmp, database (sqlite sandbox)
public              index.php, assets
tests               Unit, Integration, Security, Support
bin/mo              CLI (migrate, seed, test, lint, sql:check, i18n:check, jobs:run)
docs                dokumentasi proyek
```

## 4. Lapisan dan tanggung jawab

- **Controller**: membaca request, memanggil validator, memanggil service, mengembalikan
  view atau JSON. Tidak menulis SQL.
- **Service**: business logic, transaksi DB, audit log, pengecekan limit paket.
- **Repository**: SQL. Turunan `BaseRepository` mewajibkan `tenant_id` pada setiap query
  untuk tabel bertipe tenant-scoped.
- **Policy**: aturan izin yang tidak cukup berupa satu permission code
  (mis. "boleh mengedit karyawan di departemen ini").
- **View**: hanya HTML + pemanggilan `e()`/`t()`. Tidak mengambil data sendiri.

## 5. Multi-tenant

1. `tenant_id` ditentukan server dari sesi + membership aktif, **bukan** dari request.
2. `company_id` aktif divalidasi ulang setiap kali dipilih/diganti.
3. Setiap operasi baca/tulis pada tabel tenant-scoped menyertakan `tenant_id = ?`.
4. Validasi lintas relasi: karyawan, perusahaan, departemen, jabatan, dan atasan harus
   berada pada tenant yang sama sebelum relasi dibuat.
5. Pesan error tidak boleh membocorkan keberadaan data tenant lain
   (gunakan 404 generik untuk "tidak ditemukan" dan "tidak diizinkan").
6. Export, pencarian, autocomplete, unduhan dokumen, dan laporan tunduk pada aturan sama.

## 6. Aturan SQL portabel (wajib)

Agar kode dapat diuji tanpa MySQL sekaligus tetap benar di MySQL 8:

1. Tidak memakai fungsi tanggal SQL (`NOW()`, `CURDATE()`, `DATE_ADD()`, `INTERVAL`).
   Batas tanggal/waktu dihitung di PHP (UTC) lalu diikat sebagai parameter.
2. Tidak memakai `LIMIT offset, limit` → gunakan `LIMIT ? OFFSET ?`.
3. Tidak memakai `INSERT ... ON DUPLICATE KEY UPDATE` → gunakan `INSERT` bersyarat atau
   `UPDATE ... WHERE` pada jalur kritis (lebih aman untuk idempotensi).
4. Tidak memakai fungsi JSON MySQL; metadata disimpan sebagai JSON text dan diproses PHP.
5. Tidak memakai `GROUP_CONCAT(... SEPARATOR ...)`, `IFNULL` → gunakan `COALESCE`.
6. Nama kolom menghindari kata kunci cadangan MySQL & SQLite
   (`order`→`sort_order`, `level`→`unit_level`, `key`→`code`).
7. `SELECT` menyebutkan kolom, bukan `SELECT *`, untuk daftar besar.
8. Semua nilai dari input diikat sebagai parameter; nama tabel/kolom dinamis hanya dari
   whitelist (mis. kolom urutan pada tabel).
9. Migrasi ditulis dengan gaya seragam agar dapat diterjemahkan ke SQLite oleh
   `SqliteDialect` (tooling/test saja).

## 7. Penanganan error

- `APP_DEBUG=true` → tampilkan detail di lingkungan development.
- `APP_DEBUG=false` → halaman error generik + pencatatan ke log dengan `request_id`.
- Exception turunan `HttpException` membawa status HTTP yang jelas (403/404/422).
- Tidak ada `die()`/`var_dump()` di jalur produksi.

## 8. Pengujian

Lihat `docs/ENVIRONMENT.md` bagian 3 dan `docs/ROADMAP.md`.
