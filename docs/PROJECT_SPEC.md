# Spesifikasi Sistem — MO (MyOffice)

## 1. Ringkasan kebutuhan bisnis

MO adalah aplikasi HRIS berbasis web dengan model SaaS multi-tenant yang ditujukan untuk
perusahaan Indonesia (UMKM, perusahaan berkembang, bisnis multi-outlet, grup usaha).

Satu pelanggan (tenant) dapat mengelola satu atau banyak perusahaan. Tenant yang berbeda
tidak boleh saling mengakses data. Pengguna di dalam satu tenant hanya boleh mengakses
perusahaan yang termasuk dalam cakupan izinnya.

Tujuan produk:

1. Mengelola data perusahaan dan struktur organisasi (unit, departemen, jabatan).
2. Mengelola data karyawan beserta riwayat penempatan kerja.
3. Mengelola akun pengguna, role, dan permission secara terpusat.
4. Mengelola proses HR: onboarding, pengumuman, cuti, absensi, rekrutmen.
5. Mengelola payroll dan transaksi keuangan karyawan (tahap lanjutan).
6. Menyediakan laporan dan analitik yang mengikuti hak akses.
7. Menyediakan administrasi platform bagi operator MO (tenant, paket, langganan).

## 2. Batasan penting

- Tenant ≠ Perusahaan ≠ Pengguna ≠ Karyawan. Keempatnya entitas berbeda.
- Karyawan tidak wajib memiliki akun login.
- Perubahan jabatan/penempatan tidak boleh menghapus riwayat.
- Data penting tidak dihapus permanen (soft delete / arsip).
- Seluruh angka pada dashboard berasal dari database, tidak ada hardcode.
- Harga paket dikonfigurasi di database, bukan di source code.
- Fitur yang belum selesai tidak ditampilkan sebagai fitur yang berfungsi.

## 3. Daftar modul dan fitur

### 3.1 Platform administration (operator MO)
Dashboard platform · daftar & detail tenant · status tenant (trialing/active/past_due/
suspended/cancelled/expired) · paket langganan (CRUD) · batas karyawan, perusahaan,
penyimpanan · riwayat perubahan paket · riwayat pembayaran · pengaturan sistem ·
suspensi/aktivasi · log audit platform.
Akses data HR pelanggan oleh platform support memakai mekanisme **support access grant**
berjangka dan tercatat.

### 3.2 Tenant onboarding
Registrasi owner · verifikasi email (token berkedaluwarsa) · login · reset password ·
pembuatan tenant · perusahaan pertama · pemilihan paket/trial · checklist setup ·
undang pengguna · import karyawan · opsi lewati langkah.

### 3.3 Dashboard
Kartu ringkasan sesuai role: jumlah karyawan aktif, jumlah perusahaan, jumlah unit,
pengajuan cuti menunggu persetujuan, jadwal hari ini, onboarding berjalan, ulang tahun &
masa kerja (jika visibilitas diaktifkan), aktivitas terbaru, pengumuman aktif.

### 3.4 Company management
CRUD perusahaan · logo · nama resmi & nama tampilan · NPWP · alamat · situs · kontak ·
bahasa · timezone · format tanggal · hari kerja · status · arsip · pemilih perusahaan
pada topbar. Kolom legal boleh kosong.

### 3.5 Organization management
Unit organisasi (branch/division/department terstruktur) · departemen · jabatan ·
atasan langsung · kode · jumlah anggota · riwayat perubahan · pemindahan unit ·
pengarsipan · pencarian · filter · validasi hierarki (anti siklus).
Halaman utama memakai 3 panel saling terhubung: Unit → Departemen → Jabatan.

### 3.6 Employee management
Nomor induk · nama lengkap & panggilan · foto · email · telepon · data personal ·
penempatan kerja (perusahaan, unit, departemen, jabatan, atasan, lokasi) · status
kepegawaian & tipe · tanggal bergabung & akhir kontrak · dokumen · riwayat pekerjaan ·
catatan · import/export CSV · validasi duplikasi · arsip · akun login opsional.

### 3.7 Visibility pengaturan informasi
Perusahaan dapat menyembunyikan: Birthday, Anniversary, Leave information,
Employee WhatsApp, Employee Email, Employee Directory.
Penyembunyian di UI **selalu** dibarengi pengecekan di backend.

### 3.8 User & access management
Undang pengguna · buat akun · aktivasi/nonaktif · reset password · role · permission ·
cakupan akses perusahaan · riwayat login · manajemen sesi · logout · undangan kedaluwarsa.

### 3.9 Onboarding & workflow
Template onboarding · checklist tugas · penanggung jawab · status · dokumen wajib ·
tenggat · riwayat tindakan. Workflow sederhana dan dapat dikonfigurasi, bukan engine generik.

### 3.10 Announcement
Buat pengumuman · target perusahaan/unit · jadwal publikasi · masa berlaku ·
status draft/published/archived · riwayat publikasi · batas tenant.

### 3.11 Scheduling & attendance
Jadwal kerja · shift · hari libur · check-in/check-out · rekap kehadiran · keterlambatan ·
koreksi absensi · lokasi kerja · sumber absensi · rekap bulanan.

### 3.12 Leave management
Jenis cuti · kebijakan · saldo · pengajuan · persetujuan/penolakan/pembatalan · kalender ·
riwayat · notifikasi. Pemotongan saldo memakai transaksi + idempotency key agar tidak ganda.

### 3.13 Recruitment
Lowongan · kandidat · database pelamar · tahapan seleksi · catatan interview · penilaian ·
offering · diterima/ditolak · konversi kandidat menjadi karyawan.

### 3.14 Finance & payroll
Komponen gaji · tunjangan · potongan · bonus · periode payroll · perhitungan · slip gaji ·
reimbursement · cash advance · loan · angsuran · riwayat pembayaran · export akuntansi.

### 3.15 Reports & analytics
Laporan karyawan, struktur organisasi, status kepegawaian, cuti, kehadiran, rekrutmen ·
export CSV · filter periode/perusahaan/departemen · permission laporan.

### 3.16 System settings
Profil tenant · logo · bahasa · timezone · format tanggal · notifikasi · visibilitas ·
keamanan · modul aktif · role · konfigurasi paket.

## 4. Kriteria penerimaan produk siap rilis

Mengikuti BAGIAN 18 dokumen master:
seluruh alur utama dapat dijalankan · tidak ada tombol utama mati · tidak ada data bisnis
hardcoded · tidak ada akses silang tenant · migration dapat dipasang dari nol ·
dokumen privat terlindungi · tidak ada kredensial di source code · instalasi & upgrade
terdokumentasi · keterbatasan dicatat jujur.
