<?php
// ── Database Configuration ──
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dotcom_driving_school');
define('DB_PORT', 3307);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:2rem;background:#fee2e2;color:#991b1b;border-radius:8px;margin:2rem auto;max-width:500px;">
        <strong>Database Connection Failed</strong><br>
        ' . htmlspecialchars($conn->connect_error) . '<br><br>
        Make sure:<br>
        1. XAMPP is running (Apache + MySQL)<br>
        2. You imported <code>dotcom_db.sql</code> into phpMyAdmin<br>
        3. Database name is <strong>dotcom_driving_school</strong><br>
        4. MySQL is running on port <strong>3307</strong>
    </div>');
}

$conn->set_charset('utf8mb4');

// ── Session helper ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Flash message helper ──
function set_flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function show_flash(): void {
    if (!isset($_SESSION['flash'])) return;
    $f = $_SESSION['flash'];
    $cls = $f['type'] === 'success' ? 'alert-success' : 'alert-error';
    echo '<div class="alert ' . $cls . '">' . htmlspecialchars($f['msg']) . '</div>';
    unset($_SESSION['flash']);
}

// ── Auth guards ──
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
function require_admin(): void {
    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: admin_login.php');
        exit;
    }
}
function require_student(): void {
    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header('Location: login.php');
        exit;
    }
}
?>