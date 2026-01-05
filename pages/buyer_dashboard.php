<?php
session_start();
include '../includes/db.php';

// NEW: Include festive functions (updated version)
include '../includes/festive_functions.php';

$isLoggedIn = isset($_SESSION["user_id"]) && $_SESSION["role"] === "buyer";

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: buyer_dashboard.php");
    exit();
}

$selected_category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// NEW: Check if festive period is active globally
$festiveActive = false;
$festiveDetails = null;
$festiveCheck = $conn->query("
    SELECT * FROM festive_periods 
    WHERE is_active = TRUE 
    AND CURDATE() BETWEEN start_date AND end_date
    LIMIT 1
");
if ($festiveCheck->num_rows > 0) {
    $festiveActive = true;
    $festiveDetails = $festiveCheck->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relaxo Wears - Buyer Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Roboto', sans-serif; background: #f4f7f6; color: #333; }
a { text-decoration: none; color: inherit; }
button { cursor: pointer; }

/* Container */
.container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }

/* Header */
.header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1a4d2e;
    padding: 15px 20px;
    border-radius: 10px;
    color: #fff;
    flex-wrap: wrap;
}
.header-bar h2 { font-weight: 500; font-size: 20px; }
.nav-links { position: relative; }
.nav-btn {
    background-color: #2e7d4c;
    color: #fff;
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    transition: 0.3s;
}
.nav-btn:hover { background-color: #3fa161; }
.nav-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 45px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    min-width: 180px;
    z-index: 10;
}
.nav-dropdown a {
    display: block;
    padding: 12px 15px;
    color: #333;
    font-weight: 500;
    transition: 0.2s;
}
.nav-dropdown a:hover { background: #f1f1f1; }

/* NEW: Festive Banner */
.festive-banner {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 15px;
    text-align: center;
    border-radius: 10px;
    margin: 15px 0;
    animation: pulse 2s infinite;
    font-weight: 500;
    font-size: 18px;
}
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.9; }
    100% { opacity: 1; }
}

/* NEW: Delivery Fee Info Banner */
.delivery-info-banner {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 12px;
    text-align: center;
    border-radius: 8px;
    margin: 15px 0;
    font-size: 14px;
}
.delivery-info-banner strong { font-weight: 600; }
.delivery-info-banner small { opacity: 0.9; }

/* Hero / Welcome */
.hero {
    margin: 25px 0;
    background: linear-gradient(135deg, #1a4d2e, #2e7d4c);
    color: #fff;
    padding: 30px 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    animation: fadeIn 1s ease-in;
}
.hero h3 { font-size: 24px; margin-bottom: 12px; font-weight: 600; }
.hero p { font-size: 16px; font-weight: 400; }

/* Carousel */
.carousel {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    margin: 20px 0;
    scroll-behavior: smooth;
}
.carousel::-webkit-scrollbar { display: none; }
.carousel-card {
    min-width: 220px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    background: #fff;
    flex-shrink: 0;
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
}
.carousel-card:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
.carousel-card img { width: 100%; height: 150px; object-fit: cover; }
.carousel-card h4 { text-align: center; padding: 10px; font-size: 16px; font-weight: 500; }

/* Category + Search */
.category-bar {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 25px 0;
}
.category-bar input[type="text"], .category-bar select {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
}
.category-bar button {
    background-color: #1a4d2e;
    color: #fff;
    border: none;
    padding: 10px 15px;
    border-radius: 8px;
    transition: 0.3s;
}
.category-bar button:hover { background-color: #2e7d4c; }

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}
.product-card {
    background: #fff;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: 0.3s;
    position: relative;
    cursor: pointer;
}
.product-card:hover { transform: translateY(-5px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
.product-card img { 
    width: 100%; 
    height: auto; 
    border-radius: 10px; 
    margin-bottom: 10px;
    transition: transform 0.3s;
}
.product-card:hover img { 
    transform: scale(1.05); 
}
.product-card h4 { font-size: 16px; margin-bottom: 5px; font-weight: 500; }
.product-card p { font-size: 14px; margin-bottom: 8px; }
/* REMOVED: .btn and .btn-quick styles since buttons are gone */

/* FIXED: Better price alignment in product cards */
.product-card .price-container {
    margin: 8px 0;
    padding: 0 8px; /* Add horizontal padding */
}

/* Also update the fallback price style */
.product-card div[style*="margin: 8px 0"] {
    padding: 0 8px;
}

/* NEW: Festive pricing styles */
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
.original-price {
    text-decoration: line-through;
    color: #999;
    font-size: 14px;
    margin-right: 8px;
}
.festive-badge {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
    margin-left: 8px;
}

/* For product cards with festive pricing */
.product-card.festive-active {
    border: 2px solid #e74c3c;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
}

/* Badge for new/hot */
.badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #ff5252;
    color: #fff;
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 6px;
    font-weight: 500;
}

/* Footer */
footer {
    background: #1a4d2e;
    color: #fff;
    padding: 60px 20px 30px 20px;
    text-align: center;
    border-radius: 12px;
    margin-top: 50px;
}
footer h4 { margin-bottom: 12px; font-weight: 600; font-size: 18px; }
footer p { font-size: 14px; margin-bottom: 8px; }
footer a { color: #ffe5b4; transition: 0.3s; }
footer a:hover { text-decoration: underline; }

/* Animations */
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

@media(max-width:768px){
    .hero h3 { font-size: 20px; }
    .hero p { font-size: 14px; }
    .carousel-card { min-width: 160px; }
    .festive-banner { font-size: 16px; padding: 12px; }
    .delivery-info-banner { font-size: 13px; padding: 10px; }
}
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header-bar">
        <h2>Relaxo Wears - Buyer Dashboard</h2>
        <div class="nav-links">
            <button class="nav-btn">Menu</button>
            <div class="nav-dropdown">
                <?php if ($isLoggedIn): ?>
                    <a href="my_account.php">My Account</a>
                    <a href="order_history.php">Order History</a>
                    <a href="cart.php">Cart</a>
                    <a href="?logout=true" style="color: red;">Logout</a>
                <?php else: ?>
                    <a href="buyer_register.php">Register</a>
                    <a href="buyer_login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- NEW: Festive Banner (shows when festive period is active) -->
    <?php if ($festiveActive && $festiveDetails): ?>
    <div class="festive-banner">
        🎉 <?php echo htmlspecialchars($festiveDetails['name']); ?> SALE IS ON! 
        Enjoy massive discounts on all products! 🎉
    </div>
    <?php endif; ?>

    <!-- NEW: Delivery Fee Information Banner -->
    <div class="delivery-info-banner">
        <strong>📍 Pickup Station Delivery:</strong> 
        Lagos: ₦2,500/₦3,700/₦8,200 • Other States: ₦3,500/₦7,500/₦10,500
        <br><small>Fees based on order value. <a href="delivery_info.php" style="color:#ffd700;">Learn more</a></small>
    </div>

    <?php if ($isLoggedIn): ?>
        <!-- Welcome Hero -->
        <?php
        $buyer_id = $_SESSION["user_id"];
        $stmt = $conn->prepare("SELECT name, delivery_address FROM users WHERE id = ?");
        $stmt->bind_param("i", $buyer_id);
        $stmt->execute();
        $stmt->bind_result($buyer_name, $delivery_address);
        $stmt->fetch();
        $stmt->close();
        ?>
        <div class="hero">
            <h3>Welcome, <?= htmlspecialchars($buyer_name) ?>!</h3>
            <p>Your delivery address: <?= htmlspecialchars($delivery_address) ?: 'Not set yet.' ?></p>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <script>alert("✅ Product added to cart.");</script>
        <?php endif; ?>

        <!-- Featured Products Carousel -->
        <h3 style="margin: 15px 0; text-align:center;">Featured Products</h3>
        <div class="carousel">
            <?php
            $featured = $conn->query("SELECT * FROM products WHERE quantity>0 ORDER BY id DESC LIMIT 6");
            while ($prod = $featured->fetch_assoc()) {
                // NEW: Get festive pricing for featured products (WITH $conn parameter)
                $priceInfo = getDisplayPrice($prod, $conn);
                
                // SAFE CHECK: Ensure priceInfo is valid
                if ($priceInfo && isset($priceInfo['display'])) {
                    $cardClass = $priceInfo['is_festive'] ? 'festive-active' : '';
                    
                    echo "<a href='add_to_cart.php?product_id={$prod['id']}' class='carousel-card $cardClass'>
                            <img src='" . htmlspecialchars($prod['image']) . "' alt='Product'>
                            <h4>" . htmlspecialchars($prod['name']) . "</h4>
                            <div style='margin: 5px 0; padding: 0 5px;'>" . $priceInfo['display'] . "</div>
                          </a>";
                } else {
                    echo "<a href='add_to_cart.php?product_id={$prod['id']}' class='carousel-card'>
                            <img src='" . htmlspecialchars($prod['image']) . "' alt='Product'>
                            <h4>" . htmlspecialchars($prod['name']) . "</h4>
                            <div style='margin: 5px 0; color: #666; padding: 0 5px;'>Price loading...</div>
                          </a>";
                }
            }
            ?>
        </div>

        <!-- Category + Search -->
        <div class="category-bar">
            <form method="GET">
                <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php
                    $disallowed = ["thumbler", "headcups", "y2k glasses", "loafers"];
                    $catResult = $conn->query("SELECT * FROM categories ORDER BY name ASC");
                    while ($cat = $catResult->fetch_assoc()) {
                        $name = strtolower($cat['name']);
                        if (in_array($name, $disallowed)) continue;
                        $selected = $selected_category_id == $cat['id'] ? 'selected' : '';
                        echo "<option value='{$cat['id']}' $selected>" . htmlspecialchars($cat['name']) . "</option>";
                    }
                    ?>
                </select>
                <button type="submit">Filter</button>
            </form>
        </div>

        <!-- Product Grid -->
        <div class="product-grid">
            <?php
            $query = "SELECT p.* FROM products p WHERE p.quantity > 0";
            if ($selected_category_id > 0) { $query .= " AND p.category_id = $selected_category_id"; }
            if (!empty($search)) { $query .= " AND p.name LIKE '%".$conn->real_escape_string($search)."%'"; }
            $query .= " ORDER BY p.id DESC";
            $products = $conn->query($query);

            if ($products->num_rows > 0) {
                while ($product = $products->fetch_assoc()) {
                    $badge = rand(0,1) ? "<div class='badge'>New</div>" : "";
                    
                    // NEW: Get festive pricing (WITH $conn parameter)
                    $priceInfo = getDisplayPrice($product, $conn);
                    
                    // SAFE CHECK: Ensure priceInfo is valid before accessing
                    if ($priceInfo && isset($priceInfo['display'])) {
                        $cardClass = $priceInfo['is_festive'] ? 'festive-active' : '';
                        
                        echo "<a href='add_to_cart.php?product_id={$product['id']}' class='product-card $cardClass'>
                                $badge
                                <img src='" . htmlspecialchars($product['image']) . "' alt='Product Image'>
                                <h4>" . htmlspecialchars($product['name']) . "</h4>
                                <div class='price-container' style='margin: 8px 0;'>" . $priceInfo['display'] . "</div>
                                <p>Qty: " . (int)$product['quantity'] . "</p>
                              </a>";
                    } else {
                        // Fallback display if priceInfo is invalid
                        echo "<a href='add_to_cart.php?product_id={$product['id']}' class='product-card'>
                                $badge
                                <img src='" . htmlspecialchars($product['image']) . "' alt='Product Image'>
                                <h4>" . htmlspecialchars($product['name']) . "</h4>
                                <div class='price-container' style='margin: 8px 0; color: #666;'>Price: ₦" . number_format($product['base_price'] ?? 0, 2) . "</div>
                                <p>Qty: " . (int)$product['quantity'] . "</p>
                              </a>";
                    }
                }
            } else {
                echo "<p style='text-align:center;'>No products available in this category or search term.</p>";
            }
            ?>
        </div>

    <?php else: ?>
        <p style="text-align:center; margin:30px 0;">Welcome to Relaxo Wears. Please register or login to browse and order products.</p>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer>
    <h4>Contact Us</h4>
    <p>Email: <a href="mailto:samuelfortune264@gmail.com">samuelfortune264@gmail.com</a></p>
    <p>Phone: <a href="tel:+2348012345678">+234 801 234 5678</a></p>
    <p>&copy; <?= date("Y") ?> Relaxo Wears. All rights reserved.</p>
</footer>

<script>
// Nav dropdown
document.querySelector('.nav-btn').addEventListener('click', function () {
    const dropdown = document.querySelector('.nav-dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
});
document.addEventListener('click', function (e) {
    const btn = document.querySelector('.nav-btn');
    const dropdown = document.querySelector('.nav-dropdown');
    if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// Mobile touch support for carousel
let touchStartX = 0;
let touchEndX = 0;
const carousel = document.querySelector('.carousel');

if (carousel) {
    carousel.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    });

    carousel.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeThreshold = 50;
        const swipeDistance = touchEndX - touchStartX;
        
        if (Math.abs(swipeDistance) > swipeThreshold) {
            if (swipeDistance > 0) {
                // Swipe right - scroll left
                carousel.scrollBy({ left: -200, behavior: 'smooth' });
            } else {
                // Swipe left - scroll right
                carousel.scrollBy({ left: 200, behavior: 'smooth' });
            }
        }
    }
}
</script>
</body>
</html>