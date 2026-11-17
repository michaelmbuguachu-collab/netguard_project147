<?php
session_start();
if (!isset($_SESSION["netguard_user"])) {
    header("Location: index.php");
    exit;
}
require_once "db.php";

$faults = $conn->query("
    SELECT id, fault_type, description, status, detected_at
    FROM faults
    ORDER BY detected_at DESC
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
<title>Fault Logs - NETGUARD</title>
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
.panel{background:#0a1728;border:1px solid #183047;border-radius:10px;padding:22px;margin-top:25px;overflow-x:auto}
table{width:100%;border-collapse:collapse;margin-top:15px;min-width:650px}
th,td{text-align:left;padding:14px;border-bottom:1px solid #20364b}
th{color:#00c6ff}
.empty{color:#9db1c5}
@media(max-width:850px){.sidebar{position:static;width:100%;height:auto}.logout{position:static;margin-top:20px}.main{margin:0;padding:20px}}
</style>
</head>
<body>
<aside class="sidebar">
<div class="logo">NET<span>GUARD</span></div>
<nav>
<a href="dashboard.php">Dashboard</a>
<a href="connection.php">Customer Connection</a>
<a class="active" href="faults.php">Fault Logs</a>
<a href="notifications.php">Notifications</a>
<a href="report.php">Reports</a>
</nav>
<div class="logout"><a href="logout.php">Logout</a></div>
</aside>

<main class="main">
<header>
<h1>Fault Logs</h1>
<p>View detected internet connection failures.</p>
</header>
<section class="panel">
<h2>Detected Failures</h2>
<table>
<thead>
<tr><th>Fault ID</th><th>Fault Type</th><th>Description</th><th>Status</th><th>Date Detected</th></tr>
</thead>
<tbody>
<?php if (!$faults): ?>
<tr><td colspan="5" class="empty">No faults recorded.</td></tr>
<?php else: foreach ($faults as $fault): ?>
<tr>
<td><?= (int)$fault["id"] ?></td>
<td><?= h($fault["fault_type"]) ?></td>
<td><?= h($fault["description"]) ?></td>
<td><?= h($fault["status"]) ?></td>
<td><?= h($fault["detected_at"]) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</section>
</main>
</body>
</html>