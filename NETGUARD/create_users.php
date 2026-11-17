<?php
require_once "db.php";

$users = [
    ["NETGUARD Administrator", "admin", "admin123", "admin"],
    ["Field Technician", "technician", "tech12345", "technician"],
    ["Demo Customer", "customer", "customer12345", "customer"]
];

try {
    foreach ($users as $user) {
        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ?"
        );
        $check->execute([$user[1]]);

        if (!$check->fetch()) {
            $stmt = $conn->prepare("
                INSERT INTO users
                (full_name, username, password, role)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $user[0],
                $user[1],
                password_hash($user[2], PASSWORD_DEFAULT),
                $user[3]
            ]);
        }
    }

    echo "All three NETGUARD accounts created successfully.";

} catch (PDOException $e) {
    echo "Account creation failed: " . $e->getMessage();
}
?>