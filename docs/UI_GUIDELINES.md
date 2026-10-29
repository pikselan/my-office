# Panduan Antarmuka — MO (MyOffice)

## 1. Identitas visual

- Nama: **MO (MyOffice)**. Logo: monogram "MO" dalam rounded square ungu, tanpa meniru
  merek pihak lain.
- Palet:
  - `--mo-primary: #6D28D9` (ungu), hover `#5B21B6`, soft `#F5F3FF`
  - `--mo-bg: #F7F8FA` (abu-abu sangat muda), `--mo-surface: #FFFFFF`
  - `--mo-border: #E5E7EB`, `--mo-text: #111827`, `--mo-muted: #6B7280`
  - status: sukses `#059669` · peringatan `#D97706` · bahaya `#DC2626` · info `#2563EB`
- Tipografi: system stack (`-apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial`),
  ukuran dasar 14px, heading 20/24/28px.
- Radius 8px, bayangan sangat halus, border 1px.
- Ikon: inline SVG 20×20 stroke 1.5, tanpa dependensi eksternal.

## 2. Layout

| Elemen | Desktop | Mobile |
|---|---|---|
| Sidebar | 248px, dapat diciutkan ke 64px (ikon saja) | drawer overlay, lebar 280px |
| Topbar | 64px: judul halaman · pemilih perusahaan · pemilih bahasa · notifikasi · profil | tetap, tombol hamburger di kiri |
| Konten | padding 24px, lebar maksimal 1440px | padding 16px |
| Tabel | scroll horizontal bila perlu; kolom aksi di kanan | mode kartu bila ≤576px |
| Modal | lebar 480/640/800px, overlay gelap 40% | tinggi maksimal 90vh, tombol tetap terlihat |

Struktur halaman: breadcrumb → judul + aksi kanan → kartu/filter → tabel/konten.

## 3. Komponen wajib (resources/views/components)

`button`, `input`, `select`, `textarea`, `checkbox`, `radio`, `toggle`, `modal`,
`dropdown`, `table`, `pagination`, `badge`, `toast`, `confirm-dialog`, `empty-state`,
`loading-state`, `error-state`, `breadcrumb`, `search-box`, `filter-bar`, `file-uploader`,
`stat-card`, `avatar`, `tabs`, `panel-card`.

Aturan: komponen dipanggil dari view lain; tidak ada dua implementasi berbeda untuk
komponen yang sama.

## 4. Status komponen

Setiap daftar wajib punya: **loading** (skeleton/ spinner), **empty** (ilustrasi + ajakan),
**error** (pesan + tombol coba lagi). Tidak ada daftar yang hanya menampilkan "tidak ada data"
tanpa konteks.

## 5. Formulir

- Label selalu tampil di atas input; tanda `*` untuk wajib.
- Validasi sisi klien untuk umpan balik cepat **dan** validasi server sebagai sumber
  kebenaran; pesan server ditampilkan di bawah field terkait.
- Tombol simpan berubah menjadi "Menyimpan…" dan dinonaktifkan saat request berjalan
  (mencegah pengiriman ganda).
- Konfirmasi untuk tindakan destruktif (arsip, hapus, tolak).
- Token CSRF pada setiap form POST/PUT/DELETE.

## 6. Tabel

Pencarian (debounce 300ms) · filter · urutan kolom (whitelist) · pagination server-side
(10/25/50/100) · aksi mengikuti permission · nomor halaman dan total ditampilkan.

## 7. Aturan interaksi

- Tidak ada tombol yang tidak berfungsi. Fitur belum tersedia ditandai badge
  "Segera hadir" dan dinonaktifkan, atau tidak ditampilkan.
- Tidak ada angka hardcode pada dashboard; semua berasal dari query.
- Tidak ada grafik dengan data contoh di produksi.
- Toast untuk keberhasilan/kegagalan; pesan mengikuti bahasa aktif.

## 8. Bahasa

Semua label melalui `t('key')`. Bahasa default `id`, fallback `id` → `en`.
Tanggal, angka, dan mata uang diformat berdasarkan locale + timezone perusahaan.

## 9. Halaman utama (daftar)

Auth: `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`,
`/verify-email/{token}`, `/select-tenant`, `/invitations/{token}`, `/no-access`.
Aplikasi: `/dashboard`, `/companies`, `/companies/{id}`, `/companies/{id}/edit`,
`/companies/{id}/organization`, `/employees`, `/employees/create`, `/employees/{id}`,
`/employees/{id}/edit`, `/employees/import`, `/users`, `/users/{id}`, `/roles`,
`/onboarding`, `/announcements`, `/attendance`, `/attendance/recap`, `/schedules`,
`/holidays`, `/leave`, `/leave/requests/create`, `/leave/approvals`, `/leave/calendar`,
`/recruitment`, `/recruitment/jobs/{id}`, `/recruitment/candidates/{id}`,
`/payroll`, `/payroll/periods/{id}`, `/payslips`, `/finance/reimbursements`,
`/finance/cash-advances`, `/finance/loans`, `/reports`, `/settings/*`,
`/notifications`, `/platform/*`.
