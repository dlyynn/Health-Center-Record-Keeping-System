<?php require 'config.php';require 'layout.php';require_staff();auto_notify_daily();
if($_SERVER['REQUEST_METHOD']=='POST'){$s=$pdo->prepare("SELECT a.*,p.purok FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.id=?");$s->execute([(int)$_POST['id']]);$r=$s->fetch();
 if($r&&can_view(['id'=>$r['patient_id'],'purok'=>$r['purok']])){$act=$_POST['act'];$date=$_POST['date']?:$r['appt_date'];
  if($act=='confirm'){$sid=(int)($_POST['staff']?:me()['id']);$q=$pdo->prepare("SELECT full_name FROM users WHERE id=? AND role<>'resident'");$q->execute([$sid]);$nm=$q->fetchColumn();if(!$nm){$sid=me()['id'];$nm=null;}
   $pdo->prepare("UPDATE appointments SET status='Confirmed',appt_date=?,assigned_to=? WHERE id=?")->execute([$date,$sid,$r['id']]);
   notify($r['patient_id'],"Your appointment is confirmed ($date): {$r['purpose']}.".($nm?" Attending: $nm.":''));}
  if($act=='cancel'){$pdo->prepare("UPDATE appointments SET status='Cancelled' WHERE id=?")->execute([$r['id']]);notify($r['patient_id'],"Your appointment on {$r['appt_date']} was cancelled. Please request again if needed.");}
  if($act=='done')$pdo->prepare("UPDATE appointments SET status='Done' WHERE id=?")->execute([$r['id']]);
  audit("Appointment $act",$r['patient_id']);}
 header('Location: appointments.php');exit;}
$staff=staff_list();
$rows=array_filter($pdo->query("SELECT a.*,p.full_name,p.purok,u.full_name an,u.role ar FROM appointments a JOIN patients p ON p.id=a.patient_id LEFT JOIN users u ON u.id=a.assigned_to WHERE a.status IN('Requested','Confirmed') ORDER BY a.appt_date")->fetchAll(),fn($r)=>can_view(['id'=>$r['patient_id'],'purok'=>$r['purok']]));
head('Appointments');?>
<h4>Appointments</h4><table class="table table-sm bg-white"><tr><th>Patient</th><th>Purok</th><th>Date</th><th>Purpose / Reason</th><th>Status</th><th>Assigned to</th><th></th></tr>
<?php foreach($rows as $r){?><tr><td><?=h($r['full_name'])?></td><td><?=h($r['purok'])?></td><td><?=h($r['appt_date'])?></td><td><b><?=h($r['purpose'])?></b><?=$r['notes']?'<br><small class="text-muted">'.h($r['notes']).'</small>':''?></td><td><?=h($r['status'])?></td><td><?=who($r['an'],$r['ar'])?></td><td>
<form method="post" class="d-flex gap-1"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="date" name="date" class="form-control form-control-sm" value="<?=h($r['appt_date'])?>">
<select name="staff" class="form-select form-select-sm"><?php foreach($staff as $x)echo "<option value='{$x['id']}' ".($x['id']==($r['assigned_to']?:me()['id'])?'selected':'').">".h($x['full_name'].' ('.ucfirst($x['role']).')')."</option>";?></select>
<button name="act" value="confirm" class="btn btn-sm btn-success">Confirm</button><button name="act" value="done" class="btn btn-sm btn-primary">Done</button><button name="act" value="cancel" class="btn btn-sm btn-outline-danger">Cancel</button></form></td></tr><?php }
if(!$rows)echo '<tr><td colspan="7" class="text-muted">Nothing pending.</td></tr>';?></table>
<?php foot();
