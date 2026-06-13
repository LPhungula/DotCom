<?php
require_once 'includes/db.php';

if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password =      $_POST['password'] ?? '';
    if (!$username || !$password) {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $conn->prepare("SELECT UserID,Username,Password,Role FROM users WHERE Username=? AND Role='admin' LIMIT 1");
        $stmt->bind_param('s',$username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && password_verify($password, $row['Password'])) {
            $_SESSION['user_id']  = $row['UserID'];
            $_SESSION['username'] = $row['Username'];
            $_SESSION['role']     = 'admin';
            header('Location: admin_dashboard.php'); exit;
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Login — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{background:var(--navy);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
    .al-box{width:100%;max-width:360px;}
    .al-header{text-align:center;margin-bottom:2rem;}
    .al-header .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;display:block;}
    .al-header .school{color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:.6px;display:block;}
    .al-header h2{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:#fff;margin-top:1.5rem;margin-bottom:.2rem;}
    .al-header p{font-size:13px;color:rgba(255,255,255,0.45);}
    .card{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(10px);}
    .form-control{background:rgba(255,255,255,0.07);border-color:rgba(255,255,255,0.15);color:#fff;}
    .form-control::placeholder{color:rgba(255,255,255,0.3);}
    .form-control:focus{border-color:var(--red);}
    .form-group label{color:rgba(255,255,255,0.55);}
    .remember{display:flex;align-items:center;gap:7px;font-size:13px;color:rgba(255,255,255,0.45);margin-bottom:1rem;}
    .back-link{text-align:center;margin-top:1.2rem;font-size:13px;color:rgba(255,255,255,0.35);}
    .back-link a{color:var(--red);}
    .pw-wrap{position:relative;}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.4);}
  </style>
</head>
<body>
<div class="al-box">
  <div class="al-header">
    <span class="dot-com">DOT COM</span>
    <span class="school">DRIVING SCHOOL</span>
    <h2>Admin Login</h2>
    <p>Login to admin panel</p>
  </div>
  <div class="card">
    <div class="card-body">
      <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php show_flash(); ?>
      <form method="POST">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" class="form-control" placeholder="admin" value="<?= htmlspecialchars($_POST['username']??'') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pw" class="form-control" placeholder="••••••••" required>
            <button type="button" class="pw-toggle" onclick="document.getElementById('pw').type=document.getElementById('pw').type==='password'?'text':'password'">👁️</button>
          </div>
        </div>
        <div class="remember">
          <input type="checkbox" id="rem"><label for="rem">Remember me</label>
        </div>
        <button type="submit" class="btn btn-red btn-block" style="padding:12px;">Login</button>
      </form>
      <div class="back-link">← <a href="index.php">Back to home</a></div>
    </div>
  </div>
</div>
</body>
</html>
