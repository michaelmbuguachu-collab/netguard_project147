<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "customer") {
    header("Location: index.php");
    exit;
}

$customerId = (int) $_SESSION["user_id"];
$error = "";

// Mark notification as read
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_read"])) {

    $notificationId = (int) $_POST["mark_read"];

    try {
        $stmt = $conn->prepare("
            UPDATE notifications
            SET status = 'Read'
            WHERE id = ?
            AND recipient_role = 'customer'
            AND recipient_id = ?
            AND status = 'Unread'
        ");

        $stmt->execute([
            $notificationId,
            $customerId
        ]);

        header("Location: customer_dashboard.php?read=1");
        exit;

    } catch (PDOException $e) {
        $error = "Could not update notification. Please try again.";
    }
}

// Get customer notifications
$stmt = $conn->prepare("
    SELECT id, message, status, created_at
    FROM notifications
    WHERE recipient_role = 'customer'
    AND recipient_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$customerId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
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
<title>NETGUARD Customer Dashboard</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#07111f;color:white;font-family:Arial,sans-serif}
.sidebar{position:fixed;width:240px;height:100vh;background:#0a1728;padding:25px 15px}
.logo{font-size:25px;font-weight:bold;color:#00c6ff;margin-bottom:35px}
.sidebar a{display:block;padding:14px;color:#cbd5e1;text-decoration:none;border-radius:6px;margin-bottom:8px}
.sidebar a.active,.sidebar a:hover{background:#12304a;color:#00c6ff}
.main{margin-left:240px;padding:35px}
h1{color:#00c6ff}
.card{background:#10243a;padding:22px;border-radius:9px;max-width:260px;margin:25px 0}
.card span{display:block;font-size:28px;color:#00c6ff;margin-top:10px;font-weight:bold}
.notifications{background:#0a1728;padding:20px;border-radius:10px}
.notification{padding:17px;border-bottom:1px solid #24364b}
.notification:last-child{border-bottom:0}
.message{margin-bottom:10px;line-height:1.5}
.date{font-size:12px;color:#94a3b8}
.status{color:#00c6ff;font-size:13px;margin-top:8px}
.empty{text-align:center;padding:25px;color:#94a3b8}
button{background:#00aee8;color:#00111c;border:0;padding:9px 14px;border-radius:5px;cursor:pointer;font-weight:bold;margin-top:10px}
button:hover{background:#55d8ff}
.read{color:#55e6a5}
.success{background:#123d35;color:#55e6a5;padding:13px;border-radius:6px;margin:15px 0}
.error{background:#491f2a;color:#ff9999;padding:13px;border-radius:6px;margin:15px 0}
@media(max-width:750px){
.sidebar{position:relative;width:100%;height:auto}
.main{margin-left:0;padding:20px}
}
</style>
</head>

<body>

<div class="sidebar">
    <div class="logo">NETGUARD</div>
    <a href="customer_dashboard.php" class="active">My Notifications</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main">

<h1>Customer Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($_SESSION["netguard_user"]) ?></p>

<?php if (isset($_GET["read"])): ?>
    <div class="success">
        Notification marked as read successfully.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    Unread Notifications
    <span><?= $unreadCount ?></span>
</div>

<div class="notifications">
<h2>Service Updates</h2>

<?php if ($notifications): ?>

    <?php foreach ($notifications as $notification): ?>
        <div class="notification">

            <div class="message">
                <?= htmlspecialchars($notification["message"]) ?>
            </div>

            <div class="date">
                <?= htmlspecialchars($notification["created_at"]) ?>
            </div>

            <div class="status <?= strtolower($notification["status"]) === 'read' ? 'read' : '' ?>">
                <?= htmlspecialchars($notification["status"]) ?>
            </div>

            <?php if (strtolower($notification["status"]) === "unread"): ?>
                <form method="POST">
                    <button
                        type="submit"
                        name="mark_read"
                        value="<?= (int)$notification["id"] ?>">
                        Mark as Read
                    </button>
                </form>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>

<?php else: ?>

    <div class="empty">
        No service updates yet.
    </div>

<?php endif; ?>

</div>
</div>
</body>
</html>