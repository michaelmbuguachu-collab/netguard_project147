<?php
session_start();

if (!isset($_SESSION["netguard_user"])) {
    header("Location: index.php");
    exit;
}

require_once "db.php";

$devices = $conn->query("
    SELECT 
        nd.id,
        nd.device_name,
        nd.ip_address,
        nd.device_type,
        nd.status,
        u.full_name AS customer_name
    FROM network_device nd
    LEFT JOIN users u ON nd.customer_id = u.id
    ORDER BY nd.id DESC
")->fetchAll();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Connection - NETGUARD</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#07111f;color:#eaf4ff;font-family:Arial,sans-serif}
.sidebar{position:fixed;width:235px;height:100vh;background:#0a1728;padding:28px 18px;border-right:1px solid #183047}
.logo{font-size:27px;font-weight:bold;margin-bottom:40px}
.logo span{color:#00c6ff}
nav a{display:block;color:#a9bdd0;text-decoration:none;padding:14px;margin:7px 0;border-radius:6px}
nav a:hover,nav a.active{background:#10334b;color:#00c6ff}
.logout{position:absolute;bottom:25px}
.logout a{color:#a9bdd0;text-decoration:none;padding:14px}
.main{margin-left:235px;padding:35px}
header p{color:#9db1c5}
.panel{background:#0a1728;border:1px solid #183047;border-radius:10px;padding:22px;margin-top:25px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{text-align:left;padding:14px;border-bottom:1px solid #20364b}
th{color:#00c6ff}
.status-online{color:#00df91;font-weight:bold}
.status-offline{color:#ff6464;font-weight:bold}
.empty{text-align:center;color:#9db1c5;padding:25px}
@media(max-width:850px){
.sidebar{position:static;width:100%;height:auto}
.logout{position:static;margin-top:20px}
.main{margin:0;padding:20px}
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">NET<span>GUARD</span></div>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a class="active" href="connection.php">Customer Connection</a>
        <a href="customers.php">Customer Management</a>
        <a href="faults.php">Fault Logs</a>
        <a href="notifications.php">Notifications</a>
        <a href="report.php">Reports</a>
    </nav>

    <div class="logout">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<main class="main">

<header>
    <h1>Customer Connection</h1>
    <p>View and monitor registered customer internet connections.</p>
</header>

<section class="panel">
    <h2>Registered Customer Connections</h2>

    <table>
        <thead>
            <tr>
                <th>Connection ID</th>
                <th>Customer Name</th>
                <th>Device Name</th>
                <th>IP Address</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
        <?php if (count($devices) > 0): ?>

            <?php foreach ($devices as $device): ?>
            <tr>
                <td><?= (int)$device["id"] ?></td>
                <td><?= h($device["customer_name"] ?? "Not linked") ?></td>
                <td><?= h($device["device_name"]) ?></td>
                <td><?= h($device["ip_address"]) ?></td>
                <td>
                    <?php if (strtolower($device["status"]) === "online"): ?>
                        <span class="status-online">Online</span>
                    <?php else: ?>
                        <span class="status-offline"><?= h($device["status"]) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

        <?php else: ?>
            <tr>
                <td colspan="5" class="empty">
                    No customer connections registered yet.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

</main>
</body>
</html>