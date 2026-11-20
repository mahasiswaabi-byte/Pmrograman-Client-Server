<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "dosen") {
    header("Location: ../Interface.html?login=1");
    exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require_once __DIR__ . "/../Koneksi/Koneksi.php";
    mysqli_set_charset($conn, "utf8mb4");
    $databaseRole = "";
    $roleStatement = mysqli_prepare($conn, "SELECT `Role` FROM `login` WHERE `Username` = ? LIMIT 1");
    mysqli_stmt_bind_param($roleStatement, "s", $_SESSION["username"]);
    mysqli_stmt_execute($roleStatement);
    mysqli_stmt_bind_result($roleStatement, $databaseRole);
    $roleFound = mysqli_stmt_fetch($roleStatement);
    mysqli_stmt_close($roleStatement);
    if (!$roleFound || $databaseRole !== "dosen") {
        header("Location: " . ($databaseRole === "admin" ? "Admin.php" : "Beranda.php"));
        exit;
    }
} catch (Throwable $error) {
    error_log("Dosen role verification error: " . $error->getMessage());
    http_response_code(500);
    exit("Peran dosen tidak dapat diverifikasi.");
}
if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$roomCsrfToken = $_SESSION["csrf_token"];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Panel Dosen | Absensi Kampus</title>
    <link rel="stylesheet" href="../Visual.css?v=20261120-room" />
</head>
<body class="home-page room-page">
    <header class="header">
        <nav class="navbar" aria-label="Navigasi dosen">
            <a class="logo" href="Dosen.php"><img src="../LogoArb.png" alt="" class="logo-image" /><span>Absensi Kampus</span></a>
            <ul class="nav-menu"><li><a href="#ruangan">Kontrol Ruangan</a></li></ul>
            <form class="nav-actions" action="../Koneksi/Logout.php" method="post">
                <span class="session-user">Dosen: <?= htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8") ?></span>
                <button class="nav-button nav-button-secondary" type="submit">Keluar</button>
            </form>
        </nav>
    </header>

    <main class="home-container room-dashboard">
        <section class="room-heading">
            <p class="home-eyebrow">PANEL DOSEN</p>
            <h1>Kontrol akses ruangan</h1>
            <p>Buka atau kunci ruangan untuk mengatur akses mahasiswa melalui sidik jari dan pengenalan wajah.</p>
        </section>
        <p id="room-status" class="room-status" role="status">Memuat daftar ruangan...</p>
        <section id="room-list" class="room-grid" aria-label="Daftar ruangan"></section>
    </main>

    <script>
        const roomCsrfToken = <?= json_encode($roomCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const roomList = document.getElementById("room-list");
        const roomStatus = document.getElementById("room-status");

        async function loadRooms() {
            try {
                const response = await fetch("../Koneksi/Room.php", { headers: { Accept: "application/json" } });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                renderRooms(result.rooms);
            } catch (error) {
                roomStatus.textContent = error.message;
            }
        }

        function renderRooms(rooms) {
            roomStatus.textContent = `${rooms.length} ruangan tersedia.`;
            roomList.replaceChildren();
            rooms.forEach((room) => {
                const locked = room.RoomStatus === "locked";
                const card = document.createElement("article");
                card.className = "room-card";
                const details = document.createElement("div");
                const label = document.createElement("p");
                label.className = "room-label";
                label.textContent = "RUANGAN";
                const title = document.createElement("h2");
                title.textContent = room.RoomName;
                const badge = document.createElement("span");
                badge.className = `room-badge ${locked ? "is-locked" : "is-open"}`;
                badge.textContent = locked ? "Terkunci" : "Terbuka";
                details.append(label, title, badge);

                const button = document.createElement("button");
                button.type = "button";
                button.className = `room-action ${locked ? "open-action" : "lock-action"}`;
                button.textContent = locked ? "Buka ruangan" : "Kunci ruangan";
                button.addEventListener("click", () => setRoomStatus(room.RoomName, locked ? "open" : "locked"));
                card.append(details, button);
                roomList.appendChild(card);
            });
        }

        async function setRoomStatus(roomName, roomStatusValue) {
            roomStatus.textContent = "Memperbarui status ruangan...";
            const form = new FormData();
            form.set("action", "set_room_status");
            form.set("room_name", roomName);
            form.set("room_status", roomStatusValue);
            form.set("csrf_token", roomCsrfToken);
            try {
                const response = await fetch("../Koneksi/Room.php", {
                    method: "POST",
                    body: form,
                    headers: { Accept: "application/json" }
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                roomStatus.textContent = result.message;
                await loadRooms();
            } catch (error) {
                roomStatus.textContent = error.message;
            }
        }

        loadRooms();
    </script>
</body>
</html>
