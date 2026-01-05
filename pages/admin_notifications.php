<?php
session_start();
include '../includes/db.php';

// Use same check as admin dashboard
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit();
}

// Mark all notifications as read
$conn->query("UPDATE admin_notifications SET is_read = 1");

// Fetch notifications
$result = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Notifications - Relaxo Wears</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f9f9f9;
            padding: 30px;
        }
        .container {
            max-width: 850px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 12px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            color: #004d00;
            margin-bottom: 25px;
        }
        .notification {
            border-left: 5px solid #006400;
            background: #f0fdf4;
            padding: 15px 18px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .notification .highlight {
            background-color: #d4edda;
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        .track-button {
            margin-top: 10px;
            display: inline-block;
            padding: 8px 14px;
            background-color: #228b22;
            color: white;
            text-decoration: none;
            font-size: 13px;
            border-radius: 5px;
        }
        .track-button:hover {
            background-color: #1e7e1e;
        }
        .time {
            font-size: 13px;
            color: gray;
            margin-top: 5px;
        }
        .empty {
            text-align: center;
            color: #aaa;
            margin-top: 50px;
            font-size: 16px;
        }
        .back-link {
            display: inline-block;
            margin-top: 25px;
            text-decoration: none;
            color: #006400;
            font-weight: bold;
        }
        .order-id {
            background: #004d00;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📢 Admin Notifications</h2>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()):
            $message = htmlspecialchars($row['message']);
            $order_id = $row['order_id'] ?? 0;
            
            // Try to extract order ID from message if not in database
            if ($order_id == 0 && preg_match('/order #(\d+)/i', $message, $match)) {
                $order_id = $match[1];
            }

            // Highlight address/location if it exists
            if (preg_match('/location: (.*?)$/i', $message, $match)) {
                $location = $match[1];
                $highlighted = "<span class='highlight'>Location: " . htmlspecialchars($location) . "</span>";
                $message = preg_replace('/location: .*?$/i', $highlighted, $message);
            }

            $showTracking = stripos($message, 'delivered') !== false || 
                           stripos($message, 'location') !== false || 
                           stripos($message, 'order') !== false;
        ?>
            <div class="notification">
                <?php if ($order_id > 0): ?>
                    <span class="order-id">Order #<?= $order_id ?></span>
                <?php endif; ?>
                <?= $message ?>
                <div class="time">🕒 <?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></div>
                <?php if ($showTracking && $order_id > 0): ?>
                    <a href="track_order_admin.php?order_id=<?= $order_id ?>" class="track-button">View Tracking</a>
                <?php elseif ($showTracking): ?>
                    <span class="track-button" style="background: #95a5a6; cursor: not-allowed;" title="Order ID not available">View Tracking</span>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="empty">No notifications available.</p>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back-link">⬅ Back to Dashboard</a>
</div>
</body>
</html>