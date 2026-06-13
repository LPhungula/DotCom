<?php
require_once 'includes/db.php';
require_admin();

// ═══════════════════════════════════════════════
// BUSINESS INTELLIGENCE — Revenue & Attendance
// ═══════════════════════════════════════════════

// Monthly revenue (last 6 months, confirmed payments)
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd   = date('Y-m-t', strtotime("-$i months"));
    $label      = date('M', strtotime($monthStart));
    $total = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE Status='Confirmed' AND PaymentDate BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];
    $monthly_revenue[] = ['label' => $label, 'total' => (float)$total];
}
$max_revenue = max(1, max(array_column($monthly_revenue, 'total')));

// This month vs last month
$this_month_total = $monthly_revenue[5]['total'];
$last_month_total  = $monthly_revenue[4]['total'];
$revenue_change = $last_month_total > 0 ? round((($this_month_total - $last_month_total) / $last_month_total) * 100) : ($this_month_total > 0 ? 100 : 0);

// Outstanding payments
$total_revenue = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE Status='Confirmed'")->fetch_assoc()['t'];
$outstanding = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE Status='Pending'")->fetch_assoc()['t'];
$outstanding_count = $conn->query("SELECT COUNT(*) c FROM payments WHERE Status='Pending'")->fetch_assoc()['c'];

// Attendance rate this month vs last month
$today = date('Y-m-d');
$thisMonthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd   = date('Y-m-t', strtotime('-1 month'));

function attendanceRate($conn, $start, $end) {
    $total   = $conn->query("SELECT COUNT(*) c FROM attendance WHERE ScanTime BETWEEN '$start 00:00:00' AND '$end 23:59:59'")->fetch_assoc()['c'];
    $present = $conn->query("SELECT COUNT(*) c FROM attendance WHERE Status='Present' AND ScanTime BETWEEN '$start 00:00:00' AND '$end 23:59:59'")->fetch_assoc()['c'];
    return $total > 0 ? round(($present/$total)*100) : null;
}
$att_this_month = attendanceRate($conn, $thisMonthStart, $today);
$att_last_month = attendanceRate($conn, $lastMonthStart, $lastMonthEnd);

// Attendance by course type (last 30 days)
$att_by_course = $conn->query("
    SELECT s.CourseType,
           COUNT(*) AS total,
           SUM(CASE WHEN a.Status='Present' THEN 1 ELSE 0 END) AS present
    FROM attendance a
    JOIN students s ON s.StudentID = a.StudentID
    WHERE a.ScanTime >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    GROUP BY s.CourseType
    ORDER BY total DESC
");
$course_attendance = [];
if ($att_by_course) while ($r = $att_by_course->fetch_assoc()) {
    $course_attendance[] = [
        'course'  => $r['CourseType'],
        'pct'     => $r['total'] > 0 ? round(($r['present']/$r['total'])*100) : 0,
        'total'   => $r['total'],
    ];
}

// Total students / active students (attended in last 14 days)
$total_students = $conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$active_students = $conn->query("SELECT COUNT(DISTINCT StudentID) c FROM attendance WHERE ScanTime >= DATE_SUB(NOW(), INTERVAL 14 DAY)")->fetch_assoc()['c'];
$inactive_students = max(0, $total_students - $active_students);

// ═══════════════════════════════════════════════
// PER-STUDENT: TEST READINESS & DROPOUT RISK
// (Rule-based scoring engine)
// ═══════════════════════════════════════════════

$students_q = $conn->query("
    SELECT s.StudentID, s.FirstName, s.LastName, s.CourseType, s.EnrolledAt,
        (SELECT COUNT(*) FROM attendance WHERE StudentID=s.StudentID) AS total_lessons,
        (SELECT COUNT(*) FROM attendance WHERE StudentID=s.StudentID AND Status='Present') AS present_lessons,
        (SELECT COUNT(*) FROM attendance WHERE StudentID=s.StudentID AND Status='Absent') AS absent_lessons,
        (SELECT COUNT(*) FROM attendance WHERE StudentID=s.StudentID AND Status='Late') AS late_lessons,
        (SELECT MAX(ScanTime) FROM attendance WHERE StudentID=s.StudentID) AS last_lesson,
        (SELECT COUNT(*) FROM payments WHERE StudentID=s.StudentID AND Status='Pending') AS pending_payments
    FROM students s
    ORDER BY s.FirstName
");

// Course target lessons (used to compute progress %)
$course_targets = [
    'Learners Licence Prep' => 5,
    'Manual Driving Course' => 10,
    'Code 8 - Light Motor'  => 10,
    'Code 10 - Heavy Motor' => 15,
    'Refresher Course'      => 5,
];
$default_target = 10;

function computeInsights($s, $course_targets, $default_target) {
    $target = $course_targets[$s['CourseType']] ?? $default_target;
    $present = (int)$s['present_lessons'];
    $total   = (int)$s['total_lessons'];
    $absent  = (int)$s['absent_lessons'];
    $late    = (int)$s['late_lessons'];

    $attendanceRate = $total > 0 ? ($present / $total) : 0;
    $progress       = min(1, $present / $target);

    // Days since last lesson
    $daysSince = $s['last_lesson'] ? floor((time() - strtotime($s['last_lesson'])) / 86400) : 999;

    // ── Test Readiness Score (0-100) ──
    // 60% weight: progress toward target lessons
    // 30% weight: attendance rate
    // 10% weight: recency (penalize long gaps)
    $recencyScore = $daysSince <= 7 ? 1 : ($daysSince <= 21 ? 0.6 : ($daysSince <= 45 ? 0.3 : 0.1));
    $readiness = ($progress * 0.6 + $attendanceRate * 0.3 + $recencyScore * 0.1) * 100;
    $readiness = round(max(0, min(100, $readiness)));

    if ($readiness >= 80) {
        $status = 'Ready'; $statusColor = 'green';
    } elseif ($readiness >= 55) {
        $status = 'Nearly Ready'; $statusColor = 'blue';
    } elseif ($readiness >= 30) {
        $status = 'In Progress'; $statusColor = 'amber';
    } else {
        $status = 'Needs More Training'; $statusColor = 'red';
    }

    // Pass probability — slightly correlated but distinct
    $passProb = round(min(95, max(5, $readiness * 0.9 + ($attendanceRate*100 * 0.1))));

    // ── Dropout Risk Score (0-100, higher = more risk) ──
    $riskScore = 0;
    if ($daysSince > 30) $riskScore += 40;
    elseif ($daysSince > 14) $riskScore += 20;
    elseif ($daysSince > 7) $riskScore += 8;

    if ($total > 0 && $attendanceRate < 0.6) $riskScore += 25;
    elseif ($total > 0 && $attendanceRate < 0.8) $riskScore += 10;

    if ($absent >= 3) $riskScore += 15;
    elseif ($absent >= 1) $riskScore += 5;

    if ((int)$s['pending_payments'] > 0) $riskScore += 15;

    if ($total === 0) $riskScore += 25; // never attended

    $riskScore = min(100, $riskScore);

    if ($riskScore >= 50) {
        $riskLevel = 'High Risk'; $riskColor = 'red';
    } elseif ($riskScore >= 20) {
        $riskLevel = 'Medium Risk'; $riskColor = 'amber';
    } else {
        $riskLevel = 'Low Risk'; $riskColor = 'green';
    }

    // ── Recommendation text ──
    $recs = [];
    if ($daysSince > 21 && $total > 0) $recs[] = "Hasn't attended in {$daysSince} days — reach out to re-engage.";
    if ($attendanceRate < 0.7 && $total >= 2) $recs[] = "Attendance below 70% — consider a check-in call.";
    if ($absent >= 2) $recs[] = "{$absent} absences recorded — review scheduling fit.";
    if ((int)$s['pending_payments'] > 0) $recs[] = "Has pending payment(s) — may affect continued bookings.";
    if ($readiness >= 80) $recs[] = "On track for test — consider booking official test soon.";
    if ($readiness >= 55 && $readiness < 80) $recs[] = "Good progress — a few more lessons recommended.";
    if ($total === 0) $recs[] = "No lessons attended yet — schedule an introductory lesson.";
    if (empty($recs)) $recs[] = "Steady progress — continue current lesson plan.";

    return [
        'readiness'    => $readiness,
        'status'       => $status,
        'statusColor'  => $statusColor,
        'passProb'     => $passProb,
        'riskScore'    => $riskScore,
        'riskLevel'    => $riskLevel,
        'riskColor'    => $riskColor,
        'daysSince'    => $daysSince,
        'recommendation' => implode(' ', array_slice($recs, 0, 2)),
        'progress'     => round($progress * 100),
        'attendanceRate' => round($attendanceRate * 100),
        'lessonsTaken' => $present,
        'lessonsTarget' => $target,
    ];
}

$predictions = [];
if ($students_q) while ($s = $students_q->fetch_assoc()) {
    $predictions[] = array_merge($s, ['ai' => computeInsights($s, $course_targets, $default_target)]);
}

// Aggregate counts for KPI cards
$readyCount = 0; $nearlyCount = 0; $progressCount = 0; $needsCount = 0;
$lowRisk = 0; $medRisk = 0; $highRisk = 0;
foreach ($predictions as $p) {
    switch ($p['ai']['status']) {
        case 'Ready': $readyCount++; break;
        case 'Nearly Ready': $nearlyCount++; break;
        case 'In Progress': $progressCount++; break;
        default: $needsCount++; break;
    }
    switch ($p['ai']['riskLevel']) {
        case 'Low Risk': $lowRisk++; break;
        case 'Medium Risk': $medRisk++; break;
        default: $highRisk++; break;
    }
}

// ═══════════════════════════════════════════════
// AUTO-GENERATED BUSINESS INSIGHT TEXT
// ═══════════════════════════════════════════════
$insightLines = [];

if ($revenue_change > 0) {
    $insightLines[] = "Revenue is up {$revenue_change}% vs last month (R" . number_format($this_month_total,0) . " so far).";
} elseif ($revenue_change < 0) {
    $insightLines[] = "Revenue is down " . abs($revenue_change) . "% vs last month — currently R" . number_format($this_month_total,0) . ".";
} else {
    $insightLines[] = "Revenue this month: R" . number_format($this_month_total,0) . ".";
}

if ($att_this_month !== null && $att_last_month !== null) {
    $diff = $att_this_month - $att_last_month;
    if ($diff > 0) $insightLines[] = "Attendance rate improved by {$diff} points to {$att_this_month}% this month.";
    elseif ($diff < 0) $insightLines[] = "Attendance rate dropped by " . abs($diff) . " points to {$att_this_month}% — worth monitoring.";
    else $insightLines[] = "Attendance rate holding steady at {$att_this_month}%.";
}

if ($outstanding_count > 0) {
    $insightLines[] = "{$outstanding_count} payment(s) totalling R" . number_format($outstanding,0) . " are pending confirmation.";
}

if ($highRisk > 0) {
    $insightLines[] = "{$highRisk} student(s) flagged as High Dropout Risk — recommend proactive outreach.";
}

if ($inactive_students > 0) {
    $insightLines[] = "{$inactive_students} student(s) have not attended in the last 14 days.";
}

if (!empty($course_attendance)) {
    usort($course_attendance, fn($a,$b) => $a['pct'] <=> $b['pct']);
    $lowest = $course_attendance[0];
    if ($lowest['pct'] < 80 && $lowest['total'] >= 3) {
        $insightLines[] = "\"{$lowest['course']}\" has the lowest attendance rate at {$lowest['pct']}% — may need schedule review.";
    }
}

$page_title = 'AI Insights';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>AI Insights — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}

    .ai-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
    .ai-kpi{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.3rem;display:flex;align-items:center;gap:1rem;}
    .ai-kpi-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
    .ai-kpi .pv{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:700;line-height:1;}
    .ai-kpi .pl{font-size:11px;color:var(--muted);margin-top:.2rem;}

    .ai-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
    @media(max-width:900px){.ai-grid{grid-template-columns:1fr;}.ai-kpis{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:560px){.ai-kpis{grid-template-columns:1fr;}}

    .dist-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;font-size:13px;}
    .dist-bar-track{background:var(--offwhite);border-radius:6px;height:8px;margin-bottom:1.1rem;overflow:hidden;}
    .dist-bar-fill{height:100%;border-radius:6px;transition:width .4s;}

    .bi-revenue-chart{display:flex;align-items:flex-end;gap:.8rem;height:140px;margin-top:1rem;padding:0 .5rem;}
    .bi-bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:.4rem;}
    .bi-bar{width:100%;border-radius:6px 6px 0 0;background:var(--border);transition:height .4s;position:relative;}
    .bi-bar.current{background:linear-gradient(180deg,#38bdf8,#0ea5e9);}
    .bi-bar-label{font-size:11px;color:var(--muted);}

    .insight-box{
      background:linear-gradient(135deg,#fff7ed,#fef3e2);
      border:1px solid #fed7aa;border-left:4px solid var(--amber);
      border-radius:10px;padding:1rem 1.3rem;margin-bottom:1.5rem;
    }
    .insight-box h4{font-size:13.5px;font-weight:600;color:#92400e;margin-bottom:.5rem;display:flex;align-items:center;gap:7px;}
    .insight-box ul{margin:0;padding-left:1.3rem;color:#92400e;font-size:12.5px;line-height:1.8;}

    .filter-bar{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;}

    .readiness-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 11px;border-radius:20px;font-size:11.5px;font-weight:500;}
    .readiness-pill.green{background:#dcfce7;color:#166534;}
    .readiness-pill.blue{background:#dbeafe;color:#1e40af;}
    .readiness-pill.amber{background:#fef9c3;color:#92400e;}
    .readiness-pill.red{background:#fee2e2;color:#991b1b;}

    .mini-bar-track{background:var(--offwhite);border-radius:5px;height:6px;width:80px;overflow:hidden;display:inline-block;vertical-align:middle;margin-right:6px;}
    .mini-bar-fill{height:100%;border-radius:5px;}

    .student-avatar-sm{width:30px;height:30px;border-radius:50%;background:var(--navy);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;font-family:'Syne',sans-serif;margin-right:8px;flex-shrink:0;}
    .rec-text{font-size:12px;color:var(--muted);max-width:240px;line-height:1.5;}
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

  <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
    <div>
      <h1>🤖 AI Insights</h1>
      <p>Business intelligence + student test readiness & dropout risk predictions</p>
    </div>
    <button class="btn btn-navy" onclick="location.reload()">🔄 Refresh</button>
  </div>

  <!-- Auto-generated insight summary -->
  <div class="insight-box">
    <h4>💡 Key Insights (auto-generated)</h4>
    <ul>
      <?php foreach ($insightLines as $line): ?>
        <li><?= htmlspecialchars($line) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <!-- BI THREE-LAYER DECISION SUPPORT (Chapter 12 requirement) -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;" class="bi-layers">

    <!-- Layer 1: WHAT HAPPENED (Historical) -->
    <div class="card" style="border-top:4px solid #2563eb;">
      <div class="card-body">
        <div style="font-size:11px;font-weight:600;letter-spacing:.5px;color:#2563eb;margin-bottom:.5rem;">📊 WHAT HAPPENED — Historical</div>
        <div style="font-size:13px;color:var(--text);line-height:1.75;">
          <strong>Total revenue confirmed:</strong> R<?= number_format($total_revenue,2) ?><br>
          <strong>This month so far:</strong> R<?= number_format($this_month_total,2) ?><br>
          <strong>Total students enrolled:</strong> <?= $total_students ?><br>
          <strong>Total attendance scans:</strong> <?= $conn->query("SELECT COUNT(*) c FROM attendance")->fetch_assoc()['c'] ?><br>
          <strong>Pending payments:</strong> <?= $outstanding_count ?> (R<?= number_format($outstanding,0) ?>)<br>
          <?php if ($att_last_month !== null): ?>
          <strong>Last month attendance:</strong> <?= $att_last_month ?>%
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Layer 2: WHY IT HAPPENED (Analytical) -->
    <div class="card" style="border-top:4px solid #d97706;">
      <div class="card-body">
        <div style="font-size:11px;font-weight:600;letter-spacing:.5px;color:#d97706;margin-bottom:.5rem;">🔍 WHY IT HAPPENED — Analytical</div>
        <div style="font-size:13px;color:var(--text);line-height:1.75;">
          <?php
          if ($revenue_change > 0)
            echo "<strong>Revenue increased " . $revenue_change . "%</strong> — likely driven by new enrolments and confirmed EFT/card payments this month.<br>";
          elseif ($revenue_change < 0)
            echo "<strong>Revenue declined " . abs($revenue_change) . "%</strong> — fewer confirmed payments vs last month; " . $outstanding_count . " payments still pending review.<br>";
          else
            echo "<strong>Revenue unchanged</strong> — payment pace consistent with last month.<br>";

          if ($inactive_students > 0)
            echo "<strong>{$inactive_students} inactive student(s)</strong> — students who have not attended in 14+ days may have scheduling conflicts or are considering dropping out.<br>";

          if ($highRisk > 0)
            echo "<strong>{$highRisk} high-risk student(s)</strong> — flagged due to low attendance rates, extended absence gaps, or unresolved payments.<br>";

          if (!empty($course_attendance)) {
            usort($course_attendance, fn($a,$b) => $a['pct'] <=> $b['pct']);
            $lowest = $course_attendance[0];
            if ($lowest['pct'] < 80 && $lowest['total'] >= 2)
              echo "<strong>\"{$lowest['course']}\" attendance at {$lowest['pct']}%</strong> — may reflect scheduling difficulty or student motivation for this course type.";
          }
          ?>
        </div>
      </div>
    </div>

    <!-- Layer 3: WHAT IS LIKELY TO HAPPEN NEXT (Predictive) -->
    <div class="card" style="border-top:4px solid #16a34a;">
      <div class="card-body">
        <div style="font-size:11px;font-weight:600;letter-spacing:.5px;color:#16a34a;margin-bottom:.5rem;">🔮 WHAT IS LIKELY NEXT — Predictive</div>
        <div style="font-size:13px;color:var(--text);line-height:1.75;">
          <?php
          // Revenue projection: extend current month trend
          $projectedRevenue = $this_month_total > 0
            ? round($this_month_total * (30 / max(1, (int)date('d'))), -2)
            : 0;
          echo "<strong>Projected month-end revenue:</strong> ~R" . number_format($projectedRevenue,0) . " (based on current daily pace)<br>";

          if ($readyCount > 0)
            echo "<strong>{$readyCount} student(s) are test-ready</strong> — expect test bookings soon; ensure admin support is available.<br>";

          if ($nearlyCount > 0)
            echo "<strong>{$nearlyCount} student(s) nearly ready</strong> — likely to convert to test candidates within 2-4 weeks if attendance holds.<br>";

          if ($highRisk > 0)
            echo "<strong>{$highRisk} dropout risk</strong> — proactive outreach in the next 7 days could recover these students before they disengage permanently.<br>";

          if ($outstanding_count > 0)
            echo "<strong>R" . number_format($outstanding,0) . " outstanding</strong> — confirmations expected from {$outstanding_count} pending payment(s); follow up to maintain cash flow.";
          ?>
        </div>
      </div>
    </div>
  </div>

  <style>
    @media(max-width:900px){.bi-layers{grid-template-columns:1fr!important;}}
  </style>

  <!-- Readiness KPI row -->
  <div class="ai-kpis">
    <div class="ai-kpi">
      <div class="ai-kpi-icon" style="background:#dcfce7;color:#16a34a;">✅</div>
      <div><div class="pv"><?= $readyCount ?></div><div class="pl">Ready for Test</div></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-icon" style="background:#fef9c3;color:#d97706;">⏱️</div>
      <div><div class="pv"><?= $nearlyCount ?></div><div class="pl">Nearly Ready</div></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-icon" style="background:#fee2e2;color:var(--red);">⚠️</div>
      <div><div class="pv"><?= $highRisk ?></div><div class="pl">High Dropout Risk</div></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-icon" style="background:#dbeafe;color:#2563eb;">🤖</div>
      <div><div class="pv"><?= count($predictions) ?></div><div class="pl">Students Analysed</div></div>
    </div>
  </div>

  <!-- Business Intelligence row -->
  <div class="ai-grid">
    <div class="card">
      <div class="card-body">
        <div class="section-title">📊 Monthly Revenue (Confirmed)</div>
        <div class="bi-revenue-chart">
          <?php foreach ($monthly_revenue as $i => $m):
            $h = $m['total'] > 0 ? max(6, round(($m['total']/$max_revenue)*120)) : 4;
            $isCurrent = $i === 5;
          ?>
          <div class="bi-bar-col">
            <div style="font-size:11px;font-weight:600;color:<?= $isCurrent?'var(--navy)':'var(--muted)' ?>;">R<?= number_format($m['total'],0) ?></div>
            <div class="bi-bar <?= $isCurrent?'current':'' ?>" style="height:<?= $h ?>px;"></div>
            <div class="bi-bar-label"><?= $m['label'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:1rem;font-size:12.5px;color:var(--muted);">
          Outstanding: <strong style="color:var(--red);">R<?= number_format($outstanding,0) ?></strong> across <?= $outstanding_count ?> pending payment(s)
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="section-title">📈 Attendance by Course (60 days)</div>
        <?php if (!empty($course_attendance)): foreach ($course_attendance as $c):
          $color = $c['pct'] >= 85 ? '#16a34a' : ($c['pct'] >= 70 ? '#2563eb' : '#dc2626');
        ?>
        <div class="dist-row"><span><?= htmlspecialchars($c['course']) ?></span><strong><?= $c['pct'] ?>%</strong></div>
        <div class="dist-bar-track"><div class="dist-bar-fill" style="width:<?= $c['pct'] ?>%;background:<?= $color ?>;"></div></div>
        <?php endforeach; else: ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0;">Not enough attendance data yet.</p>
        <?php endif; ?>

        <div style="margin-top:1rem;font-size:12.5px;color:var(--muted);">
          <?= $active_students ?> of <?= $total_students ?> students active in last 14 days
          <?php if ($inactive_students>0): ?> · <strong style="color:var(--red);"><?= $inactive_students ?> inactive</strong><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Test Readiness Distribution + Dropout Risk -->
  <div class="ai-grid">
    <div class="card">
      <div class="card-body">
        <div class="section-title">📋 Test Readiness Distribution</div>
        <?php
          $statuses = [
            ['Ready','green',$readyCount],
            ['Nearly Ready','blue',$nearlyCount],
            ['In Progress','amber',$progressCount],
            ['Needs More Training','red',$needsCount],
          ];
          $colorMap = ['green'=>'#16a34a','blue'=>'#2563eb','amber'=>'#d97706','red'=>'#dc2626'];
          $maxStatus = max(1, max(array_column($statuses,2)));
          foreach ($statuses as [$label,$color,$count]):
            $pct = round(($count/$maxStatus)*100);
        ?>
        <div class="dist-row"><span><?= $label ?></span><strong><?= $count ?></strong></div>
        <div class="dist-bar-track"><div class="dist-bar-fill" style="width:<?= $pct ?>%;background:<?= $colorMap[$color] ?>;"></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="section-title">⚠️ Dropout Risk Distribution</div>
        <?php
          $risks = [['Low Risk','#16a34a',$lowRisk],['Medium Risk','#d97706',$medRisk],['High Risk','#dc2626',$highRisk]];
          $maxRisk = max(1, max(array_column($risks,2)));
          foreach ($risks as [$label,$color,$count]):
            $pct = round(($count/$maxRisk)*100);
        ?>
        <div class="dist-row"><span><?= $label ?></span><strong><?= $count ?></strong></div>
        <div class="dist-bar-track"><div class="dist-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar">
    <input type="text" name="search" class="form-control" placeholder="Search student..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:200px;">
    <select name="readiness" class="form-control">
      <option value="">All Readiness</option>
      <?php foreach (['Ready','Nearly Ready','In Progress','Needs More Training'] as $st): ?>
      <option value="<?= $st ?>" <?= (($_GET['readiness']??'')===$st)?'selected':'' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
    <select name="risk" class="form-control">
      <option value="">All Risk Levels</option>
      <?php foreach (['Low Risk','Medium Risk','High Risk'] as $rk): ?>
      <option value="<?= $rk ?>" <?= (($_GET['risk']??'')===$rk)?'selected':'' ?>><?= $rk ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-navy">Filter</button>
  </form>

  <!-- Student Predictions Table -->
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="section-title" style="padding:1.2rem 1.4rem 0;">
        <span>🤖 Student Predictions</span>
        <span class="badge" style="background:var(--offwhite);color:var(--text);"><?= count($predictions) ?> students</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Student</th><th>Test Readiness</th><th>Pass Probability</th>
              <th>Readiness Status</th><th>Dropout Risk</th><th>Risk Level</th><th>Recommendation</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $search = trim($_GET['search'] ?? '');
          $readinessFilter = trim($_GET['readiness'] ?? '');
          $riskFilter = trim($_GET['risk'] ?? '');
          $shown = 0;
          foreach ($predictions as $p):
              $name = $p['FirstName'].' '.$p['LastName'];
              if ($search && stripos($name, $search) === false) continue;
              if ($readinessFilter && $p['ai']['status'] !== $readinessFilter) continue;
              if ($riskFilter && $p['ai']['riskLevel'] !== $riskFilter) continue;
              $shown++;
              $initials = strtoupper(substr($p['FirstName'],0,1).substr($p['LastName'],0,1));
              $readyColor = $colorMap[$p['ai']['statusColor']] ?? '#64748b';
              $riskColorHex = ['green'=>'#16a34a','amber'=>'#d97706','red'=>'#dc2626'][$p['ai']['riskColor']] ?? '#64748b';
          ?>
            <tr>
              <td><span class="student-avatar-sm"><?= $initials ?></span><?= htmlspecialchars($name) ?></td>
              <td>
                <strong style="color:<?= $readyColor ?>;"><?= $p['ai']['readiness'] ?>%</strong>
                <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?= $p['ai']['readiness'] ?>%;background:<?= $readyColor ?>;"></div></div>
              </td>
              <td><?= $p['ai']['passProb'] ?>%</td>
              <td><span class="readiness-pill <?= $p['ai']['statusColor'] ?>">● <?= $p['ai']['status'] ?></span></td>
              <td>
                <?= $p['ai']['riskScore'] ?>
                <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?= $p['ai']['riskScore'] ?>%;background:<?= $riskColorHex ?>;"></div></div>
              </td>
              <td><span class="readiness-pill <?= $p['ai']['riskColor'] ?>"><?= $p['ai']['riskLevel'] ?></span></td>
              <td class="rec-text"><?= htmlspecialchars($p['ai']['recommendation']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($shown === 0): ?>
            <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--muted);">No students match the selected filters.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
</div>
</body>
</html>