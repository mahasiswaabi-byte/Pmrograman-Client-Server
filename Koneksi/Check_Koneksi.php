<?php
try {
    require_once __DIR__ . "/Koneksi.php";
    echo "Koneksi Sukses";
} catch (Throwable $error) {
    http_response_code(500);
    echo "Koneksi Gagal";
}
?>
