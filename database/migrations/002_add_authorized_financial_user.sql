-- FOR EXISTING INSTALLATIONS ONLY. Import this once into the existing campus_funds_db
-- through phpMyAdmin BEFORE running scripts/seed_demo_accounts.php.
-- Fresh installations have this role in database/schema.sql already.
USE campus_funds_db;
ALTER TABLE organization_user_roles
    MODIFY COLUMN role ENUM(
        'system_admin', 'sg_finance', 'president', 'treasurer', 'adviser',
        'authorized_financial_user', 'officer', 'member', 'auditor'
    ) NOT NULL;
