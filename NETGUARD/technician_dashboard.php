<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "technician") {
    header("Location: index.php");
    exit;
}

$error = "";

// Accept assignment and notify customer
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accept_job"])) {

    $assignmentId = (int) $_POST["accept_job"];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            SELECT
                a.id,
                a.fault_id,
                a.customer_id,
                a.status,
                f.fault_type,
                u.full_name AS technician_name
            FROM technician_assignments a
            JOIN faults f ON a.fault_id = f.id
            JOIN users u ON a.technician_id = u.id
            WHERE a.id = ?
            AND a.technician_id = ?
            AND a.status = 'Assigned'
            FOR UPDATE
        ");

        $stmt->execute([
            $assignmentId,
            $_SESSION["user_id"]
        ]);

        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            $conn->rollBack();
            header("Location: technician_dashboard.php");
            exit;
        }

        $update = $conn->prepare("
            UPDATE technician_assignments
            SET status = 'Accepted', accepted_at = NOW()
            WHERE id = ?
            AND technician_id = ?
            AND status = 'Assigned'
        ");

        $update->execute([
            $assignmentId,
            $_SESSION["user_id"]
        ]);

        if ($update->rowCount() !== 1) {
            throw new Exception("Assignment could not be accepted.");
        }

        $customerMessage =
            "NETGUARD UPDATE: A technician, "
            . $assignment["technician_name"]
            . ", has accepted your internet repair request and is coming to fix your Wi-Fi connection. Reported fault: "
            . $assignment["fault_type"] . ".";

        $notify = $conn->prepare("
            INSERT INTO notifications
            (fault_id, message, status, recipient_role, recipient_id, notification_type)
            VALUES (?, ?, 'Unread', 'customer', ?, 'assignment')
        ");

        $notify->execute([
            $assignment["fault_id"],
            $customerMessage,
            $assignment["customer_id"]
        ]);

        $conn->commit();

        header("Location: technician_dashboard.php?accepted=1");
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = "Could not accept the assignment or notify the customer. Please try again.";
    }
}


// Complete accepted job
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["complete_job"])) {

    $assignmentId = (int) $_POST["complete_job"];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            SELECT
                a.id,
                a.fault_id,
                a.customer_id,
                a.status
            FROM technician_assignments a
            WHERE a.id = ?
            AND a.technician_id = ?
            AND a.status = 'Accepted'
            FOR UPDATE
        ");

        $stmt->execute([
            $assignmentId,
            $_SESSION["user_id"]
        ]);

        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            $conn->rollBack();
            header("Location: technician_dashboard.php");
            exit;
        }

        $update = $conn->prepare("
            UPDATE technician_assignments
            SET status = 'Completed',
                completed_at = NOW()
            WHERE id = ?
            AND technician_id = ?
            AND status = 'Accepted'
        ");

        $update->execute([
            $assignmentId,
            $_SESSION["user_id"]
        ]);

        if ($update->rowCount() !== 1) {
            throw new Exception("Job could not be completed.");
        }

        // Notify customer that the technician visit is complete.
        $customerMessage =
            "NETGUARD UPDATE: The technician has marked your repair visit as completed. NETGUARD will continue monitoring your internet connection and update you when service recovery is detected.";

        $notify = $conn->prepare("
            INSERT INTO notifications
            (fault_id, message, status, recipient_role, recipient_id, notification_type)
            VALUES (?, ?, 'Unread', 'customer', ?, 'assignment')
        ");

        $notify->execute([
            $assignment["fault_id"],
            $customerMessage,
            $assignment["customer_id"]
        ]);

        $conn->commit();

        header("Location: technician_dashboard.php?completed=1");
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = "Could not complete the job or notify the customer. Please try again.";
    }
}


// Get technician assignments
$stmt = $conn->prepare("
    SELECT
        a.id AS assignment_id,
        a.status AS assignment_status,
        a.assigned_at,
        f.fault_type,
        f.description,
        f.detected_at,
        d.device_name,
        d.ip_address
    FROM technician_assignments a
    JOIN faults f ON a.fault_id = f.id
    LEFT JOIN network_device d ON f.device_id = d.id
    WHERE a.technician_id = ?
    ORDER BY a.assigned_at DESC
");

$stmt->execute([$_SESSION["user_id"]]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalJobs = count($assignments);
$pendingJobs = 0;
$completedJobs = 0;

foreach ($assignments as $job) {
    if ($job["assignment_status"] === "Assigned") {
        $pendingJobs++;
    }

    if ($job["assignment_status"] === "Completed") {
        $completedJobs++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NETGUARD Technician Dashboard</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#07111f;color:white;font-family:Arial,sans-serif}
.sidebar{position:fixed;width:240px;height:100vh;background:#0a1728;padding:25px 15px}
.logo{font-size:25px;font-weight:bold;color:#00c6ff;margin-bottom:35px}
.sidebar a{display:block;padding:14px;color:#cbd5e1;text-decoration:none;border-radius:6px;margin-bottom:8px}
.sidebar a.active,.sidebar a:hover{background:#12304a;color:#00c6ff}
.main{margin-left:240px;padding:35px}
h1{color:#00c6ff}
.cards{display:flex;gap:20px;flex-wrap:wrap;margin:25px 0}
.card{background:#10243a;padding:20px;border-radius:9px;min-width:170px;flex:1}
.card span{display:block;font-size:28px;color:#00c6ff;margin-top:10px;font-weight:bold}
.jobs{background:#0a1728;padding:20px;border-radius:10px;margin-top:20px;overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:650px}
th,td{text-align:left;padding:14px;border-bottom:1px solid #24364b}
th{color:#00c6ff}
button{background:#00aee8;color:#00111c;border:0;padding:9px 14px;border-radius:5px;cursor:pointer;font-weight:bold}
button:hover{background:#55d8ff}
.status{color:#55e6a5}
.empty{text-align:center;padding:25px;color:#94a3b8}
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
    <a href="technician_dashboard.php" class="active">My Assignments</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main">

<h1>Field Technician Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($_SESSION["netguard_user"]) ?></p>

<?php if (isset($_GET["accepted"])): ?>
    <div class="success">
        Job accepted successfully. Customer notification sent.
    </div>
<?php endif; ?>

<?php if (isset($_GET["completed"])): ?>
    <div class="success">
        Job completed successfully. Customer notification sent.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="cards">
    <div class="card">
        Total Assignments
        <span><?= $totalJobs ?></span>
    </div>

    <div class="card">
        Pending Assignments
        <span><?= $pendingJobs ?></span>
    </div>

    <div class="card">
        Completed Jobs
        <span><?= $completedJobs ?></span>
    </div>
</div>

<div class="jobs">
<h2>My Assigned Faults</h2>

<table>
<thead>
<tr>
    <th>Fault</th>
    <th>Device</th>
    <th>IP Address</th>
    <th>Detected</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php if ($assignments): ?>

<?php foreach ($assignments as $job): ?>
<tr>
    <td><?= htmlspecialchars($job["fault_type"]) ?></td>
    <td><?= htmlspecialchars($job["device_name"] ?? "Unknown") ?></td>
    <td><?= htmlspecialchars($job["ip_address"] ?? "N/A") ?></td>
    <td><?= htmlspecialchars($job["detected_at"]) ?></td>
    <td class="status"><?= htmlspecialchars($job["assignment_status"]) ?></td>
    <td>

        <?php if ($job["assignment_status"] === "Assigned"): ?>

            <form method="POST">
                <button type="submit" name="accept_job"
                    value="<?= (int)$job["assignment_id"] ?>">
                    Accept Job
                </button>
            </form>

        <?php elseif ($job["assignment_status"] === "Accepted"): ?>

            <form method="POST">
                <button type="submit" name="complete_job"
                    value="<?= (int)$job["assignment_id"] ?>">
                    Complete Job
                </button>
            </form>

        <?php else: ?>

            Completed

        <?php endif; ?>

    </td>
</tr>
<?php endforeach; ?>

<?php else: ?>
<tr>
    <td colspan="6" class="empty">
        No assignments received yet.
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

</div>
</body>
</html>