<?php
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dot Com Driving School</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body{padding-top:var(--nav-h);}

    /* ── HERO ── */
    .hero{
      min-height:calc(100vh - var(--nav-h));
      background:linear-gradient(135deg,rgba(15,25,35,0.88) 0%,rgba(15,25,35,0.6) 100%),
                 url('https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=1400&q=80') center/cover no-repeat;
      display:flex;align-items:center;padding:4rem 2rem;
      position:relative;overflow:hidden;
    }
    .hero-content{max-width:580px;}
    .hero h1{
      font-family:'Syne',sans-serif;
      font-size:clamp(2.2rem,5vw,3.4rem);font-weight:800;
      color:#fff;line-height:1.08;margin-bottom:1rem;
    }
    .hero h1 span{color:var(--red);}
    .hero p{font-size:16px;color:rgba(255,255,255,0.7);line-height:1.7;margin-bottom:2rem;max-width:440px;}
    .hero-btns{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:3rem;}
    .hero-features{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;max-width:640px;}
    .hero-feat{text-align:center;color:rgba(255,255,255,0.75);}
    .hero-feat .icon{font-size:1.6rem;margin-bottom:.4rem;}
    .hero-feat span{font-size:11.5px;display:block;line-height:1.4;}

    /* ── ABOUT ── */
    .about-section{padding:5rem 2rem;background:#fff;}
    .about-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center;}
    .about-tag{display:inline-block;background:#fee2e2;color:var(--red);font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;letter-spacing:.4px;margin-bottom:.8rem;}
    .about-inner h2{font-family:'Syne',sans-serif;font-size:1.9rem;font-weight:700;margin-bottom:1rem;line-height:1.2;}
    .about-inner p{color:var(--muted);line-height:1.75;font-size:14.5px;margin-bottom:1rem;}
    .about-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-top:2rem;padding-top:2rem;border-top:1px solid var(--border);}
    .astat-num{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:700;color:var(--red);display:block;}
    .astat-lbl{font-size:12px;color:var(--muted);}
    .about-visual{background:var(--navy);border-radius:16px;padding:2rem;color:#fff;}
    .av-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.07);}
    .av-row:last-child{border-bottom:none;padding-bottom:0;}
    .av-num{width:34px;height:34px;border-radius:8px;background:var(--red);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;flex-shrink:0;}
    .av-info strong{display:block;font-size:13px;font-weight:500;}
    .av-info small{font-size:11.5px;color:rgba(255,255,255,0.45);}

    /* ── COURSES ── */
    .courses-section{padding:5rem 2rem;background:var(--offwhite);}
    .section-header{text-align:center;margin-bottom:3rem;}
    .section-header .tag{display:inline-block;background:rgba(204,31,31,0.1);color:var(--red);font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;letter-spacing:.4px;margin-bottom:.7rem;}
    .section-header h2{font-family:'Syne',sans-serif;font-size:2rem;font-weight:700;margin-bottom:.5rem;}
    .section-header p{color:var(--muted);font-size:14.5px;max-width:480px;margin:0 auto;}
    .courses-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.2rem;}
    .course-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:1.8rem;transition:all .22s;position:relative;overflow:hidden;}
    .course-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--red);transform:scaleX(0);transition:transform .3s;transform-origin:left;}
    .course-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);}
    .course-card:hover::before{transform:scaleX(1);}
    .course-code{display:inline-block;background:#fee2e2;color:var(--red);font-size:10.5px;font-weight:600;padding:3px 10px;border-radius:20px;margin-bottom:.9rem;}
    .course-card h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:.5rem;}
    .course-card p{font-size:12.5px;color:var(--muted);line-height:1.65;margin-bottom:1.2rem;}
    .course-price{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:var(--text);margin-bottom:1.2rem;}
    .course-price small{font-size:12px;font-weight:400;color:var(--muted);font-family:'DM Sans',sans-serif;}

    /* ── HOW ── */
    .how-section{padding:5rem 2rem;background:#fff;}
    .how-grid{max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;position:relative;}
    .how-grid::before{content:'';position:absolute;top:27px;left:15%;right:15%;height:2px;background:linear-gradient(to right,var(--red),var(--navy));opacity:.15;}
    .how-step{text-align:center;padding:1.5rem 1rem 0;}
    .how-step .circle{width:54px;height:54px;border-radius:50%;background:var(--navy);color:#fff;font-family:'Syne',sans-serif;font-size:17px;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;position:relative;z-index:1;box-shadow:0 4px 16px rgba(15,25,35,0.22);}
    .how-step h3{font-size:13.5px;font-weight:600;margin-bottom:.4rem;}
    .how-step p{font-size:12px;color:var(--muted);line-height:1.6;}

    /* ── CONTACT ── */
    .contact-section{padding:5rem 2rem;background:var(--navy);}
    .contact-inner{max-width:700px;margin:0 auto;text-align:center;}
    .contact-inner h2{font-family:'Syne',sans-serif;font-size:2rem;font-weight:700;color:#fff;margin-bottom:.6rem;}
    .contact-inner p{color:rgba(255,255,255,0.55);font-size:15px;margin-bottom:2.5rem;}
    .contact-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;text-align:center;}
    .contact-item{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:1.5rem 1rem;}
    .contact-item .ci-icon{font-size:1.6rem;margin-bottom:.7rem;}
    .contact-item h4{font-size:13px;font-weight:500;color:#fff;margin-bottom:.3rem;}
    .contact-item p{font-size:12.5px;color:rgba(255,255,255,0.45);margin:0;}

    /* ── FOOTER ── */
    footer{background:var(--navy2);color:rgba(255,255,255,0.35);text-align:center;padding:1.5rem;font-size:12.5px;border-top:1px solid rgba(255,255,255,0.07);}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- HERO -->
<section class="hero" id="home">
  <div class="hero-content">
    <h1>Drive Today,<br><span>Lead Tomorrow</span></h1>
    <p>Professional driving lessons by certified instructors. Fully digital — from online registration to QR-powered attendance tracking.</p>
    <div class="hero-btns">
      <a href="register.php" class="btn btn-red btn-lg">Register Now</a>
      <a href="#courses"     class="btn btn-outline btn-lg">Learn More</a>
    </div>
    <div class="hero-features">
      <div class="hero-feat"><div class="icon">👨‍🏫</div><span>Professional Instructors</span></div>
      <div class="hero-feat"><div class="icon">🎯</div><span>Quality Training</span></div>
      <div class="hero-feat"><div class="icon">📅</div><span>Flexible Schedule</span></div>
      <div class="hero-feat"><div class="icon">🛡️</div><span>Safe Driving</span></div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
  <div class="about-inner">
    <div>
      <span class="about-tag">ABOUT US</span>
      <h2>South Africa's Smartest Driving School</h2>
      <p>Dot Com Driving School has transformed from a traditional, paper-based driving school into a fully digital operation — with online registration, QR attendance, real-time progress tracking and a secured admin system.</p>
      <p>We combine expert K53 instructors with modern technology so you spend less time on paperwork and more time on the road.</p>
      <div class="about-stats">
        <div><span class="astat-num">200+</span><span class="astat-lbl">Students enrolled</span></div>
        <div><span class="astat-num">15</span><span class="astat-lbl">Qualified instructors</span></div>
        <div><span class="astat-num">98%</span><span class="astat-lbl">Pass rate</span></div>
      </div>
    </div>
    <div class="about-visual">
      <div style="font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:rgba(255,255,255,0.4);margin-bottom:1rem;letter-spacing:.4px;">HOW THE SYSTEM WORKS</div>
      <div class="av-row">
        <div class="av-num">1</div>
        <div class="av-info"><strong>Register online</strong><small>Fill in details, pick your course</small></div>
      </div>
      <div class="av-row">
        <div class="av-num">2</div>
        <div class="av-info"><strong>Get your QR code</strong><small>Unique digital ID generated instantly</small></div>
      </div>
      <div class="av-row">
        <div class="av-num">3</div>
        <div class="av-info"><strong>Attend lessons</strong><small>Scan QR at arrival — automatic record</small></div>
      </div>
      <div class="av-row">
        <div class="av-num">4</div>
        <div class="av-info"><strong>Track your progress</strong><small>View attendance & performance anytime</small></div>
      </div>
    </div>
  </div>
</section>

<!-- COURSES -->
<section class="courses-section" id="courses">
  <div class="section-header">
    <span class="tag">COURSES</span>
    <h2>Choose Your Course</h2>
    <p>All courses include digital tracking, QR attendance, and certified instructors.</p>
  </div>
  <div class="courses-grid">
    <?php
    $courses_q = $conn->query("SELECT * FROM courses ORDER BY Price ASC");
    if ($courses_q && $courses_q->num_rows > 0):
      while ($c = $courses_q->fetch_assoc()):
    ?>
    <div class="course-card">
      <span class="course-code">📚 <?= htmlspecialchars($c['Duration']) ?></span>
      <h3><?= htmlspecialchars($c['CourseName']) ?></h3>
      <p><?= htmlspecialchars($c['Description']) ?></p>
      <div class="course-price">R <?= number_format($c['Price'], 0) ?> <small>/ package</small></div>
      <a href="register.php" class="btn btn-red btn-block">Enrol Now</a>
    </div>
    <?php endwhile; else: ?>
    <!-- Fallback if DB not yet seeded -->
    <div class="course-card"><span class="course-code">1 week</span><h3>Learners Licence Prep</h3><p>Pass your K53 test first time. Theory + mock tests included.</p><div class="course-price">R 650 <small>/ package</small></div><a href="register.php" class="btn btn-red btn-block">Enrol Now</a></div>
    <div class="course-card"><span class="course-code">10 lessons</span><h3>Manual Driving Course</h3><p>Full Code 8 manual licence. K53 manoeuvres + road test prep.</p><div class="course-price">R 2,800 <small>/ package</small></div><a href="register.php" class="btn btn-red btn-block">Enrol Now</a></div>
    <div class="course-card"><span class="course-code">15 lessons</span><h3>Code 10 - Heavy Motor</h3><p>Professional truck and minibus training.</p><div class="course-price">R 4,500 <small>/ package</small></div><a href="register.php" class="btn btn-red btn-block">Enrol Now</a></div>
    <div class="course-card"><span class="course-code">5 lessons</span><h3>Refresher Course</h3><p>Already licenced? Build confidence back on the road.</p><div class="course-price">R 1,200 <small>/ package</small></div><a href="register.php" class="btn btn-red btn-block">Enrol Now</a></div>
    <?php endif; ?>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section">
  <div class="section-header">
    <span class="tag">PROCESS</span>
    <h2>How It Works</h2>
    <p>Simple steps from sign-up to your licence.</p>
  </div>
  <div class="how-grid">
    <div class="how-step"><div class="circle">1</div><h3>Student Registers</h3><p>Create an account and choose a course online.</p></div>
    <div class="how-step"><div class="circle">2</div><h3>Get QR Code</h3><p>Each student gets a unique QR code instantly.</p></div>
    <div class="how-step"><div class="circle">3</div><h3>Scan QR Code</h3><p>Admin scans the QR code during lessons.</p></div>
    <div class="how-step"><div class="circle">4</div><h3>Record Attendance</h3><p>Attendance is automatically recorded in the database.</p></div>
    <div class="how-step"><div class="circle">5</div><h3>View Reports</h3><p>Admins can view and export attendance reports.</p></div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact-section" id="contact">
  <div class="contact-inner">
    <h2>Get in Touch</h2>
    <p>Ready to start your journey? Contact us or register directly online.</p>
    <div class="contact-grid">
      <div class="contact-item"><div class="ci-icon">📧</div><h4>Email</h4><p>info@dotcomdriving.co.za</p></div>
      <div class="contact-item"><div class="ci-icon">📞</div><h4>Phone</h4><p>071 000 0000</p></div>
      <div class="contact-item"><div class="ci-icon">📍</div><h4>Location</h4><p>Johannesburg, GP</p></div>
    </div>
    <div style="margin-top:2.5rem;">
      <a href="register.php" class="btn btn-red btn-lg">Register Now</a>
    </div>
  </div>
</section>

<footer>
  <p>&copy; <?= date('Y') ?> Dot Com Driving School. IS3 Digital Transformation Project.</p>
  <p style="margin-top:.3rem;font-size:11px;">System URL: http://localhost/dotcom-driving-school/</p>
</footer>
</body>
</html>
