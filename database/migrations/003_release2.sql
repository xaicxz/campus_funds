-- CampusFunds R2 migration. BACK UP database and storage BEFORE applying. Run ONCE.
-- Run in phpMyAdmin after the Release 1 schema (and 002 migration for older installations).
USE campus_funds_db;
ALTER TABLE financial_transactions
 MODIFY transaction_type ENUM('income','expense','advance_disbursement','advance_return') NOT NULL,
 ADD COLUMN event_key VARCHAR(90) NULL,
 ADD UNIQUE KEY uq_financial_event_key (event_key);
ALTER TABLE fund_requests
 ADD COLUMN commitment_released DECIMAL(14,2) NOT NULL DEFAULT 0,
 ADD COLUMN closed_at DATETIME NULL;
ALTER TABLE liquidations
 MODIFY status ENUM('pending','under_review','complete','draft','submitted','returned_for_revision','verified','closed','rejected') NOT NULL DEFAULT 'pending';
UPDATE liquidations SET status='closed' WHERE status='complete';
ALTER TABLE liquidations
 MODIFY status ENUM('pending','draft','submitted','under_review','returned_for_revision','verified','closed','rejected') NOT NULL DEFAULT 'pending',
 ADD COLUMN due_on DATE NULL,
 ADD COLUMN final_submission_id BIGINT NULL,
 ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE organization_settings ADD COLUMN liquidation_due_days INT NOT NULL DEFAULT 14;
CREATE TABLE IF NOT EXISTS liquidation_submissions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 liquidation_id BIGINT NOT NULL,
 version_number INT NOT NULL,
 submitted_by INT NOT NULL,
 submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 item_total DECIMAL(14,2) NOT NULL,
 reviewer_note TEXT NULL,
 UNIQUE KEY uq_liquidation_version (liquidation_id,version_number),
 FOREIGN KEY (liquidation_id) REFERENCES liquidations(id),
 FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS liquidation_items (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 submission_id BIGINT NOT NULL,
 occurred_on DATE NOT NULL,
 payee VARCHAR(150) NOT NULL,
 description VARCHAR(255) NOT NULL,
 budget_line_id INT NOT NULL,
 amount DECIMAL(14,2) NOT NULL,
 stored_name VARCHAR(100) NOT NULL UNIQUE,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 FOREIGN KEY (submission_id) REFERENCES liquidation_submissions(id),
 FOREIGN KEY (budget_line_id) REFERENCES budget_lines(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS liquidation_decisions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 liquidation_id BIGINT NOT NULL,
 submission_id BIGINT NOT NULL,
 reviewer_id INT NOT NULL,
 stage ENUM('treasurer','adviser','president') NOT NULL,
 decision ENUM('approved','returned','rejected','closed') NOT NULL,
 reason TEXT NULL,
 decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_liquidation_stage (submission_id,stage),
 FOREIGN KEY (liquidation_id) REFERENCES liquidations(id),
 FOREIGN KEY (submission_id) REFERENCES liquidation_submissions(id),
 FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB;
ALTER TABLE notifications
 ADD COLUMN organization_id INT NULL,
 ADD COLUMN category VARCHAR(40) NOT NULL DEFAULT 'general',
 ADD COLUMN event_key VARCHAR(140) NULL,
 ADD COLUMN link_page VARCHAR(40) NULL,
 ADD UNIQUE KEY uq_notification_event (event_key),
 ADD KEY ix_notifications_inbox (user_id,is_read,created_at),
 ADD FOREIGN KEY (organization_id) REFERENCES organizations(id);
ALTER TABLE financial_calendar_items
 ADD COLUMN item_type VARCHAR(40) NOT NULL DEFAULT 'manual',
 ADD COLUMN fund_request_id INT NULL,
 ADD COLUMN created_by INT NULL,
 ADD COLUMN completed_at DATETIME NULL,
 ADD COLUMN details VARCHAR(255) NULL,
 ADD UNIQUE KEY uq_calendar_request (fund_request_id,item_type),
 ADD FOREIGN KEY (fund_request_id) REFERENCES fund_requests(id),
 ADD FOREIGN KEY (created_by) REFERENCES users(id);
-- Optional dependency managed via composer.json, not loaded into SQL.
