-- ---------------------------------------------------------------------------
-- Transaksi keuangan karyawan: reimbursement, cash advance, pinjaman.
-- ---------------------------------------------------------------------------

CREATE TABLE reimbursements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(48) NOT NULL DEFAULT 'other',
  title VARCHAR(190) NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  expense_date DATE NOT NULL,
  attachment_path VARCHAR(255) NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  paid_at DATETIME NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reimbursements_company (tenant_id, company_id, status),
  KEY idx_reimbursements_employee (tenant_id, employee_id, expense_date),
  CONSTRAINT fk_reimbursements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_reimbursements_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_reimbursements_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_reimbursements_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_reimbursements_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cash_advances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  reason TEXT NULL,
  requested_at DATE NOT NULL,
  installment_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  disbursed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cash_advances_company (tenant_id, company_id, status),
  CONSTRAINT fk_cash_advances_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_cash_advances_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_cash_advances_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_cash_advances_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_loans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  principal DECIMAL(14,2) NOT NULL DEFAULT 0,
  interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  term_months SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  monthly_payment DECIMAL(14,2) NOT NULL DEFAULT 0,
  remaining_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employee_loans_company (tenant_id, company_id, status),
  CONSTRAINT fk_employee_loans_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_loans_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_loans_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_installments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  loan_id BIGINT UNSIGNED NOT NULL,
  installment_number SMALLINT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_at DATETIME NULL,
  payroll_record_id BIGINT UNSIGNED NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loan_installments (loan_id, installment_number),
  KEY idx_loan_installments_due (tenant_id, due_date, status),
  CONSTRAINT fk_loan_installments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_loan_installments_loan FOREIGN KEY (loan_id) REFERENCES employee_loans (id) ON DELETE CASCADE,
  CONSTRAINT fk_loan_installments_payroll FOREIGN KEY (payroll_record_id) REFERENCES payroll_records (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
