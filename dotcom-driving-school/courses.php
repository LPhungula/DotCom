<?php
require_once 'includes/db.php';
require_admin();

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['course_name']??'');
  $desc=trim($_POST['description']??'');
  $price=(float)($_POST['price']??0);
  $dur=trim($_POST['duration']??'');
  if($name){
    $ins=$conn->prepare("INSERT INTO courses (CourseName,Description,Price,Duration) VALUES(?,?,?,?)");
    $ins->bind_param('ssds',$name,$desc,$price,$dur);
    $ins->execute();
    set_flash('success','Course added!');
    header('Location: courses.php');exit;
  }
}
if(isset($_GET['delete'])&&is_numeric($_GET['delete'])){
  $conn->query("DELETE FROM courses WHERE CourseID=".(int)$_GET['delete']);
  set_flash('success','Course deleted.');
  header('Location: courses.php');exit;
}
$courses=$conn->query("SELECT * FROM courses ORDER BY Price");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Courses — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}
    .c-main{margin-left:var(--sidebar-w);padding:2rem;}
    @media(max-width:768px){.c-main{margin-left:0;}}
  </style>
</head>
<body>
<nav class="admin-navbar">
  <div><span class="dot-com">DOT COM</span><span class="school">DRIVING SCHOOL</span></div>
  <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
</nav>
<?php include 'includes/admin_sidebar.php'; ?>
<div class="admin-wrapper">
<div class="c-main">
  <div class="page-header"><h1>Courses</h1><p>Manage all available courses</p></div>
  <?php show_flash(); ?>
  <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;">
    <div class="card">
      <div class="card-body" style="padding:0;">
        <table class="data-table">
          <thead><tr><th>#</th><th>Course Name</th><th>Duration</th><th>Price</th><th>Actions</th></tr></thead>
          <tbody>
            <?php $n=1; while($c=$courses->fetch_assoc()):?>
            <tr>
              <td><?=$n++?></td>
              <td><strong><?=htmlspecialchars($c['CourseName'])?></strong><br><small style="color:var(--muted);"><?=htmlspecialchars(substr($c['Description'],0,60))?>...</small></td>
              <td><?=htmlspecialchars($c['Duration'])?></td>
              <td>R <?=number_format($c['Price'],0)?></td>
              <td><a href="courses.php?delete=<?=$c['CourseID']?>" class="btn btn-sm" style="background:#fee2e2;color:var(--red);" onclick="return confirm('Delete course?')">Delete</a></td>
            </tr>
            <?php endwhile;?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card" style="align-self:start;">
      <div class="card-body">
        <div class="card-title">Add New Course</div>
        <form method="POST">
          <div class="form-group"><label>Course Name</label><input type="text" name="course_name" class="form-control" required placeholder="e.g. Code 8"></div>
          <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Course description..."></textarea></div>
          <div class="form-row">
            <div class="form-group"><label>Price (R)</label><input type="number" name="price" class="form-control" min="0" step="50" placeholder="0"></div>
            <div class="form-group"><label>Duration</label><input type="text" name="duration" class="form-control" placeholder="10 lessons"></div>
          </div>
          <button type="submit" class="btn btn-red btn-block">Add Course</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
</body>
</html>
