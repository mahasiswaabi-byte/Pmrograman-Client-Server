<?php
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Metode tidak diizinkan."]);
    exit;
}

$loginUsername = trim($_POST["username"] ?? "");
$loginPassword = $_POST["password"] ?? "";

if ($loginUsername === "" || $loginPassword === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Nama pengguna dan kata sandi wajib diisi."]);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . "/Koneksi.php";
    mysqli_set_charset($conn, "utf8mb4");

    $statement = mysqli_prepare($conn, "SELECT `Password` FROM `login` WHERE `Username` = ? LIMIT 1");
    mysqli_stmt_bind_param($statement, "s", $loginUsername);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $storedPassword);
    $accountFound = mysqli_stmt_fetch($statement);
    mysqli_stmt_close($statement);

    $passwordInfo = $accountFound ? password_get_info($storedPassword) : ["algo" => null];
    $passwordValid = $accountFound && (
        ($passwordInfo["algo"] !== null && password_verify($loginPassword, $storedPassword)) ||
        ($passwordInfo["algo"] === null && hash_equals($storedPassword, $loginPassword))
    );

    if (!$passwordValid) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Nama pengguna atau kata sandi salah."]);
        exit;
    }

    echo json_encode(["success" => true]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
}