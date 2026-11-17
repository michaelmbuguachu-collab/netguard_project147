<?php
session_start();
require_once "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("
        SELECT id, full_name, username, password, role
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {

        session_regenerate_id(true);

        $_SESSION["netguard_user"] = $user["full_name"];
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        if ($user["role"] === "admin") {
            header("Location: dashboard.php");
        }
        elseif ($user["role"] === "technician") {
            header("Location: technician_dashboard.php");
        }
        elseif ($user["role"] === "customer") {
            header("Location: customer_dashboard.php");
        }
        else {
            session_destroy();
            $error = "Invalid account role.";
        }

        if (!$error) {
            exit;
        }

    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NETGUARD Login</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#07111f;color:#fff;font-family:Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center}
.login{background:#0a1728;padding:35px;width:90%;max-width:390px;border-radius:12px;border:1px solid #12344c}
.logo{text-align:center;font-size:30px;font-weight:bold;color:#fff;margin-bottom:8px}
.logo span{color:#00c6ff}
p{text-align:center;color:#9db1c5;margin-bottom:28px}
label{display:block;margin:15px 0 7px;color:#c8d8e8}
input{width:100%;padding:13px;background:#07111f;border:1px solid #24445e;border-radius:6px;color:white}
button{width:100%;padding:13px;margin-top:22px;background:#00aee8;border:0;border-radius:6px;color:#00111c;font-weight:bold;cursor:pointer}
.error{color:#ff7777;text-align:center}
</style>
</head>

<body>

<div class="login">

    <div class="logo">NET<span>GUARD</span></div>
    <p>INTERNET MONITORING SYSTEM</p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">LOGIN</button>

    </form>

</div>

</body>
</html>