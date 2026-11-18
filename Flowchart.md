# Flowchart Program Arb.Wcite

Dokumen ini menggambarkan alur aplikasi berdasarkan implementasi saat ini:
`Interface.html`, `Login.js`, `Community.js`, endpoint PHP di `Koneksi/`, dan dashboard di `Dasbor/`.
Diagram menggunakan Mermaid dan dapat ditampilkan di VS Code atau GitHub.

## 1. Inisialisasi halaman dan data lokasi

```mermaid
flowchart TD
    A([Mulai]) --> B[Buka Interface.html melalui Apache]
    B --> C[Muat Visual.css, Login.js, dan Community.js]
    C --> D[Periksa sesi melalui GET Koneksi/Session.php]
    C --> E[Muat daftar negara dari CountriesNow]
    C --> F{URL memiliki login=1?}
    C --> P[GET Koneksi/Community.php untuk pengumuman, agenda, dan direktori]

    D --> G{Respons sesi berhasil?}
    G -->|Ya, sesi aktif| H[Sembunyikan tombol Login dan Daftar; tampilkan Beranda dan Keluar]
    G -->|Ya, belum login| I[Tampilkan tombol Login dan Daftar]
    G -->|Tidak atau jaringan gagal| J[Tampilkan pesan status sesi tidak tersedia]

    E --> K{API merespons dengan daftar negara valid?}
    K -->|Ya| L[Urutkan dan tampilkan negara pada dropdown]
    K -->|Tidak, error, atau timeout 12 detik| M[Matikan dropdown; tampilkan kolom negara manual]

    F -->|Ya| N[Buka modal Login]
    F -->|Tidak| O[Modal tetap tersembunyi]

    P --> Q{Data komunitas berhasil dimuat?}
    Q -->|Ya| R[Tampilkan pengumuman, agenda, dan anggota yang menyetujui publikasi]
    Q -->|Tidak| S[Tampilkan status data komunitas tidak tersedia]
```

Daftar negara diambil dari API CountriesNow dengan Indonesia sebagai pilihan
awal. Wilayah Indonesia dimuat dari API Emsifa; jika API negara gagal, negara
dapat diisi manual, dan jika API wilayah gagal, rincian wilayah dapat diisi
manual.
Data komunitas publik hanya mengirim nama dan kabupaten/kota anggota yang
secara eksplisit mengaktifkan direktori; email dan koordinat tidak disertakan.

## 2. Pendaftaran akun dan pemilihan lokasi

```mermaid
flowchart TD
    A([Klik Daftar]) --> B[Tampilkan modal pendaftaran]
    B --> C{Daftar negara berhasil dimuat?}

    C -->|Ya| D[Indonesia dipilih sebagai nilai awal; pengguna dapat memilih negara lain]
    D --> E{Negara Indonesia?}
    E -->|Ya| F[Muat provinsi dari API Emsifa]
    F --> G[Pilih provinsi; muat kabupaten/kota]
    G --> H[Pilih kabupaten/kota; muat kecamatan]
    H --> I[Pilih kecamatan; muat desa/kelurahan]
    E -->|Tidak| J[Muat saran provinsi dari CountriesNow; isian wilayah lainnya manual]
    F -->|API gagal| K[Aktifkan isian lokasi manual]
    C -->|Tidak| L[Ketik negara pada kolom manual]
    L --> M{Negara Indonesia?}
    M -->|Ya| F
    M -->|Tidak| J

    I --> N{Pengguna ingin berbagi lokasi perangkat?}
    J --> N
    K --> N
    N -->|Tidak| P[Lanjut tanpa koordinat]
    N -->|Ya| O[Minta izin melalui Geolocation browser]
    O --> Q{Lokasi berhasil diambil?}
    Q -->|Ya| R[Simpan latitude dan longitude pada form]
    Q -->|Tidak, ditolak, atau timeout| P
    R --> S{Pengguna menghapus lokasi?}
    S -->|Ya| P
    S -->|Tidak| T[Koordinat tetap pada form]

    P --> U[Isi profil, gender, negara, provinsi, kabupaten/kota, kecamatan, desa, dan kata sandi]
    T --> U
    U --> V{Validasi browser lolos?}
    V -->|Tidak| W[Browser menandai input yang tidak valid]
    W --> U
    V -->|Ya| X{Kata sandi dan konfirmasi sama?}
    X -->|Tidak| Y[Tampilkan pesan kata sandi tidak sama]
    Y --> U
    X -->|Ya| Z[Kirim POST FormData ke Koneksi/Register.php]

    Z --> AA{Metode POST dan data server valid?}
    AA -->|Tidak| AB[Kirim JSON error 405 atau 400]
    AA -->|Ya| AC[Hubungkan ke database dan periksa tabel profil]
    AC --> AD{Tabel account_profiles tersedia?}
    AD -->|Tidak| AE[Kirim JSON error 500 tentang tabel profil]
    AD -->|Ya| AF{Username atau email sudah digunakan?}
    AF -->|Ya| AG[Kirim JSON error 409]
    AF -->|Tidak| AH[Hash kata sandi dan mulai transaksi database]
    AH --> AI[Simpan kredensial ke tabel login]
    AI --> AJ[Simpan profil, gender, negara, wilayah, dan koordinat ke account_profiles]
    AJ --> AK{Transaksi berhasil?}
    AK -->|Ya| AL[Commit dan kirim JSON 201 sukses]
    AK -->|Tidak| AM[Rollback dan kirim JSON error 409 atau 500]

    AB --> AN[Login.js menampilkan pesan gagal]
    AE --> AN
    AG --> AN
    AM --> AN
    AL --> AO[Reset form dan buka modal Login dengan username terisi]
```

Jenis kelamin dan seluruh rincian wilayah wajib diisi; koordinat lokasi opsional.
Jika daftar API gagal, pengguna dapat memasukkan negara dan wilayah sendiri.
Setelah pendaftaran berhasil, profil disimpan terpisah dari kredensial.

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
    Q --> R[Kirim JSON success dengan role akun]
    N -.->|Koneksi atau proses database gagal| S[Kirim JSON error 500]

    M --> T[Login.js menampilkan pesan gagal]
    P --> T
    S --> T
    T --> H
    R --> U{Role akun?}

    U -->|admin| V[Arahkan ke Dasbor/Admin.php]
    U -->|user| W[Arahkan ke Dasbor/Beranda.php]
    V --> X{Role admin valid di database?}
    X -->|Ya| Y[Tampilkan statistik dan pengelolaan konten komunitas]
    X -->|Tidak| Z[Arahkan ke beranda anggota]
    W --> AA{Sesi username dan profil valid?}
    AA -->|Ya| AB[Tampilkan pengaturan persetujuan direktori]
    AA -->|Tidak| AC[Arahkan kembali ke Interface.html?login=1]
```

Login menerima kata sandi hash melalui `password_verify`; kata sandi lama yang
belum di-hash masih diperiksa menggunakan pembandingan aman `hash_equals`.

## 4. Konten komunitas, direktori, dan logout

```mermaid
flowchart TD
    A([Buka Dasbor/Admin.php]) --> B{Sesi dan role admin valid?}
    B -->|Tidak ada sesi| C[Arahkan ke Interface.html?login=1]
    B -->|Bukan admin| D[Arahkan ke Dasbor/Beranda.php]
    B -->|Ya| E[Tampilkan statistik dan formulir konten]
    E --> F{Aksi admin dengan token CSRF valid?}
    F -->|Terbitkan pengumuman| G[Simpan judul dan isi pengumuman]
    F -->|Terbitkan agenda| H[Simpan deskripsi, lokasi, dan waktu kegiatan]
    F -->|Hapus konten| I[Hapus konten terpilih]
    G --> J[Tampilkan konten pada halaman publik]
    H --> J
    I --> J
    F -->|Tidak valid| K[Tampilkan pesan kesalahan]

    L([Anggota membuka Dasbor/Beranda.php]) --> M{Sesi anggota valid?}
    M -->|Tidak| C
    M -->|Ya| N[Pilih tampil atau sembunyi di direktori]
    N --> O{Token CSRF dan pilihan valid?}
    O -->|Ya| P[Simpan PublicDirectory di account_profiles]
    O -->|Tidak| Q[Tampilkan pesan kesalahan]
    P --> R[Direktori publik hanya mengambil nama dan kabupaten/kota]

    S([Klik Keluar]) --> T[Kirim POST ke Koneksi/Logout.php]
    T --> U[Hapus data sesi dan akhiri sesi]
    U --> V{Logout berhasil?}
    V -->|Ya| W[Arahkan ke Interface.html?login=1]
    V -->|Tidak| X[Kirim pesan atau JSON error]
```

Pengumuman dan agenda dapat dibaca publik. Aksi penerbitan dan penghapusan hanya
tersedia untuk admin yang telah diverifikasi di database dan mengirim token CSRF
yang valid. Anggota dapat mengubah persetujuan direktori kapan saja.

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
