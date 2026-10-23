<?php
// Run via Windows Task Scheduler once daily: C:\xampp\php\php.exe scripts\cron_deadlines.php
// CLI only. Generates scoped, deduplicated, in-app notifications. No email is sent.
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/app/release2.php';
try {
    $lock=one("SELECT GET_LOCK('campusfunds_r2_deadline_job',0) AS got_lock");
    if(!$lock || (int)$lock['got_lock']!==1){fwrite(STDERR,"Another deadline job is running.\n");exit(2);}
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));
    $events=0;
    $cases=rows("SELECT l.id,l.due_on,l.status,r.organization_id,r.requested_by,r.reference_no FROM liquidations l JOIN fund_requests r ON r.id=l.fund_request_id WHERE l.due_on IS NOT NULL AND l.status NOT IN ('closed','rejected')");
    foreach($cases as $case){
        $date=new DateTimeImmutable($case['due_on'],new DateTimeZone('Asia/Manila'));
        $days=(int)$today->diff($date)->format('%r%a');
        if(!in_array($days,[7,3,1,0],true) && $days>=0)continue;
        $text=$days<0?'OVERDUE by '.abs($days).' day(s)':($days===0?'due today':'due in '.$days.' day(s)');
        $key='liq-deadline:'.$case['id'].':'.$today->format('Ymd');
        r2_notify((int)$case['requested_by'],(int)$case['organization_id'],$key.':requester','Liquidation '.$case['reference_no'].' is '.$text,'liquidations');$events++;
        r2_notify_roles((int)$case['organization_id'],['treasurer','adviser','president'],$key.':finance',
            'Liquidation '.$case['reference_no'].' is '.$text,'liquidations');
    }
    $manual=rows("SELECT id,organization_id,title,due_on FROM financial_calendar_items WHERE item_type='manual' AND completed_at IS NULL");
    foreach($manual as $task){
        $date=new DateTimeImmutable($task['due_on'],new DateTimeZone('Asia/Manila'));
        $days=(int)$today->diff($date)->format('%r%a');
        if(!in_array($days,[7,3,1,0],true)&&$days>=0)continue;
        r2_notify_roles((int)$task['organization_id'],['treasurer'],'calendar-deadline:'.$task['id'].':'.$today->format('Ymd'),
            'Financial deadline: '.$task['title'].' ('.$task['due_on'].')','calendar');$events++;
    }
    one("SELECT RELEASE_LOCK('campusfunds_r2_deadline_job') unlocked");
    fwrite(STDOUT,"LSL Financial Portal deadline scan completed: {$events} events inspected.\n");
}catch(Throwable $e){fwrite(STDERR,"Deadline job failed: ".$e->getMessage()."\n");exit(1);}
