<?php
require 'config.php';
require 'layout.php';

if (me()) {
    header('Location: index.php');
    exit;
}

$err = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = trim($_POST['full_name']);
    $u = trim($_POST['username']);
    $bd = $_POST['birthdate'];
    $cn = trim($_POST['child_name']);
    $cb = $_POST['child_bd'];
    $dt = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d <= date('Y-m-d');
    $saved = [];

    try {
        if ($n === '') {
            throw new Exception('Please enter your full name.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $u)) {
            throw new Exception('Username: 4-30 letters, numbers, or _ only.');
        }
        if (strlen($_POST['password']) < 8) {
            throw new Exception('Password: at least 8 characters.');
        }
        if ($_POST['password'] !== $_POST['password2']) {
            throw new Exception('The two passwords do not match.');
        }
        if (!$dt($bd)) {
            throw new Exception('Please enter a valid birthday.');
        }

        $ty = $_POST['id_type'] ?? '';
        if (!in_array($ty, id_types(), true)) {
            throw new Exception('Please select the ID type.');
        }

        $f = save_id_upload('id_front', true);
        $saved[] = $f;

        $b = save_id_upload('id_back');
        if ($b) {
            $saved[] = $b;
        }

        $pdo->prepare("INSERT INTO users(username, password_hash, full_name, role, status) VALUES(?, ?, ?, 'resident', 'pending')")
            ->execute([$u, password_hash($_POST['password'], PASSWORD_DEFAULT), $n]);
        $uid = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO resident_ids(user_id, id_type, file_front, file_back) VALUES(?, ?, ?, ?)")
            ->execute([$uid, $ty, $f, $b]);

        // Look up the record of the resident and of the child (name + birthday). The user is not told whether a match was found.
        $pairs = [[$n, $bd]];
        if ($cn !== '' && $dt($cb)) {
            $pairs[] = [$cn, $cb];
        }

        $find = $pdo->prepare("SELECT id FROM patients WHERE LOWER(full_name) = LOWER(?) AND birthdate = ?");
        foreach ($pairs as [$pn, $pb]) {
            $find->execute([$pn, $pb]);
            if ($pid = $find->fetchColumn()) {
                $pdo->prepare("INSERT IGNORE INTO user_patients VALUES(?, ?)")->execute([$uid, $pid]);
            }
        }

        audit('Resident registration (pending): ' . $u);
        $ok = true;
    } catch (Exception $e) {
        foreach ($saved as $x) {
            @unlink(__DIR__ . '/uploads/ids/' . $x);
        }
        $err = $e instanceof PDOException ? 'That username is already taken.' : $e->getMessage();
    }
}

head('Register');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
  input.date-picker.form-control {
    background-color: #fff;
    min-height: 3rem;
    font-size: 1.05rem;
  }

  .flatpickr-calendar {
    width: 360px;
    font-size: 1.05rem;
  }

  .flatpickr-months .flatpickr-month {
    height: 46px;
  }

  .flatpickr-current-month {
    font-size: 1.25rem;
    padding-top: 4px;
  }

  .flatpickr-current-month .flatpickr-monthDropdown-months,
  .flatpickr-current-month input.cur-year {
    font-size: 1.15rem;
    font-weight: 600;
    padding: 4px 8px;
  }
</style>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <?php if ($ok): ?>
      <div class="alert alert-success">
        <b>Your registration has been submitted.</b><br>
        The Health Center will check your ID. Once approved, you can log in.
      </div>
      <a href="login.php" class="btn btn-success">Go to Log in</a>
    <?php else: ?>
      <div class="card">
        <div class="card-header">📝 Register an account (for residents)</div>
        <form method="post" enctype="multipart/form-data" class="card-body">
          <?php if ($err): ?>
            <div class="alert alert-danger"><?= h($err) ?></div>
          <?php endif; ?>

          <label class="form-label">Full name</label>
          <input name="full_name" class="form-control mb-3" value="<?= h($_POST['full_name'] ?? '') ?>" required>

          <label class="form-label">Birthday</label>
          <input type="text" name="birthdate" class="form-control date-picker mb-3" placeholder="YYYY-MM-DD" value="<?= h($_POST['birthdate'] ?? '') ?>" required>

          <label class="form-label">Username</label>
          <input name="username" class="form-control mb-3" value="<?= h($_POST['username'] ?? '') ?>" required>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Repeat password</label>
              <input type="password" name="password2" class="form-control" required>
            </div>
          </div>

          <hr>
          <h6>🪪 Valid ID (to verify your account)</h6>

          <label class="form-label">ID type</label>
          <select name="id_type" class="form-select mb-3">
            <?php foreach (id_types() as $x): ?>
              <option><?= h($x) ?></option>
            <?php endforeach; ?>
          </select>

          <div class="row g-3 mb-1">
            <div class="col-md-6">
              <label class="form-label">ID front (required)</label>
              <input type="file" name="id_front" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">ID back (optional)</label>
              <input type="file" name="id_back" class="form-control" accept="image/jpeg,image/png,application/pdf">
            </div>
          </div>
          <div class="form-text mb-3">JPG, PNG, or PDF. Up to 2MB per file. Only the Health Center can see your ID.</div>

          <hr>
          <p class="mb-2">For your child (optional): enter the child's name and birthday as registered at the Health Center.</p>
          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <input name="child_name" class="form-control" placeholder="Child's name" value="<?= h($_POST['child_name'] ?? '') ?>">
            </div>
            <div class="col-md-5">
              <input type="text" name="child_bd" class="form-control date-picker" placeholder="YYYY-MM-DD" value="<?= h($_POST['child_bd'] ?? '') ?>">
            </div>
          </div>

          <button class="btn btn-success w-100 py-2">Register</button>

          <div class="text-center mt-3">
            <a href="login.php">Already have an account? Log in</a>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  flatpickr('.date-picker', {
    dateFormat: 'Y-m-d',
    maxDate: 'today',
    allowInput: true
  });
</script>
<?php
foot();