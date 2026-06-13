<?php
require_once 'includes/db.php';
require_admin();

// Filters
$filter_date   = trim($_GET['date']   ?? date('Y-m-d'));
$filter_course = trim($_GET['course'] ?? '');

// Build WHERE
$where = ["1=1"];
if ($filter_date)   $where[] = "DATE(a.ScanTime) = '" . $conn->real_escape_string($filter_date) . "'";
if ($filter_course) $where[] = "s.CourseType = '" . $conn->real_escape_string($filter_course) . "'";
$where_sql = implode(' AND ', $where);

$records = $conn->query("
    SELECT a.AttendanceID, a.ScanTime, a.Status,
           s.FirstName, s.LastName, s.CourseType, s.StudentID
    FROM attendance a
    JOIN students s ON s.StudentID=a.StudentID
    WHERE $where_sql
    ORDER BY a.ScanTime DESC
");

$courses_q = $conn->query("SELECT CourseName FROM courses ORDER BY CourseName");

// Summary counts for today
$total_q   = $conn->query("SELECT COUNT(*) c FROM attendance a JOIN students s ON s.StudentID=a.StudentID WHERE $where_sql")->fetch_assoc()['c'];
$present_q = $conn->query("SELECT COUNT(*) c FROM attendance a JOIN students s ON s.StudentID=a.StudentID WHERE $where_sql AND a.Status='Present'")->fetch_assoc()['c'];
$absent_q  = $conn->query("SELECT COUNT(*) c FROM attendance a JOIN students s ON s.StudentID=a.StudentID WHERE $where_sql AND a.Status='Absent'")->fetch_assoc()['c'];
$late_q    = $conn->query("SELECT COUNT(*) c FROM attendance a JOIN students s ON s.StudentID=a.StudentID WHERE $where_sql AND a.Status='Late'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Attendance Records — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}
    .att-main{margin-left:var(--sidebar-w);padding:2rem;}
    .filter-bar{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;}
    .filter-bar .form-group{margin:0;min-width:160px;}
    .export-btn{margin-left:auto;}
    @media(max-width:768px){.att-main{margin-left:0;}.export-btn{margin-left:0;}}
    @media print{.sidebar,.admin-navbar,.filter-bar,.no-print{display:none!important;}.att-main{margin-left:0;}}
  </style>
</head>
<body>
<nav class="admin-navbar">
  <div><span class="dot-com">DOT COM</span><span class="school">DRIVING SCHOOL</span></div>
  <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
</nav>
<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-wrapper">
<div class="att-main">
  <div class="page-header">
    <h1>Attendance Records</h1>
    <p>View and export daily attendance for all students</p>
  </div>

  <!-- KPI row -->
  <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card" style="--kpi-color:var(--blue);"><div class="kpi-label">TOTAL RECORDS</div><div class="kpi-value"><?= $total_q ?></div></div>
    <div class="kpi-card" style="--kpi-color:var(--green);"><div class="kpi-label">PRESENT</div><div class="kpi-value"><?= $present_q ?></div></div>
    <div class="kpi-card" style="--kpi-color:var(--red);"><div class="kpi-label">ABSENT</div><div class="kpi-value"><?= $absent_q ?></div></div>
    <div class="kpi-card" style="--kpi-color:var(--amber);"><div class="kpi-label">LATE</div><div class="kpi-value"><?= $late_q ?></div></div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar no-print">
    <div class="form-group">
      <label>Select Date</label>
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
    </div>
    <div class="form-group">
      <label>Select Course</label>
      <select name="course" class="form-control">
        <option value="">All Courses</option>
        <?php if ($courses_q): while ($c=$courses_q->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($c['CourseName']) ?>" <?= $filter_course===$c['CourseName']?'selected':'' ?>>
          <?= htmlspecialchars($c['CourseName']) ?>
        </option>
        <?php endwhile; endif; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-navy">Filter</button>
    <a href="attendance.php" class="btn btn-white" style="border:1px solid var(--border);">Reset</a>
    <div class="export-btn">
      <button type="button" onclick="exportCSV()" class="btn btn-green">📊 Export to Excel/CSV</button>
      <button type="button" onclick="window.print()" class="btn btn-navy" style="margin-left:.5rem;">🖨️ Print</button>
    </div>
  </form>

  <!-- Table -->
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table class="data-table" id="attTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Course</th>
              <th>Date &amp; Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($records && $records->num_rows > 0):
              $n = 1;
              while ($r = $records->fetch_assoc()): ?>
            <tr>
              <td><?= $n++ ?></td>
              <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
              <td><?= htmlspecialchars($r['CourseType']) ?></td>
              <td><?= date('Y-m-d H:i:s', strtotime($r['ScanTime'])) ?></td>
              <td><span class="badge badge-<?= strtolower($r['Status']) ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--muted);">No attendance records found for the selected filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
</div>

<script>
function exportCSV(){
  const rows=[['#','Student','Course','Date & Time','Status']];
  document.querySelectorAll('#attTable tbody tr').forEach((tr,i)=>{
    const cells=[...tr.querySelectorAll('td')].map(td=>td.innerText.trim());
    if(cells.length>1) rows.push(cells);
  });
  const csv=rows.map(r=>r.map(v=>'"'+v.replace(/"/g,'""')+'"').join(',')).join('\n');
  const a=document.createElement('a');
  a.href='data:text/csv;charset=utf-8,'+encodeURIComponent(csv);
  a.download='attendance_<?= $filter_date ?>.csv';
  a.click();
}
</script>
</body>
</html>
