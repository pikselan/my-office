-- ---------------------------------------------------------------------------
-- Payroll: periode, komponen gaji, dan slip gaji.
-- ---------------------------------------------------------------------------

CREATE TABLE payroll_periods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(48) NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  payment_date DATE NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_periods (company_id, period_start, period_end),
  KEY idx_payroll_periods_tenant (tenant_id, company_id, status),
  CONSTRAINT fk_payroll_periods_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_periods_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_periods_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_payroll_periods_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_components (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(150) NOT NULL,
  component_type VARCHAR(24) NOT NULL DEFAULT 'earning',
  calculation_type VARCHAR(24) NOT NULL DEFAULT 'fixed',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  formula TEXT NULL,
  is_taxable TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_components (company_id, code),
  CONSTRAINT fk_payroll_components_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_components_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  payroll_period_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  basic_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_earnings DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
  working_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  present_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  payslip_number VARCHAR(48) NULL,
  paid_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_records (payroll_period_id, employee_id),
  KEY idx_payroll_records_tenant (tenant_id, company_id),
  CONSTRAINT fk_payroll_records_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_records_period FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_records_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_records_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_record_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  payroll_record_id BIGINT UNSIGNED NOT NULL,
  payroll_component_id BIGINT UNSIGNED NULL,
  label VARCHAR(150) NOT NULL,
  component_type VARCHAR(24) NOT NULL DEFAULT 'earning',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payroll_record_items_record (payroll_record_id),
  CONSTRAINT fk_payroll_record_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_record_items_record FOREIGN KEY (payroll_record_id) REFERENCES payroll_records (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_record_items_component FOREIGN KEY (payroll_component_id) REFERENCES payroll_components (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
