# Sistem Absensi Fingerprint dan Pengenalan Wajah

## Menjalankan aplikasi

1. Jalankan Apache dan MySQL pada XAMPP.
2. Import `Koneksi/Setup_Database.sql` melalui phpMyAdmin.
3. Buka `http://localhost/PEMROGRAMAN%20CLIENT%20SERVER/Interface.html`.

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

## Ruangan

- RK 3.16
- Lab Komputer 1
- Lab Komputer 2
- Lab Unprotek
- Lab Teknik Kendali

Dosen mengubah status ruangan melalui `Dasbor/Dosen.php`. Mahasiswa hanya dapat mencatat absensi ke ruangan yang terbuka melalui `Dasbor/Beranda.php`.

## Catatan biometrik

Versi ini menggunakan simulasi sidik jari dan kamera perangkat untuk pengenalan wajah sebagai prototipe presentasi. Verifikasi identitas produksi memerlukan sensor fingerprint, SDK wajah/liveness, dan penyimpanan template biometrik yang aman.
