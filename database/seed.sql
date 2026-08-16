-- GOVYX Seed Data v1.0

SET NAMES utf8mb4;
USE govyx;

-- ---------------------------------------------------------------------------
-- Permissions (Section 25)
-- ---------------------------------------------------------------------------

INSERT INTO permissions (code, name) VALUES
('VIEW_DASHBOARD',       'View Dashboard'),
('VIEW_KPI',             'View KPIs'),
('CREATE_KPI',           'Create KPI'),
('UPDATE_KPI',           'Update KPI'),
('VIEW_TASK',            'View Tasks'),
('CREATE_TASK',          'Create Task'),
('ASSIGN_TASK',          'Assign Task'),
('EDIT_TASK',            'Edit Task'),
('SUBMIT_TASK',          'Submit Task'),
('APPROVE_TASK',         'Approve Task'),
('REJECT_TASK',          'Reject Task'),
('VIEW_PROJECT',         'View Projects'),
('CREATE_PROJECT',       'Create Project'),
('EDIT_PROJECT',         'Edit Project'),
('VIEW_PERFORMANCE',     'View Performance'),
('CALCULATE_PERFORMANCE','Calculate Performance'),
('VIEW_RANKOR',          'View Rankor Analyses'),
('RUN_RANKOR',           'Run Rankor Analysis'),
('VIEW_RISK',            'View Risk Alerts'),
('REVIEW_RISK',          'Review Risk Alerts'),
('VIEW_REPORT',          'View Reports'),
('GENERATE_REPORT',      'Generate Reports'),
('VIEW_AUDIT',           'View Audit Logs'),
('MANAGE_USERS',         'Manage Users'),
('MANAGE_ORGANIZATIONS', 'Manage Organizations'),
('MANAGE_DEPARTMENTS',   'Manage Departments'),
('MANAGE_ROLES',         'Manage Roles'),
('VIEW_NOTIFICATIONS',   'View Notifications'),
('MANAGE_SETTINGS',      'Manage Settings'),
('VIEW_ANALYTICS',       'View Analytics')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------------
-- Roles (Section 25)
-- ---------------------------------------------------------------------------

INSERT INTO roles (code, name, description) VALUES
('super_admin',     'Super Administrator',    'Full system control, all organizations'),
('gov_admin',       'Government Administrator','Federal-level government administration'),
('regional_admin',  'Regional Administrator', 'Regional administration'),
('woreda_admin',    'Woreda Administrator',   'Woreda-level administration'),
('org_admin',       'Organization Administrator','Administration of one organization'),
('dept_manager',    'Department Manager',     'Department-level management'),
('official',        'Official',               'Government official executing tasks'),
('auditor',         'Auditor',                'Read-only audit access'),
('analyst',         'Analyst',                'Analytics and Rankor access'),
('viewer',          'Viewer',                 'Read-only access')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Super Administrator: everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'super_admin';

-- Government Administrator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'gov_admin'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','CREATE_KPI','UPDATE_KPI','VIEW_TASK','CREATE_TASK','ASSIGN_TASK','EDIT_TASK','SUBMIT_TASK','APPROVE_TASK','REJECT_TASK','VIEW_PROJECT','CREATE_PROJECT','EDIT_PROJECT','VIEW_PERFORMANCE','CALCULATE_PERFORMANCE','VIEW_RANKOR','RUN_RANKOR','VIEW_RISK','REVIEW_RISK','VIEW_REPORT','GENERATE_REPORT','VIEW_AUDIT','VIEW_NOTIFICATIONS','VIEW_ANALYTICS');

-- Regional Administrator / Woreda Administrator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('regional_admin','woreda_admin')
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','CREATE_KPI','UPDATE_KPI','VIEW_TASK','CREATE_TASK','ASSIGN_TASK','EDIT_TASK','SUBMIT_TASK','APPROVE_TASK','REJECT_TASK','VIEW_PROJECT','CREATE_PROJECT','EDIT_PROJECT','VIEW_PERFORMANCE','CALCULATE_PERFORMANCE','VIEW_RANKOR','RUN_RANKOR','VIEW_RISK','REVIEW_RISK','VIEW_REPORT','GENERATE_REPORT','VIEW_NOTIFICATIONS','VIEW_ANALYTICS');

-- Organization Administrator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'org_admin'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','CREATE_KPI','UPDATE_KPI','VIEW_TASK','CREATE_TASK','ASSIGN_TASK','EDIT_TASK','SUBMIT_TASK','APPROVE_TASK','REJECT_TASK','VIEW_PROJECT','CREATE_PROJECT','EDIT_PROJECT','VIEW_PERFORMANCE','CALCULATE_PERFORMANCE','VIEW_RANKOR','RUN_RANKOR','VIEW_RISK','REVIEW_RISK','VIEW_REPORT','GENERATE_REPORT','VIEW_NOTIFICATIONS','VIEW_ANALYTICS','MANAGE_DEPARTMENTS');

-- Department Manager
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'dept_manager'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','VIEW_TASK','CREATE_TASK','ASSIGN_TASK','EDIT_TASK','SUBMIT_TASK','APPROVE_TASK','REJECT_TASK','VIEW_PROJECT','VIEW_PERFORMANCE','VIEW_RANKOR','RUN_RANKOR','VIEW_RISK','VIEW_REPORT','VIEW_NOTIFICATIONS');

-- Official
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'official'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','VIEW_TASK','CREATE_TASK','EDIT_TASK','SUBMIT_TASK','VIEW_PROJECT','VIEW_PERFORMANCE','VIEW_RANKOR','VIEW_RISK','VIEW_REPORT','VIEW_NOTIFICATIONS');

-- Auditor
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'auditor'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','VIEW_TASK','VIEW_PROJECT','VIEW_PERFORMANCE','VIEW_RANKOR','VIEW_RISK','VIEW_REPORT','VIEW_AUDIT','VIEW_ANALYTICS');

-- Analyst
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'analyst'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','VIEW_TASK','VIEW_PROJECT','VIEW_PERFORMANCE','VIEW_RANKOR','RUN_RANKOR','VIEW_RISK','VIEW_REPORT','GENERATE_REPORT','VIEW_ANALYTICS');

-- Viewer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'viewer'
  AND p.code IN ('VIEW_DASHBOARD','VIEW_KPI','VIEW_TASK','VIEW_PROJECT','VIEW_PERFORMANCE','VIEW_RANKOR','VIEW_RISK','VIEW_REPORT');

-- ---------------------------------------------------------------------------
-- Administrative hierarchy (Ethiopia example, configurable per Section 24)
-- ---------------------------------------------------------------------------

INSERT INTO organizations (code, name, type, region, zone, woreda) VALUES
('FDRE',   'Federal Democratic Republic of Ethiopia', 'federal', 'Addis Ababa', NULL, NULL),
('AA',     'Addis Ababa City Administration',          'region',  'Addis Ababa', NULL, NULL),
('AACA',   'Addis Ababa City Administration - Central', 'zone',   'Addis Ababa', 'Central', NULL),
('W01',    'Woreda 01',                               'woreda',  'Addis Ababa', 'Central', 'Woreda 01'),
('W02',    'Woreda 02',                               'woreda',  'Addis Ababa', 'Central', 'Woreda 02');

INSERT INTO departments (organization_id, code, name) VALUES
(5, 'ADMIN', 'Administration Office'),
(5, 'FIN',   'Finance Office'),
(5, 'DEV',   'Development Office'),
(4, 'HEALTH','Health Office'),
(4, 'EDU',   'Education Office');

-- ---------------------------------------------------------------------------
-- Users (passwords: "Govyx@2026" hashed - change in production)
-- admin / Govyx@2026
-- ---------------------------------------------------------------------------

INSERT INTO users (username, password_hash, full_name, email, role_id, organization_id, department_id) VALUES
('admin',   '$2y$12$pG26YLePyyLWEu7uccdLu.KF6M5xBre8jSYTOdTNfEhbACz3Y8.8y', 'Super Administrator',   'admin@govyx.et', (SELECT id FROM roles WHERE code='super_admin'), 1, NULL),
('manager', '$2y$12$pG26YLePyyLWEu7uccdLu.KF6M5xBre8jSYTOdTNfEhbACz3Y8.8y', 'Finance Department Manager', 'manager@govyx.et', (SELECT id FROM roles WHERE code='dept_manager'), 5, 2),
('official', '$2y$12$pG26YLePyyLWEu7uccdLu.KF6M5xBre8jSYTOdTNfEhbACz3Y8.8y', 'Government Official',   'official@govyx.et', (SELECT id FROM roles WHERE code='official'), 5, 2),
('auditor', '$2y$12$pG26YLePyyLWEu7uccdLu.KF6M5xBre8jSYTOdTNfEhbACz3Y8.8y', 'System Auditor',        'auditor@govyx.et', (SELECT id FROM roles WHERE code='auditor'), 1, NULL),
('analyst', '$2y$12$pG26YLePyyLWEu7uccdLu.KF6M5xBre8jSYTOdTNfEhbACz3Y8.8y', 'Rankor Analyst',        'analyst@govyx.et', (SELECT id FROM roles WHERE code='analyst'), 5, 2),
('hesum',   '$2y$12$pG26YLePyyLWEu7uccdLu.KF6M5xBre8jSYTOdTNfEhbACz3Y8.8y', 'Hedru Sisay',           'hesum@govyx.et', (SELECT id FROM roles WHERE code='official'), 5, 2);

INSERT INTO officials (user_id, organization_id, department_id, position, grade)
SELECT id, organization_id, COALESCE(department_id, (SELECT id FROM departments LIMIT 1)), 'Government Official', 'Grade I'
FROM users WHERE role_id IN (SELECT id FROM roles WHERE code IN ('official','dept_manager'));

-- ---------------------------------------------------------------------------
-- Tasks (Section 14)
-- UPDATED_COMMENT/placeholder replaced below: statuses follow Created->Assigned->In Progress->Submitted->Reviewed->Completed
-- ---------------------------------------------------------------------------

INSERT INTO tasks (code, title, description, organization_id, department_id, assigned_to, created_by, priority, start_date, deadline, status, progress)
VALUES
('T-2026-0001', 'Prepare quarterly budget report', 'Compile and submit the Q3 budget execution report.', 5, 2, (SELECT id FROM users WHERE username='official'), (SELECT id FROM users WHERE username='manager'), 'high', '2026-07-01', '2026-07-30', 'in_progress', 60),
('T-2026-0002', 'Procurement tender document review', 'Review supplier tender documents for the construction contract.', 5, 2, (SELECT id FROM users WHERE username='official'), (SELECT id FROM users WHERE username='manager'), 'high', '2026-07-05', '2026-07-25', 'in_progress', 40),
('T-2026-0003', 'Update citizen service registry', 'Migrate citizen service data to the new registry format.', 5, 2, (SELECT id FROM users WHERE username='hesum'), (SELECT id FROM users WHERE username='manager'), 'medium', '2026-07-10', '2026-08-15', 'created', 0),
('T-2026-0004', 'Health outreach campaign planning', 'Plan the community health outreach campaign for Woreda 01.', 4, 4, (SELECT id FROM users WHERE username='official'), (SELECT id FROM users WHERE username='manager'), 'medium', '2026-06-20', '2026-06-28', 'completed', 100),
('T-2026-0005', 'Annual staff performance review', 'Complete annual staff performance evaluations for Finance Office.', 5, 2, (SELECT id FROM users WHERE username='hesum'), (SELECT id FROM users WHERE username='manager'), 'high', '2026-07-15', '2026-07-20', 'submitted', 100),
('T-2026-0006', 'Revenue collection report', 'Produce monthly revenue collection and reconciliation report.', 5, 2, (SELECT id FROM users WHERE username='official'), (SELECT id FROM users WHERE username='manager'), 'low', '2026-06-10', '2026-08-05', 'assigned', 10);

INSERT INTO task_transitions (task_id, from_status, to_status, action_by, note)
SELECT id, 'created', 'completed', (SELECT id FROM users WHERE username='manager'), 'Completed ahead of schedule'
FROM tasks WHERE code = 'T-2026-0004';

-- ---------------------------------------------------------------------------
-- Projects
-- ---------------------------------------------------------------------------

INSERT INTO projects (code, name, description, organization_id, department_id, status, progress, start_date, end_date, created_by)
VALUES
('P-2026-001', 'Woreda Digital Service Modernization', 'Digitize citizen services for Woreda 01.', 5, 2, 'in_progress', 45, '2026-01-15', '2026-12-20', (SELECT id FROM users WHERE username='manager')),
('P-2026-002', 'Community Health Campaign 2026', 'Annual health outreach campaign.', 4, 4, 'on_track', 70, '2026-03-01', '2026-09-30', (SELECT id FROM users WHERE username='manager'));

-- ---------------------------------------------------------------------------
-- KPIs (Section 15: Target 100, Actual 92 -> Achievement 92%)
-- ---------------------------------------------------------------------------

INSERT INTO kpis (code, name, description, organization_id, department_id, measurement_method, target, actual, unit, period, weight, threshold, created_by)
VALUES
('KPI-FIN-001', 'Budget Execution Rate', 'Percentage of allocated budget executed on time.', 5, 2, 'executed / allocated * 100', 100, 92, '%', '2026-Q3', 1.50, 70, (SELECT id FROM users WHERE username='manager')),
('KPI-FIN-002', 'Revenue Collection Rate', 'Percentage of annual revenue target collected.', 5, 2, 'collected / target * 100', 100, 78, '%', '2026-Q3', 1.20, 70, (SELECT id FROM users WHERE username='manager')),
('KPI-FIN-003', 'On-time Task Completion', 'Percentage of tasks completed before deadline.', 5, 2, 'completed_on_time / total_completed * 100', 100, 88, '%', '2026-Q3', 1.00, 70, (SELECT id FROM users WHERE username='manager')),
('KPI-HE-001', 'Health Facility Service Availability', 'Service availability index across facilities.', 4, 4, 'available_facilities / total_facilities * 100', 100, 95, '%', '2026-Q3', 1.00, 70, (SELECT id FROM users WHERE username='manager')),
('KPI-EDU-001', 'School Enrollment Rate', 'Percentage of eligible children enrolled.', 4, 5, 'enrolled / eligible * 100', 100, 84, '%', '2026-Q3', 1.00, 70, (SELECT id FROM users WHERE username='manager'));

INSERT INTO kpi_measurements (kpi_id, period, target, actual, achievement, measured_by)
SELECT id, '2026-Q3', target, actual, ROUND(actual / target * 100, 2), (SELECT id FROM users WHERE username='manager')
FROM kpis;

-- ---------------------------------------------------------------------------
-- Risk alerts
-- ---------------------------------------------------------------------------

INSERT INTO risk_alerts (organization_id, department_id, title, description, severity, factors_json, status)
VALUES
(5, 2, 'KPI deterioration detected', 'Revenue collection declined below 80% of target with repeated delays in report submission.', 'high',
 JSON_OBJECT('factors', JSON_ARRAY('Revenue Collection 78%', '3 overdue tasks', 'Workload above threshold')), 'open');

-- ---------------------------------------------------------------------------
-- System settings
-- ---------------------------------------------------------------------------

INSERT INTO settings (`key`, `value`) VALUES
('system.name', 'GOVYX'),
('system.timezone', 'Africa/Addis_Ababa'),
('rankor.method_version', '1.0'),
('performance.method_version', '1.0'),
('auth.session_lifetime', '7200'),
('risk.workload_threshold', '6'),
('risk.delay_threshold', '2');