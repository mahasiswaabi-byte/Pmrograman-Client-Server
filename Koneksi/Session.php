<?php
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Metode tidak diizinkan."]);
    exit;
}

session_start();
$authenticated = isset($_SESSION["username"]) && is_string($_SESSION["username"]);

echo json_encode([
    "success" => true,
    "authenticated" => $authenticated,
    "username" => $authenticated ? $_SESSION["username"] : null,
]);
