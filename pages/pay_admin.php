<?php
session_start();
include '../includes/db.php';
include '../includes/mailer_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: buyer_login.php");
    exit();
}

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("Invalid order ID.");
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, p.name AS product_name, p.price, u.name AS buyer_name, u.email AS buyer_email 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN users u ON o.buyer_id = u.id 
    WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Order not found.");
}

$order = $result->fetch_assoc();

if ($order['payment_status'] === 'paid') {
    echo "<script>alert('This order is already paid.'); window.location.href='buyer_dashboard.php';</script>";
    exit();
}

$totalAmount = $order['total_amount'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pay for Order #<?= $order_id ?> - Relaxo Wears</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Pay for Order #<?= $order_id ?></h2>
    <p>Product: <strong><?= htmlspecialchars($order['product_name']) ?></strong></p>
    <p>Total Amount: <strong>₦<?= number_format($totalAmount, 2) ?></strong></p>

    <h3>Choose Payment Method:</h3>

    <!-- PayPal Button -->
    <form action="process_paypal_payment.php" method="POST">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <input type="hidden" name="amount" value="<?= $totalAmount ?>">
        <button type="submit" class="btn">Pay with PayPal</button>
    </form>

    <br>

    <!-- Paystack Button -->
    <form action="process_paystack_payment.php" method="POST">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <input type="hidden" name="email" value="<?= $order['buyer_email'] ?>">
        <input type="hidden" name="amount" value="<?= $totalAmount * 100 ?>"> <!-- Paystack accepts kobo -->
        <button type="submit" class="btn">Pay with ATM Card (Paystack)</button>
    </form>

    <br><br>
    <a href="buyer_dashboard.php">← Back to Dashboard</a>
</div>
</body>
</html>
