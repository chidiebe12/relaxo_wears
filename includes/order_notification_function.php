<?php
function send_buyer_notification($conn, $buyer_id, $message, $order_id = null) {
    $stmt = $conn->prepare("INSERT INTO buyer_notifications (buyer_id, message, order_id, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("isi", $buyer_id, $message, $order_id);
    $stmt->execute();
    $stmt->close();
}
?>
