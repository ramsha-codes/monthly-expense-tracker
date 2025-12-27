<?php
session_start();
require 'db.php';

/**
 * 🔥 CRITICAL: Enable mysqli exceptions
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }

    $user_id     = $_SESSION['user_id'];
    $category_id = $_POST['category_id'] ?? null;
    $amount      = $_POST['amount'] ?? null;
    $description = $_POST['description'] ?? null;

    if (!$category_id || !$amount) {
        throw new Exception("Missing required fields");
    }

    /**
     * 1️⃣ Get active budget
     */
    $sql = "
        SELECT budget_id
        FROM budgets
        WHERE user_id = ? AND status = 'active'
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception("No active budget found");
    }

    $budget_id = $row['budget_id'];

    /**
     * 2️⃣ Insert expense
     * 🚨 Trigger may BLOCK here
     */
    $insert_sql = "
        INSERT INTO expenses (user_id, budget_id, category_id, amount, description)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iiids",
        $user_id,
        $budget_id,
        $category_id,
        $amount,
        $description
    );

    mysqli_stmt_execute($stmt);

    /**
     * 3️⃣ Success
     */
    header("Location: ../public/dashboard.html");
    exit;

}
catch (mysqli_sql_exception $e) {

    /**
     * 🔥 OVESPENDING (Trigger Error)
     * SQLSTATE 45000 → error code 1644
     */
    if ($e->getCode() == 1644) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Expense Not Allowed</title>
            <style>
                body { font-family: Arial; padding: 40px; }
                .box {
                    border: 1px solid #f44336;
                    padding: 20px;
                    max-width: 500px;
                }
                h2 { color: #f44336; }
                a { display: inline-block; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class="box">
                <h2>❌ Expense Denied</h2>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <a href="../public/add_expense.html">⬅ Go back</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * ❌ Any other DB error
     */
    http_response_code(500);
    echo "<h3>Database Error</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
catch (Exception $e) {
    http_response_code(400);
    echo "<h3>Error</h3>";
    echo htmlspecialchars($e->getMessage());
    exit;
}
