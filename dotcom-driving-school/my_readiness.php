<?php
require_once 'includes/db.php';
require_student();

$stmt = $conn->prepare("SELECT * FROM students WHERE UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) { set_flash('error','Student record not found.'); header('Location: logout.php'); exit; }
$sid = $student['StudentID'];

// ── Attendance data ──
$total_lessons   = (int)$conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid")->fetch_assoc()['c'];
$present_lessons = (int)$conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid AND Status='Present'")->fetch_assoc()['c'];
$absent_lessons  = (int)$conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid AND Status='Absent'")->fetch_assoc()['c'];
$late_lessons    = (int)$conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid AND Status='Late'")->fetch_assoc()['c'];
$last_lesson_row = $conn->query("SELECT MAX(ScanTime) m FROM attendance WHERE StudentID=$sid")->fetch_assoc();
$last_lesson     = $last_lesson_row['m'];
$daysSince       = $last_lesson ? floor((time() - strtotime($last_lesson)) / 86400) : null;
$pending_payments= (int)$conn->query("SELECT COUNT(*) c FROM payments WHERE StudentID=$sid AND Status='Pending'")->fetch_assoc()['c'];

// ── Course targets ──
$course_targets = [
    'Learners Licence Prep' => ['lessons'=>5,  'focus'=>['signs','rules']],
    'Manual Driving Course' => ['lessons'=>10, 'focus'=>['controls','rules']],
    'Code 8 - Light Motor'  => ['lessons'=>10, 'focus'=>['controls','rules']],
    'Code 10 - Heavy Motor' => ['lessons'=>15, 'focus'=>['controls','codes']],
    'Refresher Course'      => ['lessons'=>5,  'focus'=>['rules','controls']],
];
$default_target = ['lessons'=>10,'focus'=>['signs','rules']];
$courseConf = $course_targets[$student['CourseType']] ?? $default_target;
$target = $courseConf['lessons'];

$attendanceRate = $total_lessons > 0 ? ($present_lessons / $total_lessons) : 0;
$progress = min(1, $present_lessons / $target);
$recencyScore = $daysSince === null ? 0.1 : ($daysSince <= 7 ? 1 : ($daysSince <= 21 ? 0.6 : ($daysSince <= 45 ? 0.3 : 0.1)));

$readiness = round(max(0, min(100, ($progress*0.6 + $attendanceRate*0.3 + $recencyScore*0.1) * 100)));
$passProb  = round(min(95, max(5, $readiness*0.9 + ($attendanceRate*100*0.1))));

if ($readiness >= 80) { $status='Ready for Test'; $color='green'; $emoji='🎉'; }
elseif ($readiness >= 55) { $status='Nearly Ready'; $color='blue'; $emoji='💪'; }
elseif ($readiness >= 30) { $status='In Progress'; $color='amber'; $emoji='📈'; }
else { $status='Needs More Training'; $color='red'; $emoji='🚦'; }

// ── Test Day Predictor ──
// Estimate lessons needed to reach target, and project a date based on pace
$lessonsRemaining = max(0, $target - $present_lessons);
// Estimate lessons-per-week pace from history
$weeksActive = null;
$paceFirst = $conn->query("SELECT MIN(ScanTime) m FROM attendance WHERE StudentID=$sid")->fetch_assoc()['m'];
if ($paceFirst && $total_lessons > 0) {
    $daysActive = max(1, floor((time() - strtotime($paceFirst)) / 86400));
    $weeksActive = max(1, $daysActive / 7);
    $pace = $present_lessons / $weeksActive; // lessons per week
} else {
    $pace = 0;
}
if ($lessonsRemaining === 0) {
    $predictedDate = null; // already at target
} elseif ($pace > 0) {
    $weeksNeeded = ceil($lessonsRemaining / $pace);
    $predictedDate = date('d M Y', strtotime("+{$weeksNeeded} weeks"));
} else {
    $predictedDate = null; // not enough data
}

// ── Smart Study Plan ──
// Map weak areas to study_materials.php tabs based on attendance gaps & course focus
$studyPlan = [];

if ($daysSince !== null && $daysSince > 14) {
    $studyPlan[] = [
        'tab' => 'rules', 'icon' => '📜', 'title' => 'Refresh Rules of the Road',
        'reason' => "It's been {$daysSince} days since your last lesson — a quick refresher on right-of-way and following distance will help you get back up to speed."
    ];
}
if ($absent_lessons >= 1) {
    $studyPlan[] = [
        'tab' => 'signs', 'icon' => '🚦', 'title' => 'Review Road Signs',
        'reason' => "You've missed {$absent_lessons} lesson(s) — reviewing road sign categories (regulatory, warning, information) will help you catch up quickly."
    ];
}
if (in_array('controls', $courseConf['focus']) && $present_lessons < $target) {
    $studyPlan[] = [
        'tab' => 'controls', 'icon' => '🎛️', 'title' => 'Vehicle Controls Walkthrough',
        'reason' => "Your course (" . htmlspecialchars($student['CourseType']) . ") emphasizes vehicle controls — practice naming each control out loud before your next lesson."
    ];
}
if (in_array('codes', $courseConf['focus'])) {
    $studyPlan[] = [
        'tab' => 'codes', 'icon' => '📋', 'title' => 'Licence Codes Reference',
        'reason' => "Heavy vehicle courses often test licence code knowledge — review the GVM and code requirements table."
    ];
}
if ($readiness >= 80) {
    $studyPlan[] = [
        'tab' => 'rules', 'icon' => '✅', 'title' => 'Final Rules Review Before Test',
        'reason' => "You're testing-ready! Do one final pass over Rules of the Road and Road Signs to lock in your knowledge before booking your official test."
    ];
}
if (empty($studyPlan)) {
    $studyPlan[] = [
        'tab' => 'signs', 'icon' => '🚦', 'title' => 'Road Signs Refresher',
        'reason' => "You're doing well! A regular review of road signs keeps your knowledge sharp for the test."
    ];
}
// Limit to 3 recommendations
$studyPlan = array_slice($studyPlan, 0, 3);

// ── Personalized tips based on readiness ──
$tips = [];
if ($readiness < 30) {
    $tips[] = "Focus on building consistent lesson attendance — aim for at least 1-2 lessons per week.";
    $tips[] = "Review Study Materials before each lesson so practical time focuses on driving skills.";
} elseif ($readiness < 55) {
    $tips[] = "You're building good habits — keep your lesson pace consistent to reach test-readiness sooner.";
    $tips[] = "Ask your instructor for feedback on specific manoeuvres you find challenging.";
} elseif ($readiness < 80) {
    $tips[] = "You're close! A few more lessons focused on weak spots will push you to test-ready.";
    $tips[] = "Practice mock K53 questions from Study Materials to boost your theory score.";
} else {
    $tips[] = "You're test-ready! Talk to admin about booking your official test.";
    $tips[] = "Do a final review of Rules of the Road and Road Signs the night before your test.";
}
if ($pending_payments > 0) {
    $tips[] = "You have a pending payment — confirm it's processed to avoid booking delays.";
}

$page_title = 'My Readiness';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Readiness — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .dash-header{background:var(--navy);color:#fff;padding:2rem;}
    .dash-header h1{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;}
    .dash-header p{font-size:13.5px;color:rgba(255,255,255,0.55);margin-top:.3rem;}
    .dash-body{max-width:1100px;margin:0 auto;padding:2rem;}
    .sidebar-menu{display:flex;flex-direction:column;gap:.5rem;}
    .menu-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;font-size:13.5px;color:var(--text);text-decoration:none;background:#fff;border:1px solid var(--border);transition:all .18s;}
    .menu-item:hover,.menu-item.active{background:var(--navy);color:#fff;border-color:var(--navy);}

    /* Readiness gauge */
    .gauge-wrap{text-align:center;padding:1.5rem 0;}
    .gauge{position:relative;width:220px;height:220px;margin:0 auto 1rem;}
    .gauge svg{transform:rotate(-90deg);}
    .gauge-bg{fill:none;stroke:var(--offwhite);stroke-width:14;}
    .gauge-fill{fill:none;stroke-width:14;stroke-linecap:round;transition:stroke-dasharray 0.6s ease;}
    .gauge-text{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
    .gauge-text .num{font-family:'Syne',sans-serif;font-size:3rem;font-weight:800;line-height:1;}
    .gauge-text .lbl{font-size:11px;color:var(--muted);letter-spacing:.4px;margin-top:.2rem;}
    .status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 16px;border-radius:20px;font-size:13px;font-weight:600;margin-top:.5rem;}
    .status-pill.green{background:#dcfce7;color:#166534;}
    .status-pill.blue{background:#dbeafe;color:#1e40af;}
    .status-pill.amber{background:#fef9c3;color:#92400e;}
    .status-pill.red{background:#fee2e2;color:#991b1b;}

    .stat-row{display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:13.5px;}
    .stat-row:last-child{border-bottom:none;}
    .stat-row .v{font-weight:600;}

    .study-card{
      background:var(--offwhite);border:1px solid var(--border);border-radius:10px;
      padding:1.2rem;margin-bottom:.9rem;display:flex;gap:1rem;align-items:flex-start;
      transition:all .2s;
    }
    .study-card:hover{border-color:var(--red);box-shadow:var(--shadow);}
    .study-icon{width:42px;height:42px;border-radius:10px;background:#fff;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
    .study-card h4{font-size:14px;font-weight:600;margin-bottom:.3rem;}
    .study-card p{font-size:12.5px;color:var(--muted);line-height:1.6;margin-bottom:.6rem;}

    .tip-list{list-style:none;padding:0;margin:0;}
    .tip-list li{display:flex;gap:10px;padding:.7rem 0;font-size:13px;color:var(--text);border-bottom:1px solid var(--border);align-items:flex-start;line-height:1.6;}
    .tip-list li:last-child{border-bottom:none;}
    .tip-list li .tip-icon{flex-shrink:0;margin-top:2px;}
    .tip-list li .tip-text{flex:1;min-width:0;word-wrap:break-word;}
    .tip-list li span{flex-shrink:0;}

    .predictor-box{
      background:linear-gradient(135deg,var(--navy),#1a2636);
      color:#fff;border-radius:12px;padding:1.5rem;text-align:center;margin-bottom:1.5rem;
    }
    .predictor-box .pd-label{font-size:11px;color:rgba(255,255,255,0.5);letter-spacing:.4px;margin-bottom:.4rem;}
    .predictor-box .pd-value{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:700;color:var(--accent,#f5b800);}
    .predictor-box .pd-sub{font-size:12px;color:rgba(255,255,255,0.6);margin-top:.4rem;}

    @media(max-width:700px){.readiness-grid{grid-template-columns:1fr!important;}}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="dash-header">
  <h1>🤖 My Readiness</h1>
  <p>AI-powered test readiness score & personalized study plan</p>
</div>

<div class="dash-body">
  <?php show_flash(); ?>

  <div style="display:grid;grid-template-columns:180px 1fr;gap:1.5rem;align-items:start;">
    <div class="sidebar-menu card" style="padding:1rem;">
      <a href="student_dashboard.php" class="menu-item">🏠 Dashboard</a>
      <a href="student_profile.php"  class="menu-item">👤 My Profile</a>
      <a href="student_dashboard.php#qr" class="menu-item">📷 My QR Code</a>
      <a href="student_dashboard.php#att" class="menu-item">📋 My Attendance</a>
      <a href="study_materials.php"  class="menu-item">📚 Study Materials</a>
      <a href="student_payments.php" class="menu-item">💳 Payments</a>
      <a href="my_readiness.php"     class="menu-item active">🤖 My Readiness</a>
      <a href="change_password.php"  class="menu-item">🔒 Change Password</a>
      <a href="logout.php"           class="menu-item">🚪 Logout</a>
    </div>

    <div>
      <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:1.5rem;" class="readiness-grid">

        <!-- Readiness Score Gauge -->
        <div class="card">
          <div class="card-body gauge-wrap">
            <div class="card-title" style="text-align:left;margin-bottom:0;">Test Readiness Score</div>
            <div class="gauge">
              <svg width="220" height="220" viewBox="0 0 220 220">
                <circle class="gauge-bg" cx="110" cy="110" r="95"/>
                <?php
                  $circumference = 2 * M_PI * 95;
                  $offset = $circumference * (1 - $readiness/100);
                  $colorHex = ['green'=>'#16a34a','blue'=>'#2563eb','amber'=>'#d97706','red'=>'#dc2626'][$color];
                ?>
                <circle class="gauge-fill" cx="110" cy="110" r="95"
                  stroke="<?= $colorHex ?>"
                  stroke-dasharray="<?= $circumference ?>"
                  stroke-dashoffset="<?= $offset ?>"/>
              </svg>
              <div class="gauge-text">
                <div class="num" style="color:<?= $colorHex ?>;"><?= $readiness ?>%</div>
                <div class="lbl">READINESS</div>
              </div>
            </div>
            <div class="status-pill <?= $color ?>"><?= $emoji ?> <?= $status ?></div>

            <div style="margin-top:1.4rem;text-align:left;">
              <div class="stat-row"><span>Pass Probability</span><span class="v"><?= $passProb ?>%</span></div>
              <div class="stat-row"><span>Lessons Completed</span><span class="v"><?= $present_lessons ?> / <?= $target ?></span></div>
              <div class="stat-row"><span>Attendance Rate</span><span class="v"><?= round($attendanceRate*100) ?>%</span></div>
              <div class="stat-row"><span>Last Lesson</span><span class="v"><?= $daysSince === null ? 'No lessons yet' : ($daysSince==0?'Today':"{$daysSince} days ago") ?></span></div>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div>
          <!-- Test Day Predictor -->
          <div class="predictor-box">
            <div class="pd-label">🔮 TEST DAY PREDICTOR</div>
            <?php if ($lessonsRemaining === 0): ?>
              <div class="pd-value">You've hit your lesson target!</div>
              <div class="pd-sub">Talk to admin about booking your official test.</div>
            <?php elseif ($predictedDate): ?>
              <div class="pd-value">~<?= $predictedDate ?></div>
              <div class="pd-sub">Estimated based on your current pace (<?= round($pace,1) ?> lessons/week) — <?= $lessonsRemaining ?> lesson(s) remaining to reach target.</div>
            <?php else: ?>
              <div class="pd-value">Not enough data yet</div>
              <div class="pd-sub">Attend a few lessons so we can estimate your test-ready date — you need <?= $lessonsRemaining ?> more lesson(s) to reach the <?= $target ?>-lesson target for <?= htmlspecialchars($student['CourseType']) ?>.</div>
            <?php endif; ?>
          </div>

          <!-- Smart Study Plan -->
          <div class="card mb-3">
            <div class="card-body">
              <div class="section-title">📚 Smart Study Plan — Personalized for You</div>
              <?php foreach ($studyPlan as $item): ?>
              <div class="study-card">
                <div class="study-icon"><?= $item['icon'] ?></div>
                <div style="flex:1;">
                  <h4><?= htmlspecialchars($item['title']) ?></h4>
                  <p><?= $item['reason'] ?></p>
                  <a href="study_materials.php?tab=<?= $item['tab'] ?>" class="btn btn-sm btn-red">Open Study Materials →</a>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Personalized Tips -->
          <div class="card">
            <div class="card-body">
              <div class="section-title">💡 Personalized Tips</div>
              <ul class="tip-list">
                <?php foreach ($tips as $tip): ?>
                <li><span class="tip-icon">✓</span><span class="tip-text"><?= htmlspecialchars($tip) ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
</body>
</html>