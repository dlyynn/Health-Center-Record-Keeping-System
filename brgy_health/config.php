<?php
date_default_timezone_set('Asia/Manila');
$pdo=new PDO('mysql:host=localhost;dbname=brgy_health;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function age($bd){return (new DateTime($bd))->diff(new DateTime())->y;}
function audit($a,$pid=null){global $pdo;$pdo->prepare("INSERT INTO audit_log(user_id,action,patient_id) VALUES(?,?,?)")->execute([me()['id']??null,$a,$pid]);}
// [vaccine, dose, days after birth]. To be verified by the midwife against the DOH EPI schedule.
$SCHEDULE=[['BCG',1,0],['Hepatitis B',1,0],['Pentavalent',1,42],['Pentavalent',2,70],['Pentavalent',3,98],['OPV',1,42],['OPV',2,70],['OPV',3,98],['PCV',1,42],['PCV',2,70],['PCV',3,98],['IPV',1,98],['IPV',2,270],['MMR',1,270],['MMR',2,365]];
function vaccine_status($pid,$bd){global $pdo,$SCHEDULE;
 $s=$pdo->prepare("SELECT v.vaccine,v.dose_no,v.date_given,u.full_name by_name,u.role by_role FROM vaccinations v LEFT JOIN users u ON u.id=v.given_by WHERE v.patient_id=?");$s->execute([$pid]);
 $g=[];foreach($s as $r)$g[$r['vaccine'].$r['dose_no']]=['d'=>$r['date_given'],'by'=>$r['by_name'],'role'=>$r['by_role']];
 $out=[];$today=new DateTime('today');
 foreach($SCHEDULE as [$v,$d,$off]){
  $due=(new DateTime($bd))->modify("+$off days");$k=$v.$d;
  if(isset($g[$k]))$st='Done';
  elseif($today>(clone $due)->modify('+7 days'))$st='Overdue';
  elseif($today>=(clone $due)->modify('-7 days'))$st='Due';
  else $st='Upcoming';
  $out[]=['vaccine'=>$v,'dose'=>$d,'due'=>$due->format('Y-m-d'),'status'=>$st,'given'=>$g[$k]['d']??null,'by'=>$g[$k]['by']??null,'role'=>$g[$k]['role']??null];
 }
 return $out;}
function vflag($sys,$dia,$sugar=null){
 if($sys>=140||$dia>=90)return 'High BP';
 if($sugar!==null&&$sugar>=126)return 'High sugar';
 return '';}
function badge($st){$c=['Done'=>'success','Overdue'=>'danger','Due'=>'warning','Upcoming'=>'secondary'][$st]??'secondary';return "<span class='badge bg-$c'>".h($st)."</span>";}

// ===== AUTH / ROLES =====
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();
// CSRF: automatically added to every POST form and checked on every POST
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(16));
if($_SERVER['REQUEST_METHOD']=='POST'&&!hash_equals($_SESSION['csrf'],$_POST['csrf']??''))die('Invalid request (CSRF).');
ob_start(fn($h)=>preg_replace('/<form([^>]*method="post"[^>]*)>/i','<form$1><input type="hidden" name="csrf" value="'.($_SESSION['csrf']??'').'">',$h));
function me(){return $_SESSION['u']??null;}
function require_login(){if(!me()){header('Location: login.php');exit;}if(!empty($_SESSION['must_change'])&&basename($_SERVER['SCRIPT_NAME'])!='account.php'){header('Location: account.php');exit;}}
function require_staff(){require_login();if(me()['role']=='resident'){header('Location: portal.php');exit;}}
function require_resident(){require_login();if(me()['role']!='resident'){header('Location: index.php');exit;}}
function require_admin(){require_staff();if(me()['role']!='admin')die('Admins only.');}
function my_patient_ids(){global $pdo;$s=$pdo->prepare("SELECT patient_id FROM user_patients WHERE user_id=?");$s->execute([me()['id']]);return array_map('intval',array_column($s->fetchAll(),'patient_id'));}
function can_view($p){$u=me();if(!$u||!$p)return false;
 if($u['role']=='resident')return in_array((int)$p['id'],my_patient_ids());
 if($u['role']=='bhw')return ($p['purok']??null)===$u['purok'];
 return true;}
// ===== NOTIFICATIONS =====
function notify($pid,$msg,$key=null){global $pdo;$pdo->prepare("INSERT IGNORE INTO notifications(patient_id,message,dedupe_key,sms_status) SELECT ?,?,?,IF(contact IS NOT NULL AND contact<>'' AND sms_optin=1 AND ?=1,'pending','none') FROM patients WHERE id=?")->execute([$pid,$msg,$key,SMS_ENABLED?1:0,$pid]);}
function unread_count(){global $pdo;$ids=my_patient_ids();if(!$ids)return 0;$in=implode(',',$ids);return (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND patient_id IN($in)")->fetchColumn();}
function auto_notify_daily(){$k=date('Y-m-d');if(($_SESSION['an']??'')!=$k){auto_notify();$_SESSION['an']=$k;}}
function auto_notify(){global $pdo;$today=new DateTime('today');
 foreach($pdo->query("SELECT * FROM patients")->fetchAll() as $p){$id=$p['id'];$a=age($p['birthdate']);$n=$p['full_name'];
  if($a<5)foreach(vaccine_status($id,$p['birthdate']) as $r){$k=$r['vaccine'].$r['dose'];
   if($r['status']=='Due')notify($id,"$n's vaccine is coming up: {$r['vaccine']} dose {$r['dose']} (due {$r['due']}). Please visit the Health Center.","due:$k");
   if($r['status']=='Overdue')notify($id,"OVERDUE vaccine for $n: {$r['vaccine']} dose {$r['dose']} (was due {$r['due']}). Please get vaccinated.","over:$k");}
  if($p['has_hpn']||$p['has_dm']||$a>=60){
   $s=$pdo->prepare("SELECT MAX(date_taken) d FROM vitals WHERE patient_id=?");$s->execute([$id]);$d=$s->fetch()['d'];
   if(!$d||$today>(new DateTime($d))->modify('+30 days'))notify($id,"Reminder: it is time to have your BP/sugar checked at the Health Center.","vit:".date('Y-m'));}
  $s=$pdo->prepare("SELECT * FROM prenatal WHERE patient_id=? AND active=1");$s->execute([$id]);
  foreach($s->fetchAll() as $pr){$edd=(new DateTime($pr['lmp']))->modify('+280 days');
   if($today>=(clone $edd)->modify('-30 days')&&$today<=$edd)notify($id,"Your due date is near (".$edd->format('M d, Y')."). Please prepare and attend your check-up.","edd:{$pr['id']}");
   $v=$pdo->prepare("SELECT MAX(visit_date) d FROM prenatal_visits WHERE prenatal_id=?");$v->execute([$pr['id']]);$d=$v->fetch()['d'];
   if(!$d||$today>(new DateTime($d))->modify('+30 days'))notify($id,"Reminder: it is time for your prenatal check-up.","pnv:{$pr['id']}:".date('Y-m'));}
 }
 foreach($pdo->query("SELECT * FROM appointments WHERE status='Confirmed' AND appt_date=DATE_ADD(CURDATE(),INTERVAL 1 DAY)")->fetchAll() as $r)
  notify($r['patient_id'],"Reminder: your appointment is tomorrow ({$r['purpose']}).","appt:{$r['id']}");}

// ===== SMS (Semaphore) =====
require_once __DIR__.'/sms_config.php';
function sms_send($number,$msg){
 if(!SMS_ENABLED)return [false,'SMS disabled'];
 $n=preg_replace('/\D/','',(string)$number);if(strpos($n,'63')===0)$n='0'.substr($n,2);
 if(!preg_match('/^09\d{9}$/',$n))return [false,'Invalid number'];
 $f=['apikey'=>SMS_API_KEY,'number'=>$n,'message'=>$msg];if(SMS_SENDER!=='')$f['sendername']=SMS_SENDER;
 $ch=curl_init('https://api.semaphore.co/api/v4/messages');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($f),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
 $res=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 if($res===false)return [false,$err?:'No connection'];
 return [$code>=200&&$code<300,substr((string)$res,0,200)];}
function send_pending_sms($limit=20){global $pdo;$sent=0;$fail=0;
 foreach($pdo->query("SELECT n.id,n.message,p.contact FROM notifications n JOIN patients p ON p.id=n.patient_id WHERE n.sms_status='pending' ORDER BY n.id LIMIT ".(int)$limit)->fetchAll() as $r){
  [$ok,$info]=sms_send($r['contact'],$r['message']);
  $pdo->prepare("UPDATE notifications SET sms_status=?,sms_sent_at=NOW(),sms_error=? WHERE id=?")->execute([$ok?'sent':'failed',$ok?null:substr($info,0,200),$r['id']]);
  $ok?$sent++:$fail++;}
 return [$sent,$fail];}

// ===== STAFF / ALLERGY HELPERS =====
function staff_list(){global $pdo;return $pdo->query("SELECT id,full_name,role FROM users WHERE role<>'resident' ORDER BY full_name")->fetchAll();}
function allergies($pid){global $pdo;$s=$pdo->prepare("SELECT * FROM patient_allergies WHERE patient_id=? ORDER BY FIELD(severity,'Severe','Moderate','Mild'),id");$s->execute([$pid]);return $s->fetchAll();}
function who($name,$role){return $name?h($name).' <small class="text-muted">('.h(ucfirst($role)).')</small>':'<span class="text-muted">—</span>';}

// ===== RESIDENT ID UPLOAD / ACCESS =====
function id_types(){return ['PhilSys National ID',"Driver's License","Voter's ID",'UMID','Postal ID','Senior Citizen ID','PWD ID','School ID','Barangay ID','Passport','Other'];}
function purposes(){return ['Vaccination','Prenatal check-up','BP/Sugar check-up','Consultation (check-up)','Medical certificate','Other'];}
function save_id_upload($key,$required=false){
 if(empty($_FILES[$key])||$_FILES[$key]['error']===UPLOAD_ERR_NO_FILE){if($required)throw new Exception('Please upload a photo of the ID.');return null;}
 $f=$_FILES[$key];
 if($f['error']!==UPLOAD_ERR_OK)throw new Exception(in_array($f['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE])?'The file is too large (2MB maximum).':'The file could not be uploaded.');
 if($f['size']>2*1024*1024)throw new Exception('The file is too large (2MB maximum).');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
 $ext=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'][$mime]??null;
 if(!$ext)throw new Exception('Only JPG, PNG, or PDF files are allowed.');
 $dir=__DIR__.'/uploads/ids';if(!is_dir($dir))mkdir($dir,0755,true);
 $name=bin2hex(random_bytes(16)).'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))throw new Exception('The file could not be saved.');
 return $name;}
function can_manage_residents(){return in_array(me()['role']??'',['admin','doctor','nurse','midwife'],true);}
function staff_sees_resident($uid){global $pdo;$u=me();if(!$u||$u['role']=='resident')return false;if($u['role']!='bhw')return true;
 $s=$pdo->prepare("SELECT COUNT(*) FROM user_patients up JOIN patients p ON p.id=up.patient_id WHERE up.user_id=? AND p.purok=?");$s->execute([$uid,$u['purok']]);return $s->fetchColumn()>0;}
