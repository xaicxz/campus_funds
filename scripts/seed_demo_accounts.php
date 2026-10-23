<?php
/**
 * LSL Financial Portal DEMONSTRATION ACCOUNTS (local development only).
 * Run from Windows Command Prompt:
 *   C:\xampp\php\php.exe scripts\seed_demo_accounts.php
 *
 * Creates 30 distinct demonstration logins with 24-character random passwords.
 * Passwords are hashed with password_hash(), never saved as plain text in MySQL.
 * Outputs a one-time, private CSV OUTSIDE the typical XAMPP htdocs folder.
 * This is not a public HTTP endpoint and must not be used for production data.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once dirname(__DIR__).'/app/bootstrap.php';

$output = $argv[1] ?? dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'CampusFunds_Demo_Credentials.csv';
if (is_file($output)) {
    fwrite(STDERR, "Credential file already exists: {$output}\nMove it to a secure location or choose a different output path. Nothing was changed.\n");
    exit(1);
}
if (!is_dir(dirname($output)) || !is_writable(dirname($output))) {
    fwrite(STDERR, "Output directory is unavailable or not writable: ".dirname($output)."\n");
    exit(1);
}

$accountSpecs = [
    ['SG','system_admin','Demo System Administrator','admin@campusfunds.test'],
    ['SG','sg_finance','Demo SG Financial Administrator','sg.finance@campusfunds.test'],
];
$orgs = ['SELECT','PSCAS','JPIA','CRIM'];
$roles = [
    'president'=>'President',
    'treasurer'=>'Treasurer',
    'adviser'=>'Adviser',
    'authorized_financial_user'=>'Authorized Financial User',
    'officer'=>'Officer',
    'member'=>'Member',
    'auditor'=>'Auditor',
];
foreach ($orgs as $code) {
    foreach ($roles as $role => $label) {
        $local = $role === 'authorized_financial_user' ? 'authorized' : $role;
        $accountSpecs[] = [$code,$role,"Demo {$code} {$label}",strtolower($code).'.'.$local.'@campusfunds.test'];
    }
}

$databaseRoleType = one("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='organization_user_roles' AND COLUMN_NAME='role'");
if (!$databaseRoleType || !str_contains($databaseRoleType['COLUMN_TYPE'], 'authorized_financial_user')) {
    fwrite(STDERR, "Account role schema is outdated. Import database/migrations/002_add_authorized_financial_user.sql in phpMyAdmin first.\n");
    exit(1);
}
$orgRows = rows('SELECT id,code FROM organizations WHERE active=1');
$orgIds = array_column($orgRows, 'id', 'code');
foreach (['SG',...$orgs] as $code) {
    if (!isset($orgIds[$code])) {
        fwrite(STDERR, "Missing organization {$code}. Import database/schema.sql first.\n");
        exit(1);
    }
}
$csv = @fopen($output, 'x'); // do not overwrite other people's credential files
if ($csv === false) {
    fwrite(STDERR,"Could not create credential file at {$output}\n");
    exit(1);
}
@chmod($output, 0600); // effective on Unix; protect the file manually on Windows
$created = 0;
$skipped = [];
try {
    fputcsv($csv, ['Organization','Role','Full Name','Email','Initial Password']);
    db()->beginTransaction();
    foreach ($accountSpecs as [$code,$role,$name,$email]) {
        if (one('SELECT id FROM users WHERE email=?',[$email])) {
            $skipped[] = $email;
            continue; // Never take over an existing user, grant roles, or reset a password.
        }
        $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        execute_sql('INSERT INTO users (full_name,email,password_hash) VALUES (?,?,?)',
                    [$name,$email,password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)db()->lastInsertId();
        execute_sql('INSERT INTO organization_user_roles (user_id,organization_id,role) VALUES (?,?,?)',
                    [$userId,(int)$orgIds[$code],$role]);
        if (fputcsv($csv, [$code,$role,$name,$email,$password]) === false) {
            throw new RuntimeException('Could not write login credentials to private file.');
        }
        $created++;
    }
    if (fflush($csv) === false) throw new RuntimeException('Could not flush credentials.');
    if (!db()->commit()) throw new RuntimeException('Database commit failed.');
    fclose($csv);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fclose($csv);
    @unlink($output);
    fwrite(STDERR,'Demo account setup failed: '.$e->getMessage()."\n");
    exit(1);
}
if ($created === 0) { @unlink($output); }
echo "Created {$created} demo accounts. Existing accounts skipped: ".count($skipped)."\n";
if ($created > 0) {
    echo "Credentials (KEEP PRIVATE): {$output}\n";
    echo "Each login uses its own random password. Move this file somewhere private after import.\n";
}
if ($skipped) echo 'Skipped, unchanged: '.implode(', ', $skipped)."\n";
echo "Sign in at: http://localhost/campus_funds/public/index.php?page=login\n";
