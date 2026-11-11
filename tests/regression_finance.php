<?php
// Pure finance/security regression checks: do not connect to, or alter, a database.
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require_once dirname(__DIR__).'/app/report_service.php';

function check_eq(mixed $actual,mixed $expected,string $label): void {
    if ($actual!==$expected) {
        fwrite(STDERR,"FAIL: $label (got ".var_export($actual,true).", expected ".var_export($expected,true).")\n");
        exit(1);
    }
    echo "PASS: $label\n";
}
check_eq(cents_db('999999999999.99'),99999999999999,'Precise MySQL DECIMAL conversion at max precision');
check_eq(cents_db('-123456789.01'),-12345678901,'Signed DECIMAL conversion');
check_eq(peso_str(-150),'-1.50','Negative PHP currency formatting');
check_eq(peso_str(-1),'-0.01','Negative one-cent formatting');
check_eq(cents_db(peso_str(-150)),-150,'Signed amount round trip');
check_eq(cents_db('123.4'),12340,'One-decimal stored amount');
try { cents_db('1.234'); fwrite(STDERR,"FAIL: Fractional precision over two digits was accepted.\n"); exit(1); }
catch (InvalidArgumentException $e) { echo "PASS: Invalid stored money precision rejected\n"; }

// Blueprint sequence: request ₱10,000, advance ₱8,000, verify ₱6,500, return ₱1,500,
// release unused ₱2,000 commitment. No reservation may remain after closure.
$tot=['advances'=>800000,'direct'=>0,'recognized'=>0,'returns'=>0];
check_eq(r2_request_budget_reservation(1000000,$tot,0),1000000,'Approval reserves the full request before liquidation');
$tot['recognized']=650000;
check_eq(r2_request_budget_reservation(1000000,$tot,0),350000,'Recognized spending frees the spent budget');
$tot['returns']=150000;
check_eq(r2_request_budget_reservation(1000000,$tot,0),200000,'Returned advance cash is not a remaining budget reservation');
check_eq(r2_request_budget_reservation(1000000,$tot,200000),0,'Closed case has no phantom reservation');
check_eq(r2_request_budget_reservation(1000000,$tot,500000),0,'Reservation is never negative');
check_eq(r2_request_budget_reservation(1000000,['advances'=>0,'direct'=>400000,'recognized'=>0,'returns'=>0],0),600000,'Direct paid expense releases that share of request reservation');

echo "Financial regression tests passed (database-independent).\n";
