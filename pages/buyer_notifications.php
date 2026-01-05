<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Updated query to include order_id
$stmt = $conn->prepare("SELECT id, message, order_id, created_at FROM buyer_notifications WHERE buyer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications - Relaxo Wears</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f3;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        h2 {
            color: #006400;
            text-align: center;
            margin-bottom: 25px;
        }
        .notification {
            background: #eaf5ea;
            border-left: 6px solid #228b22;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .notification:hover {
            background: #dff5dd;
        }
        .notification a {
            text-decoration: none;
            color: inherit;
        }
        .date {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        .track-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            font-size: 14px;
            background-color: #228b22;
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
        }
        .track-btn:hover {
            background-color: #196f1d;
        }
        .empty {
            text-align: center;
            color: #888;
            margin-top: 50px;
            font-size: 18px;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #006400;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>🔔 Your Notifications</h2>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()):
            $msgPreview = substr(strip_tags($row['message']), 0, 120) . (strlen($row['message']) > 120 ? '...' : '');
            $created = date("F j, Y, g:i a", strtotime($row['created_at']));
            $id = $row['id'];
            $orderId = $row['order_id'];
        ?>
            <div class="notification">
                <a href="buyer_notification_view.php?id=<?= $id ?>">
                    <?= htmlspecialchars($msgPreview) ?>
                    <div class="date">🕒 <?= $created ?></div>
                </a>
                <?php if (!empty($orderId)): ?>
                    <a class="track-btn" href="buyer_track_order.php?order_id=<?= $orderId ?>">Track Order</a>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty">You have no notifications yet.</div>
    <?php endif; ?>

    <a href="my_account.php" class="back-link">← Back to My Account</a>
</div>
</body>
</html>
