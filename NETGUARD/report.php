<?php
session_start();
if (!isset($_SESSION["netguard_user"])) {
    header("Location: index.php");
    exit;
}
require_once "db.php";

$total = $conn->query("SELECT COUNT(*) FROM network_device")->fetchColumn();
$online = $conn->query("SELECT COUNT(*) FROM network_device WHERE LOWER(status)='online'")->fetchColumn();
$offline = $conn->query("SELECT COUNT(*) FROM network_device WHERE LOWER(status)='offline'")->fetchColumn();
$faults = $conn->query("SELECT COUNT(*) FROM faults")->fetchColumn();
$notifications = $conn->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

$records = $conn->query("
    SELECT fault_type, status, detected_at
    FROM faults ORDER BY detected_at DESC
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
<title>Reports - NETGUARD</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#07111f;color:#eaf4ff;font-family:Arial,sans-serif}
.sidebar{position:fixed;width:235px;height:100vh;background:#0a1728;padding:28px 18px;border-right:1px solid #183047}
.logo{font-size:27px;font-weight:bold;margin-bottom:40px}
.logo span{color:#00c6ff}
nav a{display:block;color:#a9bdd0;text-decoration:none;padding:14px;margin:7px 0;border-radius:6px}
nav a:hover,nav a.active{background:#10334b;color:#00c6ff}
.logout{position:absolute;bottom:25px}
.main{margin-left:235px;padding:35px}
header p{color:#9db1c5}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin:25px 0}
.card,.panel{background:#0a1728;border:1px solid #183047;border-radius:10px;padding:22px;margin-top:15px}
.card p{color:#9db1c5}
.card h2{color:#00c6ff;font-size:30px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{text-align:left;padding:14px;border-bottom:1px solid #20364b}
th{color:#00c6ff}
button{background:#00b8ed;border:0;border-radius:6px;padding:12px 20px;font-weight:bold;cursor:pointer}
@media(max-width:850px){.sidebar{position:static;width:100%;height:auto}.logout{position:static;margin-top:20px}.main{margin:0;padding:20px}.cards{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<aside class="sidebar">
<div class="logo">NET<span>GUARD</span></div>
<nav>
<a href="dashboard.php">Dashboard</a>
<a href="connection.php">Customer Connection</a>
<a href="faults.php">Fault Logs</a>
<a href="notifications.php">Notifications</a>
<a class="active" href="report.php">Reports</a>
</nav>
<div class="logout"><a href="logout.php">Logout</a></div>
</aside>

<main class="main">
<header>
<h1>System Reports</h1>
<p>Overview of network connections, faults and notifications.</p>
</header>

<button onclick="window.print()">Print Report</button>

<section class="cards">
<div class="card"><p>Total Connections</p><h2><?= (int)$total ?></h2></div>
<div class="card"><p>Online Connections</p><h2><?= (int)$online ?></h2></div>
<div class="card"><p>Offline Connections</p><h2><?= (int)$offline ?></h2></div>
<div class="card"><p>Total Faults</p><h2><?= (int)$faults ?></h2></div>
<div class="card"><p>Notifications</p><h2><?= (int)$notifications ?></h2></div>
</section>

<section class="panel">
<h2>Fault History Report</h2>
<table>
<thead><tr><th>Fault Type</th><th>Status</th><th>Date Detected</th></tr></thead>
<tbody>
<?php if (!$records): ?>
<tr><td colspan="3">No fault records available.</td></tr>
<?php else: foreach ($records as $record): ?>
<tr>
<td><?= h($record["fault_type"]) ?></td>
<td><?= h($record["status"]) ?></td>
<td><?= h($record["detected_at"]) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</section>
</main>
</body>
</html>