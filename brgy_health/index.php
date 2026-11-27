<?php require 'config.php';require 'layout.php';require_staff();auto_notify_daily();head('Dashboard');if(can_manage_residents()){$pc=$pdo->query("SELECT COUNT(*) FROM users WHERE role='resident' AND status='pending'")->fetchColumn();if($pc)echo "<div class='alert alert-warning'><b>$pc</b> resident(s) awaiting approval. <a href='residents.php?s=pending'>View</a></div>";}
$all=array_filter($pdo->query("SELECT * FROM patients")->fetchAll(),'can_view');
$over=[];$high=[];$c=['Babies (0-4)'=>0,'Seniors (60+)'=>0,'HPN'=>0,'Diabetes'=>0];
foreach($all as $p){$a=age($p['birthdate']);
 if($a<5){$c['Babies (0-4)']++;$n=count(array_filter(vaccine_status($p['id'],$p['birthdate']),fn($x)=>$x['status']=='Overdue'));if($n)$over[]=[$p,$n];}
 if($a>=60)$c['Seniors (60+)']++;if($p['has_hpn'])$c['HPN']++;if($p['has_dm'])$c['Diabetes']++;
 $q=$pdo->prepare("SELECT * FROM vitals WHERE patient_id=? ORDER BY date_taken DESC,id DESC LIMIT 1");$q->execute([$p['id']]);$v=$q->fetch();
 if($v&&($f=vflag($v['systolic'],$v['diastolic'],$v['sugar'])))$high[]=[$p,$v,$f];}
$preg=$pdo->query("SELECT pr.lmp,p.full_name,p.id pid FROM prenatal pr JOIN patients p ON p.id=pr.patient_id WHERE pr.active=1")->fetchAll();$ids=array_column($all,'id');$preg=array_filter($preg,fn($r)=>in_array($r['pid'],$ids));
?>
<div class="row g-3 mb-4"><?php foreach($c as $k=>$n){?><div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-2 fw-bold"><?=$n?></div><?=h($k)?></div></div></div><?php }?></div>
<div class="row g-4">
<div class="col-md-4"><div class="card border-danger"><div class="card-header bg-danger text-white">Overdue vaccines</div><ul class="list-group list-group-flush">
<?php foreach($over as [$p,$n]){?><li class="list-group-item"><a href="patient.php?id=<?=$p['id']?>"><?=h($p['full_name'])?></a> <span class="badge bg-danger"><?=$n?></span></li><?php }if(!$over)echo '<li class="list-group-item text-muted">None 🎉</li>';?></ul></div></div>
<div class="col-md-4"><div class="card border-warning"><div class="card-header bg-warning">High BP / Sugar (latest reading)</div><ul class="list-group list-group-flush">
<?php foreach($high as [$p,$v,$f]){?><li class="list-group-item"><a href="patient.php?id=<?=$p['id']?>"><?=h($p['full_name'])?></a> — <?=h($f)?> (<?=$v['systolic']?>/<?=$v['diastolic']?><?=$v['sugar']!==null?', '.$v['sugar']:''?>)</li><?php }if(!$high)echo '<li class="list-group-item text-muted">None</li>';?></ul></div></div>
<div class="col-md-4"><div class="card border-info"><div class="card-header bg-info">Pregnant (EDD)</div><ul class="list-group list-group-flush">
<?php foreach($preg as $r){$edd=(new DateTime($r['lmp']))->modify('+280 days');$left=(int)(new DateTime('today'))->diff($edd)->format('%r%a');?><li class="list-group-item"><a href="patient.php?id=<?=$r['pid']?>"><?=h($r['full_name'])?></a> — EDD <?=$edd->format('M d, Y')?> <?=$left<=30?"<span class='badge bg-danger'>$left days</span>":''?></li><?php }if(!$preg)echo '<li class="list-group-item text-muted">None</li>';?></ul></div></div>
</div>
<?php foot();
