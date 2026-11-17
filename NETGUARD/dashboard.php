<?php
session_start();

if (!isset($_SESSION["netguard_user"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: index.php");
    exit;
}

require_once "db.php";

$error = "";
$success = "";

if (isset($_GET["assigned"])) {
    $success = "Technician assigned successfully. Notifications sent to both technician and customer.";
}

// Process technician assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["assign_fault"])) {

    $faultId = (int)($_POST["fault_id"] ?? 0);
    $technicianId = (int)($_POST["technician_id"] ?? 0);
    $customerId = (int)($_POST["customer_id"] ?? 0);

    try {
        $faultStmt = $conn->prepare("
            SELECT f.id, f.fault_type, f.status, d.device_name
            FROM faults f
            LEFT JOIN network_device d ON f.device_id = d.id
            WHERE f.id = ? AND f.status = 'active'
        ");
        $faultStmt->execute([$faultId]);
        $fault = $faultStmt->fetch();

        $techStmt = $conn->prepare("
            SELECT id, full_name
            FROM users
            WHERE id = ? AND role = 'technician'
        ");
        $techStmt->execute([$technicianId]);
        $technician = $techStmt->fetch();

        $customerStmt = $conn->prepare("
            SELECT id, full_name
            FROM users
            WHERE id = ? AND role = 'customer'
        ");
        $customerStmt->execute([$customerId]);
        $customer = $customerStmt->fetch();

        if (!$fault || !$technician || !$customer) {
            $error = "Please select a valid active fault, technician and customer.";
        } else {

            $duplicateStmt = $conn->prepare("
                SELECT id
                FROM technician_assignments
                WHERE fault_id = ?
                AND status IN ('Assigned', 'Accepted')
                LIMIT 1
            ");
            $duplicateStmt->execute([$faultId]);

            if ($duplicateStmt->fetch()) {
                $error = "This fault already has an active technician assignment.";
            } else {

                $conn->beginTransaction();

                $insert = $conn->prepare("
                    INSERT INTO technician_assignments
                    (fault_id, technician_id, customer_id, status)
                    VALUES (?, ?, ?, 'Assigned')
                ");

                $insert->execute([
                    $faultId,
                    $technicianId,
                    $customerId
                ]);

                $deviceName = $fault["device_name"] ?? "Unlinked device";

                $notify = $conn->prepare("
                    INSERT INTO notifications
                    (fault_id, message, status, recipient_role, recipient_id, notification_type)
                    VALUES (?, ?, 'Unread', ?, ?, ?)
                ");

                // Technician notification
                $technicianMessage = "New repair assignment: "
                    . $fault["fault_type"]
                    . " affecting "
                    . $deviceName
                    . ". Please review and accept the job.";

                $notify->execute([
                    $faultId,
                    $technicianMessage,
                    "technician",
                    $technicianId,
                    "assignment"
                ]);

                // Customer notification
                $customerMessage = "A field technician, "
                    . $technician["full_name"]
                    . ", has been assigned to investigate your internet connection failure: "
                    . $fault["fault_type"]
                    . " affecting "
                    . $deviceName
                    . ". You will receive updates as the repair progresses.";

                $notify->execute([
                    $faultId,
                    $customerMessage,
                    "customer",
                    $customerId,
                    "assignment"
                ]);

                $conn->commit();

                header("Location: dashboard.php?assigned=1");
                exit;
            }
        }

    } catch (PDOException $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = "Could not assign technician. Please check the database.";
    }
}

// Dashboard statistics
$total = $conn->query("SELECT COUNT(*) FROM network_device")->fetchColumn();

$online = $conn->query("
    SELECT COUNT(*) FROM network_device
    WHERE LOWER(status) = 'online'
")->fetchColumn();

$offline = $conn->query("
    SELECT COUNT(*) FROM network_device
    WHERE LOWER(status) = 'offline'
")->fetchColumn();

$failures = $conn->query("SELECT COUNT(*) FROM faults")->fetchColumn();

// Recent failures
$recent = $conn->query("
    SELECT fault_type, status, detected_at
    FROM faults
    ORDER BY detected_at DESC
    LIMIT 5
")->fetchAll();

// Active faults
$activeFaults = $conn->query("
    SELECT f.id, f.fault_type, f.detected_at, d.device_name
    FROM faults f
    LEFT JOIN network_device d ON f.device_id = d.id
    WHERE f.status = 'active'
    ORDER BY f.detected_at DESC
")->fetchAll();

// Technicians
$technicians = $conn->query("
    SELECT id, full_name
    FROM users
    WHERE role = 'technician'
    ORDER BY full_name
")->fetchAll();

// Customers
$customers = $conn->query("
    SELECT id, full_name
    FROM users
    WHERE role = 'customer'
    ORDER BY full_name
")->fetchAll();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NETGUARD Dashboard</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#07111f;color:#eaf4ff;font-family:Arial,sans-serif}
.sidebar{position:fixed;width:235px;height:100vh;background:#0a1728;padding:28px 18px;border-right:1px solid #183047}
.logo{font-size:27px;font-weight:bold;margin-bottom:40px}
.logo span{color:#00c6ff}
nav a{display:block;color:#a9bdd0;text-decoration:none;padding:14px;margin:7px 0;border-radius:6px}
nav a:hover,nav a.active{background:#10334b;color:#00c6ff}
.logout{position:absolute;bottom:25px;left:18px;right:18px}
.logout a{display:block;color:#ff7777;text-decoration:none;padding:14px;border-radius:6px}
.logout a:hover{background:#491f2a}
.main{margin-left:235px;padding:35px}
header p{color:#9db1c5}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin:30px 0}
.card,.panel{background:#0a1728;border:1px solid #183047;border-radius:10px;padding:22px}
.card p{color:#9db1c5;margin:0}
.card h2{font-size:32px;color:#00c6ff;margin:15px 0 0}
.panel{margin-top:25px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{text-align:left;padding:14px;border-bottom:1px solid #20364b}
th{color:#00c6ff}
.empty{color:#9db1c5}
select{width:100%;padding:12px;margin:8px 0 18px;background:#07111f;border:1px solid #24445e;border-radius:6px;color:white}
label{display:block;color:#c8d8e8;margin-top:12px}
button{background:#00aee8;color:#00111c;border:0;padding:13px 20px;border-radius:6px;font-weight:bold;cursor:pointer}
button:hover{background:#00c6ff}
.success{background:#123d35;color:#55e6a5;padding:13px;border-radius:6px;margin-top:15px}
.error{background:#491f2a;color:#ff9999;padding:13px;border-radius:6px;margin-top:15px}
@media(max-width:850px){
.sidebar{position:relative;width:100%;height:auto}
.logout{position:static;margin-top:20px}
.main{margin:0;padding:20px}
.cards{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:480px){.cards{grid-template-columns:1fr}}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">NET<span>GUARD</span></div>

    <nav>
        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="connection.php">Customer Connection</a>
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
    <h1>Dashboard</h1>
    <p>Monitor and manage internet connections in real time.</p>
</header>

<?php if ($success): ?>
    <div class="success"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
<?php endif; ?>

<section class="cards">
    <div class="card"><p>Total Connections</p><h2><?= (int)$total ?></h2></div>
    <div class="card"><p>Online Connections</p><h2><?= (int)$online ?></h2></div>
    <div class="card"><p>Offline Connections</p><h2><?= (int)$offline ?></h2></div>
    <div class="card"><p>Failures Detected</p><h2><?= (int)$failures ?></h2></div>
</section>

<section class="panel">
<h2>Recent Failures</h2>

<table>
<thead>
<tr>
    <th>Fault Type</th>
    <th>Status</th>
    <th>Date Detected</th>
</tr>
</thead>
<tbody>
<?php if (!$recent): ?>
<tr><td colspan="3" class="empty">No failures recorded.</td></tr>
<?php else: ?>
<?php foreach ($recent as $fault): ?>
<tr>
    <td><?= h($fault["fault_type"]) ?></td>
    <td><?= h($fault["status"]) ?></td>
    <td><?= h($fault["detected_at"]) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</section>

<section class="panel">
<h2>Assign Field Technician</h2>
<p>Select an active fault, technician and affected customer.</p>

<?php if (!$activeFaults): ?>
<p class="empty">There are currently no active faults to assign.</p>

<?php elseif (!$technicians): ?>
<p class="empty">No technician accounts are available.</p>

<?php elseif (!$customers): ?>
<p class="empty">No customer accounts are available.</p>

<?php else: ?>

<form method="POST" id="assignmentForm">

<label>Select Active Fault</label>
<select name="fault_id" required>
<option value="">Choose fault</option>
<?php foreach ($activeFaults as $fault): ?>
<option value="<?= (int)$fault["id"] ?>">
Fault #<?= (int)$fault["id"] ?> -
<?= h($fault["fault_type"]) ?> -
<?= h($fault["device_name"] ?? "Unlinked device") ?>
</option>
<?php endforeach; ?>
</select>

<label>Select Field Technician</label>
<select name="technician_id" required>
<option value="">Choose technician</option>
<?php foreach ($technicians as $tech): ?>
<option value="<?= (int)$tech["id"] ?>">
<?= h($tech["full_name"]) ?>
</option>
<?php endforeach; ?>
</select>

<label>Select Affected Customer</label>
<select name="customer_id" required>
<option value="">Choose customer</option>
<?php foreach ($customers as $customer): ?>
<option value="<?= (int)$customer["id"] ?>">
<?= h($customer["full_name"]) ?>
</option>
<?php endforeach; ?>
</select>

<button type="submit" name="assign_fault">
Assign Technician
</button>

</form>

<?php endif; ?>
</section>

</main>

<script>
let assignmentInProgress = false;

const assignmentForm = document.getElementById("assignmentForm");

if (assignmentForm) {
    assignmentForm.addEventListener("focusin", function() {
        assignmentInProgress = true;
    });

    assignmentForm.addEventListener("submit", function() {
        assignmentInProgress = true;
    });
}

setInterval(function() {
    if (!assignmentInProgress && !document.hidden) {
        location.reload();
    }
}, 5000);
</script>

</body>
</html>