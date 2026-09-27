<?php

header("Content-Type: application/json");

require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diperbolehkan"
    ]);

    exit;
}

$conn->begin_transaction();

try {

    /*
     * Karena FK menggunakan ON DELETE CASCADE,
     * cukup hapus dari tabel monitoring.
     */

    $sql = "DELETE FROM monitoring";

    if (!$conn->query($sql)) {
        throw new Exception($conn->error);
    }

    /*
     * Reset AUTO_INCREMENT
     */

    $conn->query(
        "ALTER TABLE monitoring AUTO_INCREMENT = 1"
    );

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Seluruh data berhasil dihapus"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}

$conn->close();
?>