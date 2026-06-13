$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE Username='$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['Password'])) {

        $_SESSION['user'] = $user;

        if ($user['Role'] == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: student_dashboard.php");
        }

    } else {
        echo "❌ Invalid password";
    }
} else {
    echo "❌ User not found";
}