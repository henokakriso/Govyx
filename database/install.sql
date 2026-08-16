-- GOVYX Schema v1.0
-- AI Governance Brain - Project ARWE
-- Stack: MySQL/MariaDB

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS govyx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE govyx;

-- ---------------------------------------------------------------------------
-- Identity & Access
-- ---------------------------------------------------------------------------

CREATE TABLE roles (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(64)  NOT NULL UNIQUE,
    name         VARCHAR(128) NOT NULL,
    description  TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(64)  NOT NULL UNIQUE,
    name         VARCHAR(128) NOT NULL,
    description  TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Administrative hierarchy (configurable, not hard-coded)
-- Federal -> Region -> Zone -> Woreda -> Kebele -> Department/Office -> Official
-- ---------------------------------------------------------------------------

CREATE TABLE organizations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(32)  NOT NULL UNIQUE,
    name          VARCHAR(255) NOT NULL,
    type          VARCHAR(32)  NOT NULL DEFAULT 'organization',
    parent_id     INT UNSIGNED NULL,
    region        VARCHAR(128) NULL,
    zone          VARCHAR(128) NULL,
    woreda        VARCHAR(128) NULL,
    kebele        VARCHAR(128) NULL,
    status        VARCHAR(16)  NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_parent FOREIGN KEY (parent_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE departments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id   INT UNSIGNED NOT NULL,
    code              VARCHAR(32)  NOT NULL,
    name              VARCHAR(255) NOT NULL,
    manager_user_id   INT UNSIGNED NULL,
    status            VARCHAR(16)  NOT NULL DEFAULT 'active',
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dept_code (organization_id, code),
    CONSTRAINT fk_dept_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NULL,
    phone           VARCHAR(32)  NULL,
    role_id         INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED NULL,
    status          VARCHAR(16)  NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_user_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE departments
    ADD CONSTRAINT fk_dept_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE officials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED NOT NULL,
    position        VARCHAR(255) NULL,
    grade           VARCHAR(64)  NULL,
    status          VARCHAR(16)  NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_off_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_off_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_off_dept FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Tasks
-- ---------------------------------------------------------------------------

CREATE TABLE tasks (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(32)  NOT NULL UNIQUE,
    title              VARCHAR(255) NOT NULL,
    description        TEXT NULL,
    organization_id    INT UNSIGNED NOT NULL,
    department_id      INT UNSIGNED NULL,
    assigned_to        INT UNSIGNED NULL,
    created_by         INT UNSIGNED NOT NULL,
    priority           VARCHAR(16)  NOT NULL DEFAULT 'medium',
    start_date         DATE NULL,
    deadline           DATE NULL,
    completed_at       DATETIME NULL,
    status             VARCHAR(24)  NOT NULL DEFAULT 'created',
    progress           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    dependencies       JSON NULL,
    approval_by        INT UNSIGNED NULL,
    approval_at        DATETIME NULL,
    approval_note      TEXT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_task_status (status),
    KEY idx_task_deadline (deadline),
    KEY idx_task_assignee (assigned_to),
    KEY idx_task_org (organization_id),
    CONSTRAINT fk_task_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_task_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_task_approver FOREIGN KEY (approval_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE task_transitions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id       INT UNSIGNED NOT NULL,
    from_status   VARCHAR(24) NULL,
    to_status     VARCHAR(24) NOT NULL,
    action_by     INT UNSIGNED NOT NULL,
    note          TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tt_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_user FOREIGN KEY (action_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Projects
-- ---------------------------------------------------------------------------

CREATE TABLE projects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(32)  NOT NULL UNIQUE,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    organization_id INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED NULL,
    status          VARCHAR(24)  NOT NULL DEFAULT 'planning',
    progress        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    start_date      DATE NULL,
    end_date        DATE NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proj_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_proj_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_proj_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- KPI System
-- ---------------------------------------------------------------------------

CREATE TABLE kpis (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(32)  NOT NULL UNIQUE,
    name                VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    organization_id     INT UNSIGNED NOT NULL,
    department_id       INT UNSIGNED NULL,
    measurement_method  VARCHAR(255) NULL,
    target              DECIMAL(14,2) NOT NULL DEFAULT 0,
    actual              DECIMAL(14,2) NOT NULL DEFAULT 0,
    unit                VARCHAR(32)  NULL,
    period              VARCHAR(16)  NULL,
    weight              DECIMAL(5,2) NULL DEFAULT 1.00,
    threshold           DECIMAL(5,2) NULL DEFAULT 70.00,
    status              VARCHAR(16)  NOT NULL DEFAULT 'active',
    created_by          INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_kpi_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_kpi_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_kpi_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE kpi_measurements (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kpi_id        INT UNSIGNED NOT NULL,
    period        VARCHAR(16)  NOT NULL,
    target        DECIMAL(14,2) NOT NULL,
    actual        DECIMAL(14,2) NOT NULL,
    achievement   DECIMAL(5,2) NULL,
    measured_by   INT UNSIGNED NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_kpi_period (kpi_id, period),
    CONSTRAINT fk_km_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE,
    CONSTRAINT fk_km_user FOREIGN KEY (measured_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Performance & Rankor
-- ---------------------------------------------------------------------------

CREATE TABLE performance_records (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    official_id       INT UNSIGNED NOT NULL,
    organization_id   INT UNSIGNED NOT NULL,
    department_id     INT UNSIGNED NOT NULL,
    period            VARCHAR(16)  NOT NULL,
    kpi_achievement   DECIMAL(5,2) NULL,
    timeliness        DECIMAL(5,2) NULL,
    quality           DECIMAL(5,2) NULL,
    completion        DECIMAL(5,2) NULL,
    total_score       DECIMAL(5,2) NOT NULL,
    method_version    VARCHAR(16)  NULL,
    explanation       TEXT NULL,
    calculated_by     INT UNSIGNED NOT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_official FOREIGN KEY (official_id) REFERENCES officials(id),
    CONSTRAINT fk_pr_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_pr_dept FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_pr_calc FOREIGN KEY (calculated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE rankor_analyses (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type      VARCHAR(32)  NOT NULL,
    target_id        INT UNSIGNED NOT NULL,
    score_type       VARCHAR(32)  NOT NULL,
    score            DECIMAL(6,2) NOT NULL,
    confidence       DECIMAL(5,2) NULL,
    method_version   VARCHAR(16)  NULL,
    factors_json     JSON NULL,
    explanation      TEXT NULL,
    source           VARCHAR(32)  NOT NULL DEFAULT 'php',
    created_by       INT UNSIGNED NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ra_target (target_type, target_id, score_type)
) ENGINE=InnoDB;

CREATE TABLE risk_alerts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NULL,
    department_id   INT UNSIGNED NULL,
    task_id         INT UNSIGNED NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    severity        VARCHAR(16)  NOT NULL DEFAULT 'medium',
    factors_json    JSON NULL,
    status          VARCHAR(24)  NOT NULL DEFAULT 'open',
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME NULL,
    review_note     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ra_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    CONSTRAINT fk_ra_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_ra_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    CONSTRAINT fk_ra_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Notifications / Reports / Evidence / Audit
-- ---------------------------------------------------------------------------

CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    message     TEXT NULL,
    type        VARCHAR(32)  NOT NULL DEFAULT 'info',
    channel     VARCHAR(16)  NOT NULL DEFAULT 'in-app',
    related_type VARCHAR(32) NULL,
    related_id  INT UNSIGNED NULL,
    read_at     DATETIME NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user (user_id, read_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    type            VARCHAR(64)  NOT NULL,
    organization_id INT UNSIGNED NULL,
    period          VARCHAR(16)  NULL,
    generated_by    INT UNSIGNED NOT NULL,
    json_data       JSON NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rep_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    CONSTRAINT fk_rep_gen FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE evidence (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(512) NOT NULL,
    file_type   VARCHAR(128) NULL,
    file_size   INT UNSIGNED NULL,
    checksum    CHAR(64) NULL,
    version     INT UNSIGNED NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ev_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NULL,
    action       VARCHAR(64)  NOT NULL,
    entity_type  VARCHAR(64)  NULL,
    entity_id    INT UNSIGNED NULL,
    details_json JSON NULL,
    ip_address   VARCHAR(64)  NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;

CREATE TABLE settings (
    `key`       VARCHAR(128) NOT NULL PRIMARY KEY,
    `value`     TEXT NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;