<?php

header("Content-Type: application/json");

require_once "config.php";

// Cek kolom yang tersedia di tabel monitoring
$colBulanCheck = $conn->query("SHOW COLUMNS FROM monitoring LIKE 'periode_bulan'");
$hasPeriodeBulan = ($colBulanCheck && $colBulanCheck->num_rows > 0);
$periodeSelect = $hasPeriodeBulan ? "m.periode_bulan," : "";

$colUnitCheck = $conn->query("SHOW COLUMNS FROM monitoring LIKE 'parameter_units'");
$hasParameterUnits = ($colUnitCheck && $colUnitCheck->num_rows > 0);
$unitSelect = $hasParameterUnits ? "m.parameter_units," : "";

$sql = "
    SELECT
        m.id,
        m.location,
        m.kode_cerobong,
        m.tanggal_mulai,
        m.tanggal_akhir,
        {$periodeSelect}
        {$unitSelect}
        m.waktu_input,


        p.dust,
        p.hci,
        p.hf,
        p.so2,
        p.no2,
        p.co,
        p.noc_as_ch4,
        p.zn,
        p.hg,
        p.cd,
        p.tl,
        p.sb,
        p.arsenic,
        p.cr,
        p.co_metal,
        p.cu,
        p.pb,
        p.mn,
        p.ni,
        p.v,
        p.be,
        p.se,
        p.pcdd_pcdf

    FROM monitoring m

    LEFT JOIN monitoring_parameter p
        ON p.monitoring_id = m.id

    ORDER BY m.id DESC
";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {

    $values = [
        "dust"       => $row["dust"],
        "hci"        => $row["hci"],
        "hf"         => $row["hf"],
        "so2"        => $row["so2"],
        "no2"         => $row["no2"],
        "co"          => $row["co"],
        "noc_as_ch4" => $row["noc_as_ch4"],
        "zn"          => $row["zn"],
        "hg"          => $row["hg"],
        "cd"          => $row["cd"],
        "tl"          => $row["tl"],
        "sb"          => $row["sb"],
        "arsenic"     => $row["arsenic"],
        "cr"          => $row["cr"],
        "co_metal"    => $row["co_metal"],
        "cu"          => $row["cu"],
        "pb"          => $row["pb"],
        "mn"          => $row["mn"],
        "ni"          => $row["ni"],
        "v"           => $row["v"],
        "be"          => $row["be"],
        "se"          => $row["se"],
        "pcdd_pcdf"   => $row["pcdd_pcdf"]
    ];

    $periodeBulan = "";
    if ($hasPeriodeBulan && !empty($row["periode_bulan"])) {
        $periodeBulan = $row["periode_bulan"];
    } elseif (!empty($row["tanggal_mulai"])) {
        $periodeBulan = substr($row["tanggal_mulai"], 0, 7);
    }

    $units = null;
    if ($hasParameterUnits && !empty($row["parameter_units"])) {
        $units = json_decode($row["parameter_units"], true);
    }

    $data[] = [
        "id"            => $row["id"],
        "location"      => $row["location"],
        "kodeCerobong"  => $row["kode_cerobong"],
        "startDate"     => $row["tanggal_mulai"],
        "endDate"       => $row["tanggal_akhir"],
        "periodeBulan"  => $periodeBulan,
        "values"        => $values,
        "units"         => $units,
        "timestamp"     => $row["waktu_input"]
    ];
}

echo json_encode([
    "success" => true,
    "data" => $data
]);

$conn->close();