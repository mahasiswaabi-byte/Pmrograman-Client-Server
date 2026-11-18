const announcementsList = document.getElementById("announcements-list");
const announcementsStatus = document.getElementById("announcements-status");
const eventsList = document.getElementById("events-list");
const eventsStatus = document.getElementById("events-status");
const membersList = document.getElementById("members-list");
const membersStatus = document.getElementById("members-status");
const joinCommunityButton = document.getElementById("community-join-button");

function createTextElement(tagName, className, text) {
	const element = document.createElement(tagName);
	element.className = className;
	element.textContent = text;
	return element;
}

function formatCommunityDate(value, options) {
	const date = new Date(value.replace(" ", "T"));
	if (Number.isNaN(date.getTime())) {
		return value;
	}
	return new Intl.DateTimeFormat("id-ID", options).format(date);
}

function renderAnnouncements(announcements) {
	if (announcements.length === 0) {
		announcementsStatus.textContent = "Belum ada pengumuman dari pengelola komunitas.";
		return;
	}

	announcementsStatus.hidden = true;
	announcements.forEach((announcement) => {
		const card = document.createElement("article");
		card.className = "community-card";
		card.append(
			createTextElement("time", "community-card-date", formatCommunityDate(announcement.publishedAt, {
				dateStyle: "long",
				timeStyle: "short",
			})),
			createTextElement("h3", "community-card-title", announcement.title),
			createTextElement("p", "community-card-copy", announcement.body)
		);
		announcementsList.append(card);
	});
}

function renderEvents(events) {
	if (events.length === 0) {
		eventsStatus.textContent = "Belum ada agenda mendatang. Silakan cek kembali nanti.";
		return;
	}

	eventsStatus.hidden = true;
	events.forEach((event) => {
		const card = document.createElement("article");
		card.className = "community-card community-event-card";
		card.append(
			createTextElement("time", "community-card-date", formatCommunityDate(event.startsAt, {
				dateStyle: "full",
				timeStyle: "short",
			})),
			createTextElement("h3", "community-card-title", event.title),
			createTextElement("p", "community-card-copy", event.description),
			createTextElement("p", "community-card-location", `Lokasi: ${event.location}`)
		);
		eventsList.append(card);
	});
}

function renderMembers(members) {
	if (members.length === 0) {
		membersStatus.textContent = "Belum ada anggota yang memilih untuk tampil di direktori publik.";
		return;
	}

	membersStatus.textContent = `${members.length}${members.length === 200 ? "+" : ""} anggota memilih untuk tampil.`;
	members.forEach((member) => {
		const item = document.createElement("li");
		item.className = "community-member-item";
		item.append(
			createTextElement("span", "community-member-name", member.name),
			createTextElement("span", "community-member-region", member.regency || "Wilayah belum diisi")
		);
		membersList.append(item);
	});
}

async function loadCommunityData() {
	try {
		const response = await fetch("Koneksi/Community.php", {
			headers: { Accept: "application/json" },
		});
		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error(result.message || "Data komunitas tidak dapat dimuat.");
		}

		renderAnnouncements(Array.isArray(result.announcements) ? result.announcements : []);
		renderEvents(Array.isArray(result.events) ? result.events : []);
		renderMembers(Array.isArray(result.members) ? result.members : []);
	} catch (error) {
		const message = error instanceof Error ? error.message : "Data komunitas tidak dapat dimuat.";
		announcementsStatus.textContent = message;
		eventsStatus.textContent = message;
		membersStatus.textContent = message;
	}
}

joinCommunityButton.addEventListener("click", () => {
	if (document.getElementById("register-button").hidden) {
		window.location.href = document.getElementById("dashboard-button").href;
		return;
	}
	document.getElementById("register-button").click();
});

loadCommunityData();
