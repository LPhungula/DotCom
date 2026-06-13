<?php
require_once 'includes/db.php';
require_admin();

$result = null;
$error  = '';
$success = '';

// Handle form submission (simulated scan via token input or file upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['qr_token'] ?? '');
    $manual_id = (int)($_POST['manual_student_id'] ?? 0);

    if ($manual_id > 0) {
        // Manual mark by student ID
        $stmt = $conn->prepare("SELECT s.*,q.QRToken FROM students s LEFT JOIN qr_codes q ON q.StudentID=s.StudentID WHERE s.StudentID=? LIMIT 1");
        $stmt->bind_param('i',$manual_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
    } elseif ($token) {
        // Look up by token
        $stmt = $conn->prepare("SELECT s.*,q.QRToken FROM students s JOIN qr_codes q ON q.StudentID=s.StudentID WHERE q.QRToken=? LIMIT 1");
        $stmt->bind_param('s',$token);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
    }

    if ($result) {
        // Check for duplicate scan today
        $today = date('Y-m-d');
        $dup = $conn->prepare("SELECT AttendanceID FROM attendance WHERE StudentID=? AND DATE(ScanTime)=? LIMIT 1");
        $dup->bind_param('is',$result['StudentID'],$today);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $error = 'Attendance already recorded for ' . $result['FirstName'] . ' ' . $result['LastName'] . ' today.';
        } else {
            $ins = $conn->prepare("INSERT INTO attendance (StudentID, Status) VALUES (?, 'Present')");
            $ins->bind_param('i',$result['StudentID']);
            $ins->execute();
            $success = 'Attendance recorded for ' . $result['FirstName'] . ' ' . $result['LastName'] . '!';
        }
    } elseif ($token || $manual_id) {
        $error = 'QR Code not recognised. Please try again.';
    }
}

// All students for manual dropdown
$all_students = $conn->query("SELECT StudentID,FirstName,LastName,CourseType FROM students ORDER BY FirstName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Scan Attendance — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}
    .scan-main{margin-left:var(--sidebar-w);padding:2rem;}
    .scan-card{max-width:560px;margin:0 auto;}
    .upload-zone{
      border:2.5px dashed var(--border);border-radius:12px;
      padding:2.5rem;text-align:center;cursor:pointer;
      transition:border-color .2s,background .2s;
      background:var(--offwhite);
      margin-bottom:1.5rem;
    }
    .upload-zone:hover{border-color:var(--red);background:#fff9f9;}
    .upload-zone .uz-icon{font-size:2.5rem;margin-bottom:.8rem;}
    .upload-zone p{font-size:14px;color:var(--muted);margin-bottom:.5rem;}
    .upload-zone small{font-size:12px;color:#aaa;}
    .file-chosen{font-size:13px;color:var(--muted);margin-top:.5rem;}
    .result-box{border-radius:10px;padding:1.2rem 1.5rem;margin-top:1.5rem;}
    .result-box.success{background:#dcfce7;border:1px solid #bbf7d0;}
    .result-box.error{background:#fee2e2;border:1px solid #fecaca;}
    .result-title{font-weight:600;font-size:14.5px;margin-bottom:.3rem;}
    .result-box.success .result-title{color:#166534;}
    .result-box.error .result-title{color:#991b1b;}
    .result-details{font-size:13px;}
    .result-box.success .result-details{color:#166534;}
    .result-box.error .result-details{color:#991b1b;}
    .or-divider{text-align:center;color:var(--muted);font-size:13px;margin:1.5rem 0;position:relative;}
    .or-divider::before,.or-divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:var(--border);}
    .or-divider::before{left:0;}.or-divider::after{right:0;}
    @media(max-width:768px){.scan-main{margin-left:0;}}
  </style>
</head>
<body>
<nav class="admin-navbar">
  <div><span class="dot-com">DOT COM</span><span class="school">DRIVING SCHOOL</span></div>
  <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
</nav>
<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-wrapper">
<div class="scan-main">
  <div class="page-header">
    <h1>Scan Student QR Code</h1>
    <p>Upload a QR image or enter the token manually to record attendance</p>
  </div>

  <div class="scan-card">
    <div class="card">
      <div class="card-body">

        <!-- Method 1: File upload (simulated) -->
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <p style="font-weight:500;font-size:14px;margin-bottom:1rem;">Upload QR Code Image</p>

          <div class="upload-zone" onclick="document.getElementById('qrFile').click()">
            <div class="uz-icon">📷</div>
            <p>Click to upload QR code image</p>
            <small>PNG, JPG, GIF supported</small>
            <div class="file-chosen" id="fileChosen">No file chosen</div>
          </div>
          <input type="file" id="qrFile" name="qr_image" accept="image/*" style="display:none" onchange="handleFile(this)">

          <!-- Hidden token field (would be decoded server-side with a real QR library) -->
          <input type="hidden" name="qr_token" id="qrTokenInput">

          <button type="submit" class="btn btn-red btn-block" style="padding:12px;" id="recordBtn" disabled>
            Record Attendance
          </button>
        </form>

        <div class="or-divider">OR</div>

        <!-- Method 2: Manual select -->
        <form method="POST">
          <div class="form-group">
            <label>Select Student Manually</label>
            <select name="manual_student_id" class="form-control" required>
              <option value="">-- Select a student --</option>
              <?php if ($all_students): while ($s=$all_students->fetch_assoc()): ?>
              <option value="<?= $s['StudentID'] ?>">
                <?= htmlspecialchars($s['FirstName'].' '.$s['LastName'].' ('.$s['CourseType'].')') ?>
              </option>
              <?php endwhile; endif; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-red btn-block" style="padding:12px;">Record Attendance</button>
        </form>

        <!-- Result -->
        <?php if ($success || $result): ?>
        <div class="result-box success">
          <div style="font-size:1.5rem;margin-bottom:.5rem;">✅</div>
          <div class="result-title">QR Code Detected! Attendance Recorded.</div>
          <?php if ($result): ?>
          <div class="result-details">
            Student: <strong><?= htmlspecialchars($result['FirstName'].' '.$result['LastName']) ?></strong><br>
            Student ID: <?= 'STU-'.str_pad($result['StudentID'],3,'0',STR_PAD_LEFT) ?><br>
            Course: <?= htmlspecialchars($result['CourseType']) ?><br>
            Time: <?= date('Y-m-d H:i:s') ?>
          </div>
          <?php endif; ?>
        </div>
        <?php elseif ($error): ?>
        <div class="result-box error">
          <div style="font-size:1.5rem;margin-bottom:.5rem;">❌</div>
          <div class="result-title">Scan Failed</div>
          <div class="result-details"><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <div style="text-align:center;margin-top:1rem;">
      <a href="attendance.php" class="btn btn-navy">View Attendance Records →</a>
    </div>
  </div>
</div>
</div>

<script>
function handleFile(input){
  const fn = input.files[0]?.name ?? 'No file chosen';
  document.getElementById('fileChosen').textContent = fn;
  if(input.files[0]){
    // In production, a real QR decoder (e.g. jsQR library) would decode the image here.
    // For demo: simulate finding a token from filename or use first student token.
    document.getElementById('qrTokenInput').value = 'SIMULATED-SCAN-' + Date.now();
    document.getElementById('recordBtn').disabled = false;
    document.getElementById('recordBtn').textContent = '✅ Record Attendance — ' + fn;
  }
}
</script>
</body>
</html>
