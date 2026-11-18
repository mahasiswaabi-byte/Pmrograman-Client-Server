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

    if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION["csrf_token"];
    $adminUsername = $_SESSION["username"];

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $submittedToken = $_POST["csrf_token"] ?? "";
        if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
            http_response_code(400);
            $communityMessage = "Permintaan tidak valid. Muat ulang halaman dan coba lagi.";
            $communityMessageType = "error";
        } else {
            $action = $_POST["action"] ?? "";
            try {
                if ($action === "create_announcement") {
                    $titleValue = $_POST["title"] ?? "";
                    $bodyValue = $_POST["body"] ?? "";
                    $title = is_string($titleValue) ? trim($titleValue) : "";
                    $body = is_string($bodyValue) ? trim($bodyValue) : "";
                    if ($title === "" || mb_strlen($title, "UTF-8") > 150 || $body === "" || mb_strlen($body, "UTF-8") > 5000) {
                        throw new InvalidArgumentException("Judul (maksimal 150 karakter) dan isi pengumuman (maksimal 5.000 karakter) wajib diisi.");
                    }

                    $statement = mysqli_prepare(
                        $conn,
                        "INSERT INTO `community_announcements` (`Title`, `Body`, `CreatedBy`) VALUES (?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($statement, "sss", $title, $body, $adminUsername);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);
                } elseif ($action === "create_event") {
                    $titleValue = $_POST["title"] ?? "";
                    $descriptionValue = $_POST["description"] ?? "";
                    $locationValue = $_POST["location"] ?? "";
                    $startsAtValue = $_POST["starts_at"] ?? "";
                    $title = is_string($titleValue) ? trim($titleValue) : "";
                    $description = is_string($descriptionValue) ? trim($descriptionValue) : "";
                    $location = is_string($locationValue) ? trim($locationValue) : "";
                    $startsAt = is_string($startsAtValue)
                        ? DateTime::createFromFormat("!Y-m-d\\TH:i", $startsAtValue)
                        : false;
                    if (
                        $title === "" || mb_strlen($title, "UTF-8") > 150 ||
                        $description === "" || mb_strlen($description, "UTF-8") > 5000 ||
                        $location === "" || mb_strlen($location, "UTF-8") > 150 ||
                        !$startsAt || $startsAt->format("Y-m-d\\TH:i") !== $startsAtValue ||
                        $startsAt <= new DateTime()
                    ) {
                        throw new InvalidArgumentException("Lengkapi judul, deskripsi, lokasi, dan waktu agenda mendatang dengan batas panjang yang tersedia.");
                    }

                    $databaseStart = $startsAt->format("Y-m-d H:i:s");
                    $statement = mysqli_prepare(
                        $conn,
                        "INSERT INTO `community_events` (`Title`, `Description`, `Location`, `StartsAt`, `CreatedBy`) VALUES (?, ?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($statement, "sssss", $title, $description, $location, $databaseStart, $adminUsername);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);
                } elseif ($action === "delete_announcement" || $action === "delete_event") {
                    $itemId = filter_var($_POST["item_id"] ?? null, FILTER_VALIDATE_INT);
                    if ($itemId === false || $itemId === null || $itemId < 1) {
                        throw new InvalidArgumentException("Item yang dipilih tidak valid.");
                    }

                    $tableName = $action === "delete_announcement" ? "community_announcements" : "community_events";
                    $statement = mysqli_prepare($conn, "DELETE FROM `$tableName` WHERE `Id` = ?");
                    mysqli_stmt_bind_param($statement, "i", $itemId);
                    mysqli_stmt_execute($statement);
                    $itemDeleted = mysqli_stmt_affected_rows($statement) === 1;
                    mysqli_stmt_close($statement);
                    if (!$itemDeleted) {
                        throw new InvalidArgumentException("Item tersebut sudah tidak tersedia.");
                    }
                } else {
                    throw new InvalidArgumentException("Aksi pengelolaan tidak dikenali.");
                }

                header("Location: Admin.php?content=saved", true, 303);
                exit;
            } catch (InvalidArgumentException $error) {
                http_response_code(400);
                $communityMessage = $error->getMessage();
                $communityMessageType = "error";
            } catch (Throwable $error) {
                error_log("Admin community content error: " . $error->getMessage());
                http_response_code(500);
                $communityMessage = "Konten tidak dapat disimpan. Pastikan tabel komunitas sudah dibuat dari Setup_Database.sql.";
                $communityMessageType = "error";
            }
        }
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

    $announcementsResult = mysqli_query(
        $conn,
        "SELECT `Id`, `Title`, `Body`, `PublishedAt`
         FROM `community_announcements`
         ORDER BY `PublishedAt` DESC, `Id` DESC
         LIMIT 10"
    );
    $managedAnnouncements = mysqli_fetch_all($announcementsResult, MYSQLI_ASSOC);

    $eventsResult = mysqli_query(
        $conn,
        "SELECT `Id`, `Title`, `Description`, `Location`, `StartsAt`
         FROM `community_events`
         ORDER BY `StartsAt` ASC
         LIMIT 10"
    );
    $managedEvents = mysqli_fetch_all($eventsResult, MYSQLI_ASSOC);
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

$communityMessage = $communityMessage ?? null;
$communityMessageType = $communityMessageType ?? "success";
if ($communityMessage === null && ($_GET["content"] ?? "") === "saved") {
    $communityMessage = "Perubahan konten komunitas berhasil disimpan.";
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
        <link rel="stylesheet" href="../Visual.css?v=20261118-community" />
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
                    <li><a href="#konten-komunitas">Konten komunitas</a></li>
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

            <section class="admin-panel community-admin-panel" id="konten-komunitas">
                <div class="admin-panel-heading">
                    <div>
                        <p class="home-eyebrow">KELOLA INFORMASI</p>
                        <h2>Konten komunitas</h2>
                    </div>
                    <a class="home-inline-link" href="../Interface.html">Lihat situs komunitas <span aria-hidden="true">&rarr;</span></a>
                </div>
                <?php if ($communityMessage !== null): ?>
                    <p class="community-admin-message <?= $communityMessageType === "error" ? "is-error" : "" ?>" role="status"><?= displayLabel($communityMessage) ?></p>
                <?php endif; ?>

                <div class="community-admin-forms">
                    <form class="community-admin-form" method="post" action="Admin.php#konten-komunitas">
                        <h3>Tambah pengumuman</h3>
                        <input type="hidden" name="csrf_token" value="<?= displayLabel($csrfToken) ?>" />
                        <input type="hidden" name="action" value="create_announcement" />
                        <label for="announcement-title">Judul</label>
                        <input id="announcement-title" name="title" type="text" maxlength="150" required />
                        <label for="announcement-body">Isi pengumuman</label>
                        <textarea id="announcement-body" name="body" rows="5" maxlength="5000" required></textarea>
                        <button class="home-button" type="submit">Terbitkan pengumuman</button>
                    </form>

                    <form class="community-admin-form" method="post" action="Admin.php#konten-komunitas">
                        <h3>Tambah agenda</h3>
                        <input type="hidden" name="csrf_token" value="<?= displayLabel($csrfToken) ?>" />
                        <input type="hidden" name="action" value="create_event" />
                        <label for="event-title">Nama kegiatan</label>
                        <input id="event-title" name="title" type="text" maxlength="150" required />
                        <label for="event-description">Deskripsi</label>
                        <textarea id="event-description" name="description" rows="3" maxlength="5000" required></textarea>
                        <label for="event-location">Lokasi</label>
                        <input id="event-location" name="location" type="text" maxlength="150" required />
                        <label for="event-starts-at">Tanggal dan waktu</label>
                        <input id="event-starts-at" name="starts_at" type="datetime-local" required />
                        <button class="home-button" type="submit">Terbitkan agenda</button>
                    </form>
                </div>

                <div class="community-admin-lists">
                    <section>
                        <h3>Pengumuman yang diterbitkan</h3>
                        <?php if (count($managedAnnouncements) === 0): ?>
                            <p class="admin-empty">Belum ada pengumuman.</p>
                        <?php else: ?>
                            <ul class="community-admin-list">
                                <?php foreach ($managedAnnouncements as $announcement): ?>
                                    <li>
                                        <span><strong><?= displayLabel($announcement["Title"]) ?></strong><small><?= displayLabel($announcement["PublishedAt"]) ?></small></span>
                                        <form method="post" action="Admin.php#konten-komunitas" onsubmit="return confirm('Hapus pengumuman ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= displayLabel($csrfToken) ?>" />
                                            <input type="hidden" name="action" value="delete_announcement" />
                                            <input type="hidden" name="item_id" value="<?= (int) $announcement["Id"] ?>" />
                                            <button class="community-delete-button" type="submit">Hapus</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section>
                        <h3>Agenda yang diterbitkan</h3>
                        <?php if (count($managedEvents) === 0): ?>
                            <p class="admin-empty">Belum ada agenda.</p>
                        <?php else: ?>
                            <ul class="community-admin-list">
                                <?php foreach ($managedEvents as $event): ?>
                                    <li>
                                        <span><strong><?= displayLabel($event["Title"]) ?></strong><small><?= displayLabel($event["StartsAt"]) ?> · <?= displayLabel($event["Location"]) ?></small></span>
                                        <form method="post" action="Admin.php#konten-komunitas" onsubmit="return confirm('Hapus agenda ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= displayLabel($csrfToken) ?>" />
                                            <input type="hidden" name="action" value="delete_event" />
                                            <input type="hidden" name="item_id" value="<?= (int) $event["Id"] ?>" />
                                            <button class="community-delete-button" type="submit">Hapus</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>
            </section>

            <section class="admin-panel" id="gender">
                <div class="admin-panel-heading">
                    <div>
                        <p class="home-eyebrow">KOMPOSISI PENGGUNA</p>
                        <h2>Persentase jenis kelamin</h2>
                    </div>
                    <span><?= $totalUsers ?> pengguna</span>
                </div>
                <div class="admin-gender-chart" role="img" aria-label="Diagram batang persentase jenis kelamin">
                    <?php foreach ($genderCards as $label => $count): ?>
                        <?php
                        $percentage = percentageOfUsers($count, $totalUsers);
                        $genderClass = match ($label) {
                            "Laki-laki" => "admin-gender-male",
                            "Perempuan" => "admin-gender-female",
                            "Lainnya" => "admin-gender-other",
                            default => "admin-gender-other",
                        };
                        ?>
                        <div class="admin-gender-column">
                            <div class="admin-gender-value"><?= $percentage ?>% <span class="admin-muted">(<?= $count ?>)</span></div>
                            <div class="admin-gender-track">
                                <div
                                    class="admin-gender-bar <?= $genderClass ?>"
                                    style="height: <?= $percentage ?>%"
                                    title="<?= displayLabel($label) ?>: <?= $percentage ?> persen"
                                ></div>
                            </div>
                            <div class="admin-gender-label"><?= displayLabel($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
