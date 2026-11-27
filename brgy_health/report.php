<?php require 'config.php';require 'layout.php';require_staff();
$mo=preg_match('/^\d{4}-\d{2}$/',$_GET['m']??'')?$_GET['m']:date('Y-m');
$pu=me()['role']=='bhw'?me()['purok']:trim($_GET['purok']??'');
$start="$mo-01";$end=date('Y-m-t',strtotime($start));$pf=$pu===''?'':' AND p.purok='.$pdo->quote($pu);
$R=[];$add=function($s,$i,$v)use(&$R){$R[]=[$s,$i,$v];};
$pts=$pdo->query("SELECT p.* FROM patients p WHERE 1=1$pf")->fetchAll();$b=$sn=$hp=$dm=$ov=0;
foreach($pts as $p){$a=age($p['birthdate']);if($a<5){$b++;if(array_filter(vaccine_status($p['id'],$p['birthdate']),fn($x)=>$x['status']=='Overdue'))$ov++;}if($a>=60)$sn++;$hp+=$p['has_hpn'];$dm+=$p['has_dm'];}
$add('Patients (current)','Total registered',count($pts));$add('Patients (current)','Babies (0-4 years)',$b);$add('Patients (current)','Seniors (60+)',$sn);$add('Patients (current)','With Hypertension',$hp);$add('Patients (current)','With Diabetes',$dm);$add('Patients (current)','Babies with overdue vaccines',$ov);
$add('This month','Newly registered',$pdo->query("SELECT COUNT(*) FROM patients p WHERE DATE(p.created_at) BETWEEN '$start' AND '$end'$pf")->fetchColumn());
$vx=$pdo->query("SELECT v.vaccine,COUNT(*) c FROM vaccinations v JOIN patients p ON p.id=v.patient_id WHERE v.date_given BETWEEN '$start' AND '$end'$pf GROUP BY v.vaccine ORDER BY v.vaccine")->fetchAll();
$add('Vaccines this month','Total doses given',array_sum(array_column($vx,'c')));foreach($vx as $r)$add('Vaccines this month',$r['vaccine'],$r['c']);
foreach($pdo->query("SELECT COALESCE(u.full_name,'(none recorded)') n,COUNT(*) c FROM vaccinations v JOIN patients p ON p.id=v.patient_id LEFT JOIN users u ON u.id=v.given_by WHERE v.date_given BETWEEN '$start' AND '$end'$pf GROUP BY u.id,u.full_name ORDER BY c DESC")->fetchAll() as $r)$add('Vaccines by health worker',$r['n'],$r['c']);
$v=$pdo->query("SELECT COUNT(*) c,COUNT(DISTINCT t.patient_id) d,SUM(t.systolic>=140 OR t.diastolic>=90 OR t.sugar>=126) h FROM vitals t JOIN patients p ON p.id=t.patient_id WHERE t.date_taken BETWEEN '$start' AND '$end'$pf")->fetch();
$add('BP / Sugar this month','Number of readings',(int)$v['c']);$add('BP / Sugar this month','Patients checked',(int)$v['d']);$add('BP / Sugar this month','High readings',(int)$v['h']);
$pv=$pdo->query("SELECT COUNT(*) c,COUNT(DISTINCT pr.patient_id) d FROM prenatal_visits pv JOIN prenatal pr ON pr.id=pv.prenatal_id JOIN patients p ON p.id=pr.patient_id WHERE pv.visit_date BETWEEN '$start' AND '$end'$pf")->fetch();
$add('Prenatal','Check-ups this month',(int)$pv['c']);$add('Prenatal','Pregnant women who had a check-up',(int)$pv['d']);
$add('Prenatal','Active pregnancies (current)',$pdo->query("SELECT COUNT(*) FROM prenatal pr JOIN patients p ON p.id=pr.patient_id WHERE pr.active=1$pf")->fetchColumn());
$add('Prenatal','Expected deliveries this month (EDD)',$pdo->query("SELECT COUNT(*) FROM prenatal pr JOIN patients p ON p.id=pr.patient_id WHERE pr.active=1 AND DATE_ADD(pr.lmp,INTERVAL 280 DAY) BETWEEN '$start' AND '$end'$pf")->fetchColumn());
foreach($pdo->query("SELECT a.status,COUNT(*) c FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.appt_date BETWEEN '$start' AND '$end'$pf GROUP BY a.status")->fetchAll() as $r)$add('Appointments this month',$r['status'],$r['c']);
$add('SMS this month','Sent',$pdo->query("SELECT COUNT(*) FROM notifications n JOIN patients p ON p.id=n.patient_id WHERE n.sms_status='sent' AND DATE(n.sms_sent_at) BETWEEN '$start' AND '$end'$pf")->fetchColumn());
$title='Monthly Report - '.date('F Y',strtotime($start)).($pu!==''?" - Purok $pu":'');
if(($_GET['export']??'')=='csv'){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="report-'.$mo.'.csv"');echo "\xEF\xBB\xBF";$o=fopen('php://output','w');fputcsv($o,[$title]);fputcsv($o,['Category','Item','Count']);foreach($R as $r)fputcsv($o,$r);audit("Report exported $mo");exit;}
$puroks=$pdo->query("SELECT DISTINCT purok FROM patients WHERE purok<>'' ORDER BY purok")->fetchAll(PDO::FETCH_COLUMN);
head('Report');?>
<style>@media print{nav,.d-print-none{display:none!important}}</style>
<style>
.mp{background:#fff;border:2px solid #198754;border-radius:14px;padding:18px;max-width:640px}
.mp-year{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.mp-year b{font-size:2rem;letter-spacing:1px}
.mp-year button{font-size:1.6rem;line-height:1;width:54px;height:54px;border-radius:50%;border:2px solid #198754;background:#fff;color:#198754;font-weight:700}
.mp-year button:hover{background:#198754;color:#fff}
.mp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.mp-grid button{font-size:1.25rem;font-weight:700;padding:16px 4px;border-radius:12px;border:2px solid #cfe8da;background:#f3fbf7;color:#10202e}
.mp-grid button:hover{background:#d6f0e2;border-color:#198754}
.mp-grid button.on{background:#198754;border-color:#198754;color:#fff;box-shadow:0 4px 12px rgba(25,135,84,.4)}
.mp-grid button.now:not(.on){border-style:dashed;border-color:#198754}
@media(max-width:576px){.mp-grid{grid-template-columns:repeat(3,1fr)}}
</style>
<form class="row g-3 mb-3 d-print-none"><input type="hidden" name="m" id="mval" value="<?=h($mo)?>">
<div class="col-12"><div class="mp"><div class="mp-year"><button type="button" onclick="mpYear(-1)" aria-label="Previous year">‹</button><b id="mpy"><?=h(substr($mo,0,4))?></b><button type="button" onclick="mpYear(1)" aria-label="Next year">›</button></div>
<div class="mp-grid" id="mpg"></div></div></div>
<script>(function(){var M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],sel='<?=h($mo)?>',now='<?=date('Y-m')?>',yr=+sel.slice(0,4),f=document.getElementById('mval').form;
function draw(){document.getElementById('mpy').textContent=yr;var g=document.getElementById('mpg');g.innerHTML='';M.forEach(function(n,i){var v=yr+'-'+String(i+1).padStart(2,'0'),b=document.createElement('button');b.type='button';b.textContent=n;if(v===sel)b.className='on';else if(v===now)b.className='now';b.onclick=function(){document.getElementById('mval').value=v;f.submit()};g.appendChild(b)})}
window.mpYear=function(d){yr+=d;draw()};draw()})();</script>
<?php if(me()['role']!='bhw'){?><div class="col-md-4"><select name="purok" class="form-select form-select-lg"><option value="">All puroks</option><?php foreach($puroks as $x)echo "<option ".($pu===$x?'selected':'').">".h($x)."</option>";?></select></div><?php }?>
<div class="col-md-2 col-6"><button class="btn btn-success btn-lg w-100">Show</button></div>
<div class="col-md-2"><a class="btn btn-outline-success btn-lg w-100" href="?m=<?=h($mo)?>&purok=<?=urlencode($pu)?>&export=csv">Excel (CSV)</a></div>
<div class="col-md-2"><button type="button" class="btn btn-outline-secondary btn-lg w-100" onclick="print()">PDF / Print</button></div></form>
<h4><?=h($title)?></h4><p class="text-muted">BRGY. Health Center · Generated on <?=date('M d, Y')?></p>
<table class="table table-sm bg-white"><tr><th>Category</th><th>Item</th><th class="text-end">Count</th></tr>
<?php $last='';foreach($R as $r){?><tr><td class="<?=$r[0]==$last?'text-muted':'fw-bold'?>"><?=$r[0]==$last?'':h($r[0])?></td><td><?=h($r[1])?></td><td class="text-end"><?=h($r[2])?></td></tr><?php $last=$r[0];}?></table>
<?php foot();
