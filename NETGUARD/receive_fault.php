<?php
require_once "db.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid data"
    ]);
    exit;
}

$faultType = $data["fault_type"] ?? "Internet Connection Failure";
$description = $data["description"] ?? "NETGUARD detected an internet connection failure.";
$deviceId = !empty($data["device_id"]) ? (int)$data["device_id"] : null;

try {
    $conn->beginTransaction();

    // Identify the customer through the affected device.
    $customer = null;

    if ($deviceId !== null) {
        $customerStmt = $conn->prepare("
            SELECT u.id, u.full_name
            FROM network_device nd
            INNER JOIN users u ON nd.customer_id = u.id
            WHERE nd.id = ?
            AND u.role = 'customer'
            LIMIT 1
        ");

        $customerStmt->execute([$deviceId]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Find a registered field technician.
    $techStmt = $conn->prepare("
        SELECT id, full_name
        FROM users
        WHERE role = 'technician'
        ORDER BY id ASC
        LIMIT 1
    ");

    $techStmt->execute();
    $technician = $techStmt->fetch(PDO::FETCH_ASSOC);

    // Record the fault.
    $stmt = $conn->prepare("
        INSERT INTO faults
        (device_id, fault_type, description, status, detected_at)
        VALUES (?, ?, ?, 'active', NOW())
    ");

    $stmt->execute([
        $deviceId,
        $faultType,
        $description
    ]);

    $faultId = $conn->lastInsertId();

    // Notify administrator.
    $adminMessage = "NETGUARD detected: " . $faultType;

    $adminNotify = $conn->prepare("
        INSERT INTO notifications
        (fault_id, message, status, recipient_role, notification_type)
        VALUES (?, ?, 'Unread', 'admin', 'fault')
    ");

    $adminNotify->execute([
        $faultId,
        $adminMessage
    ]);

    $assignmentCreated = false;

    // Assign technician only when the actual customer is identified.
    if ($technician && $customer) {

        $assignStmt = $conn->prepare("
            INSERT INTO technician_assignments
            (fault_id, technician_id, customer_id, status)
            VALUES (?, ?, ?, 'Assigned')
        ");

        $assignStmt->execute([
            $faultId,
            $technician["id"],
            $customer["id"]
        ]);

        // Notify the assigned technician.
        $technicianMessage =
            "New automatic NETGUARD assignment. "
            . "Customer: " . $customer["full_name"]
            . ". Fault: " . $faultType
            . ". Please review and accept the job.";

        $techNotify = $conn->prepare("
            INSERT INTO notifications
            (fault_id, message, status, recipient_role, recipient_id, notification_type)
            VALUES (?, ?, 'Unread', 'technician', ?, 'assignment')
        ");

        $techNotify->execute([
            $faultId,
            $technicianMessage,
            $technician["id"]
        ]);

        $assignmentCreated = true;
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => $assignmentCreated
            ? "Fault recorded and correct customer technician assignment created."
            : "Fault recorded, but assignment was not created. Check device customer link and technician account.",
        "fault_id" => $faultId,
        "assignment_created" => $assignmentCreated,
        "customer" => $customer["full_name"] ?? null,
        "technician" => $technician["full_name"] ?? null
    ]);

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Could not record fault or create assignment."
    ]);
}
?>