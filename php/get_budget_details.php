<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? null; // Optional: Fetch specific month (YYYY-MM)

// 1. Determine Query based on params
if ($month) {
    // Fetch budget for specific month (Active or Closed)
    $month_date = $month . '-01';
    $sql = "SELECT budget_id, budget_month, total_amount FROM budgets WHERE user_id = ? AND budget_month = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $month_date);
} else {
    // Default: Fetch current ACTIVE budget
    $sql = "SELECT budget_id, budget_month, total_amount FROM budgets WHERE user_id = ? AND status = 'active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$budget = mysqli_fetch_assoc($result);

if ($budget) {
    // Found a budget -> Get Allocations
    $sql_cats = "
        SELECT c.name, bc.allocated_amount 
        FROM budget_categories bc
        JOIN categories c ON bc.category_id = c.category_id
        WHERE bc.budget_id = ?
    ";
    $stmt_cats = mysqli_prepare($conn, $sql_cats);
    mysqli_stmt_bind_param($stmt_cats, "i", $budget['budget_id']);
    mysqli_stmt_execute($stmt_cats);
    $res_cats = mysqli_stmt_get_result($stmt_cats);

    $categories = [];
    while ($row = mysqli_fetch_assoc($res_cats)) {
        $categories[] = $row;
    }
    
    echo json_encode([
        'found' => true,
        'budget' => $budget,
        'categories' => $categories
    ]);

} else {
    // No budget found (either no active one, or none for that specific month)
    // Return list of all categories for setup/reference
    $sql_all_cats = "SELECT name FROM categories WHERE user_id = ? ORDER BY name ASC";
    $stmt_all = mysqli_prepare($conn, $sql_all_cats);
    mysqli_stmt_bind_param($stmt_all, "i", $user_id);
    mysqli_stmt_execute($stmt_all);
    $res_all = mysqli_stmt_get_result($stmt_all);

    $categories = [];
    while ($row = mysqli_fetch_assoc($res_all)) {
        $categories[] = [
            'name' => $row['name'], 
            'allocated_amount' => 0
        ];
    }

    echo json_encode([
        'found' => false,
        'budget' => null,
        'categories' => $categories
    ]);
}
?>