# Rencana Keamanan — MO (MyOffice)

## 1. Autentikasi

- `password_hash(PASSWORD_BCRYPT)` (default PHP) / `password_verify`; rehash otomatis bila
  algoritma berubah (`password_needs_rehash`).
- `session_regenerate_id(true)` saat login dan saat perubahan hak.
- Cookie sesi: `HttpOnly`, `Secure` bila HTTPS, `SameSite=Lax`, nama `MOSESSID`.
- Sesi kedaluwiranya absolut (`SESSION_LIFETIME`, default 8 jam) dan idle (2 jam).
- Token reset password & verifikasi email: 32 byte acak, **disimpan sebagai hash SHA-256**,
  berlaku 60 menit (reset) / 24 jam (verifikasi), sekali pakai.
- Undangan: token 32 byte, hash, berlaku 7 hari, dapat dicabut.
- Throttling login: 5 percobaan gagal → kunci 15 menit; pencatatan di `login_attempts`.
- Logout menghapus baris `user_sessions` dan menghancurkan sesi.

## 2. Otorisasi & isolasi tenant

- `tenant_id` hanya dari sesi (server), tidak pernah dari body/query.
- Setiap query pada tabel tenant-scoped menyertakan `tenant_id = ?`.
- Validasi lintas relasi sebelum menyimpan relasi (perusahaan, unit, departemen, jabatan,
  atasan, karyawan harus satu tenant).
- Response untuk "tidak ditemukan" dan "tidak diizinkan" dibuat identik (404 generik)
  agar tidak membocorkan keberadaan data.
- `EmployeePolicy` membatasi manager hanya pada bawahan langsungnya.
- Uji otomatis: `tests/Security/TenantIsolationTest.php`.

## 3. CSRF

- Token per sesi, disematkan pada setiap form dan dikirim pada header
  `X-CSRF-Token` untuk request fetch/AJAX.
- Middleware `CsrfMiddleware` menolak request state-changing tanpa token sah
  (perbandingan `hash_equals`).

## 4. XSS

- Semua output melalui `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- Atribut `href`/`src` divalidasi (hanya `#`, `/`, `http(s)://`, `mailto:`, `tel:`).
- Tidak ada `innerHTML` dari data tak terpercaya di JS; memakai `textContent`.

## 5. SQL injection

- Seluruh nilai melalui prepared statement (`?` atau `:name`).
- Nama kolom/tabel dinamis hanya dari whitelist (`app/Core/Database/Query.php`).
- Tidak ada penggabungan string input ke SQL.

## 6. Upload berkas

- Batas ukuran (default 5 MB), ekstensi whitelist, validasi MIME nyata (`finfo`).
- Nama berkas acak (`bin2hex(random_bytes(16))`), tanpa path dari pengguna.
- Disimpan di `storage/private/` (di luar document root) dengan `.htaccess deny` dan
  `index.html` kosong sebagai lapisan tambahan.
- Unduhan hanya lewat `DocumentController` yang memeriksa permission;
  pengiriman memakai header `X-Content-Type-Options: nosniff`,
  `Content-Disposition: attachment`.
- Validasi path: `realpath()` harus berada di dalam direktori storage.

## 7. Manajemen rahasia

- Konfigurasi dari `.env` (tidak di-commit); `.env.example` tanpa kredensial sungguhan.
- Tidak ada kredensial pada kode, migrasi, atau seed.
- `APP_KEY` dipakai untuk enkripsi nilai sensitif (data bank) bila diaktifkan.
- Rotasi: ganti `APP_KEY` + `DB_PASSWORD`, lalu hapus `storage/cache/*`.

## 8. Header keamanan

`X-Frame-Options: SAMEORIGIN` · `X-Content-Type-Options: nosniff` ·
`Referrer-Policy: strict-origin-when-cross-origin` ·
`Content-Security-Policy` (tanpa inline script; JS eksternal + `nonce` bila diperlukan) ·
`Strict-Transport-Security` hanya bila HTTPS.

## 9. Audit & privasi

Dicatat: login/ logout penting, gagal autentikasi, perubahan role/permission, perubahan
data karyawan, perubahan organisasi, perubahan paket/langganan, akses dukungan, unduhan
dokumen sensitif, operasi administratif.
Tidak dicatat: password, token mentah, isi dokumen, seluruh body request.
Retensi log default 365 hari (`bin/mo jobs:run` membersihkan yang lebih tua).

## 10. Race condition & idempotensi

- Persetujuan cuti, pemotongan saldo, dan konfirmasi pembayaran memakai transaksi +
  `UPDATE ... WHERE status='pending'` dan hanya melanjutkan bila tepat satu baris terpengaruh.
- `leave_requests.idempotency_key` unik per tenant.
- Import karyawan diproses dalam satu transaksi per batch.

## 11. Daftar uji keamanan (dijalankan oleh `bin/mo test`)

1. Akses tanpa login → redirect 302 ke `/login`.
2. Manipulasi `tenant_id` pada body → diabaikan, data tetap terisolasi.
3. Akses record tenant lain by ID → 404.
4. Akses perusahaan tanpa `user_company_access` → 403.
5. POST tanpa token CSRF → 422.
6. Unggahan berkas berbahaya (ekstensi/MIME) → ditolak.
7. Unduh dokumen tenant lain → 404.
8. Escape HTML pada nama karyawan → tidak ada eksekusi.
9. Percobaan login berulang → terkunci setelah ambang.
10. Pengguna tanpa permission memanggil endpoint langsung → 403.
