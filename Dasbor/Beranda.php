<?php
session_start();
if (!isset($_SESSION["username"]) || !is_string($_SESSION["username"])) {
	header("Location: ../Interface.html?login=1");
	exit;
}

if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
	$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_token"];
$directoryMessage = null;
$directoryMessageType = "success";
$directorySettingsAvailable = true;
$publicDirectoryEnabled = false;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	require_once __DIR__ . "/../Koneksi/Koneksi.php";
	mysqli_set_charset($conn, "utf8mb4");

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_directory_visibility") {
		$submittedToken = $_POST["csrf_token"] ?? "";
		$visibility = $_POST["public_directory"] ?? "";
		if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
			http_response_code(400);
			$directoryMessage = "Permintaan tidak valid. Muat ulang halaman dan coba lagi.";
			$directoryMessageType = "error";
		} elseif (!is_string($visibility) || !in_array($visibility, ["0", "1"], true)) {
			http_response_code(400);
			$directoryMessage = "Pilihan direktori tidak valid.";
			$directoryMessageType = "error";
		} else {
			$updateStatement = mysqli_prepare(
				$conn,
				"UPDATE `account_profiles` SET `PublicDirectory` = ? WHERE `Username` = ?"
			);
			$visibilityValue = (int) $visibility;
			$currentUsername = $_SESSION["username"];
			mysqli_stmt_bind_param($updateStatement, "is", $visibilityValue, $currentUsername);
			mysqli_stmt_execute($updateStatement);
			mysqli_stmt_close($updateStatement);
			header("Location: Beranda.php?directory=saved", true, 303);
			exit;
		}
	}

	$profileStatement = mysqli_prepare(
		$conn,
		"SELECT `PublicDirectory` FROM `account_profiles` WHERE `Username` = ? LIMIT 1"
	);
	mysqli_stmt_bind_param($profileStatement, "s", $_SESSION["username"]);
	mysqli_stmt_execute($profileStatement);
	mysqli_stmt_bind_result($profileStatement, $publicDirectoryValue);
	$profileFound = mysqli_stmt_fetch($profileStatement);
	mysqli_stmt_close($profileStatement);
	if (!$profileFound) {
		throw new RuntimeException("Account profile not found.");
	}
	$publicDirectoryEnabled = (int) $publicDirectoryValue === 1;
} catch (Throwable $error) {
	error_log("Directory preference error: " . $error->getMessage());
	http_response_code(500);
	$directoryMessage = "Pengaturan direktori belum tersedia. Pastikan Setup_Database.sql sudah dijalankan.";
	$directoryMessageType = "error";
	$directorySettingsAvailable = false;
}

if ($directoryMessage === null && ($_GET["directory"] ?? "") === "saved") {
	$directoryMessage = "Pilihan direktori berhasil diperbarui.";
}
?>
<!DOCTYPE html>
<html lang="id">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<meta name="theme-color" content="#223a6f" />
		<title>Beranda Anggota | Arb.Wcite</title>
		<link rel="stylesheet" href="../Visual.css?v=20261118-community" />
	</head>

	<body class="home-page">
		<header class="header">
			<nav class="navbar" aria-label="Navigasi utama">
				<a class="logo" href="#beranda" aria-label="Arb.Wcite, beranda">
					<img src="../LogoArb.png" alt="" class="logo-image" />
					<span>Arb.Wcite</span>
				</a>

				<ul class="nav-menu">
					<li><a href="#beranda" aria-current="page">Beranda</a></li>
					<li><a href="../Interface.html#pengumuman">Pengumuman</a></li>
					<li><a href="../Interface.html#agenda">Agenda</a></li>
					<li><a href="../Interface.html#anggota">Anggota</a></li>
				</ul>
				<form class="nav-actions" action="../Koneksi/Logout.php" method="post">
					<span class="session-user">Masuk sebagai <?= htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8") ?></span>
					<button class="nav-button nav-button-secondary" type="submit">Keluar / Ganti akun</button>
				</form>
			</nav>
		</header>

		<main>
			<section class="home-hero" id="beranda">
				<div class="home-container home-hero-inner">
					<div class="home-hero-copy">
						<p class="home-eyebrow">RUANG ANGGOTA ARB.WCITE</p>
						<h1>Selamat datang, <?= htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8") ?>.</h1>
						<p class="home-lead">
							Ikuti kabar komunitas, temukan kegiatan mendatang, dan pilih apakah
							nama serta kabupaten/kotamu boleh tampil di direktori publik.
						</p>
						<div class="home-actions">
							<a class="home-button" href="../Interface.html#pengumuman">Jelajahi komunitas</a>
							<a class="home-secondary-link" href="#pengaturan-direktori">Pengaturan direktori</a>
						</div>
					</div>

					<aside class="home-hero-aside" aria-label="Bagian komunitas">
						<p class="home-aside-heading">JELAJAHI</p>
						<a href="../Interface.html#pengumuman"><span>01</span> Pengumuman</a>
						<a href="../Interface.html#agenda"><span>02</span> Agenda</a>
						<a href="../Interface.html#anggota"><span>03</span> Direktori anggota</a>
					</aside>
				</div>
			</section>

			<section class="community-content-section" id="pengaturan-direktori">
				<div class="home-container">
					<div class="community-section-heading">
						<div>
							<p class="home-eyebrow">PILIHAN PRIVASI</p>
							<h2>Pengaturan direktori publik</h2>
						</div>
						<span class="community-section-number">AKUN</span>
					</div>
					<p class="community-privacy-note">Jika ditampilkan, hanya nama lengkap dan kabupaten/kota yang dapat dilihat publik. Email, username, alamat terperinci, serta koordinat GPS tidak ditampilkan. Kamu dapat mengubah pilihan ini kapan saja.</p>
					<?php if ($directoryMessage !== null): ?>
						<p class="community-admin-message <?= $directoryMessageType === "error" ? "is-error" : "" ?>" role="status"><?= htmlspecialchars($directoryMessage, ENT_QUOTES, "UTF-8") ?></p>
					<?php endif; ?>
					<form class="directory-preference-form" method="post" action="Beranda.php#pengaturan-direktori">
						<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>" />
						<input type="hidden" name="action" value="update_directory_visibility" />
						<label for="public-directory">Tampilkan profil di direktori publik</label>
						<select id="public-directory" name="public_directory" <?= !$directorySettingsAvailable ? "disabled" : "" ?>>
							<option value="0" <?= !$publicDirectoryEnabled ? "selected" : "" ?>>Tidak, sembunyikan profil saya</option>
							<option value="1" <?= $publicDirectoryEnabled ? "selected" : "" ?>>Ya, tampilkan nama dan kabupaten/kota</option>
						</select>
						<button class="home-button" type="submit" <?= !$directorySettingsAvailable ? "disabled" : "" ?>>Simpan pilihan</button>
					</form>
				</div>
			</section>
		</main>

		<footer class="home-footer">
			<div class="home-container">
				<span>Arb.Wcite</span>
				<span>Komunitas lokal, terhubung dalam satu ruang.</span>
			</div>
		</footer>
	</body>
</html>
