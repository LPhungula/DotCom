<?php
require_once 'includes/db.php';
require_student();

// Fetch student record
$stmt = $conn->prepare("SELECT s.*, q.QRToken FROM students s LEFT JOIN qr_codes q ON q.StudentID=s.StudentID WHERE s.UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    set_flash('error','Student record not found.'); header('Location: logout.php'); exit;
}

$sid = $student['StudentID'];

// KPIs
$att_total  = $conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid")->fetch_assoc()['c'];
$att_present= $conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid AND Status='Present'")->fetch_assoc()['c'];
$att_pct    = $att_total > 0 ? round(($att_present/$att_total)*100) : 0;
$enrolled   = 1; // one course per student in this system

// Recent attendance
$att_q = $conn->prepare("SELECT * FROM attendance WHERE StudentID=? ORDER BY ScanTime DESC LIMIT 10");
$att_q->bind_param('i',$sid);
$att_q->execute();
$att_rows = $att_q->get_result();

// QR SVG (pure PHP, no library needed for demo)
function buildQrSvg(string $token): string {
    $size = 20;
    srand(crc32($token));
    $cells = '';
    for ($r=0;$r<$size;$r++) for ($c2=0;$c2<$size;$c2++) {
        if (rand(0,1)) $cells .= "<rect x='{$c2}' y='{$r}' width='1' height='1' fill='#0f1923'/>";
    }
    // fixed-position squares (corners)
    $fp = "<rect x='0' y='0' width='6' height='6' fill='none' stroke='#0f1923' stroke-width='.8'/>
           <rect x='1.5' y='1.5' width='3' height='3' fill='#0f1923'/>
           <rect x='13' y='0' width='6' height='6' fill='none' stroke='#0f1923' stroke-width='.8'/>
           <rect x='14.5' y='1.5' width='3' height='3' fill='#0f1923'/>
           <rect x='0' y='13' width='6' height='6' fill='none' stroke='#0f1923' stroke-width='.8'/>
           <rect x='1.5' y='14.5' width='3' height='3' fill='#0f1923'/>";
    return "<svg viewBox='0 0 $size $size' xmlns='http://www.w3.org/2000/svg' style='width:140px;height:140px;'>
      <rect width='$size' height='$size' fill='#fff'/>{$cells}{$fp}</svg>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Dashboard — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .dash-header{background:var(--navy);color:#fff;padding:2rem;}
    .dash-header h1{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;}
    .dash-header p{font-size:13.5px;color:rgba(255,255,255,0.55);margin-top:.3rem;}
    .dash-body{max-width:1100px;margin:0 auto;padding:2rem;}
    .dash-grid{display:grid;grid-template-columns:1fr 1.6fr;gap:1.5rem;margin-top:1.5rem;}
    .qr-card{text-align:center;}
    .qr-label{font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;margin-bottom:1rem;}
    .qr-wrap{background:var(--offwhite);border:1px solid var(--border);border-radius:10px;padding:1.5rem;display:inline-block;margin-bottom:1rem;}
    .sidebar-menu{display:flex;flex-direction:column;gap:.5rem;}
    .menu-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;font-size:13.5px;color:var(--text);text-decoration:none;background:#fff;border:1px solid var(--border);transition:all .18s;}
    .menu-item:hover,.menu-item.active{background:var(--navy);color:#fff;border-color:var(--navy);}
    @media(max-width:700px){.dash-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="dash-header">
  <h1>Welcome, <?= htmlspecialchars($student['FirstName'].' '.$student['LastName']) ?>!</h1>
  <p><?= htmlspecialchars($student['CourseType']) ?> — Student ID: STU-<?= str_pad($sid,3,'0',STR_PAD_LEFT) ?></p>
</div>

<div class="dash-body">
  <?php show_flash(); ?>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card" style="--kpi-color:var(--red)">
      <div class="kpi-label">ENROLLED COURSES</div>
      <div class="kpi-value"><?= $enrolled ?></div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--blue)">
      <div class="kpi-label">TOTAL CLASSES</div>
      <div class="kpi-value"><?= $att_total ?></div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--green)">
      <div class="kpi-label">CLASSES ATTENDED</div>
      <div class="kpi-value"><?= $att_present ?></div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--amber)">
      <div class="kpi-label">ATTENDANCE %</div>
      <div class="kpi-value"><?= $att_pct ?>%</div>
    </div>
  </div>

  <!-- Sidebar menu + content -->
  <div style="display:grid;grid-template-columns:180px 1fr;gap:1.5rem;align-items:start;">
    <div class="sidebar-menu card" style="padding:1rem;">
      <a href="student_dashboard.php" class="menu-item active">🏠 Dashboard</a>
      <a href="student_profile.php"  class="menu-item">👤 My Profile</a>
      <a href="student_dashboard.php#qr" class="menu-item">📷 My QR Code</a>
      <a href="student_dashboard.php#att" class="menu-item">📋 My Attendance</a>
      <a href="study_materials.php"  class="menu-item">📚 Study Materials</a>
      <a href="change_password.php"  class="menu-item">🔒 Change Password</a>
      <a href="logout.php"           class="menu-item">🚪 Logout</a>
    </div>

    <div>
      <!-- QR + Attendance grid -->
      <div class="dash-grid" style="margin-top:0;">
        <!-- QR Card -->
        <div class="card" id="qr">
          <div class="card-body qr-card">
            <div class="qr-label">My QR Code</div>
            <div class="qr-wrap">
              <?= buildQrSvg($student['QRToken'] ?? 'fallback-token') ?>
            </div>
            <p style="font-size:11.5px;color:var(--muted);margin-bottom:1rem;">Present this QR during every lesson</p>
            <a href="download_qr.php" class="btn btn-red btn-block">Download QR</a>
          </div>
        </div>

        <!-- Attendance -->
        <div class="card" id="att">
          <div class="card-body">
            <div class="section-title">Recent Attendance</div>
            <?php if ($att_rows->num_rows > 0): ?>
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                <tbody>
                  <?php while ($a = $att_rows->fetch_assoc()): ?>
                  <tr>
                    <td><?= date('Y-m-d', strtotime($a['ScanTime'])) ?></td>
                    <td><?= date('H:i:s', strtotime($a['ScanTime'])) ?></td>
                    <td><span class="badge badge-<?= strtolower($a['Status']) ?>"><?= htmlspecialchars($a['Status']) ?></span></td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
              <p style="color:var(--muted);font-size:13.5px;text-align:center;padding:2rem 0;">No attendance records yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>