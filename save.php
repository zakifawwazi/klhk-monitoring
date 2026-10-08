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

$location       = trim($data["location"] ?? "");
$kodeCerobong   = trim($data["kodeCerobong"] ?? "");
$inputDate      = trim($data["inputDate"] ?? "");
$startDate      = trim($data["startDate"] ?? $data["tanggal"] ?? $inputDate ?? "");
$endDate        = trim($data["endDate"] ?? $data["tanggal"] ?? $inputDate ?? "");
$periodeBulan   = trim($data["periodeBulan"] ?? "");

// Jika inputDate diisi tapi startDate/endDate kosong, gunakan inputDate
if (!empty($inputDate) && empty($startDate)) $startDate = $inputDate;
if (!empty($inputDate) && empty($endDate)) $endDate = $inputDate;

// Jika startDate terisi tetapi endDate kosong, samakan dengan startDate
if (!empty($startDate) && empty($endDate)) {
    $endDate = $startDate;
}

// Jika periodeBulan kosong, ambil dari startDate
if (empty($periodeBulan) && !empty($startDate)) {
    $periodeBulan = substr($startDate, 0, 7);
} elseif (!empty($periodeBulan) && empty($startDate)) {
    $startDate = $periodeBulan . "-01";
    $endDate = date("Y-m-t", strtotime($startDate));
}

// Data satuan parameter jika dikirim
$units = $data["units"] ?? null;
$parameterUnitsJson = !empty($units) ? json_encode($units, JSON_UNESCAPED_UNICODE) : null;

if (
    empty($location) ||
    empty($kodeCerobong) ||
    empty($startDate)
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Location, kode cerobong dan tanggal wajib diisi"
    ]);

    exit;
}

if (!empty($endDate) && $startDate > $endDate) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Tanggal akhir tidak boleh lebih kecil dari tanggal mulai"
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

// Batas maksimum per parameter sesuai baku mutu
$paramMaxVal = [
    "dust"       => 130,
    "hci"        => 40,
    "hf"         => 4,
    "so2"        => 1300,
    "no2"        => 1600,
    "co"         => 6000,
    "noc_as_ch4" => 200,
    "zn"         => 150,
    "hg"         => 0.4,
    "cd"         => 0.4,
    "tl"         => 0.4,
    "sb"         => 1,
    "arsenic"    => 2,
    "cr"         => 2,
    "co_metal"   => 1,
    "cu"         => 1,
    "pb"         => 10,
    "mn"         => 10,
    "ni"         => 1,
    "v"          => 1,
    "be"         => 0.2,
    "se"         => 2,
    "pcdd_pcdf"  => 0.2
];

$values = [];

foreach ($parameters as $parameter) {

    $value = $data["values"][$parameter] ?? null;

    if ($value === "" || $value === null) {
        $values[$parameter] = null;
        continue;
    }

    if (is_string($value)) {
        $value = str_replace(",", ".", trim($value));
    }

    if (!is_numeric($value) || $value < 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Nilai parameter $parameter harus berupa angka positif"
        ]);
        exit;
    }

    // Validasi batas maksimum per parameter
    $maxVal = $paramMaxVal[$parameter] ?? null;
    if ($maxVal !== null && $value > $maxVal) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Nilai parameter $parameter melebihi batas maksimum ($maxVal)"
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

    // Cek kolom yang tersedia di tabel monitoring
    $colBulanCheck = $conn->query("SHOW COLUMNS FROM monitoring LIKE 'periode_bulan'");
    $hasPeriodeBulan = ($colBulanCheck && $colBulanCheck->num_rows > 0);

    $colUnitCheck = $conn->query("SHOW COLUMNS FROM monitoring LIKE 'parameter_units'");
    $hasParameterUnits = ($colUnitCheck && $colUnitCheck->num_rows > 0);

    $cols = ["location", "kode_cerobong", "tanggal_mulai", "tanggal_akhir"];
    $placeholders = ["?", "?", "?", "?"];
    $types = "ssss";
    $bindVals = [$location, $kodeCerobong, $startDate, $endDate];

    if ($hasPeriodeBulan) {
        $cols[] = "periode_bulan";
        $placeholders[] = "?";
        $types .= "s";
        $bindVals[] = $periodeBulan;
    }

    if ($hasParameterUnits) {
        $cols[] = "parameter_units";
        $placeholders[] = "?";
        $types .= "s";
        $bindVals[] = $parameterUnitsJson;
    }

    $sql = "INSERT INTO monitoring (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param($types, ...$bindVals);
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