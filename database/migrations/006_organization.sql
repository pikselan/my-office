-- ---------------------------------------------------------------------------
-- Struktur organisasi: unit, departemen, jabatan.
-- Hierarki divalidasi anti-siklus di lapisan aplikasi karena MySQL tidak
-- dapat menegakkannya dengan constraint.
-- ---------------------------------------------------------------------------

CREATE TABLE org_units (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(190) NOT NULL,
  unit_type VARCHAR(24) NOT NULL DEFAULT 'branch',
  manager_employee_id BIGINT UNSIGNED NULL,
  unit_level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_org_units_code (company_id, code),
  KEY idx_org_units_company (company_id, parent_id),
  KEY idx_org_units_tenant (tenant_id, company_id),
  CONSTRAINT fk_org_units_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_org_units_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_org_units_parent FOREIGN KEY (parent_id) REFERENCES org_units (id) ON DELETE CASCADE,
  CONSTRAINT fk_org_units_manager FOREIGN KEY (manager_employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  org_unit_id BIGINT UNSIGNED NULL,
  parent_id BIGINT UNSIGNED NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(190) NOT NULL,
  manager_employee_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departments_code (company_id, code),
  KEY idx_departments_unit (company_id, org_unit_id),
  CONSTRAINT fk_departments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_departments_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_departments_unit FOREIGN KEY (org_unit_id) REFERENCES org_units (id) ON DELETE CASCADE,
  CONSTRAINT fk_departments_parent FOREIGN KEY (parent_id) REFERENCES departments (id) ON DELETE CASCADE,
  CONSTRAINT fk_departments_manager FOREIGN KEY (manager_employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE positions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  parent_position_id BIGINT UNSIGNED NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  unit_level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_positions_code (company_id, code),
  KEY idx_positions_department (company_id, department_id),
  CONSTRAINT fk_positions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_positions_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_positions_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE,
  CONSTRAINT fk_positions_parent FOREIGN KEY (parent_position_id) REFERENCES positions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
