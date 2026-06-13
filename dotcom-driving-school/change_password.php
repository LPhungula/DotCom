<?php
require_once 'includes/db.php';
require_student();

$error=''; $success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $old=$_POST['old_password']??'';
  $new=$_POST['new_password']??'';
  $con=$_POST['confirm_password']??'';
  if(!$old||!$new||!$con){$error='All fields are required.';}
  elseif($new!==$con){$error='New passwords do not match.';}
  elseif(strlen($new)<6){$error='Password must be at least 6 characters.';}
  else{
    $stmt=$conn->prepare("SELECT Password FROM users WHERE UserID=? LIMIT 1");
    $stmt->bind_param('i',$_SESSION['user_id']);$stmt->execute();
    $u=$stmt->get_result()->fetch_assoc();
    if(!password_verify($old,$u['Password'])){$error='Current password is incorrect.';}
    else{
      $hash=password_hash($new,PASSWORD_DEFAULT);
      $upd=$conn->prepare("UPDATE users SET Password=? WHERE UserID=?");
      $upd->bind_param('si',$hash,$_SESSION['user_id']);$upd->execute();
      $success='Password changed successfully!';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Change Password — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>body{padding-top:var(--nav-h);}.pw-wrap{max-width:420px;margin:3rem auto;padding:0 1.5rem;}</style>
</head>
<body>
<?php include 'includes/navbar.php';?>
<div class="pw-wrap">
  <div class="card">
    <div class="card-body">
      <div class="card-title">🔒 Change Password</div>
      <p class="card-sub">Update your account password</p>
      <?php if($error):?><div class="alert alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
      <?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>
      <form method="POST">
        <div class="form-group"><label>Current Password</label><input type="password" name="old_password" class="form-control" required></div>
        <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required></div>
        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn btn-red btn-block" style="padding:12px;">Update Password</button>
      </form>
      <div style="text-align:center;margin-top:1rem;"><a href="student_dashboard.php" class="btn btn-white" style="border:1px solid var(--border);">← Back to Dashboard</a></div>
    </div>
  </div>
</div>
</body>
</html>
