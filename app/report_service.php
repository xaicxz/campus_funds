<?php
declare(strict_types=1);
require_once __DIR__.'/release2.php';

function r2_filter_dates(): array {
    $from=(string)($_GET['from']??date('Y-01-01'));
    $to=(string)($_GET['to']??date('Y-m-d'));
    if (!valid_date($from) || !valid_date($to) || $from>$to) abort(422,'Choose a valid report date range.');
    return [$from,$to];
}
function r2_report_types(bool $sg): array {
    return $sg ? ['comparison'=>'SG organization comparison'] : [
        'income'=>'Posted income','expenses'=>'Recognized expenses','cash'=>'Cash movement by account',
        'budgets'=>'Approved budget utilization','requests'=>'Fund request tracker',
        'liquidations'=>'Liquidation and overdue tracker','audit'=>'Audit event history'];
}
function r2_report_query(int $org,string $type,string $from,string $to,bool $sg=false): array {
    if (!array_key_exists($type,r2_report_types($sg))) abort(422,'This report is not available for your role.');
    $headers=[];$records=[];
    if ($sg) {
        $headers=['Organization','Actual cash PHP','Available cash PHP','Posted income PHP','Recognized expense PHP','Outstanding advances PHP','Overdue liquidations'];
        foreach(rows('SELECT id,name FROM organizations WHERE is_sg=0 AND active=1 ORDER BY id') as $o){
            $s=r2_financial_summary((int)$o['id']);
            $late=one("SELECT COUNT(*) n FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id WHERE r.organization_id=? AND l.due_on<CURDATE() AND l.status NOT IN ('closed','rejected')",[$o['id']]);
            $records[]=[$o['name'],peso_str($s['actual']),peso_str($s['available']),peso_str($s['income']),peso_str($s['expense']),peso_str($s['outstanding_advances']),(string)$late['n']];
        }
        return ['title'=>'Student Government organization comparison (all-time / as-of today)','headers'=>$headers,'records'=>$records,'basis'=>'All-time posted/verified balances (date filter does not apply to this snapshot)'];
    }
    if ($type==='income') {
        $headers=['Posted date','Account','Source','Description','Amount PHP'];
        $d=rows("SELECT t.occurred_on,a.name account_name,t.category,t.description,t.amount FROM financial_transactions t JOIN financial_accounts a ON a.id=t.account_id WHERE t.organization_id=? AND t.status='posted' AND t.transaction_type='income' AND t.occurred_on BETWEEN ? AND ? ORDER BY t.occurred_on,t.id",[$org,$from,$to]);
        foreach($d as $x)$records[]=[$x['occurred_on'],$x['account_name'],$x['category'],$x['description'],$x['amount']];
    } elseif($type==='expenses') {
        $headers=['Date','Basis','Category','Description','Recognized amount PHP'];
        $d=rows("SELECT occurred_on,category,description,amount FROM financial_transactions WHERE organization_id=? AND transaction_type='expense' AND status='posted' AND occurred_on BETWEEN ? AND ? ORDER BY occurred_on,id",[$org,$from,$to]);
        foreach($d as $x)$records[]=[$x['occurred_on'],'Paid direct expense',$x['category'],$x['description'],$x['amount']];
        $l=rows("SELECT i.occurred_on,bl.category,i.description,i.amount FROM liquidation_items i JOIN budget_lines bl ON bl.id=i.budget_line_id JOIN liquidation_submissions s ON s.id=i.submission_id JOIN liquidations l ON l.final_submission_id=s.id JOIN fund_requests r ON r.id=l.fund_request_id WHERE r.organization_id=? AND l.status IN ('verified','closed') AND i.occurred_on BETWEEN ? AND ? ORDER BY i.occurred_on,i.id",[$org,$from,$to]);
        foreach($l as $x)$records[]=[$x['occurred_on'],'Verified liquidation',$x['category'],$x['description'],$x['amount']];
        usort($records,fn($a,$b)=>strcmp($a[0],$b[0]));
    } elseif($type==='cash') {
        $headers=['Account','Opening at start PHP','Income PHP','Advance returns PHP','Direct paid expense PHP','Advances released PHP','Closing PHP'];
        foreach(rows('SELECT * FROM financial_accounts WHERE organization_id=? ORDER BY id',[$org]) as $a) {
            $before=one("SELECT COALESCE(SUM(CASE WHEN transaction_type IN ('income','advance_return') THEN amount ELSE -amount END),0) total FROM financial_transactions WHERE account_id=? AND status='posted' AND occurred_on<?",[$a['id'],$from]);
            $base=cents_db($a['opening_balance'])+cents_db($before['total']);
            $parts=one("SELECT
                COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END),0) inc,
                COALESCE(SUM(CASE WHEN transaction_type='advance_return' THEN amount ELSE 0 END),0) ret,
                COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END),0) exp,
                COALESCE(SUM(CASE WHEN transaction_type='advance_disbursement' THEN amount ELSE 0 END),0) adv
                FROM financial_transactions WHERE account_id=? AND status='posted' AND occurred_on BETWEEN ? AND ?",[$a['id'],$from,$to]);
            $end=$base+cents_db($parts['inc'])+cents_db($parts['ret'])-cents_db($parts['exp'])-cents_db($parts['adv']);
            $records[]=[$a['name'],peso_str($base),$parts['inc'],$parts['ret'],$parts['exp'],$parts['adv'],peso_str($end)];
        }
    } elseif($type==='budgets') {
        $headers=['Budget','Approved PHP','Recognized PHP','Reservation PHP','Uncommitted PHP','Utilization %'];
        foreach(rows("SELECT b.id,b.name,COALESCE(SUM(bl.allocated),0) allocated FROM budgets b LEFT JOIN budget_lines bl ON bl.budget_id=b.id WHERE b.organization_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.id",[$org]) as $b) {
            $amount=cents_db($b['allocated']);$spent=r2_budget_expenses((int)$b['id']);$reserved=r2_budget_reservations((int)$b['id']);
            $records[]=[$b['name'],$b['allocated'],peso_str($spent),peso_str($reserved),peso_str($amount-$spent-$reserved),$amount>0?number_format($spent/$amount*100,1):'N/A'];
        }
    } elseif($type==='requests') {
        $headers=['Reference','Title','Requester','Status','Requested PHP','Approved PHP','Released PHP','Unpaid commitment PHP'];
        $d=rows("SELECT r.*,u.full_name owner FROM fund_requests r JOIN users u ON u.id=r.requested_by WHERE r.organization_id=? AND DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.id DESC",[$org,$from,$to]);
        foreach($d as $r) {$t=r2_request_totals($org,(int)$r['id']);$com=$r['status']==='approved'?max(0,cents_db($r['amount'])-$t['advances']-$t['direct']-cents_db($r['commitment_released'])):0;
            $records[]=[$r['reference_no'],$r['title'],$r['owner'],$r['status'],$r['amount'],$r['status']==='approved'?$r['amount']:'0.00',peso_str($t['advances']),peso_str($com)];}
    } elseif($type==='liquidations') {
        $headers=['Request reference','Activity','Status','Due on','Overdue','Advance PHP','Verified spent PHP','Returned PHP','Unresolved PHP'];
        $d=rows("SELECT l.*,r.organization_id,r.reference_no,r.title FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id WHERE r.organization_id=? AND DATE(l.created_at) BETWEEN ? AND ? ORDER BY l.id DESC",[$org,$from,$to]);
        foreach($d as $x) {$t=r2_request_totals($org,(int)$x['fund_request_id']);$records[]=[$x['reference_no'],$x['title'],$x['status'],$x['due_on'],($x['due_on'] && $x['due_on']<date('Y-m-d') && !in_array($x['status'],['closed','rejected'],true))?'YES':'NO',peso_str($t['advances']),peso_str($t['recognized']),peso_str($t['returns']),peso_str($t['advances']-$t['recognized']-$t['returns'])];}
    } elseif($type==='audit') {
        $headers=['Timestamp','Actor','Action','Record','Details'];
        $d=rows("SELECT l.*,u.full_name actor FROM audit_logs l LEFT JOIN users u ON u.id=l.actor_id WHERE l.organization_id=? AND DATE(l.created_at) BETWEEN ? AND ? ORDER BY l.id DESC LIMIT 2000",[$org,$from,$to]);
        foreach($d as $x)$records[]=[$x['created_at'],$x['actor']??'System',$x['action'],$x['entity_type'].' #'.$x['entity_id'],$x['details']??''];
    }
    $basis=($type==='budgets')
        ? 'Current approved budget totals and current reservations. Date range is not applied to this snapshot.'
        : 'Posted cash events and verified liquidation items only; request/reservation figures shown separately. Status figures reflect the current state.';
    return ['title'=>r2_report_types(false)[$type], 'headers'=>$headers,'records'=>$records,'basis'=>$basis];
}
function r2_escaped_csv_value(mixed $text): string {
    $s=(string)$text;
    return preg_match('/^[\s]*[=+\-@]/u',$s)?"'".$s:$s;
}
function r2_export_report(): never {
    login_required();
    $sg=has_role('sg_finance',1) && (($_GET['scope']??'')==='sg');
    if ($sg){$org=0;} else {$org=selected_org();require_role($org,['treasurer','president','adviser','auditor']);}
    [$from,$to]=r2_filter_dates();$type=(string)($_GET['type']??($sg?'comparison':'income'));
    $data=r2_report_query($org,$type,$from,$to,$sg);
    $format=(string)($_GET['format']??'csv');
    if(!in_array($format,['csv','pdf','xlsx'],true))abort(422,'Invalid report format.');
    $meta=['Organization'=>$sg?'SG consolidated (4 organizations)':one('SELECT name FROM organizations WHERE id=?',[$org])['name'],
        'Period'=>$from.' to '.$to, 'Data basis'=>$data['basis'], 'Generated at'=>date('Y-m-d H:i:s'). ' Asia/Manila',
        'Generated by'=>me()['name']];
    $filename='lsl-financial-portal-'.preg_replace('/[^a-z0-9_]/','_',$type).'-'.date('Ymd-His').'.'.$format;
    $autoload=dirname(__DIR__).'/vendor/autoload.php';if(is_file($autoload))require_once $autoload;
    if($format==='pdf' && !class_exists('Dompdf\\Dompdf'))abort(503,'PDF export needs Composer packages. Run composer install from the project directory.');
    if($format==='xlsx' && !class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet'))abort(503,'Excel export needs Composer packages. Run composer install from the project directory.');
    // Audit before headers (and before streaming); never store confidential reports under public/.
    audit('export_'.$format,'report',0,$org?:null,'Report '.$type.'; '.$from.' to '.$to.'; records '.count($data['records']));
    header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    if ($format==='csv') {
        header('Content-Type: text/csv; charset=utf-8');
        $out=fopen('php://output','w');fputcsv($out,[$data['title']]);foreach($meta as $key=>$v)fputcsv($out,[$key,r2_escaped_csv_value($v)]);fputcsv($out,[]);
        fputcsv($out,$data['headers']);foreach($data['records'] as $row)fputcsv($out,array_map('r2_escaped_csv_value',$row));fclose($out);exit;
    }
    if($format==='pdf') {
        $html='<!doctype html><html><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#18304d;font-size:9px}h1{font-size:18px}table{width:100%;border-collapse:collapse}th{background:#193451;color:white}th,td{border:1px solid #ccd6e0;padding:5px;text-align:left;word-break:break-word}tr:nth-child(even){background:#f5f8fc}</style><h1>'.e($data['title']).'</h1>';
        foreach($meta as $key=>$v)$html.='<div><b>'.e($key).':</b> '.e($v).'</div>';
        $html.='<br><table><thead><tr>';foreach($data['headers'] as $h)$html.='<th>'.e($h).'</th>';$html.='</tr></thead><tbody>';
        foreach($data['records'] as $record){$html.='<tr>';foreach($record as $value)$html.='<td>'.e($value).'</td>';$html.='</tr>';} $html.='</tbody></table></html>';
        $pdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false,'isPhpEnabled'=>false]);$pdf->loadHtml($html);$pdf->setPaper('A4','landscape');$pdf->render();
        header('Content-Type: application/pdf');echo $pdf->output();exit;
    }
    $spreadsheet=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$spreadsheet->getActiveSheet();$sheet->setTitle('Financial Report');
    $row=1;$sheet->setCellValue('A'.$row++,$data['title']);foreach($meta as $key=>$value){$sheet->setCellValue('A'.$row,$key);$sheet->setCellValueExplicit('B'.$row,$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$row++;}
    $row++;foreach($data['headers'] as $i=>$h)$sheet->setCellValueExplicit([$i+1,$row],$h,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $row++;foreach($data['records'] as $record){foreach($record as $i=>$value)$sheet->setCellValueExplicit([$i+1,$row],(string)$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$row++;}
    $sheet->getStyle('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($data['headers'])).'1')->getFont()->setBold(true);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');$spreadsheet->disconnectWorksheets();exit;
}
