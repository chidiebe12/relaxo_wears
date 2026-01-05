<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// ============================================
// 1. HANDLE VENDOR APPROVALS
// ============================================
if (isset($_GET['approve_vendor'])) {
    $vendorId = intval($_GET['approve_vendor']);
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'vendor'");
    $stmt->bind_param("i", $vendorId);
    $stmt->execute();
    header("Location: admin_dashboard.php");
    exit();
}

// ============================================
// 2. HANDLE ADMIN PRODUCT UPLOAD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $base_price = floatval($_POST['base_price']);
    $category_id = intval($_POST['category_id']);
    $quantity = intval($_POST['quantity']);
    
    // Handle image upload
    $image = 'default_product.jpg';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/products/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                $image = 'uploads/products/' . $new_filename;
            }
        }
    }
    
    // Admin vendor ID = 0 as you specified
    $admin_vendor_id = 0;
    $buyer_price = $base_price * 1.11; // 11% markup
    
    $stmt = $conn->prepare("INSERT INTO products (vendor_id, name, description, base_price, buyer_price, markup_percent, quantity, image, category_id, delivery_mode) VALUES (?, ?, ?, ?, ?, 11.00, ?, ?, ?, 'platform')");
    $stmt->bind_param("issddiss", $admin_vendor_id, $name, $description, $base_price, $buyer_price, $quantity, $image, $category_id);
    
    if ($stmt->execute()) {
        $product_success = "✅ Product '$name' added successfully!";
    } else {
        $product_error = "❌ Error adding product: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================
// 3. HANDLE FESTIVE PERIOD TOGGLE (existing)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_festive'])) {
        $festive_id = intval($_POST['festive_id']);
        $is_active = $_POST['is_active'] == '1' ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE festive_periods SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $festive_id);
        $stmt->execute();
        
        if ($is_active == 1) {
            $conn->query("UPDATE festive_periods SET is_active = 0 WHERE id != $festive_id");
        }
        
        header("Location: admin_dashboard.php");
        exit();
    }
    
    if (isset($_POST['create_festive'])) {
        $name = trim($_POST['festive_name']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $low_tier_discount = floatval($_POST['low_tier_discount']);
        $low_tier_max_price = floatval($_POST['low_tier_max_price']);
        
        $stmt = $conn->prepare("INSERT INTO festive_periods (name, start_date, end_date, low_tier_discount, low_tier_max_price, is_active) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssdd", $name, $start_date, $end_date, $low_tier_discount, $low_tier_max_price);
        $stmt->execute();
        
        header("Location: admin_dashboard.php");
        exit();
    }
}

// ============================================
// FETCH DATA
// ============================================

// Fetch pending vendors (needing approval)
$pending_vendors = [];
$pending_result = $conn->query("SELECT * FROM users WHERE role = 'vendor' AND is_approved = 0 ORDER BY created_at DESC");

// Fetch categories for product form
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories ORDER BY name");

// Fetch active festive period
$active_festive = null;
$festive_check = $conn->query("SELECT * FROM festive_periods WHERE is_active = TRUE LIMIT 1");
if ($festive_check->num_rows > 0) {
    $active_festive = $festive_check->fetch_assoc();
}

// Fetch all festive periods
$festive_periods = [];
$festive_result = $conn->query("SELECT * FROM festive_periods ORDER BY start_date DESC");

// Commission data (existing)
$labels = [];
$earnings = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date("Y-m-d", strtotime("-$i days"));
    $labels[] = date("D", strtotime($date));
    $stmt = $conn->prepare("SELECT SUM(commission) as total FROM orders WHERE status = 'completed' AND DATE(created_at) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $earnings[] = (float)($res['total'] ?? 0);
    $stmt->close();
}

// Category orders data (existing)
$category_labels = [];
$category_data = [];
$result = $conn->query("
    SELECT c.name AS category_name, COALESCE(SUM(o.quantity), 0) AS total_quantity
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN orders o ON o.product_id = p.id
    GROUP BY c.name
    ORDER BY total_quantity DESC
");
while ($row = $result->fetch_assoc()) {
    $category_labels[] = $row['category_name'];
    $category_data[] = (int)$row['total_quantity'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Relaxo Wears - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* YOUR ORIGINAL STYLES - NO CHANGES */
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f9f9f9;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            max-width: 1100px;
            margin: auto;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-btn {
            background-color: #006400;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            min-width: 200px;
            z-index: 1;
        }
        .dropdown-content a {
            padding: 12px 16px;
            display: block;
            text-decoration: none;
            color: #333;
        }
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        canvas {
            max-width: 100%;
            height: 300px;
            margin-bottom: 20px;
        }
        .summary {
            margin-top: 10px;
            background: #eafce6;
            padding: 12px 18px;
            border-radius: 6px;
            color: #004d00;
        }
        h3 {
            margin-top: 40px;
            color: #004d00;
        }
        
        /* YOUR ORIGINAL FESTIVE STYLES */
        .festive-section {
            background: #fff8e1;
            border: 1px solid #ffd54f;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .festive-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
            margin-left: 10px;
        }
        .festive-active {
            background: #4caf50;
            color: white;
        }
        .festive-inactive {
            background: #f44336;
            color: white;
        }
        .toggle-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .toggle-on {
            background: #4caf50;
            color: white;
        }
        .toggle-off {
            background: #f44336;
            color: white;
        }
        .festive-periods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .festive-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            position: relative;
        }
        .festive-card.active {
            border-color: #4caf50;
            background: #f1f8e9;
        }
        .create-festive-form {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        /* NEW: Simple styles for new features */
        .vendor-section {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .vendor-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .vendor-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 5px;
        }
        .approve-btn { background: #4caf50; color: white; }
        
        .product-section {
            background: #e8f5e9;
            border: 1px solid #81c784;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            height: 80px;
            resize: vertical;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .submit-btn {
            background: #2196f3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-bar">
        <h2>Relaxo Wears - Admin Dashboard</h2>
        <div class="dropdown">
            <button class="dropdown-btn">Menu</button>
            <div class="dropdown-content">
                <a href="admin_orders.php">Review Buyer Orders</a>
                <a href="completed_orders.php">Payouts</a>
                <a href="admin_notifications.php">Notifications</a>
                <a href="send_message.php">Send Message</a>
                <a href="admin_dashboard.php?logout=true" style="color: red;">Logout</a>
            </div>
        </div>
    </div>

    <!-- NEW: Vendor Approval Section -->
    <div class="vendor-section">
        <h3>📋 Vendor Approvals (<?= $pending_result->num_rows ?> pending)</h3>
        
        <?php if ($pending_result->num_rows > 0): ?>
            <?php while ($vendor = $pending_result->fetch_assoc()): ?>
                <div class="vendor-card">
                    <div>
                        <strong><?= htmlspecialchars($vendor['name']) ?></strong><br>
                        <small>📧 <?= htmlspecialchars($vendor['email']) ?></small><br>
                        <small>📅 Registered: <?= date('M d, Y', strtotime($vendor['created_at'])) ?></small>
                    </div>
                    <div class="vendor-actions">
                        <a href="?approve_vendor=<?= $vendor['id'] ?>" class="approve-btn" onclick="return confirm('Approve vendor: <?= htmlspecialchars($vendor['name']) ?>?')">✅ Approve</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 10px;">No pending vendor approvals.</p>
        <?php endif; ?>
    </div>

    <!-- NEW: Admin Product Upload Section -->
    <div class="product-section">
        <h3>🛍️ Add Product (Admin)</h3>
        
        <?php if (isset($product_success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <?= $product_success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($product_error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <?= $product_error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" required>
                </div>
                
                <div class="form-group">
                    <label>Base Price (₦) *</label>
                    <input type="number" name="base_price" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php while ($cat = $cat_result->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Product Image *</label>
                    <input type="file" name="product_image" accept="image/*" required>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <button type="submit" name="add_product" class="submit-btn">
                    🛍️ Add Product
                </button>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    <strong>Note:</strong> Products added here will have vendor_id = 0 (Admin Products)
                </p>
            </div>
        </form>
    </div>

    <!-- YOUR ORIGINAL: Festive Period Management -->
    <div class="festive-section">
        <h3>🎉 Festive Period Management</h3>
        
        <?php if ($active_festive): ?>
            <div style="margin-bottom: 15px; padding: 10px; background: #e8f5e9; border-radius: 6px;">
                <strong>Current Active Festive Period:</strong> 
                <?= htmlspecialchars($active_festive['name']) ?>
                (<?= date('M d', strtotime($active_festive['start_date'])) ?> - <?= date('M d, Y', strtotime($active_festive['end_date'])) ?>)
                <span class="festive-status festive-active">ACTIVE</span>
                
                <form method="POST" style="display: inline; margin-left: 10px;">
                    <input type="hidden" name="festive_id" value="<?= $active_festive['id'] ?>">
                    <input type="hidden" name="is_active" value="0">
                    <button type="submit" name="toggle_festive" class="toggle-btn toggle-off">Turn OFF</button>
                </form>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 15px; padding: 10px; background: #ffebee; border-radius: 6px;">
                <strong>No Active Festive Period</strong>
                <span class="festive-status festive-inactive">INACTIVE</span>
            </div>
        <?php endif; ?>
        
        <!-- List all festive periods -->
        <h4>All Festive Periods:</h4>
        <div class="festive-periods">
            <?php while ($period = $festive_result->fetch_assoc()): ?>
                <div class="festive-card <?= $period['is_active'] ? 'active' : '' ?>">
                    <strong><?= htmlspecialchars($period['name']) ?></strong>
                    <div style="font-size: 14px; color: #666;">
                        <?= date('M d', strtotime($period['start_date'])) ?> - <?= date('M d, Y', strtotime($period['end_date'])) ?>
                    </div>
                    <div style="font-size: 12px; margin-top: 5px;">
                        Discount: <strong><?= $period['low_tier_discount'] ?>%</strong> (below ₦<?= number_format($period['low_tier_max_price'], 2) ?>)
                    </div>
                    
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="festive_id" value="<?= $period['id'] ?>">
                        <input type="hidden" name="is_active" value="<?= $period['is_active'] ? '0' : '1' ?>">
                        <button type="submit" name="toggle_festive" class="toggle-btn <?= $period['is_active'] ? 'toggle-off' : 'toggle-on' ?>">
                            <?= $period['is_active'] ? 'Turn OFF' : 'Turn ON' ?>
                        </button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Create new festive period -->
        <div class="create-festive-form">
            <h4>Create New Festive Period:</h4>
            <form method="POST">
                <div class="form-group">
                    <label>Festive Name (e.g., "Black Friday 2024"):</label>
                    <input type="text" name="festive_name" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Start Date:</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date:</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Low Tier Discount (%):</label>
                        <input type="number" name="low_tier_discount" value="11" step="0.1" min="0" max="100" required>
                    </div>
                    <div class="form-group">
                        <label>Low Tier Max Price (₦):</label>
                        <input type="number" name="low_tier_max_price" value="25000" step="1000" min="0" required>
                    </div>
                </div>
                
                <button type="submit" name="create_festive" style="background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                    Create Festive Period
                </button>
            </form>
        </div>
    </div>

    <!-- YOUR ORIGINAL: Earnings Chart -->
    <h3>Admin Commission (Last 7 Days)</h3>
    <canvas id="earningsChart"></canvas>
    <div class="summary">
        This chart shows total commission earned per day from completed orders.
    </div>

    <!-- YOUR ORIGINAL: Orders per Category Chart -->
    <h3>Orders by Product Category</h3>
    <canvas id="categoryChart"></canvas>
    <div class="summary">
        Quantity of items ordered from each category (all-time).
    </div>
</div>

<script>
    // YOUR ORIGINAL: Admin Earnings Chart
    new Chart(document.getElementById('earningsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Admin Commission (₦)',
                data: <?= json_encode($earnings) ?>,
                backgroundColor: 'rgba(0,100,0,0.2)',
                borderColor: '#006400',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // YOUR ORIGINAL: Category Orders Chart
    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($category_labels) ?>,
            datasets: [{
                label: 'Quantity Ordered',
                data: <?= json_encode($category_data) ?>,
                backgroundColor: 'rgba(34,139,34,0.6)',
                borderColor: '#228B22',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>
</body>
</html>