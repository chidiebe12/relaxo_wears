<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Fetch only completed and paid orders
$stmt = $conn->prepare("
    SELECT o.*, u.name AS buyer_name, p.name AS product_name, 
           v.name AS vendor_name, v.paypal_email
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    JOIN products p ON o.product_id = p.id
    JOIN users v ON p.vendor_id = v.id
    WHERE o.status = 'completed' AND o.payout_status = 'paid'
    ORDER BY o.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Relaxo Wears - Completed Orders</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f0f5f2;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }
        h1, h2 {
            text-align: center;
            color: #004d00;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }
        th {
            background-color: #006400;
            color: white;
        }
        .paid {
            color: green;
            font-weight: bold;
        }
        a.back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #004d00;
        }
        a.back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Relaxo Wears</h1>
    <h2>Completed Orders & Payouts</h2>

    <table>
        <tr>
            <th>Order ID</th>
            <th>Buyer</th>
            <th>Product</th>
            <th>Vendor</th>
            <th>Quantity</th>
            <th>Total (₦)</th>
            <th>Vendor Earnings (₦)</th>
            <th>Payout Status</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>" . htmlspecialchars($row['buyer_name']) . "</td>
                    <td>" . htmlspecialchars($row['product_name']) . "</td>
                    <td>" . htmlspecialchars($row['vendor_name']) . "</td>
                    <td>{$row['quantity']}</td>
                    <td>₦" . number_format($row['total_amount'], 2) . "</td>
                    <td>₦" . number_format($row['vendor_earnings'], 2) . "</td>
                    <td class='paid'>✅ Paid</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='8'>No completed & paid orders found.</td></tr>";
        }
        ?>
    </table>

    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
</div>
</body>
</html>
