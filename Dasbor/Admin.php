<?php
session_start();
if (!isset($_SESSION["username"]) || !is_string($_SESSION["username"])) {
    header("Location: ../Interface.html?login=1");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . "/../Koneksi/Koneksi.php";
    mysqli_set_charset($conn, "utf8mb4");

    $roleStatement = mysqli_prepare($conn, "SELECT `Role` FROM `login` WHERE `Username` = ? LIMIT 1");
    mysqli_stmt_bind_param($roleStatement, "s", $_SESSION["username"]);
    mysqli_stmt_execute($roleStatement);
    mysqli_stmt_bind_result($roleStatement, $role);
    $roleFound = mysqli_stmt_fetch($roleStatement);
    mysqli_stmt_close($roleStatement);
    if (!$roleFound || $role !== "admin") {
        header("Location: " . ($role === "dosen" ? "Dosen.php" : "Beranda.php"));
        exit;
    }

    $totalUsersResult = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM account_profiles AS profiles
         INNER JOIN login ON login.Username = profiles.Username
         WHERE login.Role IN ('mahasiswa', 'dosen')"
    );
    $totalUsers = (int) mysqli_fetch_assoc($totalUsersResult)["total"];

    $genderResult = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(Gender), ''), 'Belum diisi') AS label, COUNT(*) AS total
         FROM account_profiles GROUP BY label ORDER BY total DESC, label ASC"
    );
    $genderCounts = [];
    while ($row = mysqli_fetch_assoc($genderResult)) {
        $genderCounts[$row["label"]] = (int) $row["total"];
    }

    $attendanceResult = mysqli_query(
        $conn,
        "SELECT attendance.Username, attendance.RoomName, attendance.FingerprintVerified,
                attendance.FaceVerified, attendance.VerifiedAt
         FROM class_attendance AS attendance
         ORDER BY attendance.VerifiedAt DESC
         LIMIT 100"
    );
    $attendanceRows = mysqli_fetch_all($attendanceResult, MYSQLI_ASSOC);

    $roomResult = mysqli_query(
        $conn,
        "SELECT RoomName, RoomStatus FROM class_rooms ORDER BY Id ASC"
    );
    $rooms = mysqli_fetch_all($roomResult, MYSQLI_ASSOC);
} catch (Throwable $error) {
    error_log("Admin attendance dashboard error: " . $error->getMessage());
    http_response_code(500);
    exit("Dashboard admin tidak dapat dimuat.");
}

function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Operator | Absensi Kampus</title>
    <link rel="stylesheet" href="../Visual.css?v=20261120-absensi" />
</head>
<body class="home-page">
<header class="header">
    <nav class="navbar" aria-label="Navigasi admin">
        <a class="logo" href="Admin.php">
            <img src="../LogoArb.png" alt="" class="logo-image" />
            <span>Absensi Kampus</span>
        </a>
        <ul class="nav-menu">
            <li><a href="#ringkasan">Ringkasan</a></li>
            <li><a href="#ruangan">Ruangan</a></li>
            <li><a href="#absensi">Absensi</a></li>
        </ul>
        <form class="nav-actions" action="../Koneksi/Logout.php" method="post">
            <span class="session-user">Operator: <?= escapeHtml($_SESSION["username"]) ?></span>
            <button class="nav-button nav-button-secondary" type="submit">Keluar</button>
        </form>
    </nav>
</header>

<main class="admin-dashboard home-container">
    <section id="ringkasan" class="admin-heading">
        <p class="home-eyebrow">PANEL OPERATOR</p>
        <h1>Ringkasan sistem absensi</h1>
        <p>Data akun Mahasiswa dan Dosen serta riwayat absensi biometrik.</p>
    </section>

    <section class="admin-stat-grid" aria-label="Ringkasan akun">
        <article class="admin-stat-card admin-stat-total">
            <p>Total akun terdaftar</p>
            <strong><?= $totalUsers ?></strong>
            <span>Mahasiswa dan dosen</span>
        </article>
        <?php foreach (["Laki-laki", "Perempuan", "Lainnya"] as $gender): ?>
            <article class="admin-stat-card">
                <p><?= escapeHtml($gender) ?></p>
                <strong><?= $genderCounts[$gender] ?? 0 ?></strong>
                <span>Akun</span>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-panel" id="ruangan">
        <div class="admin-panel-heading">
            <div>
                <p class="home-eyebrow">STATUS AKSES</p>
                <h2>Ruangan</h2>
            </div>
        </div>
        <ul class="admin-ranking-list">
            <?php foreach ($rooms as $room): ?>
                <li>
                    <span><?= escapeHtml($room["RoomName"]) ?></span>
                    <strong><?= $room["RoomStatus"] === "open" ? "Terbuka" : "Terkunci" ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="admin-panel" id="absensi">
        <div class="admin-panel-heading">
            <div>
                <p class="home-eyebrow">RIWAYAT KEHADIRAN</p>
                <h2>Absensi Mahasiswa</h2>
            </div>
            <span><?= count($attendanceRows) ?> data</span>
        </div>
        <?php if (count($attendanceRows) === 0): ?>
            <p class="admin-empty">Belum ada absensi yang tercatat.</p>
        <?php else: ?>
            <div class="attendance-table-wrap">
                <table class="attendance-table">
                    <thead>
                        <tr><th>Mahasiswa</th><th>Ruangan</th><th>Sidik Jari</th><th>Wajah</th><th>Waktu</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceRows as $row): ?>
                            <tr>
                                <td><?= escapeHtml($row["Username"]) ?></td>
                                <td><?= escapeHtml($row["RoomName"]) ?></td>
                                <td><?= $row["FingerprintVerified"] ? "Berhasil" : "Belum" ?></td>
                                <td><?= $row["FaceVerified"] ? "Berhasil" : "Belum" ?></td>
                                <td><?= escapeHtml($row["VerifiedAt"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
