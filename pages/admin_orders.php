<?php
session_start();
include '../includes/db.php';
include '../includes/mailer_config.php';
include '../includes/order_notification_function.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// ✅ Deliver with manual drop-off input (Vendor Delivery)
if (isset($_POST['deliver_with_dropoff'])) {
    $order_id = intval($_POST['order_id']);
    $drop_off_name = trim($_POST['drop_off_location']);

    // Create drop-off location WITH DEFAULT COORDINATES
    $location_stmt = $conn->prepare("INSERT INTO drop_off_locations (name, address, latitude, longitude) VALUES (?, ?, 6.5244, 3.3792)");
    $location_name = "Pickup for Order #" . $order_id;
    $location_stmt->bind_param("ss", $location_name, $drop_off_name);
    $location_stmt->execute();
    $drop_off_id = $location_stmt->insert_id;
    $location_stmt->close();

    // Update order status
    $update = $conn->prepare("UPDATE orders SET status = 'completed', drop_off_id = ? WHERE id = ? AND status = 'approved'");
    $update->bind_param("ii", $drop_off_id, $order_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        // Get buyer info
        $stmt = $conn->prepare("SELECT o.buyer_id, u.email, u.name FROM orders o JOIN users u ON o.buyer_id = u.id WHERE o.id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->bind_result($buyer_id, $buyer_email, $buyer_name);
        $stmt->fetch();
        $stmt->close();

        // ============================================
        // 🎯 NOTIFY BUYER - 3 WAYS:
        // ============================================
        
        // 1. Send to buyer_notifications table
        if (function_exists('send_buyer_notification')) {
            include_once '../includes/order_notification_function.php';
            $notification_msg = "Your order #$order_id has been delivered. Pickup location: $drop_off_name.";
            send_buyer_notification($conn, $buyer_id, $notification_msg, $order_id);
        } else {
            // Fallback: Insert directly
            $conn->query("INSERT INTO buyer_notifications (buyer_id, message, order_id) VALUES ($buyer_id, 'Your order #$order_id has been delivered. Pickup location: $drop_off_name.', $order_id)");
        }

        // 2. Send Email
        $subject = "🎉 Your Order Has Been Delivered - Relaxo Wears";
        $body = "
            <h2>Order Delivered Successfully!</h2>
            <p>Dear $buyer_name,</p>
            <p>Great news! Your order <strong>#$order_id</strong> has been delivered.</p>
            <p><strong>📦 Pickup Location:</strong> $drop_off_name</p>
            <p>You can pick up your item at the above location.</p>
            <p>Thank you for shopping with Relaxo Wears!</p>
            <p><a href='http://yourwebsite.com/buyer_dashboard.php' style='color:#1a4d2e; font-weight:bold;'>View Order Details →</a></p>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Relaxo Wears <noreply@relaxowears.com>" . "\r\n";
        
        @mail($buyer_email, $subject, $body, $headers);

        // 3. Add admin notification
        $admin_msg = "Order #$order_id delivered. Pickup: $drop_off_name";
        $conn->query("INSERT INTO admin_notifications (message, order_id) VALUES ('$admin_msg', $order_id)");

        $success = "✅ Order #$order_id delivered. Buyer notified via email & notifications.";
    } else {
        $error = "⚠️ Delivery update failed. Order may not be approved.";
    }
}

// ✅ Deliver (Platform Delivery)
if (isset($_GET['deliver'])) {
    $order_id = intval($_GET['deliver']);

    $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Get buyer info
        $stmt = $conn->prepare("SELECT o.buyer_id, u.email, u.name, o.delivery_mode FROM orders o JOIN users u ON o.buyer_id = u.id WHERE o.id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->bind_result($buyer_id, $buyer_email, $buyer_name, $delivery_mode);
        $stmt->fetch();
        $stmt->close();

        // ============================================
        // 🎯 NOTIFY BUYER - 3 WAYS:
        // ============================================
        
        // 1. Send to buyer_notifications table
        if (function_exists('send_buyer_notification')) {
            include_once '../includes/order_notification_function.php';
            $notification_msg = ($delivery_mode === 'home') 
                ? "Your order #$order_id has been delivered to your address." 
                : "Your order #$order_id has been delivered to the pickup station.";
            send_buyer_notification($conn, $buyer_id, $notification_msg, $order_id);
        } else {
            // Fallback
            $conn->query("INSERT INTO buyer_notifications (buyer_id, message, order_id) VALUES ($buyer_id, 'Your order #$order_id has been delivered.', $order_id)");
        }

        // 2. Send Email
        $subject = "🎉 Your Order Has Been Delivered - Relaxo Wears";
        $body = "
            <h2>Order Delivered Successfully!</h2>
            <p>Dear $buyer_name,</p>
            <p>Great news! Your order <strong>#$order_id</strong> has been delivered.</p>
            <p>Delivery Type: <strong>" . ucfirst($delivery_mode) . " Delivery</strong></p>
            <p>Thank you for shopping with Relaxo Wears!</p>
            <p><a href='http://yourwebsite.com/buyer_dashboard.php' style='color:#1a4d2e; font-weight:bold;'>View Order Details →</a></p>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Relaxo Wears <noreply@relaxowears.com>" . "\r\n";
        
        @mail($buyer_email, $subject, $body, $headers);

        // 3. Add admin notification
        $admin_msg = "Platform delivered order #$order_id to buyer.";
        $conn->query("INSERT INTO admin_notifications (message, order_id) VALUES ('$admin_msg', $order_id)");

        $success = "✅ Order #$order_id delivered. Buyer notified via email & notifications.";
    } else {
        $error = "⚠️ Order not eligible for delivery update.";
    }
}

// ✅ Approve order
if (isset($_GET['approve'])) {
    $order_id = intval($_GET['approve']);
    
    $stmt = $conn->prepare("
        SELECT o.*, p.quantity AS product_stock, p.vendor_id, 
               p.base_price, u.delivery_mode, u.subscription_plan_id
        FROM orders o 
        JOIN products p ON o.product_id = p.id
        JOIN users u ON p.vendor_id = u.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if ($order && $order['status'] === 'pending') {
        if ($order['product_stock'] >= $order['quantity']) {
            // Calculate commission
            include '../includes/festive_functions.php';
            $commission_data = calculateCommission(
                $order['vendor_id'], 
                $order['base_price'], 
                $order['quantity'], 
                $conn
            );
            
            // Update stock
            $new_quantity = $order['product_stock'] - $order['quantity'];
            $update_product = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
            $update_product->bind_param("ii", $new_quantity, $order['product_id']);
            $update_product->execute();
            $update_product->close();

            // Update order with commission
            $update_order = $conn->prepare("
                UPDATE orders 
                SET status = 'approved', 
                    vendor_earnings = ?, 
                    commission = ?,
                    commission_rate = ?
                WHERE id = ?
            ");
            $update_order->bind_param(
                "dddi", 
                $commission_data['vendor_earnings'],
                $commission_data['amount'],
                $commission_data['rate'],
                $order_id
            );
            $update_order->execute();
            $update_order->close();

            $success = "✅ Order #$order_id approved. Commission: " . $commission_data['rate'] . "%";
        } else {
            $error = "❌ Insufficient stock to approve order.";
        }
    } else {
        $error = "❌ Invalid order or already processed.";
    }
}

// ✅ Fetch orders
$sql = "
    SELECT 
        o.*, 
        u.name AS buyer_name, 
        u.email AS buyer_email, 
        p.name AS product_name, 
        p.vendor_id, 
        p.base_price,
        v.name AS vendor_name, 
        v.paypal_email, 
        v.delivery_mode, 
        vsp.name as plan_name
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    JOIN products p ON o.product_id = p.id
    JOIN users v ON p.vendor_id = v.id
    LEFT JOIN vendor_subscription_plans vsp ON v.subscription_plan_id = vsp.id
    ORDER BY o.created_at DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Orders - Relaxo Wears</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 1300px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 0 15px rgba(0,0,0,0.08); }
        h2 { color: #1a4d2e; text-align: center; margin-bottom: 25px; }
        .table-wrapper { overflow-x: auto; margin-top: 20px; border-radius: 10px; border: 1px solid #ddd; }
        table { min-width: 1300px; width: 100%; border-collapse: collapse; }
        th { background-color: #1a4d2e; color: white; font-weight: 600; position: sticky; top: 0; }
        th, td { padding: 12px 10px; text-align: center; border: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f0f8f0; }
        .btn { padding: 8px 15px; background-color: #1a4d2e; color: white; border-radius: 6px; font-size: 14px; margin: 2px; text-decoration: none; display: inline-block; border: none; cursor: pointer; transition: 0.3s; }
        .btn:hover { background-color: #2e7d4c; transform: translateY(-2px); }
        .btn-deliver { background-color: #27ae60; font-weight: bold; }
        .btn-deliver:hover { background-color: #219653; }
        .btn-payout { background-color: #3498db; }
        .btn-payout:hover { background-color: #2980b9; }
        .btn-track { background-color: #9b59b6; }
        .btn-track:hover { background-color: #8e44ad; }
        .back-link { display: inline-block; margin-top: 20px; text-decoration: none; color: #1a4d2e; font-weight: bold; padding: 10px 20px; border: 2px solid #1a4d2e; border-radius: 6px; }
        .back-link:hover { background: #1a4d2e; color: white; }
        .msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .success { background: #e8f5e9; color: #27ae60; border-left: 5px solid #27ae60; }
        .error { background: #ffebee; color: #e74c3c; border-left: 5px solid #e74c3c; }
        input[type="text"] { padding: 8px 12px; width: 200px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; margin-right: 8px; }
        .delivery-box { background: #f8f9fa; padding: 10px; border-radius: 6px; margin: 5px 0; }
        .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
<div class="container">
    <h2>🗂 Admin Order Management</h2>

    <?php if (isset($success)): ?>
        <div class='msg success'>✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class='msg error'>⚠️ <?= $error ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Buyer</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Commission</th>
                <th>Vendor Earnings</th>
                <th>Vendor</th>
                <th>Plan</th>
                <th>Delivery Mode</th>
                <th>Status</th>
                <th>Actions</th>
                <th>🎯 Mark as Delivered</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><strong>#<?= $row['id'] ?></strong></td>
                <td><?= htmlspecialchars($row['buyer_name']) ?><br><small><?= htmlspecialchars($row['buyer_email']) ?></small></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td>₦<?= number_format($row['total_amount'], 2) ?></td>
                <td>
                    <strong>₦<?= number_format($row['commission'], 2) ?></strong><br>
                    <small><?= number_format($row['commission_rate'] ?? 0, 1) ?>%</small>
                </td>
                <td><strong>₦<?= number_format($row['vendor_earnings'], 2) ?></strong></td>
                <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                <td><?= htmlspecialchars($row['plan_name'] ?? 'Basic') ?></td>
                <td>
                    <div class="delivery-box">
                        <strong><?= ucfirst($row['delivery_mode']) ?></strong><br>
                        <small><?= ($row['delivery_mode'] == 'vendor') ? 'Vendor delivers' : 'Platform delivers' ?></small>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-<?= $row['status'] ?>">
                        <?= ucfirst($row['status']) ?>
                    </span>
                    <?php if($row['payout_status'] === 'paid'): ?>
                        <br><small style="color: #27ae60;">Paid ✅</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <a class="btn" href="?approve=<?= $row['id'] ?>" 
                           onclick="return confirm('Approve order #<?= $row['id'] ?>?')">
                           ✅ Approve
                        </a>
                    <?php elseif ($row['status'] === 'approved' && $row['payout_status'] === 'pending'): ?>
                        <a class="btn btn-payout" 
                           href="send_payout.php?order_id=<?= $row['id'] ?>" 
                           onclick="return confirm('Send payout for order #<?= $row['id'] ?>?')">
                           💰 Send Payout
                        </a>
                    <?php endif; ?>
                    <br>
                    <a class="btn btn-track" 
                       href="admin_track_order.php?order_id=<?= $row['id'] ?>" 
                       target="_blank">
                       📍 Track Order
                    </a>
                </td>
                <td>
                    <?php if ($row['status'] === 'completed'): ?>
                        <div style="color: #27ae60; font-weight: bold; padding: 8px; background: #e8f5e9; border-radius: 6px;">
                            ✅ Delivered<br>
                            <small>Buyer notified</small>
                        </div>
                    <?php elseif ($row['status'] === 'approved'): ?>
                        <div style="text-align: center;">
                            <?php if ($row['delivery_mode'] === 'platform'): ?>
                                <!-- Platform Delivery - Simple button -->
                                <a class="btn btn-deliver" 
                                   href="?deliver=<?= $row['id'] ?>" 
                                   onclick="return confirm('Mark order #<?= $row['id'] ?> as delivered?\n\nBuyer will be notified immediately.')">
                                   🚚 Mark Delivered
                                </a>
                                <small style="display:block; margin-top:5px; color:#666;">
                                    Platform handles delivery
                                </small>
                            <?php else: ?>
                                <!-- Vendor Delivery - With location input -->
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                    <input type="text" name="drop_off_location" 
                                           placeholder="📍 Enter pickup location" 
                                           required
                                           style="width: 90%; margin-bottom: 8px;"
                                           title="Where should the buyer pick up the item?">
                                    <button class="btn btn-deliver" 
                                            type="submit" 
                                            name="deliver_with_dropoff"
                                            onclick="return confirm('Mark order #<?= $row['id'] ?> as delivered with this pickup location?\n\nBuyer will be notified immediately.')">
                                        📦 Mark as Delivered
                                    </button>
                                    <small style="display:block; margin-top:5px; color:#666;">
                                        Vendor delivery - enter pickup spot
                                    </small>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span style="color: #95a5a6;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="13" style="text-align: center; padding: 40px; color: #666;">
                    No orders available.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    
    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<script>
// Confirmation for all actions
document.addEventListener('DOMContentLoaded', function() {
    // Delivery confirmation
    const deliverButtons = document.querySelectorAll('a[href*="deliver="], button[name="deliver_with_dropoff"]');
    deliverButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const orderId = this.closest('tr').querySelector('td:first-child strong').textContent.replace('#', '');
            const buyerName = this.closest('tr').querySelector('td:nth-child(2)').textContent.split('\n')[0];
            
            if (!confirm(`🚚 Mark order #${orderId} as delivered?\n\nBuyer: ${buyerName}\n\nBuyer will receive:\n✅ Email notification\n✅ In-app notification\n✅ Delivery confirmation`)) {
                e.preventDefault();
            }
        });
    });
    
    // Approve confirmation
    const approveButtons = document.querySelectorAll('a[href*="approve="]');
    approveButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const orderId = this.closest('tr').querySelector('td:first-child strong').textContent.replace('#', '');
            if (!confirm(`✅ Approve order #${orderId}?\n\nThis will:\n• Deduct product stock\n• Calculate commission\n• Make order ready for delivery`)) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>