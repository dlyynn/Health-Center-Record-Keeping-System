<?php require 'config.php';require 'layout.php';
if($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()==0)$pdo->prepare("INSERT INTO users(username,password_hash,full_name,role) VALUES('admin',?,'Administrator','admin')")->execute([password_hash('admin123',PASSWORD_DEFAULT)]);
$err='';
if($_SERVER['REQUEST_METHOD']=='POST'){$s=$pdo->prepare("SELECT * FROM users WHERE username=?");$s->execute([trim($_POST['username'])]);$u=$s->fetch();
 if($u&&password_verify($_POST['password'],$u['password_hash'])&&$u['status']!=='active')$err=$u['status']=='pending'?'Your account is still awaiting approval by the Health Center. Please bring a valid ID.':'Your registration was rejected. Please visit the Health Center.';
 elseif($u&&password_verify($_POST['password'],$u['password_hash'])){session_regenerate_id(true);
  $_SESSION['u']=['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role'],'purok'=>$u['purok']];auto_notify();audit('Login');if(password_verify('admin123',$u['password_hash']))$_SESSION['must_change']=1;
  header('Location: '.($u['role']=='resident'?'portal.php':'index.php'));exit;}
 if(!$err){$err='Incorrect username or password.';audit('Failed login: '.substr(trim($_POST['username']),0,40));}}
head('Login');?>
<div class="row justify-content-center"><div class="col-md-5 col-lg-4"><div class="text-center mb-3"><img src="assets/logo.png" alt="Barangay Quirino" height="150" style="background:#fff;border-radius:50%;padding:4px;box-shadow:0 4px 14px rgba(0,0,0,.25)"><h4 class="mt-3 mb-0">Brgy. Quirino Health Center</h4><div class="text-muted">Solano, Nueva Vizcaya</div></div><div class="card"><div class="card-header">🔐 Log in</div><form method="post" class="card-body">
<?php if($err)echo "<div class='alert alert-danger'>".h($err)."</div>";?>
<input class="form-control mb-2" name="username" placeholder="Username" required><input type="password" class="form-control mb-3" name="password" placeholder="Password" required>
<button class="btn btn-success w-100">Log in</button><div class="text-center mt-3"><a href="register.php">No account yet? Register</a></div></form></div></div></div>
<?php foot();
