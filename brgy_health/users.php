<?php require 'config.php';require 'layout.php';require_admin();$m='';
$ROLES=['resident'=>'Resident','bhw'=>'BHW','nurse'=>'Nurse','midwife'=>'Midwife','doctor'=>'Doctor','admin'=>'Admin'];
if($_SERVER['REQUEST_METHOD']=='POST'){
 if($_POST['a']=='create'){
  try{if(strlen($_POST['password'])<8)throw new Exception('Password: 8+ characters.');
   if(trim($_POST['full_name'])==='')throw new Exception('Please enter the full name.');
   if(!isset($ROLES[$_POST['role']]))throw new Exception('Invalid role.');
   $pdo->prepare("INSERT INTO users(username,password_hash,full_name,role,license_no,purok) VALUES(?,?,?,?,?,?)")->execute([trim($_POST['username']),password_hash($_POST['password'],PASSWORD_DEFAULT),trim($_POST['full_name']),$_POST['role'],trim($_POST['license_no'])?:null,trim($_POST['purok'])?:null]);
   $uid=$pdo->lastInsertId();if($_POST['role']=='resident'&&$_POST['patient_id'])$pdo->prepare("INSERT IGNORE INTO user_patients VALUES(?,?)")->execute([$uid,(int)$_POST['patient_id']]);
   audit('User created: '.trim($_POST['username']));$m='Account created.';
  }catch(Exception $e){$m=$e instanceof PDOException?'That username already exists.':$e->getMessage();}}
 if($_POST['a']=='link'){$pdo->prepare("INSERT IGNORE INTO user_patients VALUES(?,?)")->execute([(int)$_POST['user_id'],(int)$_POST['patient_id']]);$m='Linked.';}
 if(in_array($_POST['a'],['approve','reject'])){$pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='resident' AND status='pending'")->execute([$_POST['a']=='approve'?'active':'rejected',(int)$_POST['user_id']]);audit(ucfirst($_POST['a']).' registration #'.(int)$_POST['user_id']);$m=$_POST['a']=='approve'?'Account approved.':'Registration rejected.';}
}
$pts=$pdo->query("SELECT id,full_name FROM patients ORDER BY full_name")->fetchAll();
$us=$pdo->query("SELECT u.*,GROUP_CONCAT(p.full_name SEPARATOR ', ') pts FROM users u LEFT JOIN user_patients up ON up.user_id=u.id LEFT JOIN patients p ON p.id=up.patient_id GROUP BY u.id ORDER BY u.role,u.full_name")->fetchAll();
$pend=$pdo->query("SELECT u.*,GROUP_CONCAT(p.full_name SEPARATOR ', ') pts FROM users u LEFT JOIN user_patients up ON up.user_id=u.id LEFT JOIN patients p ON p.id=up.patient_id WHERE u.status='pending' GROUP BY u.id ORDER BY u.id")->fetchAll();
$opt=function()use($pts){foreach($pts as $p)echo "<option value='{$p['id']}'>".h($p['full_name'])."</option>";};
head('Users');if($m)echo "<div class='alert alert-info'>".h($m)."</div>";?>
<div class="card mb-3 border-warning"><div class="card-header bg-warning">Awaiting approval (<?=count($pend)?>)</div><div class="card-body">
<p class="small text-muted">Verify the resident's valid ID before approving. "Matched record" is based on the name and birthday they entered.</p>
<?php foreach($pend as $x){?><div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><a href="resident.php?id=<?=$x['id']?>"><b><?=h($x['full_name'])?></b></a> <small class="text-muted">(<?=h($x['username'])?> · <?=h($x['created_at'])?>)</small><br><small>Matched record: <?=$x['pts']?h($x['pts']):'<span class="text-danger">none</span>'?></small></div>
<form method="post" class="d-flex gap-1"><input type="hidden" name="user_id" value="<?=$x['id']?>"><button name="a" value="approve" class="btn btn-sm btn-success">Approve</button><button name="a" value="reject" class="btn btn-sm btn-outline-danger">Reject</button></form></div><?php }
if(!$pend)echo '<span class="text-muted">None waiting.</span>';?></div></div>
<div class="card mb-3"><div class="card-header">Create account</div><form method="post" class="card-body row g-2"><input type="hidden" name="a" value="create">
<div class="col-md-3"><input name="full_name" class="form-control" placeholder="Full name" required></div><div class="col-md-2"><input name="username" class="form-control" placeholder="Username" required></div><div class="col-md-2"><input name="password" class="form-control" placeholder="Password (8+)" required></div>
<div class="col-md-2"><select name="role" class="form-select"><?php foreach($ROLES as $k=>$v)echo "<option value='$k'>$v</option>";?></select></div>
<div class="col-md-3"><input name="license_no" class="form-control" placeholder="PRC/License no. (optional)"></div>
<div class="col-md-3"><input name="purok" class="form-control" placeholder="Purok (for BHW)"></div>
<div class="col-md-6"><select name="patient_id" class="form-select"><option value="">— Patient to link (for resident) —</option><?php $opt();?></select></div><div class="col-md-3"><button class="btn btn-success w-100">Create</button></div></form></div>
<div class="card mb-3"><div class="card-header">Link a resident to an additional patient (e.g. child)</div><form method="post" class="card-body row g-2"><input type="hidden" name="a" value="link">
<div class="col-md-5"><select name="user_id" class="form-select"><?php foreach($us as $u)if($u['role']=='resident')echo "<option value='{$u['id']}'>".h($u['full_name'].' ('.$u['username'].')')."</option>";?></select></div>
<div class="col-md-5"><select name="patient_id" class="form-select"><?php $opt();?></select></div><div class="col-md-2"><button class="btn btn-outline-success w-100">Link</button></div></form></div>
<table class="table table-sm bg-white"><tr><th>Name</th><th>Username</th><th>Role</th><th>License</th><th>Purok</th><th>Status</th><th>Linked patients</th></tr><?php foreach($us as $u){?><tr><td><?=h($u['full_name'])?></td><td><?=h($u['username'])?></td><td><?=h($ROLES[$u['role']]??$u['role'])?></td><td><?=h($u['license_no'])?></td><td><?=h($u['purok'])?></td><td><?=h($u['status'])?></td><td><?=h($u['pts'])?></td></tr><?php }?></table>
<?php foot();
