<?php
session_start();
include '../includes/db.php';

// Ensure session and role
if (!isset($_SESSION["user_id"])) {
    header("Location: vendor_login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'vendor') {
    header("Location: vendor_login.php");
    exit();
}

$vendor_id = $_SESSION["user_id"];

// Fetch notifications
$stmt = $conn->prepare("SELECT message, created_at FROM vendor_notifications WHERE vendor_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vendor Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2d6a4f;
            margin-bottom: 20px;
        }
        .note {
            border-left: 5px solid #2d6a4f;
            background: #e9f5ec;
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 6px;
            word-wrap: break-word;
        }
        .time {
            font-size: 12px;
            color: gray;
            margin-top: 5px;
        }
        .track-link {
            display: inline-block;
            margin-top: 8px;
            font-size: 13px;
            color: #1d3557;
            text-decoration: underline;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📢 My Notifications</h2>
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="note">
                <?= nl2br(htmlspecialchars($row['message'])) ?>

                <!-- Optional tracking link if message contains it -->
                <?php
                if (strpos($row['message'], 'track_order.php') !== false) {
                    preg_match('/track_order\.php\?order_id=\d+/', $row['message'], $matches);
                    if (!empty($matches[0])) {
                        echo '<a class="track-link" href="' . $matches[0] . '">Track Order</a>';
                    }
                }
                ?>
                <div class="time"><?= date("F j, Y - g:i A", strtotime($row['created_at'])) ?></div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No notifications yet.</p>
    <?php endif; ?>
</div>
</body>
</html>
