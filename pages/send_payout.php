<?php
session_start();
include '../includes/db.php';
include '../includes/mailer_config.php';
include '../includes/paypal_config.php'; // This file should return access token

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Validate order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("Invalid or missing order ID.");
}

$order_id = intval($_GET['order_id']);

// Get order details WITH VENDOR'S PLAN AND DELIVERY MODE
$stmt = $conn->prepare("
    SELECT 
        o.id, 
        o.vendor_earnings, 
        o.total_amount, 
        o.commission,
        o.base_price,
        o.buyer_price,
        u.paypal_email, 
        u.email AS vendor_email, 
        u.name AS vendor_name,
        u.delivery_mode,
        u.subscription_plan_id,
        p.name AS product_name,
        vsp.commission_percent AS plan_commission,
        vsp.name AS plan_name
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON p.vendor_id = u.id
    LEFT JOIN vendor_subscription_plans vsp ON u.subscription_plan_id = vsp.id
    WHERE o.id = ? AND o.status = 'approved' AND o.payout_status = 'pending'
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("This order is either invalid, already paid, or not approved yet.");
}

$order = $result->fetch_assoc();

// ====================================================
// 🔧 CRITICAL FIX: VERIFY COMMISSION WAS CALCULATED CORRECTLY
// ====================================================

$plan_commission = $order['plan_commission'] ?? 8.00; // Basic plan default
$delivery_mode = $order['delivery_mode'] ?? 'platform';
$is_premium = ($order['plan_name'] ?? 'Basic') === 'Premium';

// Apply your commission matrix
if ($delivery_mode == 'vendor') {
    $correct_commission_rate = $plan_commission; // 8% or 5%
} else {
    $correct_commission_rate = $plan_commission + 3.00; // 11% or 8%
}

// Calculate what vendor SHOULD earn
$base_price = $order['base_price'] ?? $order['total_amount']; // Fallback
$correct_commission_amount = $base_price * ($correct_commission_rate / 100);
$correct_vendor_earnings = $base_price - $correct_commission_amount;

// Check if stored earnings match correct calculation
$stored_vendor_earnings = $order['vendor_earnings'];
$discrepancy = abs($stored_vendor_earnings - $correct_vendor_earnings);

// Log discrepancies for auditing
if ($discrepancy > 0.01) { // More than 1 kobo difference
    $log_message = date('Y-m-d H:i:s') . " | Order #$order_id | ";
    $log_message .= "Stored: ₦$stored_vendor_earnings | Calculated: ₦$correct_vendor_earnings | ";
    $log_message .= "Plan: {$order['plan_name']} | Delivery: $delivery_mode | Rate: $correct_commission_rate%\n";
    file_put_contents("commission_audit_log.txt", $log_message, FILE_APPEND);
    
    // For now, use correct calculation (optional: you can ask admin to verify)
    $payoutAmount = $correct_vendor_earnings;
    
    // Update database with correct values
    $update_commission = $conn->prepare("
        UPDATE orders 
        SET vendor_earnings = ?, 
            commission = ?,
            commission_rate = ?
        WHERE id = ?
    ");
    $update_commission->bind_param("dddi", 
        $correct_vendor_earnings,
        $correct_commission_amount,
        $correct_commission_rate,
        $order_id
    );
    $update_commission->execute();
} else {
    $payoutAmount = $stored_vendor_earnings;
}

$paypalEmail = $order['paypal_email'];

// ====================================================
// 📊 SHOW ADMIN COMMISSION BREAKDOWN (FOR TRANSPARENCY)
// ====================================================
echo "<div style='background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #ddd;'>";
echo "<h4>💰 Commission Breakdown for Order #$order_id</h4>";
echo "<table style='width:100%; border-collapse:collapse;'>";
echo "<tr><td style='padding:5px;'>Vendor Name:</td><td><strong>{$order['vendor_name']}</strong></td></tr>";
echo "<tr><td style='padding:5px;'>Vendor Plan:</td><td>{$order['plan_name']} Plan</td></tr>";
echo "<tr><td style='padding:5px;'>Delivery Mode:</td><td>" . ucfirst($delivery_mode) . "</td></tr>";
echo "<tr><td style='padding:5px;'>Applied Commission Rate:</td><td><strong>{$correct_commission_rate}%</strong></td></tr>";
echo "<tr><td style='padding:5px;'>Product Price:</td><td>₦" . number_format($base_price, 2) . "</td></tr>";
echo "<tr><td style='padding:5px;'>Platform Commission:</td><td>₦" . number_format($correct_commission_amount, 2) . "</td></tr>";
echo "<tr style='background:#e8f5e9;'><td style='padding:5px;'><strong>Vendor Payout:</strong></td><td><strong>₦" . number_format($payoutAmount, 2) . "</strong></td></tr>";
echo "</table>";

if ($discrepancy > 0.01) {
    echo "<p style='color:#e74c3c; font-weight:bold;'>⚠️ Commission discrepancy detected and corrected. See audit log.</p>";
}
echo "</div>";

// Step 1: Get PayPal access token
$access_token = getPayPalAccessToken();
if (!$access_token) {
    die("Unable to get PayPal access token.");
}

// Step 2: Prepare payout request
$payoutData = [
    "sender_batch_header" => [
        "sender_batch_id" => uniqid(),
        "email_subject" => "You have a payout from Relaxo Wears"
    ],
    "items" => [[
        "recipient_type" => "EMAIL",
        "amount" => [
            "value" => number_format($payoutAmount, 2, '.', ''),
            "currency" => "USD"
        ],
        "receiver" => $paypalEmail,
        "note" => "Relaxo Wears order payout - Commission Rate: {$correct_commission_rate}%",
        "sender_item_id" => "item_{$order_id}"
    ]]
];

// Step 3: Send payout via PayPal
$ch = curl_init("https://api.sandbox.paypal.com/v1/payments/payouts");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $access_token"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payoutData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_status === 201) {
    // Mark order as paid AND STORE COMMISSION DETAILS
    $update = $conn->prepare("
        UPDATE orders 
        SET payout_status = 'paid', 
            status = 'completed',
            commission_rate = ?,
            commission = ?
        WHERE id = ?
    ");
    $update->bind_param("ddi", $correct_commission_rate, $correct_commission_amount, $order_id);
    $update->execute();

    // Also record in vendor_transactions table for transparency
    $record_transaction = $conn->prepare("
        INSERT INTO vendor_transactions 
        (vendor_id, order_id, product_id, quantity, base_price, buyer_price, 
         commission_percent, commission_amount, vendor_earnings, vendor_paid, payment_date)
        SELECT 
            p.vendor_id, o.id, o.product_id, o.quantity, 
            o.base_price, o.buyer_price, 
            ?, ?, ?, 
            1, NOW()
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.id = ?
    ");
    $record_transaction->bind_param("dddi", 
        $correct_commission_rate,
        $correct_commission_amount,
        $payoutAmount,
        $order_id
    );
    $record_transaction->execute();

    // Send email receipt to vendor WITH COMMISSION DETAILS
    $subject = "Relaxo Wears - Payout Confirmation for Order #$order_id";
    $body = "
        <h2>Payout Confirmation</h2>
        <p>Dear {$order['vendor_name']},</p>
        <p>Your payout of <strong>₦" . number_format($payoutAmount, 2) . "</strong> has been processed.</p>
        
        <h3>Order Details:</h3>
        <table style='border-collapse:collapse; width:100%; max-width:500px;'>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Order ID:</strong></td><td style='padding:8px; border:1px solid #ddd;'>#{$order_id}</td></tr>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Product:</strong></td><td style='padding:8px; border:1px solid #ddd;'>{$order['product_name']}</td></tr>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Your Plan:</strong></td><td style='padding:8px; border:1px solid #ddd;'>{$order['plan_name']}</td></tr>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Delivery Mode:</strong></td><td style='padding:8px; border:1px solid #ddd;'>" . ucfirst($delivery_mode) . "</td></tr>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Commission Rate:</strong></td><td style='padding:8px; border:1px solid #ddd;'>{$correct_commission_rate}%</td></tr>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Product Price:</strong></td><td style='padding:8px; border:1px solid #ddd;'>₦" . number_format($base_price, 2) . "</td></tr>
            <tr><td style='padding:8px; border:1px solid #ddd;'><strong>Platform Commission:</strong></td><td style='padding:8px; border:1px solid #ddd;'>₦" . number_format($correct_commission_amount, 2) . "</td></tr>
            <tr style='background:#e8f5e9;'><td style='padding:8px; border:1px solid #ddd;'><strong>Your Earnings:</strong></td><td style='padding:8px; border:1px solid #ddd;'><strong>₦" . number_format($payoutAmount, 2) . "</strong></td></tr>
        </table>
        
        <p><strong>Payment Method:</strong> PayPal ({$paypalEmail})</p>
        
        <p style='margin-top:20px; color:#666; font-size:12px;'>
            Commission applied: {$order['plan_name']} Plan ({$plan_commission}%) + " . ucfirst($delivery_mode) . " Delivery
        </p>
        
        <p>Thank you for partnering with Relaxo Wears.</p>
    ";
    sendEmail($order['vendor_email'], $subject, $body);

    echo "<script>
        alert('✅ Payout sent successfully!\\n\\nVendor: {$order['vendor_name']}\\nAmount: ₦" . number_format($payoutAmount, 2) . "\\nCommission Rate: {$correct_commission_rate}%');
        window.location.href='admin_orders.php';
    </script>";
} else {
    // Log detailed error
    $error_log = date('Y-m-d H:i:s') . " | Order #$order_id | ";
    $error_log .= "HTTP Status: $http_status | Response: " . json_encode(json_decode($response, true)) . "\n";
    file_put_contents("payout_error_log.txt", $error_log, FILE_APPEND);
    
    echo "<script>
        alert('❌ Payout failed (HTTP $http_status). Check error log for details.');
        window.location.href='admin_orders.php';
    </script>";
}
?>