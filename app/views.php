<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/ui.php';
function form_start(string $action,?int $org=null,string $class=''): void {
    echo '<form method="post" class="'.e($class).'" action="index.php?action='.e($action).'"';
    if (in_array($action,['create_tx','record_advance','record_return','liquidation_submit'],true)) echo ' enctype="multipart/form-data"';
    echo '><input type="hidden" name="csrf" value="'.e(csrf()).'">';
    if ($org!==null) echo '<input type="hidden" name="org" value="'.$org.'">';
}
function shell_start(string $heading): void {
    $user=me();$name=(string)$user['name'];$page=(string)($_GET['page']??'dashboard');
    $roles=my_roles();$orgFinance=false;$officer=false;$sg=false;
    foreach($roles as $r){
        if(in_array($r['role'],['treasurer','president','adviser','auditor'],true))$orgFinance=true;
        if($r['role']==='officer')$officer=true;
        if($r['role']==='sg_finance' && (int)$r['organization_id']===1)$sg=true;
    }
    $isAdmin=has_role('system_admin',1);
    $nav=[
        'Main'=>[['dashboard','Overview','dashboard']],
        'Finance'=>[],
        'Accountability'=>[],
        'Preferences'=>[]
    ];
    if($orgFinance || $officer || $sg)$nav['Finance'][]=['budgets','Budgets','budgets'];
    if($orgFinance || $officer)$nav['Finance'][]=['requests','Fund Requests','requests'];
    if($orgFinance){
        $nav['Finance'][]=['transactions','Income & Expenses','transactions'];
        $nav['Finance'][]=['liquidations','Liquidations','liquidations'];
        $nav['Accountability'][]=['reports','Financial Reports','reports'];
        $nav['Accountability'][]=['audit','Audit Trail','audit'];
        $nav['Accountability'][]=['calendar','Financial Calendar','calendar'];
    }elseif($officer){
        $nav['Finance'][]=['liquidations','My Liquidations','liquidations'];
        $nav['Accountability'][]=['calendar','My Deadlines','calendar'];
    }
    if($sg && !$orgFinance)$nav['Accountability'][]=['reports','SG Reports','reports'];
    $nav['Accountability'][]=['notifications','Notifications','notifications'];
    if($isAdmin)$nav['Preferences'][]=['users','User Management','users'];
    $nav['Preferences'][]=['account','My Account','account'];
    $roleLabel='Authorized user';
    if($isAdmin)$roleLabel='System administrator';
    elseif($sg)$roleLabel='SG financial administrator';
    elseif($roles)$roleLabel=ucwords(str_replace('_',' ',(string)$roles[0]['role']));
    $unread=(int)(one('SELECT COUNT(*) AS total FROM notifications WHERE user_id=? AND is_read=0',[$user['id']])['total']??0);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="theme-color" content="#001B48"><title>'.e($heading).' | LSL Financial Portal</title>';
    echo '<link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg"><link rel="stylesheet" href="assets/css/style.css"></head><body>';
    echo '<a class="skip-link" href="#main-content">Skip to main content</a>';
    echo '<div class="app"><div class="menu-backdrop" data-menu-close></div><aside class="sidebar" id="portal-sidebar" aria-label="Main navigation">';
    echo '<div class="sidebar-head"><a class="brand" href="index.php?page=dashboard"><span class="brand-mark">LSL</span><span class="brand-copy">LSL <span>Financial Portal</span><small class="brand-tagline"><span>Creating Lifes Champion</span><span>Better LSL: learn better. lead better.</span></small></span></a>';
    echo '<button class="sidebar-close" type="button" data-menu-close aria-label="Close navigation">'.portal_icon('close').'</button></div>';
    foreach($nav as $section=>$items){if(!$items)continue;
        echo '<div class="nav-group"><div class="side-label">'.e($section).'</div><nav class="nav" aria-label="'.e($section).'">';
        foreach($items as [$route,$label,$icon]){
            $active=$page===$route;
            echo '<a href="index.php?page='.e($route).'" class="nav-link'.($active?' active':'').'"'.($active?' aria-current="page"':'').'><span class="nav-icon">'.portal_icon($icon).'</span><span class="nav-text">'.e($label).'</span>';
            if($route==='notifications'&&$unread>0)echo '<span class="nav-badge" aria-label="'.min(99,$unread).' unread">'.($unread>99?'99+':$unread).'</span>';
            echo '</a>';
        }
        echo '</nav></div>';
    }
    echo '</aside>';
    echo '<div class="main-column"><header class="topbar"><div class="topbar-leading">';
    echo '<button class="mobile-toggle" type="button" data-menu aria-controls="portal-sidebar" aria-expanded="false" aria-label="Open navigation">'.portal_icon('menu',23).'</button>';
    echo '<div class="breadcrumbs"><span>LSL Financial Portal</span>'.portal_icon('chevron',14).'<strong>'.e($heading).'</strong></div></div>';
    echo '<div class="account"><a class="top-icon-button" href="index.php?page=notifications" aria-label="Notifications'.($unread?' ('.$unread.' unread)':'').'">'.portal_icon('notifications',21).($unread?'<i class="notification-dot"></i>':'').'</a>';
    echo '<span class="account-divider"></span><a class="account-identity" href="index.php?page=account"><span class="avatar">'.e(strtoupper(substr($name,0,1))).'</span><span class="account-label"><strong>'.e($name).'</strong><small>'.e($roleLabel).'</small></span></a>';
    form_start('logout');echo '<button type="submit" class="logout-button" aria-label="Log out" title="Log out">'.portal_icon('logout',19).'</button></form></div></header>';
    echo '<main class="content" id="main-content"><div class="page-heading"><h1>'.e($heading).'</h1></div>';
    if(!empty($_SESSION['flash'])){echo '<div class="notice success" role="status">'.portal_icon('check',19).e($_SESSION['flash']).'</div>';unset($_SESSION['flash']);}
}
function shell_end(): void {
    echo '</main><footer class="portal-footer"><span>© '.date('Y').' LSL Financial Portal</span></footer></div></div>';
    echo '<script src="assets/js/app.js" defer></script></body></html>';
}
function login_page(): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#001B48"><title>Sign in | LSL Financial Portal</title><link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg"><link rel="stylesheet" href="assets/css/style.css"></head><body class="auth-page">';
    echo '<main class="auth-wrap"><section class="auth-intro"><div class="auth-intro-inner"><div class="auth-overline"><span class="auth-monogram">LSL</span><span>LSL FINANCIAL PORTAL</span></div>';
    echo '<h1>Creating Lifes<br><em>Champion</em></h1><p class="auth-brand-tagline">Better LSL: learn better. lead better.</p>';
    echo '<p>One secure portal for campus organization finances.</p>';
    echo '<div class="auth-line"></div><div class="auth-detail">'.portal_icon('shield',23).'<span>Secure role-based access<br><small>Transparent reporting · Organized workflows</small></span></div>';
    echo '<div class="org-chips"><span>SELECT LSL</span><span>PSCAS LSL</span><span>JPIA LSL</span><span>CRIM SOCIETY</span></div></div><div class="auth-accent-circle"></div></section>';
    echo '<section class="auth-form-side"><div class="auth-box"><div class="auth-box-top"><span class="auth-step">ACCOUNT ACCESS</span><span class="auth-lock">'.portal_icon('shield',17).' Secure login</span></div>';
    echo '<h2>Welcome back<span class="heading-period">.</span></h2><p>Sign in with your assigned credentials to continue to the portal.</p>';
    form_start('login',null,'stacked');
    echo '<label>Email address<input name="email" type="email" placeholder="name@campusfunds.test" autocomplete="username" required autofocus></label>';
    echo '<label>Password<div class="password-wrap"><input id="login-password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button class="password-toggle" type="button" data-toggle-password="login-password" aria-label="Show password" aria-pressed="false">Show</button></div></label>';
    echo '<button class="btn primary full auth-submit" type="submit">Sign in to portal <span aria-hidden="true">→</span></button></form>';
    echo '<div class="auth-help">'.portal_icon('shield',18).'<p>Account access is managed by the System Administrator. Contact your assigned officer for login assistance.</p></div>';
    echo '</div><div class="auth-copyright">© '.date('Y').' LSL Financial Portal · Authorized users only</div></section></main><script src="assets/js/app.js" defer></script></body></html>';
}
function current_org_for_page(): ?int {
    $eligible=scoped_orgs();
    $from=(int)($_GET['org']??0);
    if ($from) {
        foreach($eligible as $o) if ((int)$o['id']===$from) return $from;
        abort(403,'You do not have access to this organization.');
    }
    return isset($eligible[0])?(int)$eligible[0]['id']:null;
}
function org_tabs(?int $selected,string $page): void {
    $orgs=scoped_orgs();
    if (!$orgs) {echo '<div class="notice">No organization assignment is available for this account.</div>';return;}
    echo '<div class="org-tabs" aria-label="Choose organization">';
    foreach($orgs as $o) echo '<a class="'.($selected===(int)$o['id']?'active':'').'" href="index.php?'.http_build_query(['page'=>$page,'org'=>$o['id']]).'">'.e($o['name']).'</a>';
    echo '</div>';
}
function amount_card(string $caption,int $cents,string $symbol,string $help=''): void {
    $icon = match($symbol){'₱'=>'wallet','◈'=>'shield','↗'=>'arrow-up','↘'=>'arrow-down','▣'=>'liquidations','▤'=>'budgets',default=>'trend'};
    echo '<article class="stat-card"><div class="stat-top"><span>'.e($caption).'</span><span class="stat-icon">'.portal_icon($icon,21).'</span></div>';
    echo '<div class="stat-value">'.e(money(peso_str($cents))).'</div>';
    if($cents<0)echo '<span class="stat-warning">Negative balance</span>';
    echo '<p>'.e($help).'</p></article>';
}
function status_badge(string $s): string {
    $t=str_replace('_',' ',ucwords(str_replace('_',' ',$s)));
    $class=match($s){'approved','posted','closed','verified'=>'good','rejected','overdue'=>'bad','returned'=>'returned',default=>'pending'};
    return '<span class="status '.$class.'">'.e($t).'</span>';
}
function dashboard_page(): void {
    shell_start('Financial Dashboard');
    $org=current_org_for_page();
    if (has_role('sg_finance',1)) {
        $all=rows('SELECT id,name FROM organizations WHERE is_sg=0 AND active=1 ORDER BY name');
        echo '<p class="lede">Student Government consolidated oversight. Figures remain separate for every organization.</p>';
        $tot=['actual'=>0,'available'=>0,'income'=>0,'expense'=>0];
        foreach($all as $o){$s=cash_summary((int)$o['id']);foreach($tot as $k=>$_) $tot[$k]+=$s[$k];}
        echo '<div class="stats">';amount_card('Recorded actual cash',$tot['actual'],'₱','Combined posted ledger amounts');
        amount_card('Available cash',$tot['available'],'◈','Less approved unpaid commitments');
        amount_card('Posted income',$tot['income'],'↗','All organizations');amount_card('Recognized expenses',$tot['expense'],'↘','Direct expenses + verified liquidation');echo '</div>';
        echo '<section class="panel"><h2>Organization comparison</h2><p class="subline">SG view provides summary totals; private transactions require explicit organization roles.</p><div class="table-scroll"><table><thead><tr><th>Organization</th><th>Actual cash</th><th>Available cash</th><th>Income</th><th>Expenses</th><th>Overdue liquidations</th></tr></thead><tbody>';
        foreach($all as $o){$s=cash_summary((int)$o['id']);echo '<tr><td>'.e($o['name']).'</td><td>'.money(peso_str($s['actual'])).'</td><td>'.money(peso_str($s['available'])).'</td><td>'.money(peso_str($s['income'])).'</td><td>'.money(peso_str($s['expense'])).'</td><td>'.(int)one("SELECT COUNT(*) n FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id WHERE r.organization_id=? AND l.due_on<CURDATE() AND l.status NOT IN ('closed','rejected')",[$o['id']])['n'].'</td></tr>';}
        echo '</tbody></table></div></section>';
        r2_sg_chart();
    }
    if ($org!==null) {
        require_role($org,['treasurer','president','adviser','auditor','officer','authorized_financial_user']);
        org_tabs($org,'dashboard');
        if (!has_role('treasurer',$org) && !has_role('president',$org) && !has_role('adviser',$org) && !has_role('auditor',$org)) {
            $copy=has_role('officer',$org)
                ? 'You can submit and track your fund requests. Account balances and transactions require a financial role.'
                : 'Authorized financial user: your administrator must assign an additional permitted financial role before you can manage or view private financial records.';
            echo '<div class="notice">'.e($copy).'</div>';
        } else {
        $stats=cash_summary($org);
        $pending=one("SELECT COUNT(*) n FROM fund_requests WHERE organization_id=? AND status LIKE 'pending_%'",[$org])['n'];
        $budget=one("SELECT COALESCE(SUM(bl.allocated),0) total FROM budget_lines bl JOIN budgets b ON b.id=bl.budget_id WHERE b.organization_id=? AND b.status='approved'",[$org])['total'];
        echo '<div class="stats">';
        amount_card('Actual cash',$stats['actual'],'₱','Income + returns - direct expenses - advances');
        amount_card('Available cash',$stats['available'],'◈','After approved unpaid obligations');
        amount_card('Outstanding advances',$stats['outstanding_advances'],'▣','Advances not yet accounted for');
        amount_card('Approved budgets',cents_db($budget),'▤','Across approved budget categories');
        echo '<div class="stat-card"><div class="stat-top"><span>Pending requests</span><span class="stat-icon">'.portal_icon('clock',21).'</span></div><div class="stat-value">'.(int)$pending.'</div><p>Awaiting approvals</p></div></div>';
        echo '<section class="panel"><div class="section-title"><h2>Latest posted transactions</h2><a href="index.php?page=transactions&org='.$org.'">See all →</a></div>';
        $list=rows("SELECT occurred_on,transaction_type,description,amount FROM financial_transactions WHERE organization_id=? AND status='posted' ORDER BY posted_at DESC,id DESC LIMIT 8",[$org]);
        echo '<div class="table-scroll"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Amount</th></tr></thead><tbody>';
        foreach($list as $r) echo '<tr><td>'.e($r['occurred_on']).'</td><td>'.e(ucfirst($r['transaction_type'])).'</td><td>'.e($r['description']).'</td><td>'.money($r['amount']).'</td></tr>';
        if (!$list) echo '<tr><td colspan="4" class="empty">No posted transactions yet.</td></tr>';
        echo '</tbody></table></div></section>';
        r2_org_budget_chart($org);
        }
    }
    if ($org===null && !has_role('sg_finance',1)) {
        $roles=my_roles();
        if (has_role('system_admin',1)) {
            echo '<div class="notice">System administrators manage account access; organizational financial records need a separate permitted role.</div><p><a class="btn primary" href="index.php?page=users">Manage user accounts &rarr;</a></p>';
        } elseif ($roles) {
            echo '<section class="panel"><h2>My assigned organizations</h2><p class="subline">Your account has basic portal access. No private budgets, reports, or transactions are available for this role.</p><div class="table-scroll"><table><thead><tr><th>Organization</th><th>Role</th></tr></thead><tbody>';
            foreach($roles as $role) echo '<tr><td>'.e($role['organization_name']).'</td><td>'.e(str_replace('_',' ',ucwords($role['role'],'_'))).'</td></tr>';
            echo '</tbody></table></div></section>';
        } else echo '<div class="notice">No active role assignment. Please contact the system administrator.</div>';
    }
    shell_end();
}
function budgets_page(): void {
    shell_start('Budgets & Categories');$org=current_org_for_page();org_tabs($org,'budgets');
    if (has_role('sg_finance',1)) {
        $queue=rows("SELECT b.id,b.name,b.organization_id,b.created_by,o.name org_name,u.full_name creator
          FROM budgets b JOIN organizations o ON o.id=b.organization_id JOIN users u ON u.id=b.created_by
          WHERE b.status='pending_sg' ORDER BY b.id DESC");
        echo '<section class="panel"><h2>SG approval inbox</h2><p class="subline">These budget proposals reached the optional SG review stage.</p><div class="table-scroll"><table><thead><tr><th>Organization</th><th>Budget</th><th>Submitted by</th><th>Decision</th></tr></thead><tbody>';
        foreach($queue as $q){ echo '<tr><td>'.e($q['org_name']).'</td><td>'.e($q['name']).'</td><td>'.e($q['creator']).'</td><td>';
            if ((int)$q['id']>0 && (int)$q['created_by']!==(int)me()['id']
                && !one('SELECT id FROM budget_approvals WHERE budget_id=? AND actor_id=? LIMIT 1',[$q['id'],me()['id']]))
                decision_form('budget_decision',(int)$q['organization_id'],(int)$q['id']);
            else echo '—'; echo '</td></tr>'; }
        if (!$queue) echo '<tr><td colspan="4" class="empty">No budgets are awaiting SG review.</td></tr>';
        echo '</tbody></table></div></section>';
    }
    if ($org!==null) {
        require_role($org,['president','treasurer','adviser','auditor','officer']);
        if (has_role('treasurer',$org)) {
            echo '<section class="panel"><h2>Propose a budget</h2><p class="subline">Approval route: President → Adviser → SG, only if required.</p>';
            form_start('create_budget',$org,'form-grid');
            echo '<label>Budget name<input name="name" maxlength="180" placeholder="Annual operations budget" required></label>';
            echo '<label>Type<select name="type"><option value="annual">Annual</option><option value="event">Event</option><option value="project">Project</option></select></label>';
            echo '<div class="wide"><div class="liquidation-editor" data-budget-editor hidden><div class="section-title"><h3>Budget categories</h3><span class="editor-count" data-budget-count>1 category</span></div><p class="subline">Add a category for each planned allocation.</p><div data-budget-rows></div><button class="btn small" type="button" data-budget-add>+ Add category</button><div class="editor-summary">Total planned budget <strong data-budget-total>₱0.00</strong></div></div>';
            echo '<label class="manual-lines">Category allocations <small>Manual format: Category|Amount, one per line</small><textarea name="lines" rows="4" data-budget-lines placeholder="Events|15000.00&#10;Supplies|5000.00" required></textarea></label>';
            echo '<template id="budget-row-template"><div class="liquidation-row"><div class="line-heading"><span class="line-number">Category</span><button type="button" class="remove-line" data-budget-remove>Remove</button></div><div class="form-grid"><label>Category name<input data-field="category" maxlength="100" placeholder="e.g. Events" required></label><label>Allocation (PHP)<input data-field="amount" type="number" min="0.01" step="0.01" placeholder="0.00" required></label></div></div></template></div>';
            echo '<div class="wide"><button class="btn primary">Submit for approval</button></div></form></section>';
        }
        $budgetReader=has_role('treasurer',$org)||has_role('president',$org)||has_role('adviser',$org)||has_role('auditor',$org);
        $filter=$budgetReader?'':" AND b.status='approved'";
        $list=rows("SELECT b.*,u.full_name creator,COALESCE(SUM(bl.allocated),0) total FROM budgets b JOIN users u ON u.id=b.created_by
          LEFT JOIN budget_lines bl ON bl.budget_id=b.id WHERE b.organization_id=?".$filter." GROUP BY b.id ORDER BY b.id DESC",[$org]);
        echo '<section class="panel"><h2>Budget proposals</h2><div class="table-scroll"><table><thead><tr><th>Budget</th><th>Type</th><th>Allocation</th><th>Created by</th><th>Status / next stage</th><th>Decision</th></tr></thead><tbody>';
        foreach($list as $b){
            echo '<tr><td><strong>'.e($b['name']).'</strong><small>#'.(int)$b['id'].'</small></td><td>'.e(ucfirst($b['budget_type'])).'</td><td>'.money($b['total']).'</td><td>'.e($b['creator']).'</td><td>'.status_badge($b['status']).'</td><td>';
            $stage=['pending_president'=>'president','pending_adviser'=>'adviser','pending_sg'=>'sg_finance'][$b['status']]??null;
            $may=$stage && (int)$b['created_by']!==(int)me()['id'] && ($stage==='sg_finance'?has_role('sg_finance',1):has_role($stage,$org));
            if ($may && one('SELECT id FROM budget_approvals WHERE budget_id=? AND actor_id=? LIMIT 1',[$b['id'],me()['id']])) $may=false;
            if ($may) decision_form('budget_decision',$org,(int)$b['id']);else echo '<span class="muted">—</span>';
            echo '</td></tr>';
        }
        if (!$list) echo '<tr><td colspan="6" class="empty">No budgets yet.</td></tr>';
        echo '</tbody></table></div></section>';
        $approved=rows("SELECT b.name,bl.category,bl.allocated FROM budgets b JOIN budget_lines bl ON bl.budget_id=b.id WHERE b.organization_id=? AND b.status='approved' ORDER BY b.name,bl.category",[$org]);
        echo '<section class="panel"><h2>Approved allocation lines</h2><div class="table-scroll"><table><thead><tr><th>Budget</th><th>Category</th><th>Approved amount</th></tr></thead><tbody>';
        foreach($approved as $a) echo '<tr><td>'.e($a['name']).'</td><td>'.e($a['category']).'</td><td>'.money($a['allocated']).'</td></tr>';
        if (!$approved) echo '<tr><td colspan="3" class="empty">No approved allocations yet.</td></tr>';
        echo '</tbody></table></div></section>';
    }
    shell_end();
}
function decision_form(string $action,int $org,int $id): void {
    form_start($action,$org,'inline-decision');
    echo '<input type="hidden" name="id" value="'.$id.'"><select name="decision" aria-label="Decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select>';
    echo '<input name="reason" placeholder="Reason if rejected" maxlength="250"><button class="btn small" type="submit">Save</button></form>';
}
function requests_page(): void {
    shell_start('Fund Requests');$org=current_org_for_page();org_tabs($org,'requests');
    if ($org!==null) {
        require_role($org,['officer','treasurer','president','adviser','auditor']);
        $budgets=rows("SELECT id,name FROM budgets WHERE organization_id=? AND status='approved' ORDER BY name",[$org]);
        if (has_role('officer',$org)) {
            echo '<section class="panel"><h2>Submit a fund request</h2><p class="subline">Required reviews: Treasurer → Adviser → President. Approval reserves funds but does not pay the request.</p>';
            form_start('create_request',$org,'form-grid');
            echo '<label>Activity / request title<input name="title" required maxlength="180"></label><label>Activity date<input type="date" name="activity_date" required></label>';
            echo '<label>Approved budget<select name="budget_id" required><option value="">Choose a budget</option>';
            foreach($budgets as $b) echo '<option value="'.(int)$b['id'].'">'.e($b['name']).'</option>';
            echo '</select></label><label>Requested amount (PHP)<input name="amount" inputmode="decimal" pattern="[0-9]+(\\.[0-9]{1,2})?" placeholder="2500.00" required></label>';
            echo '<label class="wide">Purpose and expense breakdown<textarea name="purpose" rows="3" required></textarea></label>';
            echo '<div class="wide"><button class="btn primary" '.(!$budgets?'disabled':'').'>Submit request</button></div></form></section>';
        }
        $isReviewer=has_role('treasurer',$org)||has_role('president',$org)||has_role('adviser',$org)||has_role('auditor',$org);
        $sql="SELECT r.*,u.full_name requester,b.name budget_name,
            EXISTS(SELECT 1 FROM liquidations l WHERE l.fund_request_id=r.id) AS has_liquidation,
            EXISTS(SELECT 1 FROM financial_transactions t WHERE t.fund_request_id=r.id AND t.transaction_type='advance_disbursement') AS has_advance
            FROM fund_requests r JOIN users u ON u.id=r.requested_by JOIN budgets b ON b.id=r.budget_id WHERE r.organization_id=?";
        $bind=[$org];
        if (!$isReviewer) { $sql.=' AND r.requested_by=?'; $bind[]=me()['id']; }
        $list=rows($sql.' ORDER BY r.id DESC',$bind);
        echo '<section class="panel"><h2>Request tracker</h2><p class="subline">The President may close a completed or cancelled direct-pay request to release its unused approval. Requests with advances must complete liquidation instead.</p><div class="table-scroll"><table><thead><tr><th>Reference / title</th><th>Requested by</th><th>Budget</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach($list as $r){
            echo '<tr><td><strong>'.e($r['title']).'</strong><small>'.e($r['reference_no']).'</small></td><td>'.e($r['requester']).'</td><td>'.e($r['budget_name']).'</td><td>'.money($r['amount']).'</td><td>'.status_badge($r['status']).($r['closed_at']?' <small>Closed '.e($r['closed_at']).'</small>':'').'</td><td>';
            $stage=['pending_treasurer'=>'treasurer','pending_adviser'=>'adviser','pending_president'=>'president'][$r['status']]??null;
            if ($stage && has_role($stage,$org) && (int)$r['requested_by']!==(int)me()['id']
                && !one('SELECT id FROM fund_request_approvals WHERE fund_request_id=? AND actor_id=? LIMIT 1',[$r['id'],me()['id']]))
                decision_form('request_decision',$org,(int)$r['id']);
            elseif ($r['status']==='approved' && $r['closed_at']===null && !$r['has_liquidation'] && !$r['has_advance'] && has_role('president',$org)
                && (int)$r['requested_by']!==(int)me()['id']) {
                form_start('close_direct_request',$org,'inline-decision');
                echo '<input type="hidden" name="id" value="'.(int)$r['id'].'">';
                echo '<input name="reason" maxlength="500" required placeholder="Why close / cancel?" aria-label="Reason for closing direct-pay request">';
                echo '<button class="btn small" type="submit">Close direct-pay request</button></form>';
            } else echo '<span class="muted">—</span>';
            echo '</td></tr>';
        }
        if (!$list) echo '<tr><td colspan="6" class="empty">No funding requests yet.</td></tr>';
        echo '</tbody></table></div></section>';
    }
    shell_end();
}
function transactions_page(): void {
    shell_start('Income & Expenses');$org=current_org_for_page();org_tabs($org,'transactions');
    if ($org!==null) {
        require_role($org,['treasurer','president','adviser','auditor']);
        $accounts=rows('SELECT id,name FROM financial_accounts WHERE organization_id=? ORDER BY id',[$org]);
        $lines=rows("SELECT bl.id,CONCAT(b.name,' / ',bl.category,' (',bl.allocated,')') title FROM budget_lines bl JOIN budgets b ON b.id=bl.budget_id
                      WHERE b.organization_id=? AND b.status='approved' ORDER BY b.name,bl.category",[$org]);
        $requests=rows("SELECT id,reference_no,title FROM fund_requests WHERE organization_id=? AND status='approved' AND closed_at IS NULL ORDER BY id DESC",[$org]);
        if (has_role('treasurer',$org)) {
            echo '<section class="panel"><h2>Record income or expense</h2><p class="subline">New entries are pending. An independent Adviser must verify and post them before cash totals change.</p>';
            form_start('create_tx',$org,'form-grid');
            echo '<label>Transaction type<select name="type" id="transaction-type"><option value="income">Income</option><option value="expense">Expense</option></select></label>';
            echo '<label>Financial account<select name="account_id">';foreach($accounts as $a) echo '<option value="'.(int)$a['id'].'">'.e($a['name']).'</option>';echo '</select></label>';
            echo '<label>Amount (PHP)<input name="amount" pattern="[0-9]+(\\.[0-9]{1,2})?" placeholder="1000.00" required></label>';
            echo '<label>Date<input name="occurred_on" type="date" value="'.date('Y-m-d').'" required></label>';
            echo '<label id="category-field">Income source / category<input name="category" placeholder="Membership fee" maxlength="100"></label>';
            echo '<label>Payment method<input name="payment_method" placeholder="Cash / Bank transfer" maxlength="60" required></label>';
            echo '<label class="wide">Description<input name="description" maxlength="255" required></label>';
            echo '<div class="expense-only wide hidden"><div class="form-grid">';
            echo '<label>Approved budget category<select name="budget_line_id"><option value="">Select category</option>';
            foreach($lines as $l) echo '<option value="'.(int)$l['id'].'">'.e($l['title']).'</option>';
            echo '</select></label>';
            echo '<label>Approved fund request (optional)<select name="fund_request_id"><option value="">Not tied to a request</option>';
            foreach($requests as $r) echo '<option value="'.(int)$r['id'].'">'.e($r['reference_no'].' · '.$r['title']).'</option>';
            echo '</select></label>';
            echo '<label class="wide">Receipt / invoice (PDF, JPG, PNG; max 5 MB)<input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></label></div></div>';
            echo '<div class="wide"><button class="btn primary">Save pending entry</button></div></form></section>';
        }
        $list=rows("SELECT t.*,u.full_name creator FROM financial_transactions t JOIN users u ON u.id=t.created_by
                     WHERE t.organization_id=? ORDER BY t.id DESC LIMIT 100",[$org]);
        echo '<section class="panel"><div class="section-title"><h2>Transaction ledger</h2>';
        if (has_role('treasurer',$org)||has_role('adviser',$org)||has_role('president',$org)||has_role('auditor',$org)) echo '<a href="index.php?'.http_build_query(['page'=>'export','org'=>$org]).'">Export CSV ↓</a>';
        echo '</div><p class="subline">Showing the latest 100 records; reports use the underlying organization ledger.</p><div class="table-scroll"><table><thead><tr><th>Date / detail</th><th>Type</th><th>Amount</th><th>Created by</th><th>Status</th><th>Evidence</th><th>Verification</th></tr></thead><tbody>';
        foreach($list as $t){
            echo '<tr><td><strong>'.e($t['description']).'</strong><small>'.e($t['occurred_on'].' · '.$t['category']).'</small></td><td>'.e(ucfirst($t['transaction_type'])).'</td>';
            echo '<td>'.money($t['amount']).'</td><td>'.e($t['creator']).'</td><td>'.status_badge($t['status']).'</td><td>';
            $attachments=rows('SELECT id,original_name FROM transaction_attachments WHERE transaction_id=?',[$t['id']]);
            foreach($attachments as $a) echo '<a href="index.php?page=receipt&id='.(int)$a['id'].'" title="Download validated receipt">Receipt ↓</a> ';
            if (!$attachments) echo '—';echo '</td><td>';
            if ($t['status']==='pending' && has_role('adviser',$org) && (int)$t['created_by']!==(int)me()['id']) {
                form_start('post_tx',$org,'inline-decision');echo '<input type="hidden" name="id" value="'.(int)$t['id'].'"><button class="btn small">Verify & post</button></form>';
            } else echo '<span class="muted">—</span>';
            echo '</td></tr>';
        }
        if (!$list) echo '<tr><td colspan="7" class="empty">No transactions have been entered.</td></tr>';
        echo '</tbody></table></div></section>';
    }
    shell_end();
}
function reports_page(): void {
    shell_start('Financial Reports');$org=current_org_for_page();org_tabs($org,'reports');
    if ($org!==null) {
        require_role($org,['treasurer','president','adviser','auditor']);
        $s=cash_summary($org);
        echo '<p class="lede">Cash figures below include posted transactions only, except the separate approved-commitment figure.</p><div class="stats">';
        amount_card('Posted income',$s['income'],'↗');amount_card('Paid expenses',$s['expense'],'↘');
        amount_card('Unpaid commitments',$s['commitments'],'◷');amount_card('Available funds',$s['available'],'◈');echo '</div>';
        $budgets=rows("SELECT b.id,b.name,COALESCE(SUM(bl.allocated),0) allocation FROM budgets b LEFT JOIN budget_lines bl ON bl.budget_id=b.id
               WHERE b.organization_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.name",[$org]);
        echo '<section class="panel"><h2>Budget utilization</h2><div class="table-scroll"><table><thead><tr><th>Budget</th><th>Approved allocation</th><th>Posted expense</th><th>Remaining</th><th>Used</th></tr></thead><tbody>';
        foreach($budgets as $b){
            $used=cents_db($b['allocation'])-budget_left((int)$b['id']);
            $total=cents_db($b['allocation']);$pct=$total>0?round($used/$total*100,1):0;
            echo '<tr><td>'.e($b['name']).'</td><td>'.money($b['allocation']).'</td><td>'.money(peso_str($used)).'</td><td>'.money(peso_str($total-$used)).'</td><td>'.e($pct).'%</td></tr>';
        }
        if (!$budgets) echo '<tr><td colspan="5" class="empty">No approved budgets.</td></tr>';
        echo '</tbody></table></div></section>';
        if (has_role('treasurer',$org)||has_role('president',$org)||has_role('adviser',$org)||has_role('auditor',$org)) echo '<p><a class="btn primary" href="index.php?page=export&org='.$org.'">Download transaction report (CSV)</a></p>';
        echo '<p class="hint">PDF reports, Excel formatting, advanced filters, and finalized liquidation reports are scheduled for a later release.</p>';
    }
    shell_end();
}
function audit_page(): void {
    shell_start('Audit Trail');$org=current_org_for_page();org_tabs($org,'audit');
    if ($org!==null) {
        require_role($org,['president','treasurer','adviser','auditor']);
        $events=rows("SELECT l.*,u.full_name FROM audit_logs l LEFT JOIN users u ON u.id=l.actor_id WHERE l.organization_id=? ORDER BY l.id DESC LIMIT 100",[$org]);
        echo '<section class="panel"><h2>Latest recorded actions</h2><div class="table-scroll"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>';
        foreach($events as $a) echo '<tr><td>'.e($a['created_at']).'</td><td>'.e($a['full_name']??'System').'</td><td>'.e($a['action']).'</td><td>'.e($a['entity_type'].' #'.$a['entity_id']).'</td><td>'.e($a['details']).'</td></tr>';
        if (!$events) echo '<tr><td colspan="5" class="empty">No audit events.</td></tr>';
        echo '</tbody></table></div></section>';
    }
    shell_end();
}
function users_page(): void {
    if (!has_role('system_admin',1)) abort(403,'Administrator only.');
    shell_start('User Management');
    echo '<section class="panel"><h2>Create user or assign an additional role</h2><p class="subline">Use an existing email to add a role without resetting that account password. Every user is authorized separately per organization.</p>';
    form_start('create_user',null,'form-grid');
    echo '<label>Full name<input name="name" maxlength="150" required></label><label>Email<input name="email" type="email" required></label>';
    echo '<label>Initial password (12+ characters)<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>';
    echo '<label>Confirm initial password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label>';
    echo '<label>Organization<select name="role_org" required>';
    foreach(rows('SELECT id,name FROM organizations WHERE active=1 ORDER BY id') as $o) echo '<option value="'.(int)$o['id'].'">'.e($o['name']).'</option>';
    echo '</select></label><label>Role<select name="role">';
    foreach(['system_admin'=>'System Administrator','sg_finance'=>'SG Financial Administrator','president'=>'Organization President','treasurer'=>'Organization Treasurer','adviser'=>'Organization Adviser','authorized_financial_user'=>'Authorized Financial User (restricted)','officer'=>'Organization Officer','member'=>'Organization Member','auditor'=>'Auditor / Read-Only Reviewer'] as $r=>$title)
       echo '<option value="'.e($r).'">'.e($title).'</option>';
    echo '</select></label><div class="wide"><button class="btn primary">Save user / assignment</button></div></form></section>';
    echo '<section class="panel"><h2>SG budget review policy</h2><p class="subline">By default, SG budget approval is optional. Enable it only where campus policy requires it.</p>';
    $policies=rows('SELECT o.id,o.name,COALESCE(s.require_sg_budget_approval,0) required FROM organizations o LEFT JOIN organization_settings s ON s.organization_id=o.id WHERE o.is_sg=0 ORDER BY o.id');
    foreach($policies as $p) { form_start('set_sg_budget_policy',null,'inline-decision');
        echo '<input type="hidden" name="target_org" value="'.(int)$p['id'].'"><strong>'.e($p['name']).'</strong> <select name="required"><option value="0"'.(!(int)$p['required']?' selected':'').'>Not required</option><option value="1"'.((int)$p['required']?' selected':'').'>Required</option></select><button class="btn small">Save</button></form>'; }
    echo '</section>';
    echo '<section class="panel"><h2>Liquidation deadlines</h2><p class="subline">Default is 14 calendar days after a verified advance. Confirm the number of days with campus policy before actual use.</p>';
    foreach(rows('SELECT o.id,o.name,COALESCE(s.liquidation_due_days,14) days FROM organizations o LEFT JOIN organization_settings s ON s.organization_id=o.id WHERE o.is_sg=0 ORDER BY o.id') as $p){
        form_start('set_liquidation_policy',null,'inline-decision');
        echo '<input type="hidden" name="target_org" value="'.(int)$p['id'].'"><strong>'.e($p['name']).'</strong><input type="number" name="due_days" min="1" max="180" value="'.(int)$p['days'].'" aria-label="Days to liquidate"><button class="btn small">Save</button></form>';
    }
    echo '</section>';
    $people=rows('SELECT u.full_name,u.email,o.name org,r.role FROM organization_user_roles r JOIN users u ON u.id=r.user_id JOIN organizations o ON o.id=r.organization_id WHERE r.active=1 ORDER BY o.id,r.role,u.full_name');
    echo '<section class="panel"><h2>Active role assignments</h2><div class="table-scroll"><table><thead><tr><th>User</th><th>Email</th><th>Organization</th><th>Role</th></tr></thead><tbody>';
    foreach($people as $p) echo '<tr><td>'.e($p['full_name']).'</td><td>'.e($p['email']).'</td><td>'.e($p['org']).'</td><td>'.e(str_replace('_',' ',ucwords($p['role'],'_'))).'</td></tr>';
    echo '</tbody></table></div></section>';
    shell_end();
}

function account_page(): void {
    shell_start('My Account');
    echo '<section class="panel"><h2>Account details</h2>';
    echo '<p><strong>Name:</strong> '.e(me()['name']).'</p>';
    echo '<p><strong>Email:</strong> '.e(me()['email']).'</p>';
    $assignments=my_roles();
    echo '<p><strong>Assigned roles:</strong> ';
    echo $assignments ? e(implode(' · ',array_map(fn($r)=>$r['organization_name'].' - '.str_replace('_',' ',$r['role']),$assignments))) : 'None';
    echo '</p></section>';
    echo '<section class="panel"><h2>Change password</h2><p class="subline">Change your temporary password before using this account.</p>';
    form_start('change_password',null,'stacked');
    echo '<label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>';
    echo '<label>New password (12 or more characters)<input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label>';
    echo '<label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label>';
    echo '<button class="btn primary" type="submit">Update password</button></form></section>';
    shell_end();
}
