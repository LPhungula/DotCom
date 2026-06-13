<?php
require_once 'includes/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role']==='admin' ? 'admin_dashboard.php' : 'student_dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password =      $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $conn->prepare("SELECT UserID, Username, Password, Role FROM users WHERE Username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && password_verify($password, $row['Password'])) {
            $_SESSION['user_id']  = $row['UserID'];
            $_SESSION['username'] = $row['Username'];
            $_SESSION['role']     = $row['Role'];

            set_flash('success', 'Welcome back, ' . $row['Username'] . '!');

            if ($row['Role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: student_dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Login — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);background:var(--offwhite);}
    .login-wrap{min-height:calc(100vh - var(--nav-h));display:flex;align-items:center;justify-content:center;padding:2rem 1.5rem;}
    .login-box{width:100%;max-width:400px;}
    .login-header{text-align:center;margin-bottom:2rem;}
    .login-header .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;display:block;}
    .login-header .school{color:var(--muted);font-size:11px;letter-spacing:.5px;display:block;}
    .login-header h2{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;margin-top:1.4rem;margin-bottom:.2rem;}
    .login-header p{font-size:13.5px;color:var(--muted);}
    .forgot{font-size:12.5px;color:var(--red);text-align:right;display:block;margin-top:.3rem;}
    .remember{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--muted);margin-bottom:1rem;}
    .reg-link{text-align:center;margin-top:1.2rem;font-size:13.5px;color:var(--muted);}
    .reg-link a{color:var(--red);font-weight:500;}
    .demo-box{background:var(--offwhite);border:1px solid var(--border);border-radius:8px;padding:1rem;margin-top:1rem;font-size:12.5px;}
    .demo-box strong{display:block;margin-bottom:.4rem;color:var(--text);font-size:12px;}
    .demo-row{display:flex;justify-content:space-between;color:var(--muted);padding:2px 0;}
    .pw-wrap{position:relative;}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="login-wrap">
  <div class="login-box">
    <div class="login-header">
      <span class="dot-com">DOT COM</span>
      <span class="school">DRIVING SCHOOL</span>
      <h2>Welcome Back!</h2>
      <p>Login to your account</p>
    </div>

    <div class="card">
      <div class="card-body">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php show_flash(); ?>

        <form method="POST">
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="johnsmith" value="<?= htmlspecialchars($_POST['username']??'') ?>" required autofocus>
          </div>

          <div class="form-group">
            <label>Password</label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pw" class="form-control" placeholder="••••••••" required>
              <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
            </div>
            <a href="#" class="forgot">Forgot Password?</a>
          </div>

          <div class="remember">
            <input type="checkbox" name="remember" id="rem">
            <label for="rem">Remember me</label>
          </div>

          <button type="submit" class="btn btn-red btn-block" style="padding:12px;">Login</button>
        </form>

        <div class="reg-link">Don't have an account? <a href="register.php">Register here</a></div>

        <div class="demo-box">
          <strong>🔑 Demo credentials:</strong>
          <div class="demo-row"><span>Admin:</span><span>admin / admin123</span></div>
          <div class="demo-row"><span>Student:</span><span>johnsmith / student123</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>function togglePw(){const pw=document.getElementById('pw');pw.type=pw.type==='password'?'text':'password';}</script>
</body>
</html>
