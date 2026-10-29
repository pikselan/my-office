<?php

declare(strict_types=1);

/**
 * Daftar permission sistem.
 *
 * Kode permission tidak pernah diterjemahkan dan tidak boleh diubah sembarang
 * karena menjadi acuan otorisasi backend.
 */

use App\Core\Database\SeedSupport;

return static function (PDO $pdo): void {
    $permissions = [
        'platform' => [
            'platform.tenant.view' => 'Melihat daftar tenant',
            'platform.tenant.manage' => 'Mengelola tenant',
            'platform.plan.manage' => 'Mengelola paket langganan',
            'platform.subscription.manage' => 'Mengelola langganan tenant',
            'platform.settings.manage' => 'Mengelola pengaturan platform',
            'platform.audit.view' => 'Melihat log audit platform',
            'platform.support.grant' => 'Memberikan akses dukungan berjangka',
        ],
        'tenant' => [
            'tenant.view' => 'Melihat profil tenant',
            'tenant.update' => 'Mengubah profil tenant',
            'tenant.settings.manage' => 'Mengelola pengaturan tenant',
            'tenant.subscription.manage' => 'Mengelola langganan',
            'tenant.audit.view' => 'Melihat log audit tenant',
        ],
        'company' => [
            'company.view' => 'Melihat perusahaan',
            'company.create' => 'Membuat perusahaan',
            'company.update' => 'Mengubah perusahaan',
            'company.archive' => 'Mengarsipkan perusahaan',
            'company.settings.manage' => 'Mengelola pengaturan perusahaan',
        ],
        'organization' => [
            'organization.view' => 'Melihat struktur organisasi',
            'organization.create' => 'Membuat unit, departemen, atau jabatan',
            'organization.update' => 'Mengubah struktur organisasi',
            'organization.archive' => 'Mengarsipkan bagian struktur organisasi',
        ],
        'employee' => [
            'employee.view' => 'Melihat data karyawan',
            'employee.create' => 'Menambah karyawan',
            'employee.update' => 'Mengubah data karyawan',
            'employee.archive' => 'Mengarsipkan karyawan',
            'employee.import' => 'Impor data karyawan',
            'employee.export' => 'Ekspor data karyawan',
            'employee.sensitive.view' => 'Melihat data sensitif karyawan',
            'employee.note.manage' => 'Mengelola catatan karyawan',
            'employee.document.view' => 'Melihat dokumen karyawan',
            'employee.document.upload' => 'Mengunggah dokumen karyawan',
            'employee.document.delete' => 'Menghapus dokumen karyawan',
        ],
        'user' => [
            'user.manage' => 'Mengelola pengguna',
            'user.invite' => 'Mengundang pengguna',
            'role.manage' => 'Mengelola role dan permission',
            'user.session.manage' => 'Mengelola sesi pengguna',
        ],
        'onboarding' => [
            'onboarding.view' => 'Melihat proses onboarding',
            'onboarding.manage' => 'Mengelola proses onboarding',
        ],
        'announcement' => [
            'announcement.view' => 'Melihat pengumuman',
            'announcement.create' => 'Membuat pengumuman',
            'announcement.update' => 'Mengubah pengumuman',
            'announcement.publish' => 'Mempublikasikan pengumuman',
            'announcement.delete' => 'Menghapus pengumuman',
        ],
        'attendance' => [
            'attendance.view' => 'Melihat kehadiran seluruh tim',
            'attendance.self.view' => 'Melihat kehadiran sendiri',
            'attendance.checkin' => 'Melakukan check-in dan check-out',
            'attendance.correct' => 'Mengajukan koreksi absensi',
            'attendance.manage' => 'Mengelola data kehadiran',
            'schedule.manage' => 'Mengelola jadwal kerja',
            'holiday.manage' => 'Mengelola hari libur',
        ],
        'leave' => [
            'leave.view' => 'Melihat pengajuan cuti',
            'leave.self.view' => 'Melihat cuti sendiri',
            'leave.request' => 'Mengajukan cuti',
            'leave.approve' => 'Menyetujui atau menolak cuti',
            'leave.manage' => 'Mengelola kebijakan cuti',
            'leave.balance.manage' => 'Mengelola saldo cuti',
        ],
        'recruitment' => [
            'recruitment.view' => 'Melihat data rekrutmen',
            'recruitment.manage' => 'Mengelola lowongan dan kandidat',
            'candidate.convert' => 'Mengonversi kandidat menjadi karyawan',
        ],
        'payroll' => [
            'payroll.view' => 'Melihat data payroll',
            'payroll.self.view' => 'Melihat slip gaji sendiri',
            'payroll.process' => 'Memproses payroll',
            'payroll.approve' => 'Menyetujui payroll',
            'payroll.component.manage' => 'Mengelola komponen gaji',
        ],
        'finance' => [
            'finance.reimbursement.manage' => 'Mengelola reimbursement',
            'finance.cashadvance.manage' => 'Mengelola cash advance',
            'finance.loan.manage' => 'Mengelola pinjaman karyawan',
        ],
        'report' => [
            'report.view' => 'Melihat laporan',
            'report.export' => 'Mengekspor laporan',
        ],
        'system' => [
            'audit.view' => 'Melihat log audit',
            'notification.manage' => 'Mengelola notifikasi',
        ],
    ];

    foreach ($permissions as $module => $items) {
        foreach ($items as $code => $description) {
            SeedSupport::insertIfMissing($pdo, 'permissions', ['code' => $code], [
                'module' => $module,
                'description' => $description,
            ]);
        }
    }
};
