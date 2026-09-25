<?php
require_once 'includes/db.php';
require_student();

$stmt = $conn->prepare("SELECT * FROM students WHERE UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) { header('Location: logout.php'); exit; }
$sid = $student['StudentID'];

$error = ''; $success = '';

// Submit booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $testType  = $_POST['test_type']    ?? '';
    $dltc      = $_POST['dltc_centre']  ?? '';
    $testDate  = $_POST['test_date']    ?? '';
    $testTime  = $_POST['test_time']    ?? '';
    $idNum     = trim($_POST['id_number']  ?? '');
    $notes     = trim($_POST['notes']      ?? '');

    if (!$testType || !$dltc || !$testDate || !$testTime || !$idNum) {
        $error = 'Please fill in all required fields including selecting a time slot.';
    } elseif (strtotime($testDate) < strtotime('+7 days')) {
        $error = 'Test date must be at least 7 days from today.';
    } else {
        $ref = 'DCDS-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $ins = $conn->prepare("INSERT INTO official_test_bookings (StudentID, TestType, DLTCCentre, TestDate, TestTime, IDNumber, Notes, Status, RefNumber) VALUES (?,?,?,?,?,?,?,'pending',?)");
        $ins->bind_param('isssssss', $sid, $testType, $dltc, $testDate, $testTime, $idNum, $notes, $ref);
        $ins->execute();
        $success = "✅ Test slot reserved! Your reference number is <strong>$ref</strong>. You will receive confirmation within 2 business days.";
    }
}

// Cancel booking
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $bid = (int)$_GET['cancel'];
    $upd = $conn->prepare("UPDATE official_test_bookings SET Status='cancelled' WHERE BookingID=? AND StudentID=?");
    $upd->bind_param('ii', $bid, $sid);
    $upd->execute();
    header('Location: official_test.php?cancelled=1'); exit;
}

if (isset($_GET['cancelled'])) $success = 'Your test booking has been cancelled.';

// Load my bookings
$mybookings = $conn->query("SELECT * FROM official_test_bookings WHERE StudentID=$sid ORDER BY CreatedAt DESC");

$testNames = [
    'learners_k53'  => "Learner's Licence (K53 Theory)",
    'driving_code8' => 'Driving Test — Code 8',
    'driving_codeA' => 'Driving Test — Code A (Motorcycle)',
    'driving_code10'=> 'Driving Test — Code 10',
    'driving_code14'=> 'Driving Test — Code 14',
];
$dltcNames = [
    'pinetown'     => 'Pinetown DLTC — 14 Crompton St, Pinetown',
    'durban_north' => 'Durban North DLTC — 18 Garbutt Rd, Durban North',
    'umlazi'       => 'Umlazi DLTC — Umlazi, Durban',
];
$testInfo = [
    'learners_k53'  => '📋 K53 Theory Test — 68 questions, 30 minutes. You must score at least 77% (53/68) to pass. Bring your ID and R70 test fee.',
    'driving_code8' => "🚗 Code 8 Driving Test — Includes a yard test and a road test. Bring your Learner's Licence card, ID, and R130 test fee.",
    'driving_codeA' => '🏍️ Code A Motorcycle Test — Requires approved safety gear. Yard circuit + road test. Bring your Learner\'s Licence, ID, and R130 fee.',
    'driving_code10'=> '🚛 Code 10 Heavy Vehicle Test — Must hold Code 8 first. Vehicle must be roadworthy. ID, Code 8 licence, and R200 fee required.',
    'driving_code14'=> '🚚 Code 14 Extra Heavy Test — Must hold Code 10. Articulated vehicle test. ID, Code 10 licence, and R200 fee required.',
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
  <title>Book Official Test — Dot Com Driving School</title>
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

    .testbook-layout{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;}
    @media(max-width:860px){.testbook-layout{grid-template-columns:1fr;}}

    .info-banner{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:1rem 1.2rem;margin-bottom:1.2rem;display:flex;gap:.8rem;font-size:13px;color:#166534;align-items:flex-start;}

    .tb-info-box{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;padding:.9rem 1.1rem;font-size:13px;color:#1e40af;margin-bottom:1.2rem;display:none;}

    .slots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:.5rem;margin-top:.5rem;margin-bottom:1.2rem;}
    .slot-btn{padding:.5rem;border:1.5px solid var(--border);background:#fff;border-radius:6px;font-size:12.5px;font-weight:600;transition:all .15s;cursor:pointer;}
    .slot-btn:hover{border-color:var(--navy);color:var(--navy);}
    .slot-btn.selected{background:var(--navy);color:#fff;border-color:var(--navy);}

    .tbk-item{background:var(--offwhite);border:1px solid var(--border);border-radius:10px;padding:1rem;margin-bottom:.8rem;}
    .tbk-hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;}
    .tbk-type{font-weight:600;font-size:13.5px;}
    .tbk-meta{font-size:12px;color:var(--muted);display:flex;flex-direction:column;gap:.2rem;}

    .tb-checklist{background:#fff;border:1px solid var(--border);border-radius:10px;padding:1.2rem;}
    .tb-checklist h4{font-family:'Syne',sans-serif;font-size:14px;color:var(--navy);margin-bottom:.8rem;}
    .tb-check-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem;}
    .tb-check-list li{font-size:13px;color:var(--text);}

    .centre-info{background:var(--offwhite);border:1px solid var(--border);border-radius:10px;padding:1.2rem;margin-top:1rem;}
    .centre-info h4{font-family:'Syne',sans-serif;font-size:14px;color:var(--navy);margin-bottom:.6rem;}
    .centre-row{display:flex;align-items:flex-start;gap:.5rem;font-size:12.5px;color:var(--muted);margin-bottom:.3rem;}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="dash-header">
  <h1>🏛️ Book Official Test Date</h1>
  <p>Reserve your DLTC test slot — skip the queues</p>
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
      <a href="mock_test.php"         class="menu-item">📝 Mock Test</a>
      <a href="official_test.php"     class="menu-item active">🏛️ Official Test</a>
      <a href="change_password.php"   class="menu-item">🔒 Change Password</a>
      <a href="logout.php"            class="menu-item">🚪 Logout</a>
    </div>

    <!-- Main -->
    <div>
      <?php if ($error): ?><div class="alert alert-error mb-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success mb-2"><?= $success ?></div><?php endif; ?>

      <div class="testbook-layout">
        <!-- Form -->
        <div class="card">
          <div class="card-body">
            <div class="info-banner">
              <span>ℹ️</span>
              <p style="margin:0;">Dot Com Driving School partners with <strong>Pinetown DLTC</strong>, <strong>Durban North DLTC</strong>, and <strong>Umlazi DLTC</strong> to reserve test slots for our students. Book through us and skip the queues.</p>
            </div>

            <div class="section-title" style="margin-bottom:1.2rem;">New Official Test Booking</div>

            <form method="POST" id="testForm">
              <div class="form-group">
                <label>Test Type *</label>
                <select name="test_type" id="testType" class="form-control" required onchange="updateTestInfo()">
                  <option value="">Choose test type…</option>
                  <option value="learners_k53">Learner's Licence (K53 Theory)</option>
                  <option value="driving_code8">Driving Test — Code 8</option>
                  <option value="driving_codeA">Driving Test — Code A (Motorcycle)</option>
                  <option value="driving_code10">Driving Test — Code 10</option>
                  <option value="driving_code14">Driving Test — Code 14</option>
                </select>
              </div>

              <div class="tb-info-box" id="testInfoBox"></div>

              <div class="form-group">
                <label>Preferred DLTC Centre *</label>
                <select name="dltc_centre" id="dltcCentre" class="form-control" required onchange="updateCentreInfo()">
                  <option value="">Choose DLTC centre…</option>
                  <option value="pinetown">Pinetown DLTC — 14 Crompton St, Pinetown</option>
                  <option value="durban_north">Durban North DLTC — 18 Garbutt Rd, Durban North</option>
                  <option value="umlazi">Umlazi DLTC — Umlazi, Durban</option>
                </select>
              </div>

              <div id="centreInfoBox"></div>

              <div class="form-group">
                <label>Preferred Date * <small style="color:var(--muted);">(at least 7 days from today)</small></label>
                <input type="date" name="test_date" id="testDate" class="form-control" required
                  min="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                  onchange="loadSlots()">
              </div>

              <div id="slotsWrapper" style="display:none;">
                <label style="font-size:13.5px;font-weight:500;margin-bottom:.5rem;display:block;">Available Test Slots</label>
                <div class="slots-grid" id="slotsGrid"></div>
                <input type="hidden" name="test_time" id="testTime">
              </div>

              <div class="form-group">
                <label>ID / Passport Number *</label>
                <input type="text" name="id_number" class="form-control" placeholder="e.g. 9001015001088" required>
              </div>

              <div class="form-group">
                <label>Special Requirements <small style="color:var(--muted);">(optional)</small></label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. disability accommodation, interpreter needed…"></textarea>
              </div>

              <button type="submit" name="submit_booking" class="btn btn-red btn-block" style="padding:12px;">📅 Reserve My Test Slot</button>
            </form>
          </div>
        </div>

        <!-- Sidebar -->
        <div>
          <!-- My bookings -->
          <div class="card mb-3">
            <div class="card-body">
              <div class="section-title" style="margin-bottom:1rem;">My Test Bookings</div>
              <?php if ($mybookings && $mybookings->num_rows > 0):
                while ($b = $mybookings->fetch_assoc()):
                  $sbStyle = $statusStyles[$b['Status']] ?? '';
              ?>
              <div class="tbk-item">
                <div class="tbk-hdr">
                  <div class="tbk-type"><?= htmlspecialchars($testNames[$b['TestType']] ?? $b['TestType']) ?></div>
                  <span class="badge" style="<?= $sbStyle ?>"><?= ucfirst($b['Status']) ?></span>
                </div>
                <div class="tbk-meta">
                  <span>🏛️ <?= htmlspecialchars($dltcNames[$b['DLTCCentre']] ?? $b['DLTCCentre']) ?></span>
                  <span>📅 <?= date('d M Y', strtotime($b['TestDate'])) ?> · 🕐 <?= htmlspecialchars($b['TestTime']) ?></span>
                  <span>🔖 Ref: <strong><?= htmlspecialchars($b['RefNumber']) ?></strong></span>
                </div>
                <?php if (in_array($b['Status'], ['pending','confirmed'])): ?>
                <div style="margin-top:.6rem;">
                  <a href="official_test.php?cancel=<?= $b['BookingID'] ?>"
                     class="btn btn-sm" style="background:#fee2e2;color:var(--red);"
                     onclick="return confirm('Cancel this test booking?')">Cancel Booking</a>
                </div>
                <?php endif; ?>
              </div>
              <?php endwhile; else: ?>
              <p style="color:var(--muted);font-size:13px;">No official test bookings yet.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- What to bring -->
          <div class="tb-checklist">
            <h4>📋 What to Bring on Test Day</h4>
            <ul class="tb-check-list">
              <li>✅ Valid South African ID or Passport</li>
              <li>✅ Learner's Licence card (for driving test)</li>
              <li>✅ Your booking confirmation reference number</li>
              <li>✅ Proof of payment (test fee)</li>
              <li>✅ Eye test certificate (if required)</li>
              <li>✅ Arrive 30 minutes before your slot</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const TEST_INFO = <?= json_encode($testInfo) ?>;
const CENTRE_INFO = {
  pinetown:     {addr:'14 Crompton St, Pinetown, 3610', hours:'Mon–Fri 07:30–15:00', tel:'031 700 1234', note:'Street parking available. Take queue number at the gate.'},
  durban_north: {addr:'18 Garbutt Rd, Durban North, 4051', hours:'Mon–Fri 07:30–15:00', tel:'031 563 5678', note:'Arrive early — parking is limited on busy days.'},
  umlazi:       {addr:'Umlazi Township, Durban, 4066', hours:'Mon–Fri 07:30–14:30', tel:'031 906 2222', note:'Closes slightly earlier than other centres.'},
};

function updateTestInfo() {
  const val = document.getElementById('testType').value;
  const box = document.getElementById('testInfoBox');
  if (val && TEST_INFO[val]) {
    box.textContent = TEST_INFO[val];
    box.style.display = 'block';
  } else {
    box.style.display = 'none';
  }
}

function updateCentreInfo() {
  const val = document.getElementById('dltcCentre').value;
  const box = document.getElementById('centreInfoBox');
  if (val && CENTRE_INFO[val]) {
    const c = CENTRE_INFO[val];
    box.innerHTML = `<div class="centre-info">
      <h4>📍 ${document.getElementById('dltcCentre').options[document.getElementById('dltcCentre').selectedIndex].text.split(' — ')[0]}</h4>
      <div class="centre-row"><span>📍</span><span>${c.addr}</span></div>
      <div class="centre-row"><span>🕐</span><span>${c.hours}</span></div>
      <div class="centre-row"><span>📞</span><span>${c.tel}</span></div>
      <div class="centre-row"><span>💡</span><span>${c.note}</span></div>
    </div>`;
  } else {
    box.innerHTML = '';
  }
  loadSlots();
}

function loadSlots() {
  const date = document.getElementById('testDate').value;
  if (!date) return;
  const wrapper = document.getElementById('slotsWrapper');
  const grid    = document.getElementById('slotsGrid');
  const hidden  = document.getElementById('testTime');

  wrapper.style.display = 'block';
  hidden.value = '';

  // Official DLTC slots: 07:30 to 15:00 in 30-min intervals
  const slots = [];
  for (let h=7; h<=14; h++) {
    slots.push(`${String(h).padStart(2,'0')}:00`);
    slots.push(`${String(h).padStart(2,'0')}:30`);
  }
  slots.push('15:00');

  // Simulate some slots taken (for realism)
  const taken = ['08:00','09:30','11:00','13:30'];
  const dayOfWeek = new Date(date).getDay();
  const isWeekend = dayOfWeek===0 || dayOfWeek===6;

  grid.innerHTML = slots.map(t => {
    const isTaken = taken.includes(t) && !isWeekend;
    return `<button type="button" class="slot-btn ${isTaken?'':'available'}"
      ${isTaken ? 'disabled style="opacity:.4;cursor:not-allowed;"' : ''}
      onclick="selectSlot('${t}',this)">${t}</button>`;
  }).join('');
}

function selectSlot(time, btn) {
  document.querySelectorAll('.slot-btn').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('testTime').value = time;
}

// Validate time slot on submit
document.getElementById('testForm').addEventListener('submit', function(e) {
  const time = document.getElementById('testTime').value;
  if (!time) {
    e.preventDefault();
    alert('Please select a time slot.');
  }
});
</script>
</body>
</html>