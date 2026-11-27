<?php require 'config.php';
$s=$pdo->prepare("SELECT id,full_name,birthdate FROM patients WHERE token=?");$s->execute([$_GET['t']??'']);$p=$s->fetch();
if(!$p){http_response_code(404);die('Invalid QR');}
$vs=vaccine_status($p['id'],$p['birthdate']);$done=count(array_filter($vs,fn($x)=>$x['status']=='Done'));?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vaccine Card</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/style.css" rel="stylesheet"></head><body class="p-3">
<h5><?=h($p['full_name'])?></h5><p class="text-muted">Birthday: <?=h($p['birthdate'])?> · <?=$done?>/<?=count($vs)?> vaccines completed</p>
<table class="table table-sm"><tr><th>Vaccine</th><th>Dose</th><th>Status</th><th>Given / Due</th></tr>
<?php foreach($vs as $r){?><tr><td><?=h($r['vaccine'])?></td><td><?=$r['dose']?></td><td><?=badge($r['status'])?></td><td><?=h($r['given']?:$r['due'])?></td></tr><?php }?></table><footer class="text-center text-muted small mt-4 pt-3 border-top">Barangay Quirino, Solano, Nueva Vizcaya · Health Center Record Keeping System<br>Programmed by: Dela Cruz, Lyn R.</footer></body></html>
