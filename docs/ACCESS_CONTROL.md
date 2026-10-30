# Kontrol Akses — Role & Permission

Model: RBAC dengan cakupan **tenant** dan **perusahaan**.
Satu pengguna dapat memiliki beberapa role; role dapat dibatasi pada perusahaan tertentu
(`user_roles.company_id`). Efektifnya: kumpulan permission = gabungan seluruh role aktif
pengguna pada tenant tersebut, **dipotong** oleh cakupan perusahaan bila role/akses
perusahaan membatasi.

## 1. Enam aturan dasar

1. Izin selalu diperiksa di backend (`Authorization::can()`), bukan hanya di view.
2. Permission pada level tenant berlaku untuk seluruh perusahaan tenant bila
   `user_company_access` kosong; bila terisi, hanya perusahaan tersebut.
3. Role `tenant.owner` tidak otomatis berarti role platform.
4. Platform admin **tidak** otomatis dapat membaca data HR pelanggan.
5. Perubahan role/permission wajib tercatat pada `audit_logs`.
6. Penguncian owner dicegah: tenant selalu harus punya minimal satu anggota dengan role
   `tenant.owner` berstatus aktif (divalidasi pada `RoleService`).

## 2. Role

| Kode | Nama | Cakupan | Keterangan |
|---|---|---|---|
| `platform.super_admin` | Platform Super Admin | global (`tenant_id NULL`) | Kelola tenant, paket, langganan, pengaturan platform |
| `platform.support` | Platform Support | global | Dukungan terbatas; akses data pelanggan hanya lewat `support_access_grants` aktif |
| `tenant.owner` | Owner | tenant | Pemilik akun SaaS; kelola tenant, perusahaan, pengguna, langganan |
| `tenant.company_admin` | Company Admin | tenant / perusahaan | Kelola perusahaan yang diizinkan |
| `tenant.hr_admin` | HR Admin | tenant / perusahaan | Kelola karyawan, organisasi, onboarding, cuti, absensi |
| `tenant.manager` | Manager / Supervisor | tenant / perusahaan | Lihat & setujui untuk anggota timnya |
| `tenant.finance` | Finance / Payroll | tenant / perusahaan | Kelola payroll & transaksi keuangan |
| `tenant.employee` | Employee | tenant / perusahaan | Profil sendiri, ajukan cuti, lihat jadwal & slip gaji sendiri |

## 3. Daftar permission

| Modul | Kode |
|---|---|
| Platform | `platform.tenant.view`, `platform.tenant.manage`, `platform.plan.manage`, `platform.subscription.manage`, `platform.settings.manage`, `platform.audit.view`, `platform.support.grant` |
| Tenant | `tenant.view`, `tenant.update`, `tenant.settings.manage`, `tenant.subscription.manage`, `tenant.audit.view` |
| Perusahaan | `company.view`, `company.create`, `company.update`, `company.archive`, `company.settings.manage` |
| Organisasi | `organization.view`, `organization.create`, `organization.update`, `organization.archive` |
| Karyawan | `employee.view`, `employee.create`, `employee.update`, `employee.archive`, `employee.import`, `employee.export`, `employee.sensitive.view`, `employee.note.manage` |
| Dokumen | `employee.document.view`, `employee.document.upload`, `employee.document.delete` |
| Pengguna | `user.manage`, `user.invite`, `role.manage`, `user.session.manage` |
| Onboarding | `onboarding.view`, `onboarding.manage` |
| Pengumuman | `announcement.view`, `announcement.create`, `announcement.update`, `announcement.publish`, `announcement.delete` |
| Absensi | `attendance.view`, `attendance.self.view`, `attendance.checkin`, `attendance.correct`, `attendance.manage`, `schedule.manage`, `holiday.manage` |
| Cuti | `leave.view`, `leave.self.view`, `leave.request`, `leave.approve`, `leave.manage`, `leave.balance.manage` |
| Rekrutmen | `recruitment.view`, `recruitment.manage`, `candidate.convert` |
| Payroll | `payroll.view`, `payroll.self.view`, `payroll.process`, `payroll.approve`, `payroll.component.manage` |
| Keuangan | `finance.reimbursement.manage`, `finance.cashadvance.manage`, `finance.loan.manage` |
| Laporan | `report.view`, `report.export` |
| Sistem | `audit.view`, `notification.manage` |

Total 60 permission. Daftar ini di-seed oleh `database/seeds/004_permissions.php`
dan di-cache per request (bukan lintas request, untuk menghindari cache basi).

## 4. Matriks role × permission

| Permission | owner | company_admin | hr_admin | manager | finance | employee |
|---|---|---|---|---|---|---|
| `tenant.*` | ✅ | — | — | — | — | — |
| `company.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `company.create/update/archive` | ✅ | ✅ | — | — | — | — |
| `company.settings.manage` | ✅ | ✅ | — | — | — | — |
| `organization.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `organization.create/update/archive` | ✅ | ✅ | ✅ | — | — | — |
| `employee.view` | ✅ | ✅ | ✅ | tim | ✅ | sendiri |
| `employee.create/update` | ✅ | ✅ | ✅ | — | — | — |
| `employee.archive` | ✅ | ✅ | ✅ | — | — | — |
| `employee.import/export` | ✅ | ✅ | ✅ | — | — | — |
| `employee.sensitive.view` | ✅ | ✅ | ✅ | — | — | — |
| `employee.note.manage` | ✅ | ✅ | ✅ | tim | — | — |
| `employee.document.*` | ✅ | ✅ | ✅ | — | — | sendiri |
| `user.manage/invite` | ✅ | ✅ | — | — | — | — |
| `role.manage` | ✅ | ✅ | — | — | — | — |
| `onboarding.*` | ✅ | ✅ | ✅ | tim | — | — |
| `announcement.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `announcement.create/update/publish/delete` | ✅ | ✅ | ✅ | — | — | — |
| `attendance.view` | ✅ | ✅ | ✅ | tim | — | — |
| `attendance.self.view/checkin` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `attendance.correct/manage` | ✅ | ✅ | ✅ | — | — | — |
| `schedule.manage`, `holiday.manage` | ✅ | ✅ | ✅ | — | — | — |
| `leave.view` | ✅ | ✅ | ✅ | tim | — | — |
| `leave.self.view/request` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `leave.approve` | ✅ | ✅ | ✅ | ✅ | — | — |
| `leave.manage/balance.manage` | ✅ | ✅ | ✅ | — | — | — |
| `recruitment.view` | ✅ | ✅ | ✅ | ✅ | — | — |
| `recruitment.manage`, `candidate.convert` | ✅ | ✅ | ✅ | — | — | — |
| `payroll.view` | ✅ | — | — | — | ✅ | — |
| `payroll.self.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `payroll.process/approve/component.manage` | ✅ | — | — | — | ✅ | — |
| `finance.*` | ✅ | ✅ | — | — | ✅ | sendiri |
| `report.view/export` | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `audit.view` | ✅ | ✅ | — | — | — | — |

Keterangan "tim" = hanya untuk bawahan langsung (ditentukan oleh
`employee_assignments.manager_employee_id` berjalan). "sendiri" = hanya record miliknya.
Aturan "tim" dan "sendiri" diimplementasikan oleh `EmployeePolicy`, bukan oleh permission
saja, sehingga tidak bisa dilewati dengan memanipulasi ID.

## 5. Aturan tambahan non-permission

| Situasi | Aturan |
|---|---|
| Mengunduh dokumen karyawan | wajib `employee.document.view` **dan** lolos `EmployeePolicy::view()`; berkas di luar document root, di-stream oleh `DocumentController` |
| Mengubah role sendiri | dilarang; mencegah penurunan hak tanpa sengaja |
| Menghapus owner terakhir | dilarang oleh `RoleService` |
| Mengganti perusahaan aktif | divalidasi ulang terhadap `user_company_access`/role |
| Super admin platform membaca data HR | hanya bila ada `support_access_grants` aktif; setiap akses dicatat |
| `employee.sensitive.view` | diperlukan untuk NIK, NPWP, data bank, gaji |

## 6. Feature gating (bukan permission)

Terpisah dari RBAC, diperiksa oleh `FeatureGate`:

- `module.organization`, `module.leave`, `module.attendance`, `module.onboarding`,
  `module.announcement`, `module.recruitment`, `module.payroll`, `module.finance`,
  `module.reports.advanced`
- batas `employee_limit`, `company_limit`, `storage_limit_mb`
- status langganan harus `trialing` atau `active` untuk fitur berbayar

Pemeriksaan dilakukan di backend pada service **dan** middleware route.
