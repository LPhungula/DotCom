<?php
require_once 'includes/db.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role']==='admin' ? 'admin_dashboard.php' : 'student_dashboard.php'));
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first    = trim($_POST['first_name']   ?? '');
    $last     = trim($_POST['last_name']    ?? '');
    $phone    = trim($_POST['phone']        ?? '');
    $email    = trim($_POST['email']        ?? '');
    $username = trim($_POST['username']     ?? '');
    $password =      $_POST['password']    ?? '';
    $course   = trim($_POST['course_type'] ?? '');
    $idnum    = trim($_POST['id_number']   ?? '');

    // Basic validation
    if (!$first || !$last || !$phone || !$email || !$username || !$password || !$course) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check duplicate username/email
        $chk = $conn->prepare("SELECT UserID FROM users WHERE Username = ? LIMIT 1");
        $chk->bind_param('s', $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'That username is already taken. Please choose another.';
        } else {
            $chk2 = $conn->prepare("SELECT StudentID FROM students WHERE Email = ? LIMIT 1");
            $chk2->bind_param('s', $email);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) {
                $error = 'An account with that email already exists.';
            } else {
                // Insert user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins_user = $conn->prepare("INSERT INTO users (Username, Password, Role) VALUES (?, ?, 'student')");
                $ins_user->bind_param('ss', $username, $hash);
                $ins_user->execute();
                $uid = $conn->insert_id;

                // Insert student
                $ins_stu = $conn->prepare("INSERT INTO students (FirstName, LastName, Phone, Email, IDNumber, CourseType, UserID) VALUES (?,?,?,?,?,?,?)");
                $ins_stu->bind_param('ssssssi', $first, $last, $phone, $email, $idnum, $course, $uid);
                $ins_stu->execute();
                $sid = $conn->insert_id;

                // Auto-generate QR token
                $token = 'QR-STU-' . str_pad($sid, 3, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(8)));
                $ins_qr = $conn->prepare("INSERT INTO qr_codes (StudentID, QRToken) VALUES (?,?)");
                $ins_qr->bind_param('is', $sid, $token);
                $ins_qr->execute();

                set_flash('success', 'Registration successful! Please login below.');
                header('Location: login.php');
                exit;
            }
        }
    }
}

$courses_q = $conn->query("SELECT CourseName FROM courses ORDER BY Price ASC");
$course_list = [];
if ($courses_q) {
    while ($r = $courses_q->fetch_assoc()) $course_list[] = $r['CourseName'];
}
if (empty($course_list)) {
    $course_list = ['Learners Licence Prep','Manual Driving Course','Code 8 - Light Motor','Code 10 - Heavy Motor','Refresher Course'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Register — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);background:var(--offwhite);}
    .reg-wrap{min-height:calc(100vh - var(--nav-h));display:flex;align-items:center;justify-content:center;padding:2.5rem 1.5rem;}
    .reg-box{width:100%;max-width:520px;}
    .reg-header{text-align:center;margin-bottom:2rem;}
    .reg-header .logo-text .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;}
    .reg-header .logo-text .school{color:var(--muted);font-size:11px;letter-spacing:.5px;display:block;}
    .reg-header h2{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:700;margin-top:1.5rem;margin-bottom:.2rem;}
    .reg-header p{font-size:13.5px;color:var(--muted);}
    .pw-wrap{position:relative;}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:15px;}
    .login-link{text-align:center;margin-top:1.2rem;font-size:13.5px;color:var(--muted);}
    .login-link a{color:var(--red);font-weight:500;}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="reg-wrap">
  <div class="reg-box">
    <div class="reg-header">
      <div class="logo-text">
        <span class="dot-com">DOT COM</span>
        <span class="school">DRIVING SCHOOL</span>
      </div>
      <h2>Create an Account</h2>
      <p>Join Dot Com Driving School</p>
    </div>

    <div class="card">
      <div class="card-body">
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST">
          <div class="form-row">
            <div class="form-group">
              <label>First Name *</label>
              <input type="text" name="first_name" class="form-control" placeholder="John" value="<?= htmlspecialchars($_POST['first_name']??'') ?>" required>
            </div>
            <div class="form-group">
              <label>Last Name *</label>
              <input type="text" name="last_name" class="form-control" placeholder="Smith" value="<?= htmlspecialchars($_POST['last_name']??'') ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Phone Number *</label>
            <input type="tel" name="phone" class="form-control" placeholder="0712345678" value="<?= htmlspecialchars($_POST['phone']??'') ?>" required>
          </div>

          <div class="form-group">
            <label>Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="johnsmith@gmail.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
          </div>

          <div class="form-group">
            <label>ID Number (optional)</label>
            <input type="text" name="id_number" class="form-control" placeholder="9501015800082" value="<?= htmlspecialchars($_POST['id_number']??'') ?>">
          </div>

          <div class="form-group">
            <label>Course Type *</label>
            <select name="course_type" class="form-control" required>
              <option value="">-- Select a course --</option>
              <?php foreach ($course_list as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= (($_POST['course_type']??'')===$c)?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Username *</label>
              <input type="text" name="username" class="form-control" placeholder="johnsmith" value="<?= htmlspecialchars($_POST['username']??'') ?>" required>
            </div>
            <div class="form-group">
              <label>Password *</label>
              <div class="pw-wrap">
                <input type="password" name="password" id="pw" class="form-control" placeholder="Min 6 characters" required>
                <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-red btn-block" style="margin-top:.5rem;padding:12px;">Register</button>
        </form>

        <div class="login-link">Already have an account? <a href="login.php">Login here</a></div>
      </div>
    </div>
  </div>
</div>

<script>
function togglePw(){
  const pw=document.getElementById('pw');
  pw.type=pw.type==='password'?'text':'password';
}
</script>
</body>
</html>
