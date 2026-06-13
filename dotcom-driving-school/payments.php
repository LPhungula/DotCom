<?php
require_once 'includes/db.php';
require_admin();

$error = ''; $success = '';

// ── Record new payment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $course_id  = (int)($_POST['course_id'] ?? 0);
    $desc       = trim($_POST['description'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);
    $method     = $_POST['method'] ?? 'Cash';
    $reference  = trim($_POST['reference'] ?? '');
    $pay_date   = $_POST['payment_date'] ?? date('Y-m-d');
    $status     = $_POST['status'] ?? 'Pending';

    if (!$student_id || !$desc || $amount <= 0) {
        $error = 'Please fill in student, description and a valid amount.';
    } else {
        $proofFile = null;

        // Handle proof of payment upload
        if (!empty($_FILES['proof_file']['name'])) {
            $file = $_FILES['proof_file'];
            $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Error uploading proof file.';
            } elseif (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, WEBP or PDF files are allowed for proof of payment.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Proof file must be smaller than 5MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'proof_' . $student_id . '_' . time() . '.' . strtolower($ext);
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
            $courseIdParam = $course_id > 0 ? $course_id : null;
            $referenceParam = $reference ?: null;

            $ins = $conn->prepare("INSERT INTO payments (StudentID, CourseID, Description, Amount, Method, Reference, ProofFile, Status, PaymentDate, ConfirmedBy, ConfirmedAt)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $confirmedBy = $status === 'Confirmed' ? $_SESSION['user_id'] : null;
            $confirmedAt = $status === 'Confirmed' ? date('Y-m-d H:i:s') : null;
            $ins->bind_param('iisdsssssss', $student_id, $courseIdParam, $desc, $amount, $method, $referenceParam, $proofFile, $status, $pay_date, $confirmedBy, $confirmedAt);
            $ins->execute();
            set_flash('success', 'Payment recorded successfully!');
            header('Location: payments.php');
            exit;
        }
    }
}

// ── Update payment status (Confirm/Reject) ──
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'confirm') {
        $upd = $conn->prepare("UPDATE payments SET Status='Confirmed', ConfirmedBy=?, ConfirmedAt=NOW() WHERE PaymentID=?");
        $upd->bind_param('ii', $_SESSION['user_id'], $pid);
        $upd->execute();
        set_flash('success', 'Payment confirmed.');
    } elseif ($action === 'reject') {
        $upd = $conn->prepare("UPDATE payments SET Status='Rejected', ConfirmedBy=?, ConfirmedAt=NOW() WHERE PaymentID=?");
        $upd->bind_param('ii', $_SESSION['user_id'], $pid);
        $upd->execute();
        set_flash('success', 'Payment marked as rejected.');
    } elseif ($action === 'delete') {
        // Remove proof file too
        $pf = $conn->query("SELECT ProofFile FROM payments WHERE PaymentID=$pid")->fetch_assoc();
        if ($pf && $pf['ProofFile'] && file_exists(__DIR__ . '/uploads/payment_proofs/' . $pf['ProofFile'])) {
            @unlink(__DIR__ . '/uploads/payment_proofs/' . $pf['ProofFile']);
        }
        $conn->query("DELETE FROM payments WHERE PaymentID=$pid");
        set_flash('success', 'Payment record deleted.');
    }
    header('Location: payments.php');
    exit;
}

// ── Filters ──
$search       = trim($_GET['search'] ?? '');
$date_from    = trim($_GET['date_from'] ?? '');
$date_to      = trim($_GET['date_to'] ?? '');
$method_filter= trim($_GET['method'] ?? '');
$status_filter= trim($_GET['status'] ?? '');

$where = ["1=1"];
if ($search)    $where[] = "(CONCAT(s.FirstName,' ',s.LastName) LIKE '%" . $conn->real_escape_string($search) . "%' OR p.Reference LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($date_from) $where[] = "p.PaymentDate >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to)   $where[] = "p.PaymentDate <= '" . $conn->real_escape_string($date_to) . "'";
if ($method_filter) $where[] = "p.Method = '" . $conn->real_escape_string($method_filter) . "'";
if ($status_filter) $where[] = "p.Status = '" . $conn->real_escape_string($status_filter) . "'";
$where_sql = implode(' AND ', $where);

$payments = $conn->query("
    SELECT p.*, s.FirstName, s.LastName, s.StudentID
    FROM payments p
    JOIN students s ON s.StudentID = p.StudentID
    WHERE $where_sql
    ORDER BY p.PaymentDate DESC, p.PaymentID DESC
");

// ── KPI Summary ──
$month_start = date('Y-m-01');
$month_revenue = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE Status='Confirmed' AND PaymentDate >= '$month_start'")->fetch_assoc()['t'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE Status='Confirmed'")->fetch_assoc()['t'];
$outstanding   = $conn->query("SELECT COALESCE(SUM(Amount),0) t FROM payments WHERE Status='Pending'")->fetch_assoc()['t'];
$transactions  = $conn->query("SELECT COUNT(*) c FROM payments")->fetch_assoc()['c'];

// ── Data for form dropdowns ──
$students_list = $conn->query("SELECT StudentID, FirstName, LastName, CourseType FROM students ORDER BY FirstName");
$courses_list  = $conn->query("SELECT CourseID, CourseName, Price FROM courses ORDER BY CourseName");

$page_title = 'Payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Payments — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}

    .pay-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
    .pay-kpi{border-radius:var(--radius);padding:1.3rem;color:#fff;position:relative;overflow:hidden;}
    .pay-kpi .pl{font-size:11px;font-weight:500;letter-spacing:.4px;opacity:.85;margin-bottom:.6rem;}
    .pay-kpi .pv{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:700;}
    .pay-kpi.month{background:linear-gradient(135deg,#16a34a,#15803d);}
    .pay-kpi.total{background:linear-gradient(135deg,#2563eb,#1d4ed8);}
    .pay-kpi.out{background:linear-gradient(135deg,var(--red),var(--red-dark));}
    .pay-kpi.tx{background:#fff;border:1px solid var(--border);color:var(--text);}
    .pay-kpi.tx .pl{color:var(--muted);opacity:1;}

    .filter-bar{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;}
    .filter-bar .form-control{min-width:130px;}
    .filter-bar input[type=text]{min-width:180px;}

    .pay-status{display:inline-block;padding:3px 11px;border-radius:20px;font-size:11.5px;font-weight:500;}
    .pay-status.Pending{background:#fef9c3;color:#92400e;}
    .pay-status.Confirmed{background:#dcfce7;color:#166534;}
    .pay-status.Rejected{background:#fee2e2;color:#991b1b;}

    .method-chip{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;}

    /* Modal */
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
    .modal.open{display:flex;}
    .modal-box{background:#fff;border-radius:14px;padding:2rem;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);position:relative;}
    .modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);}
    .modal-box h3{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:1.2rem;}

    .proof-preview{border:1px solid var(--border);border-radius:8px;padding:1rem;text-align:center;background:var(--offwhite);margin-top:.5rem;}
    .proof-preview img{max-width:100%;max-height:300px;border-radius:6px;}
    .proof-link{display:inline-flex;align-items:center;gap:6px;color:var(--blue);font-size:13px;text-decoration:underline;}

    @media(max-width:900px){.pay-kpis{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:560px){.pay-kpis{grid-template-columns:1fr;}}
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
  <?php show_flash(); ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="page-header">
    <h1>Payments</h1>
    <p>Record and manage student payments</p>
  </div>


  <!-- Header row -->
  <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:1.2rem;">
    <button class="btn btn-red" onclick="document.getElementById('recordModal').classList.add('open')">+ Record Payment</button>
  </div>

  <!-- KPIs -->
  <div class="pay-kpis">
    <div class="pay-kpi month">
      <div class="pl">MONTH REVENUE</div>
      <div class="pv">R <?= number_format($month_revenue,2) ?></div>
    </div>
    <div class="pay-kpi total">
      <div class="pl">TOTAL REVENUE</div>
      <div class="pv">R <?= number_format($total_revenue,2) ?></div>
    </div>
    <div class="pay-kpi out">
      <div class="pl">OUTSTANDING</div>
      <div class="pv">R <?= number_format($outstanding,2) ?></div>
    </div>
    <div class="pay-kpi tx">
      <div class="pl">TRANSACTIONS</div>
      <div class="pv"><?= $transactions ?></div>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar">
    <input type="text" name="search" class="form-control" placeholder="Search student or reference..." value="<?= htmlspecialchars($search) ?>">
    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" title="From date">
    <span style="color:var(--muted);font-size:13px;">to</span>
    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" title="To date">
    <select name="method" class="form-control">
      <option value="">All Methods</option>
      <?php foreach (['Cash','EFT','Card','Mobile Money'] as $m): ?>
      <option value="<?= $m ?>" <?= $method_filter===$m?'selected':'' ?>><?= $m ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-control">
      <option value="">All Status</option>
      <?php foreach (['Pending','Confirmed','Rejected'] as $s): ?>
      <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-navy">🔍 Filter</button>
    <?php if ($search||$date_from||$date_to||$method_filter||$status_filter): ?>
    <a href="payments.php" class="btn btn-white" style="border:1px solid var(--border);">Reset</a>
    <?php endif; ?>
    <button type="button" onclick="exportCSV()" class="btn btn-green" style="margin-left:auto;">📊 Export</button>
  </form>

  <!-- Records table -->
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="section-title" style="padding:1.2rem 1.4rem 0;">
        <span>💰 Payment Records</span>
      </div>
      <div class="table-wrap">
        <table class="data-table" id="payTable">
          <thead>
            <tr>
              <th>Date</th><th>Student</th><th>Description</th><th>Reference</th>
              <th>Method</th><th>Amount</th><th>Status</th><th>Proof</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($payments && $payments->num_rows > 0):
              while ($p = $payments->fetch_assoc()):
                $methodIcon = ['Cash'=>'💵','EFT'=>'🏦','Card'=>'💳','Mobile Money'=>'📱'][$p['Method']] ?? '💰';
            ?>
            <tr>
              <td><?= date('Y-m-d', strtotime($p['PaymentDate'])) ?></td>
              <td><strong><?= htmlspecialchars($p['FirstName'].' '.$p['LastName']) ?></strong><br><small style="color:var(--muted);">STU-<?= str_pad($p['StudentID'],3,'0',STR_PAD_LEFT) ?></small></td>
              <td><?= htmlspecialchars($p['Description']) ?></td>
              <td><?= $p['Reference'] ? htmlspecialchars($p['Reference']) : '<span style="color:var(--muted);">—</span>' ?></td>
              <td><span class="method-chip"><?= $methodIcon ?> <?= htmlspecialchars($p['Method']) ?></span></td>
              <td><strong>R <?= number_format($p['Amount'],2) ?></strong></td>
              <td><span class="pay-status <?= $p['Status'] ?>"><?= $p['Status'] ?></span></td>
              <td>
                <?php if ($p['ProofFile']): ?>
                  <button class="btn btn-sm btn-navy" onclick="viewProof('<?= htmlspecialchars($p['ProofFile']) ?>','<?= htmlspecialchars($p['FirstName'].' '.$p['LastName']) ?>')">View</button>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:12px;">None</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;">
                <?php if ($p['Status']==='Pending'): ?>
                  <a href="payments.php?action=confirm&id=<?= $p['PaymentID'] ?>" class="btn btn-sm btn-green" title="Confirm">✓</a>
                  <a href="payments.php?action=reject&id=<?= $p['PaymentID'] ?>" class="btn btn-sm" style="background:#fee2e2;color:var(--red);" title="Reject">✕</a>
                <?php endif; ?>
                <a href="payments.php?action=delete&id=<?= $p['PaymentID'] ?>" class="btn btn-sm" style="background:#f1f5f9;color:var(--muted);" onclick="return confirm('Delete this payment record?')" title="Delete">🗑</a>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--muted);">No payment records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div><!-- /admin-main -->
</div><!-- /admin-wrapper -->

<!-- ═══ RECORD PAYMENT MODAL ═══ -->
<div class="modal" id="recordModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('recordModal').classList.remove('open')">✕</button>
    <h3>Record Payment</h3>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label>Student *</label>
        <select name="student_id" id="studentSelect" class="form-control" required onchange="autofillCourse()">
          <option value="">-- Select student --</option>
          <?php if ($students_list): while ($s = $students_list->fetch_assoc()): ?>
          <option value="<?= $s['StudentID'] ?>" data-course="<?= htmlspecialchars($s['CourseType']) ?>">
            <?= htmlspecialchars($s['FirstName'].' '.$s['LastName']) ?> — <?= htmlspecialchars($s['CourseType']) ?>
          </option>
          <?php endwhile; endif; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Course / Package (auto-fills amount)</label>
        <select name="course_id" id="courseSelect" class="form-control" onchange="autofillAmount()">
          <option value="">-- No specific course --</option>
          <?php
          $courses_arr = [];
          if ($courses_list) while ($c = $courses_list->fetch_assoc()) $courses_arr[] = $c;
          foreach ($courses_arr as $c): ?>
          <option value="<?= $c['CourseID'] ?>" data-price="<?= $c['Price'] ?>" data-name="<?= htmlspecialchars($c['CourseName']) ?>">
            <?= htmlspecialchars($c['CourseName']) ?> — R <?= number_format($c['Price'],2) ?>
          </option>
          <?php endforeach; ?>
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
        <label>Status *</label>
        <select name="status" class="form-control" required>
          <option value="Pending">Pending — awaiting confirmation</option>
          <option value="Confirmed">Confirmed — payment verified</option>
        </select>
      </div>

      <div class="form-group">
        <label>Proof of Payment (optional)</label>
        <input type="file" name="proof_file" class="form-control" accept="image/*,.pdf">
        <small style="color:var(--muted);font-size:11.5px;">JPG, PNG, WEBP or PDF — max 5MB</small>
      </div>

      <button type="submit" name="record_payment" class="btn btn-red btn-block" style="padding:12px;margin-top:.5rem;">Save Payment</button>
    </form>
  </div>
</div>

<!-- ═══ VIEW PROOF MODAL ═══ -->
<div class="modal" id="proofModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:480px;text-align:center;">
    <button class="modal-close" onclick="document.getElementById('proofModal').classList.remove('open')">✕</button>
    <h3 id="proofTitle">Proof of Payment</h3>
    <div id="proofContent"></div>
  </div>
</div>

<script>
function autofillCourse(){
  const sel = document.getElementById('studentSelect');
  const opt = sel.options[sel.selectedIndex];
  const courseName = opt.getAttribute('data-course');
  if (!courseName) return;

  // Try to match course dropdown to student's enrolled course
  const courseSel = document.getElementById('courseSelect');
  for (let i=0;i<courseSel.options.length;i++){
    if (courseSel.options[i].getAttribute('data-name') === courseName){
      courseSel.selectedIndex = i;
      autofillAmount();
      break;
    }
  }
}

function autofillAmount(){
  const sel = document.getElementById('courseSelect');
  const opt = sel.options[sel.selectedIndex];
  const price = opt.getAttribute('data-price');
  const name  = opt.getAttribute('data-name');
  if (price){
    document.getElementById('amountInput').value = parseFloat(price).toFixed(2);
  }
  if (name && !document.getElementById('descInput').value){
    document.getElementById('descInput').value = name;
  }
}

function viewProof(filename, studentName){
  document.getElementById('proofTitle').textContent = 'Proof of Payment — ' + studentName;
  const ext = filename.split('.').pop().toLowerCase();
  const url = 'uploads/payment_proofs/' + filename;
  let html = '';
  if (ext === 'pdf'){
    html = `<div class="proof-preview"><p style="margin-bottom:1rem;">📄 PDF document</p><a href="${url}" target="_blank" class="proof-link">Open PDF in new tab →</a></div>`;
  } else {
    html = `<div class="proof-preview"><img src="${url}" alt="Proof of payment"></div><div style="margin-top:.8rem;"><a href="${url}" target="_blank" class="proof-link">Open full size →</a></div>`;
  }
  document.getElementById('proofContent').innerHTML = html;
  document.getElementById('proofModal').classList.add('open');
}

function exportCSV(){
  const rows=[['Date','Student','Description','Reference','Method','Amount','Status']];
  document.querySelectorAll('#payTable tbody tr').forEach(tr=>{
    const cells=[...tr.querySelectorAll('td')];
    if(cells.length<8) return;
    rows.push([
      cells[0].innerText.trim(),
      cells[1].innerText.trim().replace('\n',' '),
      cells[2].innerText.trim(),
      cells[3].innerText.trim(),
      cells[4].innerText.trim(),
      cells[5].innerText.trim(),
      cells[6].innerText.trim()
    ]);
  });
  const csv=rows.map(r=>r.map(v=>'"'+v.replace(/"/g,'""')+'"').join(',')).join('\n');
  const a=document.createElement('a');
  a.href='data:text/csv;charset=utf-8,'+encodeURIComponent(csv);
  a.download='payments_export.csv';
  a.click();
}

<?php if ($error): ?>
document.getElementById('recordModal').classList.add('open');
<?php endif; ?>
</script>
</body>
</html>