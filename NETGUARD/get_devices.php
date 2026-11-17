<?php
require_once "db.php";

header("Content-Type: application/json");

$allowedAddresses = ["127.0.0.1", "::1"];

if (!in_array($_SERVER["REMOTE_ADDR"] ?? "", $allowedAddresses, true)) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied"
    ]);
    exit;
}

try {
    $stmt = $conn->query("
        SELECT id, device_name, ip_address, customer_id
        FROM network_device
        WHERE customer_id IS NOT NULL
        ORDER BY id ASC
    ");

    echo json_encode([
        "success" => true,
        "devices" => $stmt->fetchAll()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not retrieve devices"
    ]);
}
?>