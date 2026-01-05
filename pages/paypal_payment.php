<?php
session_start();
include '../includes/db.php';
include '../includes/paypal_config.php';
include '../includes/mailer_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'start';

// Fetch admin email
$stmt = $conn->prepare("SELECT paypal_email FROM admin_settings WHERE id = 1");
$stmt->execute();
$admin_settings = $stmt->get_result()->fetch_assoc();
$admin_email = $admin_settings['paypal_email'] ?? 'samuelfortune264@gmail.com';
$stmt->close();

$paypal_api = getPayPalApiBase();

// ========== 1. Start Payment ==========
if ($action === 'start' && isset($_GET['order_ids'])) {
    $order_ids = explode(',', $_GET['order_ids']);
    $from_cart = isset($_GET['from_cart']) && $_GET['from_cart'] == 1;

    $total_amount = 0;
    $valid_orders = [];
    foreach ($order_ids as $oid) {
        $oid = intval($oid);
        $stmt = $conn->prepare("
            SELECT o.total_amount, o.payment_status, p.name AS product_name, p.image, u.email, u.name AS buyer_name 
            FROM orders o 
            JOIN products p ON o.product_id = p.id 
            JOIN users u ON o.buyer_id = u.id
            WHERE o.id = ? AND o.buyer_id = ? AND o.payment_status = 'unpaid'
        ");
        $stmt->bind_param("ii", $oid, $buyer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $order = $result->fetch_assoc();
            $total_amount += floatval($order['total_amount']);
            $valid_orders[] = [
                'id' => $oid,
                'email' => $order['email'],
                'product_name' => $order['product_name'],
                'image' => $order['image'],
                'buyer_name' => $order['buyer_name']
            ];
        }
        $stmt->close();
    }

    if (empty($valid_orders)) {
        echo "<script>alert('❌ No valid unpaid orders found.'); window.location.href='buyer_dashboard.php';</script>";
        exit();
    }

    $amountUSD = number_format($total_amount / 1600, 2, '.', ''); // Adjust exchange rate
    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        error_log("PayPal access token failed");
        die("Failed to connect to PayPal.");
    }

    $redirect_url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $paymentData = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "amount" => ["value" => $amountUSD, "currency_code" => "USD"],
            "description" => "Relaxo Wears Order(s): " . implode(', ', array_column($valid_orders, 'product_name')),
            "custom_id" => implode(',', array_column($valid_orders, 'id')) . "|$buyer_id"
        ]],
        "application_context" => [
            "return_url" => "$redirect_url?action=success&order_ids=" . implode(',', array_column($valid_orders, 'id')) . "&from_cart=" . ($from_cart ? 1 : 0),
            "cancel_url" => "$redirect_url?action=cancel"
        ]
    ];

    $ch = curl_init("$paypal_api/v2/checkout/orders");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $access_token"
        ],
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = json_decode(curl_exec($ch), true);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201 && isset($response['links'])) {
        $_SESSION['payment_order_ids'] = array_column($valid_orders, 'id');
        foreach ($response['links'] as $link) {
            if ($link['rel'] === 'approve') {
                header("Location: " . $link['href']);
                exit();
            }
        }
    }

    error_log("PayPal order creation failed: " . json_encode($response));
    die("No PayPal approval URL found.");

// ========== 2. Execute on Success ==========
} elseif ($action === 'success' && isset($_GET['order_ids'], $_GET['token'])) {
    $order_ids = explode(',', $_GET['order_ids']);
    $from_cart = isset($_GET['from_cart']) && $_GET['from_cart'] == 1;
    $paymentId = $_GET['token'];

    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        error_log("PayPal access token failed on success");
        die("Could not get PayPal token.");
    }

    $ch = curl_init("$paypal_api/v2/checkout/orders/$paymentId/capture");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $access_token"
        ],
        CURLOPT_POST => 1,
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = json_decode(curl_exec($ch), true);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201 && $response['status'] === 'COMPLETED') {
        $orders = [];
        foreach ($order_ids as $oid) {
            $oid = intval($oid);
            $stmt = $conn->prepare("UPDATE orders SET payment_status='paid', status='approved', payout_status='pending' WHERE id = ? AND buyer_id = ? AND payment_status = 'unpaid'");
            $stmt->bind_param("ii", $oid, $buyer_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                $stmt = $conn->prepare("
                    SELECT o.*, u.name AS buyer_name, u.email, p.name AS product_name, p.image 
                    FROM orders o 
                    JOIN users u ON o.buyer_id = u.id 
                    JOIN products p ON o.product_id = p.id 
                    WHERE o.id = ?
                ");
                $stmt->bind_param("i", $oid);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $orders[] = $order;

                $msg = "Your PayPal payment for '{$order['product_name']}' was successful.";
                $stmt = $conn->prepare("INSERT INTO buyer_notifications (buyer_id, message, order_id) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $buyer_id, $msg, $oid);
                $stmt->execute();
                $stmt->close();

                sendEmail($admin_email, "✅ New PayPal Payment - Relaxo Wears", "
                    <h2>New Payment</h2>
                    <p>Buyer <strong>{$order['buyer_name']}</strong> has paid for order #{$oid}.</p>
                ");
                sendEmail($order['email'], "✅ Payment Confirmed - Relaxo Wears", "
                    <h2>Hello {$order['buyer_name']},</h2>
                    <p>Thanks for your PayPal payment for <strong>{$order['product_name']}</strong>.</p>
                    <p>We’ll begin processing immediately.</p>
                ");
            }
        }

        if ($from_cart) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE buyer_id = ?");
            $stmt->bind_param("i", $buyer_id);
            $stmt->execute();
            $stmt->close();
        }

        unset($_SESSION['payment_order_ids']);
        unset($_SESSION['orders_created']);
        ?>

        <!DOCTYPE html>
        <html>
        <head>
            <title>Order Confirmation - Relaxo Wears</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; }
                .container { max-width: 700px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; }
                h2 { color: darkgreen; text-align: center; }
                .product { display: flex; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                .product img { width: 100px; height: auto; border-radius: 6px; margin-right: 15px; }
                .product-info { flex: 1; }
                .btn { padding: 12px 20px; margin: 10px 0; border: none; border-radius: 6px; cursor: pointer; background-color: #3aaf85; color: white; }
            </style>
        </head>
        <body>
        <div class="container">
            <h2>✅ Order Confirmed!</h2>
            <p>Thank you for your payment. Your order is being processed.</p>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <div class="product">
                        <img src="<?= htmlspecialchars($order['image']) ?>" alt="<?= htmlspecialchars($order['product_name']) ?>">
                        <div class="product-info">
                            <div><strong><?= htmlspecialchars($order['product_name']) ?></strong></div>
                            <div>Order ID: <?= $order['id'] ?></div>
                            <div>Quantity: <?= $order['quantity'] ?></div>
                            <div>Total: ₦<?= number_format($order['total_amount'], 2) ?></div>
                            <div>Status: <?= ucfirst($order['status']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No order details available.</p>
            <?php endif; ?>
            <a href="buyer_dashboard.php"><button class="btn">Back to Dashboard</button></a>
        </div>
        </body>
        </html>

        <?php
        exit();
    } else {
        error_log("PayPal capture failed: " . json_encode($response));
        echo "<script>alert('❌ Payment failed to execute.'); window.location.href='buyer_dashboard.php';</script>";
    }

// ========== 3. Cancel ==========
} elseif ($action === 'cancel') {
    echo "<script>alert('Payment cancelled.'); window.location.href='buyer_dashboard.php';</script>";

// ========== 4. IPN Handler ==========
} elseif ($action === 'ipn') {
    $raw_post_data = file_get_contents('php://input');
    $raw_post_array = explode('&', $raw_post_data);
    $myPost = [];
    foreach ($raw_post_array as $keyval) {
        $keyval = explode('=', $keyval);
        if (count($keyval) == 2) {
            $myPost[$keyval[0]] = urldecode($keyval[1]);
        }
    }

    $req = 'cmd=_notify-validate';
    foreach ($myPost as $key => $value) {
        $value = urlencode($value);
        $req .= "&$key=$value";
    }

    $ch = curl_init("$paypal_api/cgi-bin/webscr");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Connection: Keep-Alive'],
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $req,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === 'VERIFIED' && isset($myPost['custom'])) {
        list($order_ids, $buyer_id) = explode('|', $myPost['custom']);
        $order_ids = explode(',', $order_ids);
        $buyer_id = intval($buyer_id);

        foreach ($order_ids as $oid) {
            $oid = intval($oid);
            $stmt = $conn->prepare("UPDATE orders SET payment_status='paid', status='approved', payout_status='pending' WHERE id = ? AND buyer_id = ? AND payment_status = 'unpaid'");
            $stmt->bind_param("ii", $oid, $buyer_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                $stmt = $conn->prepare("
                    SELECT o.*, u.name AS buyer_name, u.email, p.name AS product_name 
                    FROM orders o 
                    JOIN users u ON o.buyer_id = u.id 
                    JOIN products p ON o.product_id = p.id 
                    WHERE o.id = ?
                ");
                $stmt->bind_param("i", $oid);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $msg = "Your PayPal payment for '{$order['product_name']}' was successful.";
                $stmt = $conn->prepare("INSERT INTO buyer_notifications (buyer_id, message, order_id) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $buyer_id, $msg, $oid);
                $stmt->execute();
                $stmt->close();

                sendEmail($admin_email, "✅ New PayPal Payment - Relaxo Wears", "
                    <h2>New Payment</h2>
                    <p>Buyer <strong>{$order['buyer_name']}</strong> has paid for order #{$oid}.</p>
                ");
                sendEmail($order['email'], "✅ Payment Confirmed - Relaxo Wears", "
                    <h2>Hello {$order['buyer_name']},</h2>
                    <p>Thanks for your PayPal payment for <strong>{$order['product_name']}</strong>.</p>
                    <p>We’ll begin processing immediately.</p>
                ");
            }
        }
    }

    http_response_code(200);
    exit();

// ========== Invalid ==========
} else {
    echo "<script>alert('⚠️ Invalid request.'); window.location.href='buyer_dashboard.php';</script>";
}
?>