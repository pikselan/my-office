-- ---------------------------------------------------------------------------
-- Onboarding karyawan: template, proses, dan checklist tugas.
-- ---------------------------------------------------------------------------

CREATE TABLE onboarding_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_onboarding_templates_tenant (tenant_id, is_active),
  CONSTRAINT fk_onboarding_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_templates_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE onboarding_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  assigned_role VARCHAR(48) NULL,
  due_offset_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  requires_document TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_onboarding_tasks_template (template_id, sort_order),
  CONSTRAINT fk_onboarding_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_tasks_template FOREIGN KEY (template_id) REFERENCES onboarding_templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE onboarding_processes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  starts_at DATE NOT NULL,
  target_completion_at DATE NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'not_started',
  completed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_onboarding_processes_employee (employee_id, template_id),
  KEY idx_onboarding_processes_tenant (tenant_id, status),
  CONSTRAINT fk_onboarding_processes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_processes_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_processes_template FOREIGN KEY (template_id) REFERENCES onboarding_templates (id) ON DELETE RESTRICT,
  CONSTRAINT fk_onboarding_processes_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_processes_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE onboarding_task_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  process_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  assignee_user_id BIGINT UNSIGNED NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  due_at DATE NULL,
  completed_at DATETIME NULL,
  completed_by BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_onboarding_task_assignments (process_id, task_id),
  KEY idx_onboarding_task_assignments_status (tenant_id, status),
  CONSTRAINT fk_onboarding_task_assignments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_task_assignments_process FOREIGN KEY (process_id) REFERENCES onboarding_processes (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_task_assignments_task FOREIGN KEY (task_id) REFERENCES onboarding_tasks (id) ON DELETE CASCADE,
  CONSTRAINT fk_onboarding_task_assignments_assignee FOREIGN KEY (assignee_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_onboarding_task_assignments_completer FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
