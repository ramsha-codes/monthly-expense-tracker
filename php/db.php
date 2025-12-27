<?php
$host = "localhost";
$user = "root";
$password = ""; // default for XAMPP mac
$database = "expense_tracker";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
