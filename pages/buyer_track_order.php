<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

if (!isset($_GET['order_id'])) {
    echo "❌ No order specified.";
    exit();
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, 
           p.name AS product_name, 
           v.name AS vendor_name,
           v.delivery_mode,
           d.address AS drop_address,
           d.latitude AS drop_lat,
           d.longitude AS drop_lng
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users v ON p.vendor_id = v.id
    LEFT JOIN drop_off_locations d ON o.drop_off_id = d.id
    WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "❌ Order not found or you don't have permission to view it.";
    exit();
}

// Map coordinates
$lat = !empty($order['drop_lat']) ? $order['drop_lat'] : 6.5244;
$lng = !empty($order['drop_lng']) ? $order['drop_lng'] : 3.3792;

// Pickup/delivery address
$pickup_address = !empty($order['drop_address'])
    ? $order['drop_address']
    : ($order['delivery_mode'] === 'home'
        ? "Home Delivery Address"
        : "Pickup Station / Platform Location"
    );

// ---- STATUS NORMALIZATION (THE REAL FIX) ----
$statusSteps = ['pending','approved','shipped','delivered'];

$currentStatus = strtolower(trim($order['status'] ?? 'pending'));

// completed = delivered (frontend only)
if ($currentStatus === 'completed') {
    $currentStatus = 'delivered';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Track Order #<?= $order_id ?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin:0; padding: 20px; }
.container { max-width: 900px; margin:auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
h2 { color: #1a4d2f; margin-bottom: 15px; text-align:center; }
.status-line { display: flex; justify-content: space-between; margin: 20px 0; position: relative; }
.status-step { flex:1; text-align:center; position: relative; }
.status-step:before { content: ''; position: absolute; top: 12px; left: 50%; width: 100%; height: 4px; background: #ddd; z-index: -1; transform: translateX(-50%); }
.status-step:first-child:before { left: 50%; width: 50%; }
.status-step:last-child:before { width: 50%; }
.step-circle { width: 25px; height: 25px; background: #ddd; border-radius: 50%; display: inline-block; line-height: 25px; color: white; font-weight: bold; }
.step-label { margin-top: 8px; font-size: 0.9em; }
.status-step.active .step-circle { background: #1a4d2f; }
.order-info { margin-bottom: 20px; }
.order-info p { margin: 6px 0; }
#map { height: 400px; border-radius: 10px; margin-top: 15px; border: 1px solid #ddd; }
a.back { display:inline-block; margin-top:20px; text-decoration:none; color:#1a4d2f; font-weight:bold; }
a.back:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="container">
<h2>📦 Track Order #<?= $order_id ?></h2>

<div class="status-line">
<?php foreach ($statusSteps as $step): 
    $active = array_search($step, $statusSteps) <= array_search($currentStatus, $statusSteps)
        ? 'active' : '';
?>
<div class="status-step <?= $active ?>">
    <div class="step-circle"><?= strtoupper(substr($step,0,1)) ?></div>
    <div class="step-label"><?= ucfirst($step) ?></div>
</div>
<?php endforeach; ?>
</div>

<div class="order-info">
<p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
<p><strong>Vendor:</strong> <?= htmlspecialchars($order['vendor_name']) ?></p>
<p><strong>Quantity:</strong> <?= (int)$order['quantity'] ?></p>
<p><strong>Total Amount:</strong> ₦<?= number_format($order['total_amount'],2) ?></p>
<p><strong>Pickup/Delivery Location:</strong> <?= htmlspecialchars($pickup_address) ?></p>
</div>

<div id="map"></div>
<a href="buyer_dashboard.php" class="back">← Back to Dashboard</a>
</div>

<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([<?= $lat ?>, <?= $lng ?>], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
L.marker([<?= $lat ?>, <?= $lng ?>]).addTo(map)
.bindPopup("📍 <?= addslashes($pickup_address) ?>")
.openPopup();
</script>
</body>
</html>
