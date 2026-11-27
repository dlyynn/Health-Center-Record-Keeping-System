<?php require 'config.php';require 'layout.php';require_admin();
$u=(int)($_GET['u']??0);$d=$_GET['d']??'';$sql="SELECT a.*,us.username,us.role,p.full_name FROM audit_log a LEFT JOIN users us ON us.id=a.user_id LEFT JOIN patients p ON p.id=a.patient_id WHERE 1=1";$args=[];
if($u){$sql.=" AND a.user_id=?";$args[]=$u;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)){$sql.=" AND DATE(a.created_at)=?";$args[]=$d;}
$s=$pdo->prepare($sql." ORDER BY a.id DESC LIMIT 200");$s->execute($args);$rows=$s->fetchAll();
$users=$pdo->query("SELECT id,username FROM users ORDER BY username")->fetchAll();
head('Audit Log');?>
<h4>Audit Log <small class="text-muted fs-6">who did what and when (latest 200)</small></h4>
<form class="row g-2 mb-3"><div class="col-md-3"><select name="u" class="form-select"><option value="">All users</option><?php foreach($users as $x)echo "<option value='{$x['id']}' ".($u==$x['id']?'selected':'').">".h($x['username'])."</option>";?></select></div>
<div class="col-md-3"><input type="date" name="d" value="<?=h($d)?>" class="form-control"></div><div class="col-md-2"><button class="btn btn-success w-100">Filter</button></div></form>
<table class="table table-sm bg-white"><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Patient</th></tr>
<?php foreach($rows as $r){?><tr><td><?=h($r['created_at'])?></td><td><?=$r['username']?h($r['username']).' <small class="text-muted">('.h($r['role']).')</small>':'<span class="text-muted">— (not logged in)</span>'?></td><td><?=h($r['action'])?></td><td><?=h($r['full_name'])?></td></tr><?php }
if(!$rows)echo '<tr><td colspan="4" class="text-muted">No records.</td></tr>';?></table>
<?php foot();
