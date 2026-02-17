<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Config ---------- */
$DB_DSN  = "mysql:host=localhost;dbname=cmms;charset=utf8mb4";
$DB_USER = "root";
$DB_PASS = "";

/* Project base (works in subfolders like /Ma or /CampusCMMS) */
define('APP_BASE', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
function url_to($path) { return APP_BASE . '/' . ltrim($path, '/'); }
function redirect_to($path) { header('Location: ' . url_to($path)); exit; }

/* ---------- DB ---------- */
try {
  $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  die('DB connection failed.');
}

/* ---------- Auth helpers ---------- */
function verify_dual($plain, $stored) {
  if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')) {
    return password_verify($plain, $stored);
  }
  return hash_equals($stored, hash('sha256', $plain));
}
function redirect_role($role) {
  switch ($role) {
    case 'technician': redirect_to('staff/staff_dashboard.php');
    case 'reporter':
    default: redirect_to('user_dashboard.php');
  }
}

/* ---------- Controller ---------- */
$role_sel = $_POST['role'] ?? $_GET['role'] ?? 'reporter';
$created  = ($_GET['created'] ?? '') === '1';
$pending  = ($_GET['pending'] ?? '') === '1';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $role_sel = $_POST['role'] ?? $role_sel;

  if ($email === '' || $pass === '') {
    $err = 'Please enter email and password.';
  } else {
    $st = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
    $st->execute([$email, $role_sel]);
    $u = $st->fetch();

    if (!$u) {
      $err = "No {$role_sel} account found for this email.";
    } elseif (!verify_dual($pass, $u['password_hash'])) {
      $err = 'Invalid email or password.';
    } elseif (!(int)$u['is_active']) {
      $err = ($role_sel === 'technician')
        ? 'Your technician account is pending approval.'
        : 'Your account is not active.';
    } else {
      $_SESSION['user_id']    = $u['user_id'];
      $_SESSION['email']      = $u['email'];
      $_SESSION['role']       = $u['role'];
      $_SESSION['first_name'] = $u['first_name'] ?? '';
      $_SESSION['last_name']  = $u['last_name'] ?? '';
      $_SESSION['name']       = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
      redirect_role($u['role']);
    }
  }
}

$role_names = ['reporter'=>'Reporter Portal','technician'=>'Technician Portal'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - Campus CMMS</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
  :root{
    --bg:#f6f8fb;
    --card:#ffffff;
    --ink:#0f172a;
    --muted:#64748b;
    --line:#e5e7eb;
    --brand:#3b82f6;
    --brand-2:#6366f1;
    --shadow:0 10px 30px rgba(15,23,42,.08);
    --shadow-2:0 30px 80px rgba(15,23,42,.12);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--bg); font:400 16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Inter",Arial,sans-serif;
    padding:24px; position:relative; overflow:hidden;
  }

  /* ---------- BACKGROUND IMAGE ---------- */
  .bg {
    position:fixed; inset:0; z-index:-2;
    background: #000 url('<?= url_to("image/bg1.jpg") ?>') center/cover no-repeat;
    filter: saturate(1) contrast(1.05) brightness(.9);
  }
  .bg::after{
    content:""; position:absolute; inset:0;
    background: linear-gradient(180deg, rgba(255,255,255,.75), rgba(255,255,255,.75));
  }

  /* ---------- LOGIN CARD ---------- */
  .login{
    width:100%; max-width:460px; background:var(--card); border:1px solid var(--line);
    border-radius:24px; box-shadow:var(--shadow-2); overflow:hidden;
  }
  .login__head{
    padding:28px 24px 8px; text-align:center; background:#fff;
  }
  .logo-wrap{
    width:84px; height:84px; margin:0 auto 10px; border-radius:20px;
    display:grid; place-items:center; background:linear-gradient(135deg, rgba(59,130,246,.1), rgba(99,102,241,.1));
    border:1px solid var(--line);
    animation:bob 3.2s ease-in-out infinite;
  }
  .logo{
    max-width:60px; max-height:60px; display:block; filter: drop-shadow(0 4px 8px rgba(0,0,0,.1));
  }
  @keyframes bob{
    0%,100%{transform:translateY(0)}
    50%{transform:translateY(-8px)}
  }
  .title{font-weight:800; font-size:26px; color:var(--ink)}
  .subtitle{color:var(--muted); margin-top:4px; font-weight:600}
  .login__body{ padding:24px; }
  .group{ margin-bottom:16px; }
  label{ display:block; font-size:14px; font-weight:700; color:#1f2937; margin-bottom:6px }
  input, select{
    width:100%; padding:12px 14px; border:2px solid var(--line); border-radius:12px;
    background:#fff; color:var(--ink); font-size:15px; transition:border-color .15s, box-shadow .15s;
  }
  input:focus, select:focus{ outline:none; border-color:var(--brand); box-shadow:0 0 0 4px rgba(59,130,246,.12) }
  .pw-wrap{ position:relative }
  .pw-toggle{
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    font-size:12px; padding:6px 8px; border-radius:8px; cursor:pointer; color:var(--muted); user-select:none;
  }
  .alert{
    padding:12px 14px; border-radius:12px; font-size:14px; margin-bottom:14px; border:1px solid;
  }
  .alert--ok{ background:#f0fdf4; border-color:#bbf7d0; color:#166534 }
  .alert--err{ background:#fef2f2; border-color:#fecaca; color:#991b1b }

  .btn{
    width:100%; padding:14px 18px; border:none; border-radius:12px;
    background:linear-gradient(135deg,var(--brand),var(--brand-2)); color:#fff;
    font-weight:800; letter-spacing:.2px; cursor:pointer; box-shadow:var(--shadow);
    transition:transform .12s, box-shadow .12s;
  }
  .btn:hover{ transform:translateY(-1px) }

  .foot{
    margin-top:14px; padding-top:14px; border-top:1px solid var(--line); text-align:center;
  }
  .foot a{ color:var(--brand); text-decoration:none; font-weight:700 }
</style>
</head>
<body>

  <!-- ✅ Background image element -->
  <div class="bg"></div>

  <div class="login">
    <div class="login__head">
      <div class="logo-wrap">
        <img class="logo" src="<?= url_to('image/logo.png') ?>" alt="FixMate Logo"
             onerror="this.style.opacity=0; this.parentElement.style.background='transparent';">
      </div>
      <div class="title">Welcome back</div>
      <div class="subtitle"><?= $role_names[$role_sel] ?></div>
    </div>

    <form class="login__body" method="post" action="">
      <?php if ($created): ?>
        <div class="alert alert--ok">Account created successfully!
          <?php if ($pending): ?><br><strong>Note:</strong> Your technician account is pending approval.<?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($err): ?>
        <div class="alert alert--err"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <div class="group">
        <label for="role">Select Role</label>
        <select name="role" id="role" onchange="updateSubtitle(this.value)">
          <option value="reporter"   <?= $role_sel==='reporter'?'selected':'' ?>>Reporter (Teacher/Staff)</option>
          <option value="technician" <?= $role_sel==='technician'?'selected':'' ?>>Technician</option>
        </select>
      </div>

      <div class="group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" required placeholder="your.email@example.com">
      </div>

      <div class="group">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" required placeholder="Enter your password">
          <span class="pw-toggle" onclick="togglePw()">Show</span>
        </div>
      </div>

      <button type="submit" class="btn">Sign in</button>

      <div class="foot">
        <a href="<?= url_to('forgot_password.php') ?>">Forgot password?</a> •
        <a href="<?= url_to('signup.php') ?>">Create an account</a>
      </div>
    </form>
  </div>

<script>
  function togglePw(){
    const i = document.getElementById('password');
    const t = document.querySelector('.pw-toggle');
    if(i.type==='password'){ i.type='text'; t.textContent='Hide'; } else { i.type='password'; t.textContent='Show'; }
  }
  function updateSubtitle(role){
    const names = { reporter:'Reporter Portal', technician:'Technician Portal' };
    document.querySelector('.subtitle').textContent = names[role] || 'Portal';
  }
</script>
</body>
</html>
