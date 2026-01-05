<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];
$buyer_email = "";
$phone_number = "";
$message = "";

// Fetch buyer info
$stmt = $conn->prepare("SELECT email, phone_number FROM users WHERE id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$stmt->bind_result($buyer_email, $phone_number);
$stmt->fetch();
$stmt->close();

// Handle email update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $new_email = trim($_POST['new_email']);
    
    if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $new_email, $buyer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Update email
            $update_stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_email, $buyer_id);
            
            if ($update_stmt->execute()) {
                $buyer_email = $new_email;
                $message = "<div style='color: green; padding: 10px; background: #e8f5e9; border-radius: 5px;'>✅ Email updated successfully! Notifications will be sent to this address.</div>";
            } else {
                $message = "<div style='color: red; padding: 10px; background: #ffebee; border-radius: 5px;'>❌ Error updating email.</div>";
            }
            $update_stmt->close();
        } else {
            $message = "<div style='color: red; padding: 10px; background: #ffebee; border-radius: 5px;'>❌ This email is already registered by another user.</div>";
        }
        $check_stmt->close();
    } else {
        $message = "<div style='color: red; padding: 10px; background: #ffebee; border-radius: 5px;'>❌ Please enter a valid email address.</div>";
    }
}

// Fetch notifications
$notifications = [];
$stmt = $conn->prepare("SELECT message, created_at FROM buyer_notifications WHERE buyer_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Account - Relaxo Wears</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f0f0;
            padding: 0;
            margin: 0;
        }
        .container {
            max-width: 750px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            color: #1a4d2e;
            margin-bottom: 25px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #1a4d2e;
        }
        .info-box h3 {
            margin-top: 0;
            color: #1a4d2e;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin: 12px 0;
            background: #f8f8f8;
            padding: 15px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        li:hover {
            background: #e8f5e9;
            transform: translateX(5px);
        }
        a {
            text-decoration: none;
            color: #1a4d2e;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        a:before {
            content: "→";
            margin-right: 10px;
            color: #27ae60;
        }
        .notification {
            margin: 10px 0;
            background: #f4fff4;
            border-left: 4px solid #27ae60;
            padding: 12px 15px;
            border-radius: 6px;
        }
        .notification time {
            font-size: 12px;
            color: #666;
            float: right;
        }
        .email-form {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .email-form input[type="email"] {
            padding: 10px;
            width: 300px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
        }
        .email-form button {
            background: #1a4d2e;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .email-form button:hover {
            background: #2e7d4c;
        }
        .contact-info {
            display: flex;
            gap: 20px;
            margin: 15px 0;
        }
        .contact-item {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            flex: 1;
        }
        .contact-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .contact-value {
            font-weight: bold;
            color: #1a4d2e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>👤 My Account Dashboard</h2>
        
        <?php echo $message; ?>
        
        <div class="info-box">
            <h3>📧 Contact Information</h3>
            
            <div class="contact-info">
                <div class="contact-item">
                    <div class="contact-label">Email Address</div>
                    <div class="contact-value"><?= htmlspecialchars($buyer_email) ?></div>
                    <small style="color: #666;">Order notifications sent here</small>
                </div>
                
                <?php if(!empty($phone_number)): ?>
                <div class="contact-item">
                    <div class="contact-label">Phone Number</div>
                    <div class="contact-value"><?= htmlspecialchars($phone_number) ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="email-form">
                <h4 style="margin-top: 0;">Update Notification Email</h4>
                <form method="POST">
                    <input type="email" name="new_email" 
                           value="<?= htmlspecialchars($buyer_email) ?>" 
                           placeholder="Enter new email address" 
                           required>
                    <button type="submit" name="update_email">Update Email</button>
                </form>
                <small style="color: #666; display: block; margin-top: 10px;">
                    All order updates and delivery notifications will be sent to this email.
                </small>
            </div>
        </div>

        <div class="info-box">
            <h3>🚀 Quick Actions</h3>
            <ul>
                <li><a href="address_book.php">📍 Manage Address Book</a></li>
                <li><a href="buyer_notifications.php">🔔 View All Notifications</a></li>
                <li><a href="order_history.php">📦 Order History</a></li>
                <li><a href="cart.php">🛒 Shopping Cart</a></li>
                <li><a href="buyer_dashboard.php">📊 Dashboard</a></li>
            </ul>
        </div>

        <div class="info-box">
            <h3>📢 Recent Notifications</h3>
            <?php if (count($notifications) === 0): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $note): ?>
                    <div class="notification">
                        <?= htmlspecialchars($note['message']) ?>
                        <time><?= date("M d, Y h:i A", strtotime($note['created_at'])) ?></time>
                    </div>
                <?php endforeach; ?>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="buyer_notifications.php" style="color: #1a4d2e; font-weight: bold;">
                        View all notifications →
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>