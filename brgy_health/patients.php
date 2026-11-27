<?php require 'config.php';require 'layout.php';require_staff();
$err='';
if($_SERVER['REQUEST_METHOD']=='POST'){
 $d=[trim($_POST['full_name']),$_POST['birthdate'],$_POST['sex'],trim($_POST['purok']),trim($_POST['contact']),trim($_POST['guardian']),trim($_POST['address']),isset($_POST['has_hpn'])?1:0,isset($_POST['has_dm'])?1:0];
 if(me()['role']=='bhw')$d[3]=me()['purok'];
 try{
  if(!empty($_POST['id'])){$pid=(int)$_POST['id'];$o=$pdo->prepare("SELECT * FROM patients WHERE id=?");$o->execute([$pid]);$old=$o->fetch();if(!can_view($old))die('No access.');
   // When staff changes the contact or address, it is locked so the resident can no longer change it
   $lc=(string)$old['contact']!==(string)$d[4]?1:0;$la=(string)$old['address']!==(string)$d[6]?1:0;
   $pdo->prepare("UPDATE patients SET full_name=?,birthdate=?,sex=?,purok=?,contact=?,guardian=?,address=?,has_hpn=?,has_dm=?,contact_locked=IF(contact_locked=1 OR ?=1,1,0),address_locked=IF(address_locked=1 OR ?=1,1,0) WHERE id=?")->execute([...$d,$lc,$la,$pid]);
   audit('Edit patient'.($lc?' (contact locked)':'').($la?' (address locked)':''),$pid);}
  else{$pdo->prepare("INSERT INTO patients(full_name,birthdate,sex,purok,contact,guardian,address,has_hpn,has_dm,token) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([...$d,bin2hex(random_bytes(16))]);audit('Add patient',(int)$pdo->lastInsertId());}
  header('Location: patients.php');exit;
 }catch(PDOException $e){$err=$e->getCode()==23000?'A record with the same name and birthday already exists (duplicate).':'Error: '.$e->getMessage();}
}
$e=['id'=>'','full_name'=>'','birthdate'=>'','sex'=>'F','purok'=>'','contact'=>'','guardian'=>'','address'=>'','has_hpn'=>0,'has_dm'=>0];
if(isset($_GET['edit'])){$s=$pdo->prepare("SELECT * FROM patients WHERE id=?");$s->execute([(int)$_GET['edit']]);$e=$s->fetch()?:$e;if($e['id']&&!can_view($e))die('No access.');}
$q=trim($_GET['q']??'');$t=$_GET['t']??'';
$s=$pdo->prepare("SELECT p.*,(SELECT COUNT(*) FROM prenatal WHERE patient_id=p.id AND active=1) preg FROM patients p WHERE full_name LIKE ? ORDER BY full_name");$s->execute(["%$q%"]);
$rows=array_filter($s->fetchAll(),function($p)use($t){$a=age($p['birthdate']);return match($t){'babies'=>$a<5,'seniors'=>$a>=60,'hpn'=>$p['has_hpn'],'dm'=>$p['has_dm'],'preg'=>$p['preg'],default=>true};});
$rows=array_filter($rows,'can_view');
head('Patients');?>
<h4>🩺 Patients</h4>
<?php if($err)echo "<div class='alert alert-danger'>".h($err)."</div>";?>
<div class="card mb-4"><div class="card-header"><?=$e['id']?'Edit record':'Add patient'?></div><form method="post" class="card-body row g-3">
<input type="hidden" name="id" value="<?=h($e['id'])?>">
<div class="col-md-4"><label class="form-label">Full name</label><input class="form-control" name="full_name" required value="<?=h($e['full_name'])?>"></div>
<div class="col-md-2"><label class="form-label">Birthday</label><input type="date" class="form-control" name="birthdate" required value="<?=h($e['birthdate'])?>"></div>
<div class="col-md-2"><label class="form-label">Sex</label><select name="sex" class="form-select"><option value="F" <?=$e['sex']=='F'?'selected':''?>>Female</option><option value="M" <?=$e['sex']=='M'?'selected':''?>>Male</option></select></div>
<div class="col-md-2"><label class="form-label">Purok</label><input class="form-control" name="purok" value="<?=h($e['purok'])?>"></div>
<div class="col-md-2"><label class="form-label">Contact no.</label><input class="form-control" name="contact" value="<?=h($e['contact'])?>"></div>
<div class="col-md-4"><label class="form-label">Parent/Guardian</label><input class="form-control" name="guardian" value="<?=h($e['guardian'])?>"></div>
<div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="address" value="<?=h($e['address'])?>"></div>
<div class="col-md-8 pt-2"><label class="me-4"><input type="checkbox" name="has_hpn" <?=$e['has_hpn']?'checked':''?>> Hypertension</label><label><input type="checkbox" name="has_dm" <?=$e['has_dm']?'checked':''?>> Diabetes</label>
<?php if($e['id'])echo '<div class="form-text">🔒 If you change the contact or address, the resident will no longer be able to change it.</div>';?></div>
<div class="col-md-4 text-end"><button class="btn btn-success px-4">Save</button></div></form></div>
<form class="row g-3 mb-3"><div class="col-md-5"><input class="form-control" name="q" placeholder="Search name..." value="<?=h($q)?>"></div>
<div class="col-md-4"><select name="t" class="form-select" onchange="this.form.submit()"><?php foreach([''=>'All','babies'=>'Babies (0-4)','seniors'=>'Seniors','hpn'=>'Hypertension','dm'=>'Diabetes','preg'=>'Pregnant'] as $k=>$v)echo "<option value='$k' ".($t===$k?'selected':'').">$v</option>";?></select></div>
<div class="col-md-3"><button class="btn btn-outline-success w-100">Search</button></div></form>
<table class="table table-sm bg-white"><tr><th>Name</th><th>Age</th><th>Purok</th><th>Category</th><th></th></tr>
<?php foreach($rows as $p){$a=age($p['birthdate']);?><tr><td><a href="patient.php?id=<?=$p['id']?>"><b><?=h($p['full_name'])?></b></a></td><td><?=$a?></td><td><?=h($p['purok'])?></td><td>
<?=$a<5?'<span class="badge bg-primary">Baby</span> ':''?><?=$a>=60?'<span class="badge bg-dark">Senior</span> ':''?><?=$p['has_hpn']?'<span class="badge bg-danger">HPN</span> ':''?><?=$p['has_dm']?'<span class="badge bg-warning text-dark">DM</span> ':''?><?=$p['preg']?'<span class="badge bg-info text-dark">Pregnant</span>':''?></td>
<td><a class="btn btn-sm btn-outline-secondary" href="?edit=<?=$p['id']?>">Edit</a></td></tr><?php }?></table>
<?php foot();
