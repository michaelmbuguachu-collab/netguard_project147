<?php
session_start();

require_once "db.php";

// Check administrator access
if (
    empty($_SESSION["user_id"]) ||
    empty($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: index.php");
    exit;
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_customer"])) {

    $fullName = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $deviceName = trim($_POST["device_name"] ?? "");
    $ipAddress = trim($_POST["ip_address"] ?? "");
    $deviceType = trim($_POST["device_type"] ?? "");

    if (
        $fullName === "" || $username === "" || $password === "" ||
        $deviceName === "" || $ipAddress === "" || $deviceType === ""
    ) {
        $message = "Please fill in all fields.";
        $messageType = "error";

    } elseif (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        $message = "Enter a valid IP address.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";
        $messageType = "error";

    } else {
        try {
            $conn->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (full_name, username, password, role)
                VALUES (?, ?, ?, 'customer')
            ");

            $stmt->execute([
                $fullName,
                $username,
                $hashedPassword
            ]);

            $customerId = $conn->lastInsertId();

            $stmt = $conn->prepare("
                INSERT INTO network_device
                (device_name, ip_address, device_type, status, customer_id)
                VALUES (?, ?, ?, 'Online', ?)
            ");

            $stmt->execute([
                $deviceName,
                $ipAddress,
                $deviceType,
                $customerId
            ]);

            $conn->commit();

            $message = "Customer registered successfully.";
            $messageType = "success";

        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            if ($e->getCode() == "23000") {
                $message = "Username or device details already exist.";
            } else {
                $message = "Registration failed. Please check your database.";
            }

            $messageType = "error";
        }
    }
}

$stmt = $conn->query("
    SELECT
        u.id,
        u.full_name,
        u.username,
        d.device_name,
        d.ip_address,
        d.device_type,
        d.status
    FROM users u
    LEFT JOIN network_device d ON d.customer_id = u.id
    WHERE u.role = 'customer'
    ORDER BY u.id DESC
");

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NETGUARD | Customer Management</title>

<style>
*{box-sizing:border-box}

body{
    margin:0;
    font-family:Arial,sans-serif;
    background:#07111f;
    color:white;
}

.sidebar{
    width:240px;
    height:100vh;
    position:fixed;
    top:0;
    left:0;
    background:#0a1728;
    padding:25px 15px;
}

.logo{
    color:#00c6ff;
    font-size:25px;
    font-weight:bold;
    margin-bottom:35px;
    text-align:center;
}

.sidebar a{
    display:block;
    padding:14px;
    margin-bottom:8px;
    color:#cbd5e1;
    text-decoration:none;
    border-radius:6px;
}

.sidebar a:hover,
.sidebar a.active{
    background:#102b45;
    color:#00c6ff;
}

.main{
    margin-left:240px;
    padding:30px;
}

.subtitle{
    color:#94a3b8;
    margin-bottom:25px;
}

.panel{
    background:#0a1728;
    padding:25px;
    border-radius:10px;
    margin-bottom:25px;
    border:1px solid #183149;
}

.panel h2{
    margin-top:0;
    color:#00c6ff;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
}

label{
    display:block;
    margin-bottom:8px;
    color:#cbd5e1;
}

input,select{
    width:100%;
    padding:12px;
    background:#07111f;
    border:1px solid #294158;
    border-radius:6px;
    color:white;
}

button{
    margin-top:20px;
    padding:13px 22px;
    background:#00a9dc;
    border:0;
    border-radius:6px;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

.message{
    padding:13px;
    margin-bottom:20px;
    border-radius:6px;
}

.success{
    background:#123b2b;
    color:#72f0b1;
}

.error{
    background:#451e27;
    color:#ff9ba8;
}

.table-container{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:650px;
}

th,td{
    padding:14px;
    text-align:left;
    border-bottom:1px solid #20364b;
}

th{color:#00c6ff}
td{color:#d5e0ec}

@media(max-width:800px){
    .sidebar{
        width:100%;
        height:auto;
        position:relative;
    }

    .main{
        margin-left:0;
        padding:18px;
    }

    .form-grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<div class="sidebar">
    <div class="logo">NETGUARD</div>

    <a href="dashboard.php">Dashboard</a>
    <a href="connection.php">Customer Connection</a>
    <a href="customers.php" class="active">Customers</a>
    <a href="faults.php">Fault Logs</a>
    <a href="notifications.php">Notifications</a>
    <a href="report.php">Reports</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main">

<h1>Customer Management</h1>
<p class="subtitle">Register and manage NETGUARD customers.</p>

<?php if ($message !== ""): ?>
<div class="message <?= htmlspecialchars($messageType) ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="panel">
<h2>Register New Customer</h2>

<form method="POST" action="customers.php">

<div class="form-grid">

<div>
<label>Customer Full Name</label>
<input type="text" name="full_name" required>
</div>

<div>
<label>Username</label>
<input type="text" name="username" required>
</div>

<div>
<label>Customer Password</label>
<input type="password" name="password" minlength="8" required>
</div>

<div>
<label>Device Name</label>
<input type="text" name="device_name" placeholder="Customer Router 1" required>
</div>

<div>
<label>Device IP Address</label>
<input type="text" name="ip_address" placeholder="192.168.1.10" required>
</div>

<div>
<label>Device Type</label>
<select name="device_type" required>
<option value="">Select device type</option>
<option value="Router">Router</option>
<option value="Modem">Modem</option>
<option value="Access Point">Access Point</option>
<option value="Computer">Computer</option>
<option value="Other">Other</option>
</select>
</div>

</div>

<button type="submit" name="add_customer">Register Customer</button>

</form>
</div>

<div class="panel">
<h2>Registered Customers</h2>

<div class="table-container">
<table>
<thead>
<tr>
<th>ID</th>
<th>Full Name</th>
<th>Username</th>
<th>Device</th>
<th>IP Address</th>
<th>Device Type</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php if (count($customers) > 0): ?>

<?php foreach ($customers as $customer): ?>
<tr>
<td><?= htmlspecialchars($customer["id"]) ?></td>
<td><?= htmlspecialchars($customer["full_name"]) ?></td>
<td><?= htmlspecialchars($customer["username"]) ?></td>
<td><?= htmlspecialchars($customer["device_name"] ?? "Not linked") ?></td>
<td><?= htmlspecialchars($customer["ip_address"] ?? "—") ?></td>
<td><?= htmlspecialchars($customer["device_type"] ?? "—") ?></td>
<td><?= htmlspecialchars($customer["status"] ?? "—") ?></td>
</tr>
<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="7">No customers registered yet.</td>
</tr>

<?php endif; ?>

</tbody>
</table>
</div>
</div>

</div>
</body>
</html>