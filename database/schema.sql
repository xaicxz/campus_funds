-- CAMPUSFUNDS | Release 1 starter database for MySQL 8 / MariaDB 10.4+
-- Import once in phpMyAdmin. All amounts stored as DECIMAL(14,2).
CREATE DATABASE IF NOT EXISTS campus_funds_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_funds_db;

CREATE TABLE IF NOT EXISTS organizations (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
 code VARCHAR(20) NOT NULL UNIQUE, is_sg TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS academic_years (
 id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(20) NOT NULL UNIQUE,
 starts_on DATE NOT NULL, ends_on DATE NOT NULL, active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS organization_user_roles (
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, organization_id INT NOT NULL,
 role ENUM('system_admin','sg_finance','president','treasurer','adviser','authorized_financial_user','officer','member','auditor') NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_assignment (user_id,organization_id,role),
 FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (organization_id) REFERENCES organizations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS organization_settings (
 organization_id INT PRIMARY KEY, require_sg_budget_approval TINYINT(1) NOT NULL DEFAULT 0,
 FOREIGN KEY (organization_id) REFERENCES organizations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS financial_accounts (
 id INT AUTO_INCREMENT PRIMARY KEY, organization_id INT NOT NULL,
 name VARCHAR(100) NOT NULL, account_type ENUM('cash','bank') NOT NULL DEFAULT 'cash',
 opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
 UNIQUE KEY uq_account (organization_id,name),
 UNIQUE KEY uq_account_org (id,organization_id),
 FOREIGN KEY (organization_id) REFERENCES organizations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS budgets (
 id INT AUTO_INCREMENT PRIMARY KEY, organization_id INT NOT NULL, academic_year_id INT NOT NULL,
 name VARCHAR(180) NOT NULL, budget_type ENUM('annual','event','project') NOT NULL,
 status ENUM('pending_president','pending_adviser','pending_sg','approved','rejected') NOT NULL DEFAULT 'pending_president',
 created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_budget_org (id,organization_id),
 FOREIGN KEY (organization_id) REFERENCES organizations(id),
 FOREIGN KEY (academic_year_id) REFERENCES academic_years(id), FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS budget_lines (
 id INT AUTO_INCREMENT PRIMARY KEY, budget_id INT NOT NULL, category VARCHAR(100) NOT NULL,
 allocated DECIMAL(14,2) NOT NULL, UNIQUE KEY uq_budget_category (budget_id,category),
 FOREIGN KEY (budget_id) REFERENCES budgets(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS budget_approvals (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, budget_id INT NOT NULL, actor_id INT NOT NULL,
 stage ENUM('president','adviser','sg_finance') NOT NULL,
 decision ENUM('approved','rejected') NOT NULL, reason TEXT NULL,
 decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_budget_decision_stage (budget_id,stage),
 FOREIGN KEY (budget_id) REFERENCES budgets(id), FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS fund_requests (
 id INT AUTO_INCREMENT PRIMARY KEY, organization_id INT NOT NULL, budget_id INT NOT NULL,
 reference_no VARCHAR(40) NOT NULL UNIQUE, title VARCHAR(180) NOT NULL,
 purpose TEXT NOT NULL, activity_date DATE NOT NULL,
 amount DECIMAL(14,2) NOT NULL, requested_by INT NOT NULL,
 status ENUM('pending_treasurer','pending_adviser','pending_president','approved','rejected') NOT NULL DEFAULT 'pending_treasurer',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_request_org (id,organization_id),
 FOREIGN KEY (organization_id) REFERENCES organizations(id),
 FOREIGN KEY (budget_id) REFERENCES budgets(id), FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS fund_request_approvals (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, fund_request_id INT NOT NULL, actor_id INT NOT NULL,
 stage ENUM('treasurer','adviser','president') NOT NULL,
 decision ENUM('approved','rejected') NOT NULL, reason TEXT NULL,
 decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_request_decision_stage (fund_request_id,stage),
 FOREIGN KEY (fund_request_id) REFERENCES fund_requests(id), FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS financial_transactions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, organization_id INT NOT NULL, account_id INT NOT NULL,
 budget_line_id INT NULL, fund_request_id INT NULL,
 transaction_type ENUM('income','expense') NOT NULL,
 category VARCHAR(100) NOT NULL, description VARCHAR(255) NOT NULL,
 amount DECIMAL(14,2) NOT NULL, occurred_on DATE NOT NULL,
 payment_method VARCHAR(60) NOT NULL, status ENUM('pending','posted') NOT NULL DEFAULT 'pending',
 created_by INT NOT NULL, posted_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 posted_at TIMESTAMP NULL,
 UNIQUE KEY uq_tx_org (id,organization_id),
 FOREIGN KEY (organization_id) REFERENCES organizations(id),
 FOREIGN KEY (account_id,organization_id) REFERENCES financial_accounts(id,organization_id),
 FOREIGN KEY (budget_line_id) REFERENCES budget_lines(id),
 FOREIGN KEY (fund_request_id) REFERENCES fund_requests(id),
 FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (posted_by) REFERENCES users(id),
 INDEX ix_tx_scope (organization_id,status,occurred_on)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS transaction_attachments (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, transaction_id BIGINT NOT NULL,
 stored_name VARCHAR(100) NOT NULL UNIQUE, original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(100) NOT NULL, uploaded_by INT NOT NULL,
 uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id), FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS liquidations (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, fund_request_id INT NOT NULL UNIQUE,
 status ENUM('pending','under_review','complete') NOT NULL DEFAULT 'pending',
 remarks TEXT NULL, FOREIGN KEY (fund_request_id) REFERENCES fund_requests(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message VARCHAR(255) NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS financial_calendar_items (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, organization_id INT NOT NULL,
 title VARCHAR(160) NOT NULL, due_on DATE NOT NULL,
 FOREIGN KEY (organization_id) REFERENCES organizations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, actor_id INT NULL, organization_id INT NULL,
 action VARCHAR(100) NOT NULL, entity_type VARCHAR(60) NOT NULL,
 entity_id BIGINT NOT NULL, details TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (actor_id) REFERENCES users(id), FOREIGN KEY (organization_id) REFERENCES organizations(id),
 INDEX ix_audit_org (organization_id,created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO organizations (id,name,code,is_sg) VALUES
 (1,'Student Government','SG',1),
 (2,'SELECT LSL','SELECT',0),
 (3,'PSCAS LSL','PSCAS',0),
 (4,'JPIA LSL','JPIA',0),
 (5,'CRIM Student Society LSL','CRIM',0);
INSERT IGNORE INTO academic_years (id,label,starts_on,ends_on,active)
 VALUES (1,'2026-2027','2026-08-01','2027-07-31',1);
INSERT IGNORE INTO organization_settings (organization_id,require_sg_budget_approval)
 VALUES (2,0),(3,0),(4,0),(5,0);
INSERT IGNORE INTO financial_accounts (organization_id,name,account_type,opening_balance)
 VALUES (2,'Main Cash','cash',0),(3,'Main Cash','cash',0),
 (4,'Main Cash','cash',0),(5,'Main Cash','cash',0);
