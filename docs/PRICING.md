# Model Harga & Langganan — MO (MyOffice)

Seluruh harga, batas, dan fitur paket tersimpan di tabel `plans` dan `plan_features`,
dapat diubah platform admin tanpa mengubah kode. Nilai berikut adalah **konfigurasi awal**
yang di-seed, bukan nilai yang ditanam di source code.

## 1. Paket

| Paket | Bulanan | Tahunan | Karyawan aktif | Perusahaan | Fitur inti |
|---|---|---|---|---|---|
| Starter | Rp99.000 | Rp990.000 | 25 | 1 | Data karyawan, struktur organisasi, dashboard dasar, pengaturan visibilitas, laporan dasar |
| Business | Rp249.000 | Rp2.490.000 | 100 | 3 | Starter + cuti, absensi dasar, onboarding, import/export, role lanjutan, laporan tambahan |
| Growth | Rp499.000 | Rp4.990.000 | 300 | 10 | Business + rekrutmen, analitik lanjutan, workflow tambahan |
| Enterprise | Berdasarkan penawaran | — | custom | custom | Semua fitur, SLA, integrasi khusus, opsi isolasi data |

Kode fitur (`plan_features.feature_code`):
`module.organization`, `module.leave`, `module.attendance`, `module.onboarding`,
`module.announcement`, `module.recruitment`, `module.payroll`, `module.finance`,
`module.reports.advanced`, `feature.import_export`, `feature.custom_roles`,
`feature.api_access`.

## 2. Trial

- Trial 14 hari (`plans.trial_days`), dapat dimatikan dari pengaturan platform.
- Tidak meminta data kartu pembayaran pada alur trial default.
- Saat trial berakhir: status berubah `expired`; akses baca tetap terbatas,
  operasi tulis yang menambah data diblokir oleh `FeatureGate` sampai paket dipilih.

## 3. Aturan billing

1. Harga & batan paket dari database.
2. Invoice menyimpan **snapshot** harga (`payments.plan_name_snapshot`, `amount`, periode)
   sehingga perubahan harga tidak mengubah invoice lama.
3. Karyawan aktif dihitung dari `employees.employment_status IN ('active','probation')`
   dan `deleted_at IS NULL`.
4. Perubahan paket berlaku pada awal periode berikutnya (upgrade: segera, dengan
   prorata dicatat sebagai `subscription_events`).
5. Downgrade ditolak bila pemakaian saat ini melampaui batas paket baru.
6. Kelebihan pemakaian: sistem **tidak** menghapus data; operasi tambah diblokir dan
   pengguna diminta upgrade.
7. Grace period keterlambatan: 7 hari (`subscriptions.grace_ends_at`), status `past_due`,
   setelah itu `suspended` (baca saja) lalu `expired`.
8. Pembatalan: `cancelled_at` diisi, akses berlanjut sampai `current_period_end`.
9. Tidak ada penghapusan data ketika tenant berhenti berlangganan; ekspor data tersedia.
10. Retensi: data arsip disimpan 90 hari setelah `expired` sebelum pembersihan manual.

## 4. Status langganan

`trialing` → `active` → `past_due` → (`suspended` | `active`) → `cancelled` → `expired`.

## 5. Pembayaran

- Belum ada payment gateway yang dipilih. Antarmuka disiapkan
  (`app/Services/Billing/*PaymentGatewayInterface*`) dengan implementasi
  `ManualGateway` (konfirmasi oleh platform admin, tercatat di audit log).
- Integrasi gateway ditandai **belum aktif** dan tidak mengklaim dukungan gateway tertentu.
- Tidak ada penyimpanan data kartu; hanya `invoice_number`, `amount`, `status`, `method`,
  `paid_at`.
- Status pembayaran hanya berubah dari: callback terverifikasi (belum ada) atau aksi
  platform admin. Frontend tidak dapat menandai pembayaran lunas.

## 6. Feature gating

`FeatureGate::enabled('module.leave')`, `FeatureGate::canAddEmployee()`,
`FeatureGate::canAddCompany()`, `FeatureGate::withinStorage()` — diperiksa pada service
dan middleware, bukan hanya dengan menyembunyikan menu.
