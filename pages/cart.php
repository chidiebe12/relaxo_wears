<?php
session_start();
include '../includes/db.php';
// NEW: Include festive functions (updated version)
include '../includes/festive_functions.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];
$message = "";

// Handle removal
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_cart_id"])) {
    $remove_id = (int)$_POST["remove_cart_id"];
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND buyer_id = ?");
    $stmt->bind_param("ii", $remove_id, $buyer_id);
    if ($stmt->execute()) $message = "✅ Product removed from cart.";
    $stmt->close();
}

// Fetch addresses
$addresses = [];
$default_address = null;
$stmt = $conn->prepare("SELECT * FROM addresses WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
    if ($row['is_default']) $default_address = $row;
}
$stmt->close();
if (!$default_address && count($addresses) > 0) $default_address = $addresses[0];

// Fetch cart items with NEW price data
$cartItems = [];
$total = 0;
$delivery_fee = 0;

$stmt = $conn->prepare("
    SELECT c.id AS cart_id, p.*, c.quantity AS cart_quantity, c.delivery_mode,
           c.base_price, c.buyer_price, c.final_price, c.discount_percent, c.is_festive, c.markup_amount
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.buyer_id = ?
");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();

while ($item = $result->fetch_assoc()) {
    // Use price columns from cart
    $basePrice = $item['base_price'] ?? $item['price'];
    $buyerPrice = $item['buyer_price'] ?? $basePrice * 1.11;
    $finalPrice = $item['final_price'] ?? $buyerPrice;
    $isFestive = $item['is_festive'] ?? false;
    $discountPercent = $item['discount_percent'] ?? 0;
    $markupAmount = $item['markup_amount'] ?? ($buyerPrice - $basePrice);
    
    // If missing data, fetch fresh from pricing functions
    if ($buyerPrice === null || $buyerPrice == 0) {
        $priceInfo = getDisplayPrice($item, $conn);
        $buyerPrice = $priceInfo['buyer_price'];
        $finalPrice = $priceInfo['final_price'];
        $discountPercent = $priceInfo['discount_percent'];
        $isFestive = $priceInfo['is_festive'];
        $markupAmount = $priceInfo['markup_amount'];
        
        // Update cart with correct data
        $updateStmt = $conn->prepare("
            UPDATE cart 
            SET base_price = ?, buyer_price = ?, final_price = ?, 
                discount_percent = ?, is_festive = ?, markup_amount = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param(
            "ddddidi",
            $basePrice,
            $buyerPrice,
            $finalPrice,
            $discountPercent,
            $isFestive,
            $markupAmount,
            $item['cart_id']
        );
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    $item_total = $finalPrice * $item['cart_quantity'];
    
    $cartItems[] = [
        'id' => $item['cart_id'],
        'product_id' => $item['id'],
        'name' => $item['name'],
        'base_price' => $basePrice,
        'buyer_price' => $buyerPrice,
        'final_price' => $finalPrice,
        'discount_percent' => $discountPercent,
        'is_festive' => $isFestive,
        'markup_amount' => $markupAmount,
        'image' => $item['image'],
        'quantity' => $item['cart_quantity'],
        'total' => $item_total,
        'delivery_mode' => $item['delivery_mode']
    ];
    $total += $item_total;
}
$stmt->close();

// UPDATED: Calculate delivery fee based on LAGOS PICKUP STATION FEES
if ($default_address) {
    // Use the getDeliveryFee() from festive_functions.php
    $delivery_fee = getDeliveryFee($total, $default_address['state']);
} else {
    // Default to Lagos if no address
    $delivery_fee = getDeliveryFee($total, 'lagos');
}

// Get delivery tier info for display
$delivery_info = getDeliveryTierInfo($total, $default_address ? $default_address['state'] : 'lagos');

// Calculate festive savings only
$festiveSavings = 0;

foreach ($cartItems as $item) {
    if ($item['is_festive']) {
        $festiveSavings += ($item['buyer_price'] - $item['final_price']) * $item['quantity'];
    }
}

$_SESSION['cart_total'] = $total;
$_SESSION['cart_delivery_fee'] = $delivery_fee;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>My Cart - Relaxo Wears</title>
<style>
body { font-family: 'Roboto', sans-serif; background: #f4f4f4; margin:0; padding:0; }
.container { max-width:900px; margin:30px auto; padding:25px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); }
h2 { text-align:center; color:#1a4d2e; margin-bottom:25px; }
.message { background:#e0ffe0; color:#2e7d4c; padding:12px; border-radius:6px; text-align:center; margin-bottom:20px; }

/* NEW: Festive badge styles */
.festive-badge {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    display: inline-block;
    margin-left: 8px;
}
.original-price {
    text-decoration: line-through;
    color: #999;
    font-size: 14px;
    margin-right: 5px;
}
.festive-price {
    color: #e74c3c;
    font-weight: bold;
    font-size: 16px;
}
.normal-price {
    color: #1a4d2e;
    font-weight: bold;
    font-size: 16px;
}

.cart-item { 
    display:flex; 
    align-items:flex-start; 
    border-bottom:1px solid #ddd; 
    padding:15px 0;
    position: relative;
}
.cart-item.festive-item {
    background: #fff8f8;
    border-left: 4px solid #e74c3c;
    border-radius: 4px;
    margin: 5px 0;
}
.cart-item img { width:100px; height:100px; object-fit:cover; border-radius:10px; margin-right:15px; }
.cart-info { flex:1; }
.cart-info h4 { margin:0 0 6px 0; color:#333; }
.cart-info p { margin:4px 0; font-size:14px; color:#555; }
.remove-form { margin-left:15px; align-self: center; }
.remove-btn { background:crimson; color:#fff; padding:8px 16px; border:none; border-radius:6px; cursor:pointer; transition:0.3s; font-size:14px; }
.remove-btn:hover { background:darkred; transform:scale(1.05); }
.summary { text-align:right; margin-top:30px; border-top:2px solid #ccc; padding-top:15px; }
.summary h4 { margin:8px 0; }
.checkout-btn { background:#1a4d2e; color:#fff; padding:12px 24px; border:none; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; margin-top:15px; transition:0.3s; }
.checkout-btn:hover { background:#2e7d4c; transform:scale(1.02); }
.address-box { background:#f0f8f0; padding:12px; margin-bottom:20px; border-radius:6px; border-left:4px solid #2e7d4c; }
.delivery-option { margin:20px 0; font-size:16px; padding:15px; background:#f9f9f9; border-radius:8px; }
.delivery-option label { margin-right:20px; display:inline-block; margin-bottom:8px; }
.delivery-option strong { display:block; margin-bottom:10px; color:#1a4d2e; }

/* REMOVED: markup-savings and service fee displays */

/* Savings displays */
.savings-info {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 12px;
    margin: 15px 0;
    text-align: center;
    font-weight: 500;
    color: #856404;
}
.savings-info strong {
    color: #e74c3c;
}
.delivery-note {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
.lagos-badge {
    background: #198754;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 5px;
}
.delivery-tier-box {
    background: #e8f4fd;
    border: 1px solid #b6d4fe;
    border-radius: 8px;
    padding: 12px;
    margin: 15px 0;
    color: #084298;
}
.delivery-tier-box strong {
    color: #0a58ca;
}

@media (max-width: 768px) {
    .container { padding: 15px; margin: 10px; }
    .cart-item { flex-direction: column; }
    .cart-item img { width: 100%; height: auto; margin-bottom: 10px; }
    .remove-form { margin-left: 0; margin-top: 10px; }
}
</style>
</head>
<body>
<div class="container">
<h2>🛒 My Cart</h2>

<?php if($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- UPDATED: Only show festive savings -->
<?php if($festiveSavings > 0): ?>
<div class="savings-info">
    🎉 <strong>Festive Savings!</strong> You're saving ₦<?= number_format($festiveSavings, 2) ?> on this order!
</div>
<?php endif; ?>

<!-- Delivery Tier Information -->
<div class="delivery-tier-box">
    <strong>📍 Pickup Station Delivery</strong><br>
    <div style="font-size: 14px;">
        <strong>Order Tier:</strong> <?= $delivery_info['tier'] ?> (<?= $delivery_info['range'] ?>)<br>
        <strong>Your Order:</strong> ₦<?= number_format($total, 2) ?><br>
        <strong>Location:</strong> 
        <?php if($default_address): ?>
            <?= htmlspecialchars($default_address['state']) ?>
            <?php if($delivery_info['is_lagos']): ?>
                <span class="lagos-badge">Lagos Rates</span>
            <?php endif; ?>
        <?php else: ?>
            Not set (Using Lagos rates)
        <?php endif; ?><br>
        <strong>Pickup Fee:</strong> ₦<?= number_format($delivery_fee, 2) ?>
    </div>
    
    <!-- Fee Comparison -->
    <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 6px; font-size: 12px;">
        <strong>💡 Lagos vs Other States:</strong><br>
        <small>
            • <strong>Lagos:</strong> ₦2,500 / ₦3,700 / ₦8,200<br>
            • <strong>Other States:</strong> ₦3,500 / ₦7,500 / ₦10,500
        </small>
    </div>
</div>

<?php if($default_address): ?>
<div class="address-box">
    <strong>📦 Deliver To:</strong><br>
    <?= htmlspecialchars($default_address['address']) ?><br>
    <?= htmlspecialchars($default_address['city']) ?>, <?= htmlspecialchars($default_address['state']) ?><br>
    📞 <?= htmlspecialchars($default_address['phone_number']) ?> 
    <?= $default_address['alt_phone_number'] ? '/ '.htmlspecialchars($default_address['alt_phone_number']) : '' ?>
</div>
<?php else: ?>
<div class="address-box" style="color:crimson; background:#f8d7da; border-color:#f5c6cb;">
    ❌ <strong>No address found.</strong> Please <a href="my_account.php" style="color:#721c24; font-weight:bold;">add an address</a> to proceed.
</div>
<?php endif; ?>

<?php if(empty($cartItems)): ?>
    <p style="text-align:center; padding:30px; color:#666;">Your cart is empty. Start shopping!</p>
<?php else: ?>
    <?php foreach($cartItems as $item): ?>
    <div class="cart-item <?php echo $item['is_festive'] ? 'festive-item' : ''; ?>">
        <img src="<?= htmlspecialchars($item['image']) ?>" alt="Product">
        <div class="cart-info">
            <h4>
                <?= htmlspecialchars($item['name']) ?>
                <?php if($item['is_festive']): ?>
                    <span class="festive-badge"><?= $item['discount_percent'] ?>% OFF</span>
                <?php endif; ?>
            </h4>
            
            <!-- UPDATED: Clean price display - NO service fee mention -->
            <p>
                <?php if($item['is_festive']): ?>
                    <span class="original-price">₦<?= number_format($item['buyer_price'], 2) ?></span>
                    <span class="festive-price">₦<?= number_format($item['final_price'], 2) ?></span>
                <?php else: ?>
                    Price: <span class="normal-price">₦<?= number_format($item['final_price'], 2) ?></span>
                <?php endif; ?>
            </p>
            
            <p>Quantity: <?= $item['quantity'] ?></p>
            <p>Total: <strong>₦<?= number_format($item['total'], 2) ?></strong></p>
            
            <?php if($item['is_festive']): ?>
                <p style="color: #27ae60; font-size: 12px; font-weight:500;">
                    💰 You save: ₦<?= number_format(($item['buyer_price'] - $item['final_price']) * $item['quantity'], 2) ?>
                </p>
            <?php endif; ?>
        </div>
        <form method="POST" class="remove-form">
            <input type="hidden" name="remove_cart_id" value="<?= $item['id'] ?>">
            <button type="submit" class="remove-btn">Remove</button>
        </form>
    </div>
    <?php endforeach; ?>

    <form method="POST" action="order_product.php" id="checkoutForm">
        <!-- REMOVED: Home delivery option - only pickup stations -->
        <div class="delivery-option">
            <strong>🚚 Delivery Method:</strong><br>
            <label>
                <input type="radio" name="delivery_mode" value="dropoff" checked> 
                Pickup Station Only - ₦<?= number_format($delivery_fee,2) ?>
            </label>
            <div style="font-size: 14px; color: #666; margin-top: 5px;">
                • Collect your order at nearest pickup station<br>
                • Bring your order ID for verification<br>
                • 24-48 hours delivery time
            </div>
        </div>

        <!-- UPDATED: Store ALL price data for checkout -->
        <?php foreach($cartItems as $item): ?>
            <input type="hidden" name="product_ids[]" value="<?= $item['product_id'] ?>">
            <input type="hidden" name="quantities[]" value="<?= $item['quantity'] ?>">
            <input type="hidden" name="base_prices[]" value="<?= $item['base_price'] ?>">
            <input type="hidden" name="buyer_prices[]" value="<?= $item['buyer_price'] ?>">
            <input type="hidden" name="final_prices[]" value="<?= $item['final_price'] ?>">
            <input type="hidden" name="is_festive[]" value="<?= $item['is_festive'] ? '1' : '0' ?>">
            <input type="hidden" name="markup_amounts[]" value="<?= $item['markup_amount'] ?>">
        <?php endforeach; ?>

        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" name="from_cart" value="1">
        <input type="hidden" name="final_delivery_fee" value="<?= $delivery_fee ?>">
        <input type="hidden" name="final_grand_total" value="<?= $total + $delivery_fee ?>">

        <div class="summary">
            <h4>🏷️ Products Total: ₦<?= number_format($total,2) ?></h4>
            
            <?php if($festiveSavings > 0): ?>
                <h4 style="color: #27ae60;">💰 Festive Savings: -₦<?= number_format($festiveSavings, 2) ?></h4>
            <?php endif; ?>
            
            <!-- REMOVED: Service fee display line -->
            
            <h4>🚚 Pickup Station Fee: ₦<?= number_format($delivery_fee,2) ?></h4>
            <h3 style="color:#1a4d2e; margin-top:15px;">Grand Total: ₦<?= number_format($total + $delivery_fee,2) ?></h3>
            
            <?php if($default_address): ?>
                <button type="submit" class="checkout-btn">
                    <?php echo $festiveSavings > 0 ? '🎉 Proceed to Checkout' : 'Proceed to Checkout'; ?>
                </button>
            <?php else: ?>
                <button type="button" class="checkout-btn" style="background:#ccc; cursor:not-allowed;" disabled>
                    ❌ Add Address to Checkout
                </button>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>
</div>

<script>
// Get location for delivery
navigator.geolocation.getCurrentPosition(
    pos => { 
        document.getElementById('latitude').value = pos.coords.latitude; 
        document.getElementById('longitude').value = pos.coords.longitude; 
    },
    err => { 
        console.warn("📍 Location access denied or unavailable."); 
        // Set default coordinates (Lagos)
        document.getElementById('latitude').value = '6.5244';
        document.getElementById('longitude').value = '3.3792';
    }
);
</script>
</body>
</html>