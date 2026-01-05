<?php
session_start();
include '../includes/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? AND role = 'buyer'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["role"] = "buyer";
            header("Location: buyer_dashboard.php");
            exit();
        } else {
            $error = "⚠️ Invalid password.";
        }
    } else {
        $error = "⚠️ No buyer found with that email.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relaxo Wears - Buyer Login</title>
<style>
/* Reset */
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, sans-serif; }

body {
    background: linear-gradient(135deg, #e0f7f1, #f0fafb);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

.card {
    background: #fff;
    width: 100%;
    max-width: 400px;
    padding: 30px 25px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
}

h1 {
    text-align: center;
    color: #1a4d2e;
    margin-bottom: 5px;
}

h2 {
    text-align: center;
    color: #333;
    font-weight: 400;
    margin-bottom: 25px;
}

input {
    width: 100%;
    padding: 12px 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.3s, box-shadow 0.3s;
}

input:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 5px rgba(26,77,46,0.3);
}

button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: #1a4d2e;
    color: #fff;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s ease;
}

button:hover {
    background: #2e7d4c;
}

.message {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
}

.error { background: #ffe6e6; color: #c00; border: 1px solid #f5a4a4; }

p {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
}

a {
    color: #1a4d2e;
    text-decoration: none;
    font-weight: 500;
}

a:hover {
    text-decoration: underline;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .card {
        padding: 25px 20px;
    }

    input, button {
        font-size: 14px;
        padding: 10px 12px;
    }
}
</style>
</head>
<body>

<div class="card">
    <h1>Relaxo Wears</h1>
    <h2>Buyer Login</h2>

    <?php if(!empty($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email Address" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
        <p>Don't have an account? <a href="buyer_register.php">Register here</a></p>
    </form>
</div>

</body>
</html>
