<?php
session_start();
include '../includes/db.php';
include '../includes/festive_functions.php';
include_once '../includes/order_notification_function.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];
$from_cart = isset($_POST["from_cart"]) && $_POST["from_cart"] == 1;
$selected_delivery_mode = $_POST['delivery_mode'] ?? 'dropoff';

// Get data from cart
$product_ids = $_POST['product_ids'] ?? [];
$quantities = $_POST['quantities'] ?? [];

if (empty($product_ids) && empty($_SESSION['orders_created'])) {
    echo "<script>alert('❌ No products found. Please add items to cart first.'); window.location.href='cart.php';</script>";
    exit();
}

// Fetch default address
$stmt = $conn->prepare("SELECT * FROM addresses WHERE buyer_id = ? AND is_default = 1");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$address_result = $stmt->get_result();
$address = $address_result->fetch_assoc();
$stmt->close();

if (!$address) {
    echo "<script>alert('❌ Please add a delivery address first.'); window.location.href='my_account.php';</script>";
    exit();
}

$delivery_address = $address['address'] . ', ' . $address['city'] . ', ' . $address['state'];

$_SESSION['orders_created'] = $_SESSION['orders_created'] ?? [];
$orders_created = $_SESSION['orders_created'];

$delivery_total = floatval($_POST['final_delivery_fee'] ?? 0);
$grand_total = floatval($_POST['final_grand_total'] ?? 0);

// Process order creation
if ($from_cart && !empty($product_ids) && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($orders_created)) {
    $conn->begin_transaction();
    
    try {
        $new_order_ids = [];
        $order_total = 0;
        
        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = intval($product_ids[$i]);
            $quantity = intval($quantities[$i]);
            
            if ($product_id <= 0 || $quantity <= 0) continue;
            
            // Get product details
            $stmt = $conn->prepare("
                SELECT p.*, u.id as vendor_id, u.delivery_mode as vendor_delivery_pref
                FROM products p 
                JOIN users u ON p.vendor_id = u.id 
                WHERE p.id = ?
            ");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$product) continue;
            
            // Check stock
            if ($product['quantity'] < $quantity) {
                throw new Exception("Insufficient stock for: " . $product['name']);
            }
            
            // Update stock
            $update = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $update->bind_param("ii", $quantity, $product_id);
            $update->execute();
            $update->close();
            
            // Get price info
            $priceInfo = getDisplayPrice($product, $conn);
            $base_price = $priceInfo['base_price'];
            $buyer_price = $priceInfo['buyer_price'];
            $festive_price = $priceInfo['final_price']; // This is stored as festive_price in DB
            $is_festive = $priceInfo['is_festive'] ? 1 : 0;
            $discount_percent = $priceInfo['discount_percent'];
            $markup_amount = $priceInfo['markup_amount'];
            
            // Calculate commission
            $commission_data = calculateCommission($product['vendor_id'], $base_price, $quantity, $conn);
            
            // Calculate item total (use festive_price for total calculation)
            $item_total = $festive_price * $quantity;
            $order_total += $item_total;
            
            // Calculate delivery fee per item
            $delivery_fee_item = 0;
            if ($delivery_total > 0) {
                $delivery_fee_item = $delivery_total / count($product_ids);
            }
            
            // FIXED: Use festive_price column instead of final_price
            $stmt = $conn->prepare("
                INSERT INTO orders (
                    buyer_id, product_id, quantity, 
                    base_price, buyer_price, festive_price, discount_percent, is_festive, markup_amount,
                    total_amount, vendor_earnings, commission, commission_rate,
                    delivery_fee, delivery_mode, delivery_address,
                    status, payout_status, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'unpaid')
            ");
            
            $stmt->bind_param(
                "iiiddddddddddsss", 
                $buyer_id, 
                $product_id, 
                $quantity,
                $base_price,                      // Vendor's base price
                $buyer_price,                     // 11% markup price (before festive discount)
                $festive_price,                   // FIXED: festive_price column (final price customer pays)
                $discount_percent,                // Discount percentage
                $is_festive,                      // Is festive order
                $markup_amount,                   // Your markup
                $item_total,                      // Total amount customer pays
                $commission_data['vendor_earnings'], // Vendor's earnings
                $commission_data['amount'],       // Your commission
                $commission_data['rate'],         // Commission rate used
                $delivery_fee_item,               // Delivery fee for this item
                $selected_delivery_mode,          // Delivery mode
                $delivery_address                 // Delivery address
            );
            
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;
                $new_order_ids[] = $order_id;
                
                // Remove from cart
                $remove = $conn->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id = ?");
                $remove->bind_param("ii", $buyer_id, $product_id);
                $remove->execute();
                $remove->close();
                
                // Send notification
                send_buyer_notification($conn, $buyer_id, "Order #$order_id placed successfully", $order_id);
            }
            $stmt->close();
        }
        
        $conn->commit();
        $_SESSION['orders_created'] = $new_order_ids;
        $orders_created = $new_order_ids;
        
        // Store totals
        $_SESSION['order_total'] = $order_total;
        $_SESSION['delivery_total'] = $delivery_total;
        $_SESSION['grand_total'] = $order_total + $delivery_total;
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "'); window.location.href='cart.php';</script>";
        exit();
    }
}

// Payment redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && !empty($orders_created)) {
    $method = $_POST['payment_method'];
    $cart_param = $from_cart ? "&from_cart=1" : "";
    $mode_param = "&delivery_mode=$selected_delivery_mode";
    $order_ids_param = "&order_ids=" . implode(',', $orders_created);
    
    $_SESSION['payment_data'] = [
        'order_ids' => $orders_created,
        'order_total' => $_SESSION['order_total'] ?? 0,
        'delivery_total' => $_SESSION['delivery_total'] ?? 0,
        'grand_total' => $_SESSION['grand_total'] ?? 0,
        'delivery_mode' => $selected_delivery_mode
    ];
    
    if ($method === 'paystack') {
        header("Location: paystack_payment.php?action=start$order_ids_param$cart_param$mode_param");
        exit();
    } else {
        header("Location: paypal_payment.php?action=start$order_ids_param$cart_param$mode_param");
        exit();
    }
}

// Fetch orders for display - FIXED: Include festive_price
$products = [];
$payment_total = 0;

if (!empty($orders_created)) {
    foreach ($orders_created as $oid) {
        $stmt = $conn->prepare("
            SELECT o.*, p.name, p.image, p.price as product_base_price
            FROM orders o 
            JOIN products p ON o.product_id = p.id 
            WHERE o.id = ? AND o.buyer_id = ?
        ");
        $stmt->bind_param("ii", $oid, $buyer_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($order) {
            $products[] = $order;
            $payment_total += $order['total_amount'];
        }
    }
}

if (empty($orders_created) && !empty($product_ids)) {
    echo "<script>alert('Please complete the order process from the cart.'); window.location.href='cart.php';</script>";
    exit();
}

$order_total = $_SESSION['order_total'] ?? $payment_total;
$delivery_total = $_SESSION['delivery_total'] ?? $delivery_total;
$grand_total = $_SESSION['grand_total'] ?? ($order_total + $delivery_total);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Confirm & Pay - Relaxo Wears</title>
<style>
body { font-family: 'Roboto', sans-serif; background:#f4f4f4; margin:0; padding:0; }
.container { max-width:800px; margin:30px auto; padding:25px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); }
h2 { text-align:center; color:#1a4d2e; margin-bottom:25px; }
.product { display:flex; margin-bottom:15px; border-bottom:1px solid #ddd; padding-bottom:15px; align-items:center; }
.product img { width:100px; height:100px; object-fit:cover; border-radius:8px; margin-right:15px; }
.product-info { flex:1; }
.product-info div { margin:5px 0; color:#333; }
.product-title { font-weight:bold; font-size:16px; color:#1a4d2e; }
.price-info { color:#555; font-size:14px; }
.festive-price { color:#e74c3c; font-weight:bold; }
.normal-price { color:#1a4d2e; font-weight:bold; }
.original-price { text-decoration:line-through; color:#999; margin-right:8px; }
.festive-badge { background:#e74c3c; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; margin-left:5px; }
.summary { font-weight:bold; text-align:right; margin-top:25px; padding-top:15px; border-top:2px solid #ddd; color:#1a4d2e; }
.summary h4 { margin:8px 0; }
.summary h3 { margin-top:15px; color:#1a4d2e; }
.btn { padding:14px 25px; margin:15px 5px 0 0; border:none; border-radius:8px; cursor:pointer; font-weight:bold; font-size:16px; transition:0.3s; min-width:180px; }
.btn-paypal { background:#0070ba; color:#fff; }
.btn-paypal:hover { background:#005ea6; }
.btn-paystack { background:#3aaf85; color:#fff; }
.btn-paystack:hover { background:#2d9c75; }
.address-box { background:#f0f8f0; padding:12px; margin-bottom:20px; border-radius:8px; border-left:4px solid #2e7d4c; }
.payment-methods { text-align:center; margin-top:25px; }
.payment-methods p { margin-bottom:10px; color:#666; }
.delivery-note { font-size:12px; color:#666; margin-top:5px; }
</style>
</head>
<body>
<div class="container">
<h2>🎯 Review Your Order & Choose Payment</h2>

<?php if(!empty($address)): ?>
<div class="address-box">
    <strong>📦 Delivery Address:</strong><br>
    <?= htmlspecialchars($address['address']) ?><br>
    <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?><br>
    📞 <?= htmlspecialchars($address['phone_number']) ?>
    <div class="delivery-note">Pickup Station Fee: Based on order value and location</div>
</div>
<?php endif; ?>

<?php if(empty($products)): ?>
    <p style="text-align:center; color:crimson; padding:20px;">❌ No orders found. <a href="cart.php">Return to cart</a>.</p>
<?php else: ?>
    <?php 
    $total_festive_savings = 0;
    ?>
    
    <?php foreach($products as $item): ?>
        <?php 
        // For display, use festive_price as the final price
        $buyer_price = $item['buyer_price'] ?? 0;
        $festive_price = $item['festive_price'] ?? $buyer_price; // Use festive_price column
        $is_festive = $item['is_festive'] ?? 0;
        
        if ($is_festive && $buyer_price > $festive_price) {
            $festive_savings = ($buyer_price - $festive_price) * $item['quantity'];
            $total_festive_savings += $festive_savings;
        }
        ?>
        
        <div class="product">
            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <div class="product-info">
                <div class="product-title"><?= htmlspecialchars($item['name']) ?></div>
                
                <!-- Price display - Use festive_price for display -->
                <div class="price-info">
                    <?php if($is_festive && $buyer_price > $festive_price): ?>
                        <span class="original-price">₦<?= number_format($buyer_price, 2) ?></span>
                        <span class="festive-price">₦<?= number_format($festive_price, 2) ?></span>
                        <?php if($item['discount_percent'] > 0): ?>
                            <span class="festive-badge"><?= $item['discount_percent'] ?>% OFF</span>
                        <?php endif; ?>
                    <?php else: ?>
                        Price: <span class="normal-price">₦<?= number_format($festive_price, 2) ?></span>
                    <?php endif; ?>
                    × <?= $item['quantity'] ?> units
                </div>
                
                <div>Subtotal: <strong>₦<?= number_format($item['total_amount'], 2) ?></strong></div>
                <div>Delivery: <?= ucfirst(htmlspecialchars($item['delivery_mode'])) ?> (₦<?= number_format($item['delivery_fee'], 2) ?>)</div>
                
                <?php if($is_festive && $buyer_price > $festive_price): ?>
                    <div style="color: #27ae60; font-size: 12px; font-weight:500;">
                        💰 You save: ₦<?= number_format(($buyer_price - $festive_price) * $item['quantity'], 2) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="summary">
        <h4>🏷️ Order Total: ₦<?= number_format($order_total, 2) ?></h4>
        
        <?php if($total_festive_savings > 0): ?>
            <h4 style="color: #27ae60;">💰 Festive Savings: -₦<?= number_format($total_festive_savings, 2) ?></h4>
        <?php endif; ?>
        
        <h4>🚚 Delivery Fee: ₦<?= number_format($delivery_total, 2) ?></h4>
        
        <h3>Grand Total: ₦<?= number_format($grand_total, 2) ?></h3>
    </div>

    <div class="payment-methods">
        <p>Choose your preferred payment method:</p>
        <form method="POST">
            <input type="hidden" name="from_cart" value="<?= $from_cart ? '1' : '0' ?>">
            <input type="hidden" name="delivery_mode" value="<?= htmlspecialchars($selected_delivery_mode) ?>">
            <button type="submit" name="payment_method" value="paypal" class="btn btn-paypal">
                💳 Pay with PayPal
            </button>
            <button type="submit" name="payment_method" value="paystack" class="btn btn-paystack">
                🇳🇬 Pay with PayStack
            </button>
        </form>
    </div>
<?php endif; ?>
</div>
</body>
</html>