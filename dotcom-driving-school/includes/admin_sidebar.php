<?php
// includes/admin_sidebar.php (original style — double header, no avatar)
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div style="padding:0 1.5rem 1.2rem; border-bottom:1px solid rgba(255,255,255,0.07); margin-bottom:.8rem;">
    <div style="font-family:'Syne',sans-serif;">
      <span style="color:var(--red);font-size:16px;font-weight:800;display:block;">DOT COM</span>
      <span style="color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;">DRIVING SCHOOL</span>
    </div>
    <div style="margin-top:.7rem;font-size:12px;color:rgba(255,255,255,0.4);">
      Admin Panel
    </div>
  </div>
  <ul class="sidebar-nav">
    <li>
      <a href="admin_dashboard.php" class="<?= $current==='admin_dashboard.php'?'active':'' ?>">
        <span class="icon">🏠</span> Dashboard
      </a>
    </li>
    <li>
      <a href="students.php" class="<?= $current==='students.php'?'active':'' ?>">
        <span class="icon">👥</span> Students
      </a>
    </li>
    <li>
      <a href="generate_qr.php" class="<?= $current==='generate_qr.php'?'active':'' ?>">
        <span class="icon">📷</span> Generate QR
      </a>
    </li>
    <li>
      <a href="scan.php" class="<?= $current==='scan.php'?'active':'' ?>">
        <span class="icon">✅</span> Scan Attendance
      </a>
    </li>
    <li>
      <a href="attendance.php" class="<?= $current==='attendance.php'?'active':'' ?>">
        <span class="icon">📋</span> Attendance Records
      </a>
    </li>
    <li>
      <a href="payments.php" class="<?= $current==='payments.php'?'active':'' ?>">
        <span class="icon">💳</span> Payments
      </a>
    </li>
    <li>
      <a href="courses.php" class="<?= $current==='courses.php'?'active':'' ?>">
        <span class="icon">🚗</span> Courses
      </a>
    </li>
    <hr class="sidebar-divider">
    <li>
      <a href="logout.php">
        <span class="icon">🚪</span> Logout
      </a>
    </li>
  </ul>
</aside>