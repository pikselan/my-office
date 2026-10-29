# Skema Database — MO (MyOffice)

Database: `mo_hris` · Engine: InnoDB · Charset: `utf8mb4` / `utf8mb4_unicode_ci`
Waktu: seluruh `DATETIME` disimpan **UTC**. Konversi ke zona perusahaan dilakukan di
lapisan presentasi.

## 0. Konvensi

| Hal | Aturan |
|---|---|
| Primary key | `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (diisi ulang oleh aplikasi; tanpa `ON UPDATE` demi portabilitas) |
| Soft delete | `deleted_at DATETIME NULL` — hanya pada entitas yang disebutkan |
| Nilai enum | disimpan `VARCHAR` dan **divalidasi di aplikasi**, bukan `ENUM()` MySQL, agar konsisten lintas driver |
| Uang | `DECIMAL(14,2)` |
| Metadata bebas | `TEXT` berisi JSON, diproses di PHP (tanpa fungsi JSON MySQL) |
| Unique | selalu menyertakan `tenant_id` bila entitas milik tenant |
| Nama cadangan | dihindari: `order`→`sort_order`, `level`→`unit_level`, `key`→`code` |

Singkatan tipe: `PK` primary key · `FK` foreign key · `UQ` unique · `IX` index · `NN` not null · `SD` soft delete.

---

## 1. Kelompok A — SaaS & platform

### tenants
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(150) NN | nama tenant |
| slug | VARCHAR(150) NN | UQ global (dipakai pada URL platform) |
| status | VARCHAR(24) NN | `active`,`suspended`,`cancelled`,`pending` |
| default_locale | VARCHAR(8) NN | default `id` |
| timezone | VARCHAR(64) NN | default `Asia/Jakarta` |
| date_format | VARCHAR(24) NN | default `d/m/Y` |
| owner_user_id | BIGINT UNSIGNED NULL | FK users(id) ON DELETE SET NULL |
| trial_ends_at | DATETIME NULL | |
| created_at/updated_at/deleted_at | DATETIME | SD |
Index: `UQ tenants_slug (slug)`, `IX tenants_status`.

### plans
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| code | VARCHAR(40) NN | UQ: `starter`,`business`,`growth`,`enterprise` |
| name, description | VARCHAR(150) / TEXT | |
| monthly_price, annual_price | DECIMAL(14,2) NN | 0 untuk enterprise/custom |
| currency | VARCHAR(3) NN | default `IDR` |
| employee_limit, company_limit | INT UNSIGNED NN | 0 = tidak dibatasi (custom) |
| storage_limit_mb | INT UNSIGNED NN | |
| trial_days | SMALLINT UNSIGNED NN | default 14 |
| sort_order | SMALLINT UNSIGNED NN | |
| is_active, is_public | TINYINT(1) NN | |
| created_at/updated_at | DATETIME | |

### plan_features
`id`, `plan_id` FK→plans ON DELETE CASCADE, `feature_code` VARCHAR(64), `is_enabled` TINYINT(1), `configuration_json` TEXT NULL, timestamps.
`UQ (plan_id, feature_code)`.

### subscriptions
`id`, `tenant_id` FK→tenants CASCADE, `plan_id` FK→plans RESTRICT, `status` VARCHAR(24)
(`trialing`,`active`,`past_due`,`suspended`,`cancelled`,`expired`), `billing_cycle`
(`monthly`,`annual`), `starts_at`, `trial_ends_at`, `current_period_start`,
`current_period_end`, `cancelled_at`, `grace_ends_at`, timestamps.
Index: `IX (tenant_id, status)`, `IX subs_period_end`.

### subscription_events
`id`, `tenant_id` FK CASCADE, `subscription_id` FK SET NULL, `event_type` VARCHAR(48)
(`created`,`activated`,`trial_started`,`trial_ended`,`plan_changed`,`renewed`,`past_due`,
`suspended`,`reactivated`,`cancelled`,`expired`), `description` TEXT,
`actor_user_id` NULL, `metadata_json` TEXT NULL, `created_at`.
Index: `IX (tenant_id, created_at)`.

### payments
`id`, `tenant_id` FK CASCADE, `subscription_id` FK SET NULL, `invoice_number` VARCHAR(48) UQ,
`amount` DECIMAL(14,2), `currency` VARCHAR(3), `status` VARCHAR(24)
(`pending`,`paid`,`failed`,`refunded`,`expired`), `payment_method` VARCHAR(32) NULL,
`plan_name_snapshot` VARCHAR(150), `period_start`, `period_end`, `paid_at`, `created_at`.
Index: `IX (tenant_id, status)`, `UQ payments_invoice`.

### platform_settings
`id`, `setting_key` VARCHAR(80) UQ, `setting_value` TEXT, `updated_by` NULL, `updated_at`.

---

## 2. Kelompok B — Identitas & akses

### users
`id`, `name` VARCHAR(150) NN, `email` VARCHAR(190) NN **UQ global**,
`password_hash` VARCHAR(255) NN, `locale` VARCHAR(8) default `id`, `timezone` VARCHAR(64),
`status` VARCHAR(24) (`active`,`invited`,`suspended`,`disabled`),
`is_platform_admin` TINYINT(1) default 0, `email_verified_at` DATETIME NULL,
`last_login_at` DATETIME NULL, `failed_login_count` SMALLINT default 0,
`locked_until` DATETIME NULL, timestamps, `deleted_at` SD.
Index: `UQ users_email`, `IX users_status`.

### tenant_memberships
`id`, `tenant_id` FK CASCADE, `user_id` FK CASCADE, `status` VARCHAR(24)
(`active`,`invited`,`suspended`,`removed`), `joined_at` NULL, `created_at`, `updated_at`.
`UQ (tenant_id, user_id)`.

### roles
`id`, `tenant_id` BIGINT UNSIGNED **NULL** (NULL = role sistem/platform), `name`, `code`,
`description` TEXT NULL, `is_system` TINYINT(1), `sort_order`, timestamps.
`UQ (tenant_id, code)` — catatan: MySQL mengizinkan banyak NULL pada unique key, sehingga
beberapa role sistem dengan `tenant_id NULL` dan kode sama tetap mungkin terjadi; oleh
karena itu role sistem di-seed dengan kode global unik (`platform.*`, `tenant.*`) dan
dijaga oleh `RoleService` di aplikasi.
Kode role bawaan: `platform.super_admin`, `platform.support`, `tenant.owner`,
`tenant.company_admin`, `tenant.hr_admin`, `tenant.manager`, `tenant.employee`,
`tenant.finance`.

### permissions
`id`, `code` VARCHAR(80) **UQ**, `module` VARCHAR(48), `description` TEXT, `created_at`.

### role_permissions
`role_id` FK→roles CASCADE, `permission_id` FK→permissions CASCADE. `PK (role_id, permission_id)`.

### user_roles
`id`, `tenant_id` FK CASCADE, `user_id` FK CASCADE, `role_id` FK CASCADE,
`company_id` NULL FK→companies CASCADE (role terbatas perusahaan tertentu),
`created_at`. Index: `IX (tenant_id, user_id)`, `UQ (tenant_id, user_id, role_id, company_id)` —
`company_id` NULL diperlakukan unik per baris oleh MySQL, sehingga aplikasi mencegah
duplikasi cakupan NULL melalui pengecekan di `RoleService`.

### user_company_access
`id`, `tenant_id`, `user_id`, `company_id`, `created_at`. `UQ (tenant_id, user_id, company_id)`.

### user_sessions
`id`, `user_id` FK CASCADE, `session_id_hash` VARCHAR(64) UQ (hash, bukan id asli),
`ip_address` VARCHAR(45) NULL, `user_agent` VARCHAR(255) NULL, `last_activity_at`,
`created_at`, `expires_at`. Index `IX (user_id, last_activity_at)`.

### password_reset_tokens
`id`, `user_id` FK CASCADE, `token_hash` VARCHAR(64) UQ, `created_at`, `expires_at`,
`used_at` NULL, `ip_address` NULL.

### email_verification_tokens
`id`, `user_id` FK CASCADE, `token_hash` VARCHAR(64) UQ, `created_at`, `expires_at`, `used_at`.

### invitations
`id`, `tenant_id` FK CASCADE, `email` VARCHAR(190), `role_code` VARCHAR(48),
`company_ids_json` TEXT NULL, `token_hash` VARCHAR(64) UQ, `status` VARCHAR(24)
(`pending`,`accepted`,`revoked`,`expired`), `invited_by` NULL, `created_at`, `expires_at`,
`accepted_at`. Index `IX (tenant_id, email)`.

### login_attempts
`id`, `email` VARCHAR(190), `ip_address` VARCHAR(45), `user_agent` VARCHAR(255) NULL,
`successful` TINYINT(1), `created_at`. Index `IX (email, created_at)`, `IX (ip_address, created_at)`.

### support_access_grants
`id`, `tenant_id` FK CASCADE, `granted_user_id` FK→users CASCADE, `granted_by` NULL,
`reason` TEXT NN, `scope_json` TEXT, `starts_at`, `expires_at`, `revoked_at` NULL,
`created_at`. Index `IX (tenant_id, granted_user_id)`.

---

## 3. Kelompok C — Perusahaan & organisasi

### companies
`id`, `tenant_id` FK CASCADE, `name` NN, `legal_name` NULL, `slug` NN,
`npwp` NULL, `website` NULL, `email` NULL, `phone` NULL, `address` TEXT NULL,
`city`/`province`/`postal_code` NULL, `logo_path` NULL, `locale` VARCHAR(8),
`timezone` VARCHAR(64), `date_format` VARCHAR(24), `work_days_json` TEXT
(contoh `["mon","tue","wed","thu","fri"]`), `status` VARCHAR(24) (`active`,`archived`),
timestamps, `deleted_at` SD.
Index: `UQ (tenant_id, slug)`, `IX (tenant_id, status)`.

### company_settings
`id`, `tenant_id`, `company_id` FK CASCADE, `setting_key` VARCHAR(80),
`setting_value` TEXT, `updated_by` NULL, `updated_at`. `UQ (company_id, setting_key)`.
Kunci visibilitas: `visibility.birthday`, `visibility.anniversary`, `visibility.leave`,
`visibility.whatsapp`, `visibility.email`, `visibility.directory`
(nilai `all`, `manager`, `hr`, `none`).

### tenant_settings
`id`, `tenant_id` FK CASCADE, `setting_key`, `setting_value`, `updated_by`, `updated_at`.
`UQ (tenant_id, setting_key)`.

### org_units
`id`, `tenant_id`, `company_id` FK CASCADE, `parent_id` NULL FK→org_units CASCADE,
`code` NN, `name` NN, `unit_type` VARCHAR(24) (`company`,`branch`,`division`,`department_group`),
`manager_employee_id` NULL FK→employees SET NULL, `unit_level` SMALLINT NN default 1,
`sort_order` INT default 0, `status` (`active`,`archived`), timestamps, `deleted_at` SD.
Index: `UQ (company_id, code)`, `IX (company_id, parent_id)`, `IX (tenant_id, company_id)`.

### departments
`id`, `tenant_id`, `company_id` FK CASCADE, `org_unit_id` NULL FK CASCADE,
`parent_id` NULL FK→departments CASCADE, `code` NN, `name` NN,
`manager_employee_id` NULL FK SET NULL, `status`, `sort_order`, timestamps, `deleted_at`.
Index: `UQ (company_id, code)`, `IX (company_id, org_unit_id)`.

### positions
`id`, `tenant_id`, `company_id` FK CASCADE, `department_id` NULL FK→departments CASCADE,
`parent_position_id` NULL FK→positions CASCADE, `code` NN, `name` NN, `description` TEXT NULL,
`unit_level` SMALLINT default 1, `status`, `sort_order`, timestamps, `deleted_at`.
Index: `UQ (company_id, code)`, `IX (company_id, department_id)`.

> Batas domain: `org_units` = wadah bisnis/operasional (cabang, divisi, grup departemen);
> `departments` = fungsi organisasi (HR, Finance, Operasional) yang menempel pada unit;
> `positions` = jabatan yang menempel pada departemen. Hierarki ketiganya divalidasi
> anti-siklus di aplikasi karena MySQL tidak dapat menegakkannya dengan constraint.

---

## 4. Kelompok D — Karyawan

### employees
`id`, `tenant_id` FK CASCADE, `employee_number` VARCHAR(40) NN, `full_name` VARCHAR(190) NN,
`preferred_name` NULL, `email` NULL, `phone` NULL, `photo_path` NULL,
`gender` VARCHAR(16) NULL, `birth_date` DATE NULL, `hire_date` DATE NN,
`end_date` DATE NULL, `employment_status` VARCHAR(24)
(`probation`,`active`,`suspended`,`terminated`,`retired`), `employment_type` VARCHAR(24)
(`permanent`,`contract`,`internship`,`outsourced`,`probation`),
`work_location_id` NULL, `primary_company_id` NULL FK→companies SET NULL,
timestamps, `deleted_at` SD.
Index: `UQ (tenant_id, employee_number)`, `IX (tenant_id, employment_status)`,
`IX (tenant_id, full_name)`.

### employee_details (data personal & sensitif)
`id`, `tenant_id`, `employee_id` FK CASCADE **UQ**, `national_id_number` NULL,
`tax_id_number` NULL, `address` TEXT NULL, `city`,`province`,`postal_code` NULL,
`religion` NULL, `marital_status` NULL, `blood_type` NULL, `last_education` NULL,
`emergency_contact_name` NULL, `emergency_contact_phone` NULL,
`bank_name`,`bank_account_number`,`bank_account_holder` NULL, timestamps.
Akses memerlukan permission `employee.sensitive.view`.

### employee_assignments (riwayat penempatan)
`id`, `tenant_id`, `employee_id` FK CASCADE, `company_id` FK CASCADE,
`org_unit_id` NULL FK SET NULL, `department_id` NULL FK SET NULL,
`position_id` NULL FK SET NULL, `manager_employee_id` NULL FK SET NULL,
`work_location_id` NULL, `effective_from` DATE NN, `effective_until` DATE NULL,
`assignment_status` VARCHAR(24) (`active`,`ended`,`planned`), `change_reason` VARCHAR(64) NULL,
`created_by` NULL, `created_at`.
Index: `IX (employee_id, effective_from)`, `IX (tenant_id, company_id)`,
`IX (tenant_id, department_id)`.
Aturan: hanya satu baris `assignment_status = 'active'` per karyawan pada satu waktu —
dijaga oleh aplikasi dalam transaksi.

### employee_documents
`id`, `tenant_id`, `employee_id` FK CASCADE, `document_type` VARCHAR(48),
`title` VARCHAR(190), `original_filename` VARCHAR(255), `storage_path` VARCHAR(255),
`mime_type` VARCHAR(120), `file_size` INT UNSIGNED, `is_sensitive` TINYINT(1) default 0,
`uploaded_by` NULL, `created_at`, `deleted_at` SD.
Index: `IX (tenant_id, employee_id)`.
File disimpan di `storage/private/...` (di luar document root).

### employee_notes
`id`, `tenant_id`, `employee_id` FK CASCADE, `note` TEXT NN, `is_private` TINYINT(1),
`created_by` NULL, `created_at`, `updated_at`. Index `IX (employee_id, created_at)`.

### employee_user_links
`id`, `tenant_id`, `employee_id` FK CASCADE, `user_id` FK CASCADE, `created_at`.
`UQ (tenant_id, employee_id)`, `UQ (tenant_id, user_id)`.

---

## 5. Kelompok E — Workflow & pengumuman

### onboarding_templates
`id`, `tenant_id`, `name`, `description` TEXT NULL, `company_id` NULL FK SET NULL,
`duration_days` SMALLINT, `is_active` TINYINT(1), timestamps, `deleted_at` SD.

### onboarding_tasks
`id`, `tenant_id`, `template_id` FK CASCADE, `title`, `description` TEXT NULL,
`assigned_role` VARCHAR(48) NULL, `due_offset_days` SMALLINT default 0,
`requires_document` TINYINT(1) default 0, `sort_order` INT, timestamps.

### onboarding_processes
`id`, `tenant_id`, `employee_id` FK CASCADE, `template_id` FK RESTRICT,
`company_id` FK CASCADE, `starts_at` DATE, `target_completion_at` DATE NULL,
`status` VARCHAR(24) (`not_started`,`in_progress`,`completed`,`cancelled`),
`completed_at` NULL, `created_by` NULL, timestamps.
Index `IX (tenant_id, status)`.

### onboarding_task_assignments
`id`, `tenant_id`, `process_id` FK CASCADE, `task_id` FK CASCADE,
`assignee_user_id` NULL FK SET NULL, `status` (`pending`,`in_progress`,`done`,`skipped`),
`due_at` DATE NULL, `completed_at` NULL, `completed_by` NULL, `notes` TEXT NULL, timestamps.
`UQ (process_id, task_id)`.

### announcements
`id`, `tenant_id` FK CASCADE, `title` NN, `body` TEXT NN, `author_user_id` NULL,
`status` (`draft`,`scheduled`,`published`,`archived`), `published_at` DATETIME NULL,
`expires_at` DATETIME NULL, `is_pinned` TINYINT(1) default 0, timestamps, `deleted_at` SD.
Index `IX (tenant_id, status, published_at)`.

### announcement_targets
`id`, `tenant_id`, `announcement_id` FK CASCADE, `company_id` NULL FK CASCADE,
`org_unit_id` NULL FK CASCADE, `department_id` NULL FK CASCADE.
Index `IX (announcement_id)`.

---

## 6. Kelompok F — Waktu & kehadiran

### work_locations
`id`, `tenant_id`, `company_id` FK CASCADE, `name`, `address` TEXT NULL,
`latitude` DECIMAL(10,7) NULL, `longitude` DECIMAL(10,7) NULL, `radius_meters` INT NULL,
`status`, timestamps.

### work_schedules
`id`, `tenant_id`, `company_id` FK CASCADE, `name`, `schedule_type` (`fixed`,`shift`,`flexible`),
`work_days_json` TEXT, `start_time` VARCHAR(5) NULL, `end_time` VARCHAR(5) NULL,
`grace_minutes` SMALLINT default 0, `daily_hours` DECIMAL(4,2), `is_default` TINYINT(1),
`status`, timestamps.

### schedule_assignments
`id`, `tenant_id`, `employee_id` FK CASCADE, `work_schedule_id` FK CASCADE,
`effective_from` DATE, `effective_until` DATE NULL, `created_at`.
Index `IX (employee_id, effective_from)`.

### holidays
`id`, `tenant_id`, `company_id` NULL FK CASCADE (NULL = berlaku untuk seluruh tenant),
`name`, `holiday_date` DATE, `is_national` TINYINT(1), `created_at`.
Index `IX (tenant_id, holiday_date)`.

### attendance_records
`id`, `tenant_id`, `employee_id` FK CASCADE, `company_id` FK CASCADE,
`work_date` DATE, `schedule_id` NULL, `check_in_at` DATETIME NULL, `check_out_at` DATETIME NULL,
`check_in_latitude`/`check_in_longitude` NULL, `check_out_latitude`/`check_out_longitude` NULL,
`source` VARCHAR(24) (`web`,`mobile`,`manual`,`import`), `status`
(`present`,`late`,`absent`,`leave`,`holiday`,`weekend`,`half_day`),
`late_minutes` SMALLINT default 0, `work_minutes` INT default 0,
`notes` TEXT NULL, `created_by` NULL, timestamps.
`UQ (tenant_id, employee_id, work_date)`, `IX (tenant_id, company_id, work_date)`.

### attendance_corrections
`id`, `tenant_id`, `attendance_id` FK CASCADE, `requested_by` FK→users SET NULL,
`reason` TEXT, `requested_check_in_at` NULL, `requested_check_out_at` NULL,
`status` (`pending`,`approved`,`rejected`), `decided_by` NULL, `decided_at` NULL,
`decision_note` NULL, `created_at`.

---

## 7. Kelompok G — Cuti

### leave_types
`id`, `tenant_id` NULL (NULL = bawaan sistem), `company_id` NULL, `code`, `name`,
`default_quota_days` DECIMAL(5,1), `max_consecutive_days` SMALLINT NULL,
`requires_attachment` TINYINT(1), `is_paid` TINYINT(1), `is_active` TINYINT(1),
`sort_order`, timestamps. `UQ (tenant_id, company_id, code)` — dengan aturan unik
dijaga aplikasi untuk kombinasi NULL.

### leave_policies
`id`, `tenant_id`, `company_id` FK CASCADE, `leave_type_id` FK CASCADE,
`accrual_type` (`none`,`annual`,`monthly`,`manual`), `accrual_days` DECIMAL(5,1),
`carry_over_days` DECIMAL(5,1) default 0, `max_carry_over_days` DECIMAL(5,1) default 0,
`min_working_days_before` SMALLINT default 0, `allow_negative` TINYINT(1) default 0,
`effective_from` DATE, timestamps. `UQ (company_id, leave_type_id, effective_from)`.

### leave_balances
`id`, `tenant_id`, `employee_id` FK CASCADE, `leave_type_id` FK CASCADE,
`period_year` SMALLINT, `opening_days` DECIMAL(6,1) default 0,
`accrued_days` DECIMAL(6,1) default 0, `used_days` DECIMAL(6,1) default 0,
`adjustment_days` DECIMAL(6,1) default 0, `carried_over_days` DECIMAL(6,1) default 0,
`updated_at`. `UQ (employee_id, leave_type_id, period_year)`.

### leave_requests
`id`, `tenant_id`, `employee_id` FK CASCADE, `company_id` FK CASCADE,
`leave_type_id` FK RESTRICT, `start_date` DATE, `end_date` DATE,
`total_days` DECIMAL(5,1), `reason` TEXT, `attachment_path` NULL,
`status` (`draft`,`pending`,`approved`,`rejected`,`cancelled`,`cancelling`),
`idempotency_key` VARCHAR(64) NULL **UQ (tenant_id, idempotency_key)**,
`submitted_at` NULL, `decided_at` NULL, `cancelled_at` NULL,
`created_by` NULL, timestamps, `deleted_at` SD.
Index `IX (tenant_id, company_id, status)`, `IX (tenant_id, employee_id, start_date)`.

### leave_approvals
`id`, `tenant_id`, `leave_request_id` FK CASCADE, `approver_user_id` NULL,
`approval_level` SMALLINT default 1, `status` (`pending`,`approved`,`rejected`),
`decided_at` NULL, `note` TEXT NULL, `created_at`. `UQ (leave_request_id, approval_level)`.

---

## 8. Kelompok H — Rekrutmen

### job_postings
`id`, `tenant_id`, `company_id` FK CASCADE, `department_id` NULL, `position_id` NULL,
`title` NN, `slug` NN, `description` TEXT, `requirements` TEXT NULL,
`employment_type` VARCHAR(24), `vacancy_count` SMALLINT default 1,
`status` (`draft`,`open`,`closed`,`archived`), `published_at` NULL, `closes_at` NULL,
`hiring_manager_user_id` NULL, timestamps, `deleted_at` SD. `UQ (tenant_id, slug)`.

### candidates
`id`, `tenant_id`, `job_posting_id` NULL FK SET NULL, `full_name` NN, `email` NULL,
`phone` NULL, `source` VARCHAR(48) NULL, `resume_path` NULL, `cover_letter` TEXT NULL,
`current_stage` VARCHAR(32) (`applied`,`screening`,`interview`,`offering`,`hired`,`rejected`),
`rating` TINYINT NULL, `status` (`active`,`hired`,`rejected`,`withdrawn`),
`converted_employee_id` NULL FK SET NULL, `created_by` NULL, timestamps, `deleted_at` SD.
Index `IX (tenant_id, status)`, `IX (tenant_id, job_posting_id)`.

### candidate_stage_history
`id`, `tenant_id`, `candidate_id` FK CASCADE, `from_stage` NULL, `to_stage` NN,
`note` TEXT NULL, `changed_by` NULL, `created_at`. Index `IX (candidate_id, created_at)`.

### recruitment_interviews
`id`, `tenant_id`, `candidate_id` FK CASCADE, `interviewer_user_id` NULL,
`scheduled_at` DATETIME, `duration_minutes` SMALLINT, `mode` VARCHAR(24) (`online`,`onsite`,`phone`),
`location` VARCHAR(190) NULL, `result` (`pending`,`pass`,`fail`), `score` DECIMAL(4,1) NULL,
`notes` TEXT NULL, `created_at`.

---

## 9. Kelompok I — Payroll & keuangan

### payroll_periods
`id`, `tenant_id`, `company_id` FK CASCADE, `name` (mis. `2026-10`), `period_start` DATE,
`period_end` DATE, `payment_date` DATE NULL,
`status` (`draft`,`processing`,`approved`,`paid`,`closed`,`cancelled`),
`approved_by` NULL, `approved_at` NULL, `created_by` NULL, timestamps.
`UQ (company_id, period_start, period_end)`.

### payroll_components
`id`, `tenant_id`, `company_id` FK CASCADE, `code`, `name`,
`component_type` (`earning`,`deduction`,`benefit`,`overtime`),
`calculation_type` (`fixed`,`formula`,`attendance_based`,`manual`),
`amount` DECIMAL(14,2) default 0, `formula` TEXT NULL, `is_taxable` TINYINT(1),
`is_active` TINYINT(1), `sort_order`, timestamps. `UQ (company_id, code)`.

### payroll_records
`id`, `tenant_id`, `payroll_period_id` FK CASCADE, `employee_id` FK CASCADE,
`company_id` FK CASCADE, `basic_salary` DECIMAL(14,2) default 0,
`total_earnings` DECIMAL(14,2) default 0, `total_deductions` DECIMAL(14,2) default 0,
`net_salary` DECIMAL(14,2) default 0, `working_days` SMALLINT default 0,
`present_days` SMALLINT default 0, `status` (`draft`,`approved`,`paid`),
`payslip_number` VARCHAR(48) NULL, `paid_at` NULL, `notes` TEXT NULL, timestamps.
`UQ (payroll_period_id, employee_id)`, `IX (tenant_id, company_id)`.

### payroll_record_items
`id`, `tenant_id`, `payroll_record_id` FK CASCADE, `payroll_component_id` NULL FK SET NULL,
`label` VARCHAR(150), `component_type` VARCHAR(24), `amount` DECIMAL(14,2),
`sort_order`, `created_at`. Index `IX (payroll_record_id)`.

### reimbursements
`id`, `tenant_id`, `company_id` FK CASCADE, `employee_id` FK CASCADE, `category` VARCHAR(48),
`title`, `amount` DECIMAL(14,2), `expense_date` DATE, `attachment_path` NULL,
`status` (`draft`,`pending`,`approved`,`rejected`,`paid`), `decided_by` NULL,
`decided_at` NULL, `paid_at` NULL, `notes` TEXT NULL, `created_by` NULL, timestamps.

### cash_advances
`id`, `tenant_id`, `company_id` FK CASCADE, `employee_id` FK CASCADE, `amount` DECIMAL(14,2),
`requested_at`, `reason` TEXT, `installment_count` SMALLINT default 1,
`status` (`pending`,`approved`,`rejected`,`disbursed`,`settled`), `decided_by` NULL,
`disbursed_at` NULL, timestamps.

### employee_loans
`id`, `tenant_id`, `company_id` FK CASCADE, `employee_id` FK CASCADE, `principal` DECIMAL(14,2),
`interest_rate` DECIMAL(5,2) default 0, `term_months` SMALLINT, `monthly_payment` DECIMAL(14,2),
`start_date` DATE, `status` (`active`,`paid_off`,`defaulted`,`cancelled`),
`remaining_balance` DECIMAL(14,2), timestamps.

### loan_installments
`id`, `tenant_id`, `loan_id` FK CASCADE, `installment_number` SMALLINT, `due_date` DATE,
`amount` DECIMAL(14,2), `paid_amount` DECIMAL(14,2) default 0, `paid_at` NULL,
`payroll_record_id` NULL FK SET NULL, `status` (`pending`,`paid`,`skipped`), `created_at`.
`UQ (loan_id, installment_number)`.

---

## 10. Kelompok J — Sistem

### audit_logs
`id`, `tenant_id` NULL (NULL = kejadian platform), `actor_user_id` NULL FK SET NULL,
`action` VARCHAR(80), `entity_type` VARCHAR(64) NULL, `entity_id` BIGINT NULL,
`request_id` VARCHAR(40) NULL, `ip_address` VARCHAR(45) NULL,
`metadata_json` TEXT NULL, `created_at`. Index `IX (tenant_id, created_at)`,
`IX (entity_type, entity_id)`.
Tidak pernah menyimpan password, token mentah, atau isi dokumen.

### notifications
`id`, `tenant_id` FK CASCADE, `user_id` FK CASCADE, `type` VARCHAR(48), `title`,
`body` TEXT NULL, `link_url` VARCHAR(255) NULL, `read_at` NULL, `created_at`.
Index `IX (user_id, read_at)`, `IX (tenant_id, created_at)`.

### system_jobs
`id`, `tenant_id` NULL, `job_type` VARCHAR(64), `payload_json` TEXT NULL,
`status` (`pending`,`running`,`done`,`failed`), `run_after` DATETIME,
`attempts` SMALLINT default 0, `last_error` TEXT NULL, `locked_at` NULL,
`completed_at` NULL, `created_at`. Index `IX (status, run_after)`.

### schema_migrations
`id`, `migration` VARCHAR(190) **UQ**, `batch` INT, `executed_at` DATETIME,
`execution_ms` INT default 0.

---

## 11. Ringkasan relasi (ERD tekstual)

```
tenants 1─n companies 1─n org_units 1─n departments 1─n positions
tenants 1─n employees n─1 (primary_company)
employees 1─n employee_assignments n─1 company/org_unit/department/position
employees 1─1 employee_details ; employees 1─n employee_documents/notes
employees n─0..1 users (employee_user_links)
users n─n tenants (tenant_memberships) ; users n─n roles (user_roles) ; roles n─n permissions
tenants 1..n subscriptions n─1 plans 1─n plan_features
tenants 1─n payments / subscription_events
employees 1─n leave_requests 1─n leave_approvals ; leave_types 1─n leave_balances
employees 1─n attendance_records 1─n attendance_corrections
employees 1─n onboarding_processes 1─n onboarding_task_assignments
companies 1─n job_postings 1─n candidates 1─n candidate_stage_history/interviews
companies 1─n payroll_periods 1─n payroll_records 1─n payroll_record_items
employees 1─n reimbursements / cash_advances / employee_loans 1─n loan_installments
```

## 12. Migrasi

`database/migrations/` berisi berkas SQL bernomor:

Urutan mengikuti ketergantungan foreign key, bukan urutan domain:

```
001_identity.sql            users, user_sessions, password_reset_tokens,
                            email_verification_tokens, login_attempts
002_platform.sql            tenants, plans, plan_features, subscriptions,
                            subscription_events, payments, platform_settings
003_tenancy.sql             tenant_memberships, roles, permissions,
                            role_permissions, invitations, support_access_grants
004_company.sql             companies, company_settings, tenant_settings,
                            work_locations
005_employee_core.sql       employees, employee_details, employee_user_links
006_organization.sql        org_units, departments, positions
007_employee_assignment.sql employee_assignments
008_employee_support.sql    employee_documents, employee_notes
009_user_access.sql         user_roles, user_company_access
010_onboarding.sql          onboarding_*
011_announcements.sql       announcements, announcement_targets
012_attendance.sql          work_schedules, schedule_assignments, holidays,
                            attendance_records, attendance_corrections
013_leave.sql               leave_*
014_recruitment.sql         job_postings, candidates, candidate_stage_history,
                            recruitment_interviews
015_payroll.sql             payroll_*
016_finance.sql             reimbursements, cash_advances, employee_loans,
                            loan_installments
017_system.sql              audit_logs, notifications, system_jobs
```

Tabel `schema_migrations` dibuat sendiri oleh `Migrator` karena diperlukan
sebelum migrasi pertama dicatat.

Catatan penempatan:
- `work_locations` berada pada `004_company.sql` karena direferensikan oleh
  `employees.work_location_id`.
- `user_roles.tenant_id` bernilai NULL untuk penugasan role platform
  (global); NULL pada unique key tidak dicegah MySQL/SQLite, sehingga
  keunikan dijaga oleh `RoleService`.
- `employees` dibuat sebelum `org_units` karena `org_units.manager_employee_id`
  dan `departments.manager_employee_id` merujuk ke `employees`.

Dijalankan berurutan oleh `bin/mo migrate`, dicatat pada `schema_migrations`,
berada dalam transaksi per berkas.

## 13. Catatan portabilitas

Skema ditulis dalam DDL MySQL 8. Untuk pengujian dan preview di sandbox (tanpa MySQL),
`app/Core/Database/SqliteDialect.php` menerjemahkan DDL tersebut ke SQLite:
menghapus `ENGINE/CHARSET/COLLATE/COMMENT`, mengubah tipe bilangan bulat bertanda menjadi
`INTEGER`, mengubah `VARCHAR/DATE/TEXT` menjadi `TEXT`, menghapus `ON UPDATE`, dan
memindahkan `KEY`/`UNIQUE KEY` sebaris menjadi `CREATE INDEX` terpisah.
Penerjemah **hanya** dipakai oleh tooling/test, bukan oleh jalur produksi.
