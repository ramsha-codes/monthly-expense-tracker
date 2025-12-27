<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$query = "SELECT user_id, password_hash FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $row['password_hash'])) {
        $_SESSION['user_id'] = $row['user_id'];
        header("Location: ../public/dashboard.html");
        exit();
    } else {
        echo "❌ Incorrect password";
    }
} else {
    echo "❌ User not found";
}
?>
