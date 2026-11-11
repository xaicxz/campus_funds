<?php
declare(strict_types=1);
require_once __DIR__.'/report_service.php';
function liquidation_page(): void {
    shell_start('Disbursements & Liquidation');$org=current_org_for_page();org_tabs($org,'liquidations');
    if ($org===null){shell_end();return;}
    $finance=r2_finance_role($org);$isRequester=has_role('officer',$org);
    if (!$finance&&!$isRequester)abort(403,'No liquidation permission.');
    $accounts=rows('SELECT id,name FROM financial_accounts WHERE organization_id=? ORDER BY name',[$org]);
    if(has_role('treasurer',$org)){
        $approved=rows("SELECT r.id,r.reference_no,r.title FROM fund_requests r WHERE r.organization_id=? AND r.status='approved' AND r.closed_at IS NULL ORDER BY r.id DESC",[$org]);
        echo '<section class="panel"><h2>Record a cash advance or unused-fund return</h2><p class="subline">Treasurer records a pending cash event; a different Adviser independently verifies and posts it. Evidence is mandatory. An approved request alone does not decrease cash.</p>';
        foreach(['record_advance'=>'Release advance','record_return'=>'Return unused advance'] as $action=>$label){
            echo '<h3>'.e($label).'</h3>';form_start($action,$org,'form-grid');
            echo '<label>Approved request<select name="request_id" required><option value="">Select</option>';foreach($approved as $r)echo '<option value="'.(int)$r['id'].'">'.e($r['reference_no'].' · '.$r['title']).'</option>';echo '</select></label>';
            echo '<label>Financial account<select name="account_id">';foreach($accounts as $a)echo '<option value="'.(int)$a['id'].'">'.e($a['name']).'</option>';echo '</select></label>';
            echo '<label>Amount (PHP)<input name="amount" inputmode="decimal" pattern="[0-9]+(\\.[0-9]{1,2})?" required></label><label>Date<input type="date" name="occurred_on" value="'.date('Y-m-d').'" required></label>';
            echo '<label>Payment method / bank reference<input name="payment_method" maxlength="60" placeholder="Cash, bank transfer" required></label><label>Unique transaction reference<input name="event_key" maxlength="90" placeholder="e.g. SELECT-ADV-0001" required></label>';
            echo '<label class="wide">Payment or return proof<input type="file" name="receipt" accept=".pdf,.png,.jpg,.jpeg" required></label>';
            echo '<div class="wide"><button class="btn primary">'.e($label).' (pending verification)</button></div></form>';
        }
        echo '</section>';
    }
    if($finance){
        $pending=rows("SELECT t.*,r.reference_no,u.full_name author FROM financial_transactions t JOIN fund_requests r ON r.id=t.fund_request_id JOIN users u ON u.id=t.created_by WHERE t.organization_id=? AND t.transaction_type IN ('advance_disbursement','advance_return') AND t.status='pending' ORDER BY t.id DESC",[$org]);
        echo '<section class="panel"><h2>Cash events awaiting independent verification</h2><div class="table-scroll"><table><thead><tr><th>Reference</th><th>Event</th><th>Amount</th><th>Created by</th><th>Action</th></tr></thead><tbody>';
        foreach($pending as $t){echo '<tr><td>'.e($t['reference_no']).'</td><td>'.e($t['transaction_type']).'</td><td>'.money($t['amount']).'</td><td>'.e($t['author']).'</td><td>';
            if(has_role('adviser',$org)&&(int)$t['created_by']!==(int)me()['id']){form_start('post_tx',$org,'inline-decision');echo '<input type="hidden" name="id" value="'.(int)$t['id'].'"><button class="btn small">Verify & post</button></form>';}else echo 'Adviser review';echo '</td></tr>';}
        if(!$pending)echo '<tr><td colspan="5" class="empty">No pending cash events.</td></tr>';echo '</tbody></table></div></section>';
    }
    if($isRequester){
        $requests=rows("SELECT r.id,r.reference_no,r.title,r.budget_id FROM fund_requests r WHERE r.organization_id=? AND r.requested_by=? AND r.status='approved' AND r.closed_at IS NULL AND EXISTS (SELECT 1 FROM financial_transactions t WHERE t.fund_request_id=r.id AND t.transaction_type='advance_disbursement' AND t.status='posted') ORDER BY r.id DESC",[$org,me()['id']]);
        echo '<section class="panel"><h2>Submit itemized liquidation</h2><p class="subline">Enter one expense per line and upload exactly one receipt per line, in matching order. Revised submissions preserve previous versions. Submit only after the funds have been released.</p>';
        $budLines=rows("SELECT bl.id,bl.category,b.id budget_id,b.name budget_name FROM budget_lines bl JOIN budgets b ON b.id=bl.budget_id WHERE b.organization_id=? AND b.status='approved' ORDER BY b.name,bl.id",[$org]);
        echo '<details><summary>Show approved budget line IDs</summary><div class="table-scroll"><table><tr><th>Line ID</th><th>Budget</th><th>Category</th></tr>';foreach($budLines as $bl)echo '<tr><td>'.(int)$bl['id'].'</td><td>'.e($bl['budget_name']).'</td><td>'.e($bl['category']).'</td></tr>';echo '</table></div></details>';
        form_start('liquidation_submit',$org,'form-grid');echo '<label class="wide">Approved request<select name="request_id" required><option value="">Select</option>';foreach($requests as $r)echo '<option value="'.(int)$r['id'].'" data-budget="'.(int)$r['budget_id'].'">'.e($r['reference_no'].' · '.$r['title']).'</option>';echo '</select></label>';
        echo '<div class="wide"><div class="liquidation-editor" data-liq-editor hidden>';
        echo '<div class="section-title"><h3>Expense items</h3><span class="editor-count" data-liq-count>1 item</span></div>';
        echo '<p class="subline">Complete each expense row and attach its corresponding receipt (PDF, PNG or JPG; max 5 MB).</p>';
        echo '<div data-liq-rows></div><button type="button" class="btn small add-row" data-liq-add>+ Add expense item</button>';
        echo '<div class="editor-summary">Itemized total <strong data-liq-total>₱0.00</strong></div></div>';
        echo '<label class="manual-lines wide">Expense lines (manual entry format: Date|Payee|Description|Budget line ID|Amount)<textarea name="items" rows="5" required data-liq-items placeholder="2026-10-20|Bookshop|Print materials|12|650.00"></textarea></label>';
        echo '<label class="manual-lines wide" data-manual-evidence>Receipts in the same order as expense lines<input type="file" name="proofs[]" accept=".pdf,.png,.jpg,.jpeg" multiple required></label>';
        echo '<template id="liquidation-row-template"><div class="liquidation-row"><div class="line-heading"><span class="line-number">Expense item</span><button type="button" class="remove-line" data-liq-remove aria-label="Remove expense item">Remove</button></div><div class="form-grid">';
        echo '<label>Expense date<input type="date" data-field="date" required></label>';
        echo '<label>Payee<input maxlength="150" data-field="payee" placeholder="e.g. Bookshop" required></label>';
        echo '<label class="wide">Description<input maxlength="255" data-field="description" placeholder="Purchased supplies" required></label>';
        echo '<label>Budget category<select data-field="line" required><option value="">Select approved category</option>';
        foreach($budLines as $bl)echo '<option value="'.(int)$bl['id'].'" data-budget="'.(int)$bl['budget_id'].'">'.e($bl['budget_name'].' / '.$bl['category']).'</option>';
        echo '</select></label>';
        echo '<label>Amount (PHP)<input type="number" data-field="amount" min="0.01" max="999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" required></label>';
        echo '<label class="wide">Receipt / invoice<input type="file" name="proofs[]" accept=".pdf,.png,.jpg,.jpeg" required></label>';
        echo '</div></div></template></div>';
        echo '<div class="wide"><button class="btn primary" '.(!$requests?'disabled':'').'>Submit for Treasurer review</button></div></form></section>';
    }
    $sql="SELECT l.*,r.reference_no,r.title,r.organization_id,r.requested_by,u.full_name owner FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id JOIN users u ON u.id=r.requested_by WHERE r.organization_id=?";
    $args=[$org];if(!$finance){$sql.=' AND r.requested_by=?';$args[]=me()['id'];}
    $cases=rows($sql.' ORDER BY l.id DESC',$args);
    echo '<section class="panel"><h2>Liquidation case tracker</h2><p class="subline">Overdue is a date flag, not a workflow status. Only verified expense items affect recognized spending; advances are never counted again as expenses.</p><div class="table-scroll"><table><thead><tr><th>Fund request</th><th>Requester</th><th>Due</th><th>State</th><th>Released</th><th>Verified spent</th><th>Returned</th><th>Unresolved</th><th>History</th></tr></thead><tbody>';
    foreach($cases as $c){$t=r2_request_totals($org,(int)$c['fund_request_id']);$overdue=$c['due_on'] && $c['due_on']<date('Y-m-d')&&!in_array($c['status'],['closed','rejected'],true);
        echo '<tr><td>'.e($c['reference_no']).'<small>'.e($c['title']).'</small></td><td>'.e($c['owner']).'</td><td>'.e($c['due_on']??'Not set').($overdue?' <strong class="negative">OVERDUE</strong>':'').'</td><td>'.status_badge($c['status']).'</td><td>'.money(peso_str($t['advances'])).'</td><td>'.money(peso_str($t['recognized'])).'</td><td>'.money(peso_str($t['returns'])).'</td><td>'.money(peso_str($t['advances']-$t['recognized']-$t['returns'])).'</td><td><a href="index.php?'.http_build_query(['page'=>'liquidations','org'=>$org,'case'=>$c['id']]).'">Review history</a></td></tr>';
    }
    if(!$cases)echo '<tr><td colspan="9" class="empty">No liquidation cases recorded.</td></tr>';echo '</tbody></table></div></section>';
    $selected=(int)($_GET['case']??0);
    if($selected){
        $c=r2_case($selected,$org);if(!r2_can_see_case($c))abort(403,'Case not accessible.');
        $subs=rows('SELECT s.*,u.full_name submitter FROM liquidation_submissions s JOIN users u ON u.id=s.submitted_by WHERE s.liquidation_id=? ORDER BY s.version_number DESC',[$selected]);
        echo '<section class="panel"><h2>Liquidation #'.$selected.' • Revision and review history</h2><p class="subline">'.e($c['reference_no'].' — '.$c['title']).'</p>';
        foreach($subs as $sub){echo '<h3>Submission v'.(int)$sub['version_number'].' — '.e($sub['submitter']).' · '.e($sub['submitted_at']).' — '.money($sub['item_total']).'</h3>';
            $items=rows('SELECT i.*,bl.category FROM liquidation_items i JOIN budget_lines bl ON bl.id=i.budget_line_id WHERE i.submission_id=? ORDER BY i.id',[$sub['id']]);
            echo '<div class="table-scroll"><table><thead><tr><th>Date</th><th>Payee / description</th><th>Budget category</th><th>Amount</th><th>Evidence</th></tr></thead><tbody>';
            foreach($items as $x)echo '<tr><td>'.e($x['occurred_on']).'</td><td>'.e($x['payee']).'<small>'.e($x['description']).'</small></td><td>'.e($x['category']).'</td><td>'.money($x['amount']).'</td><td><a href="index.php?'.http_build_query(['page'=>'liquidation_evidence','org'=>$org,'id'=>$x['id']]).'">Download</a></td></tr>';
            echo '</tbody></table></div>';
            $decisions=rows('SELECT d.*,u.full_name reviewer FROM liquidation_decisions d JOIN users u ON u.id=d.reviewer_id WHERE d.submission_id=? ORDER BY d.id',[$sub['id']]);
            foreach($decisions as $d)echo '<p><strong>'.e($d['stage']).': '.e($d['decision']).'</strong> — '.e($d['reviewer']).' · '.e($d['decided_at']).' '.e($d['reason']??'').'</p>';
        }
        $stage=['submitted'=>'treasurer','under_review'=>'adviser','verified'=>'president'][$c['status']]??null;
        if($stage && has_role($stage,$org) && (int)$c['requested_by']!==(int)me()['id']
           && !one('SELECT id FROM liquidation_decisions WHERE submission_id=(SELECT id FROM liquidation_submissions WHERE liquidation_id=? ORDER BY version_number DESC LIMIT 1) AND reviewer_id=? LIMIT 1',[$selected,me()['id']])) {
            echo '<h3>'.e(ucfirst($stage)).' review</h3>';form_start('liquidation_decide',$org,'form-grid');echo '<input type="hidden" name="id" value="'.$selected.'"><label>Decision<select name="decision">';
            $opts=$stage==='president'?['closed'=>'Close balanced case']:['approved'=>'Approve and forward','returned'=>'Return for revision','rejected'=>'Reject submission'];
            foreach($opts as $val=>$label)echo '<option value="'.e($val).'">'.e($label).'</option>';
            echo '</select></label><label>Reason (required on return or rejection)<input name="reason" maxlength="250"></label><div class="wide"><button class="btn primary">Record review</button></div></form>';
        }
        echo '</section>';
    }
    shell_end();
}
function calendar_page(): void {
    shell_start('Financial Calendar');$org=current_org_for_page();org_tabs($org,'calendar');
    if($org===null){shell_end();return;}
    if(!r2_finance_role($org)&&!has_role('officer',$org))abort(403,'Calendar access denied.');
    if(has_role('treasurer',$org)){
        echo '<section class="panel"><h2>Add a financial deadline</h2>';form_start('calendar_save',$org,'form-grid');
        echo '<label>Title<input name="title" maxlength="160" required></label><label>Due date<input name="due_on" type="date" required></label><label class="wide">Description<input name="details" maxlength="255"></label><div class="wide"><button class="btn primary">Save deadline</button></div></form></section>';
    }
    $sql='SELECT c.* FROM financial_calendar_items c WHERE c.organization_id=?';$args=[$org];
    if(!r2_finance_role($org)){$sql.=' AND c.fund_request_id IN (SELECT id FROM fund_requests WHERE requested_by=? AND organization_id=?)';$args[] = me()['id'];$args[]=$org;}
    $items=rows($sql.' ORDER BY c.completed_at IS NOT NULL,c.due_on ASC LIMIT 200',$args);
    echo '<section class="panel"><h2>Deadlines and overdue items</h2><div class="table-scroll"><table><thead><tr><th>Due</th><th>Deadline</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach($items as $x){$completed=(bool)$x['completed_at'];if($x['item_type']==='liquidation'){$c=one('SELECT status FROM liquidations WHERE fund_request_id=?',[$x['fund_request_id']]);$completed=$c && $c['status']==='closed';}
        $due=$x['due_on']<date('Y-m-d')&&!$completed?'OVERDUE':($completed?'Completed':'Upcoming');
        echo '<tr><td>'.e($x['due_on']).'</td><td>'.e($x['title']).'<small>'.e($x['details']??'').'</small></td><td>'.e($x['item_type']).'</td><td>'.e($due).'</td><td>';
        if(!$completed && $x['item_type']==='manual' && has_role('treasurer',$org)){form_start('calendar_complete',$org,'inline-decision');echo '<input type="hidden" name="id" value="'.(int)$x['id'].'"><button class="btn small">Mark done</button></form>';}else echo '—';echo '</td></tr>';
    }if(!$items)echo '<tr><td colspan="5" class="empty">No deadlines yet.</td></tr>';echo '</tbody></table></div></section>';shell_end();
}
function notifications_page(): void {
    shell_start('Notifications');
    $items=rows("SELECT n.*,o.name organization FROM notifications n LEFT JOIN organizations o ON o.id=n.organization_id WHERE n.user_id=? ORDER BY n.is_read ASC,n.id DESC LIMIT 150",[me()['id']]);
    echo '<section class="panel"><h2>My notification inbox</h2><p class="subline">Only alerts assigned to this signed-in account are visible.</p><div class="table-scroll"><table><thead><tr><th>Date</th><th>Organization</th><th>Message</th><th>Read</th></tr></thead><tbody>';
    foreach($items as $a){
        echo '<tr><td>'.e($a['created_at']).'</td><td>'.e($a['organization']??'—').'</td><td>'.e($a['message']);
        $allowed=['budgets','requests','transactions','liquidations','calendar','reports'];
        if(in_array($a['link_page'],$allowed,true)){
            $params=['page'=>$a['link_page']];
            if((int)$a['organization_id']>1)$params['org']=(int)$a['organization_id'];
            echo ' <a href="index.php?'.e(http_build_query($params)).'">View record area</a>';
        }
        echo '</td><td>';if(!$a['is_read']){form_start('notification_read');echo '<input type="hidden" name="id" value="'.(int)$a['id'].'"><button class="btn small">Mark read</button></form>';}else echo 'Read';echo '</td></tr>';
    }
    if(!$items)echo '<tr><td colspan="4" class="empty">No notifications.</td></tr>';echo '</tbody></table></div></section>';shell_end();
}
function r2_reports_page(): void {
    shell_start('Financial Reports');
    $sg=has_role('sg_finance',1) && (($_GET['scope']??'')==='sg' || !scoped_orgs());
    $org=$sg?0:current_org_for_page();
    if($sg){echo '<p class="lede">SG consolidated summary contains organization-level totals only, not private receipts or transaction records.</p>';} else {
        org_tabs($org,'reports');if($org===null){shell_end();return;}require_role($org,['treasurer','president','adviser','auditor']);
        if(has_role('sg_finance',1))echo '<p><a href="index.php?page=reports&scope=sg">View SG consolidated report</a></p>';
    }
    [$from,$to]=r2_filter_dates();$types=r2_report_types($sg);$type=(string)($_GET['type']??array_key_first($types));
    $report=r2_report_query($org,$type,$from,$to,$sg);
    echo '<section class="panel"><h2>Report filters</h2><form method="get" action="index.php" class="form-grid"><input type="hidden" name="page" value="reports">';
    if($sg)echo '<input type="hidden" name="scope" value="sg">';else echo '<input type="hidden" name="org" value="'.(int)$org.'">';
    echo '<label>Report<select name="type">';foreach($types as $val=>$label)echo '<option value="'.e($val).'"'.($val===$type?' selected':'').'>'.e($label).'</option>';echo '</select></label>';
    echo '<label>From<input type="date" name="from" value="'.e($from).'" required></label><label>To<input type="date" name="to" value="'.e($to).'" required></label><div class="wide"><button class="btn primary">Load report</button></div></form></section>';
    $params=['page'=>'report_download','type'=>$type,'from'=>$from,'to'=>$to];if($sg)$params['scope']='sg';else $params['org']=$org;
    echo '<section class="panel"><div class="section-title"><h2>'.e($report['title']).'</h2><div>';
    foreach(['csv'=>'CSV','pdf'=>'PDF','xlsx'=>'Excel XLSX'] as $format=>$label){$params['format']=$format;echo ' <a class="btn small" href="index.php?'.http_build_query($params).'">'.e($label).' ↓</a>';}
    echo '</div></div><p class="subline">'.e($report['basis']).' · Period '.e($from).' to '.e($to).' · '.count($report['records']).' records. PDF and XLSX require Composer dependencies.</p>';
    echo '<div class="table-scroll"><table><thead><tr>';foreach($report['headers'] as $header)echo '<th>'.e($header).'</th>';echo '</tr></thead><tbody>';
    foreach($report['records'] as $row){echo '<tr>';foreach($row as $v)echo '<td>'.e($v).'</td>';echo '</tr>';}
    if(!$report['records'])echo '<tr><td colspan="'.count($report['headers']).'" class="empty">No results for the selected filters.</td></tr>';
    echo '</tbody></table></div></section>';shell_end();
}
function r2_sg_chart(): void {
    $orgs=rows('SELECT id,name FROM organizations WHERE is_sg=0 AND active=1 ORDER BY id');
    $bars=[];$max=1;
    foreach($orgs as $o){$s=r2_financial_summary((int)$o['id']);$bars[]=['name'=>$o['name'],'actual'=>$s['actual'],'expense'=>$s['expense']];$max=max($max,abs($s['actual']),abs($s['expense']));}
    echo '<section class="panel"><h2>Cash and recognized expense comparison</h2><p class="subline">SG aggregate view. Bar lengths are scaled to the largest absolute value across the four organizations; numeric totals remain authoritative.</p><div class="finance-chart">';
    foreach($bars as $b){$cash=max(0,min(100,round(abs($b['actual'])/$max*100)));$expense=max(0,min(100,round(abs($b['expense'])/$max*100)));
        echo '<div class="chart-row"><strong>'.e($b['name']).'</strong><div class="bars"><span>Cash</span><div class="bar-track"><div class="bar-fill cash" style="width:'.$cash.'%"></div></div><small>'.money(peso_str($b['actual'])).'</small><span>Spent</span><div class="bar-track"><div class="bar-fill spent" style="width:'.$expense.'%"></div></div><small>'.money(peso_str($b['expense'])).'</small></div></div>';
    }echo '</div></section>';
}
function r2_org_budget_chart(int $org): void {
    $budgets=rows("SELECT b.id,b.name,COALESCE(SUM(bl.allocated),0) allocated FROM budgets b LEFT JOIN budget_lines bl ON bl.budget_id=b.id WHERE b.organization_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.id LIMIT 10",[$org]);
    if(!$budgets)return;
    echo '<section class="panel"><h2>Approved budget utilization</h2><p class="subline">Recognized spending comprises direct posted expenses and independently verified liquidation items.</p>';
    foreach($budgets as $b){$allocated=cents_db($b['allocated']);$spent=r2_budget_expenses((int)$b['id']);$pct=$allocated?min(100,max(0,round($spent/$allocated*100))):0;
        echo '<div class="budget-meter"><div><strong>'.e($b['name']).'</strong><span>'.e((string)$pct).'% used • '.money(peso_str($spent)).' / '.money($b['allocated']).'</span></div><div class="bar-track"><div class="bar-fill spent" style="width:'.$pct.'%"></div></div></div>';
    }echo '</section>';
}
function r2_audit_page(): void {
    shell_start('Audit Trail');$org=current_org_for_page();org_tabs($org,'audit');
    if($org===null){shell_end();return;}require_role($org,['treasurer','president','adviser','auditor']);
    $search=trim((string)($_GET['search']??''));if(strlen($search)>90)abort(422,'Search is too long.');
    [$from,$to]=r2_filter_dates();$args=[$org,$from,$to];
    $sql="SELECT l.*,u.full_name actor FROM audit_logs l LEFT JOIN users u ON u.id=l.actor_id WHERE l.organization_id=? AND DATE(l.created_at) BETWEEN ? AND ?";
    if($search!==''){$sql.=" AND (l.action LIKE ? OR l.entity_type LIKE ? OR u.full_name LIKE ?)";$v='%'.$search.'%';$args=array_merge($args,[$v,$v,$v]);}
    $events=rows($sql.' ORDER BY l.id DESC LIMIT 300',$args);
    echo '<section class="panel"><h2>Search actions and approvals</h2><form class="form-grid" action="index.php" method="get"><input type="hidden" name="page" value="audit"><input type="hidden" name="org" value="'.(int)$org.'">';
    echo '<label>Search user, action, or record type<input name="search" maxlength="90" value="'.e($search).'"></label><label>From<input type="date" name="from" value="'.e($from).'"></label><label>To<input type="date" name="to" value="'.e($to).'"></label><div class="wide"><button class="btn primary">Filter audit events</button></div></form></section>';
    echo '<section class="panel"><h2>Recorded audit history</h2><p class="subline">The latest 300 matching events are displayed. Full period export is available under Financial Reports → Audit.</p><div class="table-scroll"><table><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>';
    foreach($events as $a)echo '<tr><td>'.e($a['created_at']).'</td><td>'.e($a['actor']??'System').'</td><td>'.e($a['action']).'</td><td>'.e($a['entity_type'].' #'.$a['entity_id']).'</td><td>'.e($a['details']).'</td></tr>';
    if(!$events)echo '<tr><td colspan="5" class="empty">No audit events match your filters.</td></tr>';echo '</tbody></table></div></section>';shell_end();
}
