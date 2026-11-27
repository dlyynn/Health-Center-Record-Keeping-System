<?php require 'config.php';require 'layout.php';require_resident();auto_notify_daily();
$ids=my_patient_ids();$uid=(int)me()['id'];
if($_SERVER['REQUEST_METHOD']=='POST'){$a=$_POST['a']??'';
 try{
  if($a=='id'){
   $q=$pdo->prepare("SELECT COUNT(*) FROM resident_ids WHERE user_id=? AND status='Verified'");$q->execute([$uid]);if($q->fetchColumn())throw new Exception('Your ID is already verified.');
   $ty=$_POST['id_type']??'';if(!in_array($ty,id_types(),true))throw new Exception('Please select the ID type.');
   $f=save_id_upload('id_front',true);
   try{$b=save_id_upload('id_back');}catch(Exception $e){@unlink(__DIR__.'/uploads/ids/'.$f);throw $e;}
   $pdo->prepare("INSERT INTO resident_ids(user_id,id_type,file_front,file_back) VALUES(?,?,?,?)")->execute([$uid,$ty,$f,$b]);
   audit('Resident uploaded ID');$_SESSION['flash']='ID uploaded. Please wait for the Health Center to verify it.';
  }else{$pid=(int)($_POST['pid']??0);
   if(in_array($pid,$ids,true)){
    // Contact and address: the resident can no longer change these once staff has edited them (contact_locked / address_locked)
    if($a=='info'){$pdo->prepare("UPDATE patients SET contact=IF(contact_locked=1,contact,?),address=IF(address_locked=1,address,?),sms_optin=? WHERE id=?")->execute([trim($_POST['contact']??''),trim($_POST['address']??''),isset($_POST['sms_optin'])?1:0,$pid]);audit('Resident updated own info',$pid);$_SESSION['flash']='Information saved.';}
    if($a=='appt'){$dt=$_POST['date']??'';
     if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dt)||$dt<date('Y-m-d')||$dt>date('Y-m-d',strtotime('+1 year')))throw new Exception('Please enter a valid appointment date.');
     $pu=in_array($_POST['purpose']??'',purposes(),true)?$_POST['purpose']:'Other';
     $pdo->prepare("INSERT INTO appointments(patient_id,appt_date,purpose,notes) VALUES(?,?,?,?)")->execute([$pid,$dt,$pu,substr(trim($_POST['notes']??''),0,255)?:null]);
     audit('Appointment requested',$pid);$_SESSION['flash']='Your request has been sent. Please wait for the Health Center to confirm.';}
    if($a=='cancel'){$pdo->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND patient_id=? AND status IN('Requested','Confirmed')")->execute([(int)$_POST['aid'],$pid]);audit('Appointment cancelled by resident',$pid);}
   }}
 }catch(Exception $e){$_SESSION['flash_err']=$e->getMessage();}
 header('Location: portal.php');exit;}
$ri=$pdo->prepare("SELECT * FROM resident_ids WHERE user_id=? ORDER BY id DESC");$ri->execute([$uid]);$myids=$ri->fetchAll();
$verified=count(array_filter($myids,fn($x)=>$x['status']=='Verified'));
$IDB=['Pending'=>'warning text-dark','Verified'=>'success','Rejected'=>'danger'];
head('Portal');
echo "<h4>Welcome, ".h(me()['username'])."!</h4>";
if(!empty($_SESSION['flash'])){echo "<div class='alert alert-success'>".h($_SESSION['flash'])."</div>";unset($_SESSION['flash']);}
if(!empty($_SESSION['flash_err'])){echo "<div class='alert alert-danger'>".h($_SESSION['flash_err'])."</div>";unset($_SESSION['flash_err']);}?>
<div class="card mb-4"><div class="card-header">🪪 My Valid ID</div><div class="card-body">
<?php foreach($myids as $r){?><div class="d-flex justify-content-between align-items-center border-bottom py-2"><span><b><?=h($r['id_type'])?></b> <span class="badge bg-<?=$IDB[$r['status']]?>"><?=h($r['status'])?></span> <small class="text-muted"><?=h($r['uploaded_at'])?></small>
<?php if($r['remarks'])echo '<br><small class="text-danger">'.h($r['remarks']).'</small>';?></span>
<span><a class="btn btn-sm btn-outline-secondary" target="_blank" href="view_id.php?id=<?=$r['id']?>&s=f">Front</a> <?php if($r['file_back']){?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="view_id.php?id=<?=$r['id']?>&s=b">Back</a><?php }?></span></div><?php }
if(!$myids)echo '<p class="text-muted">You have not uploaded an ID yet.</p>';
if(!$verified){?>
<form method="post" enctype="multipart/form-data" class="row g-3 mt-1"><input type="hidden" name="a" value="id">
<div class="col-md-4"><label class="form-label">ID type</label><select name="id_type" class="form-select"><?php foreach(id_types() as $x)echo "<option>".h($x)."</option>";?></select></div>
<div class="col-md-4"><label class="form-label">ID front</label><input type="file" name="id_front" class="form-control" accept="image/jpeg,image/png,application/pdf" required></div>
<div class="col-md-4"><label class="form-label">Back (optional)</label><input type="file" name="id_back" class="form-control" accept="image/jpeg,image/png,application/pdf"></div>
<div class="col-12"><button class="btn btn-success">⬆ Upload ID</button> <span class="form-text">JPG, PNG, or PDF · up to 2MB · only the Health Center can see it.</span></div></form>
<?php }else echo '<div class="alert alert-success mb-0 mt-2">✔ Your ID is already verified.</div>';?></div></div>
<?php if(!$ids)echo '<div class="alert alert-warning">No patient is linked to your account yet. Please see your BHW or Midwife.</div>';
foreach($ids as $pid){$s=$pdo->prepare("SELECT * FROM patients WHERE id=?");$s->execute([$pid]);$p=$s->fetch();if(!$p)continue;$a=age($p['birthdate']);?>
<div class="card mb-4"><div class="card-header">👤 <?=h($p['full_name'])?> <small class="text-muted">· <?=$a?> yrs · <?=h($p['purok'])?></small></div><div class="card-body">
<h6>📞 Information</h6>
<form method="post" class="row g-3 mb-3"><input type="hidden" name="a" value="info"><input type="hidden" name="pid" value="<?=$pid?>">
<div class="col-md-4"><label class="form-label">Contact no.</label><input name="contact" class="form-control" value="<?=h($p['contact'])?>" <?=$p['contact_locked']?'disabled':''?>><?php if($p['contact_locked'])echo '<div class="form-text">🔒 Edited by the Health Center. Please visit the Health Center to make changes.</div>';?></div>
<div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" value="<?=h($p['address'])?>" <?=$p['address_locked']?'disabled':''?>><?php if($p['address_locked'])echo '<div class="form-text">🔒 Edited by the Health Center. Please visit the Health Center to make changes.</div>';?></div>
<div class="col-md-2 align-self-end"><button class="btn btn-success w-100">Save</button></div>
<div class="col-12"><label><input type="checkbox" name="sms_optin" <?=$p['sms_optin']?'checked':''?>> Receive SMS reminders on this contact number</label></div>
<div class="form-text">Name, birthday, and medical records can only be edited by the Health Center.</div></form>
<?php if($a<5){?><h6>💉 Vaccine Schedule <a class="btn btn-sm btn-outline-success" href="card.php?t=<?=h($p['token'])?>" target="_blank">Vaccine card</a></h6>
<table class="table table-sm"><tr><th>Vaccine</th><th>Dose</th><th>Due</th><th>Status</th></tr><?php foreach(vaccine_status($pid,$p['birthdate']) as $r){?><tr><td><?=h($r['vaccine'])?></td><td><?=$r['dose']?></td><td><?=$r['due']?></td><td><?=badge($r['status'])?></td></tr><?php }?></table><?php }
if($p['has_hpn']||$p['has_dm']||$a>=60){$v=$pdo->prepare("SELECT * FROM vitals WHERE patient_id=? ORDER BY date_taken DESC,id DESC LIMIT 5");$v->execute([$pid]);?>
<h6>❤ Latest BP / Sugar</h6><table class="table table-sm"><tr><th>Date</th><th>BP</th><th>Sugar</th></tr><?php foreach($v as $r){?><tr><td><?=$r['date_taken']?></td><td><?=$r['systolic']?>/<?=$r['diastolic']?></td><td><?=h($r['sugar'])?></td></tr><?php }?></table><?php }
$pr=$pdo->prepare("SELECT * FROM prenatal WHERE patient_id=? AND active=1 ORDER BY id DESC LIMIT 1");$pr->execute([$pid]);$pr=$pr->fetch();
if($pr){$edd=(new DateTime($pr['lmp']))->modify('+280 days');?><h6>🤰 Prenatal</h6><p>EDD: <b><?=$edd->format('M d, Y')?></b></p><?php }
$al=allergies($pid);if($al){?><h6>⚠ Allergy</h6><ul><?php foreach($al as $x){?><li><b><?=h($x['allergen'])?></b> — <?=h($x['reaction'])?> <span class="badge bg-danger"><?=h($x['severity'])?></span></li><?php }?></ul><?php }?>
<h6>📅 Request an Appointment</h6>
<form method="post" class="row g-3 mb-3"><input type="hidden" name="a" value="appt"><input type="hidden" name="pid" value="<?=$pid?>">
<div class="col-md-3"><label class="form-label">Date</label><input type="date" name="date" min="<?=date('Y-m-d')?>" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Purpose</label><select name="purpose" class="form-select"><?php foreach(purposes() as $x)echo "<option>".h($x)."</option>";?></select></div>
<div class="col-md-5"><label class="form-label">Reason / details</label><input name="notes" maxlength="255" class="form-control" placeholder="e.g. has a fever, needs a vaccine"></div>
<div class="col-12"><button class="btn btn-success">Send request</button></div></form>
<?php $q=$pdo->prepare("SELECT * FROM appointments WHERE patient_id=? ORDER BY appt_date DESC LIMIT 5");$q->execute([$pid]);foreach($q as $r){?>
<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span><b><?=h($r['appt_date'])?></b> · <?=h($r['purpose'])?> <span class="badge bg-secondary"><?=h($r['status'])?></span><?=$r['notes']?'<br><small class="text-muted">'.h($r['notes']).'</small>':''?></span>
<?php if(in_array($r['status'],['Requested','Confirmed'])){?><form method="post"><input type="hidden" name="a" value="cancel"><input type="hidden" name="pid" value="<?=$pid?>"><input type="hidden" name="aid" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form><?php }?></div><?php }?>
</div></div><?php }
foot();
