<?php
// includes/student_sidebar.php
// Requires: $conn, $_SESSION['user_id'] to be set (require_student() already called)
$current = basename($_SERVER['PHP_SELF']);

$stmt = $conn->prepare("SELECT FirstName, LastName, ProfilePic FROM students WHERE UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$sb_user = $stmt->get_result()->fetch_assoc();
$sb_name = $sb_user ? $sb_user['FirstName'].' '.$sb_user['LastName'] : $_SESSION['username'];
$sb_initials = $sb_user ? strtoupper(substr($sb_user['FirstName'],0,1).substr($sb_user['LastName'],0,1)) : strtoupper(substr($_SESSION['username'],0,1));
$sb_pic = !empty($sb_user['ProfilePic']) ? 'uploads/profile_pics/' . $sb_user['ProfilePic'] : null;
?>
<aside class="sb-sidebar" id="sbSidebar">
  <div class="sb-brand">
    <div>
      <span class="dot-com">DOT COM</span>
      <span class="school">DRIVING SCHOOL</span>
    </div>
  </div>

  <div class="sb-user">
    <div class="sb-avatar">
      <?php if ($sb_pic): ?>
        <img src="<?= htmlspecialchars($sb_pic) ?>" alt="">
      <?php else: ?>
        <?= htmlspecialchars($sb_initials) ?>
      <?php endif; ?>
    </div>
    <div class="sb-user-info">
      <strong><?= htmlspecialchars($sb_name) ?></strong>
      <span>Student</span>
    </div>
  </div>

  <nav class="sb-nav">
    <a href="student_dashboard.php" class="<?= $current==='student_dashboard.php'?'active':'' ?>"><span class="icon">🏠</span> Dashboard</a>
    <a href="student_profile.php"   class="<?= $current==='student_profile.php'?'active':'' ?>"><span class="icon">👤</span> My Profile</a>
    <a href="student_dashboard.php#qr"><span class="icon">📷</span> My QR Code</a>
    <a href="student_dashboard.php#att"><span class="icon">📋</span> My Attendance</a>
    <a href="study_materials.php"   class="<?= $current==='study_materials.php'?'active':'' ?>"><span class="icon">📚</span> Study Materials</a>
    <a href="student_payments.php"  class="<?= $current==='student_payments.php'?'active':'' ?>"><span class="icon">💳</span> Payments</a>
    <hr>
    <a href="change_password.php"   class="<?= $current==='change_password.php'?'active':'' ?>"><span class="icon">🔒</span> Change Password</a>
    <a href="index.php"><span class="icon">🌐</span> Visit Site</a>
  </nav>

  <div class="sb-logout">
    <a href="logout.php"><span class="icon">🚪</span> Logout</a>
  </div>
</aside>