<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* base helpers (same as login) */
define('APP_BASE', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
function url_to($p){ return APP_BASE . '/' . ltrim($p, '/'); }
function redirect_to($p){ header('Location: ' . url_to($p)); exit; }

try {
  $pdo = new PDO("mysql:host=localhost;dbname=cmms;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
} catch(Throwable $e){ die("DB connection failed."); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function normalize_role($r){ $r=strtolower(trim((string)$r)); return in_array($r,['reporter','technician'],true)?$r:'reporter'; }

$err = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $err = 'Invalid form token. Please try again.';
  } else {
    $username = trim((string)($_POST['username'] ?? ''));
    $first    = trim((string)($_POST['first_name'] ?? ''));
    $last     = trim((string)($_POST['last_name'] ?? ''));
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass1    = (string)($_POST['password'] ?? '');
    $pass2    = (string)($_POST['confirm_password'] ?? '');
    $role     = normalize_role($_POST['role'] ?? 'reporter');
    $reporter_type = ($_POST['reporter_type'] ?? 'staff') === 'teacher' ? 'teacher' : 'staff';

    if ($username==='' || $first==='' || $last==='' || $email==='' || $pass1==='' || $pass2==='') {
      $err = 'Please fill all required fields.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,}$/', $username)) {
      $err = 'Username must be at least 3 characters (letters, numbers, dot, underscore, dash).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = 'Invalid email address.';
    } elseif ($pass1 !== $pass2) {
      $err = 'Passwords do not match.';
    } else {
      $u1 = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
      $u1->execute([$username]);
      $u2 = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
      $u2->execute([$email]);

      if ($u1->fetch())      $err = 'This username is already taken.';
      elseif ($u2->fetch())  $err = 'An account with this email already exists.';
      else {
        try {
          $hash = password_hash($pass1, PASSWORD_BCRYPT);
          $is_active = ($role === 'technician') ? 0 : 1; // technicians need approval

          $ins = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, is_active, first_name, last_name)
            VALUES (?, ?, ?, ?, ?, ?, ?)
          ");
          $ins->execute([$username, $email, $hash, $role, $is_active, $first, $last]);

          $pending = ($role === 'technician') ? '1' : '0';
          redirect_to('login.php?created=1&pending='.$pending.'&role='.$role);
        } catch (Throwable $tx) {
          $err = 'Registration failed. Please try again.';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign Up - Campus CMMS</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
  :root{
    --bg:#f6f8fb;
    --ink:#0f172a;
    --muted:#64748b;
    --line:#e5e7eb;
    --brand:#3b82f6;
    --brand-2:#6366f1;
    --radius:16px;
    --shadow:0 10px 30px rgba(15,23,42,.08);
    --shadow-2:0 30px 80px rgba(15,23,42,.12);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    background: var(--bg);
    font: 400 16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Inter",Arial,sans-serif;
    padding:24px; position:relative; overflow:hidden;
  }
  /* ------- OPTIONAL: maintenance background ------- */
  .bg {
    position:fixed; inset:0; z-index:-2;
    background: #000 url('<?= url_to("image/bg1.jpg") ?>') center/cover no-repeat;
    filter: saturate(1) contrast(1.05) brightness(.9);
  }
  .bg::after{ /* soft overlay for readability */
    content:""; position:absolute; inset:0;
    background: linear-gradient(180deg, rgba(255,255,255,.75), rgba(255,255,255,.75));
  }
  /* Add/remove the <div class="bg"></div> in <body> to toggle image */

  .card{
    width:100%; max-width:560px; background:#fff; border:1px solid var(--line);
    border-radius:24px; box-shadow:var(--shadow-2); overflow:hidden;
  }
  .head{
    padding:26px 24px 8px; text-align:center; background:#fff;
  }
  .logo-wrap{
    width:90px; height:90px; margin:0 auto 12px; border-radius:22px;
    display:grid; place-items:center;
    background:linear-gradient(135deg, rgba(59,130,246,.08), rgba(99,102,241,.08));
    border:1px solid var(--line);
    animation: bob 3.2s ease-in-out infinite;
  }
  .logo{ max-width:64px; max-height:64px; display:block; filter: drop-shadow(0 4px 8px rgba(0,0,0,.1)); }
  @keyframes bob{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
  .title{font-weight:800; font-size:26px; color:var(--ink)}
  .subtitle{color:var(--muted); margin-top:4px; font-weight:600}
  .body{ padding:24px; background:#fff; }
  .row{ display:grid; grid-template-columns:1fr 1fr; gap:14px }
  .group{ margin-bottom:14px }
  label{ display:block; font-size:14px; font-weight:700; color:#1f2937; margin-bottom:6px }
  input, select{
    width:100%; padding:12px 14px; border:2px solid var(--line); border-radius:12px;
    background:#fff; color:var(--ink); font-size:15px; transition:border-color .15s, box-shadow .15s;
  }
  input:focus, select:focus{ outline:none; border-color:var(--brand); box-shadow:0 0 0 4px rgba(59,130,246,.12) }
  .small{ color:#6b7280; font-size:12px; margin-top:6px; display:block }

  .alert{
    padding:12px 14px; border-radius:12px; font-size:14px; margin-bottom:14px; border:1px solid;
  }
  .alert--err{ background:#fef2f2; border-color:#fecaca; color:#991b1b }

  .btn{
    width:100%; padding:14px 18px; border:none; border-radius:12px;
    background:linear-gradient(135deg, var(--brand), var(--brand-2)); color:#fff;
    font-weight:800; letter-spacing:.2px; cursor:pointer; box-shadow:var(--shadow);
    transition:transform .12s, box-shadow .12s;
  }
  .btn:hover{ transform:translateY(-1px) }

  .foot{ margin-top:14px; padding-top:14px; border-top:1px solid var(--line); text-align:center }
  .foot a{ color:var(--brand); text-decoration:none; font-weight:700 }

  /* Hide the User Type dropdown when role = technician (no JS needed) */
#userTypeBox { display: block; }
.body:has(#role option[value="technician"]:checked) #userTypeBox {
  display: none !important;
}

  @media (max-width:560px){ .row{grid-template-columns:1fr} }
</style>
</head>
<body>
  <!-- Toggle the background image by keeping/removing this line -->
  <div class="bg"></div>

  <div class="card" role="region" aria-label="Create account">
    <div class="head">
      <div class="logo-wrap">
        <img class="logo" src="<?= url_to('image/logo.png') ?>" alt="FixMate"
             onerror="this.style.opacity=0; this.parentElement.style.background='transparent';">
      </div>
      <div class="title">Create your account</div>
      <div class="subtitle">Reporter or Technician</div>
    </div>

    <form class="body" method="post" action="">
      <?php if ($err): ?>
        <div class="alert alert--err"><?= e($err) ?></div>
      <?php endif; ?>

      <div class="group">
        <label for="username">Username *</label>
        <input id="username" name="username" required placeholder="unique login name" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username">
        <span class="small">Letters, numbers, dot, underscore or dash (min 3).</span>
      </div>

      <div class="row">
        <div class="group">
          <label for="first_name">First name *</label>
          <input id="first_name" name="first_name" required value="<?= e($_POST['first_name'] ?? '') ?>" autocomplete="given-name">
        </div>
        <div class="group">
          <label for="last_name">Last name *</label>
          <input id="last_name" name="last_name" required value="<?= e($_POST['last_name'] ?? '') ?>" autocomplete="family-name">
        </div>
      </div>

      <div class="group">
        <label for="email">Email *</label>
        <input id="email" type="email" name="email" required placeholder="your.email@example.com" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
      </div>

      <div class="row">
        <div class="group">
          <label for="password">Password *</label>
          <input id="password" type="password" name="password" required minlength="6" placeholder="At least 6 characters" autocomplete="new-password">
        </div>
        <div class="group">
          <label for="confirm_password">Confirm password *</label>
          <input id="confirm_password" type="password" name="confirm_password" required minlength="6" placeholder="Re-type your password" autocomplete="new-password">
        </div>
      </div>

      <div class="group">
        <label for="role">Role *</label>
        <select id="role" name="role" required onchange="toggleReporterType(this.value)">
          <option value="reporter"   <?= (($_POST['role'] ?? '')==='reporter')?'selected':'' ?>>Reporter</option>
          <option value="technician" <?= (($_POST['role'] ?? '')==='technician')?'selected':'' ?>>Technician (requires approval)</option>
        </select>
      </div>

      <!-- New field for all user types -->
<div id="userTypeBox" class="group">
  <label for="user_type">User Type *</label>
  <select id="user_type" name="user_type">
    <option value="staff"   <?= (($_POST['user_type'] ?? 'staff')==='staff')?'selected':'' ?>>Staff</option>
    <option value="teacher" <?= (($_POST['user_type'] ?? '')==='teacher')?'selected':'' ?>>Teacher</option>
    <option value="student" <?= (($_POST['user_type'] ?? '')==='student')?'selected':'' ?>>Student</option>
    <option value="external" <?= (($_POST['user_type'] ?? '')==='external')?'selected':'' ?>>External</option>
  </select>
  <span class="small">Select your user category for the reporter role.</span>
</div>


      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

      <button type="submit" class="btn">Create Account</button>

      <div class="foot">
        <a href="<?= url_to('login.php') ?>">Already have an account? Sign in →</a>
      </div>
    </form>
  </div>

<script>
  function toggleReporterType(role){
    document.getElementById('reporterTypeBox').style.display = (role === 'reporter') ? 'block' : 'none';
  }
</script>
</body>
</html>
