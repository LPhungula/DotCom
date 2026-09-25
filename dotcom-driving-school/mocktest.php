<?php
require_once 'includes/db.php';
require_student();

$stmt = $conn->prepare("SELECT * FROM students WHERE UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) { header('Location: logout.php'); exit; }
$sid = $student['StudentID'];

// Lock check — must have at least 1 Present attendance
$attended = (int)$conn->query("SELECT COUNT(*) c FROM attendance WHERE StudentID=$sid AND Status='Present'")->fetch_assoc()['c'];
$unlocked = $attended >= 1;

// Save result
if ($unlocked && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_test'])) {
    $score    = (int)$_POST['score'];
    $total    = (int)$_POST['total'];
    $pct      = $total > 0 ? round(($score/$total)*100, 2) : 0;
    $passed   = $pct >= 77 ? 1 : 0;
    $timeTaken= (int)($_POST['time_taken'] ?? 0);
    $ins = $conn->prepare("INSERT INTO mock_test_results (StudentID,Score,Total,Percentage,Passed,TimeTaken) VALUES (?,?,?,?,?,?)");
    $ins->bind_param('iiidii', $sid, $score, $total, $pct, $passed, $timeTaken);
    $ins->execute();
    header('Location: mock_test.php?result='.$conn->insert_id);
    exit;
}

// Load result if viewing
$showResult = null;
if (isset($_GET['result']) && is_numeric($_GET['result'])) {
    $rid = (int)$_GET['result'];
    $rs  = $conn->prepare("SELECT * FROM mock_test_results WHERE ResultID=? AND StudentID=? LIMIT 1");
    $rs->bind_param('ii', $rid, $sid);
    $rs->execute();
    $showResult = $rs->get_result()->fetch_assoc();
}

// History (last 5)
$history = $conn->query("SELECT * FROM mock_test_results WHERE StudentID=$sid ORDER BY TakenAt DESC LIMIT 5");
$histRows = $history->fetch_all(MYSQLI_ASSOC);
$bestScore = !empty($histRows) ? max(array_column($histRows,'Percentage')) : null;
$totalAttempts = count($histRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>K53 Mock Test — Dot Com Driving School</title>
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

    /* Lock card */
    .lock-card{text-align:center;padding:4rem 2rem;}
    .lock-icon{font-size:4rem;margin-bottom:1rem;}
    .lock-card h2{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:.6rem;}
    .lock-card p{color:var(--muted);font-size:14px;line-height:1.7;max-width:420px;margin:0 auto 1.5rem;}
    .lock-step{display:flex;align-items:center;gap:12px;border-radius:8px;padding:.8rem 1rem;font-size:13.5px;margin-bottom:.6rem;max-width:380px;margin-left:auto;margin-right:auto;}
    .lock-step.done{background:#dcfce7;border:1px solid #bbf7d0;}
    .lock-step.todo{background:#fee2e2;border:1px solid #fecaca;}
    .ls-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;flex-shrink:0;}
    .lock-step.done .ls-num{background:#16a34a;color:#fff;}
    .lock-step.todo .ls-num{background:var(--red);color:#fff;}

    /* Start screen */
    .mt-info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;}
    .mt-info-card{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:1.3rem;text-align:center;}
    .mt-info-icon{font-size:2rem;margin-bottom:.6rem;}
    .mt-info-card h4{font-family:'Syne',sans-serif;font-size:14px;color:var(--navy);margin-bottom:.3rem;}
    .mt-info-card p{font-size:12px;color:var(--muted);line-height:1.5;margin:0;}
    .mt-history-wrap{background:var(--offwhite);border-radius:10px;padding:1rem 1.2rem;margin-bottom:1.2rem;}
    .mt-hist-row{display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid var(--border);font-size:13px;}
    .mt-hist-row:last-child{border-bottom:none;}

    /* Test header */
    .mt-test-hdr{background:var(--navy);color:#fff;border-radius:12px;padding:1.2rem 1.5rem;margin-bottom:1.2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;}
    .mt-pbar-track{background:rgba(255,255,255,0.2);border-radius:999px;height:8px;overflow:hidden;width:250px;max-width:100%;}
    .mt-pbar-fill{background:#f5b800;height:100%;border-radius:999px;transition:width .3s;}
    .mt-timer-val{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:700;color:#f5b800;}
    .mt-timer-val.urgent{color:#f87171;animation:blink 1s infinite;}
    @keyframes blink{0%,100%{opacity:1;}50%{opacity:.5;}}

    /* Question */
    .mt-question-wrap{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:1.8rem;margin-bottom:1.2rem;}
    .mt-q-category{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--red);font-weight:600;margin-bottom:.6rem;}
    .mt-q-text{font-size:1rem;font-weight:600;color:var(--navy);margin-bottom:1.2rem;line-height:1.55;}
    .mt-options{display:flex;flex-direction:column;gap:.55rem;}
    .mt-opt{background:var(--offwhite);border:2px solid var(--border);border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;transition:all .15s;font-size:13.5px;}
    .mt-opt:hover{border-color:var(--navy);background:#f0f4ff;}
    .mt-opt.selected{border-color:#f5b800;background:#fffbeb;}
    .mt-opt.correct{border-color:#16a34a;background:#dcfce7;color:#14532d;font-weight:600;}
    .mt-opt.wrong{border-color:var(--red);background:#fee2e2;color:#7f1d1d;}
    .mt-opt-letter{width:28px;height:28px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;}
    .mt-nav-btns{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;}

    /* Results */
    .mt-result-card{background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:2rem;text-align:center;margin-bottom:1.5rem;}
    .mt-result-score{font-size:3.5rem;font-weight:800;font-family:'Syne',sans-serif;}
    .mt-result-score.pass{color:#16a34a;}
    .mt-result-score.fail{color:var(--red);}
    .mt-result-label{font-size:1.2rem;font-weight:700;font-family:'Syne',sans-serif;margin-bottom:.5rem;}
    .mt-result-sub{color:var(--muted);font-size:13.5px;margin-bottom:1.5rem;}
    .mt-result-breakdown{display:flex;gap:2rem;justify-content:center;flex-wrap:wrap;}
    .mt-rb-item{text-align:center;}
    .mt-rb-num{font-size:1.8rem;font-weight:700;font-family:'Syne',sans-serif;}
    .mt-review{display:flex;flex-direction:column;gap:.75rem;margin-top:1.5rem;}
    .mt-rv-item{background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:1.2rem;}
    .mt-rv-item.rv-correct{border-left:4px solid #16a34a;}
    .mt-rv-item.rv-wrong{border-left:4px solid var(--red);}
    .mt-rv-q{font-weight:600;font-size:13.5px;margin-bottom:.4rem;color:var(--navy);}
    .mt-rv-a{font-size:12.5px;color:var(--muted);}
    .mt-rv-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;margin-left:.5rem;}
    .mt-rv-badge.correct{background:#dcfce7;color:#14532d;}
    .mt-rv-badge.wrong{background:#fee2e2;color:#991b1b;}

    .hidden{display:none!important;}
    .answered-count{font-size:13px;color:var(--muted);}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="dash-header">
  <h1>📝 K53 Mock Test</h1>
  <p>68 questions · 30 minutes · Pass mark: 77% — just like the real DLTC test</p>
</div>

<div class="dash-body">
  <div style="display:grid;grid-template-columns:180px 1fr;gap:1.5rem;align-items:start;">
    <!-- Sidebar -->
    <div class="sidebar-menu card" style="padding:1rem;">
      <a href="student_dashboard.php" class="menu-item">🏠 Dashboard</a>
      <a href="student_profile.php"   class="menu-item">👤 My Profile</a>
      <a href="student_dashboard.php#qr" class="menu-item">📷 My QR Code</a>
      <a href="study_materials.php"   class="menu-item">📚 Study Materials</a>
      <a href="student_payments.php"  class="menu-item">💳 Payments</a>
      <a href="my_readiness.php"      class="menu-item">🤖 My Readiness</a>
      <a href="mock_test.php"         class="menu-item active">📝 Mock Test</a>
      <a href="official_test.php"     class="menu-item">🏛️ Official Test</a>
      <a href="change_password.php"   class="menu-item">🔒 Change Password</a>
      <a href="logout.php"            class="menu-item">🚪 Logout</a>
    </div>

    <!-- Main -->
    <div>
      <?php if (!$unlocked): ?>
      <!-- LOCKED -->
      <div class="card">
        <div class="card-body lock-card">
          <div class="lock-icon">🔒</div>
          <h2>Mock Test Locked</h2>
          <p>The mock test unlocks automatically after you attend your <strong>first lesson</strong>.</p>
          <div class="lock-step done"><div class="ls-num">✓</div><span>Registered — <?= htmlspecialchars($student['CourseType']) ?></span></div>
          <div class="lock-step todo"><div class="ls-num">2</div><span>Attend your first lesson (QR scan required)</span></div>
          <div class="lock-step todo"><div class="ls-num">3</div><span>Mock test unlocks automatically</span></div>
          <br>
          <a href="study_materials.php" class="btn btn-red btn-lg">📚 Study While You Wait</a>
        </div>
      </div>

      <?php elseif ($showResult): ?>
      <!-- RESULT VIEW -->
      <?php
        $pct    = (float)$showResult['Percentage'];
        $passed = (bool)$showResult['Passed'];
        $score  = (int)$showResult['Score'];
        $total  = (int)$showResult['Total'];
        $wrong  = $total - $score;
        $timeSecs = (int)$showResult['TimeTaken'];
        $timeStr  = $timeSecs > 0 ? floor($timeSecs/60).'m '.($timeSecs%60).'s' : '—';
      ?>
      <div class="mt-result-card">
        <div class="mt-result-score <?= $passed?'pass':'fail' ?>"><?= number_format($pct,1) ?>%</div>
        <div class="mt-result-label"><?= $passed ? '🎉 PASS — Well Done!' : '❌ Not Quite — Keep Studying!' ?></div>
        <div class="mt-result-sub">
          You scored <?= $score ?> out of <?= $total ?> correct.<br>
          <?= $passed ? 'You are on track for the official K53 test!' : 'You need 77% (≥53 correct) to pass. Review materials and try again.' ?>
        </div>
        <div class="mt-result-breakdown">
          <div class="mt-rb-item"><div class="mt-rb-num" style="color:#16a34a;"><?= $score ?></div><div style="font-size:12px;color:var(--muted);">Correct</div></div>
          <div class="mt-rb-item"><div class="mt-rb-num" style="color:var(--red);"><?= $wrong ?></div><div style="font-size:12px;color:var(--muted);">Wrong</div></div>
          <div class="mt-rb-item"><div class="mt-rb-num"><?= $total ?></div><div style="font-size:12px;color:var(--muted);">Total</div></div>
          <div class="mt-rb-item"><div class="mt-rb-num" style="font-size:1.2rem;"><?= $timeStr ?></div><div style="font-size:12px;color:var(--muted);">Time</div></div>
        </div>
      </div>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
        <a href="mock_test.php" class="btn btn-red btn-lg">🔄 Try Again</a>
        <a href="study_materials.php" class="btn btn-navy btn-lg">📚 Review Study Materials</a>
        <a href="official_test.php" class="btn btn-white btn-lg" style="border:1px solid var(--border);">🏛️ Book Official Test</a>
      </div>
      <!-- Answer review loaded from JS using session-stored answers -->
      <div class="card">
        <div class="card-body">
          <div class="section-title mb-2">📋 Answer Review</div>
          <div class="mt-review" id="reviewArea"><p style="color:var(--muted);font-size:13px;">Start a new test to see the answer review here.</p></div>
        </div>
      </div>

      <?php else: ?>
      <!-- START SCREEN -->
      <div id="mt-start">
        <div class="mt-info-grid">
          <div class="mt-info-card"><div class="mt-info-icon">❓</div><h4>68 Questions</h4><p>Road Signs (22), Rules of the Road (26), Vehicle Controls (20) — just like the real K53.</p></div>
          <div class="mt-info-card"><div class="mt-info-icon">⏱️</div><h4>30 Minutes</h4><p>Timed test. The actual DLTC K53 test is also 30 minutes — practice under pressure.</p></div>
          <div class="mt-info-card"><div class="mt-info-icon">✅</div><h4>Pass Mark: 77%</h4><p>You need at least 53 out of 68 correct to pass — same threshold as the official test.</p></div>
          <div class="mt-info-card"><div class="mt-info-icon">📊</div><h4>Instant Results</h4><p>Score, correct answers, and explanations shown immediately after you submit.</p></div>
        </div>

        <?php if (!empty($histRows)): ?>
        <div class="mt-history-wrap">
          <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:14px;color:var(--navy);margin-bottom:.75rem;">Your Test History</div>
          <?php foreach ($histRows as $h): ?>
          <div class="mt-hist-row">
            <span><?= date('d M Y H:i', strtotime($h['TakenAt'])) ?></span>
            <span><?= $h['Score'] ?>/<?= $h['Total'] ?> — <?= number_format($h['Percentage'],1) ?>%</span>
            <span class="badge" style="<?= $h['Passed']?'background:#dcfce7;color:#166534;':'background:#fee2e2;color:#991b1b;' ?>"><?= $h['Passed']?'Pass':'Fail' ?></span>
          </div>
          <?php endforeach; ?>
          <div style="display:flex;gap:1.5rem;margin-top:.8rem;font-size:12.5px;color:var(--muted);">
            <span>Best: <strong style="color:var(--navy);"><?= number_format($bestScore,1) ?>%</strong></span>
            <span>Attempts: <strong style="color:var(--navy);"><?= $totalAttempts ?></strong></span>
          </div>
        </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:1.5rem;">
          <button class="btn btn-red" style="font-size:1.1rem;padding:1rem 2.5rem;" onclick="startTest()">🚀 Start Mock Test</button>
        </div>
      </div>

      <!-- TEST SCREEN (built by JS) -->
      <div id="mt-test" class="hidden">
        <div class="mt-test-hdr">
          <div>
            <div id="mt-q-counter" style="font-size:13px;color:rgba(255,255,255,0.7);margin-bottom:.4rem;">Question 1 of 68</div>
            <div class="mt-pbar-track"><div class="mt-pbar-fill" id="mtPbar" style="width:0%"></div></div>
          </div>
          <div style="display:flex;align-items:center;gap:.5rem;">
            <span style="font-size:1rem;">⏱️</span>
            <span class="mt-timer-val" id="mtTimer">30:00</span>
          </div>
        </div>
        <div id="mt-question-wrap" class="mt-question-wrap"></div>
        <div class="mt-nav-btns">
          <button class="btn btn-white" id="mt-prev" onclick="mtPrev()" disabled style="border:1px solid var(--border);">← Previous</button>
          <span class="answered-count" id="answeredCount">0 of 68 answered</span>
          <button class="btn btn-red" id="mt-next" onclick="mtNext()">Next →</button>
        </div>
      </div>

      <!-- FORM to submit to PHP -->
      <form method="POST" id="submitForm" style="display:none;">
        <input type="hidden" name="submit_test" value="1">
        <input type="hidden" name="score"      id="formScore">
        <input type="hidden" name="total"      id="formTotal">
        <input type="hidden" name="time_taken" id="formTime">
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  FULL K53 QUESTION BANK — 68 QUESTIONS
// ══════════════════════════════════════════════════════════════
const QUESTIONS = [
  // ROAD SIGNS (22)
  {cat:'Road Signs',q:'A red octagonal sign with white letters STOP means:',opts:['Slow down and look both ways','Come to a complete stop and yield to all traffic','Stop only if other vehicles are present','Reduce speed to 20 km/h'],a:1},
  {cat:'Road Signs',q:'A red circle with a white horizontal bar means:',opts:['Maximum speed limit','No entry for vehicles','No stopping','Yield to oncoming traffic'],a:1},
  {cat:'Road Signs',q:'A yellow diamond-shaped sign generally indicates:',opts:['A mandatory instruction','A warning of a hazard ahead','The speed limit','An information guide'],a:1},
  {cat:'Road Signs',q:'A round blue sign with a white arrow pointing right means:',opts:['You may turn right if clear','You MUST turn right','No right turn ahead','Right lane ends'],a:1},
  {cat:'Road Signs',q:'What colour are regulatory signs in South Africa?',opts:['Yellow and black','Blue and white','Red, white, and black','Green and white'],a:2},
  {cat:'Road Signs',q:'A sign showing a motorcycle with a red cross through it means:',opts:['Motorcycles may park here','Motorcycles are prohibited','Motorcycle lane ahead','Motorcycle warning zone'],a:1},
  {cat:'Road Signs',q:'A triangular sign (pointing up) with a red border is a:',opts:['Regulatory sign','Warning sign','Yield sign','Information sign'],a:2},
  {cat:'Road Signs',q:'What does a sign with "80" inside a red circle mean?',opts:['Minimum speed 80 km/h','Maximum speed 80 km/h','Advisory speed 80 km/h','End of speed restriction'],a:1},
  {cat:'Road Signs',q:'A "No Overtaking" sign applies until:',opts:['You have passed 5 vehicles','The next traffic light','You see an end-of-restriction sign or change in road markings','It has been 2km'],a:2},
  {cat:'Road Signs',q:'A level crossing sign warns you of:',opts:['A steep hill','A railway line crossing the road','A pedestrian crossing','A sharp bend'],a:1},
  {cat:'Road Signs',q:'What does a pedestrian crossing (zebra crossing) sign look like?',opts:['Blue rectangle with a walking person','Yellow diamond with walking figure','Red octagon','Green rectangle'],a:1},
  {cat:'Road Signs',q:'A broken white line in the centre of the road means:',opts:['No overtaking at all','You may overtake if safe','Road is ending','Pedestrian zone'],a:1},
  {cat:'Road Signs',q:'A solid yellow line on your side of the road means:',opts:['Advisory caution: slow down','You may NOT cross or overtake','End of speed restriction','Bicycle lane ahead'],a:1},
  {cat:'Road Signs',q:'An inverted triangle (point down) sign means:',opts:['Stop completely','Yield to all approaching traffic','Warning of danger','One-way road ahead'],a:1},
  {cat:'Road Signs',q:'What do green direction signs indicate?',opts:['Major hazards ahead','Mandatory turns','Routes and directions on national roads','No-go zones'],a:2},
  {cat:'Road Signs',q:'A sign showing two opposing arrows on a narrow road means:',opts:['Overtaking is permitted','Two-way traffic ahead — prepare for oncoming vehicles','Road narrows','High speed zone'],a:1},
  {cat:'Road Signs',q:'A "No Stopping" sign means:',opts:['You may stop for 5 minutes','You may not stop at all in that zone','You may stop to drop passengers','Parking is restricted to 30 minutes'],a:1},
  {cat:'Road Signs',q:'What is the shape of a STOP sign?',opts:['Triangle','Circle','Octagon','Diamond'],a:2},
  {cat:'Road Signs',q:'A sign with children and a school building means:',opts:['Maximum speed 40 km/h applies','Children may cross anywhere','School buses have priority','No children under 18'],a:0},
  {cat:'Road Signs',q:'When you see a "Slippery Road" sign, you should:',opts:['Increase speed to maintain momentum','Reduce speed and avoid harsh braking or steering','Switch to neutral gear','Brake firmly and quickly'],a:1},
  {cat:'Road Signs',q:'A blue rectangular sign with a "P" means:',opts:['Parking is prohibited','Parking is permitted','Police checkpoint ahead','Pedestrian priority zone'],a:1},
  {cat:'Road Signs',q:'A sign with a torch/flashlight icon warns of:',opts:['Tunnel ahead','Low visibility zone','No lights zone','Emergency vehicles ahead'],a:0},
  // RULES OF THE ROAD (26)
  {cat:'Rules of the Road',q:'At an uncontrolled intersection you must yield to:',opts:['Vehicles on your left','Vehicles on your right','Vehicles coming from straight ahead','No one — first to arrive proceeds'],a:1},
  {cat:'Rules of the Road',q:'At a 4-way stop, if two vehicles arrive simultaneously, who has right of way?',opts:['The heavier vehicle','The vehicle on the left yields to the one on the right','The faster vehicle','The vehicle going straight'],a:1},
  {cat:'Rules of the Road',q:'The general speed limit on a freeway in South Africa is:',opts:['100 km/h','110 km/h','120 km/h','140 km/h'],a:2},
  {cat:'Rules of the Road',q:'The legal blood alcohol limit for a driver in South Africa is:',opts:['0.02g per 100ml blood','0.05g per 100ml blood','0.08g per 100ml blood','0.10g per 100ml blood'],a:1},
  {cat:'Rules of the Road',q:'You should use the 2-second rule to maintain:',opts:['The correct speed','A safe following distance','Your lane position','Correct mirror use frequency'],a:1},
  {cat:'Rules of the Road',q:'When may you use a cell phone while driving?',opts:['At a red traffic light','Only to make calls','Only if you use a hands-free system','Never — any use is illegal while the vehicle is moving'],a:3},
  {cat:'Rules of the Road',q:'In wet conditions, your following distance should be:',opts:['The same as dry conditions','Halved','Doubled to at least 4 seconds','Tripled to 6 seconds'],a:2},
  {cat:'Rules of the Road',q:'You are approaching a yellow traffic light. You should:',opts:['Speed up to clear the intersection','Stop if it is safe to do so','Always proceed — yellow means caution','Flash your lights'],a:1},
  {cat:'Rules of the Road',q:'When must headlights be used?',opts:['Only in fog','30 minutes after sunset to 30 minutes before sunrise','Only on freeways at night','Whenever speed exceeds 100 km/h'],a:1},
  {cat:'Rules of the Road',q:'On which side may you overtake in South Africa?',opts:['The left side only','The right side only','Either side if safe','Only on a freeway'],a:1},
  {cat:'Rules of the Road',q:'You must NOT overtake when:',opts:['On a straight road with good visibility','Near the crest of a hill or blind rise','The vehicle in front is travelling at 60 km/h','On a dual carriageway'],a:1},
  {cat:'Rules of the Road',q:'Are all vehicle occupants required to wear seat belts?',opts:['Only the driver','Driver and front-seat passenger','All occupants','Only adults over 18'],a:2},
  {cat:'Rules of the Road',q:'Who is responsible for ensuring passengers under 14 are buckled?',opts:['The passenger themselves','The parent regardless of where they sit','The driver','It is voluntary'],a:2},
  {cat:'Rules of the Road',q:"A learner licence holder's blood alcohol limit is:",opts:['0.05g/100ml','0.02g/100ml','0.00g/100ml (zero tolerance)','0.08g/100ml'],a:2},
  {cat:'Rules of the Road',q:'When approaching an emergency vehicle with sirens on, you must:',opts:['Speed up and get out of its way','Pull to the left and stop until it has passed','Continue at the same speed','Flash your lights and continue'],a:1},
  {cat:'Rules of the Road',q:'What is the urban speed limit in South Africa?',opts:['50 km/h','60 km/h','70 km/h','80 km/h'],a:1},
  {cat:'Rules of the Road',q:'You must NOT use your hooter (horn):',opts:['To warn of danger on an open road','Between 21:00 and 06:00 in a built-up area','In an emergency situation','When a pedestrian steps into the road'],a:1},
  {cat:'Rules of the Road',q:'When parking on a hill facing uphill, you should turn your wheels:',opts:['Straight ahead','Toward the kerb (right)','Away from the kerb (left)','Position does not matter'],a:2},
  {cat:'Rules of the Road',q:'What is the minimum tread depth for vehicle tyres?',opts:['1mm','1.6mm','2.5mm','3mm'],a:1},
  {cat:'Rules of the Road',q:'In a roundabout, who has right of way?',opts:['Vehicles entering the roundabout','Vehicles already inside the roundabout','The largest vehicle','Vehicles on the left'],a:1},
  {cat:'Rules of the Road',q:'A double solid centre line means:',opts:['Overtaking is allowed for faster vehicles','No vehicle may cross or overtake','The road is ending','Speed limit doubles'],a:1},
  {cat:'Rules of the Road',q:'At a pedestrian (zebra) crossing, you must:',opts:['Hoot to warn pedestrians','Yield to pedestrians already on the crossing','Only stop if pedestrians are in your lane','Flash lights and proceed slowly'],a:1},
  {cat:'Rules of the Road',q:'How far before a turn should you signal?',opts:['At least 20m','At least 30m','At least 50m','At the turn itself'],a:1},
  {cat:'Rules of the Road',q:'You are involved in an accident. What should you do FIRST?',opts:['Leave the scene and report it later','Stop, warn other traffic, assist injured persons, and report to police','Take photos and leave','Move all vehicles off the road immediately'],a:1},
  {cat:'Rules of the Road',q:'Is it legal to make a U-turn at a traffic light?',opts:['Yes, always','No, never','Yes, unless a sign prohibits it','Only on a green arrow'],a:2},
  {cat:'Rules of the Road',q:'What does a broken yellow line separating lanes mean?',opts:['You may NOT overtake','You may change lanes if safe','The road is about to end','Bus lane ahead'],a:1},
  {cat:'Rules of the Road',q:'You see a school bus with flashing amber lights and STOP arm extended. You must:',opts:['Slow to 40 km/h','Stop completely until children have crossed and lights stop','Proceed if no children are in your lane','Hoot to alert children'],a:1},
  // VEHICLE CONTROLS (20)
  {cat:'Vehicle Controls',q:'The MSM routine stands for:',opts:['Move, Stop, Manoeuvre','Mirror, Signal, Manoeuvre','Monitor, Steer, Move','Mirror, Speed, Move'],a:1},
  {cat:'Vehicle Controls',q:'Before moving off, you should perform a blind spot check by:',opts:['Checking only the interior mirror','Looking over your shoulder in the direction of travel','Sounding the hooter','Checking only the door mirror'],a:1},
  {cat:'Vehicle Controls',q:'The clutch pedal on a manual vehicle is located:',opts:['On the far right','In the middle','On the far left','On the steering column'],a:2},
  {cat:'Vehicle Controls',q:'To prevent a manual car from rolling back on a hill start, use:',opts:['The accelerator only','The footbrake and left-foot clutch technique or handbrake method','High gear','Neutral gear with foot on brake'],a:1},
  {cat:'Vehicle Controls',q:'When should you check your mirrors?',opts:['Only when turning','Only when changing lanes','Before every signal, speed change, or manoeuvre','Only when reversing'],a:2},
  {cat:'Vehicle Controls',q:'Coasting (travelling in neutral downhill) is dangerous because:',opts:['It increases tyre wear','You have no engine braking and less steering control','It improves fuel economy too much','It overheats the clutch'],a:1},
  {cat:'Vehicle Controls',q:'What is the "biting point" on a clutch?',opts:['When the clutch pedal is fully pressed','The point where the clutch starts to engage and the car moves forward','When the gear is fully engaged','When the car is in first gear only'],a:1},
  {cat:'Vehicle Controls',q:'In an emergency stop on a vehicle WITHOUT ABS brakes:',opts:['Pump the brakes rapidly','Apply steady, firm pressure to the brake pedal','Press the brake as hard as possible and hold','Use the handbrake first'],a:0},
  {cat:'Vehicle Controls',q:'In an emergency stop on a vehicle WITH ABS brakes:',opts:['Pump the brakes rapidly','Press the brake hard and hold — the ABS prevents wheel lock','Use the handbrake simultaneously','Apply both clutch and brake at once'],a:1},
  {cat:'Vehicle Controls',q:'Low beam headlights should be switched to high beam when:',opts:['In fog','Approaching oncoming traffic within 150m','On an open dark road with no oncoming traffic','In heavy rain'],a:2},
  {cat:'Vehicle Controls',q:'When should you NOT use the handbrake as your primary stopping device?',opts:['When parking','When stopped on a hill','While the vehicle is moving at speed','Before leaving the vehicle'],a:2},
  {cat:'Vehicle Controls',q:'The correct sequence when stopping the vehicle is:',opts:['Brake → Clutch → Handbrake → Neutral','Clutch → Brake → Neutral → Handbrake','Neutral → Brake → Clutch → Handbrake','Handbrake → Neutral → Clutch → Brake'],a:0},
  {cat:'Vehicle Controls',q:'Before a right turn, which mirrors do you check in order?',opts:['Right door mirror only','Interior mirror, then right door mirror','Right door mirror, then interior mirror','Only check blind spot'],a:1},
  {cat:'Vehicle Controls',q:'When reversing, you should:',opts:['Rely solely on mirrors','Look over your right shoulder and check all mirrors','Only look through the rear windscreen','Sound the hooter continuously'],a:1},
  {cat:'Vehicle Controls',q:'Harsh braking on a slippery surface can cause:',opts:['Better braking performance','Wheel lock and loss of steering','Engine overheating','Tyre blowout only'],a:1},
  {cat:'Vehicle Controls',q:'When is it correct to use hazard lights while driving?',opts:['When double parking briefly','When travelling slowly as a warning to other drivers of an obstruction','To signal a right turn','Never — hazard lights are only for stationary vehicles'],a:1},
  {cat:'Vehicle Controls',q:'A vehicle\'s tyres should be checked:',opts:['Once a year','Only before long trips','Regularly — at least monthly','Only when a tyre looks flat'],a:2},
  {cat:'Vehicle Controls',q:'Your steering wheel begins to vibrate at high speed. This most likely indicates:',opts:['Engine problems','Unbalanced or worn tyres','Brake failure','Clutch slipping'],a:1},
  {cat:'Vehicle Controls',q:'When driving through a deep puddle (water splash), what should you do afterward?',opts:['Accelerate hard to dry the brakes','Gently apply the brakes several times to dry them','Come to a complete stop for 2 minutes','Switch off the engine'],a:1},
  {cat:'Vehicle Controls',q:'The purpose of the defroster/rear window heater is:',opts:['To warm the cabin','To clear condensation or ice from the rear window','To boost engine power','To heat the boot'],a:1},
];

// ══ State ══
let questions = [], answers = {}, currentQ = 0, timerInterval = null, timerSecs = 30*60, startTime = null;

function shuffle(arr) {
  for (let i=arr.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[arr[i],arr[j]]=[arr[j],arr[i]];}
  return arr;
}

function startTest() {
  // Build 68-question paper same way as Uni-Drive
  const signs  = shuffle(QUESTIONS.filter(q=>q.cat==='Road Signs')).slice(0,22);
  const rules  = shuffle(QUESTIONS.filter(q=>q.cat==='Rules of the Road')).slice(0,26);
  const ctrls  = shuffle(QUESTIONS.filter(q=>q.cat==='Vehicle Controls')).slice(0,20);
  questions = [...signs, ...rules, ...ctrls];
  answers   = {};
  currentQ  = 0;
  timerSecs = 30*60;
  startTime = Date.now();

  document.getElementById('mt-start').classList.add('hidden');
  document.getElementById('mt-test').classList.remove('hidden');

  renderQuestion();
  startTimer();
}

function renderQuestion() {
  const q   = questions[currentQ];
  const tot = questions.length;
  const letters = ['A','B','C','D'];

  document.getElementById('mt-q-counter').textContent = `Question ${currentQ+1} of ${tot}`;
  document.getElementById('mtPbar').style.width = ((currentQ+1)/tot*100)+'%';
  document.getElementById('answeredCount').textContent = `${Object.keys(answers).length} of ${tot} answered`;
  document.getElementById('mt-prev').disabled = currentQ===0;
  document.getElementById('mt-next').textContent = currentQ===tot-1 ? 'Submit →' : 'Next →';

  document.getElementById('mt-question-wrap').innerHTML = `
    <div class="mt-q-category">${q.cat}</div>
    <div class="mt-q-text">${currentQ+1}. ${q.q}</div>
    <div class="mt-options">
      ${q.opts.map((opt,i)=>`
        <button type="button" class="mt-opt ${answers[currentQ]===i?'selected':''}" onclick="selectAnswer(${i})">
          <div class="mt-opt-letter">${letters[i]}</div>
          <span>${opt}</span>
        </button>`).join('')}
    </div>`;
}

function selectAnswer(idx) {
  answers[currentQ] = idx;
  document.querySelectorAll('.mt-opt').forEach((b,i)=>{
    b.classList.toggle('selected', i===idx);
  });
  document.getElementById('answeredCount').textContent = `${Object.keys(answers).length} of ${questions.length} answered`;
}

function mtNext() {
  if (currentQ === questions.length-1) {
    const unanswered = questions.length - Object.keys(answers).length;
    if (unanswered > 0) {
      if (!confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) return;
    }
    submitTest(); return;
  }
  currentQ++; renderQuestion();
}

function mtPrev() { if (currentQ>0){currentQ--; renderQuestion();} }

function startTimer() {
  clearInterval(timerInterval);
  timerInterval = setInterval(()=>{
    timerSecs--;
    const m=Math.floor(timerSecs/60), s=timerSecs%60;
    const el=document.getElementById('mtTimer');
    el.textContent=`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    el.classList.toggle('urgent', timerSecs<=120);
    if (timerSecs<=0){clearInterval(timerInterval);alert('Time is up! Submitting your test.');submitTest();}
  },1000);
}

function submitTest() {
  clearInterval(timerInterval);
  let correct=0;
  questions.forEach((q,i)=>{ if(answers[i]===q.a) correct++; });
  const timeTaken = Math.round((Date.now()-startTime)/1000);
  document.getElementById('formScore').value = correct;
  document.getElementById('formTotal').value = questions.length;
  document.getElementById('formTime').value  = timeTaken;
  // Store answers in sessionStorage for result review page
  sessionStorage.setItem('mt_answers', JSON.stringify(answers));
  sessionStorage.setItem('mt_questions', JSON.stringify(questions));
  document.getElementById('submitForm').submit();
}

// If on result view, load review from session
<?php if ($showResult): ?>
(function(){
  const qs = sessionStorage.getItem('mt_questions');
  const as = sessionStorage.getItem('mt_answers');
  if (!qs || !as) return;
  const questions = JSON.parse(qs);
  const answers   = JSON.parse(as);
  const letters   = ['A','B','C','D'];
  const area      = document.getElementById('reviewArea');
  area.innerHTML  = questions.map((q,i)=>{
    const chosen    = answers[i];
    const isCorrect = chosen === q.a;
    return `<div class="mt-rv-item ${isCorrect?'rv-correct':'rv-wrong'}">
      <div class="mt-rv-q">${i+1}. ${q.q} <span class="mt-rv-badge ${isCorrect?'correct':'wrong'}">${isCorrect?'✅ Correct':'❌ Wrong'}</span></div>
      <div class="mt-rv-a">
        ${!isCorrect ? `Your answer: <em>${chosen!==undefined ? letters[chosen]+'. '+q.opts[chosen] : 'Not answered'}</em> · ` : ''}
        Correct: <strong>${letters[q.a]}. ${q.opts[q.a]}</strong>
      </div>
    </div>`;
  }).join('');
})();
<?php endif; ?>
</script>
</body>
</html>