const loginButton = document.getElementById("login-button");
const loginModal = document.getElementById("login-modal");
const closeLoginButton = document.getElementById("close-login");
const loginForm = document.getElementById("login-form");
const passwordInput = document.getElementById("password");
const passwordToggle = document.getElementById("password-toggle");
const loginMessage = document.getElementById("login-message");

function openLoginModal(event) {
	event.preventDefault();
	loginModal.hidden = false;
	document.body.style.overflow = "hidden";
	document.getElementById("username").focus();
}

function closeLoginModal() {
	loginModal.hidden = true;
	document.body.style.overflow = "";
}

loginButton.addEventListener("click", openLoginModal);
closeLoginButton.addEventListener("click", closeLoginModal);

loginModal.addEventListener("click", (event) => {
	if (event.target === loginModal) {
		closeLoginModal();
	}
});

document.addEventListener("keydown", (event) => {
	if (event.key === "Escape" && !loginModal.hidden) {
		closeLoginModal();
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

loginForm.addEventListener("submit", (event) => {
	event.preventDefault();
	loginMessage.textContent = "Data login siap diproses.";
});
