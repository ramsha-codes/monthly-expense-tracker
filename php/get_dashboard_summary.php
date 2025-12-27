<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "
SELECT 
    b.total_amount AS total_budget,
    COALESCE(SUM(e.amount), 0) AS total_spent
FROM budgets b
LEFT JOIN expenses e ON b.budget_id = e.budget_id
WHERE b.user_id = ? AND b.status = 'active'
GROUP BY b.budget_id
LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo json_encode([
        "total_budget" => 0,
        "total_spent" => 0,
        "remaining" => 0
    ]);
    exit;
}

$total_budget = (float)$data['total_budget'];
$total_spent  = (float)$data['total_spent'];

echo json_encode([
    "total_budget" => $total_budget,
    "total_spent"  => $total_spent,
    "remaining"    => $total_budget - $total_spent
]);
