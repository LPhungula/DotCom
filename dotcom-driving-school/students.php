<?php
require_once 'includes/db.php';
require_admin();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->query("DELETE FROM students WHERE StudentID=$did");
    set_flash('success','Student deleted successfully.');
    header('Location: students.php'); exit;
}

$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE CONCAT(s.FirstName,' ',s.LastName) LIKE '%" . $conn->real_escape_string($search) . "%' OR s.Email LIKE '%" . $conn->real_escape_string($search) . "%'" : '';

$students = $conn->query("
    SELECT s.*, u.Username,
      (SELECT COUNT(*) FROM attendance WHERE StudentID=s.StudentID) AS total_att,
      (SELECT COUNT(*) FROM attendance WHERE StudentID=s.StudentID AND Status='Present') AS present_att
    FROM students s JOIN users u ON u.UserID=s.UserID
    $where
    ORDER BY s.EnrolledAt DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Students — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}
    .stu-main{margin-left:var(--sidebar-w);padding:2rem;}
    .search-bar{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;gap:1rem;align-items:center;}
    .att-pct{display:inline-block;min-width:48px;background:var(--offwhite);border-radius:4px;padding:2px 7px;font-size:12px;font-weight:500;color:var(--text);}
    @media(max-width:768px){.stu-main{margin-left:0;}}
  </style>
</head>
<body>
<nav class="admin-navbar">
  <div><span class="dot-com">DOT COM</span><span class="school">DRIVING SCHOOL</span></div>
  <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
</nav>
<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-wrapper">
<div class="stu-main">
  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div><h1>Students</h1><p>All registered students in the system</p></div>
    <a href="register.php" class="btn btn-red">+ Add Student</a>
  </div>

  <?php show_flash(); ?>

  <form method="GET" class="search-bar">
    <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" style="max-width:360px;">
    <button type="submit" class="btn btn-navy">Search</button>
    <?php if ($search): ?><a href="students.php" class="btn btn-white" style="border:1px solid var(--border);">Clear</a><?php endif; ?>
  </form>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Course</th><th>Attendance</th><th>Enrolled</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($students && $students->num_rows > 0):
              $n=1; while ($s=$students->fetch_assoc()):
              $pct = $s['total_att'] > 0 ? round($s['present_att']/$s['total_att']*100) : 0;
            ?>
            <tr>
              <td><?= $n++ ?></td>
              <td><strong><?= htmlspecialchars($s['FirstName'].' '.$s['LastName']) ?></strong><br><small style="color:var(--muted);">@<?= htmlspecialchars($s['Username']) ?></small></td>
              <td><?= htmlspecialchars($s['Email']) ?></td>
              <td><?= htmlspecialchars($s['Phone']) ?></td>
              <td><?= htmlspecialchars($s['CourseType']) ?></td>
              <td><span class="att-pct"><?= $pct ?>%</span></td>
              <td><?= date('d M Y', strtotime($s['EnrolledAt'])) ?></td>
              <td>
                <a href="generate_qr.php?student_id=<?= $s['StudentID'] ?>" class="btn btn-sm btn-navy">QR</a>
                <a href="students.php?delete=<?= $s['StudentID'] ?>" class="btn btn-sm" style="background:#fee2e2;color:var(--red);" onclick="return confirm('Delete this student?')">Del</a>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted);">No students found.</td></tr>
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
