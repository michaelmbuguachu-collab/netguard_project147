<?php
require_once "db.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !is_array($data) || empty($data["device_id"])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid device ID is required."
    ]);
    exit;
}

$deviceId = (int)$data["device_id"];

try {
    $conn->beginTransaction();

    // Recover only active faults belonging to this device.
    $stmt = $conn->prepare("
        UPDATE faults
        SET status = 'recovered'
        WHERE device_id = ?
        AND status = 'active'
    ");

    $stmt->execute([$deviceId]);

    $recoveredCount = $stmt->rowCount();

    if ($recoveredCount > 0) {
        // Notify administrator.
        $notify = $conn->prepare("
            INSERT INTO notifications
            (fault_id, message, status, recipient_role, notification_type)
            SELECT id, 'NETGUARD detected that the device connection has been restored.',
                   'Unread', 'admin', 'recovery'
            FROM faults
            WHERE device_id = ?
            AND status = 'recovered'
            ORDER BY id DESC
            LIMIT ?
        ");

        $notify->bindValue(1, $deviceId, PDO::PARAM_INT);
        $notify->bindValue(2, $recoveredCount, PDO::PARAM_INT);
        $notify->execute();
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Device recovery processed.",
        "device_id" => $deviceId,
        "recovered_count" => $recoveredCount
    ]);

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Recovery failed."
    ]);
}
?>