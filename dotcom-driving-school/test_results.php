<?php
require_once 'includes/db.php';
require_admin();

// Confirm/cancel official test bookings
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bid = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'confirm') {
        $upd = $conn->prepare("UPDATE official_test_bookings SET Status='confirmed', ConfirmedBy=?, ConfirmedAt=NOW() WHERE BookingID=?");
        $upd->bind_param('ii', $_SESSION['user_id'], $bid);
        $upd->execute();
        set_flash('success','Test booking confirmed.');
    } elseif ($action === 'cancel') {
        $conn->query("UPDATE official_test_bookings SET Status='cancelled' WHERE BookingID=$bid");
        set_flash('success','Test booking cancelled.');
    } elseif ($action === 'complete') {
        $conn->query("UPDATE official_test_bookings SET Status='completed' WHERE BookingID=$bid");
        set_flash('success','Test marked as completed.');
    }
    header('Location: test_results.php'); exit;
}

// KPIs
$total_attempts   = $conn->query("SELECT COUNT(*) c FROM mock_test_results")->fetch_assoc()['c'];
$total_passes     = $conn->query("SELECT COUNT(*) c FROM mock_test_results WHERE Passed=1")->fetch_assoc()['c'];
$avg_score        = $conn->query("SELECT ROUND(AVG(Percentage),1) a FROM mock_test_results")->fetch_assoc()['a'] ?? 0;
$pending_bookings = $conn->query("SELECT COUNT(*) c FROM official_test_bookings WHERE Status='pending'")->fetch_assoc()['c'];

// Mock test results with student info
$mock_results = $conn->query("
    SELECT r.*, s.FirstName, s.LastName, s.CourseType
    FROM mock_test_results r
    JOIN students s ON s.StudentID = r.StudentID
    ORDER BY r.TakenAt DESC
    LIMIT 50
");

// Official test bookings
$test_bookings = $conn->query("
    SELECT b.*, s.FirstName, s.LastName, s.CourseType
    FROM official_test_bookings b
    JOIN students s ON s.StudentID = b.StudentID
    ORDER BY b.TestDate ASC, b.TestTime ASC
");

$testNames = [
    'learners_k53'  => "Learner's Licence (K53)",
    'driving_code8' => 'Code 8',
    'driving_codeA' => 'Code A (Motorcycle)',
    'driving_code10'=> 'Code 10',
    'driving_code14'=> 'Code 14',
];
$dltcNames = [
    'pinetown'     => 'Pinetown DLTC',
    'durban_north' => 'Durban North DLTC',
    'umlazi'       => 'Umlazi DLTC',
];
$statusStyles = [
    'pending'   => 'background:#fef9c3;color:#92400e;',
    'confirmed' => 'background:#dcfce7;color:#166534;',
    'cancelled' => 'background:#f1f5f9;color:#64748b;',
    'completed' => 'background:#dbeafe;color:#1e40af;',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Test Results — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}
    .tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:1.5rem;}
    .tab-btn{padding:10px 20px;font-size:14px;font-weight:500;color:var(--muted);background:none;border:none;border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;transition:all .18s;}
    .tab-btn.active{color:var(--red);border-bottom-color:var(--red);}
    .tab-content{display:none;}.tab-content.active{display:block;}
    .score-bar-track{background:var(--border);border-radius:999px;height:8px;overflow:hidden;width:80px;display:inline-block;vertical-align:middle;margin-right:6px;}
    .score-bar-fill{height:100%;border-radius:999px;}
  </style>
</head>
<body>
<nav class="admin-navbar">
  <div><span class="dot-com">DOT COM</span><span class="school">DRIVING SCHOOL</span></div>
  <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
</nav>
<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-wrapper">
<div class="admin-main">
  <div class="page-header">
    <h1>📝 Test Results & Bookings</h1>
    <p>Track student mock test performance and manage official DLTC test bookings</p>
  </div>
  <?php show_flash(); ?>

  <!-- KPIs -->
  <div class="kpi-grid" style="margin-bottom:1.5rem;">
    <div class="kpi-card" style="--kpi-color:var(--blue);">
      <div class="kpi-label">MOCK TEST ATTEMPTS</div>
      <div class="kpi-value"><?= $total_attempts ?></div>
    </div>
    <div class="kpi-card" style="--kpi-color:#16a34a;">
      <div class="kpi-label">MOCK TEST PASSES</div>
      <div class="kpi-value"><?= $total_passes ?></div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--amber);">
      <div class="kpi-label">AVG MOCK SCORE</div>
      <div class="kpi-value"><?= $avg_score ?>%</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--red);">
      <div class="kpi-label">PENDING BOOKINGS</div>
      <div class="kpi-value"><?= $pending_bookings ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="openTab('mock',this)">📝 Mock Test Results</button>
    <button class="tab-btn" onclick="openTab('official',this)">🏛️ Official Test Bookings</button>
  </div>

  <!-- Mock Test Tab -->
  <div class="tab-content active" id="tab-mock">
    <div class="card">
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Student</th><th>Course</th><th>Score</th><th>%</th><th>Result</th><th>Time Taken</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php if ($mock_results && $mock_results->num_rows > 0):
                while ($r = $mock_results->fetch_assoc()):
                  $pct   = (float)$r['Percentage'];
                  $color = $pct >= 77 ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626');
                  $timeSecs = (int)$r['TimeTaken'];
                  $timeStr  = $timeSecs > 0 ? floor($timeSecs/60).'m '.($timeSecs%60).'s' : '—';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></strong></td>
                <td><?= htmlspecialchars($r['CourseType']) ?></td>
                <td><?= $r['Score'] ?>/<?= $r['Total'] ?></td>
                <td>
                  <div class="score-bar-track"><div class="score-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
                  <strong style="color:<?= $color ?>;"><?= number_format($pct,1) ?>%</strong>
                </td>
                <td>
                  <span class="badge" style="<?= $r['Passed'] ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
                    <?= $r['Passed'] ? '✅ Pass' : '❌ Fail' ?>
                  </span>
                </td>
                <td><?= $timeStr ?></td>
                <td><?= date('d M Y H:i', strtotime($r['TakenAt'])) ?></td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--muted);">No mock test results yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Official Test Bookings Tab -->
  <div class="tab-content" id="tab-official">
    <div class="card">
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Student</th><th>Test Type</th><th>DLTC Centre</th><th>Date & Time</th><th>Reference</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if ($test_bookings && $test_bookings->num_rows > 0):
                while ($b = $test_bookings->fetch_assoc()):
                  $sbStyle = $statusStyles[$b['Status']] ?? '';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($b['FirstName'].' '.$b['LastName']) ?></strong><br><small style="color:var(--muted);"><?= htmlspecialchars($b['CourseType']) ?></small></td>
                <td><?= htmlspecialchars($testNames[$b['TestType']] ?? $b['TestType']) ?></td>
                <td><?= htmlspecialchars($dltcNames[$b['DLTCCentre']] ?? $b['DLTCCentre']) ?></td>
                <td><?= date('d M Y', strtotime($b['TestDate'])) ?><br><small style="color:var(--muted);"><?= htmlspecialchars($b['TestTime']) ?></small></td>
                <td><code><?= htmlspecialchars($b['RefNumber']) ?></code></td>
                <td><span class="badge" style="<?= $sbStyle ?>"><?= ucfirst($b['Status']) ?></span></td>
                <td style="white-space:nowrap;">
                  <?php if ($b['Status']==='pending'): ?>
                    <a href="test_results.php?action=confirm&id=<?= $b['BookingID'] ?>" class="btn btn-sm btn-green">✓ Confirm</a>
                    <a href="test_results.php?action=cancel&id=<?= $b['BookingID'] ?>" class="btn btn-sm" style="background:#fee2e2;color:var(--red);" onclick="return confirm('Cancel this booking?')">✕</a>
                  <?php elseif ($b['Status']==='confirmed'): ?>
                    <a href="test_results.php?action=complete&id=<?= $b['BookingID'] ?>" class="btn btn-sm btn-navy" onclick="return confirm('Mark as completed?')">✅ Done</a>
                  <?php else: ?>
                    <span style="color:var(--muted);font-size:12px;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--muted);">No official test bookings yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
</div>
<script>
function openTab(id, btn) {
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  if (btn) btn.classList.add('active');
}
</script>
</body>
</html>