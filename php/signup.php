<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public/signup.html");
    exit;
}

$first_name = trim($_POST['first_name']);
$last_name  = trim($_POST['last_name']);
$email      = trim($_POST['email']);
$password   = $_POST['password'];

/**
 * Basic validation
 */
// 1. Standard PHP Email Filter
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("❌ Invalid email format");
}

// 2. Strict Check: Must contain a '.' after the '@' symbol (e.g., .com, .net)
if (!preg_match('/@[^@]+\.[a-zA-Z]{2,}$/', $email)) {
    die("❌ Invalid email format. Missing domain extension (e.g., .com)");
}

if (strlen($password) < 6) {
    die("❌ Password must be at least 6 characters");
}

$full_name = $first_name . " " . $last_name;
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

/**
 * Insert user
 */
$sql = "
    INSERT INTO users (full_name, email, password_hash)
    VALUES (?, ?, ?)
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sss", $full_name, $email, $hashed_password);

if (!mysqli_stmt_execute($stmt)) {
    if (mysqli_errno($conn) == 1062) {
        die("❌ Email already exists");
    }
    die("❌ Error: " . mysqli_error($conn));
}

/**
 * Auto-login after signup
 */
$user_id = mysqli_insert_id($conn);
$_SESSION['user_id'] = $user_id;

/**
 * Redirect first-time user to budget setup
 */
header("Location: ../public/budget.html");
exit;
?>