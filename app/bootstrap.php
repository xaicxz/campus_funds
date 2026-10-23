<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    session_start();
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = require dirname(__DIR__) . '/config/database.php';
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    }
    return $pdo;
}
function rows(string $sql, array $args = []): array { $st=db()->prepare($sql); $st->execute($args); return $st->fetchAll(); }
function one(string $sql, array $args = []): ?array { $st=db()->prepare($sql); $st->execute($args); return $st->fetch() ?: null; }
function execute_sql(string $sql, array $args = []): int { $st=db()->prepare($sql); $st->execute($args); return $st->rowCount(); }
function e(mixed $x): string { return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) abort(419, 'Invalid request token. Please reload.'); }
function me(): ?array { return $_SESSION['user'] ?? null; }
function login_required(): void { if (!me()) { header('Location: index.php?page=login'); exit; } }
function my_roles(?int $org = null): array {
    if (!me()) return [];
    $sql='SELECT r.role,r.organization_id,o.name AS organization_name,o.code FROM organization_user_roles r JOIN organizations o ON o.id=r.organization_id WHERE r.user_id=? AND r.active=1 AND o.active=1';
    $params=[me()['id']];
    if ($org !== null) { $sql.=' AND r.organization_id=?'; $params[]=$org; }
    return rows($sql,$params);
}
function has_role(string $role, ?int $org=null): bool {
    foreach (my_roles($org) as $r) if ($r['role']===$role) return true;
    return false;
}
function scoped_orgs(): array {
    $all = rows('SELECT id,name,code FROM organizations WHERE is_sg=0 AND active=1 ORDER BY name');
    // SG finance may see consolidated totals, but not organization transaction details without an explicit assignment.
    return array_values(array_filter($all,fn($o)=>count(array_filter(my_roles((int)$o['id']),fn($r)=>$r['role']!=='member'))>0));
}
function selected_org(): int {
    $org=filter_var($_REQUEST['org'] ?? 0, FILTER_VALIDATE_INT);
    if (!$org || !count(my_roles((int)$org))) abort(403,'No permission for this organization.');
    $check=one('SELECT is_sg FROM organizations WHERE id=? AND active=1',[(int)$org]);
    if (!$check || (int)$check['is_sg']===1) abort(403,'Choose a participating organization.');
    return (int)$org;
}
function require_role(int $org,array $roles): void {
    foreach($roles as $r) if(has_role($r,$org)) return;
    abort(403,'Your assigned role cannot perform this action.');
}
function abort(int $status,string $message): never {
    http_response_code($status); throw new RuntimeException($message,$status);
}
function redirect_to(string $page,?int $org=null,string $msg=''): never {
    if ($msg !== '') $_SESSION['flash']=$msg;
    header('Location: index.php?'.http_build_query(array_filter(['page'=>$page,'org'=>$org], fn($v)=>$v!==null)));
    exit;
}
function money(mixed $n): string { return '₱'.number_format((float)$n,2); }
function cents(mixed $raw): int {
    $str=(string)$raw;
    if (!preg_match('/^\d{1,11}(?:\.\d{1,2})?$/',$str)) abort(422,'Enter a valid positive peso amount with up to 2 decimals.');
    [$pesos,$fraction]=array_pad(explode('.',$str,2),2,'');
    $value=(int)$pesos*100+(int)str_pad($fraction,2,'0');
    if ($value<=0) abort(422,'Amount must be above zero.');
    return $value;
}
function cents_db(mixed $raw): int { return (int)round((float)$raw*100); }
function peso_str(int $cents): string { return sprintf('%d.%02d',intdiv($cents,100),$cents%100); }
function audit(string $action,string $type,int $entity,?int $org,string $details=''): void {
    execute_sql('INSERT INTO audit_logs (actor_id,organization_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?,?)',
        [me()['id'] ?? null,$org,$action,$type,$entity,$details]);
}
function active_year(): array {
    $year=one('SELECT * FROM academic_years WHERE active=1 LIMIT 1');
    if (!$year) abort(500,'No active academic year configured.');
    return $year;
}
function cash_summary(int $org): array { return r2_financial_summary($org); }
function budget_left(int $budgetId): int {
    $v=one('SELECT COALESCE(SUM(allocated),0) total FROM budget_lines WHERE budget_id=?',[$budgetId]);
    return cents_db($v['total'])-r2_budget_expenses($budgetId);
}
function request_ref(): string { return 'FR-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4))); }
function valid_date(string $s): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s); return $d!==false && $d->format('Y-m-d')===$s; }
function must_date(string $s): string { if (!valid_date($s)) abort(422,'Enter a valid date.'); return $s; }
function safe_text(string $x,int $max=180): string { $x=trim($x); if ($x==='' || strlen($x)>$max) abort(422,'A required text field is empty or too long.'); return $x; }
