<?php
require_once 'includes/db.php';
require_admin();

$error = ''; $success = '';

$all_licence_types = [
    'Learners Licence Prep','Code 8 - Light Motor',
    'Code 10 - Heavy Motor','Manual Driving Course','Refresher Course',
];

// ── DELETE ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = $conn->query("SELECT Photo,Status FROM vehicles WHERE VehicleID=$did")->fetch_assoc();
    if ($row['Status']==='Out on Road') {
        set_flash('error','Cannot delete a vehicle that is currently out on the road.');
    } else {
        if ($row['Photo'] && file_exists(__DIR__.'/uploads/vehicle_photos/'.$row['Photo']))
            @unlink(__DIR__.'/uploads/vehicle_photos/'.$row['Photo']);
        $conn->query("DELETE FROM vehicles WHERE VehicleID=$did");
        set_flash('success','Vehicle deleted.');
    }
    header('Location: vehicles.php'); exit;
}

// ── RETURN TRIP ──
if (isset($_GET['return_trip']) && is_numeric($_GET['return_trip'])) {
    $tid = (int)$_GET['return_trip'];
    $endMileage = (int)($_GET['end_mileage'] ?? 0);
    $tripRow = $conn->query("SELECT * FROM vehicle_trips WHERE TripID=$tid")->fetch_assoc();
    if ($tripRow) {
        $conn->query("UPDATE vehicle_trips SET Status='Returned', ReturnTime=NOW(), EndMileage=".($endMileage>0?$endMileage:'NULL')." WHERE TripID=$tid");
        $mileageSql = $endMileage > 0 ? ", Mileage=$endMileage" : '';
        $conn->query("UPDATE vehicles SET Status='Available'$mileageSql WHERE VehicleID={$tripRow['VehicleID']}");
        set_flash('success','Vehicle marked as returned.');
    }
    header('Location: vehicles.php?tab=live'); exit;
}

// ── ADD VEHICLE ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_vehicle'])) {
    $make=$_POST['make']??''; $model=$_POST['model']??''; $year=(int)($_POST['year']??0);
    $reg=$_POST['reg_number']??''; $colour=$_POST['colour']??'';
    $ltype=$_POST['licence_type']??''; $trans=$_POST['transmission']??'Manual';
    $status=$_POST['status']??'Available'; $miles=(int)($_POST['mileage']??0);
    $lsvc=$_POST['last_service']??null; $nsvc=$_POST['next_service']??null;
    $notes=$_POST['notes']??'';

    if (!$make||!$model||!$year||!$reg||!$ltype) {
        $error='Please fill in all required fields.';
    } else {
        $photo=null;
        if (!empty($_FILES['photo']['name'])) {
            $file=$_FILES['photo']; $allowed=['image/jpeg','image/png','image/webp'];
            if ($file['error']===UPLOAD_ERR_OK && in_array($file['type'],$allowed) && $file['size']<=3*1024*1024) {
                $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
                $photo='vehicle_'.time().'_'.rand(100,999).'.'.strtolower($ext);
                if (!move_uploaded_file($file['tmp_name'],__DIR__.'/uploads/vehicle_photos/'.$photo)) $photo=null;
            }
        }
        $m=$conn->real_escape_string($make); $mo=$conn->real_escape_string($model);
        $r=$conn->real_escape_string($reg); $c=$conn->real_escape_string($colour);
        $lt=$conn->real_escape_string($ltype); $tr=$conn->real_escape_string($trans);
        $st=$conn->real_escape_string($status); $no=$conn->real_escape_string($notes);
        $ph=$photo?("'".$conn->real_escape_string($photo)."'"):'NULL';
        $ls=$lsvc?"'".$conn->real_escape_string($lsvc)."'":'NULL';
        $ns=$nsvc?"'".$conn->real_escape_string($nsvc)."'":'NULL';
        $conn->query("INSERT INTO vehicles (Make,Model,Year,RegNumber,Colour,LicenceType,Transmission,Status,Mileage,LastService,NextService,Notes,Photo) VALUES ('$m','$mo',$year,'$r','$c','$lt','$tr','$st',$miles,$ls,$ns,'$no',$ph)");
        set_flash('success','Vehicle added successfully!');
        header('Location: vehicles.php'); exit;
    }
}

// ── LOG TRIP ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['log_trip'])) {
    $vid=(int)($_POST['vehicle_id']??0); $sid=(int)($_POST['student_id']??0);
    $instName=trim($_POST['instructor_name']??'');
    $depart=$_POST['depart_time']??date('Y-m-d H:i:s');
    $startMil=(int)($_POST['start_mileage']??0);
    $route=trim($_POST['route']??''); $tnotes=trim($_POST['trip_notes']??'');
    if (!$vid||!$sid||!$instName) { set_flash('error','Vehicle, student and instructor are required.'); }
    else {
        $vcheck=$conn->query("SELECT Status FROM vehicles WHERE VehicleID=$vid")->fetch_assoc();
        if ($vcheck['Status']==='Out on Road') { set_flash('error','That vehicle is already out on the road.'); }
        elseif ($vcheck['Status']==='Under Maintenance') { set_flash('error','That vehicle is under maintenance.'); }
        else {
            $in=$conn->real_escape_string($instName); $ro=$conn->real_escape_string($route);
            $tn=$conn->real_escape_string($tnotes); $dp=$conn->real_escape_string($depart);
            $conn->query("INSERT INTO vehicle_trips (VehicleID,StudentID,InstructorName,DepartTime,StartMileage,Route,Status,Notes) VALUES ($vid,$sid,'$in','$dp',$startMil,'$ro','Active','$tn')");
            $conn->query("UPDATE vehicles SET Status='Out on Road' WHERE VehicleID=$vid");
            set_flash('success','Trip logged — vehicle dispatched.');
        }
    }
    header('Location: vehicles.php?tab=live'); exit;
}

// ── KPIs ──
$total_v   = $conn->query("SELECT COUNT(*) c FROM vehicles")->fetch_assoc()['c'];
$avail_v   = $conn->query("SELECT COUNT(*) c FROM vehicles WHERE Status='Available'")->fetch_assoc()['c'];
$out_v     = $conn->query("SELECT COUNT(*) c FROM vehicles WHERE Status='Out on Road'")->fetch_assoc()['c'];
$maint_v   = $conn->query("SELECT COUNT(*) c FROM vehicles WHERE Status='Under Maintenance'")->fetch_assoc()['c'];
$svc_due   = $conn->query("SELECT COUNT(*) c FROM vehicles WHERE NextService IS NOT NULL AND NextService<=DATE_ADD(NOW(),INTERVAL 60 DAY) AND Status!='Retired'")->fetch_assoc()['c'];

// ── ACTIVE TRIPS ──
$active_trips = $conn->query("
    SELECT vt.*, v.Make, v.Model, v.RegNumber, v.Colour,
           s.FirstName, s.LastName, s.CourseType
    FROM vehicle_trips vt
    JOIN vehicles v ON v.VehicleID=vt.VehicleID
    JOIN students s ON s.StudentID=vt.StudentID
    WHERE vt.Status='Active' ORDER BY vt.DepartTime DESC
");
$activeRows = $active_trips ? $active_trips->fetch_all(MYSQLI_ASSOC) : [];

// ── FLEET ──
$filter_status=trim($_GET['status']??''); $filter_type=trim($_GET['type']??''); $search=trim($_GET['search']??'');
$where=["1=1"];
if ($filter_status) $where[]="Status='".$conn->real_escape_string($filter_status)."'";
if ($filter_type)   $where[]="LicenceType='".$conn->real_escape_string($filter_type)."'";
if ($search)        $where[]="CONCAT(Make,' ',Model,' ',RegNumber) LIKE '%".$conn->real_escape_string($search)."%'";
$vehicles=$conn->query("SELECT * FROM vehicles WHERE ".implode(' AND ',$where)." ORDER BY Status ASC, Make ASC");

// ── TRIP HISTORY ──
$trip_history=$conn->query("
    SELECT vt.*, v.Make, v.Model, v.RegNumber, s.FirstName, s.LastName
    FROM vehicle_trips vt JOIN vehicles v ON v.VehicleID=vt.VehicleID JOIN students s ON s.StudentID=vt.StudentID
    WHERE vt.Status='Returned' ORDER BY vt.DepartTime DESC LIMIT 20
");

$students_list=$conn->query("SELECT StudentID,FirstName,LastName,CourseType FROM students ORDER BY FirstName");

$status_styles=['Available'=>'background:#dcfce7;color:#166534;','Out on Road'=>'background:#dbeafe;color:#1e40af;','Under Maintenance'=>'background:#fef9c3;color:#92400e;','Retired'=>'background:#f1f5f9;color:#64748b;'];
$licence_colors=['Learners Licence Prep'=>'#dbeafe;color:#1e40af','Code 8 - Light Motor'=>'#dcfce7;color:#166534','Code 10 - Heavy Motor'=>'#fef9c3;color:#92400e','Manual Driving Course'=>'#f3e8ff;color:#7e22ce','Refresher Course'=>'#ffedd5;color:#c2410c'];

// Isipingo area coords for the map base (real Dot Com location)
$BASE_LAT = -29.9882;
$BASE_LNG = 30.9244;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Vehicles — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <!-- Leaflet.js for live map -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <style>
    body{padding-top:var(--nav-h);}
    .admin-navbar{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--navy);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:1000;box-shadow:0 2px 12px rgba(0,0,0,0.25);}
    .admin-navbar .dot-com{color:var(--red);font-family:'Syne',sans-serif;font-size:16px;font-weight:800;display:block;}
    .admin-navbar .school{color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:.5px;}

    .v-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem;}
    @media(max-width:1000px){.v-kpis{grid-template-columns:repeat(3,1fr);}}

    .live-badge{display:inline-flex;align-items:center;gap:6px;background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
    .live-dot{width:8px;height:8px;border-radius:50%;background:#dc2626;animation:livepulse 1.2s ease infinite;}
    @keyframes livepulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(1.4);}}

    /* Map */
    #trackMap{width:100%;height:420px;border-radius:12px;border:1.5px solid var(--border);z-index:1;}
    .map-legend{display:flex;gap:1rem;flex-wrap:wrap;margin-top:.8rem;font-size:12.5px;color:var(--muted);}
    .map-legend span{display:flex;align-items:center;gap:6px;}
    .leg-dot{width:12px;height:12px;border-radius:50%;display:inline-block;}

    .trip-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:1.3rem;display:grid;grid-template-columns:auto 1fr auto;gap:1rem;align-items:center;margin-bottom:1rem;}
    .trip-icon{width:46px;height:46px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
    .trip-vehicle{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;margin-bottom:.2rem;}
    .trip-meta{font-size:12.5px;color:var(--muted);line-height:1.7;}
    .trip-timer{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:#2563eb;text-align:right;}

    .v-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem;}
    .v-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:all .22s;}
    .v-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);}
    .v-card-img{height:140px;background:var(--offwhite);display:flex;align-items:center;justify-content:center;font-size:3rem;position:relative;overflow:hidden;}
    .v-card-img img{width:100%;height:100%;object-fit:cover;}
    .v-card-img .status-badge{position:absolute;top:.6rem;right:.6rem;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
    .v-card-body{padding:1.2rem;}
    .v-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:.2rem;}
    .v-reg{font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.5px;margin-bottom:.7rem;}
    .v-meta{display:grid;grid-template-columns:1fr 1fr;gap:.3rem .8rem;font-size:12px;color:var(--muted);margin-bottom:.9rem;}
    .ltag{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:500;margin-bottom:.8rem;}
    .service-warn{background:#fef9c3;color:#92400e;border-radius:6px;padding:4px 10px;font-size:11.5px;margin-bottom:.8rem;}
    .v-actions{display:flex;gap:.5rem;}

    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
    .modal.open{display:flex;}
    .modal-box{background:#fff;border-radius:14px;padding:2rem;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);position:relative;}
    .modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);}
    .modal-box h3{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:1.2rem;}

    .filter-bar{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;}

    .tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:1.5rem;}
    .tab-btn{padding:10px 18px;font-size:14px;font-weight:500;color:var(--muted);background:none;border:none;border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;transition:all .18s;}
    .tab-btn.active{color:var(--red);border-bottom-color:var(--red);}
    .tab-content{display:none;}.tab-content.active{display:block;}
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
  <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div><h1>🚗 Vehicles</h1><p>Fleet management and real-time road tracking</p></div>
    <div style="display:flex;gap:.7rem;">
      <button class="btn btn-navy" onclick="openTab('trips',document.querySelectorAll(\'.tab-btn\')[2])">📍 Log New Trip</button>
      <button class="btn btn-red"  onclick="document.getElementById('addModal').classList.add('open')">+ Add Vehicle</button>
    </div>
  </div>
  <?php show_flash(); ?>
  <?php if ($error): ?><div class="alert alert-error mb-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- KPIs -->
  <div class="v-kpis">
    <div class="kpi-card" style="--kpi-color:var(--blue);"><div class="kpi-label">TOTAL FLEET</div><div class="kpi-value"><?= $total_v ?></div></div>
    <div class="kpi-card" style="--kpi-color:#16a34a;"><div class="kpi-label">AVAILABLE</div><div class="kpi-value"><?= $avail_v ?></div></div>
    <div class="kpi-card" style="--kpi-color:#2563eb;"><div class="kpi-label">OUT ON ROAD</div><div class="kpi-value"><?= $out_v ?></div></div>
    <div class="kpi-card" style="--kpi-color:var(--amber);"><div class="kpi-label">MAINTENANCE</div><div class="kpi-value"><?= $maint_v ?></div></div>
    <div class="kpi-card" style="--kpi-color:<?= $svc_due>0?'var(--red)':'var(--muted)' ?>;"><div class="kpi-label">SERVICE DUE</div><div class="kpi-value"><?= $svc_due ?></div></div>
  </div>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab-btn active" onclick="openTab('fleet',this)">🚗 Fleet</button>
    <button class="tab-btn" onclick="openTab('live',this)">
      📍 Live Tracking
      <?php if ($out_v>0): ?><span class="live-badge" style="margin-left:6px;"><span class="live-dot"></span><?= $out_v ?> active</span><?php endif; ?>
    </button>
    <button class="tab-btn" onclick="openTab('trips',this)">📋 Log Trip</button>
    <button class="tab-btn" onclick="openTab('history',this)">🕐 Trip History</button>
  </div>

  <!-- ═══ FLEET ═══ -->
  <div class="tab-content active" id="tab-fleet">
    <form method="GET" class="filter-bar">
      <input type="hidden" name="tab" value="fleet">
      <input type="text" name="search" class="form-control" placeholder="Make, model, reg..." value="<?= htmlspecialchars($search) ?>" style="min-width:180px;">
      <select name="status" class="form-control">
        <option value="">All Statuses</option>
        <?php foreach (['Available','Out on Road','Under Maintenance','Retired'] as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type" class="form-control">
        <option value="">All Licence Types</option>
        <?php foreach ($all_licence_types as $lt): ?>
        <option value="<?= htmlspecialchars($lt) ?>" <?= $filter_type===$lt?'selected':'' ?>><?= $lt ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-navy">Filter</button>
      <?php if ($search||$filter_status||$filter_type): ?>
      <a href="vehicles.php" class="btn btn-white" style="border:1px solid var(--border);">Reset</a>
      <?php endif; ?>
    </form>

    <?php if ($vehicles && $vehicles->num_rows>0): ?>
    <div class="v-grid">
      <?php while ($v=$vehicles->fetch_assoc()):
        $picUrl=$v['Photo']?'uploads/vehicle_photos/'.$v['Photo']:null;
        $sbStyle=$status_styles[$v['Status']]??'';
        $lcStyle=$licence_colors[$v['LicenceType']]??'#e2e8f0;color:#334155';
        $serviceWarn=$v['NextService']&&strtotime($v['NextService'])<=strtotime('+60 days');
        $daysToSvc=$v['NextService']?ceil((strtotime($v['NextService'])-time())/86400):null;
      ?>
      <div class="v-card">
        <div class="v-card-img">
          <?php if ($picUrl): ?><img src="<?= htmlspecialchars($picUrl) ?>" alt=""><?php else: ?>🚗<?php endif; ?>
          <span class="status-badge" style="<?= $sbStyle ?>"><?= $v['Status'] ?></span>
        </div>
        <div class="v-card-body">
          <div class="v-name"><?= htmlspecialchars($v['Year'].' '.$v['Make'].' '.$v['Model']) ?></div>
          <div class="v-reg">🔖 <?= htmlspecialchars($v['RegNumber']) ?> · <?= htmlspecialchars($v['Colour']?:'—') ?></div>
          <span class="ltag" style="background:<?= $lcStyle ?>;"><?= htmlspecialchars($v['LicenceType']) ?></span>
          <?php if ($serviceWarn): ?>
          <div class="service-warn">⚠️ Service <?= $daysToSvc!==null?($daysToSvc<=0?'OVERDUE':"in {$daysToSvc} days"):'due soon' ?></div>
          <?php endif; ?>
          <div class="v-meta">
            <div>⚙️ <?= $v['Transmission'] ?></div>
            <div>📍 <?= number_format($v['Mileage']) ?> km</div>
            <div>🔧 <?= $v['LastService']?date('d M Y',strtotime($v['LastService'])):'—' ?></div>
            <div>📅 <?= $v['NextService']?date('d M Y',strtotime($v['NextService'])):'—' ?></div>
          </div>
          <?php if ($v['Notes']): ?><p style="font-size:12px;color:var(--muted);margin-bottom:.8rem;line-height:1.5;"><?= htmlspecialchars($v['Notes']) ?></p><?php endif; ?>
          <div class="v-actions">
            <?php if ($v['Status']==='Available'): ?>
            <button class="btn btn-sm btn-navy" style="flex:1;" onclick="prefillTrip(<?= $v['VehicleID'] ?>,'<?= htmlspecialchars(addslashes($v['Make'].' '.$v['Model'])) ?>',<?= $v['Mileage'] ?>)">📍 Dispatch</button>
            <?php elseif ($v['Status']==='Out on Road'): ?>
            <button class="btn btn-sm btn-green" style="flex:1;" onclick="openReturnModal(<?= $v['VehicleID'] ?>,'<?= htmlspecialchars(addslashes($v['Make'].' '.$v['Model'])) ?>')">✅ Mark Returned</button>
            <?php endif; ?>
            <a href="vehicles.php?delete=<?= $v['VehicleID'] ?>" class="btn btn-sm" style="background:#fee2e2;color:var(--red);" onclick="return confirm('Delete this vehicle?')">🗑</a>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center;padding:4rem;"><div style="font-size:2.5rem;">🚗</div><p style="color:var(--muted);">No vehicles found.</p></div>
    <?php endif; ?>
  </div>

  <!-- ═══ LIVE TRACKING ═══ -->
  <div class="tab-content" id="tab-live">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.2rem;">
      <div class="live-badge"><span class="live-dot"></span> LIVE</div>
      <span style="font-size:13px;color:var(--muted);">Map shows vehicle positions. Pins update on page refresh.</span>
    </div>

    <!-- LIVE MAP -->
    <div id="trackMap"></div>
    <div class="map-legend">
      <span><span class="leg-dot" style="background:#2563eb;"></span> Vehicle out on road</span>
      <span><span class="leg-dot" style="background:#16a34a;border:2px solid #fff;box-shadow:0 0 0 2px #16a34a;"></span> Dot Com Driving School (base)</span>
      <span style="color:#94a3b8;font-style:italic;">⚠️ Pins show estimated positions — real GPS requires a hardware tracker device.</span>
    </div>

    <div style="margin-top:1.5rem;">
      <?php if (!empty($activeRows)): ?>
        <?php foreach ($activeRows as $t):
          $elapsed = round((time()-strtotime($t['DepartTime']))/60);
          $hrs=floor($elapsed/60); $mins=$elapsed%60;
          $elapsedStr=$hrs>0?"{$hrs}h {$mins}m":"{$mins}m";
        ?>
        <div class="trip-card">
          <div class="trip-icon">🚗</div>
          <div>
            <div class="trip-vehicle"><?= htmlspecialchars($t['Make'].' '.$t['Model']) ?> — <?= htmlspecialchars($t['RegNumber']) ?></div>
            <div class="trip-meta">
              👤 <strong><?= htmlspecialchars($t['FirstName'].' '.$t['LastName']) ?></strong> · <?= htmlspecialchars($t['CourseType']) ?><br>
              👨‍🏫 <?= htmlspecialchars($t['InstructorName']) ?><br>
              🕐 Departed: <?= date('d M Y H:i',strtotime($t['DepartTime'])) ?>
              <?php if ($t['Route']): ?> · 📍 <?= htmlspecialchars($t['Route']) ?><?php endif; ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div class="trip-timer"><?= $elapsedStr ?><div style="font-size:11px;font-weight:400;color:var(--muted);">on road</div></div>
            <button class="btn btn-sm btn-green mt-1" onclick="openReturnByTrip(<?= $t['TripID'] ?>,'<?= htmlspecialchars(addslashes($t['Make'].' '.$t['Model'])) ?>')">✅ Return</button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
      <div class="card" style="text-align:center;padding:3rem;"><div style="font-size:2.5rem;margin-bottom:.8rem;">✅</div><p style="color:var(--muted);">All vehicles are currently in the yard. No active trips.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ LOG TRIP ═══ -->
  <div class="tab-content" id="tab-trips">
    <div class="card" style="max-width:560px;">
      <div class="card-body">
        <div class="card-title" style="margin-bottom:1rem;">📍 Dispatch Vehicle</div>
        <form method="POST">
          <div class="form-group">
            <label>Vehicle *</label>
            <select name="vehicle_id" id="tripVehicle" class="form-control" required onchange="updateStartMileage(this)">
              <option value="">-- Select available vehicle --</option>
              <?php
              $av=$conn->query("SELECT VehicleID,Make,Model,RegNumber,Mileage FROM vehicles WHERE Status='Available' ORDER BY Make");
              if ($av) while ($row=$av->fetch_assoc()):?>
              <option value="<?= $row['VehicleID'] ?>" data-mileage="<?= $row['Mileage'] ?>"><?= htmlspecialchars($row['Make'].' '.$row['Model'].' ('.$row['RegNumber'].')') ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Student *</label>
            <select name="student_id" class="form-control" required>
              <option value="">-- Select student --</option>
              <?php if ($students_list) while ($s=$students_list->fetch_assoc()): ?>
              <option value="<?= $s['StudentID'] ?>"><?= htmlspecialchars($s['FirstName'].' '.$s['LastName'].' — '.$s['CourseType']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Instructor *</label>
            <select name="instructor_name" class="form-control" required>
              <option value="">-- Select instructor --</option>
              <?php
              $il=$conn->query("SELECT FirstName,LastName FROM instructors WHERE Status='Active' ORDER BY FirstName");
              if ($il) while ($i=$il->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($i['FirstName'].' '.$i['LastName']) ?>"><?= htmlspecialchars($i['FirstName'].' '.$i['LastName']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Depart Time</label>
              <input type="datetime-local" name="depart_time" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="form-group">
              <label>Start Mileage (km)</label>
              <input type="number" name="start_mileage" id="startMileage" class="form-control" placeholder="0" min="0">
            </div>
          </div>
          <div class="form-group">
            <label>Route / Area</label>
            <input type="text" name="route" class="form-control" placeholder="e.g. Isipingo Rail — Edwin Swales loop">
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea name="trip_notes" class="form-control" rows="2"></textarea>
          </div>
          <button type="submit" name="log_trip" class="btn btn-red btn-block" style="padding:12px;">🚗 Dispatch Vehicle</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ═══ TRIP HISTORY ═══ -->
  <div class="tab-content" id="tab-history">
    <div class="card">
      <div class="card-body" style="padding:0;">
        <div class="section-title" style="padding:1.2rem 1.4rem 0;">Trip History (last 20)</div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Vehicle</th><th>Student</th><th>Instructor</th><th>Depart</th><th>Return</th><th>Distance</th><th>Route</th></tr></thead>
            <tbody>
              <?php if ($trip_history && $trip_history->num_rows>0):
                while ($th=$trip_history->fetch_assoc()):
                  $dist=($th['EndMileage']&&$th['StartMileage'])?($th['EndMileage']-$th['StartMileage']).' km':'—';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($th['Make'].' '.$th['Model']) ?></strong><br><small style="color:var(--muted);"><?= htmlspecialchars($th['RegNumber']) ?></small></td>
                <td><?= htmlspecialchars($th['FirstName'].' '.$th['LastName']) ?></td>
                <td><?= htmlspecialchars($th['InstructorName']) ?></td>
                <td><?= date('d M y H:i',strtotime($th['DepartTime'])) ?></td>
                <td><?= $th['ReturnTime']?date('d M y H:i',strtotime($th['ReturnTime'])):'—' ?></td>
                <td><?= $dist ?></td>
                <td><?= htmlspecialchars($th['Route']?:'—') ?></td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--muted);">No completed trips yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- ADD VEHICLE MODAL -->
<div class="modal" id="addModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">✕</button>
    <h3>Add New Vehicle</h3>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-row">
        <div class="form-group"><label>Make *</label><input type="text" name="make" class="form-control" placeholder="Toyota" required></div>
        <div class="form-group"><label>Model *</label><input type="text" name="model" class="form-control" placeholder="Corolla" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Year *</label><input type="number" name="year" class="form-control" placeholder="2022" min="2000" max="2030" required></div>
        <div class="form-group"><label>Colour</label><input type="text" name="colour" class="form-control" placeholder="White"></div>
      </div>
      <div class="form-group"><label>Registration *</label><input type="text" name="reg_number" class="form-control" placeholder="GP 12 34 AB" required></div>
      <div class="form-row">
        <div class="form-group">
          <label>Licence Type *</label>
          <select name="licence_type" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach ($all_licence_types as $lt): ?><option value="<?= htmlspecialchars($lt) ?>"><?= htmlspecialchars($lt) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Transmission *</label>
          <select name="transmission" class="form-control"><option value="Manual">Manual</option><option value="Automatic">Automatic</option></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Mileage (km)</label><input type="number" name="mileage" class="form-control" placeholder="0" min="0"></div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control"><option value="Available">Available</option><option value="Under Maintenance">Under Maintenance</option></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Last Service</label><input type="date" name="last_service" class="form-control"></div>
        <div class="form-group"><label>Next Service</label><input type="date" name="next_service" class="form-control"></div>
      </div>
      <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
      <div class="form-group"><label>Photo (JPG/PNG — max 3MB)</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
      <button type="submit" name="add_vehicle" class="btn btn-red btn-block" style="padding:12px;">Add Vehicle</button>
    </form>
  </div>
</div>

<!-- RETURN MODAL -->
<div class="modal" id="returnModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:400px;">
    <button class="modal-close" onclick="document.getElementById('returnModal').classList.remove('open')">✕</button>
    <h3 id="returnTitle">Mark Returned</h3>
    <p style="color:var(--muted);font-size:13.5px;margin-bottom:1.2rem;">Enter the return mileage to update the vehicle log.</p>
    <form method="GET" action="vehicles.php">
      <input type="hidden" name="return_trip" id="returnTripId">
      <div class="form-group"><label>End Mileage (km)</label><input type="number" name="end_mileage" id="returnMileage" class="form-control" placeholder="Current odometer reading" min="0"></div>
      <button type="submit" class="btn btn-green btn-block" style="padding:12px;">✅ Confirm Return</button>
    </form>
  </div>
</div>

<script>
// ── Tab switching ──
function openTab(id, btn) {
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  if (btn) btn.classList.add('active');
  if (id==='live') initMap();
}

// ── Active trips data from PHP ──
const activeTrips = <?= json_encode($activeRows) ?>;
const BASE_LAT    = <?= $BASE_LAT ?>;
const BASE_LNG    = <?= $BASE_LNG ?>;
let mapInitialised = false;

// ── Build Leaflet Map ──
function initMap() {
  if (mapInitialised) return;
  mapInitialised = true;

  const map = L.map('trackMap').setView([BASE_LAT, BASE_LNG], 13);

  // OpenStreetMap tiles (free, no API key needed)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 18
  }).addTo(map);

  // Base / school marker
  const schoolIcon = L.divIcon({
    html: `<div style="background:#0f1923;color:#fff;border-radius:8px;padding:4px 8px;font-size:11px;font-weight:700;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.3);">🏫 Dot Com DS</div>`,
    className: '', iconAnchor: [40, 20]
  });
  L.marker([BASE_LAT, BASE_LNG], {icon: schoolIcon})
    .addTo(map)
    .bindPopup('<b>Dot Com Driving School</b><br>34 Inwabi Rd, Isipingo Rail');

  if (activeTrips.length === 0) return;

  // For each active trip, place a pulsing car pin at a simulated position
  // (real GPS requires hardware tracker — we offset from base using elapsed time)
  const routeOffsets = [
    [0.012, 0.008], [0.025, -0.015], [-0.010, 0.020],
    [0.018, 0.030], [-0.020, -0.010], [0.030, 0.005]
  ];

  activeTrips.forEach((trip, idx) => {
    const offset  = routeOffsets[idx % routeOffsets.length];
    const elapsed = Math.floor((Date.now() - new Date(trip.DepartTime).getTime()) / 60000);
    // Move pin further from base as time increases (up to offset max)
    const factor  = Math.min(1, elapsed / 45);
    const lat     = BASE_LAT + offset[0] * factor;
    const lng     = BASE_LNG + offset[1] * factor;

    const carIcon = L.divIcon({
      html: `<div style="position:relative;width:36px;height:36px;">
        <div style="position:absolute;inset:0;background:#2563eb;border-radius:50%;opacity:0.25;animation:mapPulse 1.5s ease infinite;"></div>
        <div style="position:absolute;inset:4px;background:#2563eb;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 2px 6px rgba(0,0,0,0.25);">🚗</div>
      </div>
      <style>@keyframes mapPulse{0%,100%{transform:scale(1);opacity:.25;}50%{transform:scale(1.8);opacity:.05;}}</style>`,
      className: '', iconSize: [36, 36], iconAnchor: [18, 18]
    });

    const hrs = Math.floor(elapsed/60), mins = elapsed%60;
    const timeStr = hrs>0?`${hrs}h ${mins}m`:`${mins}m`;

    L.marker([lat, lng], {icon: carIcon})
      .addTo(map)
      .bindPopup(`
        <div style="font-family:sans-serif;min-width:180px;">
          <div style="font-weight:700;font-size:13px;margin-bottom:4px;">🚗 ${trip.Make} ${trip.Model}</div>
          <div style="font-size:12px;color:#555;line-height:1.6;">
            <b>Reg:</b> ${trip.RegNumber}<br>
            <b>Student:</b> ${trip.FirstName} ${trip.LastName}<br>
            <b>Instructor:</b> ${trip.InstructorName}<br>
            <b>Route:</b> ${trip.Route||'Not specified'}<br>
            <b>On road:</b> ${timeStr}
          </div>
          <div style="margin-top:6px;padding:3px 8px;background:#dbeafe;color:#1e40af;border-radius:4px;font-size:11px;font-weight:600;display:inline-block;">Out on Road</div>
        </div>`)
      .openPopup();

    // Draw dotted line from school to vehicle
    L.polyline([[BASE_LAT, BASE_LNG], [lat, lng]], {
      color: '#2563eb', weight: 2, dashArray: '6,8', opacity: 0.6
    }).addTo(map);
  });

  // Fit map to show all markers
  if (activeTrips.length > 0) {
    const bounds = [[BASE_LAT, BASE_LNG]];
    activeTrips.forEach((trip, idx) => {
      const offset = routeOffsets[idx % routeOffsets.length];
      const factor = Math.min(1, Math.floor((Date.now()-new Date(trip.DepartTime).getTime())/60000)/45);
      bounds.push([BASE_LAT+offset[0]*factor, BASE_LNG+offset[1]*factor]);
    });
    map.fitBounds(bounds, {padding:[40,40]});
  }
}

function prefillTrip(vid, vname, miles) {
  openTab('trips', document.querySelectorAll('.tab-btn')[2]);
  const sel=document.getElementById('tripVehicle');
  for (let i=0;i<sel.options.length;i++){if(sel.options[i].value==vid){sel.selectedIndex=i;break;}}
  document.getElementById('startMileage').value=miles;
  window.scrollTo(0,0);
}

function openReturnByTrip(tid, vname) {
  document.getElementById('returnTitle').textContent='Return: '+vname;
  document.getElementById('returnTripId').value=tid;
  document.getElementById('returnMileage').value='';
  document.getElementById('returnModal').classList.add('open');
}

function openReturnModal(vid, vname) {
  // Find the active trip for this vehicle
  const trip=activeTrips.find(t=>t.VehicleID==vid);
  if (trip) openReturnByTrip(trip.TripID, vname);
  else { openTab('live',document.querySelectorAll('.tab-btn')[1]); }
}

function updateStartMileage(sel) {
  const opt=sel.options[sel.selectedIndex];
  const m=opt.getAttribute('data-mileage');
  if (m) document.getElementById('startMileage').value=m;
}

// Auto open correct tab from URL param
const urlTab=new URLSearchParams(window.location.search).get('tab');
if (urlTab) openTab(urlTab, null);

<?php if ($error): ?>document.getElementById('addModal').classList.add('open');<?php endif; ?>
</script>
</body>
</html>