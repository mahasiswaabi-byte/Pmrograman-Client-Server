# Sistem Absensi Fingerprint dan Pengenalan Wajah

## Menjalankan aplikasi

1. Jalankan Apache dan MySQL pada XAMPP.
2. Jika database belum dibuat, import `Koneksi/Setup_Database.sql` melalui phpMyAdmin.
3. Jalankan `Koneksi/Migration_Profile_Roles.sql` melalui phpMyAdmin untuk membuat tabel profil terpisah dan menyalin data profil lama.
4. Buka `http://localhost/PEMROGRAMAN%20CLIENT%20SERVER/Interface.html`.

## Peran akun

- **Mahasiswa:** memilih ruangan, melakukan verifikasi sidik jari dan wajah, lalu mencatat kehadiran.
- **Dosen:** membuka atau mengunci lima ruangan.
- **Admin:** memantau daftar akun, status ruangan, dan riwayat absensi.

## Menetapkan role

```sql
UPDATE login SET Role = 'mahasiswa' WHERE Username = 'nama_mahasiswa';
UPDATE login SET Role = 'dosen' WHERE Username = 'nama_dosen';
UPDATE login SET Role = 'admin' WHERE Username = 'nama_admin';
```

Role lama `user` akan dikonversi menjadi `mahasiswa` saat database di-import ulang.

Pendaftaran publik hanya tersedia untuk **Mahasiswa**. Akun Dosen dan Admin dibuat atau ditetapkan oleh pengelola melalui database; NIK, NIM, tanggal lahir, dan foto digunakan sebagai data identitas profil Mahasiswa.

## Pemisahan profil

Data login dan peran akun disimpan di tabel `login`. Data profil disimpan terpisah berdasarkan peran:

- `mahasiswa_profiles`: identitas dan data pendaftaran Mahasiswa.
- `dosen_profiles`: nama dan email Dosen.

Jalankan `Koneksi/Migration_Profile_Roles.sql` satu kali untuk menyalin profil lama dari `account_profiles` ke tabel yang sesuai; data dan tabel lama tidak dihapus. Pendaftaran baru hanya menyimpan profil ke `mahasiswa_profiles`. Untuk menambahkan Dosen, buat akun dengan `Role = 'dosen'` di tabel `login`, lalu tambahkan profil dengan `Username` yang sama di `dosen_profiles`. Nama dan email Dosen dapat diisi saat datanya tersedia.

## Ruangan

- RK 3.16
- Lab Komputer 1
- Lab Komputer 2
- Lab Unprotek
- Lab Teknik Kendali

Dosen mengubah status ruangan melalui `Dasbor/Dosen.php`. Mahasiswa hanya dapat mencatat absensi ke ruangan yang terbuka melalui `Dasbor/Beranda.php`.

## Catatan biometrik

Versi ini menggunakan simulasi sidik jari dan kamera perangkat untuk pengenalan wajah sebagai prototipe presentasi. Verifikasi identitas produksi memerlukan sensor fingerprint, SDK wajah/liveness, dan penyimpanan template biometrik yang aman.
