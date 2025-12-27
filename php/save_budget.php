<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("User not logged in");
}

$user_id = $_SESSION['user_id'];
$budget_month = $_POST['budget_month'] . "-01";
$total_amount = $_POST['total_amount'];

$category_names = $_POST['category_name'];
$allocated_amounts = $_POST['allocated_amount'];

mysqli_begin_transaction($conn);

try {
    // Close previous budgets
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE budgets SET status='closed' WHERE user_id=? AND status='active'"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    // Insert new budget
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO budgets (user_id, budget_month, total_amount, status)
         VALUES (?, ?, ?, 'active')"
    );
    mysqli_stmt_bind_param($stmt, "isd", $user_id, $budget_month, $total_amount);
    mysqli_stmt_execute($stmt);

    $budget_id = mysqli_insert_id($conn);

    // Handle categories
    for ($i = 0; $i < count($category_names); $i++) {
        // Insert category if not exists
        $stmt = mysqli_prepare(
            $conn,
            "INSERT IGNORE INTO categories (name, user_id) VALUES (?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "si", $category_names[$i], $user_id);
        mysqli_stmt_execute($stmt);

        // Get category ID
        $stmt = mysqli_prepare(
            $conn,
            "SELECT category_id FROM categories WHERE name=? AND user_id=?"
        );
        mysqli_stmt_bind_param($stmt, "si", $category_names[$i], $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $category_id);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        // Insert allocation
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO budget_categories (budget_id, category_id, allocated_amount)
             VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "iid",
            $budget_id,
            $category_id,
            $allocated_amounts[$i]
        );
        mysqli_stmt_execute($stmt);
    }

    mysqli_commit($conn);
    header("Location: ../public/dashboard.html");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "Error saving budget: " . $e->getMessage();
}
