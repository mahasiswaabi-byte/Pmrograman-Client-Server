<?php
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Metode tidak diizinkan."]);
    exit;
}

session_start();
$authenticated = isset($_SESSION["username"]) && is_string($_SESSION["username"]);
$role = null;

if ($authenticated) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        require_once __DIR__ . "/Koneksi.php";
        mysqli_set_charset($conn, "utf8mb4");

        $statement = mysqli_prepare($conn, "SELECT `Role` FROM `login` WHERE `Username` = ? LIMIT 1");
        mysqli_stmt_bind_param($statement, "s", $_SESSION["username"]);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $role);
        $accountFound = mysqli_stmt_fetch($statement);
        mysqli_stmt_close($statement);

        if (!$accountFound) {
            $_SESSION = [];
            session_destroy();
            $authenticated = false;
            $role = null;
        } else {
            $role = $role === "admin"
                ? "admin"
                : ($role === "dosen" ? "dosen" : "mahasiswa");
            $_SESSION["role"] = $role;
        }
    } catch (Throwable $error) {
        error_log("Session role lookup error: " . $error->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Status akun tidak dapat diperiksa."]);
        exit;
    }
}

echo json_encode([
    "success" => true,
    "authenticated" => $authenticated,
    "username" => $authenticated ? $_SESSION["username"] : null,
    "role" => $role,
]);
