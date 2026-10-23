<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/release2.php';
require_once dirname(__DIR__).'/app/report_service.php';
require_once dirname(__DIR__).'/app/actions.php';
require_once dirname(__DIR__).'/app/views.php';
require_once dirname(__DIR__).'/app/release2_views.php';
try {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        $act=(string)($_POST['action']??$_GET['action']??'');
        if (in_array($act,['set_liquidation_policy','record_advance','record_return','liquidation_submit','liquidation_decide','notification_read','calendar_save','calendar_complete'],true)) r2_handle_action($act);
        handle_action($act);
    }
    $page=(string)($_GET['page']??'dashboard');
    if ($page==='login' && !me()) {login_page();exit;}
    login_required();
    switch ($page) {
        case 'dashboard': dashboard_page();break;
        case 'budgets': budgets_page();break;
        case 'requests': requests_page();break;
        case 'transactions': transactions_page();break;
        case 'reports': r2_reports_page();break;
        case 'audit': r2_audit_page();break;
        case 'users': users_page();break;
        case 'account': account_page();break;
        case 'liquidations': liquidation_page();break;
        case 'calendar': calendar_page();break;
        case 'notifications': notifications_page();break;
        case 'liquidation_evidence': login_required();r2_attachment((int)($_GET['id']??0),selected_org());break;
        case 'report_download': r2_export_report();break;
        case 'receipt': serve_receipt((int)($_GET['id']??0));break;
        case 'export': export_transactions();break;
        default: abort(404,'Page not found.');
    }
} catch(Throwable $e) {
    $code=(int)$e->getCode();if ($code<400 || $code>599) $code=500;
    http_response_code($code);
    $message=$code===500?'An internal error occurred. Check PHP error logs.':$e->getMessage();
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="assets/css/style.css"><title>LSL Financial Portal | Error</title><body class="auth-page"><div class="error-box">';
    echo '<span class="eyebrow">LSL FINANCIAL PORTAL</span><h1>Request could not be completed</h1><p>'.e($message).'</p>';
    echo '<p><a class="btn primary" href="index.php">Return to dashboard</a></p></div></body></html>';
    error_log((string)$e);
}
