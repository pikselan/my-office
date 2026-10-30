-- ---------------------------------------------------------------------------
-- Rekrutmen: lowongan, kandidat, tahapan, dan wawancara.
-- ---------------------------------------------------------------------------

CREATE TABLE job_postings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  position_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  requirements TEXT NULL,
  employment_type VARCHAR(24) NOT NULL DEFAULT 'permanent',
  vacancy_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  closes_at DATETIME NULL,
  hiring_manager_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_postings_slug (tenant_id, slug),
  KEY idx_job_postings_company (tenant_id, company_id, status),
  CONSTRAINT fk_job_postings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_job_postings_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_job_postings_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_job_postings_position FOREIGN KEY (position_id) REFERENCES positions (id) ON DELETE SET NULL,
  CONSTRAINT fk_job_postings_manager FOREIGN KEY (hiring_manager_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  job_posting_id BIGINT UNSIGNED NULL,
  full_name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  source VARCHAR(48) NULL,
  resume_path VARCHAR(255) NULL,
  cover_letter TEXT NULL,
  current_stage VARCHAR(32) NOT NULL DEFAULT 'applied',
  rating TINYINT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  converted_employee_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_candidates_tenant (tenant_id, status),
  KEY idx_candidates_posting (tenant_id, job_posting_id),
  CONSTRAINT fk_candidates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_candidates_posting FOREIGN KEY (job_posting_id) REFERENCES job_postings (id) ON DELETE SET NULL,
  CONSTRAINT fk_candidates_employee FOREIGN KEY (converted_employee_id) REFERENCES employees (id) ON DELETE SET NULL,
  CONSTRAINT fk_candidates_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidate_stage_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  from_stage VARCHAR(32) NULL,
  to_stage VARCHAR(32) NOT NULL,
  note TEXT NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_candidate_stage_history (candidate_id, created_at),
  CONSTRAINT fk_candidate_stage_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_candidate_stage_history_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_candidate_stage_history_actor FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recruitment_interviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  interviewer_user_id BIGINT UNSIGNED NULL,
  scheduled_at DATETIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  mode VARCHAR(24) NOT NULL DEFAULT 'online',
  location VARCHAR(190) NULL,
  result VARCHAR(24) NOT NULL DEFAULT 'pending',
  score DECIMAL(4,1) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_recruitment_interviews_candidate (candidate_id, scheduled_at),
  CONSTRAINT fk_recruitment_interviews_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_recruitment_interviews_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_recruitment_interviews_interviewer FOREIGN KEY (interviewer_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
