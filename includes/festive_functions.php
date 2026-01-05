<?php
// includes/festive_functions.php

// ============================================
// PRICING FUNCTIONS (11% markup always)
// ============================================

function getBuyerPrice($base_price, $markup_percent = 11) {
    return $base_price * (1 + ($markup_percent / 100));
}

/**
 * LAGOS PICKUP STATION FEES:
 * ₦0-25,000 = ₦2,500
 * ₦25,001-75,000 = ₦3,700  
 * ₦75,001+ = ₦8,200
 * 
 * OTHER STATES:
 * ₦0-25,000 = ₦3,500
 * ₦25,001-75,000 = ₦7,500
 * ₦75,001+ = ₦10,500
 */
function getDeliveryFee($order_total, $state = 'lagos') {
    $state = strtolower(trim($state));
    
    // LAGOS SPECIAL RATES
    if ($state === 'lagos') {
        if ($order_total <= 25000) return 2500;
        if ($order_total <= 75000) return 3700;
        return 8200;
    }
    // OTHER STATES
    else {
        if ($order_total <= 25000) return 3500;
        if ($order_total <= 75000) return 7500;
        return 10500;
    }
}

function getDeliveryTierInfo($order_total, $state = 'lagos') {
    $state = strtolower(trim($state));
    $is_lagos = ($state === 'lagos');
    
    if ($order_total <= 25000) {
        return [
            'tier' => 'Small Order',
            'fee' => $is_lagos ? 2500 : 3500,
            'range' => '₦0 - ₦25,000',
            'is_lagos' => $is_lagos,
            'lagos_fee' => 2500,
            'other_fee' => 3500
        ];
    } 
    if ($order_total <= 75000) {
        return [
            'tier' => 'Medium Order',
            'fee' => $is_lagos ? 3700 : 7500,
            'range' => '₦25,001 - ₦75,000',
            'is_lagos' => $is_lagos,
            'lagos_fee' => 3700,
            'other_fee' => 7500
        ];
    }
    return [
        'tier' => 'Large Order',
        'fee' => $is_lagos ? 8200 : 10500,
        'range' => '₦75,001+',
        'is_lagos' => $is_lagos,
        'lagos_fee' => 8200,
        'other_fee' => 10500
    ];
}

// ============================================
// FESTIVE PRICING FUNCTIONS
// ============================================

function getDisplayPrice($product, $conn) {
    // Debug: Check what we're receiving
    if (!isset($product['id']) || !isset($product['base_price'])) {
        error_log("Invalid product data in getDisplayPrice: " . print_r($product, true));
        return [
            'display' => '<span class="normal-price">Error loading price</span>',
            'base_price' => 0,
            'buyer_price' => 0,
            'final_price' => 0,
            'discount_percent' => 0,
            'is_festive' => false,
            'markup_amount' => 0
        ];
    }
    
    // Get buyer price - use stored or calculate
    if (isset($product['buyer_price']) && $product['buyer_price'] > 0) {
        $buyer_price = $product['buyer_price'];
    } else {
        $buyer_price = getBuyerPrice($product['base_price']);
    }
    
    $base_price = $product['base_price'];
    
    // Check festive period
    $festiveCheck = $conn->query("SELECT * FROM festive_periods WHERE is_active = TRUE AND CURDATE() BETWEEN start_date AND end_date LIMIT 1");
    
    if ($festiveCheck->num_rows > 0) {
        $festive = $festiveCheck->fetch_assoc();
        
        // Apply festive discount
        if ($base_price < 25000) {
            $discount = 11;
        } else {
            $discount = rand(25, 28);
        }
        
        $final_price = $buyer_price * (1 - ($discount / 100));
        
        return [
            'display' => '<span class="original-price">₦' . number_format($buyer_price, 2) . '</span>
                         <span class="festive-price">₦' . number_format($final_price, 2) . '</span>
                         <span class="festive-badge">' . $discount . '% OFF</span>',
            'base_price' => $base_price,
            'buyer_price' => $buyer_price,
            'final_price' => $final_price,
            'discount_percent' => $discount,
            'is_festive' => true,
            'markup_amount' => $buyer_price - $base_price
        ];
    }
    
    // Normal day
    return [
        'display' => '<span class="normal-price">₦' . number_format($buyer_price, 2) . '</span>',
        'base_price' => $base_price,
        'buyer_price' => $buyer_price,
        'final_price' => $buyer_price,
        'discount_percent' => 0,
        'is_festive' => false,
        'markup_amount' => $buyer_price - $base_price
    ];
}

function getFinalPrice($productId, $conn) {
    $stmt = $conn->prepare("SELECT base_price, buyer_price FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stmt->bind_result($basePrice, $buyerPrice);
    $stmt->fetch();
    $stmt->close();
    
    if ($buyerPrice === null || $buyerPrice == 0) {
        $buyerPrice = getBuyerPrice($basePrice);
    }
    
    // Check festive
    $festiveCheck = $conn->query("SELECT * FROM festive_periods WHERE is_active = TRUE AND CURDATE() BETWEEN start_date AND end_date LIMIT 1");
    
    if ($festiveCheck->num_rows > 0) {
        $festive = $festiveCheck->fetch_assoc();
        $discount = ($basePrice < 25000) ? 11 : rand(25, 28);
        $finalPrice = $buyerPrice * (1 - ($discount / 100));
        
        return [
            'final_price' => $finalPrice,
            'base_price' => $basePrice,
            'buyer_price' => $buyerPrice,
            'discount_percent' => $discount,
            'is_festive' => true
        ];
    }
    
    return [
        'final_price' => $buyerPrice,
        'base_price' => $basePrice,
        'buyer_price' => $buyerPrice,
        'discount_percent' => 0,
        'is_festive' => false
    ];
}

// ============================================
// COMMISSION CALCULATION - UPDATED FOR YOUR MATRIX
// ============================================

function calculateCommission($vendor_id, $base_price = 0, $quantity = 1, $conn = null) {
    // ============================================
    // 🎯 YOUR COMMISSION MATRIX IMPLEMENTATION
    // ============================================
    
    // If no database connection, return default structure
    if ($conn === null) {
        return [
            'rate' => 11.00,  // Default: Basic + Platform delivery
            'amount' => 0,
            'vendor_earnings' => 0,
            'total_base' => 0,
            'plan_name' => 'Basic',
            'delivery_mode' => 'platform',
            'is_premium' => false,
            'raw_rate' => 0.11
        ];
    }
    
    // 1. Get vendor's subscription plan AND delivery preference
    $stmt = $conn->prepare("
        SELECT 
            u.delivery_mode,
            u.subscription_plan_id,
            vsp.commission_percent as plan_commission,
            vsp.name as plan_name,
            vsp.monthly_fee
        FROM users u
        LEFT JOIN vendor_subscription_plans vsp ON u.subscription_plan_id = vsp.id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Default values if vendor not found
        $plan_commission = 8.00;    // Basic plan default
        $delivery_mode = 'platform'; // Default delivery
        $plan_name = 'Basic';
        $is_premium = false;
    } else {
        $vendor_data = $result->fetch_assoc();
        $plan_commission = $vendor_data['plan_commission'] ?? 8.00;
        $delivery_mode = $vendor_data['delivery_mode'] ?? 'platform';
        $plan_name = $vendor_data['plan_name'] ?? 'Basic';
        $is_premium = ($plan_name === 'Premium');
    }
    
    // ============================================
    // 🎯 APPLY YOUR COMMISSION MATRIX
    // ============================================
    
    if ($delivery_mode == 'vendor') {
        // Vendor handles delivery: keep plan commission
        $final_rate = $plan_commission / 100;  // Convert 8.00 → 0.08, 5.00 → 0.05
    } else {
        // Platform handles delivery: add 3% extra
        $final_rate = ($plan_commission + 3.00) / 100;  // 8% → 11%, 5% → 8%
    }
    
    // Calculate amounts
    $total_base = $base_price * $quantity;
    $commission_amount = $total_base * $final_rate;
    $vendor_earnings = $total_base - $commission_amount;
    
    return [
        'rate' => $final_rate * 100,  // Return as percentage (8.00, 5.00, 11.00, 8.00)
        'amount' => $commission_amount,
        'vendor_earnings' => $vendor_earnings,
        'total_base' => $total_base,
        'plan_name' => $plan_name,
        'delivery_mode' => $delivery_mode,
        'is_premium' => $is_premium,
        'raw_rate' => $final_rate  // For calculations
    ];
}

// ============================================
// ADDITIONAL HELPER FUNCTION
// ============================================

function getVendorCommissionInfo($vendor_id, $conn) {
    // Get vendor's commission info without calculating amounts
    $stmt = $conn->prepare("
        SELECT 
            u.delivery_mode,
            vsp.commission_percent as plan_commission,
            vsp.name as plan_name,
            vsp.monthly_fee
        FROM users u
        LEFT JOIN vendor_subscription_plans vsp ON u.subscription_plan_id = vsp.id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'plan_commission' => 8.00,
            'plan_name' => 'Basic',
            'delivery_mode' => 'platform',
            'monthly_fee' => 0,
            'is_premium' => false
        ];
    }
    
    $vendor_data = $result->fetch_assoc();
    $plan_commission = $vendor_data['plan_commission'] ?? 8.00;
    $plan_name = $vendor_data['plan_name'] ?? 'Basic';
    $delivery_mode = $vendor_data['delivery_mode'] ?? 'platform';
    
    // Calculate final rate using your matrix
    if ($delivery_mode == 'vendor') {
        $final_rate = $plan_commission;
    } else {
        $final_rate = $plan_commission + 3.00;
    }
    
    return [
        'plan_commission' => $plan_commission,
        'final_commission_rate' => $final_rate,
        'plan_name' => $plan_name,
        'delivery_mode' => $delivery_mode,
        'monthly_fee' => $vendor_data['monthly_fee'] ?? 0,
        'is_premium' => ($plan_name === 'Premium'),
        'description' => "{$plan_name} Plan + " . ucfirst($delivery_mode) . " Delivery = {$final_rate}% commission"
    ];
}

// ============================================
// CLEANUP
// ============================================

function clearFestivePricing($conn) {
    $ended = $conn->query("SELECT id FROM festive_periods WHERE is_active = TRUE AND end_date < CURDATE()");
    
    while ($period = $ended->fetch_assoc()) {
        $conn->query("UPDATE products SET festive_display_price = NULL, festive_discount_percent = NULL, festive_period_id = NULL, is_festive_active = FALSE WHERE festive_period_id = {$period['id']}");
        $conn->query("UPDATE festive_periods SET is_active = FALSE WHERE id = {$period['id']}");
    }
}

clearFestivePricing($conn);
?>