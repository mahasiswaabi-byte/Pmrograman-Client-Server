<?php
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Metode tidak diizinkan."]);
    exit;
}

session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $cookieParameters = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $cookieParameters["path"],
        $cookieParameters["domain"],
        $cookieParameters["secure"],
        $cookieParameters["httponly"]
    );
}

if (!session_destroy()) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Sesi tidak dapat diakhiri."]);
    exit;
}

$acceptHeader = $_SERVER["HTTP_ACCEPT"] ?? "";
if (strpos($acceptHeader, "application/json") === false) {
    header("Location: ../Interface.html?login=1", true, 303);
    exit;
}

echo json_encode(["success" => true, "message" => "Logout berhasil."]);
