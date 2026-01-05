<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "vendor") {
    header("Location: vendor_login.php");
    exit();
}

$vendor_id = $_SESSION["user_id"];
$message = "";

// First, check if vendor_subscription_plans table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'vendor_subscription_plans'");
if ($table_check->num_rows == 0) {
    // Create the table
    $conn->query("CREATE TABLE IF NOT EXISTS vendor_subscription_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT,
        monthly_fee DECIMAL(10,2) DEFAULT 0.00,
        commission_percent DECIMAL(5,2) DEFAULT 8.00,
        max_products INT DEFAULT 50,
        features TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default plans
    $conn->query("INSERT INTO vendor_subscription_plans (name, description, monthly_fee, commission_percent, max_products, features) VALUES
        ('Basic', 'Free plan with standard commission rate', 0.00, 8.00, 50, 'Basic product listing,Standard visibility,Weekly payments'),
        ('Premium', 'Premium features with lower commission rate', 1600.00, 5.00, 200, 'Featured listings,Priority support,Faster payments,Advanced analytics')");
}

// Check if users table has subscription columns
$column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_plan_id'");
if ($column_check->num_rows == 0) {
    // Add subscription columns
    $conn->query("ALTER TABLE users 
        ADD COLUMN subscription_plan_id INT DEFAULT 1,
        ADD COLUMN subscription_expires DATE,
        ADD COLUMN subscription_status ENUM('active', 'expired', 'cancelled') DEFAULT 'active'");
}

// Get vendor's current subscription plan AND delivery mode
$vendor_info = $conn->query("
    SELECT u.delivery_mode, u.subscription_plan_id, 
           vsp.*
    FROM users u 
    LEFT JOIN vendor_subscription_plans vsp ON u.subscription_plan_id = vsp.id 
    WHERE u.id = $vendor_id
");

if ($vendor_info && $vendor_info->num_rows > 0) {
    $vendor_data = $vendor_info->fetch_assoc();
    $plan_commission = $vendor_data['commission_percent'] ?? 8.00;
    $plan_name = $vendor_data['name'] ?? 'Basic';
    $is_premium = ($plan_name === 'Premium');
    $delivery_mode = $vendor_data['delivery_mode'] ?? 'platform';
    
    // Apply your commission matrix based on delivery mode
    if ($delivery_mode == 'vendor') {
        // Vendor handles delivery: keep plan commission (8% or 5%)
        $final_commission = $plan_commission;
    } else {
        // Platform handles delivery: add 3% extra
        $final_commission = $plan_commission + 3.00;
    }
    
    // Commission breakdown for display
    $commission_rate = $final_commission;
    $commission_type = ($delivery_mode == 'vendor') ? 'Vendor Delivery' : 'Platform Delivery';
} else {
    // Default values
    $plan_name = 'Basic';
    $is_premium = false;
    $delivery_mode = 'platform';
    $commission_rate = 11.00; // Platform delivery + Basic
    $commission_type = 'Platform Delivery';
}

// Fetch categories (all categories, no filtering)
$categoryQuery = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $categoryQuery->fetch_all(MYSQLI_ASSOC);

// Final upload
if (isset($_POST['confirm_upload']) && isset($_SESSION['preview'])) {
    $p = $_SESSION['preview'];

    // Calculate buyer price (base_price + 11% markup)
    $buyer_price = $p['base_price'] * 1.11;
    $markup_percent = 11.00;
    
    $stmt = $conn->prepare("INSERT INTO products 
        (name, description, base_price, buyer_price, markup_percent, quantity, weight, image, vendor_id, category_id, delivery_mode) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssdddiisiss", 
        $p['name'], 
        $p['description'], 
        $p['base_price'],
        $buyer_price,
        $markup_percent,
        $p['quantity'], 
        $p['weight'], 
        $p['image_path'], 
        $vendor_id, 
        $p['category_id'], 
        $delivery_mode
    );

    if ($stmt->execute()) {
        $product_id = $stmt->insert_id;

        // Save other images into product_images table
        if (!empty($p['other_images'])) {
            $insertImageStmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
            foreach ($p['other_images'] as $img) {
                $insertImageStmt->bind_param("is", $product_id, $img);
                $insertImageStmt->execute();
            }
            $insertImageStmt->close();
        }

        $message = "✅ Product uploaded successfully.";
    } else {
        $message = "❌ Error uploading product: " . $stmt->error;
    }
    $stmt->close();
    unset($_SESSION['preview']);
}

// Preview setup
elseif (isset($_POST['preview'])) {
    if (!is_dir("../uploads")) {
        mkdir("../uploads", 0777, true);
    }

    $image_path = "";
    $other_images = [];

    // Main image
    if (!empty($_FILES["image"]["name"])) {
        $image_name = basename($_FILES["image"]["name"]);
        $unique_main = uniqid() . "_" . $image_name;
        $target_main = "../uploads/" . $unique_main;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_main)) {
            $image_path = $target_main;
        } else {
            $message = "❌ Failed to upload main image.";
        }
    }

    // Other images
    if (!empty($_FILES['other_images']['name'][0])) {
        foreach ($_FILES['other_images']['name'] as $key => $img_name) {
            $img_tmp = $_FILES['other_images']['tmp_name'][$key];
            $unique_name = uniqid() . "_" . basename($img_name);
            $upload_path = "../uploads/" . $unique_name;

            if (move_uploaded_file($img_tmp, $upload_path)) {
                $other_images[] = $upload_path;
            }
        }
    }

    // Store preview data
    if (!empty($image_path)) {
        $_SESSION['preview'] = [
            "name" => $_POST["name"],
            "description" => $_POST["description"],
            "base_price" => $_POST["base_price"],
            "quantity" => $_POST["quantity"],
            "weight" => $_POST["weight"],
            "category_id" => $_POST["category_id"],
            "image_path" => $image_path,
            "other_images" => $other_images
        ];
    }
}

// Cancel preview
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['preview']) && !isset($_POST['confirm_upload'])) {
    if (isset($_SESSION['preview']['image_path']) && file_exists($_SESSION['preview']['image_path'])) {
        unlink($_SESSION['preview']['image_path']);
    }
    unset($_SESSION['preview']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Relaxo Wears - Upload Product</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 700px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
        h1 { color: darkgreen; }
        input, select, textarea, button {
            width: 100%; padding: 10px; margin-top: 10px; border: 1px solid #ccc; border-radius: 5px;
        }
        button { background: darkgreen; color: white; cursor: pointer; }
        .back-link { display: inline-block; margin-top: 20px; text-decoration: none; color: darkgreen; }
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .success { background: #e6ffe6; color: green; border: 1px solid #a5d6a7; }
        .error { background: #ffe6e6; color: red; border: 1px solid #f5a4a4; }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .preview-grid img {
            width: 100%;
            height: auto;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        #lowPriceWarning {
            color: red;
            font-size: 14px;
            font-weight: bold;
            display: none;
        }
        .commission-info {
            background: <?= $is_premium ? '#e8f5e9' : '#fff3cd'; ?>;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid <?= $is_premium ? '#27ae60' : '#f39c12'; ?>;
        }
        .plan-badge {
            background: <?= $is_premium ? '#27ae60' : '#f39c12'; ?>;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
        }
        .delivery-badge {
            background: <?= $delivery_mode == 'vendor' ? '#3498db' : '#e74c3c'; ?>;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-left: 10px;
        }
        .upgrade-prompt {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #bbdefb;
        }
        .price-breakdown {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #2e7d4c;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .price-row.total {
            border-top: 2px solid #1a4d2e;
            border-bottom: none;
            font-weight: bold;
            font-size: 16px;
        }
        .upload-btn {
            background: #27ae60;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
        }
        .cancel-btn {
            background: #95a5a6;
            margin-top: 10px;
        }
        .commission-matrix {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 13px;
        }
        .commission-matrix table {
            width: 100%;
            border-collapse: collapse;
        }
        .commission-matrix td, .commission-matrix th {
            padding: 6px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .commission-matrix th {
            background: #1a4d2e;
            color: white;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Relaxo Wears</h1>
    <h2>Upload Product</h2>

    <?php if (!empty($message)): ?>
        <div class="msg <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- Commission Information Banner -->
    <div class="commission-info">
        <span class="plan-badge"><?= $plan_name ?> Plan</span>
        <span class="delivery-badge"><?= ucfirst($delivery_mode) ?> Delivery</span>
        <h3 style="margin: 10px 0;">Commission Rate: <strong><?= $commission_rate ?>%</strong> per sale</h3>
        <p style="margin: 5px 0; font-size: 14px;">
            <strong>Delivery Mode:</strong> <?= ucfirst($delivery_mode) ?> 
            (<?= $delivery_mode == 'vendor' ? 'You handle delivery' : 'Platform handles delivery' ?>)
        </p>
        
        <div class="commission-matrix">
            <p style="margin: 0 0 10px 0; font-weight: bold;">Your Commission Breakdown:</p>
            <table>
                <tr>
                    <th>Plan</th>
                    <th>Vendor Delivery</th>
                    <th>Platform Delivery</th>
                </tr>
                <tr>
                    <td><strong>Basic</strong> (Free)</td>
                    <td <?= (!$is_premium && $delivery_mode=='vendor') ? 'style="background:#d4edda;"' : '' ?>>8% commission</td>
                    <td <?= (!$is_premium && $delivery_mode=='platform') ? 'style="background:#d4edda;"' : '' ?>>11% commission</td>
                </tr>
                <tr>
                    <td><strong>Premium</strong> (₦1,600/month)</td>
                    <td <?= ($is_premium && $delivery_mode=='vendor') ? 'style="background:#d4edda;"' : '' ?>>5% commission</td>
                    <td <?= ($is_premium && $delivery_mode=='platform') ? 'style="background:#d4edda;"' : '' ?>>8% commission</td>
                </tr>
            </table>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                <strong>Current:</strong> <?= $plan_name ?> Plan + <?= ucfirst($delivery_mode) ?> Delivery = <?= $commission_rate ?>%
            </p>
        </div>
        
        <?php if (!$is_premium): ?>
            <div class="upgrade-prompt">
                <strong>⭐ Want to pay less commission?</strong>
                <p style="margin: 5px 0;">Upgrade to <strong>Premium Plan</strong> for only ₦1,600/month and save:</p>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <li><strong><?= ($delivery_mode=='vendor') ? '3% less' : '3% less' ?></strong> on commission (<?= ($delivery_mode=='vendor') ? '8% → 5%' : '11% → 8%' ?>)</li>
                    <li>Featured product listings</li>
                    <li>Priority customer support</li>
                </ul>
                <a href="vendor_subscription.php" style="display: inline-block; background: #1a4d2e; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; margin-top: 5px;">
                    Upgrade to Premium
                </a>
            </div>
        <?php else: ?>
            <p style="margin: 10px 0; font-size: 14px; color: #27ae60;">
                ✅ You're on the best plan! Enjoy low commission rates.
            </p>
        <?php endif; ?>
        
        <?php if ($delivery_mode == 'platform'): ?>
            <p style="margin: 10px 0; font-size: 14px; color: #666;">
                💡 <strong>Tip:</strong> Switch to <strong>Vendor Delivery</strong> to save 3% on commission 
                (<?= $is_premium ? '8% → 5%' : '11% → 8%' ?>).
                <a href="vendor_dashboard.php" style="color: #1a4d2e; font-weight: bold;">Change in Dashboard</a>
            </p>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['preview'])): ?>
        <?php 
        $p = $_SESSION['preview'];
        // Calculate commission for this product
        $commission_amount = $p['base_price'] * ($commission_rate / 100);
        $vendor_earnings = $p['base_price'] - $commission_amount;
        
        // Calculate what they'd earn with different options
        $vendor_delivery_rate = $is_premium ? 5.00 : 8.00;
        $vendor_delivery_earnings = $p['base_price'] * (1 - $vendor_delivery_rate/100);
        $premium_rate = $delivery_mode == 'vendor' ? 5.00 : 8.00;
        $premium_earnings = $p['base_price'] * (1 - $premium_rate/100);
        ?>
        
        <h3>🔍 Preview Product</h3>
        <p><strong>Name:</strong> <?= htmlspecialchars($p['name']) ?></p>
        <p><strong>Description:</strong> <?= htmlspecialchars($p['description']) ?></p>
        
        <div class="price-breakdown">
            <h4>💰 Your Earnings Breakdown</h4>
            <div class="price-row">
                <span>Your Selling Price:</span>
                <span>₦<?= number_format($p['base_price'], 2) ?></span>
            </div>
            <div class="price-row">
                <span>Platform Commission (<?= $commission_rate ?>%):</span>
                <span style="color: #e74c3c;">-₦<?= number_format($commission_amount, 2) ?></span>
            </div>
            <div class="price-row total">
                <span>You Earn Per Sale:</span>
                <span style="color: #27ae60; font-size: 18px;">₦<?= number_format($vendor_earnings, 2) ?></span>
            </div>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                Based on: <?= $plan_name ?> Plan + <?= ucfirst($delivery_mode) ?> Delivery
            </p>
            
            <!-- Optimization Tips -->
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; border: 1px solid #ddd;">
                <p style="margin: 0 0 5px 0; font-weight: bold; color: #1a4d2e;">💡 Maximize Your Earnings:</p>
                <?php if (!$is_premium || $delivery_mode == 'platform'): ?>
                    <ul style="margin: 0; padding-left: 20px; font-size: 12px;">
                        <?php if (!$is_premium): ?>
                        <li>With <strong>Premium Plan</strong>: You'd earn <strong>₦<?= number_format($premium_earnings, 2) ?></strong> 
                            (₦<?= number_format($premium_earnings - $vendor_earnings, 2) ?> more per sale)</li>
                        <?php endif; ?>
                        <?php if ($delivery_mode == 'platform'): ?>
                        <li>With <strong>Vendor Delivery</strong>: You'd earn <strong>₦<?= number_format($vendor_delivery_earnings, 2) ?></strong> 
                            (₦<?= number_format($vendor_delivery_earnings - $vendor_earnings, 2) ?> more per sale)</li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin: 0; font-size: 12px;">✅ You're already at the optimal commission rate!</p>
                <?php endif; ?>
            </div>
        </div>
        
        <p><strong>Quantity:</strong> <?= (int)$p['quantity'] ?></p>
        <p><strong>Weight:</strong> <?= htmlspecialchars($p['weight']) ?> kg</p>
        <p><strong>Category:</strong>
            <?php
            foreach ($categories as $cat) {
                if ($cat['id'] == $p['category_id']) {
                    echo htmlspecialchars($cat['name']);
                    break;
                }
            }
            ?>
        </p>
        <p><strong>Delivery Mode:</strong> <?= ucfirst($delivery_mode) ?></p>

        <div class="preview-grid">
            <img src="<?= $p['image_path'] ?>" alt="Main Image">
            <?php foreach ($p['other_images'] as $img): ?>
                <img src="<?= $img ?>" alt="Other Image">
            <?php endforeach; ?>
        </div>

        <form method="POST">
            <button type="submit" name="confirm_upload" class="upload-btn">✅ Confirm Upload</button>
        </form>
        <form method="POST">
            <button type="submit" class="cancel-btn">❌ Cancel</button>
        </form>

    <?php else: ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Product Name" required>
            <textarea name="description" placeholder="Description" required></textarea>

            <label for="base_price">Your Selling Price (₦)</label>
            <input type="number" step="0.01" name="base_price" id="base_price" placeholder="Enter your price" required>
            
            <div class="price-breakdown" id="commissionPreview" style="display: none;">
                <h4>💰 Commission Preview</h4>
                <div class="price-row">
                    <span>Your Price:</span>
                    <span id="display-price">₦0.00</span>
                </div>
                <div class="price-row">
                    <span>Commission (<?= $commission_rate ?>%):</span>
                    <span id="display-commission" style="color: #e74c3c;">-₦0.00</span>
                </div>
                <div class="price-row total">
                    <span>You Earn:</span>
                    <span id="display-earnings" style="color: #27ae60;">₦0.00</span>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    Based on: <?= $plan_name ?> Plan + <?= ucfirst($delivery_mode) ?> Delivery
                </p>
                
                <!-- Optimization tips in real-time -->
                <div id="optimization-tips" style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; border: 1px solid #ddd; display: none;">
                    <p style="margin: 0; font-size: 12px; font-weight: bold; color: #1a4d2e;">💡 How to earn more:</p>
                    <p id="tip-content" style="margin: 5px 0 0 0; font-size: 11px;"></p>
                </div>
            </div>
            
            <div id="lowPriceWarning" style="display: none;">
                ⚠️ Products below ₦2,000 may have different commission rates.
            </div>

            <input type="number" name="quantity" placeholder="Quantity Available" required>
            <input type="number" name="weight" step="0.01" placeholder="Weight (kg)" required>

            <select name="category_id" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Main Product Image:</label>
            <input type="file" name="image" accept="image/*" required>

            <label>Additional Images (up to 6):</label>
            <input type="file" name="other_images[]" accept="image/*" multiple>

            <button type="submit" name="preview">🔍 Preview Product</button>
        </form>
    <?php endif; ?>

    <a href="vendor_dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const basePriceInput = document.getElementById('base_price');
    const warningDiv = document.getElementById('lowPriceWarning');
    const commissionPreview = document.getElementById('commissionPreview');
    const displayPrice = document.getElementById('display-price');
    const displayCommission = document.getElementById('display-commission');
    const displayEarnings = document.getElementById('display-earnings');
    const optimizationTips = document.getElementById('optimization-tips');
    const tipContent = document.getElementById('tip-content');
    
    // PHP values passed to JavaScript
    const commissionRate = <?= $commission_rate ?>;
    const isPremium = <?= $is_premium ? 'true' : 'false'; ?>;
    const deliveryMode = '<?= $delivery_mode ?>';
    const planName = '<?= $plan_name ?>';

    function updateCommissionDisplay() {
        const basePrice = parseFloat(basePriceInput.value) || 0;
        
        if (basePrice > 0) {
            commissionPreview.style.display = 'block';
            
            const commissionAmount = basePrice * (commissionRate / 100);
            const vendorEarnings = basePrice - commissionAmount;
            
            // Update displays
            displayPrice.textContent = '₦' + basePrice.toFixed(2);
            displayCommission.textContent = '-₦' + commissionAmount.toFixed(2);
            displayEarnings.textContent = '₦' + vendorEarnings.toFixed(2);
            
            // Calculate optimization tips
            let tips = [];
            
            if (!isPremium) {
                const premiumRate = (deliveryMode === 'vendor') ? 5.00 : 8.00;
                const premiumEarnings = basePrice * (1 - premiumRate/100);
                const premiumDiff = premiumEarnings - vendorEarnings;
                tips.push(`With <strong>Premium Plan</strong>: Earn ₦${premiumDiff.toFixed(2)} more per sale`);
            }
            
            if (deliveryMode === 'platform') {
                const vendorDeliveryRate = isPremium ? 5.00 : 8.00;
                const vendorDeliveryEarnings = basePrice * (1 - vendorDeliveryRate/100);
                const deliveryDiff = vendorDeliveryEarnings - vendorEarnings;
                tips.push(`With <strong>Vendor Delivery</strong>: Earn ₦${deliveryDiff.toFixed(2)} more per sale`);
            }
            
            // Show optimization tips if available
            if (tips.length > 0) {
                optimizationTips.style.display = 'block';
                tipContent.innerHTML = tips.join('<br>');
            } else {
                optimizationTips.style.display = 'none';
            }
            
            // Show warning for low price
            warningDiv.style.display = (basePrice < 2000) ? 'block' : 'none';
        } else {
            commissionPreview.style.display = 'none';
            optimizationTips.style.display = 'none';
            warningDiv.style.display = 'none';
        }
    }

    basePriceInput.addEventListener('input', updateCommissionDisplay);
    basePriceInput.addEventListener('change', updateCommissionDisplay);
    
    // Trigger on page load if there's already a value
    if (basePriceInput.value) {
        updateCommissionDisplay();
    }
});
</script>
</body>
</html>