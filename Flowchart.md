# Flowchart Program Arb.Wcite

Dokumen ini menggambarkan alur aplikasi berdasarkan implementasi saat ini:
`Interface.html`, `Login.js`, endpoint PHP di `Koneksi/`, dan `Dasbor/Beranda.php`.
Diagram menggunakan Mermaid dan dapat ditampilkan di VS Code atau GitHub.

## 1. Inisialisasi halaman dan data lokasi

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka Interface.html melalui Apache]
    B --> C[Muat Visual.css dan Login.js]
    C --> D[Periksa sesi melalui GET Koneksi/Session.php]
    C --> E[Muat daftar negara dari CountriesNow]
    C --> F{URL memiliki login=1?}

    D --> G{Respons sesi berhasil?}
    G -->|Ya, sesi aktif| H[Sembunyikan tombol Login dan Daftar; tampilkan Beranda dan Keluar]
    G -->|Ya, belum login| I[Tampilkan tombol Login dan Daftar]
    G -->|Tidak atau jaringan gagal| J[Tampilkan pesan status sesi tidak tersedia]

    E --> K{API merespons dengan daftar negara valid?}
    K -->|Ya| L[Urutkan dan tampilkan negara pada dropdown]
    K -->|Tidak, error, atau timeout 12 detik| M[Matikan dropdown; tampilkan kolom negara manual]

    F -->|Ya| N[Buka modal Login]
    F -->|Tidak| O[Modal tetap tersembunyi]
```

Daftar negara dan daerah diambil langsung oleh browser dari API CountriesNow.
Jika daftar negara gagal dimuat, pendaftaran tetap dapat dilanjutkan dengan
mengisi negara dan daerah secara manual.

## 2. Pendaftaran akun dan pemilihan lokasi

```mermaid
flowchart TD
    A([Klik Daftar]) --> B[Tampilkan modal pendaftaran]
    B --> C{Daftar negara berhasil dimuat?}

    C -->|Ya| D[Pilih negara dari dropdown]
    D --> E{Daerah negara tersedia di cache?}
    E -->|Ya| F[Tampilkan saran daerah dari cache]
    E -->|Tidak| G[Kirim POST negara ke API CountriesNow]
    G --> H{API memberi data daerah valid?}
    H -->|Ya, daftar tidak kosong| I[Tampilkan saran daerah; pengguna boleh mengetik manual]
    H -->|Tidak, kosong, error, atau timeout| J[Aktifkan isian daerah manual dan tampilkan status]

    C -->|Tidak| K[Ketik negara pada kolom manual]
    K --> L[Aktifkan isian daerah manual]

    F --> M{Pengguna ingin berbagi lokasi perangkat?}
    I --> M
    J --> M
    L --> M
    M -->|Tidak| P[Lanjut tanpa koordinat]
    M -->|Ya| N[Minta izin melalui Geolocation browser]
    N --> O{Lokasi berhasil diambil?}
    O -->|Ya| Q[Simpan latitude dan longitude pada form]
    O -->|Tidak, ditolak, atau timeout| P
    Q --> R{Pengguna menghapus lokasi?}
    R -->|Ya| P
    R -->|Tidak| S[Koordinat tetap pada form]

    P --> T[Isi nama, username, email, negara, daerah, dan kata sandi]
    S --> T
    T --> U{Validasi browser lolos?}
    U -->|Tidak| V[Browser menandai input yang tidak valid]
    V --> T
    U -->|Ya| W{Kata sandi dan konfirmasi sama?}
    W -->|Tidak| X[Tampilkan pesan kata sandi tidak sama]
    X --> T
    W -->|Ya| Y[Kirim POST FormData ke Koneksi/Register.php]

    Y --> Z{Metode POST dan data server valid?}
    Z -->|Tidak| AA[Kirim JSON error 405 atau 400]
    Z -->|Ya| AB[Hubungkan ke database dan periksa tabel profil]
    AB --> AC{Tabel account_profiles tersedia?}
    AC -->|Tidak| AD[Kirim JSON error 500 tentang tabel profil]
    AC -->|Ya| AE{Username atau email sudah digunakan?}
    AE -->|Ya| AF[Kirim JSON error 409]
    AE -->|Tidak| AG[Hash kata sandi dan mulai transaksi database]
    AG --> AH[Simpan kredensial ke tabel login]
    AH --> AI[Simpan profil, negara, daerah, dan koordinat ke account_profiles]
    AI --> AJ{Transaksi berhasil?}
    AJ -->|Ya| AK[Commit dan kirim JSON 201 sukses]
    AJ -->|Tidak| AL[Rollback dan kirim JSON error 409 atau 500]

    AA --> AM[Login.js menampilkan pesan gagal]
    AD --> AM
    AF --> AM
    AL --> AM
    AK --> AN[Reset form dan buka modal Login dengan username terisi]
```

Negara dan daerah wajib diisi; koordinat lokasi opsional. Jika daftar API gagal,
pengguna dapat memasukkan negara dan daerah sendiri. Setelah pendaftaran berhasil,
profil disimpan terpisah dari kredensial.

## 3. Login dan pemeriksaan sesi

```mermaid
flowchart TD
    A([Interface.html dimuat]) --> B[GET Koneksi/Session.php]
    B --> C{Respons sesi valid?}
    C -->|Sesi aktif| D[Tampilkan akses Beranda dan Keluar]
    C -->|Belum login| E[Tampilkan tombol Login dan Daftar]
    C -->|Permintaan gagal| F[Tampilkan pesan status sesi tidak tersedia]

    G([Modal Login]) --> H[Isi username dan kata sandi]
    H --> I{Validasi required browser lolos?}
    I -->|Tidak| J[Browser meminta input yang wajib]
    J --> H
    I -->|Ya| K[Kirim POST FormData ke Koneksi/Login.php]

    K --> L{Username dan kata sandi terisi?}
    L -->|Tidak| M[Kirim JSON error 400]
    L -->|Ya| N[Cari username menggunakan prepared statement]
    N --> O{Akun ditemukan dan kata sandi cocok?}
    O -->|Tidak| P[Kirim JSON error 401]
    O -->|Ya| Q[Regenerasi ID sesi dan simpan username]
    Q --> R[Kirim JSON success]
    N -.->|Koneksi atau proses database gagal| S[Kirim JSON error 500]

    M --> T[Login.js menampilkan pesan gagal]
    P --> T
    S --> T
    T --> H
    R --> U[Arahkan browser ke Dasbor/Beranda.php]

    U --> V{Sesi username valid?}
    V -->|Tidak| W[Arahkan kembali ke Interface.html?login=1]
    V -->|Ya| X[Tampilkan dashboard dan username]
    X -.->|Jika kembali ke Interface.html| B
```

Login menerima kata sandi hash melalui `password_verify`; kata sandi lama yang
belum di-hash masih diperiksa menggunakan pembandingan aman `hash_equals`.

## 4. Dashboard, navigasi, dan logout

```mermaid
flowchart TD
    A([Buka Beranda.html lama atau Dasbor/Beranda.php]) --> B{Rute yang dibuka?}
    B -->|Beranda.html lama| C[Arahkan ke Dasbor/Beranda.php]
    B -->|Dasbor/Beranda.php| D{Sesi username valid?}
    C --> D
    D -->|Tidak| E[Arahkan ke Interface.html?login=1]
    D -->|Ya| F[Tampilkan dashboard dan username sesi]

    F --> G{Bagian yang dipilih?}
    G -->|Logo atau Beranda| H[Arahkan ke bagian beranda]
    G -->|Informasi| I[Arahkan ke bagian informasi]
    G -->|Perbaikan| J[Arahkan ke panduan perbaikan]
    G -->|Hubungi Kami| K[Arahkan ke bagian kontak]
    H --> G
    I --> G
    J --> G
    K --> G

    F --> L[Klik Keluar / Ganti akun]
    L --> M[Kirim POST ke Koneksi/Logout.php]
    M --> N[Hapus data sesi dan akhiri sesi]
    N --> O{Logout berhasil?}
    O -->|Ya, form dashboard| P[Arahkan ke Interface.html?login=1]
    O -->|Ya, tombol di halaman utama| Q[Kirim JSON sukses; arahkan ke Interface.html?login=1]
    O -->|Tidak| R[Kirim JSON error 500]
```

Tautan dashboard berpindah ke bagian pada halaman yang sama. Proses perbaikan
yang tersedia saat ini berupa panduan informasi, bukan pengajuan perbaikan otomatis.

## 5. Pemeriksaan koneksi database

```mermaid
flowchart TD
    A([Buka Koneksi/Check_Koneksi.php]) --> B[Muat Koneksi.php]
    B --> C{Koneksi ke database arb.wcite berhasil?}
    C -->|Ya| D[Tampilkan Koneksi Sukses]
    C -->|Tidak| E[Koneksi.php melempar error]
    E --> F[Check_Koneksi.php menangkap error]
    F --> G[Set HTTP 500 dan tampilkan Koneksi Gagal]
```

Pemeriksaan koneksi ini berdiri sendiri dan tidak dijalankan otomatis saat login.
