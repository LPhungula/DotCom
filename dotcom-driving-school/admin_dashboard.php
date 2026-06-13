<?php
require_once 'includes/db.php';
require_admin();

// KPIs
$total_students = $conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$today = date('Y-m-d');
$todays_att = $conn->query("SELECT COUNT(*) c FROM attendance WHERE DATE(ScanTime)='$today' AND Status='Present'")->fetch_assoc()['c'];
$total_courses = $conn->query("SELECT COUNT(*) c FROM courses")->fetch_assoc()['c'];
$total_scans   = $conn->query("SELECT COUNT(*) c FROM attendance")->fetch_assoc()['c'];

// Recent scans
$recent_scans = $conn->query("
  SELECT a.ScanTime, a.Status, s.FirstName, s.LastName
  FROM attendance a
  JOIN students s ON s.StudentID=a.StudentID
  ORDER BY a.ScanTime DESC LIMIT 8
");

// Student list
$students_q = $conn->query("
  SELECT s.StudentID, s.FirstName, s.LastName, s.CourseType, s.EnrolledAt,
         (SELECT Status FROM attendance WHERE StudentID=s.StudentID ORDER BY ScanTime DESC LIMIT 1) AS LastStatus
  FROM students s ORDER BY s.EnrolledAt DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Dashboard — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{
      position:fixed;top:0;left:0;right:0;height:var(--nav-h);
      background:var(--navy);display:flex;align-items:center;justify-content:space-between;
      padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);
    }
    .admin-navbar .logo .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .logo .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}
    .admin-navbar .admin-info{display:flex;align-items:center;gap:12px;color:rgba(255,255,255,0.7);font-size:13px;}
    .admin-avatar{width:32px;height:32px;border-radius:50%;background:var(--red);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;}
    .dash-main{margin-left:var(--sidebar-w);padding:2rem;min-height:calc(100vh - var(--nav-h));}
    .dash-grid-2{display:grid;grid-template-columns:1.4fr 1fr;gap:1.5rem;margin-top:1.5rem;}
    @media(max-width:900px){.dash-grid-2{grid-template-columns:1fr;}.dash-main{margin-left:0;}}
  </style>
</head>
<body>

<!-- Admin navbar -->
<nav class="admin-navbar">
  <div class="logo">
    <span class="dot-com">DOT COM</span>
    <span class="school">DRIVING SCHOOL</span>
  </div>
  <div class="admin-info">
    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
    <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
  </div>
</nav>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-wrapper">
  <div class="dash-main">
    <?php show_flash(); ?>

    <div class="page-header">
      <h1>Admin Dashboard</h1>
      <p>Overview of all school operations — <?= date('l, d F Y') ?></p>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
      <div class="kpi-card" style="--kpi-color:#16a34a;">
        <div class="kpi-label">TOTAL STUDENTS</div>
        <div class="kpi-value"><?= $total_students ?></div>
      </div>
      <div class="kpi-card" style="--kpi-color:var(--red);">
        <div class="kpi-label">TODAY'S ATTENDANCE</div>
        <div class="kpi-value"><?= $todays_att ?></div>
      </div>
      <div class="kpi-card" style="--kpi-color:var(--amber);">
        <div class="kpi-label">TOTAL COURSES</div>
        <div class="kpi-value"><?= $total_courses ?></div>
      </div>
      <div class="kpi-card" style="--kpi-color:var(--blue);">
        <div class="kpi-label">TOTAL SCANS</div>
        <div class="kpi-value"><?= $total_scans ?></div>
      </div>
    </div>

    <!-- Main grid -->
    <div class="dash-grid-2">
      <!-- Recent scans -->
      <div class="card">
        <div class="card-body">
          <div class="section-title">
            Recent Attendance Scans
            <a href="attendance.php" class="btn btn-sm btn-navy">View All</a>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Student</th><th>Time</th><th>Status</th></tr></thead>
              <tbody>
                <?php if ($recent_scans && $recent_scans->num_rows > 0):
                  while ($r = $recent_scans->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
                  <td><?= date('Y-m-d H:i:s', strtotime($r['ScanTime'])) ?></td>
                  <td><span class="badge badge-<?= strtolower($r['Status']) ?>"><?= $r['Status'] ?></span></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:2rem;">No attendance scans yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="card">
        <div class="card-body">
          <div class="section-title">Quick Actions</div>
          <div style="display:flex;flex-direction:column;gap:.7rem;">
            <a href="students.php"      class="btn btn-navy btn-block">👥 View All Students</a>
            <a href="generate_qr.php"   class="btn btn-navy btn-block">📷 Generate QR Codes</a>
            <a href="scan.php"          class="btn btn-navy btn-block">✅ Scan Attendance</a>
            <a href="attendance.php"    class="btn btn-navy btn-block">📋 Attendance Records</a>
            <a href="courses.php"       class="btn btn-navy btn-block">🚗 Manage Courses</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Students table -->
    <div class="card mt-3">
      <div class="card-body">
        <div class="section-title">
          Registered Students
          <a href="students.php" class="btn btn-sm btn-red">All Students</a>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>#</th><th>Name</th><th>Course</th><th>Enrolled</th><th>Last Status</th></tr></thead>
            <tbody>
              <?php $n=1; while ($s = $students_q->fetch_assoc()): ?>
              <tr>
                <td><?= $n++ ?></td>
                <td><?= htmlspecialchars($s['FirstName'].' '.$s['LastName']) ?></td>
                <td><?= htmlspecialchars($s['CourseType']) ?></td>
                <td><?= date('Y-m-d', strtotime($s['EnrolledAt'])) ?></td>
                <td>
                  <?php if ($s['LastStatus']): ?>
                    <span class="badge badge-<?= strtolower($s['LastStatus']) ?>"><?= $s['LastStatus'] ?></span>
                  <?php else: ?>
                    <span style="color:var(--muted);font-size:12px;">No record</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /dash-main -->
</div><!-- /admin-wrapper -->
</body>
</html>
