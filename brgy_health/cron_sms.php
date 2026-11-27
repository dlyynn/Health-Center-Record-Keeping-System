<?php
// Run daily (Windows Task Scheduler / cron): php C:\xampp\htdocs\brgy_health\cron_sms.php
if(php_sapi_name()!=='cli')die('CLI only.');
require __DIR__.'/config.php';
auto_notify();
[$s,$f]=send_pending_sms(200);
echo date('Y-m-d H:i')." SMS sent: $s, failed: $f\n";
