<?php require 'config.php';require 'layout.php';require_staff();
$id=(int)($_GET['id']??0);$s=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='resident'");$s->execute([$id]);$ru=$s->fetch();
if(!$ru||!staff_sees_resident($id))die('Not found.');
$mgr=can_manage_residents();$msg='';$bad=false;
if($_SERVER['REQUEST_METHOD']=='POST'&&$mgr){$a=$_POST['a']??'';
 try{
  if($a=='account'){$stt=$_POST['status']??'';$un=trim($_POST['username']);
   if(!in_array($stt,['active','pending','rejected'],true))throw new Exception('Invalid status.');
   if(!preg_match('/^[A-Za-z0-9_]{4,30}$/',$un))throw new Exception('Username: 4-30 letters, numbers, or _ only.');
   if(trim($_POST['full_name'])==='')throw new Exception('Please enter the name.');
   $pdo->prepare("UPDATE users SET full_name=?,username=?,status=? WHERE id=?")->execute([trim($_POST['full_name']),$un,$stt,$id]);audit("Edited resident account #$id");$msg='Account saved.';}
  if($a=='resetpw'){if(strlen($_POST['password'])<8)throw new Exception('Password: 8+ characters.');
   $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$id]);audit("Reset resident password #$id");$msg='Password changed.';}
  if($a=='idstatus'){$stt=$_POST['status']??'';$rid=(int)$_POST['rid'];
   if(!in_array($stt,['Pending','Verified','Rejected'],true))throw new Exception('Invalid status.');
   $pdo->prepare("UPDATE resident_ids SET status=?,remarks=? WHERE id=? AND user_id=?")->execute([$stt,trim($_POST['remarks'])?:null,$rid,$id]);audit("ID #$rid marked $stt (resident #$id)");$msg="ID marked as $stt.";
   if($stt=='Verified'){if($ru['status']=='pending'){$pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);$msg.=' The account has also been approved.';}
    foreach($pdo->query("SELECT patient_id FROM user_patients WHERE user_id=".(int)$id)->fetchAll(PDO::FETCH_COLUMN) as $pn)notify($pn,"Your account ID has been verified. Thank you!");}}
  if($a=='link'){$pdo->prepare("INSERT IGNORE INTO user_patients VALUES(?,?)")->execute([$id,(int)$_POST['patient_id']]);audit("Linked patient to resident #$id");$msg='Patient linked.';}
  if($a=='unlink'){$pdo->prepare("DELETE FROM user_patients WHERE user_id=? AND patient_id=?")->execute([$id,(int)$_POST['patient_id']]);audit("Unlinked patient from resident #$id");$msg='Link removed.';}
 }catch(Exception $e){$bad=true;$msg=$e instanceof PDOException?'That username already exists.':$e->getMessage();}
 $s->execute([$id]);$ru=$s->fetch();}
$l=$pdo->prepare("SELECT p.* FROM user_patients up JOIN patients p ON p.id=up.patient_id WHERE up.user_id=? ORDER BY p.full_name");$l->execute([$id]);$links=$l->fetchAll();
$ri=$pdo->prepare("SELECT * FROM resident_ids WHERE user_id=? ORDER BY id DESC");$ri->execute([$id]);$myids=$ri->fetchAll();
$ap=$pdo->prepare("SELECT a.*,p.full_name pn FROM appointments a JOIN user_patients up ON up.patient_id=a.patient_id JOIN patients p ON p.id=a.patient_id WHERE up.user_id=? ORDER BY a.appt_date DESC LIMIT 30");$ap->execute([$id]);$apps=$ap->fetchAll();
$allp=$mgr?$pdo->query("SELECT id,full_name FROM patients ORDER BY full_name")->fetchAll():[];
$IB=['Pending'=>'warning text-dark','Verified'=>'success','Rejected'=>'danger'];
head($ru['full_name']);?>
<p><a href="residents.php">← Back to the residents list</a></p>
<h4>👤 <?=h($ru['full_name'])?></h4>
<?php if($msg)echo "<div class='alert alert-".($bad?'danger':'info')."'>".h($msg)."</div>";?>
<div class="card mb-4"><div class="card-header">Account</div><form method="post" class="card-body row g-3"><input type="hidden" name="a" value="account">
<div class="col-md-4"><label class="form-label">Full name</label><input name="full_name" class="form-control" value="<?=h($ru['full_name'])?>" <?=$mgr?'':'disabled'?>></div>
<div class="col-md-3"><label class="form-label">Username</label><input name="username" class="form-control" value="<?=h($ru['username'])?>" <?=$mgr?'':'disabled'?>></div>
<div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select" <?=$mgr?'':'disabled'?>><?php foreach(['pending'=>'Pending','active'=>'Active','rejected'=>'Rejected'] as $k=>$v)echo "<option value='$k' ".($ru['status']===$k?'selected':'').">$v</option>";?></select></div>
<?php if($mgr){?><div class="col-md-2 align-self-end"><button class="btn btn-success w-100">Save</button></div><?php }?>
<div class="col-12 text-muted">Registered: <?=h($ru['created_at'])?></div></form>
<?php if($mgr){?><form method="post" class="card-body row g-3 border-top"><input type="hidden" name="a" value="resetpw"><div class="col-md-5"><label class="form-label">Reset password</label><input name="password" class="form-control" placeholder="New password (8+)" required></div><div class="col-md-3 align-self-end"><button class="btn btn-outline-danger w-100">Reset</button></div></form><?php }?></div>

<div class="card mb-4"><div class="card-header">🪪 Uploaded IDs</div><div class="card-body">
<?php foreach($myids as $r){$fe=strtolower(pathinfo($r['file_front'],PATHINFO_EXTENSION));$be=$r['file_back']?strtolower(pathinfo($r['file_back'],PATHINFO_EXTENSION)):'';?>
<div class="border-bottom pb-3 mb-3"><b><?=h($r['id_type'])?></b> <span class="badge bg-<?=$IB[$r['status']]?>"><?=h($r['status'])?></span> <small class="text-muted"><?=h($r['uploaded_at'])?></small>
<div class="d-flex flex-wrap gap-3 my-2">
<?php foreach([['f',$fe,'Front'],['b',$be,'Back']] as [$sd,$ex,$lb]){if(!$ex)continue;?><div><div class="small text-muted"><?=$lb?></div>
<?php if($ex=='pdf'){?><a class="btn btn-outline-secondary" target="_blank" href="view_id.php?id=<?=$r['id']?>&s=<?=$sd?>">Open PDF</a><?php }else{?><a target="_blank" href="view_id.php?id=<?=$r['id']?>&s=<?=$sd?>"><img src="view_id.php?id=<?=$r['id']?>&s=<?=$sd?>" alt="ID" class="img-fluid border rounded" style="max-height:260px"></a><?php }?></div><?php }?></div>
<?php if($r['remarks'])echo '<div class="text-danger mb-2">Remarks: '.h($r['remarks']).'</div>';
if($mgr){?><form method="post" class="row g-2"><input type="hidden" name="a" value="idstatus"><input type="hidden" name="rid" value="<?=$r['id']?>">
<div class="col-md-3"><select name="status" class="form-select"><?php foreach(array_keys($IB) as $x)echo "<option ".($r['status']===$x?'selected':'').">$x</option>";?></select></div>
<div class="col-md-6"><input name="remarks" class="form-control" placeholder="Remarks (e.g. photo is blurry)" value="<?=h($r['remarks'])?>"></div>
<div class="col-md-3"><button class="btn btn-success w-100">Save status</button></div></form><?php }?></div><?php }
if(!$myids)echo '<p class="text-muted mb-0">No ID uploaded yet.</p>';?></div></div>

<div class="card mb-4"><div class="card-header">🩺 Linked patients</div><div class="card-body">
<table class="table table-sm"><tr><th>Name</th><th>Purok</th><th>Contact</th><th>Address</th><th></th></tr>
<?php foreach($links as $p){?><tr><td><?=h($p['full_name'])?></td><td><?=h($p['purok'])?></td><td><?=h($p['contact'])?> <?=$p['contact_locked']?'🔒':''?></td><td><?=h($p['address'])?> <?=$p['address_locked']?'🔒':''?></td>
<td class="d-flex gap-1"><a class="btn btn-sm btn-outline-success" href="patient.php?id=<?=$p['id']?>">Record</a><a class="btn btn-sm btn-success" href="patients.php?edit=<?=$p['id']?>">Edit information</a>
<?php if($mgr){?><form method="post"><input type="hidden" name="a" value="unlink"><input type="hidden" name="patient_id" value="<?=$p['id']?>"><button class="btn btn-sm btn-outline-danger">Remove link</button></form><?php }?></td></tr><?php }
if(!$links)echo '<tr><td colspan="5" class="text-muted">Nothing linked yet.</td></tr>';?></table>
<div class="small text-muted mb-2">🔒 = edited by staff; the resident can no longer change it.</div>
<?php if($mgr){?><form method="post" class="row g-2"><input type="hidden" name="a" value="link"><div class="col-md-8"><select name="patient_id" class="form-select"><?php foreach($allp as $x)echo "<option value='{$x['id']}'>".h($x['full_name'])."</option>";?></select></div><div class="col-md-4"><button class="btn btn-outline-success w-100">Link patient</button></div></form><?php }?></div></div>

<div class="card mb-4"><div class="card-header">📅 Appointments</div><div class="card-body"><table class="table table-sm"><tr><th>Date</th><th>Patient</th><th>Purpose</th><th>Reason / details</th><th>Status</th></tr>
<?php foreach($apps as $r){?><tr><td><?=h($r['appt_date'])?></td><td><?=h($r['pn'])?></td><td><?=h($r['purpose'])?></td><td><?=h($r['notes'])?></td><td><?=h($r['status'])?></td></tr><?php }
if(!$apps)echo '<tr><td colspan="5" class="text-muted">No appointments yet.</td></tr>';?></table>
<a href="appointments.php" class="btn btn-outline-success">Go to Appointments (confirm / assign)</a></div></div>
<?php foot();
