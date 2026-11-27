<?php require 'config.php';require_login();
$s=$pdo->prepare("SELECT * FROM resident_ids WHERE id=?");$s->execute([(int)($_GET['id']??0)]);$r=$s->fetch();
$side=($_GET['s']??'f')==='b'?'file_back':'file_front';
if(!$r||empty($r[$side]))die('Not found.');
$u=me();
if($u['role']=='resident'){if((int)$r['user_id']!==(int)$u['id'])die('No access.');}
elseif(!staff_sees_resident($r['user_id']))die('No access.');
$path=__DIR__.'/uploads/ids/'.basename($r[$side]);
if(!is_file($path))die('File is missing.');
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if($u['role']!='resident')audit('Viewed resident ID #'.$r['id']);
while(ob_get_level())ob_end_clean();
header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');
readfile($path);
