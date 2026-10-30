-- ---------------------------------------------------------------------------
-- Cuti: jenis, kebijakan, saldo, pengajuan, dan persetujuan.
-- idempotency_key mencegah pemotongan saldo ganda akibat pengiriman ulang.
-- ---------------------------------------------------------------------------

CREATE TABLE leave_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NULL,
  company_id BIGINT UNSIGNED NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  default_quota_days DECIMAL(5,1) NOT NULL DEFAULT 12.0,
  max_consecutive_days SMALLINT UNSIGNED NULL,
  requires_attachment TINYINT(1) NOT NULL DEFAULT 0,
  is_paid TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_types_code (tenant_id, company_id, code),
  KEY idx_leave_types_tenant (tenant_id, is_active),
  CONSTRAINT fk_leave_types_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_types_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  leave_type_id BIGINT UNSIGNED NOT NULL,
  accrual_type VARCHAR(24) NOT NULL DEFAULT 'annual',
  accrual_days DECIMAL(5,1) NOT NULL DEFAULT 0,
  carry_over_days DECIMAL(5,1) NOT NULL DEFAULT 0,
  max_carry_over_days DECIMAL(5,1) NOT NULL DEFAULT 0,
  min_working_days_before SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  allow_negative TINYINT(1) NOT NULL DEFAULT 0,
  effective_from DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_policies (company_id, leave_type_id, effective_from),
  CONSTRAINT fk_leave_policies_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_policies_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_policies_type FOREIGN KEY (leave_type_id) REFERENCES leave_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_balances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  leave_type_id BIGINT UNSIGNED NOT NULL,
  period_year SMALLINT UNSIGNED NOT NULL,
  opening_days DECIMAL(6,1) NOT NULL DEFAULT 0,
  accrued_days DECIMAL(6,1) NOT NULL DEFAULT 0,
  used_days DECIMAL(6,1) NOT NULL DEFAULT 0,
  adjustment_days DECIMAL(6,1) NOT NULL DEFAULT 0,
  carried_over_days DECIMAL(6,1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_balances (employee_id, leave_type_id, period_year),
  KEY idx_leave_balances_tenant (tenant_id, period_year),
  CONSTRAINT fk_leave_balances_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_balances_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_balances_type FOREIGN KEY (leave_type_id) REFERENCES leave_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  leave_type_id BIGINT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total_days DECIMAL(5,1) NOT NULL DEFAULT 0,
  reason TEXT NULL,
  attachment_path VARCHAR(255) NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  idempotency_key VARCHAR(64) NULL,
  submitted_at DATETIME NULL,
  decided_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_requests_idempotency (tenant_id, idempotency_key),
  KEY idx_leave_requests_company (tenant_id, company_id, status),
  KEY idx_leave_requests_employee (tenant_id, employee_id, start_date),
  CONSTRAINT fk_leave_requests_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_requests_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_requests_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_requests_type FOREIGN KEY (leave_type_id) REFERENCES leave_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_leave_requests_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  leave_request_id BIGINT UNSIGNED NOT NULL,
  approver_user_id BIGINT UNSIGNED NULL,
  approval_level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decided_at DATETIME NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_approvals (leave_request_id, approval_level),
  CONSTRAINT fk_leave_approvals_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_approvals_request FOREIGN KEY (leave_request_id) REFERENCES leave_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_approvals_approver FOREIGN KEY (approver_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
