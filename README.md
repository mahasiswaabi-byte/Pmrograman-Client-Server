# Pmrograman-Client-Server
Tempat tugas

## Pendaftaran akun di XAMPP

1. Jalankan Apache dan MySQL dari XAMPP Control Panel.
2. Buka phpMyAdmin, pilih **Import**, lalu impor [`Koneksi/Setup_Database.sql`](Koneksi/Setup_Database.sql). Jalankan skrip ini juga jika database atau tabel sudah ada: skrip menambahkan kolom profil yang belum tersedia, termasuk `Province`, `Regency`, `District`, `Village`, dan `Gender`. Jika tabel `login` sudah ada, pastikan tabel tersebut memiliki kolom `Username` dan `Password`, kolom `Username` unik, menggunakan InnoDB, serta dapat menyimpan kata sandi hingga 255 karakter.
3. Buka aplikasi melalui Apache, misalnya `http://localhost/ABI%20CLIENT%20SERVER/Interface.html`; jangan membuka berkas HTML langsung dari File Explorer karena PHP perlu dijalankan oleh Apache.
4. Tekan **Daftar** untuk membuat akun. Daftar negara dimuat dari API publik CountriesNow dan Indonesia dipilih sebagai nilai awal. Untuk Indonesia, provinsi, kabupaten/kota, kecamatan, dan desa/kelurahan dimuat bertahap dari API publik [Emsifa wilayah Indonesia](https://www.emsifa.com/api-wilayah-indonesia/). Untuk negara lain, provinsi/daerah memiliki saran dari CountriesNow dan rincian wilayah diisi manual; isian manual juga tersedia jika API wilayah Indonesia tidak dapat diakses. Jenis kelamin dipilih dari Laki-laki, Perempuan, atau Lainnya. Data profil tersebut dikirim ke endpoint pendaftaran dan disimpan pada `account_profiles`. Pengguna dapat memilih apakah akan memberikan izin lokasi; tanpa izin lokasi, akun tetap dapat dibuat.
5. Setelah login, tombol **Keluar / Ganti akun** mengakhiri sesi dan membuka kembali formulir login.

Kata sandi disimpan menggunakan `password_hash()`. Informasi profil, gender, negara, provinsi, kabupaten/kota, kecamatan, desa/kelurahan, dan koordinat (jika diberikan) disimpan di `account_profiles`, terpisah dari tabel kredensial `login`.

## Dashboard admin

1. Impor ulang [`Koneksi/Setup_Database.sql`](Koneksi/Setup_Database.sql) untuk menambahkan kolom `Role` pada tabel `login`. Akun yang sudah ada otomatis tetap ber-role `user`.
2. Buat akun melalui formulir **Daftar**, lalu masuk ke phpMyAdmin dan jalankan perintah berikut di tab **SQL**. Ganti `nama_admin` dengan username akun yang dibuat:

   ```sql
   UPDATE login SET Role = 'admin' WHERE Username = 'nama_admin';
   ```

3. Login menggunakan akun tersebut. Admin diarahkan ke `Dasbor/Admin.php`, yang menampilkan total pengguna, persentase jenis kelamin, serta sebaran negara dan provinsi. Akun ber-role admin tidak dihitung sebagai pengguna terdaftar.

Akses dashboard admin diverifikasi terhadap role akun di database; mengganti alamat URL saja tidak memberikan akses admin.
