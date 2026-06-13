<?php
// includes/navbar.php — used by all public-facing pages
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
  <a href="index.php" class="nav-logo">
    <div class="nav-logo-text">
      <span class="dot-com">DOT COM</span>
      <span class="school">DRIVING SCHOOL</span>
    </div>
  </a>

  <ul class="nav-links">
    <li><a href="index.php"    class="<?= $current==='index.php'?'active':'' ?>">Home</a></li>
    <li><a href="index.php#about">About Us</a></li>
    <li><a href="index.php#courses">Courses</a></li>
    <li><a href="index.php#contact">Contact</a></li>
  </ul>

  <div class="nav-actions">
    <?php if (!empty($_SESSION['user_id']) && $_SESSION['role']==='student'): ?>
      <a href="student_dashboard.php" class="btn btn-outline btn-sm">My Dashboard</a>
      <a href="logout.php" class="btn btn-red btn-sm">Logout</a>
    <?php else: ?>
      <a href="login.php"    class="btn btn-outline btn-sm">Login</a>
      <a href="register.php" class="btn btn-red btn-sm">Register</a>
    <?php endif; ?>
  </div>
</nav>
