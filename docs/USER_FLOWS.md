# Alur Pengguna — MO (MyOffice)

## 1. Registrasi tenant

```
Landing → /register
  → validasi (nama, email, password kuat, nama tenant, slug)
  → users (status=active|pending_verification) + tenants + tenant_memberships(owner)
    + subscription(trialing) + perusahaan pertama (opsional, bisa dilewati)
  → kirim token verifikasi email
  → /onboarding (checklist: verifikasi email, profil perusahaan, undang pengguna,
     struktur organisasi, import karyawan)
  → dashboard
```
Idempotensi: slug tenant dan email bersifat unik; permintaan ganda tidak membuat tenant
kedua (ditolak dengan pesan validasi).

## 2. Login

```
/login → cek throttle (login_attempts) → cek status akun → password_verify
  → session_regenerate_id → user_sessions
  → ambil tenant_memberships aktif
      - 0 tenant  → /no-access
      - 1 tenant  → set tenant aktif → lanjut
      - >1 tenant → /select-tenant
  → ambil perusahaan yang boleh diakses
      - 1 perusahaan → set aktif → dashboard
      - >1           → set default (pertama) → dashboard (topbar menyediakan pemilih)
```
Semua kegagalan dicatat pada `login_attempts`; 5 gagal berturut-turut mengunci akun
15 menit (`users.locked_until`).

## 3. Verifikasi email & reset password

- `/verify-email/{token}` → token_hash dicari, belum kedaluwarsa, belum dipakai →
  `users.email_verified_at` diisi, token ditandai `used_at`.
- `/forgot-password` → selalu memberi respons generik (tidak membocorkan keberadaan email).
- `/reset-password/{token}` → token berlaku 60 menit, sekali pakai, semua sesi
  pengguna dicabut setelah berhasil.

## 4. Setup organisasi (3 panel)

```
/companies/{id}/organization
  panel 1: Unit      → pilih unit
  panel 2: Departemen (mengikuti unit) → pilih departemen
  panel 3: Jabatan   (mengikuti departemen)
```
- Tambah unit → validasi kode unik per perusahaan, tipe, parent (anti-siklus).
- Pindah unit → seluruh departemen & jabatan di bawahnya mengikuti; dicatat di audit log.
- Arsip unit → berstatus `archived`; tidak muncul pada dropdown aktif; riwayat tetap ada.
- Setiap panel: pencarian, tombol tambah, menu tindakan, loading, empty state.

## 5. Menambahkan karyawan

```
/employees/create
  → data personal (nomor induk wajib & unik per tenant, nama, email opsional)
  → data pekerjaan (perusahaan, unit, departemen, jabatan, atasan, tanggal efektif)
  → simpan dalam transaksi: employees + employee_details + employee_assignments(active)
  → opsional kirim undangan akun (membuat invitations)
  → /employees/{id} (profil: ringkasan, penempatan, dokumen, catatan, riwayat)
```
- Akun login **bukan** syarat.
- Import CSV: unggah → validasi per baris → pratinjau baris gagal → konfirmasi →
  transaksi; baris gagal dapat diunduh kembali.

## 6. Pengajuan cuti

```
/leave/requests/create → pilih jenis & tanggal
  → cek kebijakan (min hari kerja sebelum, kuota, lampiran wajib, bentrok dengan cuti lain)
  → hitung hari kerja (menghitung hari libur & hari kerja perusahaan)
  → simpan leave_requests(status=pending) + leave_approvals(levels)
  → notifikasi ke atasan
Atasan: /leave/approvals → tinjau → approved/rejected (transaksi + idempotency)
  → saldo diperbarui SEKALI (penjagaan: status transisi + idempotency_key + kondisi WHERE status=pending)
  → notifikasi ke pemohon
```
Saldo tidak boleh berkurang dua kali: pembaruan memakai
`UPDATE leave_requests SET status='approved' WHERE id=? AND status='pending'` lalu
pembaruan saldo hanya bila `rowCount() === 1`.

## 7. Pengelolaan pengguna & akses

```
/users → undang (email + role + cakupan perusahaan) → invitations (token 7 hari)
  → penerima membuka /invitations/{token} → set nama & password → tenant_memberships aktif
/users/{id} → ubah role, cakupan perusahaan, nonaktifkan, cabut sesi
/roles → daftar role & permission (role sistem hanya baca)
```

## 8. Pengelolaan langganan

```
/settings/subscription → lihat paket aktif & pemakaian (karyawan aktif, perusahaan,_storage)
  → pilih paket → sistem cek batas (tidak boleh downgrade di bawah pemakaian aktif)
  → buat payments(status=pending) + invoice_number
  → konfirmasi pembayaran HANYA melalui: (a) webhook/callback gateway yang diverifikasi,
    atau (b) persetujuan manual platform admin (dicatat di audit log)
  → subscription_events + perubahan status
```
Frontend tidak pernah bisa mengaktifkan paket berbayar sendiri.

## 9. Platform admin

```
/platform → daftar tenant (filter status), detail tenant (paket, pemakaian, riwayat)
/platform/plans → CRUD paket & fitur
/platform/subscriptions → ubah status, catat event
/platform/support → buat support_access_grants (alasan + kedaluwarsa) → tercatat
/platform/audit → log platform
```

## 10. Alur absensi (tahap lanjutan)

```
Jadwal kerja → shift → hari libur → penugasan jadwal ke karyawan
Check-in/out (web) → attendance_records → status hadir/terlambat dihitung dari grace period
Koreksi: karyawan mengajukan → atasan/HR menyetujui → attendance_corrections
Rekap bulanan per perusahaan/departemen
```
