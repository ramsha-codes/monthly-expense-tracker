<?php
// Prevent HTML error output from corrupting JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');
$mode  = $_GET['mode'] ?? 'paginate'; // 'paginate' (default) or 'chart' (all data)

// Validate Month
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid month format"]);
    exit;
}

$startDate = $month . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

// =========================================================
// MODE 1: CHART DATA (Simple list of all expenses for graphs)
// =========================================================
if ($mode === 'chart') {
    $sql = "
        SELECT COALESCE(c.name, 'Uncategorized') as category, e.amount, e.expense_date
        FROM expenses e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.user_id = ? 
        AND e.expense_date BETWEEN ? AND ?
        ORDER BY e.expense_date ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

// =========================================================
// MODE 2: PAGINATED HISTORY (For tables)
// =========================================================
$page  = max(1, (int)($_GET['page'] ?? 1));
$sortBy = $_GET['sortBy'] ?? 'expense_date';
$sortDir = $_GET['sortDir'] ?? 'DESC';

// Whitelist sort columns
$allowedSorts = ['expense_date', 'amount'];
if (!in_array($sortBy, $allowedSorts)) $sortBy = 'expense_date';
if ($sortDir !== 'ASC') $sortDir = 'DESC';

$pageSize = 10; // Fixed page size
$offset = ($page - 1) * $pageSize;

// 1. Count Total
$countSql = "SELECT COUNT(*) AS total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $startDate, $endDate);
mysqli_stmt_execute($stmt);
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
$totalPages = (int)ceil($total / $pageSize);

// 2. Fetch Data
$sql = "
    SELECT 
        e.expense_id,
        e.expense_date,
        c.name AS category,
        e.amount,
        e.description,
        bc.allocated_amount,
        (
            SELECT COALESCE(SUM(e2.amount), 0)
            FROM expenses e2
            WHERE e2.category_id = e.category_id
              AND e2.budget_id = e.budget_id
              AND e2.expense_date <= e.expense_date
        ) AS cumulative_spent
    FROM expenses e
    JOIN categories c ON c.category_id = e.category_id
    LEFT JOIN budget_categories bc ON bc.category_id = e.category_id AND bc.budget_id = e.budget_id
    WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
    ORDER BY $sortBy $sortDir
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "issii", $user_id, $startDate, $endDate, $pageSize, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$expenses = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Check overspending
    if ($row['allocated_amount'] !== null) {
        $row['is_overspent'] = ((float)$row['cumulative_spent'] > (float)$row['allocated_amount']);
    } else {
        $row['is_overspent'] = false;
    }
    unset($row['allocated_amount'], $row['cumulative_spent']);
    $expenses[] = $row;
}

echo json_encode([
    "expenses"   => $expenses,
    "page"       => $page,
    "total"      => $total,
    "totalPages" => $totalPages
]);
?>