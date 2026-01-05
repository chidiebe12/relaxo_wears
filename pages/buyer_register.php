<?php
session_start();
include '../includes/db.php';
include '../includes/mailer_config.php';
include_once '../includes/load_env.php';

// Load environment variables
loadEnv();

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

    // Check for duplicate email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "⚠️ Email already exists. Try logging in instead.";
        $message_type = "error";
    } else {
        $stmt->close();
        // Insert buyer into users table
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_approved) VALUES (?, ?, ?, 'buyer', 1)");
        $stmt->bind_param("sss", $name, $email, $password);

        if ($stmt->execute()) {
            // Send email to admin
            $subject = "New Buyer Registered - Relaxo Wears";
            $body = "
                <h2>New Buyer Registration</h2>
                <p><strong>Name:</strong> {$name}</p>
                <p><strong>Email:</strong> {$email}</p>
            ";
            sendAdminEmail($subject, $body);

            // Success message
            $message = "✅ Registration successful! Please login.";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . $stmt->error;
            $message_type = "error";
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
<title>Relaxo Wears - Buyer Registration</title>
<link rel="stylesheet" href="style.css">
<style>
/* Ensure proper sizing */
*, *::before, *::after {
    box-sizing: border-box;
}

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

h1, h2 {
    text-align: center;
    color: #1a4d2e;
    margin: 0 0 20px;
}

form input, form button {
    width: 100%;
    padding: 14px 15px;
    margin: 10px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
    transition: 0.3s;
    display: block;
}

input:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 5px rgba(26,77,46,0.3);
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
}

.message {
    padding: 10px 15px;
    border-radius: 6px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 500;
}

.error { background: #ffe6e6; color: #c00; border: 1px solid #f5a4a4; }
.success { background: #e6ffe6; color: #2e7d4c; border: 1px solid #a5d6a7; }

p { text-align: center; font-size: 14px; }
a { color: #1a4d2e; text-decoration: none; }
a:hover { text-decoration: underline; }

/* Responsive adjustments */
@media(max-width: 480px){
    .container {
        margin: 30px 15px;
        padding: 25px 20px;
    }
    form input, form button {
        padding: 12px 10px;
    }
}
</style>
</head>
<body>
<div class="container">
    <h1>Relaxo Wears</h1>
    <h2>Buyer Registration</h2>

    <?php if(!empty($message)): ?>
        <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="name" placeholder="Full Name" required />
        <input type="email" name="email" placeholder="Email Address" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Register</button>
        <p>Already have an account? <a href="buyer_login.php">Login here</a></p>
    </form>
</div>
</body>
</html>
