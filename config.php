<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "dbklhk_monitoring";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Koneksi database gagal: " . $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");