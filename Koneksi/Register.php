<?php
header("Content-Type: application/json; charset=utf-8");

function respond($statusCode, $success, $message)
{
    http_response_code($statusCode);
    echo json_encode(
        ["success" => $success, "message" => $message],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, false, "Metode tidak diizinkan.");
}

$fullName = trim($_POST["full_name"] ?? "");
$username = trim($_POST["username"] ?? "");
$nik = trim($_POST["nik"] ?? "");
$birthDate = trim($_POST["birth_date"] ?? "");
$email = trim($_POST["email"] ?? "");
$province = trim($_POST["province"] ?? ($_POST["region"] ?? ""));
$regency = trim($_POST["regency"] ?? "");
$district = trim($_POST["district"] ?? "");
$village = trim($_POST["village"] ?? "");
$gender = trim($_POST["gender"] ?? "");
$country = trim($_POST["country"] ?? "");
$role = "mahasiswa";
$password = $_POST["password"] ?? "";
$passwordConfirm = $_POST["password_confirm"] ?? "";
$latitudeValue = $_POST["latitude"] ?? "";
$longitudeValue = $_POST["longitude"] ?? "";

if ($fullName === "" || mb_strlen($fullName, "UTF-8") > 100) {
    respond(400, false, "Nama lengkap wajib diisi dan maksimal 100 karakter.");
}

if (!preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username)) {
    respond(400, false, "Nama pengguna harus 3-50 karakter dan hanya berisi huruf, angka, titik, garis bawah, atau tanda hubung.");
}

if (!preg_match('/\A[0-9]{16}\z/', $nik)) {
    respond(400, false, "NIK harus terdiri dari tepat 16 digit.");
}

$birthDateObject = DateTime::createFromFormat("Y-m-d", $birthDate);
if (!$birthDateObject || $birthDateObject->format("Y-m-d") !== $birthDate) {
    respond(400, false, "Tanggal lahir tidak valid.");
}
if ($birthDateObject >= new DateTime("today")) {
    respond(400, false, "Tanggal lahir harus sebelum hari ini.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    respond(400, false, "Alamat email tidak valid.");
}

foreach (
    [
        "Provinsi/daerah" => $province,
        "Kabupaten/kota" => $regency,
        "Kecamatan" => $district,
        "Desa/kelurahan" => $village,
    ] as $locationName => $locationValue
) {
    if ($locationValue === "" || mb_strlen($locationValue, "UTF-8") > 100) {
        respond(400, false, $locationName . " wajib diisi dan maksimal 100 karakter.");
    }
}

if (!in_array($gender, ["Laki-laki", "Perempuan", "Lainnya"], true)) {
    respond(400, false, "Jenis kelamin tidak valid.");
}

if ($country === "" || mb_strlen($country, "UTF-8") > 100) {
    respond(400, false, "Negara wajib diisi dan maksimal 100 karakter.");
}

if (strlen($password) < 8 || strlen($password) > 72) {
    respond(400, false, "Kata sandi harus terdiri dari 8-72 byte.");
}

if (!hash_equals($password, $passwordConfirm)) {
    respond(400, false, "Kata sandi dan konfirmasi kata sandi tidak sama.");
}

$photoPath = null;
if (!isset($_FILES["photo"]) || !is_uploaded_file($_FILES["photo"]["tmp_name"])) {
    respond(400, false, "Foto Mahasiswa wajib diunggah.");
}
if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
    respond(400, false, "Foto gagal diunggah.");
}
if ((int) $_FILES["photo"]["size"] > 3 * 1024 * 1024) {
    respond(400, false, "Ukuran foto maksimal 3 MB.");
}
$allowedPhotoTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp",
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$photoMime = $finfo->file($_FILES["photo"]["tmp_name"]);
if (!isset($allowedPhotoTypes[$photoMime])) {
    respond(400, false, "Foto harus berformat JPG, PNG, atau WebP.");
}

$latitude = null;
$longitude = null;
if ($latitudeValue !== "" || $longitudeValue !== "") {
    if (
        $latitudeValue === "" ||
        $longitudeValue === "" ||
        !is_numeric($latitudeValue) ||
        !is_numeric($longitudeValue)
    ) {
        respond(400, false, "Koordinat lokasi tidak valid.");
    }

    $latitude = (float) $latitudeValue;
    $longitude = (float) $longitudeValue;
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        respond(400, false, "Koordinat lokasi berada di luar rentang yang valid.");
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$transactionStarted = false;

try {
    require_once __DIR__ . "/Koneksi.php";
    mysqli_set_charset($conn, "utf8mb4");

    $profileTableName = "account_profiles";
    $profileTable = mysqli_prepare(
        $conn,
        "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($profileTable, "s", $profileTableName);
    mysqli_stmt_execute($profileTable);
    mysqli_stmt_store_result($profileTable);
    $profileTableExists = mysqli_stmt_num_rows($profileTable) === 1;
    mysqli_stmt_close($profileTable);
    if (!$profileTableExists) {
        respond(500, false, "Tabel profil belum tersedia. Import Koneksi/Setup_Database.sql melalui phpMyAdmin terlebih dahulu.");
    }

    $checkUsername = mysqli_prepare($conn, "SELECT `Username` FROM `login` WHERE `Username` = ? LIMIT 1");
    mysqli_stmt_bind_param($checkUsername, "s", $username);
    mysqli_stmt_execute($checkUsername);
    mysqli_stmt_store_result($checkUsername);
    $usernameExists = mysqli_stmt_num_rows($checkUsername) > 0;
    mysqli_stmt_close($checkUsername);

    if ($usernameExists) {
        respond(409, false, "Nama pengguna sudah digunakan.");
    }

    $checkEmail = mysqli_prepare($conn, "SELECT `Email` FROM `account_profiles` WHERE `Email` = ? LIMIT 1");
    mysqli_stmt_bind_param($checkEmail, "s", $email);
    mysqli_stmt_execute($checkEmail);
    mysqli_stmt_store_result($checkEmail);
    $emailExists = mysqli_stmt_num_rows($checkEmail) > 0;
    mysqli_stmt_close($checkEmail);

    if ($emailExists) {
        respond(409, false, "Email sudah digunakan.");
    }

    $checkNik = mysqli_prepare($conn, "SELECT `NIK` FROM `account_profiles` WHERE `NIK` = ? LIMIT 1");
    mysqli_stmt_bind_param($checkNik, "s", $nik);
    mysqli_stmt_execute($checkNik);
    mysqli_stmt_store_result($checkNik);
    $nikExists = mysqli_stmt_num_rows($checkNik) > 0;
    mysqli_stmt_close($checkNik);
    if ($nikExists) {
        respond(409, false, "NIK sudah digunakan.");
    }

    $checkNim = mysqli_prepare($conn, "SELECT `NIM` FROM `account_profiles` WHERE `NIM` = ? LIMIT 1");
    mysqli_stmt_bind_param($checkNim, "s", $username);
    mysqli_stmt_execute($checkNim);
    mysqli_stmt_store_result($checkNim);
    $nimExists = mysqli_stmt_num_rows($checkNim) > 0;
    mysqli_stmt_close($checkNim);
    if ($nimExists) {
        respond(409, false, "NIM sudah digunakan.");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        throw new RuntimeException("Password hashing failed.");
    }

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $insertLogin = mysqli_prepare($conn, "INSERT INTO `login` (`Username`, `Password`, `Role`) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insertLogin, "sss", $username, $hashedPassword, $role);
    mysqli_stmt_execute($insertLogin);
    mysqli_stmt_close($insertLogin);

    $latitudeForDatabase = $latitude === null ? null : (string) $latitude;
    $longitudeForDatabase = $longitude === null ? null : (string) $longitude;

    $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "Uploads" . DIRECTORY_SEPARATOR . "Mahasiswa";
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException("Photo directory could not be created.");
    }
    $photoExtension = $allowedPhotoTypes[$photoMime];
    $photoFilename = bin2hex(random_bytes(16)) . "." . $photoExtension;
    $photoTarget = $uploadDirectory . DIRECTORY_SEPARATOR . $photoFilename;
    if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $photoTarget)) {
        throw new RuntimeException("Photo upload could not be saved.");
    }
    $photoPath = "Uploads/Mahasiswa/" . $photoFilename;

    $insertProfile = mysqli_prepare(
        $conn,
        "INSERT INTO `account_profiles`
         (`Username`, `NIK`, `NIM`, `FullName`, `Email`, `Region`, `Country`, `Province`, `Regency`, `District`, `Village`, `Gender`, `BirthDate`, `PhotoPath`, `Latitude`, `Longitude`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $insertProfile,
        "ssssssssssssssss",
        $username,
        $nik,
        $username,
        $fullName,
        $email,
        $province,
        $country,
        $province,
        $regency,
        $district,
        $village,
        $gender,
        $birthDate,
        $photoPath,
        $latitudeForDatabase,
        $longitudeForDatabase
    );
    mysqli_stmt_execute($insertProfile);
    mysqli_stmt_close($insertProfile);

    mysqli_commit($conn);
    $transactionStarted = false;
    respond(201, true, "Akun berhasil dibuat.");
} catch (mysqli_sql_exception $error) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }

    error_log("Registration database error: " . $error->getMessage());
    if ((int) $error->getCode() === 1062) {
        respond(409, false, "Nama pengguna atau email sudah digunakan.");
    }
    respond(500, false, "Terjadi kesalahan pada server. Periksa konfigurasi database dan tabel pendaftaran.");
} catch (Throwable $error) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }

    error_log("Registration error: " . $error->getMessage());
    respond(500, false, "Terjadi kesalahan pada server.");
}
