<?php
// Load environment variables
include 'load_env.php';
loadEnv();

function getPayPalAccessToken() {
    $clientId = getenv('PAYPAL_CLIENT_ID');
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET');

    $paypal_url = getPayPalApiBase() . "/v1/oauth2/token";

    $ch = curl_init($paypal_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$clientSecret");
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Accept-Language: en_US"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("PayPal Token Error ($http_code): $response");
        return false;
    }

    $json = json_decode($response, true);
    return $json['access_token'] ?? false;
}

function getPayPalApiBase() {
    $mode = getenv('PAYPAL_MODE');
    return $mode === 'live' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
}
