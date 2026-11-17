<?php
session_start();

if (!isset($_SESSION["netguard_user"])) {
    header("Location: index.php");
    exit;
}

require_once "db.php";

// Mark notification as read
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_read"])) {
    $notificationId = (int) $_POST["mark_read"];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET status = 'Read'
        WHERE id = ?
    ");

    $stmt->execute([$notificationId]);

    header("Location: notifications.php");
    exit;
}

// Get notifications
$stmt = $conn->query("
    SELECT id, fault_id, message, status, created_at
    FROM notifications
    ORDER BY id DESC
");

$notifications = $stmt->fetchAll();

$unreadCount = 0;

foreach ($notifications as $notification) {
    if (strtolower($notification["status"]) === "unread") {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NETGUARD - Notifications</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #07111f;
    color: #ffffff;
}

.sidebar {
    position: fixed;
    width: 240px;
    height: 100vh;
    background: #0a1728;
    padding: 25px 15px;
}

.logo {
    color: #00c6ff;
    font-size: 25px;
    font-weight: bold;
    margin-bottom: 35px;
}

.sidebar a {
    display: block;
    color: #cbd5e1;
    text-decoration: none;
    padding: 14px;
    margin-bottom: 8px;
    border-radius: 6px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #12304a;
    color: #00c6ff;
}

.main {
    margin-left: 240px;
    padding: 35px;
}

h1 {
    color: #00c6ff;
}

.summary {
    background: #10243a;
    padding: 18px;
    border-radius: 8px;
    margin: 25px 0;
    max-width: 260px;
}

.summary span {
    display: block;
    color: #00c6ff;
    font-size: 28px;
    font-weight: bold;
    margin-top: 8px;
}

.table-container {
    background: #0a1728;
    padding: 20px;
    border-radius: 10px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #24364b;
}

th {
    color: #00c6ff;
}

.status {
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: bold;
}

.unread {
    background: #493719;
    color: #ffd166;
}

.read {
    background: #123d35;
    color: #55e6a5;
}

button {
    background: #00a9dc;
    color: #ffffff;
    border: none;
    padding: 9px 13px;
    border-radius: 5px;
    cursor: pointer;
}

button:hover {
    background: #008bb8;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
}

@media(max-width: 750px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="sidebar">
    <div class="logo">NETGUARD</div>

    <a href="dashboard.php">Dashboard</a>
    <a href="connection.php">Customer Connection</a>
    <a href="faults.php">Fault Logs</a>
    <a href="notifications.php" class="active">Notifications</a>
    <a href="report.php">Reports</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main">

    <h1>Fault Notifications</h1>
    <p>Manage and acknowledge detected internet failures.</p>

    <div class="summary">
        Unread Notifications
        <span><?php echo $unreadCount; ?></span>
    </div>

    <div class="table-container">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fault ID</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($notifications) > 0): ?>

                <?php foreach ($notifications as $notification): ?>

                    <tr>
                        <td><?php echo htmlspecialchars($notification["id"]); ?></td>

                        <td><?php echo htmlspecialchars($notification["fault_id"]); ?></td>

                        <td><?php echo htmlspecialchars($notification["message"]); ?></td>

                        <td><?php echo htmlspecialchars($notification["created_at"]); ?></td>

                        <td>
                            <?php if (strtolower($notification["status"]) === "unread"): ?>
                                <span class="status unread">Unread</span>
                            <?php else: ?>
                                <span class="status read">Read</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (strtolower($notification["status"]) === "unread"): ?>
                                <form method="POST">
                                    <button type="submit"
                                        name="mark_read"
                                        value="<?php echo (int)$notification["id"]; ?>">
                                        Mark as Read
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color:#55e6a5;">Acknowledged</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        No notifications available.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>