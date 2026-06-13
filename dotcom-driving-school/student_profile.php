<?php
require_once 'includes/db.php';
require_student();

$stmt = $conn->prepare("SELECT s.*, u.Username FROM students s JOIN users u ON u.UserID=s.UserID WHERE s.UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) { set_flash('error','Student record not found.'); header('Location: logout.php'); exit; }

$sid = $student['StudentID'];
$error = ''; $success = '';

// Get course list for dropdown
$courses_q = $conn->query("SELECT CourseName FROM courses ORDER BY CourseName");
$course_list = [];
if ($courses_q) while ($r = $courses_q->fetch_assoc()) $course_list[] = $r['CourseName'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Photo-only upload — independent of profile field validation
    if (isset($_POST['upload_photo'])) {
        if (empty($_FILES['profile_pic']['name'])) {
            $error = 'Please choose a photo to upload.';
        } else {
            $file = $_FILES['profile_pic'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Error uploading file.';
            } elseif (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, GIF or WEBP images are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Image must be smaller than 2MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'student_' . $sid . '_' . time() . '.' . strtolower($ext);
                $destDir = __DIR__ . '/uploads/profile_pics/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $dest = $destDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if ($student['ProfilePic'] && file_exists(__DIR__ . '/uploads/profile_pics/' . $student['ProfilePic'])) {
                        @unlink(__DIR__ . '/uploads/profile_pics/' . $student['ProfilePic']);
                    }
                    $upd = $conn->prepare("UPDATE students SET ProfilePic=? WHERE StudentID=?");
                    $upd->bind_param('si', $newName, $sid);
                    $upd->execute();
                    $success = 'Profile photo updated!';
                    $stmt->execute();
                    $student = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = 'Failed to save uploaded image.';
                }
            }
        }
    }

    if (isset($_POST['update_profile'])) {
        $first  = trim($_POST['first_name'] ?? '');
        $last   = trim($_POST['last_name']  ?? '');
        $phone  = trim($_POST['phone']      ?? '');
        $email  = trim($_POST['email']      ?? '');
        $idnum  = trim($_POST['id_number']  ?? '');
        $course = trim($_POST['course_type']?? '');

        if (!$first || !$last || !$phone || !$email || !$course) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email uniqueness (excluding self)
            $chk = $conn->prepare("SELECT StudentID FROM students WHERE Email=? AND StudentID!=? LIMIT 1");
            $chk->bind_param('si', $email, $sid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'That email is already used by another account.';
            } else {
                $upd = $conn->prepare("UPDATE students SET FirstName=?, LastName=?, Phone=?, Email=?, IDNumber=?, CourseType=? WHERE StudentID=?");
                $upd->bind_param('ssssssi', $first, $last, $phone, $email, $idnum, $course, $sid);
                $upd->execute();
                $success = 'Profile updated successfully!';

                // Refresh student data
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
            }
        }
    }

    if (isset($_POST['remove_pic'])) {
        if ($student['ProfilePic'] && file_exists(__DIR__ . '/uploads/profile_pics/' . $student['ProfilePic'])) {
            @unlink(__DIR__ . '/uploads/profile_pics/' . $student['ProfilePic']);
        }
        $upd = $conn->prepare("UPDATE students SET ProfilePic=NULL WHERE StudentID=?");
        $upd->bind_param('i', $sid);
        $upd->execute();
        $success = 'Profile picture removed.';
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
    }
}

$initials = strtoupper(substr($student['FirstName'],0,1) . substr($student['LastName'],0,1));
$picUrl = $student['ProfilePic'] ? 'uploads/profile_pics/' . $student['ProfilePic'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Profile — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .dash-header{background:var(--navy);color:#fff;padding:2rem;}
    .dash-header h1{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;}
    .dash-header p{font-size:13.5px;color:rgba(255,255,255,0.55);margin-top:.3rem;}
    .dash-body{max-width:1100px;margin:0 auto;padding:2rem;}
    .layout{display:grid;grid-template-columns:180px 1fr;gap:1.5rem;align-items:start;}
    .sidebar-menu{display:flex;flex-direction:column;gap:.5rem;}
    .menu-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;font-size:13.5px;color:var(--text);text-decoration:none;background:#fff;border:1px solid var(--border);transition:all .18s;}
    .menu-item:hover,.menu-item.active{background:var(--navy);color:#fff;border-color:var(--navy);}

    .profile-grid{display:grid;grid-template-columns:260px 1fr;gap:1.5rem;}
    .avatar-wrap{text-align:center;}
    .avatar{
      width:140px;height:140px;border-radius:50%;
      background:var(--navy);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-family:'Syne',sans-serif;font-size:2.6rem;font-weight:700;
      margin:0 auto 1rem;overflow:hidden;border:4px solid var(--offwhite);
      box-shadow:var(--shadow);
    }
    .avatar img{width:100%;height:100%;object-fit:cover;}
    .avatar-name{font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;margin-bottom:.2rem;}
    .avatar-sub{font-size:12.5px;color:var(--muted);margin-bottom:1.2rem;}
    .file-label{
      display:inline-block;padding:9px 18px;border-radius:7px;
      background:var(--navy);color:#fff;font-size:13px;font-weight:500;
      cursor:pointer;transition:background .2s;margin-bottom:.6rem;width:100%;
      box-sizing:border-box;text-align:center;
    }
    .file-label:hover{background:var(--navy2);}
    .file-name{font-size:12px;color:var(--muted);margin-bottom:.8rem;word-break:break-all;min-height:16px;}
    @media(max-width:768px){.layout{grid-template-columns:1fr;}.profile-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="dash-header">
  <h1>My Profile</h1>
  <p>View and update your personal information</p>
</div>

<div class="dash-body">
  <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="layout">
    <!-- Sidebar -->
    <div class="sidebar-menu card" style="padding:1rem;">
      <a href="student_dashboard.php" class="menu-item">🏠 Dashboard</a>
      <a href="student_profile.php"  class="menu-item active">👤 My Profile</a>
      <a href="student_dashboard.php#qr" class="menu-item">📷 My QR Code</a>
      <a href="student_dashboard.php#att" class="menu-item">📋 My Attendance</a>
      <a href="study_materials.php"  class="menu-item">📚 Study Materials</a>
      <a href="student_payments.php" class="menu-item">💳 Payments</a>
      <a href="my_readiness.php"     class="menu-item">🤖 My Readiness</a>
      <a href="change_password.php"  class="menu-item">🔒 Change Password</a>
      <a href="logout.php"           class="menu-item">🚪 Logout</a>
    </div>

    <!-- Profile content -->
    <div class="card">
      <div class="card-body">
        <div class="profile-grid">

          <!-- Avatar column -->
          <div class="avatar-wrap">
            <div class="avatar">
              <?php if ($picUrl): ?>
                <img src="<?= htmlspecialchars($picUrl) ?>" alt="Profile picture">
              <?php else: ?>
                <?= htmlspecialchars($initials) ?>
              <?php endif; ?>
            </div>
            <div class="avatar-name"><?= htmlspecialchars($student['FirstName'].' '.$student['LastName']) ?></div>
            <div class="avatar-sub">@<?= htmlspecialchars($student['Username']) ?> · STU-<?= str_pad($sid,3,'0',STR_PAD_LEFT) ?></div>

            <form method="POST" enctype="multipart/form-data" id="picForm">
              <label class="file-label" for="profile_pic">📷 Choose new photo</label>
              <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display:none" onchange="updateFileName(this)">
              <div class="file-name" id="fileName">JPG, PNG, GIF or WEBP — max 2MB</div>
              <button type="submit" name="upload_photo" class="btn btn-red btn-block btn-sm">Upload Photo</button>
            </form>

            <?php if ($picUrl): ?>
            <form method="POST" style="margin-top:.6rem;">
              <button type="submit" name="remove_pic" class="btn btn-white btn-block btn-sm" style="border:1px solid var(--border);" onclick="return confirm('Remove profile picture?')">Remove Photo</button>
            </form>
            <?php endif; ?>
          </div>

          <!-- Form column -->
          <div>
            <div class="card-title">Personal Information</div>
            <p class="card-sub">Keep your details up to date</p>

            <form method="POST">
              <div class="form-row">
                <div class="form-group">
                  <label>First Name *</label>
                  <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($student['FirstName']) ?>" required>
                </div>
                <div class="form-group">
                  <label>Last Name *</label>
                  <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($student['LastName']) ?>" required>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label>Phone Number *</label>
                  <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($student['Phone']) ?>" required>
                </div>
                <div class="form-group">
                  <label>Email Address *</label>
                  <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['Email']) ?>" required>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label>ID Number</label>
                  <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($student['IDNumber'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label>Course Type *</label>
                  <select name="course_type" class="form-control" required>
                    <?php foreach ($course_list as $c): ?>
                      <option value="<?= htmlspecialchars($c) ?>" <?= $student['CourseType']===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                    <?php if (!in_array($student['CourseType'], $course_list)): ?>
                      <option value="<?= htmlspecialchars($student['CourseType']) ?>" selected><?= htmlspecialchars($student['CourseType']) ?></option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($student['Username']) ?>" disabled style="background:var(--offwhite);color:var(--muted);">
              </div>

              <button type="submit" name="update_profile" class="btn btn-red" style="padding:11px 28px;margin-top:.5rem;">Save Changes</button>
              <a href="student_dashboard.php" class="btn btn-white" style="border:1px solid var(--border);padding:11px 28px;margin-top:.5rem;">← Back to Dashboard</a>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
function updateFileName(input){
  const f = input.files[0];
  document.getElementById('fileName').textContent = f ? f.name : 'JPG, PNG, GIF or WEBP — max 2MB';
}
</script>
</body>
</html>