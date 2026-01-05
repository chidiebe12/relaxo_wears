<?php
session_start();
include '../includes/db.php';
include '../includes/order_notification_function.php'; // Added notification function

if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== "vendor") {
    header("Location: vendor_login.php");
    exit();
}
$vendor_id = $_SESSION['user_id'];

// Handle delivery confirmation by vendor
if (isset($_GET['delivered'])) {
    $order_id = intval($_GET['delivered']);

    // Fetch order details
    $stmt = $conn->prepare("SELECT o.*, u.name AS buyer_name, u.email AS buyer_email FROM orders o 
        JOIN products p ON o.product_id = p.id 
        JOIN users u ON o.buyer_id = u.id 
        WHERE o.id = ? AND p.vendor_id = ? AND o.status = 'approved'");
    $stmt->bind_param("ii", $order_id, $vendor_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $delivery_mode = $conn->query("SELECT delivery_mode FROM users WHERE id = $vendor_id")->fetch_assoc()['delivery_mode'];
        
        // Get drop-off location if exists
        $location = 'Pickup location will be specified';
        if (!empty($order['drop_off_id'])) {
            $location_query = $conn->query("SELECT address FROM drop_off_locations WHERE id = " . $order['drop_off_id']);
            if ($location_query->num_rows > 0) {
                $location = $location_query->fetch_assoc()['address'];
            }
        }

        if ($delivery_mode === 'vendor') {
            // Update order status
            $conn->query("UPDATE orders SET status = 'completed' WHERE id = $order_id");

            // ============================================
            // 🎯 NOTIFY BUYER - 4 WAYS:
            // ============================================
            
            // 1. Send in-app notification to buyer
            $buyer_msg = "Your order #$order_id has been delivered by the vendor. Pickup location: $location.";
            send_buyer_notification($conn, $order['buyer_id'], $buyer_msg, $order_id);

            // 2. Send HTML email notification to buyer
            $to = $order['buyer_email'];
            $subject = "🎉 Your Order Has Been Delivered - Relaxo Wears";
            $body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; padding: 20px; }
                        .email-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                        .header { text-align: center; color: #1a4d2e; margin-bottom: 25px; }
                        .content { color: #333; line-height: 1.6; }
                        .location-box { background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #27ae60; }
                        .button { display: inline-block; background: #1a4d2e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 15px 0; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                            <h1>🎉 Order Delivered Successfully!</h1>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>{$order['buyer_name']}</strong>,</p>
                            <p>Great news! Your order <strong style='color: #1a4d2e;'>#$order_id</strong> has been delivered by the vendor.</p>
                            
                            <div class='location-box'>
                                <h3 style='margin-top: 0; color: #1a4d2e;'>📍 Pickup Location</h3>
                                <p style='margin: 10px 0; font-size: 16px;'><strong>$location</strong></p>
                                <p style='margin: 5px 0; color: #666;'>You can pick up your item at this location.</p>
                            </div>
                            
                            <p><strong>Delivery Details:</strong></p>
                            <ul style='padding-left: 20px;'>
                                <li>Order ID: #$order_id</li>
                                <li>Delivered by: Vendor Delivery</li>
                                <li>Status: ✅ Delivered</li>
                            </ul>
                            
                            <p style='margin-top: 25px;'>
                                <a href='http://yourwebsite.com/vendor_track_order.php?order_id=$order_id' class='button'>
                                    📍 Track Your Delivery
                                </a>
                            </p>
                            
                            <p>If you have any questions, please contact our support team.</p>
                        </div>
                        <div class='footer'>
                            <p>Thank you for shopping with <strong>Relaxo Wears</strong>!</p>
                            <p><small>This is an automated notification. Please do not reply to this email.</small></p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Relaxo Wears <noreply@relaxowears.com>" . "\r\n";
            @mail($to, $subject, $body, $headers);

            // 3. Log vendor notification
            $vendor_msg = "You delivered order #$order_id to buyer {$order['buyer_name']} at: $location.";
            $stmt = $conn->prepare("INSERT INTO vendor_notifications (vendor_id, message, order_id) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $vendor_id, $vendor_msg, $order_id);
            $stmt->execute();
            $stmt->close();

            // 4. Log admin notification
            $admin_msg = "Vendor #$vendor_id delivered order #$order_id to buyer #{$order['buyer_id']}. Location: $location";
            $stmt = $conn->prepare("INSERT INTO admin_notifications (message, order_id, vendor_id, buyer_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siii", $admin_msg, $order_id, $vendor_id, $order['buyer_id']);
            $stmt->execute();
            $stmt->close();

            echo "<script>
                alert('✅ Order #$order_id marked as delivered.\\n\\nBuyer has been notified via:\\n📧 Email to: {$order['buyer_email']}\\n📱 In-app notification');
                window.location.href='view_orders.php';
            </script>";
            exit();
        }
    }
}

// Handle drop-off form submission
if (isset($_POST['confirm_drop'], $_POST['drop_address'], $_POST['drop_lat'], $_POST['drop_lng'], $_POST['order_id'])) {
    $address = trim($_POST['drop_address']);
    $lat = floatval($_POST['drop_lat']);
    $lng = floatval($_POST['drop_lng']);
    $order_id = intval($_POST['order_id']);

    // Store drop-off location in drop_off_locations table
    $location_stmt = $conn->prepare("INSERT INTO drop_off_locations (name, address, latitude, longitude) VALUES (?, ?, ?, ?)");
    $location_name = "Drop-off for Order #" . $order_id;
    $location_stmt->bind_param("ssdd", $location_name, $address, $lat, $lng);
    $location_stmt->execute();
    $drop_off_id = $location_stmt->insert_id;
    $location_stmt->close();

    // Update order with drop_off_id
    $stmt = $conn->prepare("UPDATE orders SET drop_off_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $drop_off_id, $order_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('✅ Drop-off location saved. Ready for delivery.'); window.location.href='view_orders.php';</script>";
    exit();
}

$sql = "SELECT o.id AS order_id, o.status AS order_status, o.payment_status, o.quantity, o.total_amount, o.created_at, 
        p.name AS product_name, u.name AS buyer_name 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        JOIN users u ON o.buyer_id = u.id 
        WHERE p.vendor_id = ? 
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();

$delivery_mode = $conn->query("SELECT delivery_mode FROM users WHERE id = $vendor_id")->fetch_assoc()['delivery_mode'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Orders - Relaxo Wears</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f7f7f7; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 15px rgba(0,0,0,0.08); }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h2 { margin: 0; color: #2d6a4f; }
        .nav-links .button { background: #2d6a4f; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; }
        .nav-links .button:hover { background: #1b4332; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; min-width: 900px; }
        th, td { padding: 12px 16px; text-align: center; border-bottom: 1px solid #ccc; }
        th { background-color: #2d6a4f; color: white; position: sticky; top: 0; }
        tr:hover { background-color: #f1f1f1; }
        .btn { padding: 8px 14px; background: #1a4d2e; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; border: none; cursor: pointer; transition: 0.3s; }
        .btn:hover { background: #2e7d4c; transform: translateY(-2px); }
        .btn-deliver { background: #27ae60; font-weight: bold; }
        .btn-deliver:hover { background: #219653; }
        .btn-location { background: #3498db; }
        .btn-location:hover { background: #2980b9; }
        .btn-track { background: #9b59b6; }
        .btn-track:hover { background: #8e44ad; }
        .disabled { background: gray; color: white; padding: 6px 12px; border-radius: 4px; font-size: 14px; display: inline-block; cursor: not-allowed; }
        .table-wrapper { overflow-x: auto; }
        .error { color: red; text-align: center; }
        h3 { color: #1b4332; margin-top: 10px; }
        #map { height: 300px; margin-top: 15px; border-radius: 10px; border: 1px solid #ddd; }
        .location-form { background: #f9f9f9; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #2d6a4f; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .action-buttons { display: flex; flex-direction: column; gap: 5px; }
        input[type="text"] { padding: 8px 12px; width: 100%; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header-bar">
        <h2>📦 Vendor Orders - Relaxo Wears</h2>
        <div class="nav-links">
            <a href="vendor_dashboard.php" class="button">← Back to Dashboard</a>
        </div>
    </div>

    <h3>Your Product Orders</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Buyer</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total (₦)</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Ordered At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?= htmlspecialchars($row['order_id']) ?></strong></td>
                    <td><?= htmlspecialchars($row['buyer_name']) ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= (int)$row['quantity'] ?></td>
                    <td>₦<?= number_format($row['total_amount'], 2) ?></td>
                    <td>
                        <span style="color: <?= $row['payment_status'] === 'paid' ? '#27ae60' : '#e74c3c' ?>; font-weight: bold;">
                            <?= ucfirst(htmlspecialchars($row['payment_status'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $row['order_status'] ?>">
                            <?= ucfirst(htmlspecialchars($row['order_status'])) ?>
                        </span>
                    </td>
                    <td><?= date("M d, h:i A", strtotime($row['created_at'])) ?></td>
                    <td>
                        <?php if ($row['order_status'] === 'completed'): ?>
                            <div style="color: #27ae60; font-weight: bold; padding: 8px; background: #e8f5e9; border-radius: 6px;">
                                ✅ Delivered<br>
                                <small>Buyer notified</small>
                            </div>
                        <?php elseif ($row['order_status'] === 'approved' && $delivery_mode === 'vendor'): ?>
                            <div class="location-form">
                                <h4 style="margin-top: 0; margin-bottom: 10px; color: #2d6a4f;">🚚 Deliver This Order</h4>
                                
                                <form method="POST">
                                    <input type="text" name="drop_address" 
                                           placeholder="📍 Enter pickup location (where buyer collects)" 
                                           required 
                                           title="Enter the exact location where buyer should pick up">
                                    <input type="hidden" name="drop_lat" id="drop_lat_<?= $row['order_id'] ?>">
                                    <input type="hidden" name="drop_lng" id="drop_lng_<?= $row['order_id'] ?>">
                                    <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                    
                                    <div class="action-buttons">
                                        <button type="submit" name="confirm_drop" class="btn btn-location">
                                            📍 Save Location
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="action-buttons" style="margin-top: 10px;">
                                    <a href="view_orders.php?delivered=<?= $row['order_id'] ?>" 
                                       class="btn btn-deliver"
                                       onclick="return confirm('Mark order #<?= $row['order_id'] ?> as delivered?\\n\\nBuyer will receive:\\n📧 Email notification\\n📱 In-app notification\\n📍 Pickup location details')">
                                       ✅ Mark as Delivered
                                    </a>
                                    
                                    <a href="vendor_track_order.php?order_id=<?= $row['order_id'] ?>" 
                                       target="_blank" 
                                       class="btn btn-track">
                                       📱 Track Order
                                    </a>
                                </div>
                                
                                <small style="display: block; margin-top: 10px; color: #666; font-size: 12px;">
                                    <strong>Instructions:</strong> 
                                    1. Save drop-off location first<br>
                                    2. Click "Mark as Delivered" when buyer collects<br>
                                    3. Buyer will be notified automatically
                                </small>
                            </div>
                        <?php elseif ($row['order_status'] === 'approved' && $delivery_mode === 'platform'): ?>
                            <span class="disabled">Platform Handling Delivery</span>
                            <small style="display: block; margin-top: 5px; color: #666;">
                                Our team will deliver to buyer
                            </small>
                        <?php else: ?>
                            <span style="color: #95a5a6;">Waiting for approval</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="error">No orders found for your products.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div id="map"></div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var map = L.map('map').setView([6.5244, 3.3792], 12); // Default to Lagos
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    let marker;

    // When user types address, geocode it
    document.addEventListener('blur', function (e) {
        if (e.target.name === 'drop_address') {
            const addressInput = e.target;
            const location = addressInput.value;
            const orderId = addressInput.closest('form').querySelector('input[name="order_id"]').value;
            
            if (location.trim() === '') return;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lon = parseFloat(data[0].lon);
                        
                        // Set hidden inputs for this specific order
                        document.getElementById('drop_lat_' + orderId).value = lat;
                        document.getElementById('drop_lng_' + orderId).value = lon;
                        
                        // Update map
                        if (marker) map.removeLayer(marker);
                        marker = L.marker([lat, lon]).addTo(map)
                            .bindPopup("Drop-off Location for Order #" + orderId).openPopup();
                        map.setView([lat, lon], 15);
                        
                        console.log('Location found for order #' + orderId + ':', lat, lon);
                        
                        // Show success message
                        addressInput.style.borderColor = '#27ae60';
                        addressInput.style.backgroundColor = '#e8f5e9';
                    } else {
                        console.log('Location not found');
                        addressInput.style.borderColor = '#e74c3c';
                    }
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    addressInput.style.borderColor = '#e74c3c';
                });
        }
    });
});
</script>
</body>
</html>