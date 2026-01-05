<?php
session_start();
include '../includes/db.php';
// NEW: Include festive functions (updated version)
include '../includes/festive_functions.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: ../pages/buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];

if (!isset($_GET['product_id'])) {
    die("Product not specified.");
}

$product_id = (int)$_GET['product_id'];
$product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

// NEW: Get festive pricing for this product
$priceInfo = getDisplayPrice($product, $conn);

// NEW: Calculate final price (using updated function)
$finalPriceInfo = getFinalPrice($product_id, $conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = max(1, (int)$_POST['quantity']);
    
    // NEW: Check if cart exists and get current quantity
    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE buyer_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $buyer_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cartItem = $result->fetch_assoc();
        $newQuantity = $cartItem['quantity'] + $quantity;
        
        // Check if enough stock
        if ($newQuantity > $product['quantity']) {
            $_SESSION['cart_error'] = "Only " . $product['quantity'] . " items available in stock.";
            header("Location: add_to_cart.php?product_id=$product_id");
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $newQuantity, $buyer_id, $product_id);
    } else {
        // Check if enough stock for new addition
        if ($quantity > $product['quantity']) {
            $_SESSION['cart_error'] = "Only " . $product['quantity'] . " items available in stock.";
            header("Location: add_to_cart.php?product_id=$product_id");
            exit();
        }
        
        // UPDATED: Insert with CORRECT price data for new system
        $stmt = $conn->prepare("INSERT INTO cart (buyer_id, product_id, quantity, base_price, buyer_price, final_price, discount_percent, is_festive, markup_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iiiddddid", 
            $buyer_id, 
            $product_id, 
            $quantity,
            $priceInfo['base_price'],      // Vendor's base price
            $priceInfo['buyer_price'],     // Buyer price (11% markup)
            $priceInfo['final_price'],     // Final price after discount
            $priceInfo['discount_percent'], // Discount percentage
            $priceInfo['is_festive'],      // Festive flag
            $priceInfo['markup_amount']    // Your markup (₦1,100 on ₦10,000)
        );
    }
    
    if ($stmt->execute()) {
        $_SESSION['cart_success'] = "Product added to cart!";
    } else {
        $_SESSION['cart_error'] = "Failed to add product to cart.";
    }
    
    header("Location: cart.php");
    exit();
}

// NEW: Check for cart messages
$successMsg = isset($_SESSION['cart_success']) ? $_SESSION['cart_success'] : '';
$errorMsg = isset($_SESSION['cart_error']) ? $_SESSION['cart_error'] : '';
unset($_SESSION['cart_success'], $_SESSION['cart_error']);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add to Cart - Relaxo Wears</title>
<style>
    body { font-family: 'Roboto', sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 15px; }
    .card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        text-align: center;
        animation: fadeIn 0.5s ease-in;
    }
    .card img { 
        width: 100%; 
        border-radius: 10px; 
        margin-bottom: 15px; 
        cursor: pointer;
        transition: transform 0.3s;
    }
    .card img:hover { 
        transform: scale(1.02); 
    }
    h2 { font-size: 22px; margin-bottom: 10px; color: #1a4d2e; }
    p { margin-bottom: 10px; font-size: 16px; color: #555; }
    .price-container { 
        margin: 15px 0; 
        padding: 10px;
        background: #f9f9f9;
        border-radius: 8px;
        border-left: 4px solid #2e7d4c;
    }
    .festive-price {
        color: #e74c3c;
        font-weight: bold;
        font-size: 20px;
    }
    .normal-price {
        color: #1a4d2e;
        font-weight: bold;
        font-size: 20px;
    }
    .original-price {
        text-decoration: line-through;
        color: #999;
        font-size: 16px;
        margin-right: 10px;
    }
    .festive-badge {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: bold;
        display: inline-block;
        margin-left: 10px;
    }
    .quantity-input {
        width: 70px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        text-align: center;
        margin-right: 15px;
        font-size: 16px;
    }
    .btn {
        background-color: #1a4d2e;
        color: #fff;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
        margin-top: 10px;
    }
    .btn:hover { background-color: #2e7d4c; }
    .btn-festive {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }
    .btn-festive:hover {
        background: linear-gradient(135deg, #c0392b, #a93226);
    }
    .image-gallery {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        margin-bottom: 15px;
        justify-content: center;
        padding: 10px 0;
    }
    .image-gallery img { 
        width: 80px; 
        height: 80px;
        object-fit: cover;
        border-radius: 6px; 
        cursor: pointer; 
        transition: transform 0.2s, border 0.2s;
        border: 2px solid transparent;
    }
    .image-gallery img:hover, 
    .image-gallery img.active { 
        transform: scale(1.1); 
        border-color: #1a4d2e;
    }
    .stock-info {
        color: #666;
        font-size: 14px;
        margin: 10px 0;
        padding: 8px;
        background: #f0f8f0;
        border-radius: 6px;
        border-left: 3px solid #2e7d4c;
    }
    .alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-weight: 500;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .savings-info {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 8px;
        padding: 10px;
        margin: 10px 0;
        color: #856404;
        font-weight: 500;
    }
    .markup-info {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    
    /* NEW: Image Modal Styles */
    .image-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 20px;
    }
    .image-modal.active {
        display: flex;
        animation: fadeIn 0.3s ease-out;
    }
    .image-modal-content {
        max-width: 90%;
        max-height: 90%;
        position: relative;
    }
    .image-modal-content img {
        width: 100%;
        height: auto;
        max-height: 70vh;
        object-fit: contain;
        border-radius: 8px;
        animation: zoomIn 0.3s ease-out;
    }
    .image-modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        color: white;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        background: rgba(0,0,0,0.5);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.3s;
        z-index: 1001;
    }
    .image-modal-close:hover {
        background: rgba(0,0,0,0.8);
    }
    .image-modal-info {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 15px;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }
    .image-modal-info h4 {
        margin: 0 0 5px 0;
        font-size: 18px;
    }
    .image-modal-price {
        font-size: 16px;
        font-weight: bold;
    }
    
    /* Animations */
    @keyframes fadeIn { 
        from { opacity: 0; transform: translateY(-10px);} 
        to { opacity: 1; transform: translateY(0);} 
    }
    @keyframes zoomIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    
    /* Mobile Responsive */
    @media(max-width: 768px) {
        .card {
            padding: 20px;
        }
        h2 { font-size: 20px; }
        .price-container { 
            padding: 8px;
            font-size: 14px;
        }
        .festive-price, .normal-price {
            font-size: 18px;
        }
        .original-price {
            font-size: 14px;
        }
        .quantity-input {
            width: 60px;
            padding: 8px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            font-size: 14px;
        }
        .image-gallery img { 
            width: 70px; 
            height: 70px;
        }
        .image-modal-close {
            top: 10px;
            right: 10px;
            width: 35px;
            height: 35px;
            font-size: 24px;
        }
        .image-modal-info {
            padding: 10px;
        }
        .image-modal-info h4 {
            font-size: 16px;
        }
        .image-modal-price {
            font-size: 14px;
        }
    }
    
    @media(max-width: 480px) {
        .card {
            padding: 15px;
        }
        h2 { font-size: 18px; }
        .price-container { 
            padding: 6px;
            font-size: 13px;
        }
        .festive-price, .normal-price {
            font-size: 16px;
        }
        .quantity-input {
            width: 100%;
            margin: 0 0 10px 0;
        }
        .btn {
            width: 100%;
            margin-top: 10px;
        }
        .image-gallery {
            justify-content: flex-start;
        }
        .image-gallery img { 
            width: 60px; 
            height: 60px;
        }
    }
</style>
</head>
<body>
    <div class="card">
        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>
        
        <!-- NEW: Image Modal -->
        <div class="image-modal" id="imageModal">
            <span class="image-modal-close">&times;</span>
            <div class="image-modal-content">
                <img id="modalImage" src="" alt="Product Image">
                <div class="image-modal-info">
                    <h4 id="modalProductName"><?= htmlspecialchars($product['name']) ?></h4>
                    <div id="modalProductPrice" class="image-modal-price"></div>
                </div>
            </div>
        </div>
        
        <!-- Product Images -->
        <?php
        $image_query = $conn->query("SELECT image_path FROM product_images WHERE product_id = $product_id");
        $images = [];
        if ($image_query->num_rows > 0) {
            while ($img = $image_query->fetch_assoc()) {
                $images[] = $img['image_path'];
            }
        } else {
            // Use main product image if no gallery images
            $images[] = $product['image'];
        }
        
        if (count($images) > 0) {
            echo '<div class="image-gallery">';
            foreach ($images as $index => $imagePath) {
                $fullPath = strpos($imagePath, 'http') === 0 ? $imagePath : '../uploads/' . $imagePath;
                $isActive = $index === 0 ? 'active' : '';
                echo "<img src='" . htmlspecialchars($fullPath) . "' 
                      alt='Product Image' 
                      data-index='$index'
                      data-src='" . htmlspecialchars($fullPath) . "'
                      class='gallery-thumb $isActive'
                      onclick='openImageModal(this)'>";
            }
            echo '</div>';
            
            // Display main image (first one)
            echo "<img id='mainProductImage' src='" . htmlspecialchars($fullPath) . "' alt='Product Image' onclick='openImageModal(this)'>";
        }
        ?>
        
        <h2><?= htmlspecialchars($product['name']) ?></h2>
        
        <!-- UPDATED: Price Display with new pricing system -->
        <div class="price-container">
            <?php echo $priceInfo['display']; ?>
        </div>
        
        <?php if ($priceInfo['is_festive']): ?>
            <div class="savings-info">
                🎉 <strong>Festive Deal Active!</strong> You save ₦<?php 
                echo number_format($priceInfo['buyer_price'] - $priceInfo['final_price'], 2); 
                ?> (<?php echo $priceInfo['discount_percent']; ?>% OFF)
            </div>
            
            <!-- UPDATED: Show actual savings calculation -->
            <div style="font-size: 14px; color: #27ae60; margin: 5px 0;">
                💰 Total savings: ₦<?php 
                $savings = ($priceInfo['buyer_price'] - $priceInfo['final_price']) + $priceInfo['markup_amount'];
                echo number_format($savings, 2);
                ?> (Discount + Service Fee)
            </div>
        <?php endif; ?>
        
        <p><?= htmlspecialchars($product['description']) ?></p>
        
        <div class="stock-info">
            📦 <strong>Stock:</strong> <?php echo $product['quantity']; ?> units available
        </div>

        <form method="POST">
            <div style="margin: 20px 0;">
                <label for="quantity" style="display: block; margin-bottom: 8px; font-weight: 500;">Quantity:</label>
                <input type="number" name="quantity" id="quantity" min="1" max="<?php echo $product['quantity']; ?>" value="1" class="quantity-input" required>
                <button type="submit" class="btn <?php echo $priceInfo['is_festive'] ? 'btn-festive' : ''; ?>">
                    <?php echo $priceInfo['is_festive'] ? '🎉 Add to Cart' : 'Add to Cart'; ?>
                </button>
            </div>
        </form>
        
        <!-- UPDATED: Hidden fields with correct price data -->
        <input type="hidden" id="base-price" value="<?php echo $priceInfo['base_price']; ?>">
        <input type="hidden" id="buyer-price" value="<?php echo $priceInfo['buyer_price']; ?>">
        <input type="hidden" id="final-price" value="<?php echo $priceInfo['final_price']; ?>">
        <input type="hidden" id="is-festive" value="<?php echo $priceInfo['is_festive'] ? '1' : '0'; ?>">
        <input type="hidden" id="discount-percent" value="<?php echo $priceInfo['discount_percent']; ?>">
        <input type="hidden" id="markup-amount" value="<?php echo $priceInfo['markup_amount']; ?>">
        <input type="hidden" id="festive-savings" value="<?php echo $priceInfo['buyer_price'] - $priceInfo['final_price']; ?>">
    </div>
    
    <script>
    // NEW: Image Modal functionality
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalProductName = document.getElementById('modalProductName');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const imageModalClose = document.querySelector('.image-modal-close');
    const galleryThumbs = document.querySelectorAll('.gallery-thumb');
    const mainProductImage = document.getElementById('mainProductImage');
    
    // Set initial price in modal
    const priceContainer = document.querySelector('.price-container');
    if (priceContainer) {
        modalProductPrice.innerHTML = priceContainer.innerHTML;
    }
    
    // Function to open image modal
    function openImageModal(element) {
        const imgSrc = element.getAttribute('data-src') || element.src;
        modalImage.src = imgSrc;
        imageModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Update active thumbnail
        galleryThumbs.forEach(thumb => {
            thumb.classList.remove('active');
            if (thumb.src === element.src || thumb.getAttribute('data-src') === imgSrc) {
                thumb.classList.add('active');
            }
        });
    }
    
    // Close modal
    imageModalClose.addEventListener('click', () => {
        imageModal.classList.remove('active');
        document.body.style.overflow = 'auto';
    });
    
    // Close modal when clicking outside
    imageModal.addEventListener('click', (e) => {
        if (e.target === imageModal) {
            imageModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && imageModal.classList.contains('active')) {
            imageModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Thumbnail click events
    galleryThumbs.forEach(thumb => {
        thumb.addEventListener('click', function(e) {
            e.stopPropagation();
            const imgSrc = this.getAttribute('data-src');
            modalImage.src = imgSrc;
            
            // Update main image
            if (mainProductImage) {
                mainProductImage.src = imgSrc;
            }
            
            // Update active state
            galleryThumbs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Open modal
            imageModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });
    
    // Update price display when quantity changes
    document.getElementById('quantity').addEventListener('input', function() {
        const quantity = parseInt(this.value) || 1;
        const finalPrice = parseFloat(document.getElementById('final-price').value);
        const buyerPrice = parseFloat(document.getElementById('buyer-price').value);
        const isFestive = document.getElementById('is-festive').value === '1';
        const discountPercent = parseFloat(document.getElementById('discount-percent').value);
        const markupAmount = parseFloat(document.getElementById('markup-amount').value);
        
        const total = (finalPrice * quantity).toFixed(2);
        const originalTotal = (buyerPrice * quantity).toFixed(2);
        
        if (isFestive) {
            const savings = (buyerPrice - finalPrice) * quantity;
            console.log(`Festive deal! ${quantity} items: ₦${originalTotal} → ₦${total} (Save ₦${savings.toFixed(2)})`);
        } else {
            console.log(`${quantity} items: ₦${total} (Includes ₦${(markupAmount * quantity).toFixed(2)} service fee)`);
        }
    });
    
    // Mobile touch support for image gallery
    let touchStartX = 0;
    const imageGallery = document.querySelector('.image-gallery');
    
    if (imageGallery) {
        imageGallery.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        imageGallery.addEventListener('touchend', (e) => {
            const touchEndX = e.changedTouches[0].screenX;
            const swipeThreshold = 50;
            const swipeDistance = touchEndX - touchStartX;
            
            if (Math.abs(swipeDistance) > swipeThreshold) {
                if (swipeDistance > 0) {
                    // Swipe right - scroll left
                    imageGallery.scrollBy({ left: -100, behavior: 'smooth' });
                } else {
                    // Swipe left - scroll right
                    imageGallery.scrollBy({ left: 100, behavior: 'smooth' });
                }
            }
        });
    }
    </script>
</body>
</html>