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

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Data JSON tidak valid"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Data utama
|--------------------------------------------------------------------------
*/

$location       = $data["location"] ?? "";
$kodeCerobong   = $data["kodeCerobong"] ?? "";
$startDate      = $data["startDate"] ?? "";
$endDate        = $data["endDate"] ?? "";

if (
    empty($location) ||
    empty($kodeCerobong) ||
    empty($startDate) ||
    empty($endDate)
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Location, kode cerobong dan periode wajib diisi"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Daftar parameter
|--------------------------------------------------------------------------
*/

$parameters = [
    "dust",
    "hci",
    "hf",
    "so2",
    "no2",
    "co",
    "noc_as_ch4",
    "zn",
    "hg",
    "cd",
    "tl",
    "sb",
    "arsenic",
    "cr",
    "co_metal",
    "cu",
    "pb",
    "mn",
    "ni",
    "v",
    "be",
    "se",
    "pcdd_pcdf"
];


/*
|--------------------------------------------------------------------------
| Validasi nilai parameter
|--------------------------------------------------------------------------
*/

$values = [];

foreach ($parameters as $parameter) {

    $value = $data["values"][$parameter] ?? null;

    if ($value === "" || $value === null) {
        $values[$parameter] = null;
        continue;
    }

    if (!is_numeric($value) || $value < 0 || $value > 3500) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Nilai parameter $parameter harus berada antara 0 sampai 3500"
        ]);

        exit;
    }

    $values[$parameter] = $value;
}


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | INSERT TABLE MONITORING
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO monitoring
        (
            location,
            kode_cerobong,
            tanggal_mulai,
            tanggal_akhir
        )
        VALUES (?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ssss",
        $location,
        $kodeCerobong,
        $startDate,
        $endDate
    );

    $stmt->execute();

    $monitoringId = $conn->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | INSERT TABLE MONITORING_PARAMETER
    |--------------------------------------------------------------------------
    */

    $sqlParameter = "
        INSERT INTO monitoring_parameter
        (
            monitoring_id,
            dust,
            hci,
            hf,
            so2,
            no2,
            co,
            noc_as_ch4,
            zn,
            hg,
            cd,
            tl,
            sb,
            arsenic,
            cr,
            co_metal,
            cu,
            pb,
            mn,
            ni,
            v,
            be,
            se,
            pcdd_pcdf
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";

    $stmt = $conn->prepare($sqlParameter);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "iddddddddddddddddddddddd",
        $monitoringId,
        $values["dust"],
        $values["hci"],
        $values["hf"],
        $values["so2"],
        $values["no2"],
        $values["co"],
        $values["noc_as_ch4"],
        $values["zn"],
        $values["hg"],
        $values["cd"],
        $values["tl"],
        $values["sb"],
        $values["arsenic"],
        $values["cr"],
        $values["co_metal"],
        $values["cu"],
        $values["pb"],
        $values["mn"],
        $values["ni"],
        $values["v"],
        $values["be"],
        $values["se"],
        $values["pcdd_pcdf"]
    );

    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Data monitoring berhasil disimpan",
        "monitoring_id" => $monitoringId
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