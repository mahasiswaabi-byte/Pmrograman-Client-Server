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
    $accountFound = mysqli_stmt_fetch($roleStatement);
    mysqli_stmt_close($roleStatement);

    if (!$accountFound || $role !== "admin") {
        header("Location: Beranda.php");
        exit;
    }

    $registeredUsersFilter = "COALESCE(login.Role, 'user') <> 'admin'";
    $totalResult = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM account_profiles AS profiles
         LEFT JOIN login ON login.Username = profiles.Username
         WHERE $registeredUsersFilter"
    );
    $totalUsers = (int) mysqli_fetch_assoc($totalResult)["total"];

    $genderResult = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(profiles.Gender), ''), 'Belum diisi') AS label, COUNT(*) AS total
         FROM account_profiles AS profiles
         LEFT JOIN login ON login.Username = profiles.Username
         WHERE $registeredUsersFilter
         GROUP BY label
         ORDER BY total DESC, label ASC"
    );
    $genderCounts = [];
    while ($row = mysqli_fetch_assoc($genderResult)) {
        $genderCounts[$row["label"]] = (int) $row["total"];
    }

    $countryResult = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(profiles.Country), ''), 'Belum diisi') AS label, COUNT(*) AS total
         FROM account_profiles AS profiles
         LEFT JOIN login ON login.Username = profiles.Username
         WHERE $registeredUsersFilter
         GROUP BY label
         ORDER BY total DESC, label ASC
         LIMIT 5"
    );
    $countries = mysqli_fetch_all($countryResult, MYSQLI_ASSOC);

    $provinceResult = mysqli_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(profiles.Province), ''), 'Belum diisi') AS label, COUNT(*) AS total
         FROM account_profiles AS profiles
         LEFT JOIN login ON login.Username = profiles.Username
         WHERE $registeredUsersFilter
         GROUP BY label
         ORDER BY total DESC, label ASC
         LIMIT 5"
    );
    $provinces = mysqli_fetch_all($provinceResult, MYSQLI_ASSOC);
} catch (Throwable $error) {
    error_log("Admin dashboard error: " . $error->getMessage());
    http_response_code(500);
    exit("Dashboard admin tidak dapat dimuat. Periksa koneksi database.");
}

function percentageOfUsers($count, $total)
{
    return $total > 0 ? round(($count / $total) * 100, 1) : 0;
}

function displayLabel($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$genderCards = [
    "Laki-laki" => $genderCounts["Laki-laki"] ?? 0,
    "Perempuan" => $genderCounts["Perempuan"] ?? 0,
    "Lainnya" => $genderCounts["Lainnya"] ?? 0,
];
$unclassifiedGenderCount = $genderCounts["Belum diisi"] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="theme-color" content="#223a6f" />
        <title>Dashboard Admin | Arb.Wcite</title>
        <link rel="stylesheet" href="../Visual.css" />
    </head>
    <body class="home-page">
        <header class="header">
            <nav class="navbar" aria-label="Navigasi utama">
                <a class="logo" href="Admin.php" aria-label="Arb.Wcite, dashboard admin">
                    <img src="../LogoArb.png" alt="" class="logo-image" />
                    <span>Arb.Wcite</span>
                </a>
                <ul class="nav-menu">
                    <li><a href="#ringkasan" aria-current="page">Ringkasan</a></li>
                    <li><a href="#gender">Jenis kelamin</a></li>
                    <li><a href="#wilayah">Wilayah</a></li>
                </ul>
                <form class="nav-actions" action="../Koneksi/Logout.php" method="post">
                    <span class="session-user">Admin: <?= displayLabel($_SESSION["username"]) ?></span>
                    <button class="nav-button nav-button-secondary" type="submit">Keluar</button>
                </form>
            </nav>
        </header>

        <main class="admin-dashboard home-container">
            <section id="ringkasan" class="admin-heading">
                <p class="home-eyebrow">PANEL ADMIN ARB.WCITE</p>
                <h1>Ringkasan pendaftaran</h1>
                <p>Statistik akun pengguna terdaftar. Akun dengan role admin tidak dihitung.</p>
            </section>

            <section class="admin-stat-grid" aria-label="Jumlah akun menurut jenis kelamin">
                <article class="admin-stat-card admin-stat-total">
                    <p>Total pengguna terdaftar</p>
                    <strong><?= $totalUsers ?></strong>
                    <span>Akun pengguna</span>
                </article>
                <?php foreach ($genderCards as $label => $count): ?>
                    <article class="admin-stat-card">
                        <p><?= displayLabel($label) ?></p>
                        <strong><?= $count ?></strong>
                        <span><?= percentageOfUsers($count, $totalUsers) ?>% dari pengguna</span>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="admin-panel" id="gender">
                <div class="admin-panel-heading">
                    <div>
                        <p class="home-eyebrow">KOMPOSISI PENGGUNA</p>
                        <h2>Persentase jenis kelamin</h2>
                    </div>
                    <span><?= $totalUsers ?> pengguna</span>
                </div>
                <?php foreach ($genderCounts as $label => $count): ?>
                    <div class="admin-breakdown-row">
                        <div class="admin-breakdown-label">
                            <span><?= displayLabel($label) ?></span>
                            <span><?= percentageOfUsers($count, $totalUsers) ?>% <span class="admin-muted">(<?= $count ?>)</span></span>
                        </div>
                        <progress max="100" value="<?= percentageOfUsers($count, $totalUsers) ?>" aria-label="<?= displayLabel($label) ?>: <?= percentageOfUsers($count, $totalUsers) ?> persen"></progress>
                    </div>
                <?php endforeach; ?>
                <?php if ($unclassifiedGenderCount === 0 && $totalUsers === 0): ?>
                    <p class="admin-empty">Belum ada data pendaftaran untuk ditampilkan.</p>
                <?php endif; ?>
            </section>

            <section class="admin-location-grid" id="wilayah">
                <article class="admin-panel">
                    <div class="admin-panel-heading">
                        <div>
                            <p class="home-eyebrow">SEBARAN PENGGUNA</p>
                            <h2>Negara</h2>
                        </div>
                    </div>
                    <?php if (count($countries) === 0): ?>
                        <p class="admin-empty">Belum ada data negara.</p>
                    <?php else: ?>
                        <ul class="admin-ranking-list">
                            <?php foreach ($countries as $country): ?>
                                <li><span><?= displayLabel($country["label"]) ?></span><strong><?= (int) $country["total"] ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <article class="admin-panel">
                    <div class="admin-panel-heading">
                        <div>
                            <p class="home-eyebrow">SEBARAN PENGGUNA</p>
                            <h2>Provinsi/daerah</h2>
                        </div>
                    </div>
                    <?php if (count($provinces) === 0): ?>
                        <p class="admin-empty">Belum ada data wilayah.</p>
                    <?php else: ?>
                        <ul class="admin-ranking-list">
                            <?php foreach ($provinces as $province): ?>
                                <li><span><?= displayLabel($province["label"]) ?></span><strong><?= (int) $province["total"] ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>
        </main>
    </body>
</html>
