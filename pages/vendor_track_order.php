<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header("Location: vendor_login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    echo "Order ID is missing.";
    exit();
}

$order_id = intval($_GET['order_id']);
$vendor_id = $_SESSION['user_id'];

// Fetch order
$stmt = $conn->prepare("
SELECT o.*, 
       u.name AS buyer_name, 
       u.delivery_address,
       d.address AS drop_address, 
       d.latitude AS drop_lat, 
       d.longitude AS drop_lng,
       p.name AS product_name
FROM orders o 
JOIN users u ON o.buyer_id = u.id
JOIN products p ON o.product_id = p.id
LEFT JOIN drop_off_locations d ON o.drop_off_id = d.id
WHERE o.id = ? AND p.vendor_id = ?
");
$stmt->bind_param("ii",$order_id,$vendor_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { echo "Order not found or no permission."; exit(); }

$dropLat = !empty($order['drop_lat'])?$order['drop_lat']:6.5244;
$dropLng = !empty($order['drop_lng'])?$order['drop_lng']:3.3792;

$statusSteps = ['pending','approved','shipped','delivered'];
// Normalize completed → shipped internally
$currentStatus = strtolower($order['status'] ?? 'pending');
if ($currentStatus === 'completed') {
    $currentStatus = 'delivered';
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Vendor Track Order #<?= $order_id ?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
body{font-family:sans-serif;margin:0;padding:20px;background:#f4f4f4;}
#map{height:50vh;width:100%;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,0.1);}
.info-box{background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 0 10px rgba(0,0,0,0.1);}
h2{color:#2d6a4f;text-align:center;margin-bottom:15px;}
.order-details{display:flex;justify-content:space-between;flex-wrap:wrap;gap:15px;margin:20px 0;}
.detail-card{background:#e8f5e9;padding:15px;border-radius:8px;flex:1;min-width:200px;}
.back-btn{display:inline-block;background:#2d6a4f;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin-top:20px;}
.back-btn:hover{background:#1b4332;}
.progress-container{display:flex;justify-content:space-between;margin:30px 0;}
.step{flex:1;text-align:center;position:relative;}
.step:before{content:'';position:absolute;top:12px;left:50%;width:100%;height:4px;background:#ddd;z-index:-1;transform:translateX(-50%);}
.step:first-child:before{left:50%;width:50%;}
.step:last-child:before{width:50%;}
.step-circle{width:25px;height:25px;background:#ddd;border-radius:50%;display:inline-block;line-height:25px;color:white;font-weight:bold;}
.step.active .step-circle{background:#2d6a4f;}
.step-label{margin-top:8px;font-size:0.9em;}
</style>
</head>
<body>
<div class="info-box">
<h2>📦 Tracking Order #<?= $order_id ?></h2>

<div class="progress-container">
<?php foreach($statusSteps as $step):
$active = array_search($step,$statusSteps) <= array_search($currentStatus,$statusSteps)?'active':'';
?>
<div class="step <?= $active ?>">
<div class="step-circle"><?= ucfirst(substr($step,0,1)) ?></div>
<div class="step-label"><?= ucfirst($step) ?></div>
</div>
<?php endforeach; ?>
</div>

<div class="order-details">
<div class="detail-card">
<h4>Buyer Information</h4>
<p><strong>Name:</strong> <?= htmlspecialchars($order['buyer_name']) ?></p>
<p><strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address'] ?? 'Not specified') ?></p>
</div>
<div class="detail-card">
<h4>Order Information</h4>
<p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
<p><strong>Status:</strong> <?= ucfirst($currentStatus) ?></p>
<p><strong>Delivery Mode:</strong> <?= ucfirst($order['delivery_mode'] ?? 'platform') ?></p>
</div>
<div class="detail-card">
<h4>Drop-off Location</h4>
<?php if(!empty($order['drop_address'])): ?>
<p><strong>Address:</strong> <?= htmlspecialchars($order['drop_address']) ?></p>
<p><strong>Coordinates:</strong> <?= round($dropLat,6) ?>, <?= round($dropLng,6) ?></p>
<?php else: ?>
<p>📍 No drop-off location set yet.</p>
<?php endif; ?>
</div>
</div>

<a href="view_orders.php" class="back-btn">← Back to Orders</a>
</div>

<h3 style="text-align:center;color:#2d6a4f;">📍 Delivery Location Map</h3>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([<?= $dropLat ?>,<?= $dropLng ?>],13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

<?php if(!empty($order['drop_address'])): ?>
L.marker([<?= $dropLat ?>,<?= $dropLng ?>]).addTo(map)
.bindPopup(`<strong>Drop-off Location</strong><br><?= addslashes($order['drop_address']) ?>`).openPopup();
<?php else: ?>
L.marker([<?= $dropLat ?>,<?= $dropLng ?>]).addTo(map)
.bindPopup(`<strong>Default Location (Lagos)</strong><br>Waiting for drop-off`).openPopup();
<?php endif; ?>

<?php if(!empty($order['delivery_address'])): ?>
L.marker([<?= $dropLat+0.01 ?>,<?= $dropLng+0.01 ?>],{
icon:L.icon({iconUrl:'https://cdn-icons-png.flaticon.com/512/1077/1077114.png',iconSize:[30,30]})
}).addTo(map).bindPopup(`<strong>Buyer's Area</strong><br><?= addslashes($order['delivery_address']) ?>`);
<?php endif; ?>
</script>
</body>
</html>
