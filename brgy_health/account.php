<?php require 'config.php';require 'layout.php';require_login();$m='';
if($_SERVER['REQUEST_METHOD']=='POST'){$s=$pdo->prepare("SELECT password_hash FROM users WHERE id=?");$s->execute([me()['id']]);
 if(!password_verify($_POST['old'],$s->fetchColumn()))$m='The old password is incorrect.';
 elseif(strlen($_POST['new'])<8||$_POST['new']==='admin123')$m='Must be at least 8 characters and not the default password.';
 else{$pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['new'],PASSWORD_DEFAULT),me()['id']]);unset($_SESSION['must_change']);audit('Password changed');$m='Password changed.';}}
head('Account');if(!empty($_SESSION['must_change']))echo "<div class='alert alert-danger'>Please change the default password before continuing.</div>";?>
<div class="col-md-4"><div class="card"><div class="card-header">Change password</div><form method="post" class="card-body">
<?php if($m)echo "<div class='alert alert-info'>".h($m)."</div>";?>
<input type="password" name="old" class="form-control mb-2" placeholder="Old password" required><input type="password" name="new" class="form-control mb-3" placeholder="New password (8+)" required><button class="btn btn-success">Save</button></form></div></div>
<?php foot();
