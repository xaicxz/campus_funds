<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function r2_due_days(int $org): int {
    $v=one('SELECT liquidation_due_days FROM organization_settings WHERE organization_id=?',[$org]);
    return max(1,min(180,(int)($v['liquidation_due_days']??14)));
}
function r2_finance_role(int $org): bool {
    foreach (['treasurer','adviser','president','auditor'] as $role) if (has_role($role,$org)) return true;
    return false;
}
function r2_can_see_case(array $case): bool {
    return r2_finance_role((int)$case['organization_id']) || (int)$case['requested_by']===(int)me()['id'];
}
function r2_request(int $id,int $org,bool $lock=false): array {
    $record=one('SELECT r.*,b.name budget_name FROM fund_requests r JOIN budgets b ON b.id=r.budget_id WHERE r.id=? AND r.organization_id=?'.($lock?' FOR UPDATE':''),[$id,$org]);
    if (!$record) abort(404,'Organization request not found.');
    return $record;
}
function r2_case(int $id,int $org,bool $lock=false): array {
    $r=one('SELECT l.*,r.organization_id,r.requested_by,r.amount approved_amount,r.reference_no,r.title,r.budget_id,r.commitment_released,r.closed_at FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id WHERE l.id=? AND r.organization_id=?'.($lock?' FOR UPDATE':''),[$id,$org]);
    if (!$r) abort(404,'Liquidation record not found.');
    return $r;
}
function r2_tx_total(int $org, string $type,?int $request=null): int {
    $sql='SELECT COALESCE(SUM(amount),0) total FROM financial_transactions WHERE organization_id=? AND status=\'posted\' AND transaction_type=?';
    $args=[$org,$type]; if ($request!==null){$sql.=' AND fund_request_id=?';$args[]=$request;}
    return cents_db(one($sql,$args)['total']);
}
function r2_request_totals(int $org,int $request): array {
    $tx=one("SELECT COALESCE(SUM(CASE WHEN transaction_type='advance_disbursement' THEN amount ELSE 0 END),0) advances,
        COALESCE(SUM(CASE WHEN transaction_type='advance_return' THEN amount ELSE 0 END),0) returns,
        COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END),0) direct
        FROM financial_transactions WHERE organization_id=? AND fund_request_id=? AND status='posted'",[$org,$request]);
    $case=one("SELECT l.status,s.item_total FROM liquidations l LEFT JOIN liquidation_submissions s ON s.id=l.final_submission_id WHERE l.fund_request_id=?",[$request]);
    $recognized=$case && in_array($case['status'],['verified','closed'],true) ? cents_db($case['item_total']??0):0;
    return ['advances'=>cents_db($tx['advances']),'returns'=>cents_db($tx['returns']),'direct'=>cents_db($tx['direct']),'recognized'=>$recognized];
}
function r2_budget_expenses(int $budgetId): int {
    $direct=one("SELECT COALESCE(SUM(t.amount),0) total FROM financial_transactions t JOIN budget_lines bl ON bl.id=t.budget_line_id WHERE bl.budget_id=? AND t.transaction_type='expense' AND t.status='posted'",[$budgetId]);
    $items=one("SELECT COALESCE(SUM(i.amount),0) total FROM liquidation_items i
      JOIN liquidation_submissions s ON s.id=i.submission_id JOIN liquidations l ON l.final_submission_id=s.id
      JOIN budget_lines bl ON bl.id=i.budget_line_id
      WHERE bl.budget_id=? AND l.status IN ('verified','closed')",[$budgetId]);
    return cents_db($direct['total'])+cents_db($items['total']);
}
function r2_budget_line_left(int $lineId): int {
    $line=one('SELECT allocated FROM budget_lines WHERE id=?',[$lineId]);
    if (!$line) abort(422,'Unknown budget category.');
    $direct=one("SELECT COALESCE(SUM(amount),0) total FROM financial_transactions WHERE status='posted' AND transaction_type='expense' AND budget_line_id=?",[$lineId]);
    $items=one("SELECT COALESCE(SUM(i.amount),0) total FROM liquidation_items i JOIN liquidation_submissions s ON s.id=i.submission_id JOIN liquidations l ON l.final_submission_id=s.id WHERE i.budget_line_id=? AND l.status IN ('verified','closed')",[$lineId]);
    return cents_db($line['allocated'])-cents_db($direct['total'])-cents_db($items['total']);
}
function r2_budget_reservations(int $budgetId): int {
    $requests=rows("SELECT id,organization_id,amount,commitment_released FROM fund_requests WHERE budget_id=? AND status='approved'",[$budgetId]);
    $reservation=0;
    foreach($requests as $r) {
        $tot=r2_request_totals((int)$r['organization_id'],(int)$r['id']);
        $reservation+=max(0,cents_db($r['amount'])-$tot['direct']-$tot['recognized']-cents_db($r['commitment_released']));
    }
    return $reservation;
}
function r2_account_cash(int $account): int {
    $acc=one('SELECT opening_balance,organization_id FROM financial_accounts WHERE id=?',[$account]);
    if (!$acc) abort(404,'Financial account missing.');
    $tot=one("SELECT
      COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END),0) income,
      COALESCE(SUM(CASE WHEN transaction_type='advance_return' THEN amount ELSE 0 END),0) returns,
      COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END),0) expense,
      COALESCE(SUM(CASE WHEN transaction_type='advance_disbursement' THEN amount ELSE 0 END),0) advances
      FROM financial_transactions WHERE account_id=? AND status='posted'",[$account]);
    return cents_db($acc['opening_balance'])+cents_db($tot['income'])+cents_db($tot['returns'])-cents_db($tot['expense'])-cents_db($tot['advances']);
}
function r2_financial_summary(int $org): array {
    $accounts=rows('SELECT id FROM financial_accounts WHERE organization_id=?',[$org]);
    $actual=0;foreach($accounts as $a) $actual+=r2_account_cash((int)$a['id']);
    $comm=0;$outstanding=0;
    $reqs=rows("SELECT id,amount,commitment_released FROM fund_requests WHERE organization_id=? AND status='approved'",[$org]);
    foreach($reqs as $r) {
        $t=r2_request_totals($org,(int)$r['id']);
        $comm+=max(0,cents_db($r['amount'])-$t['advances']-$t['direct']-cents_db($r['commitment_released']));
        $outstanding+=max(0,$t['advances']-$t['returns']-$t['recognized']);
    }
    $income=r2_tx_total($org,'income');$direct=r2_tx_total($org,'expense');
    $advances=r2_tx_total($org,'advance_disbursement');$returns=r2_tx_total($org,'advance_return');
    $spent=one("SELECT COALESCE(SUM(s.item_total),0) total FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id JOIN liquidation_submissions s ON s.id=l.final_submission_id WHERE r.organization_id=? AND l.status IN ('verified','closed')",[$org]);
    $recognized=$direct+cents_db($spent['total']);
    return ['actual'=>$actual,'available'=>$actual-$comm,'commitments'=>$comm,'income'=>$income,'expense'=>$recognized,
        'direct_expenses'=>$direct,'advances'=>$advances,'returns'=>$returns,'outstanding_advances'=>$outstanding];
}
function r2_notify(int $user,int $org,string $key,string $message,string $page='notifications'): void {
    execute_sql('INSERT IGNORE INTO notifications (user_id,organization_id,category,event_key,message,link_page) VALUES (?,?,?,?,?,?)',[$user,$org,'financial',substr($key,0,140),substr($message,0,255),$page]);
}
function r2_notify_roles(int $org,array $roles,string $event,string $message,string $page): void {
    if (!$roles) return;
    $placeholders=implode(',',array_fill(0,count($roles),'?'));
    $users=rows("SELECT DISTINCT r.user_id FROM organization_user_roles r JOIN users u ON u.id=r.user_id AND u.is_active=1 WHERE r.organization_id=? AND r.active=1 AND r.role IN ($placeholders)",array_merge([$org],$roles));
    foreach($users as $u) r2_notify((int)$u['user_id'],$org,$event.':'.$u['user_id'],$message,$page);
}
function r2_store_private(array $file): array {
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || (int)($file['size']??0)<=0 || (int)$file['size']>5*1024*1024) abort(422,'Every evidence file must be provided (maximum 5 MB).');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if (!isset($allowed[$mime])) abort(422,'Evidence must be a PDF, JPG or PNG file.');
    $base=dirname(__DIR__).'/storage/uploads';if (!is_dir($base)&&!mkdir($base,0700,true)) abort(500,'Storage directory unavailable.');
    $name=bin2hex(random_bytes(24)).'.'.$allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'],$base.'/'.$name)) abort(500,'Unable to save evidence file.');
    return [$name,substr(basename((string)($file['name']??'file')),0,255),$mime];
}
function r2_upload_array(string $field): array {
    $up=$_FILES[$field]??null;
    if (!$up || !is_array($up['name'])) return [];
    $files=[];foreach($up['name'] as $i=>$name) $files[]=['name'=>$name,'tmp_name'=>$up['tmp_name'][$i],'size'=>$up['size'][$i],'error'=>$up['error'][$i]];
    return $files;
}
function r2_attachment(int $id,int $org): never {
    $x=one("SELECT i.*,r.organization_id,r.requested_by FROM liquidation_items i JOIN liquidation_submissions s ON s.id=i.submission_id JOIN liquidations l ON l.id=s.liquidation_id JOIN fund_requests r ON r.id=l.fund_request_id WHERE i.id=? AND r.organization_id=?",[$id,$org]);
    if (!$x) abort(404,'Evidence not found.');
    if (!r2_finance_role($org) && (int)$x['requested_by']!==(int)me()['id']) abort(403,'Access denied.');
    $path=dirname(__DIR__).'/storage/uploads/'.$x['stored_name'];
    if (!is_file($path)) abort(404,'Document unavailable.');
    audit('download_evidence','liquidation_item',$id,$org,'Protected evidence accessed');
    header('Content-Type: '.$x['mime_type']);header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="liquidation-evidence-'.$id.'.'.pathinfo($x['stored_name'],PATHINFO_EXTENSION).'"');
    header('Cache-Control: private, no-store');header('Content-Length: '.filesize($path));readfile($path);exit;
}

function r2_handle_action(string $action): never {
    if ($_SERVER['REQUEST_METHOD']!=='POST') abort(405,'POST required.');
    check_csrf();login_required();
    if ($action==='set_liquidation_policy') {
        if(!has_role('system_admin',1))abort(403,'Administrator configuration only.');
        $org=(int)($_POST['target_org']??0);$days=(int)($_POST['due_days']??0);
        if($days<1||$days>180||!one('SELECT id FROM organizations WHERE id=? AND is_sg=0',[$org]))abort(422,'Choose an organization and a deadline between 1 and 180 days.');
        execute_sql('UPDATE organization_settings SET liquidation_due_days=? WHERE organization_id=?',[$days,$org]);
        audit('change_liquidation_policy','organization',$org,$org,'Due days: '.$days);
        redirect_to('users',null,'Liquidation due-day policy updated.');
    }
    if ($action==='notification_read') {
        execute_sql('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?',[(int)($_POST['id']??0),me()['id']]);
        redirect_to('notifications',null,'Notification marked as read.');
    }
    if ($action==='calendar_save') {
        $org=selected_org();require_role($org,['treasurer']);
        $title=safe_text((string)($_POST['title']??''),160);$date=must_date((string)($_POST['due_on']??''));
        $details=trim((string)($_POST['details']??'')); if (strlen($details)>255) abort(422,'Deadline details too long.');
        execute_sql('INSERT INTO financial_calendar_items (organization_id,title,due_on,item_type,created_by,details) VALUES (?,?,?,\'manual\',?,?)',[$org,$title,$date,me()['id'],$details]);
        $id=(int)db()->lastInsertId();audit('create_deadline','calendar',$id,$org,$title);
        redirect_to('calendar',$org,'Deadline saved.');
    }
    if ($action==='calendar_complete') {
        $org=selected_org();require_role($org,['treasurer']);$id=(int)($_POST['id']??0);
        execute_sql("UPDATE financial_calendar_items SET completed_at=NOW() WHERE id=? AND organization_id=? AND item_type='manual' AND completed_at IS NULL",[$id,$org]);
        audit('complete_deadline','calendar',$id,$org,'Manual deadline completed');redirect_to('calendar',$org,'Deadline marked complete.');
    }
    if ($action==='record_advance' || $action==='record_return') {
        $org=selected_org();require_role($org,['treasurer']);
        $id=(int)($_POST['request_id']??0);$type=$action==='record_advance'?'advance_disbursement':'advance_return';
        $amount=cents((string)($_POST['amount']??''));$account=(int)($_POST['account_id']??0);
        $date=must_date((string)($_POST['occurred_on']??''));$method=safe_text((string)($_POST['payment_method']??''),60);
        $key=safe_text((string)($_POST['event_key']??''),90);
        $proof=$_FILES['receipt']??null;
        if (!$proof) abort(422,'Proof of transfer/payment is required.');
        $saved=[];db()->beginTransaction();
        try {
            one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$org]);
            $r=r2_request($id,$org,true);
            if ($r['status']!=='approved' || $r['closed_at']!==null) abort(409,'Request is not open and approved.');
            if (!one('SELECT id FROM financial_accounts WHERE id=? AND organization_id=?',[$account,$org])) abort(422,'Account is not in this organization.');
            $t=r2_request_totals($org,$id);
            $pending=one("SELECT COALESCE(SUM(amount),0) total FROM financial_transactions WHERE fund_request_id=? AND status='pending' AND transaction_type=?",[$id,$type]);
            if ($type==='advance_disbursement') {
                if ($t['direct']>0) abort(409,'Cannot mix an advance with direct paid expenses for the same request.');
                $allowed=cents_db($r['amount'])-cents_db($r['commitment_released'])-$t['advances']-cents_db($pending['total']);
                if ($amount>$allowed) abort(422,'Advance exceeds the unpaid approved commitment.');
                $case=one('SELECT status FROM liquidations WHERE fund_request_id=?',[$id]);
                if ($case && in_array($case['status'],['submitted','under_review','verified','closed'],true)) abort(409,'Complete the existing liquidation before another advance.');
            } else {
                if ($t['advances']===0) abort(422,'This request has no posted advance to return.');
                if ($amount>$t['advances']-$t['returns']-$t['recognized']-cents_db($pending['total'])) abort(422,'Return exceeds remaining unaccounted cash.');
            }
            execute_sql('INSERT INTO financial_transactions (organization_id,account_id,fund_request_id,transaction_type,category,description,amount,occurred_on,payment_method,status,created_by,event_key) VALUES (?,?,?,?,?,?,?,?,?,\'pending\',?,?)',
                [$org,$account,$id,$type,$type==='advance_return'?'Advance return':'Fund advance',$r['reference_no'].' / '.$r['title'],peso_str($amount),$date,$method,me()['id'],$key]);
            $tx=(int)db()->lastInsertId();
            $saved=r2_store_private($proof);
            execute_sql('INSERT INTO transaction_attachments (transaction_id,stored_name,original_name,mime_type,uploaded_by) VALUES (?,?,?,?,?)',[$tx,$saved[0],$saved[1],$saved[2],me()['id']]);
            audit('record_'.$type,'transaction',$tx,$org,'Pending independent verification; event='.$key);
            db()->commit();
        } catch(Throwable $e){db()->rollBack();if($saved) @unlink(dirname(__DIR__).'/storage/uploads/'.$saved[0]);throw $e;}
        redirect_to('liquidations',$org,'Cash movement recorded; Adviser verification is required before posting.');
    }
    if ($action==='liquidation_submit') {
        $org=selected_org();$request=(int)($_POST['request_id']??0);
        $raw=trim((string)($_POST['items']??''));
        $lines=array_values(array_filter(preg_split('/\r\n|\n|\r/',$raw),fn($s)=>trim($s)!==''));
        $files=r2_upload_array('proofs');
        if (!$lines || count($files)!==count($lines) || count($lines)>40) abort(422,'Enter 1-40 item lines and upload exactly one evidence file per item, in matching order.');
        $items=[];$total=0;
        foreach($lines as $line) {
            $p=array_map('trim',explode('|',$line));
            if(count($p)!==5)abort(422,'Every item must be Date|Payee|Description|Budget line ID|Amount.');
            [$when,$payee,$description,$bl,$value]=$p;$valueC=cents($value);
            $items[]=['date'=>must_date($when),'payee'=>safe_text($payee,150),'description'=>safe_text($description,255),'line'=>(int)$bl,'amount'=>$valueC];$total+=$valueC;
        }
        $saved=[];db()->beginTransaction();
        try {
            one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$org]);
            $r=r2_request($request,$org,true);
            if ((int)$r['requested_by']!==(int)me()['id']) abort(403,'Only the request owner may submit the liquidation.');
            if ($r['status']!=='approved' || $r['closed_at']!==null) abort(409,'Only open approved requests can be liquidated.');
            $t=r2_request_totals($org,$request);
            if ($t['advances']<=0 || $t['direct']>0) abort(422,'This request needs a posted advance and no direct expense payments.');
            if ($total>$t['advances']-$t['returns'] || $total>cents_db($r['amount'])) abort(422,'Itemized spending exceeds the advance available or approval.');
            foreach($items as $item) {
                if(!one('SELECT bl.id FROM budget_lines bl JOIN budgets b ON b.id=bl.budget_id WHERE bl.id=? AND b.id=? AND b.organization_id=? AND b.status=\'approved\'',[$item['line'],$r['budget_id'],$org]))abort(422,'Item has a budget category outside the approved request budget.');
            }
            $case=one('SELECT * FROM liquidations WHERE fund_request_id=? FOR UPDATE',[$request]);
            if ($case && !in_array($case['status'],['pending','draft','returned_for_revision'],true)) abort(409,'Submission cannot be modified while it is being reviewed.');
            if (!$case) {execute_sql("INSERT INTO liquidations (fund_request_id,status,due_on) VALUES (?,'draft',DATE_ADD(CURDATE(),INTERVAL 14 DAY))",[$request]);$case=['id'=>(int)db()->lastInsertId()];}
            $next=one('SELECT COALESCE(MAX(version_number),0)+1 next FROM liquidation_submissions WHERE liquidation_id=?',[$case['id']]);
            execute_sql('INSERT INTO liquidation_submissions (liquidation_id,version_number,submitted_by,item_total) VALUES (?,?,?,?)',[$case['id'],$next['next'],me()['id'],peso_str($total)]);
            $sub=(int)db()->lastInsertId();
            foreach($items as $i=>$item) {
                $file=r2_store_private($files[$i]);$saved[]=$file[0];
                execute_sql('INSERT INTO liquidation_items (submission_id,occurred_on,payee,description,budget_line_id,amount,stored_name,original_name,mime_type) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$sub,$item['date'],$item['payee'],$item['description'],$item['line'],peso_str($item['amount']),$file[0],$file[1],$file[2]]);
            }
            execute_sql("UPDATE liquidations SET status='submitted',final_submission_id=NULL WHERE id=?",[$case['id']]);
            audit('submit_liquidation','liquidation',(int)$case['id'],$org,'Version '.$next['next'].'; item total '.peso_str($total));
            r2_notify_roles($org,['treasurer'],'liquidation-submitted:'.$sub,'Liquidation submitted for request '.$r['reference_no'],'liquidations');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();foreach($saved as $f)@unlink(dirname(__DIR__).'/storage/uploads/'.$f);throw $e;}
        redirect_to('liquidations',$org,'Evidence submitted to Treasurer for review.');
    }
    if ($action==='liquidation_decide') {
        $org=selected_org();$caseId=(int)($_POST['id']??0);$decision=(string)($_POST['decision']??'');$reason=trim((string)($_POST['reason']??''));
        if (!in_array($decision,['approved','returned','rejected','closed'],true)) abort(422,'Invalid liquidation decision.');
        if (in_array($decision,['returned','rejected'],true) && !$reason) abort(422,'A reason is required to return or reject liquidation.');
        db()->beginTransaction();
        try {
            one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$org]);
            $case=r2_case($caseId,$org,true);
            $stages=['submitted'=>'treasurer','under_review'=>'adviser','verified'=>'president'];
            $stage=$stages[$case['status']]??null;
            if (!$stage) abort(409,'The current liquidation state is not reviewable.');
            require_role($org,[$stage]);
            if ((int)$case['requested_by']===(int)me()['id']) abort(403,'Cannot review a request you originally submitted.');
            $sub=one('SELECT * FROM liquidation_submissions WHERE liquidation_id=? ORDER BY version_number DESC LIMIT 1 FOR UPDATE',[$caseId]);
            if(!$sub || (int)$sub['submitted_by']===(int)me()['id'])abort(403,'Cannot review your own liquidation submission.');
            if(one('SELECT id FROM liquidation_decisions WHERE submission_id=? AND reviewer_id=? LIMIT 1',[$sub['id'],me()['id']]))abort(403,'The same person cannot sign multiple review stages for one submission.');
            if ($stage==='president' && $decision!=='closed') abort(422,'Final review must close the verified case.');
            if ($stage!=='president' && $decision==='closed') abort(422,'The final stage must be completed by the President.');
            if ($decision==='approved' && $stage==='treasurer')$next='under_review';
            elseif ($decision==='approved' && $stage==='adviser') {
                $tot=r2_request_totals($org,(int)$case['fund_request_id']);
                if ($tot['advances']!==cents_db($sub['item_total'])+$tot['returns']) abort(422,'Liquidation is not balanced: posted advances must equal itemized spending plus posted returns.');
                if (one("SELECT id FROM financial_transactions WHERE fund_request_id=? AND status='pending' AND transaction_type IN ('advance_disbursement','advance_return') LIMIT 1",[$case['fund_request_id']]))abort(422,'Resolve pending cash movements first.');
                $items=rows('SELECT budget_line_id,COALESCE(SUM(amount),0) amount FROM liquidation_items WHERE submission_id=? GROUP BY budget_line_id',[$sub['id']]);
                foreach($items as $item) if(r2_budget_line_left((int)$item['budget_line_id'])<cents_db($item['amount']))abort(422,'Verified items would exceed the approved budget category.');
                $next='verified';
                execute_sql('UPDATE liquidations SET final_submission_id=? WHERE id=?',[$sub['id'],$caseId]);
            } elseif ($decision==='closed') {
                $tot=r2_request_totals($org,(int)$case['fund_request_id']);
                if($tot['advances']!==$tot['recognized']+$tot['returns']) abort(422,'Case must remain balanced before closure.');
                $r=r2_request((int)$case['fund_request_id'],$org,true);
                $remaining=max(0,cents_db($r['amount'])-$tot['advances']-$tot['direct']-cents_db($r['commitment_released']));
                execute_sql('UPDATE fund_requests SET commitment_released=commitment_released+?,closed_at=NOW() WHERE id=?',[peso_str($remaining),$r['id']]);
                $next='closed';
            } elseif ($decision==='returned')$next='returned_for_revision';
            else $next='rejected';
            $stored=$decision==='approved'?'approved':$decision;
            execute_sql('INSERT INTO liquidation_decisions (liquidation_id,submission_id,reviewer_id,stage,decision,reason) VALUES (?,?,?,?,?,?)',[$caseId,$sub['id'],me()['id'],$stage,$stored,$reason?:null]);
            execute_sql('UPDATE liquidations SET status=? WHERE id=?',[$next,$caseId]);
            audit('liquidation_'.$stored,'liquidation',$caseId,$org,json_encode(['before'=>$case['status'],'after'=>$next,'stage'=>$stage,'version'=>$sub['version_number'],'reviewer'=>me()['id'],'reason'=>$reason],JSON_UNESCAPED_UNICODE));
            $msg='Liquidation '.$case['reference_no'].' is now '.str_replace('_',' ',$next);
            r2_notify((int)$case['requested_by'],$org,'liquidation-decision:'.$caseId.':'.$sub['id'].':'.$stage,$msg,'liquidations');
            $nextRole=match($next){'under_review'=>['adviser'],'verified'=>['president'],default=>[]};
            if($nextRole)r2_notify_roles($org,$nextRole,'liq-next-'.$caseId.'-'.$next,$msg,'liquidations');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('liquidations',$org,'Review decision saved with its approval history.');
    }
    abort(404,'Unknown action.');
}
