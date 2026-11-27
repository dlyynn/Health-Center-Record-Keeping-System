<?php require 'config.php';require 'layout.php';require_staff();
$q=trim($_GET['q']??'');$st=$_GET['s']??'';
$rows=$pdo->prepare("SELECT u.*,
 (SELECT GROUP_CONCAT(p.full_name SEPARATOR ', ') FROM user_patients up JOIN patients p ON p.id=up.patient_id WHERE up.user_id=u.id) pts,
 (SELECT status FROM resident_ids r WHERE r.user_id=u.id ORDER BY r.id DESC LIMIT 1) idst,
 (SELECT COUNT(*) FROM appointments a JOIN user_patients up ON up.patient_id=a.patient_id WHERE up.user_id=u.id AND a.status IN('Requested','Confirmed')) appts
 FROM users u WHERE u.role='resident' AND (u.full_name LIKE ? OR u.username LIKE ?) ORDER BY FIELD(u.status,'pending','active','rejected'),u.full_name");
$rows->execute(["%$q%","%$q%"]);
$rows=array_filter($rows->fetchAll(),fn($r)=>($st===''||$r['status']===$st)&&staff_sees_resident($r['id']));
$SB=['active'=>'success','pending'=>'warning text-dark','rejected'=>'danger'];$IB=['Pending'=>'warning text-dark','Verified'=>'success','Rejected'=>'danger'];
head('Residents');?>
<h4>👪 Residents (accounts)</h4>
<form class="row g-3 mb-3"><div class="col-md-5"><input name="q" class="form-control" placeholder="Search name or username..." value="<?=h($q)?>"></div>
<div class="col-md-4"><select name="s" class="form-select" onchange="this.form.submit()"><?php foreach([''=>'All statuses','pending'=>'Awaiting approval','active'=>'Active','rejected'=>'Rejected'] as $k=>$v)echo "<option value='$k' ".($st===$k?'selected':'').">$v</option>";?></select></div>
<div class="col-md-3"><button class="btn btn-success w-100">Search</button></div></form>
<table class="table table-sm bg-white"><tr><th>Name</th><th>Username</th><th>Account</th><th>ID</th><th>Linked patients</th><th>Appointments</th><th></th></tr>
<?php foreach($rows as $r){?><tr><td><b><?=h($r['full_name'])?></b></td><td><?=h($r['username'])?></td>
<td><span class="badge bg-<?=$SB[$r['status']]??'secondary'?>"><?=h($r['status'])?></span></td>
<td><?=$r['idst']?'<span class="badge bg-'.$IB[$r['idst']].'">'.h($r['idst']).'</span>':'<span class="text-muted">none</span>'?></td>
<td><?=h($r['pts'])?></td><td><?=(int)$r['appts']?></td><td><a class="btn btn-sm btn-success" href="resident.php?id=<?=$r['id']?>">Open</a></td></tr><?php }
if(!$rows)echo '<tr><td colspan="7" class="text-muted">Nothing found.</td></tr>';?></table>
<?php foot();
