# Pmrograman-Client-Server
Tempat tugas

## Pendaftaran akun di XAMPP

1. Jalankan Apache dan MySQL dari XAMPP Control Panel.
2. Buka phpMyAdmin, pilih **Import**, lalu impor [`Koneksi/Setup_Database.sql`](Koneksi/Setup_Database.sql). Skrip ini membuat database `arb.wcite`, tabel `login`, dan tabel `account_profiles` jika belum tersedia. Jika tabel `login` sudah ada, pastikan tabel tersebut memiliki kolom `Username` dan `Password`, kolom `Username` unik, menggunakan InnoDB, serta dapat menyimpan kata sandi hingga 255 karakter.
3. Buka aplikasi melalui Apache, misalnya `http://localhost/ABI%20CLIENT%20SERVER/Interface.html`; jangan membuka berkas HTML langsung dari File Explorer karena PHP perlu dijalankan oleh Apache.
4. Tekan **Daftar** untuk membuat akun. Isi daerah/provinsi dan negara; keduanya dikirim ke endpoint pendaftaran aplikasi dan disimpan pada profil. Pengguna dapat memilih apakah akan memberikan izin lokasi; tanpa izin lokasi, akun tetap dapat dibuat.
5. Setelah login, tombol **Keluar / Ganti akun** mengakhiri sesi dan membuka kembali formulir login.

Kata sandi disimpan menggunakan `password_hash()`. Informasi profil, daerah/negara, dan koordinat (jika diberikan) disimpan di `account_profiles`, terpisah dari tabel kredensial `login`.
