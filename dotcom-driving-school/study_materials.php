<?php
require_once 'includes/db.php';
require_student();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Study Materials — Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}

    /* HERO */
    .sm-hero{
      background:linear-gradient(135deg,var(--navy) 0%,var(--red-dark) 130%);
      color:#fff;padding:3rem 2rem;text-align:center;position:relative;
    }
    .sm-hero .back-btn{
      position:absolute;top:1.5rem;left:1.5rem;
      background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);
      color:#fff;padding:7px 16px;border-radius:20px;font-size:13px;
      text-decoration:none;transition:background .2s;
    }
    .sm-hero .back-btn:hover{background:rgba(255,255,255,0.18);}
    .sm-hero .icon{font-size:2.4rem;margin-bottom:.6rem;}
    .sm-hero h1{font-family:'Syne',sans-serif;font-size:2rem;font-weight:700;margin-bottom:.4rem;}
    .sm-hero p{font-size:14.5px;color:rgba(255,255,255,0.65);}

    /* CONTENT */
    .sm-body{max-width:1100px;margin:0 auto;padding:2rem;}

    /* TABS */
    .sm-tabs{
      display:flex;gap:.5rem;border-bottom:1px solid var(--border);
      margin-bottom:2rem;overflow-x:auto;
    }
    .sm-tab{
      display:flex;align-items:center;gap:7px;
      padding:12px 18px;font-size:14px;font-weight:500;
      color:var(--muted);cursor:pointer;border-bottom:3px solid transparent;
      white-space:nowrap;transition:all .18s;background:none;border-top:none;border-left:none;border-right:none;
    }
    .sm-tab:hover{color:var(--text);}
    .sm-tab.active{color:var(--red);border-bottom-color:var(--red);}

    .sm-section{display:none;}
    .sm-section.active{display:block;animation:fadeIn .3s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}

    .sm-section h2{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;margin-bottom:1.4rem;}

    /* SIGN CATEGORY */
    .sign-category{margin-bottom:2.4rem;}
    .sign-category-title{
      display:flex;align-items:center;gap:8px;
      font-size:15px;font-weight:600;margin-bottom:1rem;
    }
    .cat-dot{width:11px;height:11px;border-radius:50%;display:inline-block;flex-shrink:0;}
    .cat-red{background:var(--red);color:var(--red);}
    .cat-yellow{background:#f5b800;color:#92400e;}
    .cat-green{background:var(--green);color:var(--green);}
    .cat-blue{background:var(--blue);color:var(--blue);}

    .sign-grid{
      display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
      gap:1rem;
    }
    .sign-card{
      background:#fff;border:1px solid var(--border);border-radius:var(--radius);
      padding:1.2rem;text-align:center;transition:all .2s;
    }
    .sign-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
    .sign-icon{
      width:54px;height:54px;border-radius:10px;margin:0 auto .8rem;
      display:flex;align-items:center;justify-content:center;font-size:1.6rem;
    }
    .sign-icon.bg-red{background:#fee2e2;}
    .sign-icon.bg-yellow{background:#fef9c3;}
    .sign-icon.bg-blue{background:#dbeafe;}
    .sign-icon.bg-green{background:#dcfce7;}
    .sign-name{font-weight:600;font-size:13px;letter-spacing:.3px;margin-bottom:.4rem;}
    .sign-desc{font-size:12px;color:var(--muted);line-height:1.55;}

    /* RULES LIST */
    .rule-card{
      display:flex;gap:1.2rem;align-items:flex-start;
      background:#fff;border:1px solid var(--border);border-radius:var(--radius);
      padding:1.4rem 1.6rem;margin-bottom:1rem;
    }
    .rule-num{
      font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;
      color:#e6e9ed;flex-shrink:0;line-height:1;min-width:48px;
    }
    .rule-content h3{font-size:14.5px;font-weight:600;margin-bottom:.3rem;}
    .rule-content p{font-size:13px;color:var(--muted);line-height:1.6;}

    /* VEHICLE CONTROLS */
    .control-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.2rem;}
    .control-card{
      background:#fff;border:1px solid var(--border);border-radius:var(--radius);
      padding:1.4rem;
    }
    .control-card .ci{font-size:1.6rem;margin-bottom:.6rem;}
    .control-card h3{font-size:14px;font-weight:600;margin-bottom:.4rem;}
    .control-card p{font-size:12.5px;color:var(--muted);line-height:1.6;}

    /* LICENCE CODES TABLE */
    .code-table{width:100%;border-collapse:collapse;background:#fff;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);}
    .code-table th{background:var(--navy);color:#fff;font-size:12px;font-weight:500;letter-spacing:.4px;padding:12px 16px;text-align:left;}
    .code-table td{padding:12px 16px;font-size:13.5px;border-bottom:1px solid var(--border);}
    .code-table tr:last-child td{border-bottom:none;}
    .code-badge{
      display:inline-block;background:var(--navy);color:#fff;font-family:'Syne',sans-serif;
      font-weight:700;font-size:12px;padding:3px 10px;border-radius:6px;
    }

    /* TIP BOX */
    .tip-box{
      background:#fff7ed;border:1px solid #fed7aa;border-radius:var(--radius);
      padding:1.2rem 1.5rem;margin-top:2rem;display:flex;gap:12px;align-items:flex-start;
    }
    .tip-box .ti{font-size:1.4rem;flex-shrink:0;}
    .tip-box h4{font-size:13.5px;font-weight:600;color:#92400e;margin-bottom:.3rem;}
    .tip-box p{font-size:12.5px;color:#92400e;line-height:1.6;opacity:.85;}

    @media(max-width:600px){
      .sm-hero h1{font-size:1.5rem;}
      .sm-tabs{gap:0;}
      .sm-tab{padding:10px 12px;font-size:13px;}
    }
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- HERO -->
<div class="sm-hero">
  <a href="student_dashboard.php" class="back-btn">← Back to Dashboard</a>
  <div class="icon">📚</div>
  <h1>Study Materials</h1>
  <p>Everything you need to prepare for your Learner's Licence (K53)</p>
</div>

<div class="sm-body">

  <!-- TABS -->
  <div class="sm-tabs">
    <button class="sm-tab active" onclick="showTab('signs', this)">🚦 Road Signs</button>
    <button class="sm-tab" onclick="showTab('rules', this)">📜 Rules of the Road</button>
    <button class="sm-tab" onclick="showTab('controls', this)">🎛️ Vehicle Controls</button>
    <button class="sm-tab" onclick="showTab('codes', this)">📋 Licence Codes</button>
  </div>

  <!-- ═══ ROAD SIGNS ═══ -->
  <div class="sm-section active" id="signs">
    <h2>Road Signs — K53 Reference</h2>

    <!-- Regulatory (Red) -->
    <div class="sign-category">
      <div class="sign-category-title cat-red"><span class="cat-dot cat-red"></span>Regulatory Signs (Red) — Prohibit or restrict</div>
      <div class="sign-grid">
        <div class="sign-card"><div class="sign-icon bg-red">🛑</div><div class="sign-name">STOP</div><div class="sign-desc">Come to a complete halt. Yield right of way to all traffic.</div></div>
        <div class="sign-card"><div class="sign-icon bg-red">⛔</div><div class="sign-name">NO ENTRY</div><div class="sign-desc">Entry is prohibited in this direction. Do not proceed.</div></div>
        <div class="sign-card"><div class="sign-icon bg-red">🚫</div><div class="sign-name">NO OVERTAKING</div><div class="sign-desc">Overtaking of any moving vehicle is prohibited.</div></div>
        <div class="sign-card"><div class="sign-icon bg-blue">⬇️</div><div class="sign-name">YIELD</div><div class="sign-desc">Give way to approaching traffic before proceeding.</div></div>
        <div class="sign-card"><div class="sign-icon bg-blue">🔢</div><div class="sign-name">SPEED LIMIT</div><div class="sign-desc">Maximum speed: 60 km/h urban, 120 km/h freeways.</div></div>
        <div class="sign-card"><div class="sign-icon bg-blue">🅿️</div><div class="sign-name">NO PARKING</div><div class="sign-desc">Parking is prohibited at this location.</div></div>
      </div>
    </div>

    <!-- Warning (Yellow) -->
    <div class="sign-category">
      <div class="sign-category-title cat-yellow"><span class="cat-dot cat-yellow"></span>Warning Signs (Yellow) — Alert to hazards</div>
      <div class="sign-grid">
        <div class="sign-card"><div class="sign-icon bg-yellow">⚠️</div><div class="sign-name">SHARP CURVE</div><div class="sign-desc">Reduce speed. Sharp bend ahead.</div></div>
        <div class="sign-card"><div class="sign-icon bg-yellow">🚸</div><div class="sign-name">PEDESTRIANS</div><div class="sign-desc">Watch for pedestrians crossing ahead.</div></div>
        <div class="sign-card"><div class="sign-icon bg-yellow">🦓</div><div class="sign-name">ZEBRA CROSSING</div><div class="sign-desc">Yield to pedestrians on crossing.</div></div>
        <div class="sign-card"><div class="sign-icon bg-yellow">⛰️</div><div class="sign-name">STEEP DESCENT</div><div class="sign-desc">Steep downward gradient ahead. Use lower gear.</div></div>
        <div class="sign-card"><div class="sign-icon bg-yellow">🚂</div><div class="sign-name">LEVEL CROSSING</div><div class="sign-desc">Railway crossing ahead. Look for trains.</div></div>
        <div class="sign-card"><div class="sign-icon bg-yellow">🌀</div><div class="sign-name">SLIPPERY ROAD</div><div class="sign-desc">Slippery when wet. Reduce speed.</div></div>
      </div>
    </div>

    <!-- Informational (Green/Blue) -->
    <div class="sign-category">
      <div class="sign-category-title cat-green"><span class="cat-dot cat-green"></span>Information Signs (Green/Blue) — Guidance & directions</div>
      <div class="sign-grid">
        <div class="sign-card"><div class="sign-icon bg-green">🏥</div><div class="sign-name">HOSPITAL</div><div class="sign-desc">Hospital nearby. Avoid using your hooter.</div></div>
        <div class="sign-card"><div class="sign-icon bg-green">⛽</div><div class="sign-name">FUEL STATION</div><div class="sign-desc">Petrol/fuel station ahead.</div></div>
        <div class="sign-card"><div class="sign-icon bg-blue">🛣️</div><div class="sign-name">FREEWAY</div><div class="sign-desc">Freeway begins. Minimum speed limits apply.</div></div>
        <div class="sign-card"><div class="sign-icon bg-blue">🅿️</div><div class="sign-name">PARKING AREA</div><div class="sign-desc">Designated parking area ahead.</div></div>
        <div class="sign-card"><div class="sign-icon bg-green">🍴</div><div class="sign-name">REST AREA</div><div class="sign-desc">Rest stop / picnic area available ahead.</div></div>
        <div class="sign-card"><div class="sign-icon bg-blue">📞</div><div class="sign-name">EMERGENCY PHONE</div><div class="sign-desc">Emergency telephone available at this point.</div></div>
      </div>
    </div>

    <div class="tip-box">
      <div class="ti">💡</div>
      <div>
        <h4>Exam Tip</h4>
        <p>Sign shape and color are tested as much as the meaning. Round = regulatory, triangle = warning, rectangle = information. Red border = prohibition/command.</p>
      </div>
    </div>
  </div>

  <!-- ═══ RULES OF THE ROAD ═══ -->
  <div class="sm-section" id="rules">
    <h2>Rules of the Road — K53 Key Principles</h2>

    <div class="rule-card">
      <div class="rule-num">01</div>
      <div class="rule-content">
        <h3>Right of Way at Intersections</h3>
        <p>At an uncontrolled intersection, yield to the vehicle on your RIGHT. At a 4-way stop, the first vehicle to arrive goes first; if vehicles arrive simultaneously, the vehicle on the right has priority.</p>
      </div>
    </div>

    <div class="rule-card">
      <div class="rule-num">02</div>
      <div class="rule-content">
        <h3>Following Distance (2-Second Rule)</h3>
        <p>Maintain a minimum 2-second gap behind the vehicle in front. Double this distance (4 seconds) in wet or low-visibility conditions, and increase further for heavy vehicles.</p>
      </div>
    </div>

    <div class="rule-card">
      <div class="rule-num">03</div>
      <div class="rule-content">
        <h3>Overtaking Rules</h3>
        <p>Only overtake on the right, unless the vehicle ahead is turning right or you're on a multi-lane one-way road. Never overtake on solid yellow lines, hills, bends, or near pedestrian crossings.</p>
      </div>
    </div>

    <div class="rule-card">
      <div class="rule-num">04</div>
      <div class="rule-content">
        <h3>Speed Limits</h3>
        <p>Urban areas: 60 km/h. Rural roads: 100 km/h. Freeways: 120 km/h. School zones: 40 km/h (or as posted). Always adjust speed for road and weather conditions, even below the posted limit.</p>
      </div>
    </div>

    <div class="rule-card">
      <div class="rule-num">05</div>
      <div class="rule-content">
        <h3>Seat Belts</h3>
        <p>All occupants must wear seat belts at all times while the vehicle is moving. The driver is responsible for ensuring passengers under 14 years old are properly restrained.</p>
      </div>
    </div>

    <div class="rule-card">
      <div class="rule-num">06</div>
      <div class="rule-content">
        <h3>Traffic Lights & Stop Signs</h3>
        <p>A red traffic light or stop sign requires a complete stop behind the line. At a green light, proceed only if the intersection is clear. An amber light means stop if it's safe to do so.</p>
      </div>
    </div>

    <div class="rule-card">
      <div class="rule-num">07</div>
      <div class="rule-content">
        <h3>Alcohol & Driving</h3>
        <p>The legal blood alcohol limit for drivers in South Africa is 0.05g per 100ml (0.02g for professional drivers). The safest choice is never to drink and drive.</p>
      </div>
    </div>

    <div class="tip-box">
      <div class="ti">💡</div>
      <div>
        <h4>Exam Tip</h4>
        <p>Many K53 questions test "what should you do" scenarios rather than pure facts. Always choose the safest option — even if it seems slower or more cautious.</p>
      </div>
    </div>
  </div>

  <!-- ═══ VEHICLE CONTROLS ═══ -->
  <div class="sm-section" id="controls">
    <h2>Vehicle Controls — Know Your Vehicle</h2>

    <div class="control-grid">
      <div class="control-card"><div class="ci">🎚️</div><h3>Steering Wheel</h3><p>Use the "push-pull" or "hand-over-hand" method. Keep both hands on the wheel at the 9 and 3 o'clock positions for maximum control.</p></div>
      <div class="control-card"><div class="ci">🦶</div><h3>Pedals (Manual)</h3><p>From left to right: Clutch, Brake, Accelerator (CBA). Clutch is operated with the left foot only; brake and accelerator with the right foot.</p></div>
      <div class="control-card"><div class="ci">⚙️</div><h3>Gear Lever</h3><p>Always depress the clutch fully before changing gears. Move through gears progressively — don't skip gears when accelerating from a stop.</p></div>
      <div class="control-card"><div class="ci">🅿️</div><h3>Handbrake</h3><p>Apply the handbrake whenever the vehicle is stationary, especially on a hill (handbrake start/hill start technique is tested in the yard test).</p></div>
      <div class="control-card"><div class="ci">💡</div><h3>Indicators</h3><p>Signal at least 2 seconds before turning or changing lanes. Cancel the indicator after completing the manoeuvre if it doesn't self-cancel.</p></div>
      <div class="control-card"><div class="ci">🔆</div><h3>Lights</h3><p>Use headlights in low visibility, rain, or after sunset. Switch to low beam when approaching oncoming traffic to avoid blinding other drivers.</p></div>
      <div class="control-card"><div class="ci">🪞</div><h3>Mirrors</h3><p>Check rear-view and side mirrors every 5–8 seconds, and always before braking, turning, or changing lanes (mirror-signal-manoeuvre routine).</p></div>
      <div class="control-card"><div class="ci">🧯</div><h3>Wipers & Demisters</h3><p>Know how to quickly activate wipers and rear demister — visibility checks are part of the vehicle controls test.</p></div>
      <div class="control-card"><div class="ci">🔧</div><h3>Dashboard Warning Lights</h3><p>Be able to identify common warning lights: engine temperature, oil pressure, battery, brake system, and seatbelt reminder.</p></div>
    </div>

    <div class="tip-box">
      <div class="ti">💡</div>
      <div>
        <h4>Exam Tip</h4>
        <p>The vehicle controls test happens BEFORE you start driving. Examiners often ask you to point to or operate 3–5 controls — practice naming each one out loud.</p>
      </div>
    </div>
  </div>

  <!-- ═══ LICENCE CODES ═══ -->
  <div class="sm-section" id="codes">
    <h2>South African Driving Licence Codes</h2>

    <table class="code-table">
      <thead>
        <tr><th>Code</th><th>Vehicle Type</th><th>Description</th><th>Min. Age</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="code-badge">A</span></td>
          <td>Motorcycle</td>
          <td>Motorcycles with or without a sidecar, any engine size.</td>
          <td>16 (A1) / 18 (A)</td>
        </tr>
        <tr>
          <td><span class="code-badge">A1</span></td>
          <td>Light Motorcycle</td>
          <td>Motorcycles with an engine capacity not exceeding 125cc.</td>
          <td>16</td>
        </tr>
        <tr>
          <td><span class="code-badge">B</span></td>
          <td>Light Motor Vehicle</td>
          <td>Cars, light vehicles &amp; minibuses up to 3,500kg GVM, max 16 passengers.</td>
          <td>18</td>
        </tr>
        <tr>
          <td><span class="code-badge">C1</span></td>
          <td>Mid-size Heavy Vehicle</td>
          <td>Heavy vehicles 3,500kg–16,000kg GVM.</td>
          <td>18</td>
        </tr>
        <tr>
          <td><span class="code-badge">C</span></td>
          <td>Heavy Motor Vehicle</td>
          <td>Heavy vehicles over 16,000kg GVM (trucks).</td>
          <td>18 (21 for public transport)</td>
        </tr>
        <tr>
          <td><span class="code-badge">EB</span></td>
          <td>Light Vehicle + Trailer</td>
          <td>Code B vehicle towing a trailer exceeding 750kg.</td>
          <td>18</td>
        </tr>
        <tr>
          <td><span class="code-badge">EC1</span></td>
          <td>Articulated Mid-size</td>
          <td>Code C1 vehicle towing a trailer exceeding 750kg.</td>
          <td>18</td>
        </tr>
        <tr>
          <td><span class="code-badge">EC</span></td>
          <td>Articulated Heavy Vehicle</td>
          <td>Code C vehicle towing a trailer exceeding 750kg (e.g. truck + trailer combos).</td>
          <td>18 (21 for public transport)</td>
        </tr>
      </tbody>
    </table>

    <div class="tip-box">
      <div class="ti">💡</div>
      <div>
        <h4>Good to Know</h4>
        <p>GVM = Gross Vehicle Mass (the maximum operating weight of a vehicle as specified by the manufacturer, including its own weight plus passengers, fuel and cargo).</p>
      </div>
    </div>
  </div>

</div>

<script>
function showTab(id, btn){
  document.querySelectorAll('.sm-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.sm-tab').forEach(t => t.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>