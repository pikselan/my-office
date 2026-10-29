-- ---------------------------------------------------------------------------
-- Riwayat penempatan kerja. Riwayat tidak pernah ditimpa: perubahan
-- menutup baris aktif dan membuat baris baru.
-- ---------------------------------------------------------------------------

CREATE TABLE employee_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  org_unit_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  position_id BIGINT UNSIGNED NULL,
  manager_employee_id BIGINT UNSIGNED NULL,
  work_location_id BIGINT UNSIGNED NULL,
  effective_from DATE NOT NULL,
  effective_until DATE NULL,
  assignment_status VARCHAR(24) NOT NULL DEFAULT 'active',
  change_reason VARCHAR(64) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assignments_employee (employee_id, effective_from),
  KEY idx_assignments_company (tenant_id, company_id),
  KEY idx_assignments_department (tenant_id, department_id),
  KEY idx_assignments_position (tenant_id, position_id),
  CONSTRAINT fk_assignments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_unit FOREIGN KEY (org_unit_id) REFERENCES org_units (id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_position FOREIGN KEY (position_id) REFERENCES positions (id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_manager FOREIGN KEY (manager_employee_id) REFERENCES employees (id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_location FOREIGN KEY (work_location_id) REFERENCES work_locations (id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
