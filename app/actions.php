<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function store_upload(int $txId,int $org,array $file): string {
    if (($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) abort(422,'A receipt or invoice is required for expenses.');
    if ($file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024 || $file['size']<=0) abort(422,'Upload a valid file no larger than 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if (!isset($ext[$mime])) abort(422,'Only PDF, JPG, or PNG receipts are permitted.');
    $storage=dirname(__DIR__).'/storage/uploads';
    if (!is_dir($storage) && !mkdir($storage,0700,true)) abort(500,'Cannot initialize private upload directory.');
    $name=bin2hex(random_bytes(24)).'.'.$ext[$mime];
    if (!move_uploaded_file($file['tmp_name'],$storage.'/'.$name)) abort(500,'Cannot store uploaded document.');
    try {
        execute_sql('INSERT INTO transaction_attachments (transaction_id,stored_name,original_name,mime_type,uploaded_by) VALUES (?,?,?,?,?)',
        [$txId,$name,substr(basename((string)$file['name']),0,255),$mime,me()['id']]);
    } catch (Throwable $e) { @unlink($storage.'/'.$name); throw $e; }
    audit('upload','transaction',$txId,$org,'Receipt added');
    return $name;
}
function handle_action(string $action): never {
    if ($_SERVER['REQUEST_METHOD']!=='POST') abort(405,'POST required.');
    check_csrf();
    if ($action==='login') {
        $email=strtolower(trim((string)($_POST['email']??'')));
        $user=one('SELECT * FROM users WHERE email=? AND is_active=1',[$email]);
        if (!$user || !password_verify((string)($_POST['password']??''),$user['password_hash'])) abort(401,'Invalid email or password.');
        session_regenerate_id(true);
        $_SESSION['user']=['id'=>(int)$user['id'],'name'=>$user['full_name'],'email'=>$user['email']];
        audit('login','user',(int)$user['id'],null,'Authenticated');
        redirect_to('dashboard');
    }
    login_required();
    if ($action==='logout') {
        $_SESSION=[]; session_regenerate_id(true); session_destroy();
        header('Location: index.php?page=login'); exit;
    }
    if ($action==='change_password') {
        $current=(string)($_POST['current_password']??'');
        $new=(string)($_POST['new_password']??'');
        $confirm=(string)($_POST['confirm_password']??'');
        if (strlen($new)<12 || strlen($new)>255) abort(422,'New password must contain 12 to 255 characters.');
        if (!hash_equals($new,$confirm)) abort(422,'New passwords do not match.');
        $record=one('SELECT password_hash FROM users WHERE id=? AND is_active=1',[me()['id']]);
        if (!$record || !password_verify($current,$record['password_hash'])) abort(403,'Current password is incorrect.');
        if (password_verify($new,$record['password_hash'])) abort(422,'Choose a different new password.');
        execute_sql('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),me()['id']]);
        session_regenerate_id(true);
        audit('change_password','user',(int)me()['id'],null,'Account password changed');
        redirect_to('account',null,'Your password has been updated.');
    }
    if ($action==='create_user') {
        if (!has_role('system_admin',1)) abort(403,'Only the system administrator provisions users.');
        $name=safe_text((string)($_POST['name']??''),150);
        $email=strtolower(trim((string)($_POST['email']??'')));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) abort(422,'Enter a valid email address.');
        $password=(string)($_POST['password']??'');
        if (strlen($password)<12) abort(422,'Passwords must contain at least 12 characters.');
        if (!hash_equals($password,(string)($_POST['confirm_password']??''))) abort(422,'Password confirmation does not match.');
        $org=(int)($_POST['role_org']??0); $role=(string)($_POST['role']??'');
        $allowed=['system_admin','sg_finance','president','treasurer','adviser','authorized_financial_user','officer','member','auditor'];
        if (!in_array($role,$allowed,true)) abort(422,'Invalid role.');
        $sg=in_array($role,['system_admin','sg_finance'],true);
        $target=one('SELECT is_sg FROM organizations WHERE id=? AND active=1',[$org]);
        if (!$target || ($sg && ((int)$target['is_sg']!==1 || $org!==1)) || (!$sg && (int)$target['is_sg']!==0))
            abort(422,'Choose an active participating organization for this role.');
        db()->beginTransaction();
        try {
            $existing=one('SELECT id FROM users WHERE email=?',[$email]);
            if ($existing) {
                $id=(int)$existing['id'];
                // Do not change another account's password while assigning a role.
            } else {
                execute_sql('INSERT INTO users (full_name,email,password_hash) VALUES (?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                $id=(int)db()->lastInsertId();
            }
            execute_sql('INSERT INTO organization_user_roles (user_id,organization_id,role,active) VALUES (?,?,?,1)
                ON DUPLICATE KEY UPDATE active=1',[$id,$org,$role]);
            audit('assign_role','user',$id,$org,$role); db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('users',null,'User created or role assigned.');
    }
    if ($action==='set_sg_budget_policy') {
        if (!has_role('system_admin',1)) abort(403,'Only the system administrator can configure approval policy.');
        $org=(int)($_POST['target_org']??0);
        if (!one('SELECT id FROM organizations WHERE id=? AND is_sg=0',[$org])) abort(422,'Invalid organization.');
        $enabled=(int)!empty($_POST['required']);
        execute_sql('INSERT INTO organization_settings (organization_id,require_sg_budget_approval) VALUES (?,?)
          ON DUPLICATE KEY UPDATE require_sg_budget_approval=VALUES(require_sg_budget_approval)',[$org,$enabled]);
        audit('update_budget_policy','organization',$org,$org,'SG approval required: '.$enabled);
        redirect_to('users',null,'Budget approval policy updated.');
    }
    if ($action==='create_budget') {
        $org=selected_org(); require_role($org,['treasurer']);
        $name=safe_text((string)($_POST['name']??''));
        $type=(string)($_POST['type']??'');
        if (!in_array($type,['annual','event','project'],true)) abort(422,'Invalid budget type.');
        $parts=preg_split('/\r\n|\n|\r/',trim((string)($_POST['lines']??'')));
        $lines=[];
        foreach($parts as $s) {
            if (trim($s)==='') continue;
            $pair=explode('|',$s,2);
            if (count($pair)!==2) abort(422,'Use one category per line: Category|Amount.');
            $category=safe_text($pair[0],100); $amount=cents(trim($pair[1]));
            if (isset($lines[strtolower($category)])) abort(422,'Duplicate budget category.');
            $lines[strtolower($category)]=[$category,peso_str($amount)];
        }
        if (!$lines) abort(422,'Add at least one budget category.');
        db()->beginTransaction();
        try {
            execute_sql('INSERT INTO budgets (organization_id,academic_year_id,name,budget_type,created_by) VALUES (?,?,?,?,?)',
                [$org,active_year()['id'],$name,$type,me()['id']]);
            $id=(int)db()->lastInsertId();
            foreach($lines as [$category,$amount]) execute_sql('INSERT INTO budget_lines (budget_id,category,allocated) VALUES (?,?,?)',[$id,$category,$amount]);
            audit('create_budget','budget',$id,$org,$name);
            r2_notify_roles($org,['president'],'budget-created:'.$id,'New budget proposal '.$name.' awaits President review','budgets');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('budgets',$org,'Budget submitted for president review.');
    }
    if ($action==='budget_decision') {
        $id=(int)($_POST['id']??0);$decision=(string)($_POST['decision']??'');
        if (!in_array($decision,['approved','rejected'],true)) abort(422,'Invalid decision.');
        $reason=trim((string)($_POST['reason']??''));
        if ($decision==='rejected' && $reason==='') abort(422,'A rejection reason is mandatory.');
        db()->beginTransaction();
        try {
            $b=one('SELECT * FROM budgets WHERE id=? FOR UPDATE',[$id]);
            if (!$b) abort(404,'Budget not found.');
            $org=(int)$b['organization_id'];
            $stages=['pending_president'=>'president','pending_adviser'=>'adviser','pending_sg'=>'sg_finance'];
            if (!isset($stages[$b['status']])) abort(409,'This budget cannot be reviewed again.');
            $stage=$stages[$b['status']];
            if ($stage==='sg_finance') { if (!has_role('sg_finance',1)) abort(403,'SG review permission required.'); }
            else require_role($org,[$stage]);
            if ((int)$b['created_by']===(int)me()['id']) abort(403,'You cannot review your own budget.');
            if (one('SELECT id FROM budget_approvals WHERE budget_id=? AND actor_id=? LIMIT 1',[$id,me()['id']]))
                abort(403,'A different reviewer must handle each budget approval stage.');
            $next=$decision==='rejected'?'rejected':match($stage){
                'president'=>'pending_adviser',
                'adviser'=>(int)(one('SELECT require_sg_budget_approval FROM organization_settings WHERE organization_id=?',[$org])['require_sg_budget_approval']??0) ? 'pending_sg' : 'approved',
                default=>'approved'};
            execute_sql('INSERT INTO budget_approvals (budget_id,actor_id,stage,decision,reason) VALUES (?,?,?,?,?)',[$id,me()['id'],$stage,$decision,$reason?:null]);
            execute_sql('UPDATE budgets SET status=? WHERE id=?',[$next,$id]);
            audit('budget_'.$decision,'budget',$id,$org,$stage.($reason?': '.$reason:''));
            r2_notify((int)$b['created_by'],$org,'budget-decision-'.$id.'-'.$stage,'Budget '.$b['name'].' stage '.$stage.': '.$decision,'budgets');
            $nextBudgetRole=['pending_adviser'=>'adviser','pending_sg'=>'sg_finance'][$next]??null;
            if ($nextBudgetRole)r2_notify_roles($next==='pending_sg'?1:$org,[$nextBudgetRole],'budget-stage-'.$id.'-'.$next,'Budget '.$b['name'].' awaits '.$nextBudgetRole.' review','budgets');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('budgets',count(my_roles($org))?$org:null,'Budget decision recorded.');
    }
    if ($action==='create_request') {
        $org=selected_org(); require_role($org,['officer']);
        $budget=(int)($_POST['budget_id']??0);
        $b=one("SELECT id FROM budgets WHERE id=? AND organization_id=? AND status='approved'",[$budget,$org]);
        if (!$b) abort(422,'Select an approved budget in your organization.');
        $title=safe_text((string)($_POST['title']??''));
        $purpose=safe_text((string)($_POST['purpose']??''),2000);
        $date=must_date((string)($_POST['activity_date']??''));
        $amount=peso_str(cents((string)($_POST['amount']??'')));
        db()->beginTransaction();
        try {
            execute_sql('INSERT INTO fund_requests (organization_id,budget_id,reference_no,title,purpose,activity_date,amount,requested_by) VALUES (?,?,?,?,?,?,?,?)',
                [$org,$budget,request_ref(),$title,$purpose,$date,$amount,me()['id']]);
            $id=(int)db()->lastInsertId(); audit('submit_request','fund_request',$id,$org,$title);
            r2_notify_roles($org,['treasurer'],'request-created:'.$id,'New fund request '.$title.' awaits Treasurer review.','requests');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('requests',$org,'Fund request sent for Treasurer review.');
    }
    if ($action==='request_decision') {
        $org=selected_org();$id=(int)($_POST['id']??0);
        $decision=(string)($_POST['decision']??'');$reason=trim((string)($_POST['reason']??''));
        if (!in_array($decision,['approved','rejected'],true)) abort(422,'Invalid decision.');
        if ($decision==='rejected' && $reason==='') abort(422,'A rejection reason is required.');
        db()->beginTransaction();
        try {
            // Serializes approvals and postings that affect an organization's available funds.
            one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$org]);
            $r=one('SELECT * FROM fund_requests WHERE id=? AND organization_id=? FOR UPDATE',[$id,$org]);
            if (!$r) abort(404,'Request not found.');
            $stages=['pending_treasurer'=>'treasurer','pending_adviser'=>'adviser','pending_president'=>'president'];
            if (!isset($stages[$r['status']])) abort(409,'Request is not awaiting approval.');
            $stage=$stages[$r['status']]; require_role($org,[$stage]);
            if ((int)$r['requested_by']===(int)me()['id']) abort(403,'You cannot review your own request.');
            if (one('SELECT id FROM fund_request_approvals WHERE fund_request_id=? AND actor_id=? LIMIT 1',[$id,me()['id']]))
                abort(403,'A different reviewer must handle each fund-request approval stage.');
            if ($decision==='approved' && $stage==='president') {
                if (cash_summary($org)['available']<cents_db($r['amount'])) abort(422,'Insufficient uncommitted cash for final approval.');
                if (budget_left((int)$r['budget_id'])-r2_budget_reservations((int)$r['budget_id'])<cents_db($r['amount'])) abort(422,'Insufficient uncommitted budget allocation.');
            }
            $next=$decision==='rejected'?'rejected':match($stage){'treasurer'=>'pending_adviser','adviser'=>'pending_president',default=>'approved'};
            execute_sql('INSERT INTO fund_request_approvals (fund_request_id,actor_id,stage,decision,reason) VALUES (?,?,?,?,?)',[$id,me()['id'],$stage,$decision,$reason?:null]);
            execute_sql('UPDATE fund_requests SET status=? WHERE id=?',[$next,$id]);
            audit('request_'.$decision,'fund_request',$id,$org,$stage.($reason?': '.$reason:''));
            r2_notify((int)$r['requested_by'],$org,'request-decision-'.$id.'-'.$stage,'Fund request '.$r['reference_no'].' stage '.$stage.': '.$decision,'requests');
            $nextStage=['pending_adviser'=>'adviser','pending_president'=>'president'][$next]??null;
            if($nextStage)r2_notify_roles($org,[$nextStage],'request-stage-'.$id.'-'.$next,'Fund request '.$r['reference_no'].' awaits '.$nextStage.' review','requests');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('requests',$org,'Request decision recorded.');
    }
    if ($action==='close_direct_request') {
        // A completed/cancelled direct-pay request must not reserve its unused approval forever.
        $org=selected_org();require_role($org,['president']);
        $id=(int)($_POST['id']??0);
        $reason=safe_text((string)($_POST['reason']??''),500);
        db()->beginTransaction();
        try {
            one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$org]);
            $r=r2_request($id,$org,true);
            if ($r['status']!=='approved' || $r['closed_at']!==null) abort(409,'Request must be approved and open.');
            if ((int)$r['requested_by']===(int)me()['id']) abort(403,'A request owner cannot close their own request.');
            if (one('SELECT id FROM liquidations WHERE fund_request_id=? LIMIT 1',[$id]))
                abort(409,'Advance requests must close through the verified liquidation process.');
            if (one("SELECT id FROM financial_transactions WHERE fund_request_id=? AND status='pending' LIMIT 1",[$id]))
                abort(409,'Pending financial entries must be resolved before closing this request.');
            $tot=r2_request_totals($org,$id);
            if ($tot['advances']>0 || $tot['returns']>0 || $tot['recognized']>0)
                abort(409,'This request contains advances and requires liquidation.');
            $remaining=cents_db($r['amount'])-$tot['direct']-cents_db($r['commitment_released']);
            if ($remaining<0) abort(409,'Request has inconsistent released commitments.');
            execute_sql('UPDATE fund_requests SET commitment_released=commitment_released+?,closed_at=NOW() WHERE id=? AND organization_id=?',
                [peso_str($remaining),$id,$org]);
            audit('close_direct_request','fund_request',$id,$org,json_encode([
                'reason'=>$reason,'posted_direct'=>peso_str($tot['direct']),
                'released_unpaid'=>peso_str($remaining),'closed_by'=>me()['id']
            ],JSON_UNESCAPED_UNICODE));
            r2_notify((int)$r['requested_by'],$org,'direct-request-closed:'.$id,
                'Direct-pay request '.$r['reference_no'].' is closed: '.$reason,'requests');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('requests',$org,'Request closed and unused approval released.');
    }
    if ($action==='create_tx') {
        $org=selected_org();require_role($org,['treasurer']);
        $type=(string)($_POST['type']??'');
        if (!in_array($type,['income','expense'],true)) abort(422,'Invalid transaction type.');
        $account=(int)($_POST['account_id']??0);
        if (!one('SELECT id FROM financial_accounts WHERE id=? AND organization_id=?',[$account,$org])) abort(422,'Invalid financial account.');
        $category=safe_text((string)($_POST['category']??''),100);
        $description=safe_text((string)($_POST['description']??''),255);
        $amount=peso_str(cents((string)($_POST['amount']??'')));
        $date=must_date((string)($_POST['occurred_on']??''));
        $method=safe_text((string)($_POST['payment_method']??''),60);
        $line=null;$request=null;
        if ($type==='expense') {
            $line=(int)($_POST['budget_line_id']??0);
            $b=one("SELECT bl.*,b.status,b.organization_id FROM budget_lines bl JOIN budgets b ON b.id=bl.budget_id
                  WHERE bl.id=? AND b.organization_id=? AND b.status='approved'",[$line,$org]);
            if (!$b) abort(422,'Expenses require a valid approved budget category.');
            $category=$b['category'];
            $raw=(int)($_POST['fund_request_id']??0);
            if ($raw) {
                $r=one("SELECT r.id FROM fund_requests r WHERE r.id=? AND r.organization_id=?
                      AND r.budget_id=? AND r.status='approved' AND r.closed_at IS NULL",[$raw,$org,$b['budget_id']]);
                if (!$r) abort(422,'The linked request must be approved and belong to this budget.');
                $request=$raw;
            }
            if (!isset($_FILES['receipt'])) abort(422,'Please attach a receipt or invoice.');
        }
        $uploaded=null;
        db()->beginTransaction();
        try {
            execute_sql('INSERT INTO financial_transactions
             (organization_id,account_id,budget_line_id,fund_request_id,transaction_type,category,description,amount,occurred_on,payment_method,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$org,$account,$line,$request,$type,$category,$description,$amount,$date,$method,me()['id']]);
            $id=(int)db()->lastInsertId();
            if ($type==='expense') $uploaded=store_upload($id,$org,$_FILES['receipt']);
            audit('create_transaction','transaction',$id,$org,$type.' '.$amount);
            r2_notify_roles($org,['adviser'],'transaction-new:'.$id,'New '. $type .' requires independent verification','transactions');
            db()->commit();
        } catch(Throwable $e){db()->rollBack();if($uploaded!==null)@unlink(dirname(__DIR__).'/storage/uploads/'.$uploaded);throw $e;}
        redirect_to('transactions',$org,'Record saved as pending verification (no balance changed).');
    }
    if ($action==='post_tx') {
        $org=selected_org();require_role($org,['adviser']);$id=(int)($_POST['id']??0);
        db()->beginTransaction();
        try {
            one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$org]);
            $t=one('SELECT * FROM financial_transactions WHERE id=? AND organization_id=? FOR UPDATE',[$id,$org]);
            if (!$t) abort(404,'Transaction not found.');
            if ($t['status']!=='pending') abort(409,'This transaction has already been posted.');
            if ((int)$t['created_by']===(int)me()['id']) abort(403,'You cannot verify your own financial entry.');
            if ($t['transaction_type']==='expense') {
                if (!one('SELECT id FROM transaction_attachments WHERE transaction_id=? LIMIT 1',[$id])) abort(422,'Receipt or invoice required.');
                $bl=one("SELECT bl.allocated,bl.budget_id,b.status FROM budget_lines bl
                      JOIN budgets b ON b.id=bl.budget_id WHERE bl.id=? AND b.organization_id=?",[$t['budget_line_id'],$org]);
                if (!$bl || $bl['status']!=='approved') abort(422,'Budget is not approved.');
                if (r2_budget_line_left((int)$t['budget_line_id'])<cents_db($t['amount'])) abort(422,'Budget category would be overspent.');
                if(!$t['fund_request_id'] && budget_left((int)$bl['budget_id'])-r2_budget_reservations((int)$bl['budget_id'])<cents_db($t['amount']))abort(422,'Uncommitted budget would be overspent.');
                // An organization may have cash in another account; never overdraw the selected one.
                if (r2_account_cash((int)$t['account_id'])<cents_db($t['amount'])) abort(422,'Insufficient cash in the selected financial account.');
                $cash=cash_summary($org);
                if ($t['fund_request_id']) {
                    $r=one("SELECT * FROM fund_requests WHERE id=? AND organization_id=? AND status='approved' AND closed_at IS NULL",[$t['fund_request_id'],$org]);
                    if (!$r || (int)$r['budget_id']!==(int)$bl['budget_id'] || $r['closed_at']!==null) abort(422,'Fund request is no longer open and approved.');
                    $tot=r2_request_totals($org,(int)$r['id']);
                    if ($tot['advances']>0) abort(409,'Direct expenses cannot be mixed with cash advances for one request.');
                    if (cents_db($r['amount'])-$tot['direct']-cents_db($r['commitment_released'])<cents_db($t['amount'])) abort(422,'Expense exceeds remaining approved request.');
                    if ($cash['actual']<cents_db($t['amount'])) abort(422,'Insufficient actual cash.');
                } else if ($cash['available']<cents_db($t['amount'])) abort(422,'Insufficient uncommitted available cash.');
            }
            if ($t['transaction_type']==='advance_disbursement' || $t['transaction_type']==='advance_return') {
                if (!one('SELECT id FROM transaction_attachments WHERE transaction_id=? LIMIT 1',[$id])) abort(422,'Payment proof is required.');
                $r=r2_request((int)$t['fund_request_id'],$org,true);
                if($r['status']!=='approved' || $r['closed_at']!==null) abort(409,'Request must remain open and approved.');
                $tot=r2_request_totals($org,(int)$r['id']);
                if ($t['transaction_type']==='advance_disbursement') {
                    if($tot['direct']>0 || $tot['advances']+cents_db($t['amount'])>cents_db($r['amount'])-cents_db($r['commitment_released']))abort(422,'Advance exceeds approval or conflicts with a direct expense.');
                    if(r2_account_cash((int)$t['account_id'])<cents_db($t['amount']))abort(422,'Insufficient cash in the chosen account.');
                    $case=one('SELECT status FROM liquidations WHERE fund_request_id=?',[$r['id']]);
                    if ($case && in_array($case['status'],['submitted','under_review','verified','closed'],true))abort(409,'Liquidation review is underway; new advance blocked.');
                } else if ($t['transaction_type']==='advance_return') {
                    if($tot['returns']+cents_db($t['amount'])>$tot['advances']-$tot['recognized'])abort(422,'Return exceeds funds available to repay.');
                }
            }
            execute_sql("UPDATE financial_transactions SET status='posted',posted_by=?,posted_at=NOW()
                         WHERE id=? AND organization_id=? AND status='pending'",[me()['id'],$id,$org]);
            audit('post_transaction','transaction',$id,$org,$t['transaction_type'].' '.$t['amount']);
            if ($t['transaction_type']==='advance_disbursement') {
                $due=(new DateTimeImmutable('today'))->modify('+'.r2_due_days($org).' days')->format('Y-m-d');
                execute_sql("INSERT INTO liquidations (fund_request_id,status,due_on) VALUES (?,'draft',?) ON DUPLICATE KEY UPDATE due_on=COALESCE(due_on,VALUES(due_on))",[$t['fund_request_id'],$due]);
                execute_sql("INSERT INTO financial_calendar_items (organization_id,title,due_on,item_type,fund_request_id,created_by) VALUES (?,?,?,'liquidation',?,?) ON DUPLICATE KEY UPDATE id=id",[$org,'Liquidation due for fund request #'.$t['fund_request_id'],$due,$t['fund_request_id'],me()['id']]);
                $rr=r2_request((int)$t['fund_request_id'],$org);
                r2_notify((int)$rr['requested_by'],$org,'advance-posted:'.$id,'Advance posted for '.$rr['reference_no'].'. Submit evidence before the deadline.','liquidations');
            }
            if ($t['transaction_type']==='advance_return') {
                $rr=r2_request((int)$t['fund_request_id'],$org);
                r2_notify((int)$rr['requested_by'],$org,'return-posted:'.$id,'Advance return posted for '.$rr['reference_no'],'liquidations');
            }
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        redirect_to('transactions',$org,'Transaction verified and posted exactly once.');
    }
    abort(404,'Unknown action.');
}

function serve_receipt(int $id): never {
    login_required();
    $a=one("SELECT a.*,t.organization_id FROM transaction_attachments a
            JOIN financial_transactions t ON t.id=a.transaction_id WHERE a.id=?",[$id]);
    if (!$a) abort(404,'File not found.');
    $org=(int)$a['organization_id'];
    if (!array_filter(my_roles($org),fn($r)=>in_array($r['role'],['treasurer','president','adviser','auditor'],true)))
        abort(403,'Not authorized to view this organization document.');
    $p=dirname(__DIR__).'/storage/uploads/'.$a['stored_name'];
    if (!is_file($p)) abort(404,'File unavailable.');
    header('Content-Type: '.$a['mime_type']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Content-Disposition: attachment; filename="receipt-'.(int)$id.'.'.pathinfo($a['stored_name'],PATHINFO_EXTENSION).'"');
    header('Content-Length: '.filesize($p));
    readfile($p);exit;
}
function export_transactions(): never {
    login_required();$org=selected_org();
    require_role($org,['treasurer','president','adviser','auditor']);
    $data=rows("SELECT t.id,t.occurred_on,t.transaction_type,t.category,t.description,t.amount,t.status,
       u.full_name created_by FROM financial_transactions t JOIN users u ON u.id=t.created_by
       WHERE t.organization_id=? ORDER BY t.occurred_on DESC,t.id DESC",[$org]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lsl-financial-portal-org-'.$org.'-transactions.csv"');
    $out=fopen('php://output','w'); fputcsv($out,['ID','Date','Type','Category','Description','Amount PHP','Status','Created By']);
    foreach($data as $r) fputcsv($out,array_map('r2_escaped_csv_value',array_values($r)));fclose($out);exit;
}
