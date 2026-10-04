<div align="center">

<img src="favicon.svg" alt="Server Console" width="72" height="72">

# Server Console

**Dashboard server untuk FreeBSD + nginx + PHP-FPM.**
Pantau resource, buka project, cek service, dan tampilkan halaman error yang rapi, semuanya dalam satu tema.

<br>

[![FreeBSD](https://img.shields.io/badge/FreeBSD-13%2B-AB2B28?style=flat-square&logo=freebsd&logoColor=white)](https://www.freebsd.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![nginx](https://img.shields.io/badge/nginx-1.2x%2B-009639?style=flat-square&logo=nginx&logoColor=white)](https://nginx.org)
[![Lisensi](https://img.shields.io/badge/lisensi-CDDL--1.0-ff8f47?style=flat-square)](#lisensi)

<br>

<img src="https://github.com/user-attachments/assets/5c138775-a471-4679-9eb8-d129c5f9f6a8" alt="Tampilan utama Server Console (tema terang)" width="75%">

</div>

<br>

## Daftar Isi

- [Tentang Proyek](#tentang-proyek)
- [Fitur](#fitur)
- [Tampilan](#tampilan)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
- [Konfigurasi nginx](#konfigurasi-nginx)
- [Konfigurasi PHP-FPM](#konfigurasi-php-fpm)
- [Cara Kerja Halaman Error](#cara-kerja-halaman-error)
- [Menguji Hasilnya](#menguji-hasilnya)
- [Kustomisasi](#kustomisasi)
- [Keamanan](#keamanan)
- [Pemecahan Masalah](#pemecahan-masalah)
- [Pertanyaan Umum](#pertanyaan-umum)
- [Struktur File](#struktur-file)
- [Lisensi](#lisensi)
- [Kredit](#kredit)

<br>

## Tentang Proyek

Server Console adalah halaman depan untuk server pengembangan (*development server*) berbasis FreeBSD. Saat Anda membuka alamat server, Anda langsung melihat kondisi mesin dan daftar project yang ada, tanpa perlu masuk lewat SSH.

Proyek ini terdiri dari **tiga file PHP** yang berdiri sendiri dan **satu file ikon**. Tidak ada framework, tidak ada Composer, tidak ada `npm`, dan tidak ada file font atau skrip dari internet. Salin file ke document root, atur nginx, selesai.

| File | Fungsi |
| :--- | :--- |
| `index.php` | Dashboard utama: resource, project, service, dan informasi PHP. |
| `40x.php` | Halaman untuk error sisi pengguna (400, 401, 403, 404, dan sejenisnya). |
| `50x.php` | Halaman untuk error sisi server (500, 502, 503, 504, dan sejenisnya). |
| `favicon.svg` | Ikon tab browser (disediakan sendiri oleh pemilik proyek). |

<br>

## Fitur

### Dashboard (`index.php`)

- **Jam server live** dalam timezone server, lengkap dengan tanggal dan nomor minggu.
- **Metrik live** yang diperbarui otomatis setiap 3 detik: prosesor, memori, swap, dan disk. Pembaruan berhenti sendiri saat tab tidak aktif.
- **Grafik System pulse** yang menggambar pemakaian CPU dan memori secara langsung. Arahkan kursor ke grafik untuk melihat nilai di titik waktu tertentu.
- **Kartu Uptime** berisi lama server menyala, versi FreeBSD, load average (1, 5, dan 15 menit), nama sistem, model prosesor, jumlah core, serta kapasitas **pool ZFS**.
- **Deteksi project otomatis**: setiap folder di document root tampil sebagai kartu yang bisa diklik.
- **Status service**: web server, PHP, MySQL/MariaDB, PostgreSQL, Redis, Memcached, dan SSH. MySQL dan PostgreSQL dicek lewat port TCP **dan** *unix socket*, karena paket FreeBSD sering memakai socket saja.
- **Daftar PHP extension** dalam modal, dikelompokkan per kategori dan bisa dicari.
- **PHP info** dalam modal, hanya untuk alamat IP *private*.
- **Tema terang dan gelap** otomatis mengikuti pengaturan sistem pengguna.
- **Responsif**: di layar kecil, navigasi berubah menjadi tab bar di bagian bawah dan modal menjadi *bottom sheet*.

### Halaman error (`40x.php` dan `50x.php`)

- Kode error **terbaca otomatis**, sehingga satu file melayani banyak kode.
- Teks, saran, dan warna **menyesuaikan jenis error**.
- `40x.php` menampilkan detail permintaan dan **saran folder yang mirip** ("Did you mean") saat alamat salah ketik.
- `50x.php` menampilkan **kondisi server** saat error terjadi, **nomor referensi** yang juga dicatat di log, dan **pengecekan ulang otomatis** untuk 502, 503, dan 504.
- Catatan teknis khusus pengembang (perintah FreeBSD yang relevan) hanya terlihat dari IP *private*.

> [!NOTE]
> Semua gaya, ikon, dan skrip ditulis langsung di dalam file. Halaman tetap jalan sempurna di server yang tidak terhubung ke internet.

<br>

## Tampilan
<table>
  <tr>
    <td width="50%" align="center">
      <img src="https://github.com/user-attachments/assets/5c138775-a471-4679-9eb8-d129c5f9f6a8" alt="Dashboard tema terang">
      <br><sub><b>Dashboard</b> · tema terang</sub>
    </td>
    <td width="50%" align="center">
      <img src="https://github.com/user-attachments/assets/61143def-6424-4430-95b4-f8de5240013a" alt="Dashboard tema gelap">
      <br><sub><b>Dashboard</b> · tema gelap</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center">
      <img src="https://github.com/user-attachments/assets/5e42dfb0-802c-4ead-bf82-e991738ecce7" alt="Modal PHP extensions">
      <br><sub><b>Modal PHP extensions</b> · dengan kategori dan pencarian</sub>
    </td>
    <td width="50%" align="center">
      <img src="https://github.com/user-attachments/assets/2eb5b4ed-27c1-4907-bfa4-ca81c1f4f966" alt="Tampilan mobile" width="240">
      <br><sub><b>Mobile</b> · tab bar di bagian bawah</sub>
    </td>
  </tr>
</table>

<details>
<summary><b>Lihat halaman error</b></summary>
<br>

<table>
  <tr>
    <td width="50%" align="center">
      <img src="https://github.com/user-attachments/assets/b4188633-f88b-4ea3-b595-275d3bd7ebf4" alt="Halaman error 404">
      <br><sub><b>404</b> · dengan saran folder yang mirip</sub>
    </td>
    <td width="50%" align="center">
      <img src="https://github.com/user-attachments/assets/525154b9-7ba0-456b-adbc-61a0112b30b0" alt="Halaman error 502">
      <br><sub><b>502</b> · dengan kondisi server dan pengecekan ulang otomatis</sub>
    </td>
  </tr>
</table>

</details>

<br>

## Kebutuhan Sistem

| Komponen | Minimum | Catatan |
| :--- | :--- | :--- |
| **FreeBSD** | 13.0 | Dikembangkan dan dicoba pada FreeBSD 15.1. |
| **nginx** | 1.20 | Dicoba pada nginx 1.30. |
| **PHP** | 8.0 | Dicoba pada PHP 8.5, dijalankan lewat **PHP-FPM**. |
| **Extension PHP** | `mbstring` | Dipakai oleh `40x.php` dan `50x.php`. |
| **Fungsi PHP** | `shell_exec` | Untuk membaca `sysctl`, `swapinfo`, dan `zpool`. Jika dimatikan, metrik tampil "not available". |

<br>

## Instalasi

### 1. Pasang paket

```sh
pkg install nginx php85 php85-mbstring php85-mysqli
```

> [!NOTE]
> Ganti `php85` dengan versi PHP yang Anda pakai (misalnya `php84`).

### 2. Aktifkan service saat boot

```sh
sysrc nginx_enable=YES
sysrc php_fpm_enable=YES
```

### 3. Salin file ke document root

Document root bawaan nginx di FreeBSD adalah `/usr/local/www/nginx`.

```sh
cd /usr/local/www/nginx
git clone https://github.com/relvinarsenio/dev-env -b freebsd-env .
# atau salin manual: index.php, 40x.php, 50x.php, favicon.svg
```

### 4. Atur nginx dan PHP-FPM

Ikuti bagian [Konfigurasi nginx](#konfigurasi-nginx) dan [Konfigurasi PHP-FPM](#konfigurasi-php-fpm) di bawah.

### 5. Jalankan

```sh
service php_fpm start
service nginx configtest
service nginx start
```

Buka `http://<alamat-server>/` di browser.

<br>

## Konfigurasi nginx

Berkas konfigurasi utama berada di `/usr/local/etc/nginx/nginx.conf`. Contoh blok `server` lengkap:

```nginx
server {
    listen       80;
    server_name  localhost;

    root   /usr/local/www/nginx;
    index  index.php index.html;

    # ------------------------------------------------------------------
    # Halaman error
    # ------------------------------------------------------------------
    error_page 400 401 403 404 405 408 410 413 414 429 431  /40x.php;
    error_page 500 501 502 503 504 505 507 508              /50x.php;

    # ------------------------------------------------------------------
    # Halaman biasa
    # ------------------------------------------------------------------
    location / {
        try_files $uri $uri/ =404;
    }

    # ------------------------------------------------------------------
    # Eksekusi PHP
    # ------------------------------------------------------------------
    location ~ \.php$ {
        try_files $uri =404;

        include        fastcgi_params;
        fastcgi_pass   unix:/var/run/php-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;

        # Error dari aplikasi PHP (misalnya 404 atau 500) ikut memakai
        # halaman error di atas, bukan halaman bawaan aplikasi.
        fastcgi_intercept_errors on;
    }

    # ------------------------------------------------------------------
    # Handler halaman error
    # ------------------------------------------------------------------
    location = /40x.php {
        internal;
        include        fastcgi_params;
        fastcgi_pass   unix:/var/run/php-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $document_root/40x.php;
        fastcgi_param  ERROR_STATUS    $status;
    }

    location = /50x.php {
        internal;
        include        fastcgi_params;
        fastcgi_pass   unix:/var/run/php-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $document_root/50x.php;
        fastcgi_param  ERROR_STATUS    $status;
    }
}
```

Setelah mengubah konfigurasi, periksa dan muat ulang:

```sh
service nginx configtest && service nginx reload
```

### Penjelasan bagian penting

| Direktif | Fungsi |
| :--- | :--- |
| `error_page ... /40x.php;` | Mengarahkan kode error tertentu ke halaman error milik proyek ini. |
| `fastcgi_intercept_errors on;` | Membuat nginx **mengambil alih** respons error dari PHP-FPM. Tanpa ini, error yang dikirim aplikasi PHP (misalnya `http_response_code(404)`) tampil apa adanya dan `error_page` tidak dipakai. |
| `internal;` | Menutup akses langsung ke `/40x.php` dan `/50x.php` dari browser. Halaman ini hanya bisa dipanggil oleh nginx. |
| `fastcgi_param ERROR_STATUS $status;` | Menyampaikan kode error asli ke PHP. **Tanpa baris ini, halaman error tidak tahu kode yang sebenarnya** dan akan menampilkan 404 atau 500 sebagai nilai bawaan. |

> [!IMPORTANT]
> `fastcgi_intercept_errors on;` hanya diletakkan di `location ~ \.php$`. **Jangan** menaruhnya di `location = /40x.php` atau `location = /50x.php`, supaya tidak terjadi pengalihan berulang (*redirect loop*).

> [!NOTE]
> Hanya kode yang terdaftar di `error_page` yang dicegat. Respons lain dari PHP, seperti pengalihan `301` atau `302`, tetap diteruskan seperti biasa.

> [!WARNING]
> Dengan `fastcgi_intercept_errors on;`, halaman error buatan aplikasi Anda sendiri (misalnya respons JSON dari API yang mengembalikan 404 atau 422) ikut digantikan. Untuk endpoint API, matikan di location khusus:
>
> ```nginx
> location ^~ /api/ {
>     fastcgi_intercept_errors off;
>     # ... pengaturan fastcgi lainnya
> }
> ```

<details>
<summary><b>Opsional: cadangan statis saat PHP-FPM mati</b></summary>
<br>

Jika PHP-FPM mati, nginx tidak bisa menjalankan `50x.php`. Agar pengunjung tetap melihat halaman yang layak, sediakan cadangan HTML statis:

```nginx
location = /50x.php {
    internal;
    include        fastcgi_params;
    fastcgi_pass   unix:/var/run/php-fpm.sock;
    fastcgi_param  SCRIPT_FILENAME $document_root/50x.php;
    fastcgi_param  ERROR_STATUS    $status;

    # Jika PHP-FPM tidak merespons, jatuh ke halaman statis.
    error_page 502 503 504 /50x.html;
}

location = /50x.html {
    internal;
    root /usr/local/www/nginx-dist;   # berisi 50x.html bawaan nginx
}
```

Uji dulu di server Anda sebelum dipakai, karena perilakunya bergantung pada versi nginx.

</details>

<br>

## Konfigurasi PHP-FPM

Berkas pool berada di `/usr/local/etc/php-fpm.d/www.conf`. Pastikan nilai berikut sesuai dengan `fastcgi_pass` di nginx:

```ini
user  = www
group = www

listen        = /var/run/php-fpm.sock
listen.owner  = www
listen.group  = www
listen.mode   = 0660

; Agar shell_exec() menemukan sysctl, swapinfo, dan zpool
clear_env = no
env[PATH] = /sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin
```

Lalu muat ulang:

```sh
service php_fpm restart
```

> [!TIP]
> Jika Anda lebih suka TCP, ubah menjadi `listen = 127.0.0.1:9000` di PHP-FPM dan `fastcgi_pass 127.0.0.1:9000;` di nginx (di **ketiga** tempat).

<br>

## Cara Kerja Halaman Error

### Dari mana kode error didapat?

Halaman error mencari kode dengan urutan berikut, lalu memakai yang pertama ditemukan:

| Urutan | Sumber | Keterangan |
| :---: | :--- | :--- |
| 1 | `$_SERVER['ERROR_STATUS']` | Diisi nginx lewat `fastcgi_param ERROR_STATUS $status;`. **Cara yang direkomendasikan.** |
| 2 | `$_SERVER['REDIRECT_STATUS']` | Cadangan jika diteruskan oleh konfigurasi lain. |
| 3 | `http_response_code()` | Cadangan terakhir. |
| 4 | `?code=NNN` | **Mode pratinjau**, hanya jika tiga sumber di atas kosong. |

### Kode yang didukung

<table>
  <tr>
    <th align="left">File</th>
    <th align="left">Kode</th>
  </tr>
  <tr>
    <td><code>40x.php</code></td>
    <td>400, 401, 403, 404, 405, 408, 410, 413, 414, 429, 431</td>
  </tr>
  <tr>
    <td><code>50x.php</code></td>
    <td>500, 501, 502, 503, 504, 505, 507, 508</td>
  </tr>
</table>

Kode lain pada rentang yang sama tetap ditangani dengan teks umum.

### Perilaku khusus

| Fitur | `40x.php` | `50x.php` |
| :--- | :---: | :---: |
| Detail permintaan (method, host, path, asal tautan) | ✅ | ✅ (path dan method) |
| Saran folder yang mirip ("Did you mean") | ✅ (404 dan 410) | – |
| Pintasan ke project | ✅ | – |
| Kondisi server (CPU, memori, disk, service) | – | ✅ |
| Nomor referensi dan pencatatan ke log | – | ✅ |
| Pengecekan ulang otomatis | – | ✅ (502, 503, 504) |
| Tombol *Try again* | ✅ (408, 429) | ✅ |
| Catatan pengembang (hanya IP private) | ✅ | ✅ |

### Mencari error lewat nomor referensi

Setiap error 5xx diberi nomor referensi 8 karakter, misalnya `A1B2C3D4`. Nomor yang sama tercatat di log PHP, sehingga mudah dicari:

```sh
grep "A1B2C3D4" /var/log/php-fpm.log /var/log/nginx/error.log
```

<br>

## Menguji Hasilnya

### Error 404

```sh
curl -i http://localhost/halaman-yang-tidak-ada
```

Yang diharapkan: status `404 Not Found` dan isi halaman berjudul `404 Not found - localhost`.

### Error 5xx

Tambahkan sementara blok ini di nginx, lalu muat ulang:

```nginx
location = /uji-503 { return 503; }
```

```sh
curl -i http://localhost/uji-503
```

Yang diharapkan: status `503` dan halaman "Temporarily unavailable". Hapus blok ini setelah selesai menguji.

### Pratinjau semua kode error

Hapus `internal;` **sementara** dari location error, muat ulang nginx, lalu buka:

```text
http://localhost/40x.php?code=403
http://localhost/50x.php?code=502
```

Dari IP *private*, di bagian bawah halaman muncul deretan chip untuk berpindah antar-kode. Mode pratinjau mematikan pengecekan ulang otomatis dan pencatatan log.

> [!WARNING]
> Kembalikan `internal;` setelah selesai menguji.

<br>

## Kustomisasi

Semua pengaturan berada di bagian atas file masing-masing.

| Yang ingin diubah | Lokasi | Contoh |
| :--- | :--- | :--- |
| Folder yang disembunyikan dari daftar project | `index.php` · variabel `$ignored` | `['html', 'images', 'nginx-dist', 'cgi-bin']` |
| Jumlah project di rail kanan | `index.php` · variabel `$railMax` | `6` |
| Service yang dicek | `index.php` · array `$services` | Tambah baris `['Nama', 'host:port', probe(...), null]` |
| Extension yang diberi warna oranye | `index.php` · array `$key` | `['mysqli', 'curl', ...]` |
| Kategori extension | `index.php` · array `$extCats` | Ubah pola regex per kategori |
| Tautan phpMyAdmin | `index.php` · cari `/phpmyadmin` | Sesuaikan dengan alias nginx Anda |
| Interval pembaruan live | `index.php` · `setInterval(poll, 3000)` | Nilai dalam milidetik |
| Teks dan saran per error | `40x.php` / `50x.php` · array `$codes` | Format: `[judul, kalimat, saran, catatan pengembang]` |
| Warna | Blok `:root` di tiap file | `--orange`, `--yellow`, `--dark`, dan lainnya |
| Ikon tab | `favicon.svg` di document root | Dipanggil lewat `/favicon.svg` |

> [!TIP]
> Jika Anda mengubah warna, ubah di **ketiga** file (`index.php`, `40x.php`, `50x.php`) agar tema tetap seragam.

<br>

## Keamanan

Dashboard ini menampilkan informasi internal server. Perlakukan seperti panel admin.

> [!CAUTION]
> **Jangan buka dashboard ini ke internet publik tanpa pembatasan akses.** Versi PHP, nama user, daftar project, dan kondisi service adalah informasi yang berguna bagi penyerang.

### Yang sudah dilakukan

- **PHP info** hanya bisa dibuka dari alamat IP *private* (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) dan *loopback*. Dari alamat lain, tombolnya nonaktif dan alamat `?view=phpinfo` mengembalikan `403`.
- **Catatan pengembang** di halaman error hanya tampil untuk IP *private*.
- Semua data dari pengguna (path, header, nama folder) di-*escape* sebelum ditampilkan.
- Halaman error memakai `<meta name="robots" content="noindex">`.
- `40x.php` dan `50x.php` ditutup dengan `internal;` sehingga tidak bisa diakses langsung.

### Yang perlu Anda lakukan

Batasi akses ke seluruh dashboard di level nginx:

```nginx
location = /index.php {
    allow 127.0.0.1;
    allow 10.0.0.0/8;
    allow 172.16.0.0/12;
    allow 192.168.0.0/16;
    deny  all;

    include       fastcgi_params;
    fastcgi_pass  unix:/var/run/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_intercept_errors on;
}
```

> [!NOTE]
> Pemeriksaan "IP private" memakai `REMOTE_ADDR`. Jika nginx berada di belakang *reverse proxy*, alamat yang terbaca adalah alamat proxy, bukan alamat pengunjung. Atur modul `realip` di nginx jika Anda memakai proxy.

<br>

## Pemecahan Masalah

<details>
<summary><b>Metrik tampil "not available" atau kosong</b></summary>
<br>

Penyebab umum: `shell_exec` dimatikan atau `PATH` milik PHP-FPM tidak memuat `/sbin`.

1. Periksa `disable_functions` di `php.ini`, dan pastikan `shell_exec` tidak ada di dalamnya.
2. Tambahkan `clear_env = no` dan `env[PATH]` seperti di bagian [Konfigurasi PHP-FPM](#konfigurasi-php-fpm), lalu `service php_fpm restart`.
3. Cek manual sebagai user web:

   ```sh
   su -m www -c 'sysctl -n hw.ncpu && swapinfo -k && zpool list'
   ```

</details>

<details>
<summary><b>Halaman error selalu menampilkan 404 (atau 500) padahal kodenya lain</b></summary>
<br>

Baris `fastcgi_param ERROR_STATUS $status;` belum ada di `location = /40x.php` atau `location = /50x.php`. Tambahkan, lalu `service nginx reload`.

</details>

<details>
<summary><b>Error dari aplikasi PHP tidak memakai halaman error ini</b></summary>
<br>

`fastcgi_intercept_errors on;` belum aktif di `location ~ \.php$`, atau kode errornya belum terdaftar di `error_page`.

</details>

<details>
<summary><b>Muncul 403 atau 502 saat membuka <code>index.php</code></b></summary>
<br>

- **403**: periksa kepemilikan file dan izin folder.

  ```sh
  chown -R www:www /usr/local/www/nginx
  find /usr/local/www/nginx -type d -exec chmod 755 {} \;
  find /usr/local/www/nginx -type f -exec chmod 644 {} \;
  ```

- **502**: PHP-FPM belum berjalan atau alamat `fastcgi_pass` tidak cocok dengan `listen`.

  ```sh
  service php_fpm status
  sockstat -l | grep php
  tail -n 30 /var/log/nginx/error.log
  ```

</details>

<details>
<summary><b>Halaman error tidak muncul saat PHP-FPM mati</b></summary>
<br>

Ini wajar: nginx tidak bisa menjalankan file PHP jika PHP-FPM tidak aktif. Gunakan [cadangan statis](#konfigurasi-nginx) agar pengunjung tetap melihat halaman yang layak.

</details>

<details>
<summary><b>Card ZFS tidak muncul</b></summary>
<br>

Kartu ini hanya tampil jika perintah `zpool` bisa dijalankan oleh user web. Lihat langkah pengecekan di bagian "Metrik tampil not available".

</details>

<details>
<summary><b>Favicon tidak berubah</b></summary>
<br>

Pastikan `favicon.svg` berada di document root, bisa dibuka lewat `http://<alamat-server>/favicon.svg`, lalu muat ulang paksa dengan <kbd>Ctrl</kbd> + <kbd>F5</kbd>. Browser menyimpan favicon cukup lama di cache.

</details>

<br>

## Pertanyaan Umum

**Apakah bisa dipakai di Linux?**
`index.php` punya cadangan yang membaca `/proc` sehingga tetap menampilkan CPU, memori, swap, dan uptime. Namun proyek ini dirancang dan dicoba di FreeBSD, jadi bagian seperti pool ZFS dan `freebsd-version` hanya tersedia di sana.

**Apakah bisa dipakai dengan Apache?**
Dashboard (`index.php`) bisa. Halaman error memerlukan kode error dari server, dan cara mengirimnya berbeda di Apache. Panduan di dokumen ini hanya mencakup nginx.

**Kenapa tidak memakai font atau library dari CDN?**
Server pengembangan sering berada di jaringan tertutup. Dengan semua aset ditanam di dalam file, halaman tetap tampil sama persis tanpa internet, dan tidak ada permintaan keluar yang tidak perlu.

**Kenapa fontnya terlihat lebih tebal dari screenshot?**
Gaya angka besar memakai bobot sangat tipis. Jika font sistem Anda tidak punya bobot tipis (misalnya hanya DejaVu Sans), browser memakai bobot normal. Pasang font seperti Inter atau Helvetica Neue di perangkat pengunjung, atau tambahkan `@font-face` sendiri.

**Apakah aman menaruh phpMyAdmin di sini?**
Tombol phpMyAdmin hanya tautan ke `/phpmyadmin`. Pengamanan phpMyAdmin tetap tanggung jawab Anda: batasi akses, gunakan HTTPS, dan jangan buka ke publik.

<br>

## Struktur File

```text
/usr/local/www/nginx/
├── index.php        # Dashboard utama
├── 40x.php          # Halaman error 4xx
├── 50x.php          # Halaman error 5xx
├── favicon.svg      # Ikon tab browser
```

<br>

## Lisensi

Proyek ini dirilis di bawah **Common Development and Distribution License 1.0 (CDDL-1.0)**, sesuai header di setiap file.

Teks lengkap lisensi: <http://www.opensource.org/licenses/cddl1.php>

<br>

## Kredit

- Arah visual (warna hangat, kartu kaca, angka besar yang tipis) terinspirasi dari desain [*Planet Clock Dashboard*](https://dribbble.com/shots/26740320-Planet-Clock-Dashboard-World-Time-Tracking-Reimagined) di Dribbble. Hak atas desain referensi tersebut milik pembuat aslinya.
- Dibuat untuk [FreeBSD](https://www.freebsd.org), [nginx](https://nginx.org), dan [PHP](https://www.php.net).

<br>

<div align="center">
<sub>Server Console · dibuat untuk server yang dirawat dengan baik.</sub>
</div>
