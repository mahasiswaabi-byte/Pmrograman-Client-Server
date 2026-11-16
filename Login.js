const loginButton = document.getElementById("login-button");
const loginModal = document.getElementById("login-modal");
const closeLoginButton = document.getElementById("close-login");
const loginForm = document.getElementById("login-form");
const passwordInput = document.getElementById("password");
const passwordToggle = document.getElementById("password-toggle");
const loginMessage = document.getElementById("login-message");
const registerButton = document.getElementById("register-button");
const dashboardButton = document.getElementById("dashboard-button");
const logoutButton = document.getElementById("logout-button");
const accountMessage = document.getElementById("account-message");
const registerModal = document.getElementById("register-modal");
const closeRegisterButton = document.getElementById("close-register");
const openRegisterButton = document.getElementById("open-register");
const openLoginButton = document.getElementById("open-login");
const registerForm = document.getElementById("register-form");
const registerMessage = document.getElementById("register-message");
const locationButton = document.getElementById("get-location");
const clearLocationButton = document.getElementById("clear-location");
const locationStatus = document.getElementById("location-status");
const latitudeInput = document.getElementById("register-latitude");
const longitudeInput = document.getElementById("register-longitude");

function openLoginModal(event) {
	if (event) {
		event.preventDefault();
	}
	registerModal.hidden = true;
	loginModal.hidden = false;
	document.body.style.overflow = "hidden";
	document.getElementById("username").focus();
}

function openRegisterModal() {
	loginModal.hidden = true;
	registerModal.hidden = false;
	document.body.style.overflow = "hidden";
	registerMessage.textContent = "";
	document.getElementById("register-name").focus();
}

function closeLoginModal() {
	loginModal.hidden = true;
	if (registerModal.hidden) {
		document.body.style.overflow = "";
	}
}

function closeRegisterModal() {
	registerModal.hidden = true;
	if (loginModal.hidden) {
		document.body.style.overflow = "";
	}
}

loginButton.addEventListener("click", openLoginModal);
closeLoginButton.addEventListener("click", closeLoginModal);
registerButton.addEventListener("click", openRegisterModal);
logoutButton.addEventListener("click", async () => {
	logoutButton.disabled = true;
	accountMessage.hidden = true;

	try {
		const response = await fetch("Koneksi/Logout.php", {
			method: "POST",
			headers: { Accept: "application/json" },
		});
		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error(result.message || "Logout gagal.");
		}
		window.location.href = "Interface.html?login=1";
	} catch {
		accountMessage.textContent = "Logout gagal. Periksa koneksi server lalu coba lagi.";
		accountMessage.hidden = false;
		logoutButton.disabled = false;
	}
});
openRegisterButton.addEventListener("click", openRegisterModal);
openLoginButton.addEventListener("click", () => {
	closeRegisterModal();
	loginModal.hidden = false;
	document.body.style.overflow = "hidden";
	document.getElementById("username").focus();
});
closeRegisterButton.addEventListener("click", closeRegisterModal);

loginModal.addEventListener("click", (event) => {
	if (event.target === loginModal) {
		closeLoginModal();
	}
});

registerModal.addEventListener("click", (event) => {
	if (event.target === registerModal) {
		closeRegisterModal();
	}
});

document.addEventListener("keydown", (event) => {
	if (event.key === "Escape") {
		if (!registerModal.hidden) {
			closeRegisterModal();
		} else if (!loginModal.hidden) {
			closeLoginModal();
		}
	}
});

passwordToggle.addEventListener("click", () => {
	const isPasswordHidden = passwordInput.type === "password";
	passwordInput.type = isPasswordHidden ? "text" : "password";
	passwordToggle.setAttribute(
		"aria-label",
		isPasswordHidden ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"
	);
	passwordToggle.setAttribute(
		"title",
		isPasswordHidden ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"
	);
});

locationButton.addEventListener("click", () => {
	if (!navigator.geolocation) {
		locationStatus.textContent = "Browser ini tidak mendukung fitur lokasi.";
		return;
	}

	locationButton.disabled = true;
	locationStatus.textContent = "Meminta izin lokasi...";
	navigator.geolocation.getCurrentPosition(
		(position) => {
			latitudeInput.value = String(position.coords.latitude);
			longitudeInput.value = String(position.coords.longitude);
			locationStatus.textContent = "Lokasi berhasil diambil dan akan disimpan saat akun dibuat.";
			clearLocationButton.hidden = false;
			locationButton.disabled = false;
		},
		(error) => {
			const messages = {
				1: "Izin lokasi ditolak. Kamu tetap dapat mendaftar tanpa lokasi.",
				2: "Lokasi tidak tersedia. Kamu tetap dapat mendaftar tanpa lokasi.",
				3: "Waktu permintaan lokasi habis. Silakan coba lagi atau lanjut tanpa lokasi.",
			};
			locationStatus.textContent = messages[error.code] || "Lokasi gagal diambil. Kamu tetap dapat mendaftar tanpa lokasi.";
			locationButton.disabled = false;
		},
		{ enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
	);
});

clearLocationButton.addEventListener("click", () => {
	latitudeInput.value = "";
	longitudeInput.value = "";
	clearLocationButton.hidden = true;
	locationStatus.textContent = "Lokasi dihapus. Pendaftaran akan dilanjutkan tanpa lokasi.";
});

registerForm.addEventListener("submit", async (event) => {
	event.preventDefault();
	const password = registerForm.elements.password.value;
	const passwordConfirm = registerForm.elements.password_confirm.value;
	const submitButton = registerForm.querySelector('[type="submit"]');

	if (password !== passwordConfirm) {
		registerMessage.textContent = "Kata sandi dan konfirmasi kata sandi tidak sama.";
		registerForm.elements.password_confirm.focus();
		return;
	}

	submitButton.disabled = true;
	registerMessage.textContent = "Membuat akun...";

	try {
		const response = await fetch("Koneksi/Register.php", {
			method: "POST",
			body: new FormData(registerForm),
			headers: { Accept: "application/json" },
		});
		const result = await response.json();

		if (!response.ok || !result.success) {
			registerMessage.textContent = result.message || "Pendaftaran gagal. Silakan coba lagi.";
			return;
		}

		const registeredUsername = registerForm.elements.username.value;
		registerForm.reset();
		latitudeInput.value = "";
		longitudeInput.value = "";
		clearLocationButton.hidden = true;
		locationStatus.textContent = "Belum ada lokasi yang dipilih.";
		closeRegisterModal();
		loginModal.hidden = false;
		document.body.style.overflow = "hidden";
		document.getElementById("username").value = registeredUsername;
		document.getElementById("password").focus();
		loginMessage.textContent = "Akun berhasil dibuat. Silakan login.";
	} catch {
		registerMessage.textContent = "Tidak dapat memproses pendaftaran. Pastikan Apache dan MySQL XAMPP berjalan.";
	} finally {
		submitButton.disabled = false;
	}
});

loginForm.addEventListener("submit", async (event) => {
	event.preventDefault();
	const submitButton = loginForm.querySelector('[type="submit"]');
	submitButton.disabled = true;
	loginMessage.textContent = "Memeriksa data login...";

	try {
		const response = await fetch("Koneksi/Login.php", {
			method: "POST",
			body: new FormData(loginForm),
			headers: { Accept: "application/json" },
		});
		const result = await response.json();

		if (!response.ok || !result.success) {
			loginMessage.textContent = result.message || "Nama pengguna atau kata sandi salah.";
			return;
		}

		loginMessage.textContent = "Login berhasil. Mengalihkan ke beranda...";
		window.location.href = "Dasbor/Beranda.php";
	} catch {
		loginMessage.textContent = "Tidak dapat memproses login. Periksa koneksi server.";
	} finally {
		submitButton.disabled = false;
	}
});

async function updateAccountButtons() {
	try {
		const response = await fetch("Koneksi/Session.php", {
			headers: { Accept: "application/json" },
		});
		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error("Status sesi tidak tersedia.");
		}

		registerButton.hidden = result.authenticated;
		loginButton.hidden = result.authenticated;
		dashboardButton.hidden = !result.authenticated;
		logoutButton.hidden = !result.authenticated;
		accountMessage.hidden = true;
	} catch {
		accountMessage.textContent = "Status sesi tidak dapat diperiksa. Pastikan server aktif.";
		accountMessage.hidden = false;
	}
}

updateAccountButtons();
if (new URLSearchParams(window.location.search).get("login") === "1") {
	openLoginModal();
}