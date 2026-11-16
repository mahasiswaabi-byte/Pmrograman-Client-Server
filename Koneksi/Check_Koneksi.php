<?php
try {
    require_once __DIR__ . "/Koneksi.php";
    echo "Connection successful";
} catch (Throwable $error) {
    http_response_code(500);
    echo "Connection failed";
}
?>
