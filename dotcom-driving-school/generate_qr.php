<?php
require_once 'includes/db.php';
require_admin();

$filter_course = trim($_GET['course'] ?? '');
$courses_list = $conn->query("SELECT CourseName FROM courses ORDER BY CourseName");

$where = $filter_course ? "WHERE s.CourseType = '" . $conn->real_escape_string($filter_course) . "'" : '';
$students_q = $conn->query("
    SELECT s.StudentID, s.FirstName, s.LastName, s.CourseType,
           q.QRToken
    FROM students s
    LEFT JOIN qr_codes q ON q.StudentID = s.StudentID
    $where
    ORDER BY s.FirstName
");

$selected_student = null;
if (isset($_GET['student_id'])) {
    $sid = (int)$_GET['student_id'];
    $stmt = $conn->prepare("SELECT s.*,q.QRToken FROM students s LEFT JOIN qr_codes q ON q.StudentID=s.StudentID WHERE s.StudentID=? LIMIT 1");
    $stmt->bind_param('i',$sid);
    $stmt->execute();
    $selected_student = $stmt->get_result()->fetch_assoc();
}

function buildQrSvg(string $token, int $px=140): string {
    $size=20;
    srand(crc32($token));
    $cells='';
    for($r=0;$r<$size;$r++) for($c=0;$c<$size;$c++){
        if(rand(0,1)) $cells.="<rect x='$c' y='$r' width='1' height='1' fill='#0f1923'/>";
    }
    $fp="<rect x='0' y='0' width='6' height='6' fill='none' stroke='#0f1923' stroke-width='.7'/>
         <rect x='1.5' y='1.5' width='3' height='3' fill='#0f1923'/>
         <rect x='13' y='0' width='6' height='6' fill='none' stroke='#0f1923' stroke-width='.7'/>
         <rect x='14.5' y='1.5' width='3' height='3' fill='#0f1923'/>
         <rect x='0' y='13' width='6' height='6' fill='none' stroke='#0f1923' stroke-width='.7'/>
         <rect x='1.5' y='14.5' width='3' height='3' fill='#0f1923'/>";
    return "<svg viewBox='0 0 $size $size' xmlns='http://www.w3.org/2000/svg' style='width:{$px}px;height:{$px}px;display:block;'>
      <rect width='$size' height='$size' fill='#fff'/>{$cells}{$fp}</svg>";
}

$page_title = 'Generate QR Codes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Generate QR Codes — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .qr-filter{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.4rem;margin-bottom:1.5rem;display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;}
    .qr-filter .form-group{margin:0;min-width:200px;}
    .qr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1.2rem;}
    .qr-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.4rem;text-align:center;transition:all .2s;}
    .qr-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px);}
    .qr-name{font-weight:600;font-size:13.5px;margin-top:.8rem;margin-bottom:2px;}
    .qr-course{font-size:11.5px;color:var(--muted);margin-bottom:.9rem;}
    .qr-preview{display:flex;justify-content:center;margin-bottom:.5rem;}
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;align-items:center;justify-content:center;}
    .modal.open{display:flex;}
    .modal-box{background:#fff;border-radius:14px;padding:2.5rem;text-align:center;max-width:360px;width:90%;box-shadow:var(--shadow-lg);position:relative;}
    .modal-box h3{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:.3rem;}
    .modal-box p{font-size:13px;color:var(--muted);margin-bottom:1.5rem;}
    .modal-qr{display:flex;justify-content:center;margin-bottom:1.5rem;}
    .modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);}
  </style>
</head>
<body>
<?php include 'includes/admin_sidebar.php'; ?>
<?php include 'includes/topbar.php'; ?>

<div class="sb-main">
  <form method="GET" class="qr-filter">
    <div class="form-group">
      <label>Select Course</label>
      <select name="course" class="form-control" onchange="this.form.submit()">
        <option value="">All Courses</option>
        <?php if ($courses_list): while ($c=$courses_list->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($c['CourseName']) ?>" <?= $filter_course===$c['CourseName']?'selected':'' ?>>
            <?= htmlspecialchars($c['CourseName']) ?>
          </option>
        <?php endwhile; endif; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Select Student</label>
      <select name="student_id" class="form-control" onchange="this.form.submit()">
        <option value="">All Students</option>
        <?php
        $sd = $conn->query("SELECT StudentID,FirstName,LastName FROM students ORDER BY FirstName");
        if ($sd) while ($s=$sd->fetch_assoc()):?>
          <option value="<?= $s['StudentID'] ?>" <?= (isset($_GET['student_id'])&&$_GET['student_id']==$s['StudentID'])?'selected':'' ?>>
            <?= htmlspecialchars($s['FirstName'].' '.$s['LastName']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
  </form>

  <?php if ($selected_student): ?>
  <div style="max-width:360px;margin:0 auto;text-align:center;">
    <div class="qr-card" style="padding:2rem;">
      <div class="qr-preview"><?= buildQrSvg($selected_student['QRToken']??'fallback', 160) ?></div>
      <div class="qr-name"><?= htmlspecialchars($selected_student['FirstName'].' '.$selected_student['LastName']) ?></div>
      <div class="qr-course"><?= htmlspecialchars($selected_student['CourseType']) ?></div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:1rem;word-break:break-all;"><?= htmlspecialchars($selected_student['QRToken']??'') ?></div>
      <a href="download_qr.php?student_id=<?= $selected_student['StudentID'] ?>" class="btn btn-red btn-block">Download QR Code</a>
    </div>
  </div>
  <?php else: ?>
  <div class="qr-grid">
    <?php if ($students_q && $students_q->num_rows > 0):
      while ($s = $students_q->fetch_assoc()): ?>
    <div class="qr-card">
      <div class="qr-preview"><?= buildQrSvg($s['QRToken']??$s['StudentID'].'fallback') ?></div>
      <div class="qr-name"><?= htmlspecialchars($s['FirstName'].' '.$s['LastName']) ?></div>
      <div class="qr-course"><?= htmlspecialchars($s['CourseType']) ?></div>
      <button class="btn btn-red btn-block btn-sm"
        onclick="openModal('<?= htmlspecialchars(addslashes($s['FirstName'].' '.$s['LastName'])) ?>','<?= $s['StudentID'] ?>','<?= htmlspecialchars(addslashes($s['QRToken']??'')) ?>')">
        View / Download
      </button>
    </div>
    <?php endwhile; else: ?>
      <p style="grid-column:1/-1;text-align:center;color:var(--muted);padding:3rem;">No students found.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="modal" id="qrModal" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <h3 id="modal-name"></h3>
    <p id="modal-id"></p>
    <div class="modal-qr" id="modal-qr"></div>
    <a id="modal-dl" href="#" class="btn btn-red btn-block">Download QR Code</a>
  </div>
</div>

<script>
function buildSvg(token){
  const size=20;let cells='';
  function seededRand(s){let x=Math.sin(s+1)*10000;return x-Math.floor(x);}
  let seed=0;for(let i=0;i<token.length;i++)seed+=token.charCodeAt(i)*i;
  for(let r=0;r<size;r++)for(let c=0;c<size;c++){if(seededRand(seed+r*size+c)>.5)cells+=`<rect x='${c}' y='${r}' width='1' height='1' fill='%230f1923'/>`;}
  const fp=`<rect x='0' y='0' width='6' height='6' fill='none' stroke='%230f1923' stroke-width='.7'/><rect x='1.5' y='1.5' width='3' height='3' fill='%230f1923'/><rect x='13' y='0' width='6' height='6' fill='none' stroke='%230f1923' stroke-width='.7'/><rect x='14.5' y='1.5' width='3' height='3' fill='%230f1923'/><rect x='0' y='13' width='6' height='6' fill='none' stroke='%230f1923' stroke-width='.7'/><rect x='1.5' y='14.5' width='3' height='3' fill='%230f1923'/>`;
  return `<img src="data:image/svg+xml,<svg viewBox='0 0 ${size} ${size}' xmlns='http://www.w3.org/2000/svg'><rect width='${size}' height='${size}' fill='%23fff'/>${cells}${fp}</svg>" style='width:160px;height:160px;'>`;
}
function openModal(name,sid,token){
  document.getElementById('modal-name').textContent=name;
  document.getElementById('modal-id').textContent='Student ID: STU-'+String(sid).padStart(3,'0');
  document.getElementById('modal-qr').innerHTML=buildSvg(token||'fallback'+sid);
  document.getElementById('modal-dl').href='download_qr.php?student_id='+sid;
  document.getElementById('qrModal').classList.add('open');
}
function closeModal(){document.getElementById('qrModal').classList.remove('open');}
</script>
</body>
</html>