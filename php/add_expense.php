<?php
session_start();
require '../php/db.php';

if (!isset($_SESSION['user_id'])) {
    die("User not logged in");
}

$user_id = $_SESSION['user_id'];

/**
 * Get active budget
 */
$budget_sql = "
    SELECT b.budget_id, b.total_amount,
           COALESCE(SUM(e.amount), 0) AS spent
    FROM budgets b
    LEFT JOIN expenses e ON b.budget_id = e.budget_id
    WHERE b.user_id = ? AND b.status = 'active'
    GROUP BY b.budget_id
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $budget_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$budget = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$budget) {
    die("No active budget found");
}

$remaining = $budget['total_amount'] - $budget['spent'];

/**
 * Load categories for this user
 */
$cat_sql = "
    SELECT c.category_id, c.name,
           bc.allocated_amount,
           bc.spent_amount
    FROM categories c
    JOIN budget_categories bc ON bc.category_id = c.category_id
    WHERE bc.budget_id = ?
";
$stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($stmt, "i", $budget['budget_id']);
mysqli_stmt_execute($stmt);
$categories = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Expense</title>
</head>
<body>

<h2>Add Expense</h2>

<p><strong>Remaining Monthly Budget:</strong> <?= number_format($remaining, 2) ?></p>

<form action="../php/save_expense.php" method="POST">

    <label>Category:</label><br>
    <select name="category_id" required>
        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
            <option value="<?= $cat['category_id'] ?>">
                <?= htmlspecialchars($cat['name']) ?>
                (Remaining: <?= number_format($cat['allocated_amount'] - $cat['spent_amount'], 2) ?>)
            </option>
        <?php endwhile; ?>
    </select>
    <br><br>

    <label>Amount:</label><br>
    <input type="number" name="amount" step="0.01" required>
    <br><br>

    <label>Description (optional):</label><br>
    <input type="text" name="description">
    <br><br>

    <button type="submit">Add Expense</button>

</form>

</body>
</html>
