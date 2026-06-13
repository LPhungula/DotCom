<?php
require_once 'includes/db.php';
require_student();

$stmt = $conn->prepare("SELECT * FROM students WHERE UserID=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) { set_flash('error','Student record not found.'); header('Location: logout.php'); exit; }

$sid = $student['StudentID'];
$error = ''; $success = '';

// ── Submit new payment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $course_id  = (int)($_POST['course_id'] ?? 0);
    $desc       = trim($_POST['description'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);
    $method     = $_POST['method'] ?? 'Cash';
    $reference  = trim($_POST['reference'] ?? '');
    $pay_date   = $_POST['payment_date'] ?? date('Y-m-d');

    if (!$desc || $amount <= 0) {
        $error = 'Please fill in description and a valid amount.';
    } else {
        $proofFile = null;

        if (!empty($_FILES['proof_file']['name'])) {
            $file = $_FILES['proof_file'];
            $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Error uploading proof file.';
            } elseif (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, WEBP or PDF files are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Proof file must be smaller than 5MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'proof_' . $sid . '_' . time() . '.' . strtolower($ext);
                $destDir = __DIR__ . '/uploads/payment_proofs/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
                    $proofFile = $newName;
                } else {
                    $error = 'Failed to save proof file.';
                }
            }
        }

        if (!$error) {
            $courseIdParam  = $course_id > 0 ? $course_id : null;
            $referenceParam = $reference ?: null;

            $ins = $conn->prepare("INSERT INTO payments (StudentID, CourseID, Description, Amount, Method, Reference, ProofFile, Status, PaymentDate)
                VALUES (?,?,?,?,?,?,?,'Pending',?)");
            $ins->bind_param('iisdssss', $sid, $courseIdParam, $desc, $amount, $method, $referenceParam, $proofFile, $pay_date);
            $ins->execute();
            set_flash('success', 'Payment submitted! It will be reviewed by an admin shortly.');
            header('Location: student_payments.php');
            exit;
        }
    }
}

// ── Summary ──
$total_paid = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE StudentID=$sid AND Status='Confirmed'")->fetch_assoc()['t'];
$balance_due = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE StudentID=$sid AND Status='Pending'")->fetch_assoc()['t'];

// ── History ──
$history = $conn->query("
    SELECT p.*, c.CourseName
    FROM payments p
    LEFT JOIN courses c ON c.CourseID = p.CourseID
    WHERE p.StudentID = $sid
    ORDER BY p.PaymentDate DESC, p.PaymentID DESC
");

// Courses for form
$courses_list = $conn->query("SELECT CourseID, CourseName, Price FROM courses ORDER BY CourseName");

$page_title = 'Payment History';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Payment History — Dot Com Driving School</title>
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

    .pay-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;}
    .pay-card{border-radius:var(--radius);padding:1.4rem;color:#fff;position:relative;overflow:hidden;}
    .pay-card .pl{font-size:11px;font-weight:500;letter-spacing:.4px;opacity:.85;margin-bottom:.6rem;}
    .pay-card .pv{font-family:'Syne',sans-serif;font-size:2rem;font-weight:700;}
    .pay-card .ps{font-size:11.5px;opacity:.75;margin-top:.3rem;}
    .pay-card.paid{background:linear-gradient(135deg,#16a34a,#15803d);}
    .pay-card.due{background:linear-gradient(135deg,var(--red),var(--red-dark));}
    .pay-card.pkg{background:#fff;border:1px solid var(--border);color:var(--text);}
    .pay-card.pkg .pl{color:var(--muted);opacity:1;}
    .pay-card.pkg .pv{font-size:1.3rem;}

    .howto-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:1rem;}
    .howto-card{background:var(--offwhite);border:1px solid var(--border);border-radius:10px;padding:1.2rem;}
    .howto-card h4{font-size:13.5px;font-weight:600;margin-bottom:.6rem;display:flex;align-items:center;gap:7px;}
    .howto-card p{font-size:12.5px;color:var(--muted);line-height:1.7;margin:0;}
    .howto-card strong{color:var(--text);}

    .query-box{
      background:#fff7ed;border:1px solid #fed7aa;border-left:4px solid var(--amber);
      border-radius:8px;padding:1rem 1.3rem;margin-top:1.2rem;
      font-size:13px;color:#92400e;display:flex;align-items:flex-start;gap:10px;
    }

    .pay-status{display:inline-block;padding:3px 11px;border-radius:20px;font-size:11.5px;font-weight:500;}
    .pay-status.Pending{background:#fef9c3;color:#92400e;}
    .pay-status.Confirmed{background:#dcfce7;color:#166534;}
    .pay-status.Rejected{background:#fee2e2;color:#991b1b;}

    .method-chip{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;}

    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
    .modal.open{display:flex;}
    .modal-box{background:#fff;border-radius:14px;padding:2rem;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);position:relative;}
    .modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);}
    .modal-box h3{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:1.2rem;}

    @media(max-width:800px){.pay-summary{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="dash-header">
  <h1>Payment History</h1>
  <p>View all your payment transactions</p>
</div>

<div class="dash-body">
  <?php show_flash(); ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:180px 1fr;gap:1.5rem;align-items:start;">
    <div class="sidebar-menu card" style="padding:1rem;">
      <a href="student_dashboard.php" class="menu-item">🏠 Dashboard</a>
      <a href="student_profile.php"  class="menu-item">👤 My Profile</a>
      <a href="student_dashboard.php#qr" class="menu-item">📷 My QR Code</a>
      <a href="student_dashboard.php#att" class="menu-item">📋 My Attendance</a>
      <a href="study_materials.php"  class="menu-item">📚 Study Materials</a>
      <a href="student_payments.php" class="menu-item active">💳 Payments</a>
      <a href="my_readiness.php"     class="menu-item">🤖 My Readiness</a>
      <a href="change_password.php"  class="menu-item">🔒 Change Password</a>
      <a href="logout.php"           class="menu-item">🚪 Logout</a>
    </div>

  <div>
  <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:1.2rem;">
    <button class="btn btn-red" onclick="document.getElementById('payModal').classList.add('open')">+ Submit Payment</button>
  </div>

  <!-- Summary -->
  <div class="pay-summary">
    <div class="pay-card paid">
      <div class="pl">TOTAL PAID</div>
      <div class="pv">R<?= number_format($total_paid,2) ?></div>
      <div class="ps">All time</div>
    </div>
    <div class="pay-card due">
      <div class="pl">BALANCE DUE</div>
      <div class="pv">R<?= number_format($balance_due,2) ?></div>
      <div class="ps">Outstanding / Pending review</div>
    </div>
    <div class="pay-card pkg">
      <div class="pl">PACKAGE</div>
      <div class="pv"><?= htmlspecialchars($student['CourseType']) ?></div>
      <div class="ps">&nbsp;</div>
    </div>
  </div>

  <!-- How to make payments -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="section-title">💳 How to Make Payments</div>
      <div class="howto-grid">
        <div class="howto-card">
          <h4>🏦 EFT / Bank Transfer</h4>
          <p>
            <strong>First National Bank</strong><br>
            Account: <strong>62012345678</strong><br>
            Branch: <strong>255655</strong><br>
            Ref: Your ID number
          </p>
        </div>
        <div class="howto-card">
          <h4>💵 Cash</h4>
          <p>Pay in person at either branch. Cash payments are recorded immediately by admin staff.</p>
        </div>
        <div class="howto-card">
          <h4>💳 Card</h4>
          <p>Card machine available at both branches. Visa and Mastercard accepted.</p>
        </div>
      </div>
      <div class="query-box">
        <span>📞</span>
        <span><strong>Payment queries:</strong> Call +27 31 123 4567 (Durban North) or +27 31 987 6543 (Pinetown). Always quote your ID number as reference.</span>
      </div>
    </div>
  </div>

  <!-- History -->
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="section-title" style="padding:1.2rem 1.4rem 0;">
        <span>📜 Payment History</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Description</th><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th><th>Proof</th></tr>
          </thead>
          <tbody>
            <?php if ($history && $history->num_rows > 0):
              while ($p = $history->fetch_assoc()):
                $methodIcon = ['Cash'=>'💵','EFT'=>'🏦','Card'=>'💳','Mobile Money'=>'📱'][$p['Method']] ?? '💰';
            ?>
            <tr>
              <td><?= date('Y-m-d', strtotime($p['PaymentDate'])) ?></td>
              <td><?= htmlspecialchars($p['Description']) ?></td>
              <td><?= $p['Reference'] ? htmlspecialchars($p['Reference']) : '<span style="color:var(--muted);">—</span>' ?></td>
              <td><span class="method-chip"><?= $methodIcon ?> <?= htmlspecialchars($p['Method']) ?></span></td>
              <td><strong>R <?= number_format($p['Amount'],2) ?></strong></td>
              <td><span class="pay-status <?= $p['Status'] ?>"><?= $p['Status'] ?></span></td>
              <td>
                <?php if ($p['ProofFile']): ?>
                  <button class="btn btn-sm btn-navy" onclick="viewProof('<?= htmlspecialchars($p['ProofFile']) ?>')">View</button>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:12px;">None</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--muted);">No payment records yet. Submit your first payment using the button above.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  </div><!-- /content column -->
  </div><!-- /sidebar+content grid -->
</div><!-- /dash-body -->

<!-- Submit Payment Modal -->
<div class="modal" id="payModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('payModal').classList.remove('open')">✕</button>
    <h3>Submit Payment</h3>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label>Course / Package (optional — auto-fills amount)</label>
        <select name="course_id" id="courseSelect" class="form-control" onchange="autofillAmount()">
          <option value="">-- No specific course --</option>
          <?php if ($courses_list): while ($c = $courses_list->fetch_assoc()): ?>
          <option value="<?= $c['CourseID'] ?>" data-price="<?= $c['Price'] ?>" data-name="<?= htmlspecialchars($c['CourseName']) ?>" <?= $c['CourseName']===$student['CourseType']?'selected':'' ?>>
            <?= htmlspecialchars($c['CourseName']) ?> — R <?= number_format($c['Price'],2) ?>
          </option>
          <?php endwhile; endif; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Description *</label>
        <input type="text" name="description" id="descInput" class="form-control" placeholder="e.g. Manual Driving Course - Deposit" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Amount (R) *</label>
          <input type="number" name="amount" id="amountInput" class="form-control" step="0.01" min="0" placeholder="0.00" required>
        </div>
        <div class="form-group">
          <label>Payment Date *</label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Method *</label>
          <select name="method" class="form-control" required>
            <option value="Cash">💵 Cash</option>
            <option value="EFT">🏦 EFT</option>
            <option value="Card">💳 Card</option>
            <option value="Mobile Money">📱 Mobile Money</option>
          </select>
        </div>
        <div class="form-group">
          <label>Reference / Transaction ID</label>
          <input type="text" name="reference" class="form-control" placeholder="e.g. EFT-REF-12345">
        </div>
      </div>

      <div class="form-group">
        <label>Proof of Payment</label>
        <input type="file" name="proof_file" class="form-control" accept="image/*,.pdf">
        <small style="color:var(--muted);font-size:11.5px;">JPG, PNG, WEBP or PDF — max 5MB</small>
      </div>

      <div class="alert alert-info" style="margin-top:.5rem;">
        Your payment will be marked as <strong>Pending</strong> until an admin reviews and confirms it.
      </div>

      <button type="submit" name="submit_payment" class="btn btn-red btn-block" style="padding:12px;">Submit Payment</button>
    </form>
  </div>
</div>

<!-- View Proof Modal -->
<div class="modal" id="proofModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:480px;text-align:center;">
    <button class="modal-close" onclick="document.getElementById('proofModal').classList.remove('open')">✕</button>
    <h3>Proof of Payment</h3>
    <div id="proofContent"></div>
  </div>
</div>

<script>
function autofillAmount(){
  const sel = document.getElementById('courseSelect');
  const opt = sel.options[sel.selectedIndex];
  const price = opt.getAttribute('data-price');
  const name  = opt.getAttribute('data-name');
  if (price) document.getElementById('amountInput').value = parseFloat(price).toFixed(2);
  if (name && !document.getElementById('descInput').value) document.getElementById('descInput').value = name;
}

function viewProof(filename){
  const ext = filename.split('.').pop().toLowerCase();
  const url = 'uploads/payment_proofs/' + filename;
  let html = '';
  if (ext === 'pdf'){
    html = `<div style="border:1px solid var(--border);border-radius:8px;padding:1.5rem;background:var(--offwhite);"><p style="margin-bottom:1rem;">📄 PDF document</p><a href="${url}" target="_blank" style="color:var(--blue);text-decoration:underline;">Open PDF in new tab →</a></div>`;
  } else {
    html = `<div style="border:1px solid var(--border);border-radius:8px;padding:1rem;background:var(--offwhite);"><img src="${url}" style="max-width:100%;max-height:300px;border-radius:6px;"></div><div style="margin-top:.8rem;"><a href="${url}" target="_blank" style="color:var(--blue);text-decoration:underline;font-size:13px;">Open full size →</a></div>`;
  }
  document.getElementById('proofContent').innerHTML = html;
  document.getElementById('proofModal').classList.add('open');
}

<?php if ($error): ?>
document.getElementById('payModal').classList.add('open');
<?php endif; ?>
</script>
</body>
</html>