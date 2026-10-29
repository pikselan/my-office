-- ---------------------------------------------------------------------------
-- Jadwal kerja, hari libur, dan catatan kehadiran.
-- ---------------------------------------------------------------------------

CREATE TABLE work_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  schedule_type VARCHAR(24) NOT NULL DEFAULT 'fixed',
  work_days_json TEXT NULL,
  start_time VARCHAR(5) NULL,
  end_time VARCHAR(5) NULL,
  grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  daily_hours DECIMAL(4,2) NOT NULL DEFAULT 8.00,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_work_schedules_company (company_id, status),
  CONSTRAINT fk_work_schedules_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_work_schedules_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  work_schedule_id BIGINT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  effective_until DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_schedule_assignments_employee (employee_id, effective_from),
  CONSTRAINT fk_schedule_assignments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_assignments_schedule FOREIGN KEY (work_schedule_id) REFERENCES work_schedules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holidays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  holiday_date DATE NOT NULL,
  is_national TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_holidays_tenant (tenant_id, holiday_date),
  CONSTRAINT fk_holidays_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_holidays_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NULL,
  work_date DATE NOT NULL,
  check_in_at DATETIME NULL,
  check_out_at DATETIME NULL,
  check_in_latitude DECIMAL(10,7) NULL,
  check_in_longitude DECIMAL(10,7) NULL,
  check_out_latitude DECIMAL(10,7) NULL,
  check_out_longitude DECIMAL(10,7) NULL,
  source VARCHAR(24) NOT NULL DEFAULT 'web',
  status VARCHAR(24) NOT NULL DEFAULT 'present',
  late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance_records_day (tenant_id, employee_id, work_date),
  KEY idx_attendance_records_company (tenant_id, company_id, work_date),
  CONSTRAINT fk_attendance_records_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_records_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_records_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_records_schedule FOREIGN KEY (schedule_id) REFERENCES work_schedules (id) ON DELETE SET NULL,
  CONSTRAINT fk_attendance_records_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_corrections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  attendance_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NULL,
  reason TEXT NOT NULL,
  requested_check_in_at DATETIME NULL,
  requested_check_out_at DATETIME NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decision_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attendance_corrections_attendance (attendance_id, status),
  CONSTRAINT fk_attendance_corrections_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_corrections_attendance FOREIGN KEY (attendance_id) REFERENCES attendance_records (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_corrections_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_attendance_corrections_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
