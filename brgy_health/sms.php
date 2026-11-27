<?php require 'config.php';require 'layout.php';require_admin();$m='';
if($_SERVER['REQUEST_METHOD']=='POST'){
 if($_POST['act']=='test'){[$ok,$i]=sms_send($_POST['number'],'Test from the BRGY. Health Center system.');$m=$ok?'Test SMS sent.':'Not sent: '.$i;}
 if($_POST['act']=='flush'){[$s,$f]=send_pending_sms(50);$m="Sent: $s · Failed: $f";}
}
$st=$pdo->query("SELECT sms_status,COUNT(*) c FROM notifications GROUP BY sms_status")->fetchAll(PDO::FETCH_KEY_PAIR);
$rows=$pdo->query("SELECT n.*,p.full_name,p.contact FROM notifications n JOIN patients p ON p.id=n.patient_id WHERE n.sms_status<>'none' ORDER BY n.id DESC LIMIT 30")->fetchAll();
head('SMS');if($m)echo "<div class='alert alert-info'>".h($m)."</div>";?>
<h4>SMS Notifications</h4>
<?php if(!SMS_ENABLED)echo '<div class="alert alert-warning">SMS is OFF. Enter the API key and set SMS_ENABLED to <code>true</code> in <code>sms_config.php</code>.</div>';?>
<div class="row g-3 mb-3"><?php foreach(['pending'=>'Pending','sent'=>'Sent','failed'=>'Failed'] as $k=>$v){?><div class="col-4"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold"><?=(int)($st[$k]??0)?></div><?=$v?></div></div></div><?php }?></div>
<div class="row g-3 mb-3"><div class="col-md-6"><form method="post" class="card card-body"><input type="hidden" name="act" value="flush"><button class="btn btn-success">Send pending messages now (up to 50)</button></form></div>
<div class="col-md-6"><form method="post" class="card card-body d-flex flex-row gap-2"><input type="hidden" name="act" value="test"><input name="number" class="form-control" placeholder="09XXXXXXXXX" required><button class="btn btn-outline-success">Test SMS</button></form></div></div>
<table class="table table-sm bg-white"><tr><th>Date</th><th>Patient</th><th>Number</th><th>Message</th><th>Status</th></tr>
<?php foreach($rows as $r){?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['full_name'])?></td><td><?=h($r['contact'])?></td><td><?=h($r['message'])?></td><td><?=h($r['sms_status'])?><?=$r['sms_error']?' <small class="text-danger">'.h($r['sms_error']).'</small>':''?></td></tr><?php }?></table>
<?php foot();
