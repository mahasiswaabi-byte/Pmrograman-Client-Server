<?php
header("Content-Type: application/json; charset=utf-8");
session_start();

function roomRespond($statusCode, $success, $message, $extra = [])
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["username"]) || !is_string($_SESSION["username"])) {
    roomRespond(401, false, "Silakan login terlebih dahulu.");
}

if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . "/Koneksi.php";
    mysqli_set_charset($conn, "utf8mb4");

    $username = $_SESSION["username"];
    $roleStatement = mysqli_prepare($conn, "SELECT `Role` FROM `login` WHERE `Username` = ? LIMIT 1");
    mysqli_stmt_bind_param($roleStatement, "s", $username);
    mysqli_stmt_execute($roleStatement);
    mysqli_stmt_bind_result($roleStatement, $storedRole);
    $accountFound = mysqli_stmt_fetch($roleStatement);
    mysqli_stmt_close($roleStatement);

    if (!$accountFound) {
        roomRespond(401, false, "Akun tidak ditemukan.");
    }

    $role = $storedRole === "admin" ? "admin" : ($storedRole === "dosen" ? "dosen" : "mahasiswa");

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $result = mysqli_query($conn, "SELECT `RoomName`, `RoomStatus`, `UpdatedAt` FROM `class_rooms` ORDER BY `Id` ASC");
        $rooms = mysqli_fetch_all($result, MYSQLI_ASSOC);
        roomRespond(200, true, "Data ruangan berhasil dimuat.", ["rooms" => $rooms, "role" => $role]);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        roomRespond(405, false, "Metode tidak diizinkan.");
    }

    $submittedToken = $_POST["csrf_token"] ?? "";
    if (!is_string($submittedToken) || !hash_equals($_SESSION["csrf_token"], $submittedToken)) {
        roomRespond(400, false, "Permintaan tidak valid. Muat ulang halaman dan coba lagi.");
    }

    $action = trim($_POST["action"] ?? "");

    if ($action === "set_room_status") {
        if ($role !== "dosen" && $role !== "admin") {
            roomRespond(403, false, "Hanya dosen atau admin yang dapat mengatur status ruangan.");
        }

        $roomName = trim($_POST["room_name"] ?? "");
        $roomStatus = trim($_POST["room_status"] ?? "");
        if ($roomName === "" || !in_array($roomStatus, ["open", "locked"], true)) {
            roomRespond(400, false, "Nama ruangan dan status tidak valid.");
        }

        $roomCheck = mysqli_prepare($conn, "SELECT 1 FROM `class_rooms` WHERE `RoomName` = ? LIMIT 1");
        mysqli_stmt_bind_param($roomCheck, "s", $roomName);
        mysqli_stmt_execute($roomCheck);
        mysqli_stmt_store_result($roomCheck);
        if (mysqli_stmt_num_rows($roomCheck) !== 1) {
            mysqli_stmt_close($roomCheck);
            roomRespond(404, false, "Ruangan tidak ditemukan.");
        }
        mysqli_stmt_close($roomCheck);

        $update = mysqli_prepare(
            $conn,
            "UPDATE `class_rooms` SET `RoomStatus` = ?, `UpdatedBy` = ? WHERE `RoomName` = ?"
        );
        mysqli_stmt_bind_param($update, "sss", $roomStatus, $username, $roomName);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);

        roomRespond(200, true, "Status " . $roomName . " berhasil diperbarui.");
    }

    if ($action === "verify_access") {
        if ($role !== "mahasiswa") {
            roomRespond(403, false, "Hanya mahasiswa yang dapat melakukan verifikasi masuk.");
        }

        $roomName = trim($_POST["room_name"] ?? "");
        $fingerprintVerified = (($_POST["fingerprint_verified"] ?? "") === "1") ? 1 : 0;
        $faceVerified = (($_POST["face_verified"] ?? "") === "1") ? 1 : 0;

        if ($roomName === "" || $fingerprintVerified !== 1 || $faceVerified !== 1) {
            roomRespond(400, false, "Sidik jari dan pengenalan wajah harus berhasil terlebih dahulu.");
        }

        $roomStatement = mysqli_prepare($conn, "SELECT `RoomStatus` FROM `class_rooms` WHERE `RoomName` = ? LIMIT 1");
        mysqli_stmt_bind_param($roomStatement, "s", $roomName);
        mysqli_stmt_execute($roomStatement);
        mysqli_stmt_bind_result($roomStatement, $roomStatus);
        if (!mysqli_stmt_fetch($roomStatement)) {
            mysqli_stmt_close($roomStatement);
            roomRespond(404, false, "Ruangan tidak ditemukan.");
        }
        mysqli_stmt_close($roomStatement);

        if ($roomStatus !== "open") {
            roomRespond(403, false, "Akses ditolak. Ruangan sedang dikunci.");
        }

        $insert = mysqli_prepare(
            $conn,
            "INSERT INTO `class_attendance` (`Username`, `RoomName`, `FingerprintVerified`, `FaceVerified`) VALUES (?, ?, 1, 1)"
        );
        mysqli_stmt_bind_param($insert, "ss", $username, $roomName);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);

        roomRespond(200, true, "Verifikasi berhasil. Akses ke " . $roomName . " dibuka.");
    }

    roomRespond(400, false, "Aksi ruangan tidak dikenali.");
} catch (Throwable $error) {
    error_log("Room API error: " . $error->getMessage());
    roomRespond(500, false, "Data ruangan tidak dapat diproses. Pastikan Setup_Database.sql sudah dijalankan.");
}
