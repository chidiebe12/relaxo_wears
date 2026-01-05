<?php
include_once __DIR__ . '/load_env.php';
loadEnv();

// Load Paystack keys from .env
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY'));
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY'));

if (!PAYSTACK_SECRET_KEY || !PAYSTACK_PUBLIC_KEY) {
    die("⚠️ Paystack keys not properly configured in the .env file.");
}

// Optional: function to verify transaction
function verifyPaystackTransaction($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || empty($result['data'])) return false;

    $result = json_decode($response, true);
    return ($result['status'] && $result['data']['status'] === 'success') ? $result['data'] : false;
}
?>
