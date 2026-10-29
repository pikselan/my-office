# Audit Lingkungan Kerja (Sandbox)

Dokumen ini mencatat kondisi nyata lingkungan development, keterbatasannya, dan strategi
pengujian yang dipakai agar klaim "sudah diuji" dapat dipertanggungjawabkan.

## 1. Kondisi sandbox (hasil audit 2026-10-29)

| Komponen | Hasil pemeriksaan |
|---|---|
| Sistem operasi | Debian GNU/Linux 12 (bookworm), 2 vCPU, 4 GB RAM |
| PHP terinstal | **Tidak ada** (`php` tidak ditemukan) |
| MySQL / MariaDB | **Tidak ada** |
| Apache / Nginx | **Tidak ada** |
| Composer / PHPUnit | **Tidak ada** |
| `apt-get` | Tidak dapat digunakan: seluruh mirror `deb.debian.org` diblokir jaringan |
| Akar akses | `sudo` tersedia, tetapi tidak ada paket yang bisa diunduh |
| Jaringan keluar | Hanya `github.com`, `codeload.github.com`, `api.github.com`, `registry.npmjs.org`, `pypi.org`, `files.pythonhosted.org` |
| Release binary GitHub | Tidak dapat diunduh (redirect ke `objects.githubusercontent.com` / `release-assets.githubusercontent.com` → diblokir) |
| Header dev | `ncurses.h`, `openssl/ssl.h`, `zlib.h`, `curl/curl.h` **tidak ada** → build MariaDB/MySQL dari source tidak layak |
| Toolchain | `gcc 12`, `g++`, `make`, `git`, `curl`, `wget`, `python3 3.11`, `node 22`, `npm` tersedia |

Kesimpulan: **MySQL Server dan Apache tidak dapat dijalankan di sandbox ini.**
PHP juga tidak tersedia secara native.

## 2. Solusi yang dipakai

### 2.1 PHP
Dipakai **PHP 8.5.10 CLI** dari `@php-wasm/cli` (WordPress playground) yang berjalan di atas Node.
Terbukti menyediakan:

- `php -l` (lint), `php -r`, eksekusi script file
- `PDO` dengan driver `mysql` **dan** `sqlite`
- ekstensi: `mbstring`, `json`, `session`, `openssl`, `fileinfo`, `gd`
- `password_hash()` / `password_verify()`, `random_bytes()`
- **built-in web server**: `php-wasm-cli -S 0.0.0.0:PORT -t public` (terbukti melayani
  GET/POST, `$_GET`, `$_POST`, `$_COOKIE`, `php://input`, session, header)

Alias: `tools/php` → pembungkus agar seluruh perintah tetap berbentuk `php ...`.

### 2.2 Database saat pengujian dan preview: SQLite
Karena tidak ada MySQL, seluruh pengujian yang benar-benar dieksekusi dan live preview
menjalankan **SQLite** melalui `PDO`. Konsekuensi desain yang diambil:

1. **SQL aplikasi ditulis dalam subset portabel** MySQL/SQLite (aturan lengkap ada di
   `docs/ARCHITECTURE.md` bagian "Aturan SQL portabel"). Ini bukan sekadar kompromi
   sandbox: subset ini menghindari ketergantungan pada fungsi tanggal MySQL dan membuat
   seluruh batasan tanggal dihitung di PHP (UTC), sehingga lebih mudah diuji.
2. **Skema tetap ditulis dalam DDL MySQL 8** (`database/migrations/*.sql`) sebagai sumber
   kebenaran untuk produksi.
3. Untuk SQLite, skema diterjemahkan oleh `app/Core/Database/SqliteDialect.php`
   (khusus tooling/test). Tidak ada duplikasi skema, tidak ada drift.
4. `DB_DRIVER=mysql` tetap menjadi konfigurasi produksi dan **satu-satunya** jalur yang
   didukung untuk rilis.

### 2.3 Validasi SQL MySQL
Selain dieksekusi dalam SQLite, seluruh migrasi diperiksa dengan parser MySQL
(`sqlglot`, dialect `mysql`) pada tahap `bin/mo sql:check`, sehingga kesalahan sintaks
MySQL tetap tertangkap meski tidak ada server MySQL.

## 3. Matriks pengujian: apa yang benar-benar dijalankan

| Jenis uji | Dijalankan? | Cara |
|---|---|---|
| Lint sintaks PHP (`php -l`) | ✅ Ya | `bin/mo lint` |
| Unit test logika murni (validator, hierarki, saldo cuti, limit paket, i18n, RBAC, slug) | ✅ Ya | `bin/mo test` |
| Test skema: migrasi bisa dieksekusi berurutan dari nol | ✅ Ya (SQLite) | `bin/mo test` |
| Test integrasi repository + isolasi tenant | ✅ Ya (SQLite) | `bin/mo test` |
| Test keamanan (IDOR, CSRF, XSS escape, upload, akses tanpa login) | ✅ Sebagian, lihat catatan | `bin/mo test` |
| Sintaks DDL MySQL | ✅ Ya (parser) | `bin/mo sql:check` |
| Uji dengan MySQL 8 asli | ❌ **Tidak bisa** | tidak ada server |
| Uji di Apache | ❌ **Tidak bisa** | tidak ada Apache |
| Uji beban / performa | ❌ Belum | butuh MySQL + server nyata |
| Uji browser nyata (responsive) | ⚠️ Parsial | preview manual, bukan otomatis |

Semua hasil uji dicatat apa adanya di `docs/PROGRESS.md`. Tidak ada uji yang diklaim
berhasil bila tidak dijalankan.

## 4. Yang harus dikerjakan pemilik proyek sebelum rilis

1. Jalankan `bin/mo migrate` dan `bin/mo test` pada server **MySQL 8** yang sesungguhnya.
2. Jalankan smoke test manual di Apache + HTTPS.
3. Verifikasi query plan (`EXPLAIN`) untuk daftar karyawan dan laporan dengan data besar.
