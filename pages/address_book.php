<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: buyer_login.php");
    exit();
}

$buyer_id = $_SESSION["user_id"];
$message = "";

// Handle form submission (add new address)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_address"])) {
    $state = trim($_POST["state"]);
    $city = trim($_POST["city"]);
    $address = trim($_POST["address"]);
    $phone = trim($_POST["phone_number"]);
    $alt_phone = trim($_POST["alt_phone_number"]);
    $is_default = isset($_POST["is_default"]) ? 1 : 0;

    if ($is_default) {
        $conn->query("UPDATE addresses SET is_default = 0 WHERE buyer_id = $buyer_id");
    }

    $stmt = $conn->prepare("INSERT INTO addresses (buyer_id, state, city, address, phone_number, alt_phone_number, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $buyer_id, $state, $city, $address, $phone, $alt_phone, $is_default);
    if ($stmt->execute()) {
        $message = "✅ Address saved successfully.";
    } else {
        $message = "❌ Failed to save address.";
    }
    $stmt->close();
}

// Handle set default
if (isset($_GET["set_default"])) {
    $addr_id = (int)$_GET["set_default"];
    $conn->query("UPDATE addresses SET is_default = 0 WHERE buyer_id = $buyer_id");
    $conn->query("UPDATE addresses SET is_default = 1 WHERE id = $addr_id AND buyer_id = $buyer_id");
    $message = "✅ Default address updated.";
}

// Handle delete
if (isset($_GET["delete"])) {
    $addr_id = (int)$_GET["delete"];
    $conn->query("DELETE FROM addresses WHERE id = $addr_id AND buyer_id = $buyer_id");
    $message = "✅ Address deleted.";
}

// Fetch addresses
$result = $conn->query("SELECT * FROM addresses WHERE buyer_id = $buyer_id ORDER BY is_default DESC, created_at DESC");
$addresses = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Addresses - Relaxo Wears</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 800px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; }
        h2 { text-align: center; color: darkgreen; }
        .message { background: #e0ffe0; color: green; padding: 10px; margin-bottom: 20px; border-radius: 6px; }
        form { margin-bottom: 30px; }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; }
        .btn { background: darkgreen; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
        .address-card { border: 1px solid #ccc; padding: 15px; border-radius: 6px; margin-bottom: 15px; background: #f9f9f9; }
        .address-card h4 { margin: 0 0 6px 0; }
        .address-actions a { margin-right: 15px; text-decoration: none; color: #006400; }
        .default-badge { color: white; background: green; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h2>📍 Address Book</h2>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="add_address" value="1">
        <div class="form-group">
            <label>State</label>
           <select name="state" required>
    <option value="">-- Select State --</option>
    <option value="Abia">Abia</option>
    <option value="Adamawa">Adamawa</option>
    <option value="Akwa Ibom">Akwa Ibom</option>
    <option value="Anambra">Anambra</option>
    <option value="Bauchi">Bauchi</option>
    <option value="Bayelsa">Bayelsa</option>
    <option value="Benue">Benue</option>
    <option value="Borno">Borno</option>
    <option value="Cross River">Cross River</option>
    <option value="Delta">Delta</option>
    <option value="Ebonyi">Ebonyi</option>
    <option value="Edo">Edo</option>
    <option value="Ekiti">Ekiti</option>
    <option value="Enugu">Enugu</option>
    <option value="FCT">FCT</option>
    <option value="Gombe">Gombe</option>
    <option value="Imo">Imo</option>
    <option value="Jigawa">Jigawa</option>
    <option value="Kaduna">Kaduna</option>
    <option value="Kano">Kano</option>
    <option value="Katsina">Katsina</option>
    <option value="Kebbi">Kebbi</option>
    <option value="Kogi">Kogi</option>
    <option value="Kwara">Kwara</option>
    <option value="Lagos">Lagos</option>
    <option value="Nasarawa">Nasarawa</option>
    <option value="Niger">Niger</option>
    <option value="Ogun">Ogun</option>
    <option value="Ondo">Ondo</option>
    <option value="Osun">Osun</option>
    <option value="Oyo">Oyo</option>
    <option value="Plateau">Plateau</option>
    <option value="Rivers">Rivers</option>
    <option value="Sokoto">Sokoto</option>
    <option value="Taraba">Taraba</option>
    <option value="Yobe">Yobe</option>
    <option value="Zamfara">Zamfara</option>
</select>
        </div>
        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" required>
        </div>
        <div class="form-group">
            <label>Full Address</label>
            <textarea name="address" required></textarea>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone_number" required>
        </div>
        <div class="form-group">
            <label>Alt. Phone Number (Optional)</label>
            <input type="text" name="alt_phone_number">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_default"> Set as default address</label>
        </div>
        <button type="submit" class="btn">Save Address</button>
    </form>

    <hr>

    <h3>📦 Saved Addresses</h3>

    <?php foreach ($addresses as $addr): ?>
        <div class="address-card">
            <h4><?= htmlspecialchars($addr['state']) ?> - <?= htmlspecialchars($addr['city']) ?>
                <?php if ($addr['is_default']): ?>
                    <span class="default-badge">Default</span>
                <?php endif; ?>
            </h4>
            <p><?= nl2br(htmlspecialchars($addr['address'])) ?></p>
            <p>📞 <?= htmlspecialchars($addr['phone_number']) ?><?= $addr['alt_phone_number'] ? " | Alt: " . htmlspecialchars($addr['alt_phone_number']) : "" ?></p>
            <div class="address-actions">
                <?php if (!$addr['is_default']): ?>
                    <a href="?set_default=<?= $addr['id'] ?>">Make Default</a>
                <?php endif; ?>
                <a href="?delete=<?= $addr['id'] ?>" onclick="return confirm('Delete this address?')">Delete</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
