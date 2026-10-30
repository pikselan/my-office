-- ---------------------------------------------------------------------------
-- Data inti karyawan. Data personal tambahan dipisahkan ke employee_details
-- agar akses terhadap data sensitif dapat dibatasi tersendiri.
-- ---------------------------------------------------------------------------

CREATE TABLE employees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_number VARCHAR(40) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  preferred_name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  photo_path VARCHAR(255) NULL,
  gender VARCHAR(16) NULL,
  birth_date DATE NULL,
  hire_date DATE NOT NULL,
  end_date DATE NULL,
  employment_status VARCHAR(24) NOT NULL DEFAULT 'active',
  employment_type VARCHAR(24) NOT NULL DEFAULT 'permanent',
  primary_company_id BIGINT UNSIGNED NULL,
  work_location_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_number (tenant_id, employee_number),
  KEY idx_employees_tenant (tenant_id, employment_status),
  KEY idx_employees_name (tenant_id, full_name),
  CONSTRAINT fk_employees_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_employees_company FOREIGN KEY (primary_company_id) REFERENCES companies (id) ON DELETE SET NULL,
  CONSTRAINT fk_employees_location FOREIGN KEY (work_location_id) REFERENCES work_locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_details (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  national_id_number VARCHAR(40) NULL,
  tax_id_number VARCHAR(40) NULL,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  province VARCHAR(100) NULL,
  postal_code VARCHAR(16) NULL,
  religion VARCHAR(32) NULL,
  marital_status VARCHAR(24) NULL,
  blood_type VARCHAR(4) NULL,
  last_education VARCHAR(48) NULL,
  emergency_contact_name VARCHAR(150) NULL,
  emergency_contact_phone VARCHAR(40) NULL,
  bank_name VARCHAR(100) NULL,
  bank_account_number VARCHAR(60) NULL,
  bank_account_holder VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employee_details_employee (employee_id),
  CONSTRAINT fk_employee_details_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_details_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_user_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employee_user_links_employee (tenant_id, employee_id),
  UNIQUE KEY uq_employee_user_links_user (tenant_id, user_id),
  CONSTRAINT fk_employee_user_links_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_user_links_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_user_links_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
