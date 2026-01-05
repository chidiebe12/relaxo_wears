<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "vendor") {
    header("Location: vendor_login.php");
    exit();
}

$vendor_id = $_SESSION["user_id"];

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: vendor_login.php");
    exit();
}

// Handle delivery mode update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_mode'])) {
    $new_mode = $_POST['delivery_mode'] === 'self' ? 'self' : 'platform';
    $stmt = $conn->prepare("UPDATE users SET delivery_mode = ? WHERE id = ?");
    $stmt->bind_param("si", $new_mode, $vendor_id);
    $stmt->execute();
    $stmt->close();
}
// Handle delivery mode change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_mode'])) {
    $new_mode = $_POST['delivery_mode'];
    if (in_array($new_mode, ['vendor', 'platform'])) {
        $stmt = $conn->prepare("UPDATE users SET delivery_mode = ? WHERE id = ?");
        $stmt->bind_param("si", $new_mode, $vendor_id);
        $stmt->execute();
        $stmt->close();
        $delivery_mode = $new_mode;
    }
}

// Get current delivery mode
$stmt = $conn->prepare("SELECT delivery_mode FROM users WHERE id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$stmt->bind_result($delivery_mode);
$stmt->fetch();
$stmt->close();

// Fetch analytics
$orders_week = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders o JOIN products p ON o.product_id = p.id WHERE p.vendor_id = ? AND DATE(o.created_at) = ?");
    $stmt->bind_param("is", $vendor_id, $date);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $orders_week[] = ["date" => $date, "count" => $count];
    $stmt->close();
}

$earnings_week = [];
$total_earnings = 0;
$total_orders = 0;
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT SUM(o.vendor_earnings), COUNT(*) FROM orders o JOIN products p ON o.product_id = p.id WHERE p.vendor_id = ? AND DATE(o.created_at) = ?");
    $stmt->bind_param("is", $vendor_id, $date);
    $stmt->execute();
    $stmt->bind_result($day_earnings, $order_count);
    $stmt->fetch();
    $day_earnings = $day_earnings ?? 0;
    $earnings_week[] = ["date" => $date, "earnings" => $day_earnings];
    $total_earnings += $day_earnings;
    $total_orders += $order_count;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard - Relaxo Wears</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            background: #fff;
            padding: 25px 40px;
            border-radius: 12px;
            box-shadow: 0 0 15px #ccc;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #006400;
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .nav-links {
            position: relative;
        }
        .nav-btn {
            background: white;
            color: #006400;
            border: none;
            padding: 10px 18px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: background 0.3s;
        }
        .nav-btn:hover {
            background: #e0ffe0;
        }
        .nav-dropdown {
            position: absolute;
            top: 48px;
            right: 0;
            background: white;
            box-shadow: 0 0 10px #aaa;
            display: none;
            padding: 10px 0;
            border-radius: 6px;
            z-index: 100;
            min-width: 220px;
        }
        .nav-dropdown a {
            display: block;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
        }
        .nav-dropdown a:hover {
            background: #f0f0f0;
        }
        .summary-cards {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: #f9fff9;
            padding: 20px;
            border-radius: 8px;
            flex: 1;
            box-shadow: 0 0 10px #ddd;
            text-align: center;
        }
        .card h3 {
            color: #006400;
            font-size: 22px;
        }
        .card p {
            font-size: 20px;
            margin-top: 5px;
        }
        .chart-container {
            margin-top: 40px;
        }
        .delivery-toggle {
            margin: 30px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fefefe;
        }
        .delivery-toggle h4 {
            margin: 0 0 10px;
            color: #006400;
        }
        .delivery-toggle label {
            margin-right: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-bar">
        <h2>Vendor Dashboard - Relaxo Wears</h2>
        <div class="nav-links">
            <button class="nav-btn">Menu ☰</button>
            <div class="nav-dropdown">
                <a href="upload_products.php">📦 Upload Product</a>
                <a href="view_orders.php">📄 View Orders</a>
                <a href="vendor_notifications.php">🔔 Notifications</a>
                <a href="?logout=true" style="color: red;">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="summary-cards">
        <div class="card">
            <h3>Total Earnings (7 days)</h3>
            <p>₦<?= number_format($total_earnings, 2) ?></p>
        </div>
        <div class="card">
            <h3>Total Orders (7 days)</h3>
            <p><?= $total_orders ?></p>
        </div>
    </div>

    <div class="delivery-toggle">
        <h4>Delivery Method</h4>
      <!-- Delivery Mode Toggle Section -->
<div style="margin: 20px 0; padding: 20px; border: 1px solid #ccc; border-radius: 10px; background-color: #f9f9f9;">
    <h4 style="margin-bottom: 10px;">Delivery Mode</h4>
    <form method="post" action="" style="display: flex; align-items: center; gap: 10px;">
        <select name="delivery_mode" style="padding: 8px 12px; border-radius: 5px; border: 1px solid #bbb;">
            <option value="vendor" <?= ($delivery_mode === 'vendor') ? 'selected' : '' ?>>Vendor Delivery</option>
            <option value="platform" <?= ($delivery_mode === 'platform') ? 'selected' : '' ?>>Platform Delivery</option>
        </select>
        <button type="submit" name="update_mode" style="padding: 8px 14px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Update
        </button>
    </form>
    <small style="color: #666; display: block; margin-top: 10px;">Current Mode: <strong><?= ucfirst($delivery_mode) ?></strong></small>
</div>

    </div>

    <div class="chart-container">
        <canvas id="ordersChart"></canvas>
    </div>

    <div class="chart-container">
        <canvas id="earningsChart"></canvas>
    </div>
</div>

<script>
    const dropdownBtn = document.querySelector('.nav-btn');
    const dropdown = document.querySelector('.nav-dropdown');

    dropdownBtn.addEventListener('click', () => {
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });
    window.addEventListener('click', e => {
        if (!e.target.closest('.nav-links')) {
            dropdown.style.display = 'none';
        }
    });

    const ordersData = <?= json_encode($orders_week) ?>;
    const earningsData = <?= json_encode($earnings_week) ?>;

    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ordersCtx, {
        type: 'line',
        data: {
            labels: ordersData.map(d => d.date),
            datasets: [{
                label: 'Orders per Day',
                data: ordersData.map(d => d.count),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40,167,69,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 5
            }]
        },
        options: {
            plugins: {
                legend: { display: false },
                title: {
                    display: true,
                    text: 'Weekly Orders',
                    font: { size: 18 }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    const earningsCtx = document.getElementById('earningsChart').getContext('2d');
    new Chart(earningsCtx, {
        type: 'bar',
        data: {
            labels: earningsData.map(d => d.date),
            datasets: [{
                label: 'Earnings (₦)',
                data: earningsData.map(d => d.earnings),
                backgroundColor: '#006400'
            }]
        },
        options: {
            plugins: {
                legend: { display: false },
                title: {
                    display: true,
                    text: 'Daily Earnings (₦)',
                    font: { size: 18 }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>

</body>
</html>
