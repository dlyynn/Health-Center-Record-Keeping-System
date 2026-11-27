<?php require 'config.php';require 'layout.php';require_resident();auto_notify_daily();
$ids=my_patient_ids();$rows=[];
if($ids){$in=implode(',',$ids);$rows=$pdo->query("SELECT n.*,p.full_name FROM notifications n JOIN patients p ON p.id=n.patient_id WHERE n.patient_id IN($in) ORDER BY n.id DESC LIMIT 50")->fetchAll();$pdo->query("UPDATE notifications SET is_read=1 WHERE patient_id IN($in)");}
head('Notifications');?>
<h4>Notices</h4><div class="list-group">
<?php foreach($rows as $r){?><div class="list-group-item <?=$r['is_read']?'':'list-group-item-warning'?>"><div><?=h($r['message'])?></div><small class="text-muted"><?=h($r['full_name'])?> · <?=h($r['created_at'])?></small></div><?php }
if(!$rows)echo '<div class="list-group-item text-muted">No notices yet.</div>';?></div>
<?php foot();
