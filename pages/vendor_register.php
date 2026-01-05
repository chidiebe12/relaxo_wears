<?php
session_start();
include '../includes/db.php';

$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role = 'vendor';
    $address = ""; // optional placeholder

    // Check if vendor already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'vendor'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Vendor with this email already exists.";
    } else {
        $stmt->close();
        // Insert new vendor with empty address
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $password, $role, $address);
        if ($stmt->execute()) {
            $success = "✅ Vendor account created successfully. You can now <a href='vendor_login.php'>login here</a>.";
        } else {
            $error = "❌ Error registering vendor: " . $stmt->error;
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Registration - Relaxo Wears</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, sans-serif;
    background: #eef5f2;
    margin: 0;
    padding: 0;
}
.container {
    max-width: 400px;
    margin: 60px auto;
    background: #fff;
    padding: 35px 30px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
h2 {
    text-align: center;
    color: #1a4d2e;
    margin-bottom: 25px;
}
form input, form button {
    width: 100%;
    padding: 14px 15px;
    margin: 10px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
    transition: 0.3s;
}
input:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 8px rgba(26,77,46,0.3);
}
button {
    background-color: #1a4d2e;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
    border: none;
    transition: 0.3s;
}
button:hover {
    background-color: #2e7d4c;
    transform: scale(1.02);
}
.success, .error {
    padding: 10px 15px;
    border-radius: 6px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 500;
}
.success { background: #e6ffe6; color: #2e7d4c; border: 1px solid #a5d6a7; }
.error { background: #ffe6e6; color: #c00; border: 1px solid #f5a4a4; }
p { text-align: center; font-size: 14px; }
a { color: #1a4d2e; text-decoration: none; }
a:hover { text-decoration: underline; }
@media(max-width: 480px){
    .container { margin: 30px 15px; padding: 25px 20px; }
    form input, form button { padding: 12px 10px; }
}
</style>
</head>
<body>
<div class="container">
    <h2>Register as Vendor</h2>

    <?php if($success): ?>
        <div class="success"><?= $success ?></div>
    <?php elseif($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="vendor_login.php">Login here</a></p>
</div>
</body>
</html>
