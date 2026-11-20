const loginButton = document.getElementById("login-button");
const loginModal = document.getElementById("login-modal");
const serviceModal = document.getElementById("service-modal");
const closeServiceButton = document.getElementById("close-service");
const closeLoginButton = document.getElementById("close-login");
const loginForm = document.getElementById("login-form");
const loginRoleSelect = document.getElementById("login-role");
const loginTitle = document.getElementById("login-title");
const passwordInput = document.getElementById("password");
const passwordToggle = document.getElementById("password-toggle");
const loginMessage = document.getElementById("login-message");
const registerButton = document.getElementById("register-button");
const dashboardButton = document.getElementById("dashboard-button");
const logoutButton = document.getElementById("logout-button");
const accountMessage = document.getElementById("account-message");
const communityJoinButton = document.getElementById("community-join-button");
const registerModal = document.getElementById("register-modal");
const closeRegisterButton = document.getElementById("close-register");
const openRegisterButton = document.getElementById("open-register");
const openLoginButton = document.getElementById("open-login");
const registerForm = document.getElementById("register-form");
const registerMessage = document.getElementById("register-message");
const countrySelect = document.getElementById("register-country");
const countryManualInput = document.getElementById("register-country-manual");
const countryStatus = document.getElementById("country-status");
const indonesiaLocationFields = document.getElementById("indonesia-location-fields");
const manualLocationFields = document.getElementById("manual-location-fields");
const locationStatusMessage = document.getElementById("location-status-message");
const manualLocationStatus = document.getElementById("manual-location-status");
const provinceSelect = document.getElementById("register-province");
const regencySelect = document.getElementById("register-regency");
const districtSelect = document.getElementById("register-district");
const villageSelect = document.getElementById("register-village");
const manualProvinceInput = document.getElementById("register-province-manual");
const manualRegencyInput = document.getElementById("register-regency-manual");
const manualDistrictInput = document.getElementById("register-district-manual");
const manualVillageInput = document.getElementById("register-village-manual");
const internationalProvinceOptions = document.getElementById("international-province-options");
const locationButton = document.getElementById("get-location");
const clearLocationButton = document.getElementById("clear-location");
const locationStatus = document.getElementById("location-status");
const latitudeInput = document.getElementById("register-latitude");
const longitudeInput = document.getElementById("register-longitude");
const countryStateCache = new Map();

async function fetchLocationData(url, options = {}) {
	const controller = new AbortController();
	const timeout = window.setTimeout(() => controller.abort(), 12000);

	try {
		const response = await fetch(url, {
			...options,
			signal: controller.signal,
			headers: { Accept: "application/json", ...options.headers },
		});
		if (!response.ok) {
			throw new Error(`Permintaan data lokasi gagal (${response.status}).`);
		}

		return await response.json();
	} finally {
		window.clearTimeout(timeout);
	}
}

async function loadCountries() {
	try {
		const result = await fetchLocationData("https://countriesnow.space/api/v0.1/countries/iso");
		if (result.error || !Array.isArray(result.data)) {
			throw new Error("Format daftar negara tidak valid.");
		}

		const countries = result.data
			.filter((country) => typeof country.name === "string" && country.name.trim() !== "")
			.sort((first, second) => first.name.localeCompare(second.name, "id"));
		if (countries.length === 0) {
			throw new Error("Daftar negara kosong.");
		}

		if (!countries.some((country) => country.name.toLocaleLowerCase("id") === "indonesia")) {
			throw new Error("Indonesia tidak tersedia pada daftar negara.");
		}

		countrySelect.replaceChildren(new Option("Pilih negara", ""));
		countries.forEach((country) => {
			countrySelect.add(new Option(country.name, country.name));
		});
		countrySelect.value = countries.find((country) => country.name.toLocaleLowerCase("id") === "indonesia").name;
		countrySelect.disabled = false;
		countryStatus.textContent = `${countries.length} negara tersedia.`;
		configureLocationFields(countrySelect.value);
	} catch {
		countrySelect.disabled = true;
		countrySelect.removeAttribute("name");
		countrySelect.id = "register-country-select";
		countryManualInput.id = "register-country";
		countryManualInput.name = "country";
		countryManualInput.disabled = false;
		countryManualInput.hidden = false;
		countryStatus.textContent = "Daftar negara tidak dapat dimuat. Indonesia dipilih sebagai nilai awal; kamu dapat mengubahnya.";
		configureLocationFields(countryManualInput.value);
	}
}

function resetLocationSelect(select, placeholder) {
	select.replaceChildren(new Option(placeholder, ""));
	select.disabled = true;
}

function populateLocationSelect(select, entries, placeholder) {
	const options = entries
		.filter((entry) => typeof entry.code === "string" && typeof entry.name === "string" && entry.name.trim() !== "")
		.map((entry) => {
			const option = new Option(entry.name, entry.name);
			option.dataset.code = entry.code;
			return option;
		});
	select.replaceChildren(new Option(placeholder, ""), ...options);
	select.disabled = options.length === 0;
	return options.length;
}

async function fetchIndonesianRegions(path) {
	const result = await fetchLocationData(`https://www.emsifa.com/api-wilayah-indonesia/api/${path}`);
	if (!Array.isArray(result)) {
		throw new Error("Format data wilayah Indonesia tidak valid.");
	}
	return result
		.filter((region) => typeof region.id === "string" && typeof region.name === "string")
		.map((region) => ({ code: region.id, name: region.name }));
}

function clearManualLocationInputs() {
	[manualProvinceInput, manualRegencyInput, manualDistrictInput, manualVillageInput].forEach((input) => {
		input.value = "";
	});
	internationalProvinceOptions.replaceChildren();
}

function enableManualLocationFields(message) {
	indonesiaLocationFields.hidden = true;
	manualLocationFields.hidden = false;
	[provinceSelect, regencySelect, districtSelect, villageSelect].forEach((select) => {
		select.disabled = true;
	});
	[manualProvinceInput, manualRegencyInput, manualDistrictInput, manualVillageInput].forEach((input) => {
		input.disabled = false;
	});
	clearManualLocationInputs();
	manualLocationStatus.textContent = message;
}

async function loadInternationalProvinces(country) {
	manualLocationStatus.textContent = `Memuat saran provinsi/daerah untuk ${country}...`;
	if (countryStateCache.has(country)) {
		populateLocationSelect(
			internationalProvinceOptions,
			countryStateCache.get(country).map((name) => ({ code: name, name })),
			""
		);
		manualLocationStatus.textContent = "Saran provinsi/daerah tersedia. Kabupaten/kota, kecamatan, dan desa diisi manual.";
		return;
	}

	try {
		const result = await fetchLocationData("https://countriesnow.space/api/v0.1/countries/states", {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ country }),
		});
		if (result.error) {
			throw new Error("Data provinsi/daerah tidak tersedia.");
		}

		const countryData = Array.isArray(result.data)
			? result.data.find((item) => item.name === country)
			: result.data;
		const selectedCountry = countrySelect.disabled ? countryManualInput.value : countrySelect.value;
		if (selectedCountry !== country) {
			return;
		}
		const regions = Array.isArray(countryData && countryData.states)
			? countryData.states.map((state) => state.name).filter((name) => typeof name === "string" && name.trim() !== "")
			: [];
		countryStateCache.set(country, regions);
		populateLocationSelect(
			internationalProvinceOptions,
			regions.map((name) => ({ code: name, name })),
			""
		);
		manualLocationStatus.textContent = regions.length > 0
			? "Saran provinsi/daerah tersedia. Kabupaten/kota, kecamatan, dan desa diisi manual."
			: "Saran provinsi tidak tersedia. Semua rincian wilayah dapat diisi manual.";
	} catch {
		const selectedCountry = countrySelect.disabled ? countryManualInput.value : countrySelect.value;
		if (selectedCountry === country) {
			manualLocationStatus.textContent = "Saran provinsi tidak dapat dimuat. Semua rincian wilayah dapat diisi manual.";
		}
	}
}

async function loadProvinces() {
	[provinceSelect, regencySelect, districtSelect, villageSelect].forEach((select, index) => {
		resetLocationSelect(select, ["Pilih provinsi", "Pilih kabupaten/kota", "Pilih kecamatan", "Pilih desa/kelurahan"][index]);
	});
	locationStatusMessage.textContent = "Memuat daftar provinsi Indonesia...";

	try {
		const provinces = await fetchIndonesianRegions("provinces.json");
		if (!countrySelect.disabled && countrySelect.value.toLocaleLowerCase("id") !== "indonesia") {
			return;
		}
		if (populateLocationSelect(provinceSelect, provinces, "Pilih provinsi") === 0) {
			throw new Error("Daftar provinsi Indonesia kosong.");
		}
		locationStatusMessage.textContent = "Pilih provinsi untuk memuat kabupaten/kota.";
	} catch {
		const country = countrySelect.disabled ? countryManualInput.value : countrySelect.value;
		if (country.toLocaleLowerCase("id") === "indonesia") {
			enableManualLocationFields("API wilayah Indonesia tidak dapat dimuat. Silakan isi provinsi, kabupaten/kota, kecamatan, dan desa secara manual.");
		}
	}
}

async function loadRegencies(provinceCode) {
	resetLocationSelect(regencySelect, "Pilih kabupaten/kota");
	resetLocationSelect(districtSelect, "Pilih kecamatan");
	resetLocationSelect(villageSelect, "Pilih desa/kelurahan");
	locationStatusMessage.textContent = "Memuat kabupaten/kota...";
	try {
		const regencies = await fetchIndonesianRegions(`regencies/${encodeURIComponent(provinceCode)}.json`);
		if (provinceSelect.selectedOptions[0]?.dataset.code !== provinceCode) {
			return;
		}
		if (populateLocationSelect(regencySelect, regencies, "Pilih kabupaten/kota") === 0) {
			throw new Error("Daftar kabupaten/kota kosong.");
		}
		locationStatusMessage.textContent = "Pilih kabupaten/kota untuk memuat kecamatan.";
	} catch {
		enableManualLocationFields("Kabupaten/kota gagal dimuat. Silakan isi provinsi, kabupaten/kota, kecamatan, dan desa secara manual.");
	}
}

async function loadDistricts(regencyCode) {
	resetLocationSelect(districtSelect, "Pilih kecamatan");
	resetLocationSelect(villageSelect, "Pilih desa/kelurahan");
	locationStatusMessage.textContent = "Memuat kecamatan...";
	try {
		const districts = await fetchIndonesianRegions(`districts/${encodeURIComponent(regencyCode)}.json`);
		if (regencySelect.selectedOptions[0]?.dataset.code !== regencyCode) {
			return;
		}
		if (populateLocationSelect(districtSelect, districts, "Pilih kecamatan") === 0) {
			throw new Error("Daftar kecamatan kosong.");
		}
		locationStatusMessage.textContent = "Pilih kecamatan untuk memuat desa/kelurahan.";
	} catch {
		enableManualLocationFields("Kecamatan gagal dimuat. Silakan isi provinsi, kabupaten/kota, kecamatan, dan desa secara manual.");
	}
}

async function loadVillages(districtCode) {
	resetLocationSelect(villageSelect, "Pilih desa/kelurahan");
	locationStatusMessage.textContent = "Memuat desa/kelurahan...";
	try {
		const villages = await fetchIndonesianRegions(`villages/${encodeURIComponent(districtCode)}.json`);
		if (districtSelect.selectedOptions[0]?.dataset.code !== districtCode) {
			return;
		}
		if (populateLocationSelect(villageSelect, villages, "Pilih desa/kelurahan") === 0) {
			throw new Error("Daftar desa/kelurahan kosong.");
		}
		locationStatusMessage.textContent = "Pilih desa/kelurahan.";
	} catch {
		enableManualLocationFields("Desa/kelurahan gagal dimuat. Silakan isi provinsi, kabupaten/kota, kecamatan, dan desa secara manual.");
	}
}

function configureLocationFields(country) {
	clearManualLocationInputs();
	if (!country.trim()) {
		indonesiaLocationFields.hidden = false;
		manualLocationFields.hidden = true;
		[manualProvinceInput, manualRegencyInput, manualDistrictInput, manualVillageInput].forEach((input) => {
			input.disabled = true;
		});
		[provinceSelect, regencySelect, districtSelect, villageSelect].forEach((select, index) => {
			resetLocationSelect(select, ["Pilih provinsi", "Pilih kabupaten/kota", "Pilih kecamatan", "Pilih desa/kelurahan"][index]);
		});
		return;
	}

	if (country.trim().toLocaleLowerCase("id") === "indonesia") {
		indonesiaLocationFields.hidden = false;
		manualLocationFields.hidden = true;
		[manualProvinceInput, manualRegencyInput, manualDistrictInput, manualVillageInput].forEach((input) => {
			input.disabled = true;
		});
		loadProvinces();
		return;
	}

	enableManualLocationFields("Untuk negara selain Indonesia, provinsi/daerah dapat menggunakan saran API; rincian lainnya diisi manual.");
	loadInternationalProvinces(country.trim());
}

provinceSelect.addEventListener("change", () => {
	const provinceCode = provinceSelect.selectedOptions[0]?.dataset.code;
	if (provinceCode) {
		loadRegencies(provinceCode);
	} else {
		resetLocationSelect(regencySelect, "Pilih kabupaten/kota");
		resetLocationSelect(districtSelect, "Pilih kecamatan");
		resetLocationSelect(villageSelect, "Pilih desa/kelurahan");
	}
});
regencySelect.addEventListener("change", () => {
	const regencyCode = regencySelect.selectedOptions[0]?.dataset.code;
	if (regencyCode) {
		loadDistricts(regencyCode);
	} else {
		resetLocationSelect(districtSelect, "Pilih kecamatan");
		resetLocationSelect(villageSelect, "Pilih desa/kelurahan");
	}
});
districtSelect.addEventListener("change", () => {
	const districtCode = districtSelect.selectedOptions[0]?.dataset.code;
	if (districtCode) {
		loadVillages(districtCode);
	} else {
		resetLocationSelect(villageSelect, "Pilih desa/kelurahan");
	}
});
countrySelect.addEventListener("change", () => configureLocationFields(countrySelect.value));
countryManualInput.addEventListener("change", () => configureLocationFields(countryManualInput.value));

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

function openServiceModal(event) {
	if (event) {
		event.preventDefault();
	}
	registerModal.hidden = true;
	loginModal.hidden = true;
	serviceModal.hidden = false;
	document.body.style.overflow = "hidden";
}

function closeServiceModal() {
	serviceModal.hidden = true;
	document.body.style.overflow = "";
}

loginButton.addEventListener("click", openServiceModal);
communityJoinButton?.addEventListener("click", openServiceModal);
closeLoginButton.addEventListener("click", closeLoginModal);
closeServiceButton.addEventListener("click", closeServiceModal);
serviceModal.addEventListener("click", (event) => {
	if (event.target === serviceModal) {
		closeServiceModal();
	}
});
document.querySelectorAll(".service-card").forEach((card) => {
	card.addEventListener("click", () => {
		const role = card.dataset.role;
		loginRoleSelect.value = role;
		loginRoleSelect.dispatchEvent(new Event("change"));
		const authSwitch = loginForm.querySelector(".auth-switch");
		authSwitch.hidden = role !== "mahasiswa";
		closeServiceModal();
		loginModal.hidden = false;
		document.body.style.overflow = "hidden";
		loginMessage.textContent = "";
		document.getElementById("username").focus();
	});
});
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
		} else if (!serviceModal.hidden) {
			closeServiceModal();
		}
	}
});

loginRoleSelect.addEventListener("change", () => {
	const roleLabel = {
		dosen: "Dosen",
		mahasiswa: "Mahasiswa",
		admin: "Operator",
	};
	loginTitle.textContent = `Masuk Sebagai ${roleLabel[loginRoleSelect.value] || "Pengguna"}`;
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
		if (!countrySelect.disabled) {
			countrySelect.value = "Indonesia";
			configureLocationFields(countrySelect.value);
		} else {
			countryManualInput.value = "Indonesia";
			configureLocationFields(countryManualInput.value);
		}
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
		window.location.href = result.role === "admin"
			? "Dasbor/Admin.php"
			: (result.role === "dosen" ? "Dasbor/Dosen.php" : "Dasbor/Beranda.php");
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
		if (result.authenticated) {
			dashboardButton.href = result.role === "admin"
				? "Dasbor/Admin.php"
				: (result.role === "dosen" ? "Dasbor/Dosen.php" : "Dasbor/Beranda.php");
		}
		if (communityJoinButton) {
			communityJoinButton.textContent = result.authenticated ? "Buka dashboard" : "Mulai absensi";
		}
		logoutButton.hidden = !result.authenticated;
		accountMessage.hidden = true;
	} catch {
		accountMessage.textContent = "Status sesi tidak dapat diperiksa. Pastikan server aktif.";
		accountMessage.hidden = false;
	}
}

updateAccountButtons();
loadCountries();
if (new URLSearchParams(window.location.search).get("login") === "1") {
	openLoginModal();
}