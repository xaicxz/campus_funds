<?php
// Database-independent release 2 checks. Run with PHP CLI after installation.
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once dirname(__DIR__).'/app/report_service.php';
function verify(bool $condition,string $label): void {if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);} echo "PASS: $label\n";}
verify(cents('125.75')===12575,'Peso -> integer cents');
verify(peso_str(12575)==='125.75','Integer cents -> Peso decimal');
verify(valid_date('2026-10-22') && !valid_date('2026-02-30'),'Real calendar date checks');
verify(str_starts_with(r2_escaped_csv_value('=HYPERLINK("x")'),"'"),'CSV spreadsheet formula text escaping');
verify(str_starts_with(r2_escaped_csv_value(' +SUM(A1)'),"'"),'CSV leading whitespace formula escaping');
verify(r2_escaped_csv_value('Normal description')==='Normal description','Safe normal cells unchanged');
verify(str_contains(e('<script>'),'&lt;script&gt;'),'HTML output escaped');
verify(count(r2_report_types(false))===7 && count(r2_report_types(true))===1,'Report type scoping');
verify((new DateTimeImmutable('now'))->getTimezone()->getName()==='Asia/Manila','Manila timezone configured');
$pdoCheck=extension_loaded('pdo_mysql')?'available':'NOT installed/enabled';
echo "pdo_mysql: $pdoCheck (required for real database tests).\n";
echo "Database-independent smoke tests passed. Run tests/RELEASE2_ACCEPTANCE.md on MySQL for financial verification.\n";
