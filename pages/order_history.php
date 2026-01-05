<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT o.*, p.name AS product_name, p.price AS product_price
                        FROM orders o
                        JOIN products p ON o.product_id = p.id
                        WHERE o.buyer_id = ?
                        ORDER BY o.created_at DESC");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order History - Relaxo Wears</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="header-bar">
        <h2>Relaxo Wears - Order History</h2>
        <div class="nav-links">
            <button class="nav-btn" onclick="toggleDropdown()">Menu</button>
            <div class="nav-dropdown" id="dropdownMenu">
                <a href="buyer_dashboard.php">Dashboard</a>
                <a href="my_account.php">My Account</a>
                <a href="?logout=true" style="color: red;">Logout</a>
            </div>
        </div>
    </div>

    <h3>Your Orders</h3>
    <table>
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Total (₦)</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td>₦<?= number_format($row['total_amount'], 2) ?></td>
                    <td><?= ucfirst($row['status']) ?></td>
                    <td><?= date("F j, Y H:i", strtotime($row['created_at'])) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">You have not placed any orders yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

<script>
function toggleDropdown() {
    const menu = document.getElementById("dropdownMenu");
    menu.style.display = menu.style.display === "block" ? "none" : "block";
}
</script>
</body>
</html>
