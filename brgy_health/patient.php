<?php require 'config.php';require 'layout.php';require_staff();
$id=(int)($_GET['id']??0);$s=$pdo->prepare("SELECT * FROM patients WHERE id=?");$s->execute([$id]);$p=$s->fetch();if(!$p||!can_view($p))die('Not found.');
$by=me()['id'];
if($_SERVER['REQUEST_METHOD']=='POST'){$a=$_POST['a']??'';
 if($a=='vax'){$pdo->prepare("INSERT INTO vaccinations(patient_id,vaccine,dose_no,date_given,given_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE date_given=VALUES(date_given),given_by=VALUES(given_by)")->execute([$id,$_POST['vaccine'],(int)$_POST['dose'],$_POST['date'],$by]);audit("Vaccine {$_POST['vaccine']} #{$_POST['dose']}",$id);notify($id,"Vaccine recorded: {$_POST['vaccine']} dose {$_POST['dose']} ({$_POST['date']}).");}
 if($a=='vit'){$sg=$_POST['sugar']!==''?$_POST['sugar']:null;$pdo->prepare("INSERT INTO vitals(patient_id,taken_by,date_taken,systolic,diastolic,sugar) VALUES(?,?,?,?,?,?)")->execute([$id,$by,$_POST['date'],(int)$_POST['sys'],(int)$_POST['dia'],$sg]);audit('Vitals',$id);if($f=vflag((int)$_POST['sys'],(int)$_POST['dia'],$sg!==null?(float)$sg:null))notify($id,"Your latest reading is high ($f). Please consult at the Health Center.");}
 if($a=='preg'){$pdo->prepare("INSERT INTO prenatal(patient_id,lmp) VALUES(?,?)")->execute([$id,$_POST['lmp']]);audit('Prenatal start',$id);}
 if($a=='visit'){$o=$pdo->prepare("SELECT 1 FROM prenatal WHERE id=? AND patient_id=?");$o->execute([(int)$_POST['pid'],$id]);
  if($o->fetch()){$pdo->prepare("INSERT INTO prenatal_visits(prenatal_id,attended_by,visit_date,weight,bp,notes) VALUES(?,?,?,?,?,?)")->execute([(int)$_POST['pid'],$by,$_POST['date'],$_POST['weight']?:null,$_POST['bp'],$_POST['notes']]);audit('Prenatal visit',$id);notify($id,"Your prenatal check-up on {$_POST['date']} has been recorded.");}}
 if($a=='allergy'&&trim($_POST['allergen'])!==''){$sev=in_array($_POST['severity'],['Mild','Moderate','Severe'])?$_POST['severity']:'Mild';
  $pdo->prepare("INSERT INTO patient_allergies(patient_id,allergen,reaction,severity,noted_date) VALUES(?,?,?,?,?)")->execute([$id,trim($_POST['allergen']),trim($_POST['reaction']),$sev,$_POST['date']?:date('Y-m-d')]);audit('Allergy added: '.substr(trim($_POST['allergen']),0,60),$id);}
 if($a=='unlock'&&me()['role']=='admin'){$col=($_POST['col']??'')=='address'?'address_locked':'contact_locked';$pdo->prepare("UPDATE patients SET $col=0 WHERE id=?")->execute([$id]);audit("Unlocked $col",$id);}
 if($a=='allergy_del'){$pdo->prepare("DELETE FROM patient_allergies WHERE id=? AND patient_id=?")->execute([(int)$_POST['aid'],$id]);audit('Allergy removed',$id);}
 header("Location: patient.php?id=$id");exit;}
$age=age($p['birthdate']);$al=allergies($id);
$alTxt=implode(', ',array_map(fn($x)=>$x['allergen'].' ('.$x['severity'].')',$al));
head($p['full_name']);?>
<h4><?=h($p['full_name'])?> <small class="text-muted"><?=$age?> yrs · <?=h($p['purok'])?></small> <a class="btn btn-sm btn-outline-secondary" href="patients.php?edit=<?=$id?>">Edit</a></h4>

<div class="card my-3"><div class="card-header">📞 Resident information</div><div class="card-body d-flex flex-wrap gap-5">
<?php foreach([['contact','Contact','contact_locked'],['address','Address','address_locked']] as [$k,$lb,$lk]){?><div><?=$lb?>: <b><?=h($p[$k])?:'—'?></b>
<?php if($p[$lk]){?><span class="badge bg-secondary">🔒 locked for resident</span><?php if(me()['role']=='admin'){?><form method="post" class="d-inline"><input type="hidden" name="a" value="unlock"><input type="hidden" name="col" value="<?=$k?>"><button class="btn btn-sm btn-outline-secondary">Unlock</button></form><?php }}?></div><?php }?></div></div>
<?php if($al){?><div class="alert alert-danger mt-3"><b>⚠ HAS ALLERGY:</b> <?=h($alTxt)?></div><?php }?>
<div class="card my-3"><div class="card-header">Allergies</div><div class="card-body">
<?php foreach($al as $x){?><div class="d-flex justify-content-between border-bottom py-1"><span><b><?=h($x['allergen'])?></b> — <?=h($x['reaction'])?> <span class="badge bg-danger"><?=h($x['severity'])?></span> <small class="text-muted"><?=h($x['noted_date'])?></small></span>
<form method="post"><input type="hidden" name="a" value="allergy_del"><input type="hidden" name="aid" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></div><?php }
if(!$al)echo '<p class="text-muted mb-2">No allergies recorded.</p>';?>
<form method="post" class="row g-2 mt-1"><input type="hidden" name="a" value="allergy">
<div class="col-md-3"><input name="allergen" class="form-control" placeholder="Allergen (e.g. egg, penicillin)" required></div>
<div class="col-md-3"><input name="reaction" class="form-control" placeholder="Reaction (e.g. rash)"></div>
<div class="col-md-2"><select name="severity" class="form-select"><option>Mild</option><option>Moderate</option><option>Severe</option></select></div>
<div class="col-md-2"><input type="date" name="date" value="<?=date('Y-m-d')?>" class="form-control"></div>
<div class="col-md-2"><button class="btn btn-danger w-100">Add</button></div></form></div></div>

<?php if($age<5){$vs=vaccine_status($id,$p['birthdate']);?>
<div class="card my-3"><div class="card-header">Vaccine Schedule (auto-computed)</div>
<table class="table table-sm mb-0"><tr><th>Vaccine</th><th>Dose</th><th>Due</th><th>Status</th><th>Given on / by</th></tr>
<?php foreach($vs as $r){?><tr><td><?=h($r['vaccine'])?></td><td><?=$r['dose']?></td><td><?=$r['due']?></td><td><?=badge($r['status'])?></td><td>
<?php if($r['given'])echo h($r['given']).' · '.who($r['by'],$r['role']);else{?><form method="post" class="d-flex gap-1" <?php if($al){?>onsubmit="return confirm(<?=h(json_encode('WARNING: patient has allergies: '.$alTxt.'. Are you sure you want to give the vaccine?'))?>)"<?php }?>><input type="hidden" name="a" value="vax"><input type="hidden" name="vaccine" value="<?=h($r['vaccine'])?>"><input type="hidden" name="dose" value="<?=$r['dose']?>"><input type="date" name="date" value="<?=date('Y-m-d')?>" class="form-control form-control-sm"><button class="btn btn-sm btn-success">Given</button></form><?php }?></td></tr><?php }?></table></div>
<div class="card mb-3"><div class="card-header">QR Vaccine Card</div><div class="card-body"><div id="qr"></div><small class="text-muted">Scan to view the vaccine summary. Use the server's IP address (not localhost) so it works on a phone.</small></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'),{text:location.origin+location.pathname.replace('patient.php','card.php')+'?t=<?=h($p['token'])?>',width:180,height:180});</script>
<?php }?>

<?php if($p['has_hpn']||$p['has_dm']||$age>=60){
 $v=$pdo->prepare("SELECT t.*,u.full_name bn,u.role br FROM vitals t LEFT JOIN users u ON u.id=t.taken_by WHERE t.patient_id=? ORDER BY t.date_taken DESC,t.id DESC");$v->execute([$id]);$vit=$v->fetchAll();?>
<div class="card my-3"><div class="card-header">BP / Blood Sugar Monitoring</div><div class="card-body">
<form method="post" class="row g-2 mb-3"><input type="hidden" name="a" value="vit"><div class="col"><input type="date" name="date" value="<?=date('Y-m-d')?>" class="form-control"></div><div class="col"><input type="number" name="sys" placeholder="Systolic" class="form-control" required></div><div class="col"><input type="number" name="dia" placeholder="Diastolic" class="form-control" required></div><div class="col"><input type="number" step="0.1" name="sugar" placeholder="Sugar (mg/dL)" class="form-control"></div><div class="col"><button class="btn btn-success">Add</button></div></form>
<table class="table table-sm"><tr><th>Date</th><th>BP</th><th>Sugar</th><th>Flag</th><th>Taken by</th></tr>
<?php foreach($vit as $r){$f=vflag($r['systolic'],$r['diastolic'],$r['sugar']);?><tr class="<?=$f?'table-danger':''?>"><td><?=$r['date_taken']?></td><td><?=$r['systolic']?>/<?=$r['diastolic']?></td><td><?=h($r['sugar'])?></td><td><?=h($f)?></td><td><?=who($r['bn'],$r['br'])?></td></tr><?php }?></table></div></div>
<?php }?>

<?php if($p['sex']=='F'&&$age>=10&&$age<=49){
 $s=$pdo->prepare("SELECT * FROM prenatal WHERE patient_id=? AND active=1 ORDER BY id DESC LIMIT 1");$s->execute([$id]);$pr=$s->fetch();?>
<div class="card my-3"><div class="card-header">Prenatal Check-up</div><div class="card-body">
<?php if(!$pr){?><form method="post" class="row g-2"><input type="hidden" name="a" value="preg"><div class="col-md-4"><label class="form-label">LMP (last menstrual period)</label><input type="date" name="lmp" class="form-control" required></div><div class="col-md-3 align-self-end"><button class="btn btn-info">Start</button></div></form>
<?php }else{$lmp=new DateTime($pr['lmp']);$edd=(clone $lmp)->modify('+280 days');$w=(int)floor($lmp->diff(new DateTime('today'))->days/7);$tri=$w<14?'1st':($w<28?'2nd':'3rd');
 $vs=$pdo->prepare("SELECT pv.*,u.full_name an,u.role ar FROM prenatal_visits pv LEFT JOIN users u ON u.id=pv.attended_by WHERE pv.prenatal_id=? ORDER BY pv.visit_date");$vs->execute([$pr['id']]);$vis=$vs->fetchAll();?>
<p><b>EDD:</b> <?=$edd->format('M d, Y')?> · <b><?=$w?></b> weeks (<?=$tri?> trimester) · <b>Visits:</b> <?=count($vis)?>/4 minimum <?=count($vis)<4?'<span class="badge bg-warning text-dark">Incomplete</span>':'<span class="badge bg-success">Complete</span>'?></p>
<form method="post" class="row g-2 mb-3"><input type="hidden" name="a" value="visit"><input type="hidden" name="pid" value="<?=$pr['id']?>"><div class="col"><input type="date" name="date" value="<?=date('Y-m-d')?>" class="form-control"></div><div class="col"><input type="number" step="0.1" name="weight" placeholder="Weight (kg)" class="form-control"></div><div class="col"><input name="bp" placeholder="BP" class="form-control"></div><div class="col"><input name="notes" placeholder="Notes" class="form-control"></div><div class="col"><button class="btn btn-info">Add</button></div></form>
<table class="table table-sm"><tr><th>Date</th><th>Weight</th><th>BP</th><th>Notes</th><th>Attended by</th></tr><?php foreach($vis as $r){?><tr><td><?=$r['visit_date']?></td><td><?=h($r['weight'])?></td><td><?=h($r['bp'])?></td><td><?=h($r['notes'])?></td><td><?=who($r['an'],$r['ar'])?></td></tr><?php }?></table>
<?php }?></div></div><?php }?>
<?php foot();
