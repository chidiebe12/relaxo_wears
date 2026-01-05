<?php
session_start();
include '../includes/db.php';
include '../includes/mailer_config.php';
include '../includes/paystack_config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: ../buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];

// Fetch admin email
$stmt = $conn->prepare("SELECT paypal_email FROM admin_settings WHERE id = 1");
$stmt->execute();
$admin_settings = $stmt->get_result()->fetch_assoc();
$admin_email = $admin_settings['paypal_email'] ?? 'samuelfortune264@gmail.com';
$stmt->close();

// ========== 1. START PAYMENT ==========
if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['order_ids'])) {
    $order_ids = explode(',', $_GET['order_ids']);
    $from_cart = isset($_GET['from_cart']) && $_GET['from_cart'] == 1;

    // Validate orders
    $total_amount = 0;
    $valid_orders = [];
    foreach ($order_ids as $oid) {
        $oid = intval($oid);
        $stmt = $conn->prepare("
            SELECT o.total_amount, o.payment_status, u.email, u.name AS buyer_name, p.name AS product_name, p.image
            FROM orders o
            JOIN users u ON o.buyer_id = u.id
            JOIN products p ON o.product_id = p.id
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
                'image' => $order['image']
            ];
        }
        $stmt->close();
    }

    if (empty($valid_orders)) {
        echo "<script>alert('❌ No valid unpaid orders found.'); window.location.href='buyer_dashboard.php';</script>";
        exit();
    }

    $email = $valid_orders[0]['email'];
    $amount_kobo = $total_amount * 100;
    $description = "Relaxo Wears Order(s): " . implode(', ', array_column($valid_orders, 'product_name'));

    $_SESSION['payment_order_ids'] = array_column($valid_orders, 'id');
    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <title>Relaxo Wears - Pay with PayStack</title>
        <script src="https://js.paystack.co/v1/inline.js"></script>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; }
            .container { max-width: 700px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; }
            h2, h3 { color: darkgreen; text-align: center; }
        </style>
    </head>
    <body>
    <div class="container">
        <h3>🔄 Processing payment for <?= htmlspecialchars($description) ?> (₦<?= number_format($total_amount, 2) ?>)...</h3>
    </div>
    <script>
        let handler = PaystackPop.setup({
            key: '<?= PAYSTACK_PUBLIC_KEY ?>',
            email: '<?= htmlspecialchars($email) ?>',
            amount: <?= $amount_kobo ?>,
            currency: 'NGN',
            ref: 'RW<?= time() . rand(1000, 9999) ?>',
            metadata: {
                order_ids: '<?= implode(',', array_column($valid_orders, 'id')) ?>',
                buyer_id: <?= $buyer_id ?>
            },
            callback: function(response) {
                window.location.href = "paystack_payment.php?action=success&ref=" + response.reference + "&from_cart=<?= $from_cart ? 1 : 0 ?>";
            },
            onClose: function () {
                alert('Transaction cancelled.');
                window.location.href = "buyer_dashboard.php";
            }
        });
        handler.openIframe();
    </script>
    </body>
    </html>

<?php
// ========== 2. VERIFY SUCCESS ==========
} elseif (isset($_GET['action']) && $_GET['action'] === 'success' && isset($_GET['ref'])) {
    $reference = $_GET['ref'];
    $from_cart = isset($_GET['from_cart']) && $_GET['from_cart'] == 1;

    $transaction = verifyPaystackTransaction($reference);

    if ($transaction && $transaction['status'] === 'success' && isset($_SESSION['payment_order_ids'])) {
        $orders = [];
        foreach ($_SESSION['payment_order_ids'] as $oid) {
            $stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'paid', status = 'approved', payout_status = 'pending' 
                WHERE id = ? AND buyer_id = ? AND payment_status = 'unpaid'
            ");
            $stmt->bind_param("ii", $oid, $buyer_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                $stmt = $conn->prepare("
                    SELECT o.*, p.name AS product_name, p.image, u.email, u.name AS buyer_name 
                    FROM orders o
                    JOIN products p ON o.product_id = p.id
                    JOIN users u ON o.buyer_id = u.id
                    WHERE o.id = ?
                ");
                $stmt->bind_param("i", $oid);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $orders[] = $order;

                $note = "Your payment for '{$order['product_name']}' has been confirmed.";
                $stmt = $conn->prepare("INSERT INTO buyer_notifications (buyer_id, message, order_id) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $buyer_id, $note, $oid);
                $stmt->execute();
                $stmt->close();

                sendEmail($admin_email, "✅ New PayStack Payment - Relaxo Wears", "
                    <h2>Buyer Paid</h2>
                    <p><strong>{$order['buyer_name']}</strong> completed payment for order #{$oid} via PayStack.</p>
                ");

                sendEmail($order['email'], "✅ Payment Confirmed - Relaxo Wears", "
                    <h2>Thanks, {$order['buyer_name']}!</h2>
                    <p>Your payment for <strong>{$order['product_name']}</strong> was successful.</p>
                    <p>We’ll process your delivery shortly.</p>
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
        error_log("Paystack verification failed for ref: $reference");
        echo "<script>alert('❌ Payment verification failed.'); window.location.href='buyer_dashboard.php';</script>";
    }

// ========== 3. WEBHOOK HANDLER ==========
} elseif (isset($_GET['action']) && $_GET['action'] === 'webhook') {
    $secret_key = PAYSTACK_SECRET_KEY;
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    $input = file_get_contents('php://input');
    if (hash_hmac('sha512', $input, $secret_key) !== $signature) {
        http_response_code(401);
        exit("Invalid signature");
    }

    $event = json_decode($input, true);
    if ($event['event'] === 'charge.success') {
        $reference = $event['data']['reference'];
        $transaction = verifyPaystackTransaction($reference);
        if ($transaction && $transaction['status'] === 'success') {
            $metadata = $event['data']['metadata'] ?? [];
            $order_ids = explode(',', $metadata['order_ids'] ?? '');
            $buyer_id = intval($metadata['buyer_id'] ?? 0);

            foreach ($order_ids as $oid) {
                $oid = intval($oid);
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET payment_status = 'paid', status = 'approved', payout_status = 'pending' 
                    WHERE id = ? AND buyer_id = ? AND payment_status = 'unpaid'
                ");
                $stmt->bind_param("ii", $oid, $buyer_id);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();

                if ($affected > 0) {
                    $stmt = $conn->prepare("
                        SELECT o.*, p.name AS product_name, u.email, u.name AS buyer_name 
                        FROM orders o
                        JOIN products p ON o.product_id = p.id
                        JOIN users u ON o.buyer_id = u.id
                        WHERE o.id = ?
                    ");
                    $stmt->bind_param("i", $oid);
                    $stmt->execute();
                    $order = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $note = "Your payment for '{$order['product_name']}' has been confirmed.";
                    $stmt = $conn->prepare("INSERT INTO buyer_notifications (buyer_id, message, order_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $buyer_id, $note, $oid);
                    $stmt->execute();
                    $stmt->close();

                    sendEmail($admin_email, "✅ New PayStack Payment - Relaxo Wears", "
                        <h2>Buyer Paid</h2>
                        <p><strong>{$order['buyer_name']}</strong> completed payment for order #{$oid} via PayStack.</p>
                    ");

                    sendEmail($order['email'], "✅ Payment Confirmed - Relaxo Wears", "
                        <h2>Thanks, {$order['buyer_name']}!</h2>
                        <p>Your payment for <strong>{$order['product_name']}</strong> was successful.</p>
                        <p>We’ll process your delivery shortly.</p>
                    ");
                }
            }
        }
    }

    http_response_code(200);
    exit();

// ========== 4. INVALID REQUEST ==========
} else {
    echo "<script>alert('⚠️ Invalid request.'); window.location.href='buyer_dashboard.php';</script>";
}
?>