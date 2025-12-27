<?php
session_start();
require 'db.php';

$user_id = $_SESSION['user_id'];

$sql = "
SELECT 
    bc.category_id,
    c.name,
    (bc.allocated_amount - bc.spent_amount) AS remaining
FROM budgets b
JOIN budget_categories bc ON b.budget_id = bc.budget_id
JOIN categories c ON bc.category_id = c.category_id
WHERE b.user_id = ? AND b.status = 'active'
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

header("Content-Type: application/json");
echo json_encode($data);
