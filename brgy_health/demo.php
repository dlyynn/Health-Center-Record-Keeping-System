<?php require 'config.php';require 'layout.php';require_admin();$m='';
function mk($n,$bd,$sx,$pu,$g,$hp,$dm){global $pdo;$pdo->prepare("INSERT IGNORE INTO patients(token,full_name,birthdate,sex,purok,guardian,has_hpn,has_dm) VALUES(?,?,?,?,?,?,?,?)")->execute(['demo'.bin2hex(random_bytes(14)),$n,$bd,$sx,$pu,$g,$hp,$dm]);$s=$pdo->prepare("SELECT id FROM patients WHERE full_name=? AND birthdate=?");$s->execute([$n,$bd]);return (int)$s->fetchColumn();}
function vax($id,$bd,$max,$by){global $pdo,$SCHEDULE;foreach($SCHEDULE as [$v,$d,$off])if($off<=$max)$pdo->prepare("INSERT IGNORE INTO vaccinations(patient_id,vaccine,dose_no,date_given,given_by) VALUES(?,?,?,?,?)")->execute([$id,$v,$d,date('Y-m-d',strtotime("$bd +$off days")),$by]);}
function vit($id,$ago,$s,$d,$g,$by){global $pdo;$pdo->prepare("INSERT INTO vitals(patient_id,taken_by,date_taken,systolic,diastolic,sugar) VALUES(?,?,?,?,?,?)")->execute([$id,$by,date('Y-m-d',strtotime("-$ago days")),$s,$d,$g]);}
function preg_start($id,$lmpAgo,$visits,$by){global $pdo;$pdo->prepare("INSERT INTO prenatal(patient_id,lmp) VALUES(?,?)")->execute([$id,date('Y-m-d',strtotime("-$lmpAgo days"))]);$pr=$pdo->lastInsertId();
 foreach($visits as $a)$pdo->prepare("INSERT INTO prenatal_visits(prenatal_id,attended_by,visit_date,weight,bp,notes) VALUES(?,?,?,?,?,?)")->execute([$pr,$by,date('Y-m-d',strtotime("-$a days")),58.5,'110/70','Normal']);}
function usr($u,$p,$r,$pu,$name,$lic=null){global $pdo;$pdo->prepare("INSERT IGNORE INTO users(username,password_hash,full_name,role,license_no,purok) VALUES(?,?,?,?,?,?)")->execute([$u,password_hash($p,PASSWORD_DEFAULT),$name,$r,$lic,$pu]);$s=$pdo->prepare("SELECT id FROM users WHERE username=?");$s->execute([$u]);return (int)$s->fetchColumn();}
function alg($id,$a,$r,$sev){global $pdo;$pdo->prepare("INSERT INTO patient_allergies(patient_id,allergen,reaction,severity,noted_date) VALUES(?,?,?,?,CURDATE())")->execute([$id,$a,$r,$sev]);}
$DEMO_USERS="'doctor1','nurse1','midwife1','bhw1','resident1'";
if($_SERVER['REQUEST_METHOD']=='POST'){
 if($_POST['act']=='load'){
  if($pdo->query("SELECT COUNT(*) FROM patients WHERE token LIKE 'demo%'")->fetchColumn())$m='Demo data already exists. Remove it first before loading again.';
  else{$d=fn($x)=>date('Y-m-d',strtotime("-$x"));
   $doc=usr('doctor1','doctor123','doctor',null,'Dr. Ramon Villanueva','DEMO-0001');
   $nur=usr('nurse1','nurse1234','nurse',null,'Nurse Joy Aquino','DEMO-0002');
   $mid=usr('midwife1','midwife123','midwife',null,'Midwife Elena Cruz','DEMO-0003');
   $bhw=usr('bhw1','bhw12345','bhw','Purok 1','BHW Mario Santos');
   $res=usr('resident1','resident123','resident',null,'Maria Santos');
   $b1=mk('Baby Juan Santos',$d('45 days'),'M','Purok 1','Maria Santos',0,0);vax($b1,$d('45 days'),0,$nur);
   $b2=mk('Baby Ana Reyes',$d('150 days'),'F','Purok 2','Liza Reyes',0,0);vax($b2,$d('150 days'),42,$nur);alg($b2,'Egg','Rash and itching','Mild');
   $b3=mk('Baby Luis Garcia',$d('400 days'),'M','Purok 3','Ana Garcia',0,0);vax($b3,$d('400 days'),270,$doc);alg($b3,'Penicillin','Facial swelling','Moderate');
   $s1=mk('Lolo Pedro Ramos',$d('68 years'),'M','Purok 1',null,1,0);vit($s1,40,130,85,null,$bhw);vit($s1,10,156,98,null,$nur);
   $s2=mk('Lola Carmen Flores',$d('72 years'),'F','Purok 2',null,1,1);vit($s2,20,138,88,110,$nur);vit($s2,5,142,92,142,$doc);
   $w1=mk('Rosa Mendoza',$d('28 years'),'F','Purok 1',null,0,0);preg_start($w1,200,[60,35],$mid);
   $w2=mk('Liza Cruz',$d('24 years'),'F','Purok 3',null,0,0);preg_start($w2,255,[20],$mid);
   foreach([$b1,$b2]as $x)$pdo->prepare("INSERT IGNORE INTO user_patients VALUES(?,?)")->execute([$res,$x]);
   $pdo->prepare("INSERT INTO appointments(patient_id,appt_date,purpose) VALUES(?,?,?)")->execute([$b1,date('Y-m-d',strtotime('+3 days')),'Vaccination']);
   auto_notify();audit('Demo data loaded');$m='Demo data loaded.';}}
 if($_POST['act']=='remove'){$pdo->exec("DELETE FROM patients WHERE token LIKE 'demo%'");$pdo->exec("DELETE FROM users WHERE username IN($DEMO_USERS)");audit('Demo data removed');$m='Demo data removed.';}
}
head('Demo');if($m)echo "<div class='alert alert-info'>".h($m)."</div>";?>
<h4>Demo Data</h4><p>For the defense: creates sample patients and accounts so there is something to show right away. Demo patients have no contact numbers, so no SMS will be sent to real people.</p>
<div class="row g-3"><div class="col-md-6"><div class="card"><div class="card-body"><b>Contents:</b>
<ul class="mb-2"><li>3 babies (Due, Overdue, and nearly complete vaccines), 2 of them with allergies</li><li>2 seniors with HPN/DM and high readings</li><li>2 pregnant women (one close to delivery)</li><li>1 appointment request</li><li>Every vaccine, BP/sugar reading, and prenatal check-up has a recorded health worker</li></ul>
<b>Accounts:</b><br><code>doctor1 / doctor123</code><br><code>nurse1 / nurse1234</code><br><code>midwife1 / midwife123</code><br><code>bhw1 / bhw12345</code> (Purok 1)<br><code>resident1 / resident123</code> (parent of Baby Juan and Baby Ana)</div></div></div>
<div class="col-md-6 d-flex flex-column gap-2"><form method="post"><input type="hidden" name="act" value="load"><button class="btn btn-success w-100">Load demo data</button></form>
<form method="post" onsubmit="return confirm('All demo patients and accounts will be deleted. Are you sure?')"><input type="hidden" name="act" value="remove"><button class="btn btn-outline-danger w-100">Remove demo data</button></form></div></div>
<?php foot();
