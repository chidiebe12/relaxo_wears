<?php
session_start(); // This must come first

// Ensure database connection is loaded
include_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: buyer_login.php");
    exit();
}

// Check if DB connection exists
if (!isset($conn)) {
    die("Database connection failed.");
}

if (!isset($_GET['id'])) {
    echo "Invalid notification.";
    exit();
}

$buyer_id = $_SESSION['user_id'];
$notif_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT message, created_at, order_id FROM buyer_notifications WHERE id = ? AND buyer_id = ?");
$stmt->bind_param("ii", $notif_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Notification not found.";
    exit();
}
$row = $result->fetch_assoc();
$stmt->close();

$msg = htmlspecialchars($row['message']);
$created = date("F j, Y, g:i a", strtotime($row['created_at']));
$order_id = $row['order_id']; // Use direct DB reference

// Highlight delivery location
if (stripos($msg, 'location:') !== false) {
    $msg = preg_replace('/location:\s*([^\n]+)/i', 'Delivery Location: <strong>$1</strong>', $msg);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notification Detail - Relaxo Wears</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9;
        }
        .container {
            max-width: 700px;
            margin: 40px auto;
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
        .message-box {
            background: #eaf5ea;
            border-left: 6px solid #228b22;
            padding: 20px;
            border-radius: 8px;
            font-size: 16px;
        }
        .date {
            font-size: 13px;
            color: #555;
            margin-top: 10px;
        }
        .track-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 18px;
            background: #006400;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 15px;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #006400;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📬 Notification Details</h2>
    <div class="message-box">
        <?= nl2br($msg) ?>
        <div class="date">🕒 <?= $created ?></div>
    </div>

    <?php if (!empty($order_id)): ?>
        <a class="track-btn" href="buyer_track_order.php?order_id=<?= $order_id ?>" target="_blank">📍 Track Your Order</a>
    <?php endif; ?>

    <a class="back-link" href="buyer_notifications.php">← Back to Notifications</a>
</div>
</body>
</html>
