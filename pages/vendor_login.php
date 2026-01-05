<?php
include '../includes/db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, password, is_approved FROM users WHERE email = ? AND role = 'vendor'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed_password, $is_approved);
        $stmt->fetch();

        if (!$is_approved) {
            $error = "Your vendor account is pending approval.";
        } elseif (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["role"] = "vendor";
            header("Location: vendor_dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No vendor found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relaxo Wears - Vendor Login</title>
<link rel="stylesheet" href="style.css">
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
    <h2>Vendor Login</h2>

    <?php if(isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email Address" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
        <p>New vendor? <a href="vendor_register.php">Register here</a></p>
    </form>
</div>
</body>
</html>
