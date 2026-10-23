<?php
// CLI ONLY: php scripts/install_admin.php "Admin Name" "admin@school.edu" "Strong-Password-123"
if (PHP_SAPI!=='cli') {http_response_code(403);exit('CLI only');}
require_once dirname(__DIR__).'/app/bootstrap.php';
[$script,$name,$email,$password]=array_pad($argv,4,'');
if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) {
    fwrite(STDERR,"Usage: php scripts/install_admin.php \"Full Name\" \"email@school.edu\" \"PasswordAtLeast12Chars\"\n");exit(1);
}
try {
    db()->beginTransaction();
    if (one('SELECT id FROM users WHERE email=?',[$email])) throw new RuntimeException('Email already exists.');
    execute_sql('INSERT INTO users (full_name,email,password_hash) VALUES (?,?,?)',[$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);
    $id=(int)db()->lastInsertId();
    execute_sql("INSERT INTO organization_user_roles (user_id,organization_id,role) VALUES (?,1,'system_admin')",[$id]);
    db()->commit();echo "Created system administrator: $email\n";
} catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
