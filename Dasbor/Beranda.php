<?php
session_start();
if (!isset($_SESSION["username"]) || !is_string($_SESSION["username"])) {
	header("Location: ../Interface.html?login=1");
	exit;
}
if (($_SESSION["role"] ?? "") === "dosen") {
	header("Location: Dosen.php");
	exit;
}
if (($_SESSION["role"] ?? "") === "admin") {
	header("Location: Admin.php");
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
	$normalizedDatabaseRole = $databaseRole === "dosen"
		? "dosen"
		: ($databaseRole === "admin" ? "admin" : "mahasiswa");
	if (!$roleFound) {
		header("Location: ../Interface.html?login=1");
		exit;
	}
	if ($normalizedDatabaseRole === "dosen") {
		header("Location: Dosen.php");
		exit;
	}
	if ($normalizedDatabaseRole === "admin") {
		header("Location: Admin.php");
		exit;
	}
	$_SESSION["role"] = "mahasiswa";
} catch (Throwable $error) {
	error_log("Student role verification error: " . $error->getMessage());
	http_response_code(500);
	exit("Peran mahasiswa tidak dapat diverifikasi.");
}

if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
	$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_token"];
?>
<!DOCTYPE html>
<html lang="id">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<meta name="theme-color" content="#223a6f" />
		<title>Dashboard Mahasiswa | Absensi Kampus</title>
		<link rel="stylesheet" href="../Visual.css?v=20261120-absensi" />
	</head>

	<body class="home-page">
		<header class="header">
			<nav class="navbar" aria-label="Navigasi utama">
				<a class="logo" href="#beranda" aria-label="Beranda Mahasiswa">
					<img src="../LogoArb.png" alt="" class="logo-image" />
					<span>Absensi Kampus</span>
				</a>

				<ul class="nav-menu">
					<li><a href="#beranda" aria-current="page">Beranda Mahasiswa</a></li>
					<li><a href="#verifikasi-ruangan">Verifikasi Kehadiran</a></li>
				</ul>
				<form class="nav-actions" action="../Koneksi/Logout.php" method="post">
					<span class="session-user">Mahasiswa: <?= htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8") ?></span>
					<button class="nav-button nav-button-secondary" type="submit">Keluar / Ganti akun</button>
				</form>
			</nav>
		</header>

		<main>
			<section class="home-hero" id="beranda">
				<div class="home-container home-hero-inner">
					<div class="home-hero-copy">
						<p class="home-eyebrow">DASHBOARD MAHASISWA</p>
						<h1>Selamat datang, <?= htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8") ?>.</h1>
						<p class="home-lead">
							Pilih ruangan, lakukan verifikasi sidik jari dan pengenalan wajah,
							lalu catat kehadiran Anda jika ruangan terbuka.
						</p>
						<div class="home-actions">
							<a class="home-button" href="#verifikasi-ruangan">Mulai absensi</a>
						</div>
					</div>

					<aside class="home-hero-aside" aria-label="Tahapan absensi">
						<p class="home-aside-heading">ABSENSI</p>
							<a href="#verifikasi-ruangan"><span>01</span> Pilih ruangan</a>
							<a href="#verifikasi-ruangan"><span>02</span> Sidik jari</a>
							<a href="#verifikasi-ruangan"><span>03</span> Pengenalan wajah</a>
					</aside>
				</div>
			</section>

			<section class="community-content-section room-verification-section" id="verifikasi-ruangan">
				<div class="home-container">
					<div class="community-section-heading">
						<div>
							<p class="home-eyebrow">PRESENSI BIOMETRIK</p>
							<h2>Verifikasi masuk ruangan</h2>
						</div>
						<span class="community-section-number">AKSES</span>
					</div>
					<p id="verification-status" class="community-data-status" role="status">Memuat status ruangan...</p>
					<div class="verification-layout">
						<div class="verification-controls">
							<label for="room-select">Pilih ruangan</label>
							<select id="room-select">
								<option value="">Memuat ruangan...</option>
							</select>
							<button type="button" id="fingerprint-button" class="room-action lock-action" disabled>1. Verifikasi sidik jari</button>
							<button type="button" id="face-button" class="room-action open-action" disabled>2. Aktifkan &amp; verifikasi wajah</button>
							<button type="button" id="enter-room-button" class="home-button" disabled>Verifikasi dan masuk</button>
						</div>
						<div class="face-preview">
							<video id="face-video" autoplay muted playsinline></video>
							<span id="camera-status">Kamera belum aktif</span>
						</div>
					</div>
					<p class="community-privacy-note">Mode presentasi ini mensimulasikan sidik jari dan menggunakan kamera perangkat untuk demonstrasi pengenalan wajah. Untuk sistem produksi, gunakan sensor sidik jari perangkat/SDK liveness dan enkripsi template biometrik.</p>
				</div>
			</section>

		</main>

		<footer class="home-footer">
			<div class="home-container">
				<span>Sistem Absensi Kampus</span>
				<span>Fingerprint dan pengenalan wajah.</span>
			</div>
		</footer>
		<script>
			const roomCsrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
			const roomSelect = document.getElementById("room-select");
			const verificationStatus = document.getElementById("verification-status");
			const fingerprintButton = document.getElementById("fingerprint-button");
			const faceButton = document.getElementById("face-button");
			const enterRoomButton = document.getElementById("enter-room-button");
			const faceVideo = document.getElementById("face-video");
			const cameraStatus = document.getElementById("camera-status");
			let fingerprintVerified = false;
			let faceVerified = false;
			let cameraStream = null;

			function stopCamera() {
				if (cameraStream) {
					cameraStream.getTracks().forEach((track) => track.stop());
					cameraStream = null;
				}
				faceVideo.srcObject = null;
				cameraStatus.textContent = "Kamera belum aktif";
			}

			function resetVerification(message) {
				fingerprintVerified = false;
				faceVerified = false;
				faceButton.disabled = true;
				enterRoomButton.disabled = true;
				stopCamera();
				verificationStatus.textContent = message;
			}

			async function loadRoomsForStudent() {
				try {
					const response = await fetch("../Koneksi/Room.php", { headers: { Accept: "application/json" } });
					const result = await response.json();
					if (!response.ok || !result.success) throw new Error(result.message);
					roomSelect.replaceChildren(new Option("Pilih ruangan", ""));
					result.rooms.forEach((room) => {
						const locked = room.RoomStatus === "locked";
						roomSelect.add(new Option(`${room.RoomName}${locked ? " - TERKUNCI" : " - TERBUKA"}`, room.RoomName));
					});
					fingerprintButton.disabled = false;
					verificationStatus.textContent = "Pilih ruangan, lalu lakukan kedua tahap verifikasi.";
				} catch (error) {
					verificationStatus.textContent = error.message;
				}
			}

			roomSelect.addEventListener("change", () => {
				resetVerification(roomSelect.value
					? "Tahap 1: verifikasi sidik jari pada sensor/simulasi."
					: "Pilih ruangan terlebih dahulu.");
			});

			fingerprintButton.addEventListener("click", () => {
				if (!roomSelect.value) {
					verificationStatus.textContent = "Pilih ruangan terlebih dahulu.";
					return;
				}
				fingerprintVerified = true;
				faceButton.disabled = false;
				verificationStatus.textContent = "Sidik jari berhasil diverifikasi (mode simulasi). Lanjutkan ke wajah.";
			});

			faceButton.addEventListener("click", async () => {
				if (!fingerprintVerified) {
					verificationStatus.textContent = "Verifikasi sidik jari terlebih dahulu.";
					return;
				}
				if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
					verificationStatus.textContent = "Browser tidak mendukung akses kamera.";
					return;
				}
				try {
					cameraStream = await navigator.mediaDevices.getUserMedia({
						video: { facingMode: "user" },
						audio: false
					});
					faceVideo.srcObject = cameraStream;
					cameraStatus.textContent = "Wajah terdeteksi (mode demonstrasi)";
					faceVerified = true;
					enterRoomButton.disabled = false;
					verificationStatus.textContent = "Sidik jari dan wajah berhasil diverifikasi.";
				} catch (error) {
					cameraStatus.textContent = "Kamera tidak dapat diaktifkan";
					verificationStatus.textContent = "Izinkan akses kamera untuk tahap pengenalan wajah.";
				}
			});

			enterRoomButton.addEventListener("click", async () => {
				if (!roomSelect.value || !fingerprintVerified || !faceVerified) {
					verificationStatus.textContent = "Lengkapi kedua tahap verifikasi terlebih dahulu.";
					return;
				}
				enterRoomButton.disabled = true;
				verificationStatus.textContent = "Memeriksa status ruangan...";
				const form = new FormData();
				form.set("action", "verify_access");
				form.set("room_name", roomSelect.value);
				form.set("fingerprint_verified", "1");
				form.set("face_verified", "1");
				form.set("csrf_token", roomCsrfToken);
				try {
					const response = await fetch("../Koneksi/Room.php", {
						method: "POST",
						body: form,
						headers: { Accept: "application/json" }
					});
					const result = await response.json();
					verificationStatus.textContent = result.message;
					if (response.ok && result.success) {
						await loadRoomsForStudent();
						resetVerification("Verifikasi berhasil. Pilih ruangan jika ingin melakukan presensi lagi.");
					}
				} catch (error) {
					verificationStatus.textContent = "Verifikasi masuk tidak dapat diproses.";
				} finally {
					enterRoomButton.disabled = false;
				}
			});

			window.addEventListener("beforeunload", stopCamera);

			loadRoomsForStudent();
		</script>
	</body>
</html>
