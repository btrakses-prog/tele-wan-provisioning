# WAN Provisioning V1.5.1

## Fitur tambahan V1.5.1

- Menu **Pengaturan Modem** berisi tiga pilihan: Konfigurasi WiFi, Restart Modem, dan Informasi Modem. Tidak ada fitur reset pabrik.
- Pembacaan WiFi mencoba TR-098 (`InternetGatewayDevice.LANDevice.1.WLANConfiguration`) dan TR-181 (`Device.WiFi.SSID` serta `Device.WiFi.AccessPoint.Security`). Profil 2.4 GHz dan 5 GHz dipilih berdasarkan band/channel yang dilaporkan perangkat; menu hanya muncul jika parameter ditemukan.
- Perintah restart memakai task GenieACS `reboot` dan selalu meminta konfirmasi.
- Informasi modem menampilkan vendor, model, serial number, firmware, uptime, serta band WiFi yang terdeteksi jika tersedia.
- Hasil pencarian ONT kini menyediakan tombol **Lihat Detail WAN** dan **Pengaturan Modem** pada kartu hasil.

**Batasan:** TR-098/TR-181 dan task `reboot` adalah mekanisme standar, tetapi dukungan parameter dapat berbeda per model dan firmware. Jika GenieACS tidak mengekspos parameter yang diperlukan atau perangkat menolak task, fitur akan menampilkan error; tidak ada jaminan semua model/firmware telah diuji fisik. Uji pada satu ONT per vendor sebelum digunakan massal.

## Fitur baru V1.5.0

- Menu konfigurasi WiFi dari detail ONT FiberHome, Huawei, dan ZTE.
- Membaca SSID1 (2.4 GHz) dan SSID5 (5 GHz) dari parameter TR-098 jika tersedia.
- Menampilkan password saat ini hanya jika GenieACS mengembalikan nilainya; jika kosong/tidak tersedia bot menyatakan password tidak dapat dibaca.
- Mengubah SSID dan password melalui alur input dan konfirmasi admin.
- Mengirim task `setParameterValues` hanya untuk parameter SSID dan password pada jaringan yang dipilih.
- Tombol verifikasi membaca ulang SSID dari GenieACS dan membandingkannya dengan nilai yang diminta.
- Pesan fitur WiFi menggunakan susunan judul, identitas ONT, informasi jaringan, dan tombol navigasi yang konsisten. Baris baru dari template lama juga dinormalisasi agar tidak tampil sebagai karakter `\n`.

## Catatan kompatibilitas WiFi

Implementasi ini menggunakan jalur TR-098 `InternetGatewayDevice.LANDevice.1.WLANConfiguration`. SSID1 dan SSID5 hanya ditawarkan jika instance dan nilai SSID tersebut tersedia pada perangkat. Parameter password menggunakan `KeyPassphrase`, atau fallback `PreSharedKey.1.PreSharedKey` bila parameter tersebut tersedia. Model yang tidak mengekspos parameter yang diperlukan akan ditolak dengan pesan yang jelas; tidak ada perubahan konfigurasi yang dikirim.

Data parameter yang disertakan bersama pekerjaan ini menunjukkan jalur SSID1/SSID5 dan KeyPassphrase pada contoh FiberHome HG6145D2, Huawei HG8145V5, dan ZTE F672Y. Perilaku task tetap perlu diuji pada modem fisik setiap model/firmware karena task GenieACS bergantung pada dukungan CWMP perangkat.

## Keamanan

- Fitur hanya dapat dijalankan oleh Telegram ID yang ada di `telegram_admin_ids`.
- Password baru tidak dimasukkan ke audit log.
- Password hanya disimpan sementara dalam state proses sampai task dikirim; setelah task dikirim state WiFi dibersihkan.
- Jangan membagikan bot token, API secret, atau password WiFi di percakapan.

## Pemeriksaan sebelum upgrade

1. Backup folder aplikasi lama, khususnya `config.php`, `data/`, dan `logs/`.
2. Upload isi arsip V1.5.0 untuk menggantikan file aplikasi. Jangan menimpa `config.php`, `data/`, atau `logs/` milik instalasi aktif.
3. Pastikan PHP cURL dan ekstensi JSON tersedia.
4. Uji `/start`, `/help`, `/fiberhome`, `/huawei`, `/zte`, `/onu`, `/newonu`, dan `/offline`.
5. Uji fitur WiFi terlebih dahulu pada satu ONT percobaan sebelum digunakan ke pelanggan.

---

# WAN Provisioning V1.5.1

## Perintah Telegram V1.4.0

- `/start` — menu utama.
- `/fiberhome` — daftar ONT FiberHome.
- `/huawei` — daftar ONT Huawei.
- `/zte` — daftar ONT ZTE.
- `/onu` — mencari ONT berdasarkan Serial Number atau PPP Username; dapat juga langsung menggunakan `/onu SN_PERANGKAT`.
- `/newonu` — mendeteksi modem baru yang belum memiliki WAN PPP.
- `/help` — menampilkan menu utama dan bantuan.

Perintah `/devices` dan `/search` sudah tidak digunakan. Tombol internal menu tetap memakai callback agar navigasi lama di dalam bot tetap berjalan.

### Daftar Commands BotFather

Kirim `/setcommands` ke `@BotFather`, pilih bot, lalu kirim:

```text
start - Menu Utama
fiberhome - Daftar ONT FiberHome
huawei - Daftar ONT Huawei
zte - Daftar ONT ZTE
onu - Cari ONT berdasarkan SN atau PPP Username
newonu - Deteksi Modem Baru
help - Bantuan
```

---

# WAN Provisioning V1.3.9

Bot Telegram untuk memantau dan mengelola konfigurasi WAN modem/ONT FiberHome dan Huawei melalui GenieACS NBI. Aplikasi menggunakan PHP dan penyimpanan JSON lokal; tidak memerlukan database MySQL.

> **Catatan kompatibilitas:** fitur FiberHome dikembangkan untuk HG6145D2. Modul Huawei PPP dikembangkan berdasarkan ekspor parameter HG8145V5; HG8245H5 diasumsikan memiliki parameter WAN yang sama dengan perbedaan ketersediaan SSID. Uji pada satu ONT tiap model/firmware terlebih dahulu.

## Riwayat V1.2.2
- Menghilangkan tombol **Kembali ke Menu Utama** dari layar pilihan tindakan setelah daftar WAN ditampilkan.
- Alur detail WAN dan pengelolaan WAN menjadi lebih ringkas; tombol navigasi utama pada menu lain tidak diubah.
- Mempertahankan perbaikan V1.2.1: WLAN yang dipakai untuk WAN Hotspot dikirim dengan `Enable=true`, nama SSID sesuai input admin, dan `BeaconType=None` (Wi-Fi terbuka).

## Pembaruan V1.3.0
- Menambahkan menu **List Huawei** untuk mendeteksi dan menampilkan ONT Huawei HG8145V5 dan HG8245H5 dari GenieACS.
- Menambahkan alur pembuatan WAN Internet PPPoE Huawei dengan input Username, Password, dan VLAN ID (1–4094).
- Target pembuatan WAN Huawei adalah `WANConnectionDevice.2.WANPPPConnection.2`; objek PPP.1 yang sudah ada dipertahankan.
- Template Huawei memakai parameter `X_HW_VLAN` dan `X_HW_SERVICELIST=INTERNET`, bukan parameter proprietary FiberHome.
- Mengaktifkan WLANConfiguration.1 jika parameter Enable dan SSID tersedia; WLANConfiguration.5 juga diaktifkan jika tersedia. Nama SSID tidak diubah.
- Deteksi TR069 memeriksa nama WAN dan ServiceList tanpa mengunci posisi WAN IP. Seluruh objek WANIPConnection tidak pernah diubah oleh proses pembuatan PPP Huawei.
- Tidak mengubah LAN binding.
- FiberHome V1.2.2 tetap tersedia dengan alur konfigurasi sebelumnya.

## 1. Fitur

### Pengelolaan modem
- Menampilkan daftar modem FiberHome dan Huawei yang didukung dari GenieACS.
- Daftar Huawei memfilter model HG8145V5 dan HG8245H5.
- Pencarian modem berdasarkan Serial Number (SN) atau PPP Username.
- Menampilkan model, SN, Device ID, PPP Username, dan redaman optik/RX Power jika tersedia.
- SN ditampilkan sebagai teks kode agar mudah disalin dari Telegram.

### Informasi dan konfigurasi WAN
- Membaca daftar WAN PPP dan WAN IP yang tersedia.
- Menampilkan detail WAN, termasuk nama, tipe objek, Enable, Connection Mode, Service List, PPP Username, NAT, VLAN, VLAN ID, dan LAN Binding jika parameter tersedia.
- Mengubah parameter WAN melalui menu Telegram dengan konfirmasi Terapkan/Batal.
- Password PPP disamarkan pada pesan bot dan data audit.
- Membuat WAN Internet baru jika WAN PPP Internet belum terdeteksi.
- Mencegah pembuatan WAN Internet duplikat berdasarkan pemeriksaan WAN yang sudah ada.
- Menyediakan tombol Periksa & Lanjutkan ketika GenieACS masih menunggu modem menghubungi ACS.
- Tombol kembali ke menu utama tidak ditampilkan pada layar pilihan tindakan WAN; menu utama tetap dapat dibuka melalui perintah `/start`.

### Keamanan dan pencatatan
- Akses bot dibatasi berdasarkan Telegram Admin ID.
- Webhook Telegram menggunakan secret token.
- Riwayat aktivitas dicatat pada `logs/audit.log`.
- Direktori `data/` dan `logs/` diblokir dari akses web melalui `.htaccess`.
- Tidak menggunakan MySQL.

## 2. Template WAN Internet standar

Template pembuatan WAN Internet pada versi ini:

| Parameter | Nilai |
|---|---|
| Nama WAN | `2_INTERNET_R_VID_200` |
| Tipe objek | `WANPPPConnection` |
| Enable | `true` |
| Connection Type | `IP_Routed` |
| Service List | `INTERNET` |
| NAT | `true` |
| VLAN Enable | `true` |
| VLAN ID | Diisi admin saat membuat WAN baru (1–4094) |
| LAN Binding | LAN Ethernet 1, WLAN Configuration 1, WLAN Configuration 5 |

Saat membuat WAN Internet baru, bot meminta PPP Username, password, lalu VLAN ID (1–4094). Nama WAN dibuat otomatis dengan format `2_INTERNET_R_VID_<VLAN>`. Pengaturan VLAN pada menu edit WAN yang sudah ada tetap manual seperti sebelumnya.

Daftar modem menampilkan status PPPoE dan IP Address. Status ONLINE ditentukan dari IP PPPoE valid yang terbaca (atau status `Connected` bila IP belum terbaca); pembacaan dilakukan setiap kali admin membuka daftar modem.
| PPP Username | Diisi admin melalui Telegram |
| PPP Password | Diisi admin melalui Telegram |

LAN Binding menggunakan path parameter:

```text
InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1
InternetGatewayDevice.LANDevice.1.WLANConfiguration.5
```

### Struktur objek WAN yang dituju

Pada perangkat yang menggunakan struktur seperti hasil pengujian HG6145D2, WAN Internet kedua dibuat di dalam `WANConnectionDevice.2`:

```text
InternetGatewayDevice
└── WANDevice.1
    ├── WANConnectionDevice.1
    │   └── WAN IP / TR-069 / VoIP (dipertahankan)
    └── WANConnectionDevice.2
        └── WANPPPConnection.1
            └── WAN Internet
```

**Penting:** nomor instance `WANPPPConnection` dimulai ulang di setiap `WANConnectionDevice`. Karena itu, `WANConnectionDevice.2.WANPPPConnection.1` merupakan PPP pertama di WCD.2, sekaligus WAN PPP kedua pada susunan perangkat yang digunakan dalam pengujian. Jangan menyamakan ini dengan `WANConnectionDevice.1.WANPPPConnection.1`.

Jika `WANConnectionDevice.2` belum tersedia, bot meminta GenieACS membuat objek induk tersebut terlebih dahulu. Setelah modem menghubungi ACS dan WCD.2 terdeteksi, bot melanjutkan pembuatan WAN PPP di dalamnya. Proses dibatalkan jika ditemukan objek PPP yang sudah ada di WCD.2 agar konfigurasi yang ada tidak tertimpa.


## 3. WAN Internet PPPoE Huawei

Menu **List Huawei** mendeteksi modem Huawei HG8145V5 dan HG8245H5. Dari detail modem, pilih **Buat WAN Internet (PPPoE)**, lalu masukkan Username PPPoE, Password PPPoE, dan VLAN ID.

### Target objek

```text
InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2
└── WANPPPConnection.1  (objek yang sudah ada, dipertahankan)
└── WANPPPConnection.2  (objek baru untuk WAN PPP 2)
```

Bot hanya melanjutkan jika WCD.2 dan PPP.1 tersedia serta PPP.2 belum ada. Jika kondisi berbeda, proses dihentikan agar tidak membuat objek pada slot yang salah atau menimpa konfigurasi lama.

### Parameter Huawei yang dikirim

| Parameter | Nilai |
|---|---|
| `Enable` | `true` |
| `Name` | `2_INTERNET_R_VID_<VLAN>` |
| `ConnectionType` | `IP_Routed` |
| `Username` / `Password` | Diisi admin |
| `NATEnabled` | `true` |
| `X_HW_VLAN` | VLAN ID dari admin |
| `X_HW_SERVICELIST` | `INTERNET` |
| `X_HW_IPv4Enable` | `true` |
| `X_HW_IPv6Enable` | `false` |
| `ConnectionTrigger` | `AlwaysOn` |
| `X_HW_DefaultPri` | `0` |

Parameter tersebut diperiksa pada ekspor HG8145V5 yang disediakan. Tidak digunakan `X_FH_*`, `VLANEnable`, atau LAN Binding FiberHome.

### Wi-Fi dan TR069

- WLANConfiguration.1 diaktifkan jika parameter `Enable` dan `SSID` tersedia.
- WLANConfiguration.5 ikut diaktifkan jika parameter `Enable` dan `SSID` tersedia. Jika tidak ada, hanya WLANConfiguration.1 yang diproses.
- Nama SSID dan keamanan Wi-Fi tidak diubah.
- Deteksi TR069 mencari teks `TR069` pada Name atau ServiceList. Posisi WAN IP tidak diasumsikan.
- Semua objek `WANIPConnection` dibiarkan apa adanya, baik TR069 berhasil dikenali maupun tidak. Proses PPP hanya menambah/mengubah parameter pada objek PPP.2 yang baru.
- LAN binding tidak diubah.

**Catatan model:** ekspor parameter yang tersedia saat pengembangan berasal dari HG8145V5. Untuk HG8245H5, modul memakai parameter WAN yang sama sesuai keterangan pengguna; WLANConfiguration.5 hanya akan diproses bila benar-benar ditemukan di GenieACS.

## 4. Template WAN Hotspot (bridged)

Bot memilih slot secara otomatis berdasarkan keberadaan WAN PPP 2:

- **WAN PPP 2 belum ada:** Hotspot dibuat sebagai **WAN IP 2** pada `WANConnectionDevice.2`, dengan binding LAN1 + WLAN1 + WLAN5. Admin diminta mengisi SSID WLAN1 dan WLAN5.
- **WAN PPP 2 sudah ada:** Hotspot dibuat sebagai **WAN IP 3** pada `WANConnectionDevice.3`, dengan binding LAN2 + WLAN2 + WLAN6. Admin diminta mengisi SSID WLAN2 dan WLAN6.

Template WAN Hotspot:

| Parameter | Nilai |
|---|---|
| Enable | `true` |
| Name | `2_INTERNET_B_VID_<VLAN>` atau `3_INTERNET_B_VID_<VLAN>` |
| Connection Type | `IP_Bridged` |
| Service List | `Other` |
| Addressing Type | `DHCP` |
| IP / Subnet / Gateway / DNS | Kosong |
| NAT Enabled | `false` |
| VLAN Enable | `true` |
| VLAN ID | Diisi admin (1–4094) |
| Beacon Type pada WLAN yang dipakai | `None` (Wi-Fi terbuka tanpa password) |

Bot meminta VLAN ID dan dua nama SSID, lalu menampilkan ringkasan konfirmasi sebelum mengirim task ke GenieACS. Jika objek WCD atau WAN IP belum tersedia, bot meminta GenieACS menambahkannya dan menyediakan tombol **Periksa & Lanjutkan** setelah ONT melakukan Inform. Jika slot WAN IP tujuan sudah berisi objek, proses dihentikan untuk mencegah konfigurasi lama tertimpa. WAN pada WCD.1 tidak disentuh.

Parameter template ini dicocokkan dengan parameter writable dari ekspor GenieACS HG6145D2 yang tersedia pada saat pengembangan. Perbedaan firmware tetap perlu diuji pada satu ONT terlebih dahulu.

## 5. Persyaratan instalasi

### Server
- Linux server (disarankan Ubuntu/Debian) dengan Apache2 atau web server PHP lain.
- PHP **8.0 atau lebih baru**.
- Ekstensi PHP:
  - `curl`
  - `json`
- Koneksi server ke GenieACS NBI.
- HTTPS publik untuk webhook Telegram.
- Akses tulis user web server ke folder `data/` dan `logs/`.

### Layanan eksternal
- GenieACS berjalan dan dapat diakses dari server aplikasi, umumnya melalui NBI port `7557`.
- Telegram Bot Token dari BotFather.
- Telegram Admin ID untuk setiap admin yang diizinkan menggunakan bot.
- Modem FiberHome sudah terdaftar di GenieACS dan dapat melakukan Inform/connection ke ACS.

Aplikasi ini **tidak membutuhkan MySQL**.

## 6. Instalasi pada Ubuntu/Debian + Apache2

### Langkah 1 — Pasang kebutuhan server

```bash
sudo apt update
sudo apt install apache2 php php-cli php-curl php-json unzip -y
sudo systemctl enable --now apache2
```

Periksa versi PHP dan ekstensi:

```bash
php -v
php -m | grep -E 'curl|json'
```

Pastikan `curl` dan `json` tampil pada hasil pemeriksaan.

### Langkah 2 — Upload dan ekstrak aplikasi

Upload ZIP ke server, lalu ekstrak ke webroot. Contoh:

```bash
sudo mkdir -p /var/www/html/wan-provisioning
sudo unzip WAN-Provisioning-V1.3.0.zip -d /var/www/html/wan-provisioning
```

Sesuaikan nama file ZIP dan lokasi webroot jika berbeda.

### Langkah 3 — Buat konfigurasi

Masuk ke folder aplikasi:

```bash
cd /var/www/html/wan-provisioning
sudo cp config.example.php config.php
sudo nano config.php
```

Isi konfigurasi sesuai server Anda:

```php
return [
  'genieacs_url' => 'http://127.0.0.1:7557',
  'genieacs_user' => '',
  'genieacs_pass' => '',
  'telegram_token' => 'TOKEN_BOT_DARI_BOTFATHER',
  'telegram_admin_ids' => [123456789],
  'telegram_webhook_secret' => 'GANTI_DENGAN_SECRET_ACAK_PANJANG',
  'device_manufacturer' => 'FiberHome',
  'device_model' => 'HG6145D2',
  'request_timeout' => 15,
  'verify_after_seconds' => 8,
];
```

Keterangan:
- `genieacs_url`: alamat GenieACS NBI yang dapat dijangkau dari server aplikasi.
- `genieacs_user` dan `genieacs_pass`: isi jika NBI menggunakan autentikasi; jika tidak, biarkan kosong.
- `telegram_token`: token bot dari BotFather.
- `telegram_admin_ids`: daftar ID numerik Telegram admin, contoh `[123456789, 987654321]`.
- `telegram_webhook_secret`: secret acak untuk memvalidasi webhook. Gunakan nilai acak yang panjang dan simpan rahasia.
- `device_manufacturer` dan `device_model`: nilai konfigurasi lama yang tetap disertakan untuk kompatibilitas; filter FiberHome dan Huawei kini dilakukan berdasarkan identitas perangkat.

Jangan mengirim atau membagikan `config.php`, token bot, password GenieACS, maupun webhook secret kepada orang lain.

### Langkah 4 — Atur izin folder

Pada instalasi Apache Ubuntu/Debian, user web server umumnya `www-data`:

```bash
sudo chown -R www-data:www-data data logs
sudo chmod 750 data logs
sudo chmod 640 config.php
```

Jika aplikasi di-update, pastikan izin tulis untuk `data/` dan `logs/` tetap benar. Jangan memberi izin `777` kecuali benar-benar diperlukan untuk diagnosis sementara.

### Langkah 5 — Aktifkan HTTPS

Webhook Telegram membutuhkan URL HTTPS yang dapat dijangkau dari internet. Pasang sertifikat SSL pada domain Anda (misalnya menggunakan Let's Encrypt/Certbot) dan pastikan endpoint aplikasi dapat dibuka melalui:

```text
https://DOMAIN-ANDA/fiberhome-wan-manager/telegram.php
```

Sesuaikan domain dan subfolder dengan instalasi Anda.

### Langkah 6 — Daftarkan webhook Telegram

Gunakan URL webhook aplikasi dan secret yang sama dengan `telegram_webhook_secret` di `config.php`.

Contoh perintah (ganti placeholder; jangan memasukkan token asli ke dokumentasi atau membagikan URL yang mengandung token):

```text
https://api.telegram.org/bot<TOKEN_BOT>/setWebhook?url=https://DOMAIN-ANDA/fiberhome-wan-manager/telegram.php&secret_token=<WEBHOOK_SECRET>
```

Buka URL tersebut melalui browser atau gunakan `curl` dari terminal. Respons Telegram seharusnya menunjukkan `"ok": true`.

Periksa status webhook:

```text
https://api.telegram.org/bot<TOKEN_BOT>/getWebhookInfo
```

Pastikan URL yang terdaftar benar dan tidak ada error pengiriman webhook.

### Langkah 7 — Uji aplikasi

1. Buka `https://DOMAIN-ANDA/fiberhome-wan-manager/` untuk melihat halaman status aplikasi.
2. Buka bot Telegram, lalu kirim `/start`.
3. Pastikan menu utama tampil.
4. Coba **Daftar modem**. Saat menu ini dibuka, bot otomatis membaca status PPPoE dan IP Address dari data GenieACS terbaru; tidak ada polling berkala di latar belakang.
5. Coba **Cari modem** dengan SN atau PPP Username.
6. Pilih modem dan periksa daftar/detail WAN.

## 7. Cara menggunakan bot Telegram

### Menu utama
- **Daftar modem**: menampilkan modem FiberHome yang ditemukan.
- **Cari modem**: mencari modem berdasarkan SN atau PPP Username.
- **Bantuan**: menampilkan petunjuk singkat.

### Mencari modem
1. Tekan **Cari modem**.
2. Kirim Serial Number atau PPP Username.
3. Bot menampilkan model, SN, Device ID, PPP Username, dan redaman optik jika tersedia.
4. Tekan tombol **Lihat Detail WAN** untuk membuka WAN modem.

### Melihat dan mengubah WAN
1. Pilih modem.
2. Pilih WAN yang ingin diperiksa.
3. Buka detail WAN.
4. Tekan **Ubah konfigurasi**.
5. Pilih parameter yang tersedia dan masukkan nilai baru.
6. Periksa ringkasan konfirmasi.
7. Tekan **Terapkan** untuk mengirim task atau **Batal** untuk membatalkan.

Perubahan baru benar-benar berlaku setelah modem menerima dan menyelesaikan task GenieACS. Periksa kembali nilai parameter setelah task selesai.

### Membuat WAN Internet baru
1. Pilih modem FiberHome.
2. Jika WAN PPP Internet belum terdeteksi, tekan **Buat WAN Internet**.
3. Masukkan PPP Username dan PPP Password.
4. Periksa ringkasan template, lalu tekan **Buat WAN**.
5. Jika WCD.2 belum ada, bot mengirim task `addObject` untuk membuat `WANConnectionDevice.2`.
6. Setelah modem melakukan Inform dan objek WCD.2 terlihat di GenieACS, tekan **Periksa & Lanjutkan**.
7. Bot meminta GenieACS membuat `WANPPPConnection.1` di dalam WCD.2.
8. Setelah objek PPP tersebut terlihat di GenieACS, bot mengirim parameter template WAN Internet.
9. Periksa kembali detail WAN dan status koneksi pada modem/GenieACS.

**Jangan menekan proses pembuatan berulang kali ketika task sebelumnya masih berjalan.** Tunggu modem melakukan Inform, lalu gunakan tombol pemeriksaan yang disediakan bot.

## 8. Troubleshooting

| Gejala | Pemeriksaan |
|---|---|
| Bot tidak merespons `/start` | Periksa webhook Telegram, URL HTTPS, token bot, secret webhook, dan log Apache. |
| `config.php` belum dibuat | Salin `config.example.php` menjadi `config.php`, lalu isi nilainya. |
| GenieACS tidak dapat dihubungi | Periksa `genieacs_url`, konektivitas server, port NBI, firewall, dan autentikasi. |
| Daftar modem kosong | Pastikan perangkat sudah terdaftar di GenieACS dan identitas Manufacturer/ProductClass sesuai filter. |
| PPP Username atau redaman kosong | Periksa parameter yang dilaporkan modem ke GenieACS; redaman hanya tampil jika parameter RX Power tersedia. |
| Task masih menunggu | Pastikan modem online, dapat menjangkau ACS, dan melakukan Inform. Gunakan **Periksa & Lanjutkan** setelah objek muncul. |
| WCD.2 belum terdeteksi | Periksa hasil task `addObject` di GenieACS dan tunggu modem melaporkan objek baru. |
| Pembuatan WAN dibatalkan karena objek PPP sudah ada | Periksa struktur `WANConnectionDevice.2` di GenieACS sebelum mencoba lagi. Jangan hapus objek tanpa memastikan fungsinya. |
| Konfigurasi tidak aktif setelah task | Baca ulang parameter WAN, periksa status task GenieACS, VLAN, kredensial PPP, dan koneksi fisik/provider. |

Log aplikasi berada di:

```text
logs/audit.log
```

Log Apache Ubuntu/Debian umumnya berada di:

```text
/var/log/apache2/error.log
```

## 9. Backup, update, dan keamanan

### Backup sebelum update
Simpan salinan:
- `config.php`
- Folder `data/`
- Folder `logs/`

### Update versi aplikasi
1. Backup tiga item di atas.
2. Ekstrak ZIP versi baru.
3. Timpa file aplikasi lama dengan file versi baru.
4. Jangan menimpa `config.php`, `data/`, dan `logs/`.
5. Pastikan kepemilikan/izin folder tetap benar.
6. Uji `/start` dan fungsi pencarian modem.

### Keamanan
- Gunakan HTTPS untuk webhook.
- Batasi `telegram_admin_ids` hanya untuk admin yang dipercaya.
- Jangan membagikan token Telegram, kredensial GenieACS, atau webhook secret.
- Batasi akses server dan backup.
- Pastikan `data/` dan `logs/` tidak dapat diakses langsung dari web.
- Password PPP yang menunggu konfirmasi disimpan sementara dalam penyimpanan state aplikasi. Lindungi folder `data/` dan jangan membagikan isinya.
- Setelah token atau secret terlanjur bocor, segera ganti token/secret dan perbarui konfigurasi serta webhook.

## 10. Batasan dan catatan pengujian

- Fokus versi ini adalah FiberHome HG6145D2; kompatibilitas model/firmware FiberHome lain harus diverifikasi.
- Parameter proprietary FiberHome seperti `X_FH_ServiceList` dan `X_FH_LanInterface` dapat berbeda antar firmware.
- Pembuatan WAN menggunakan task GenieACS. HTTP sukses atau task masuk antrean **bukan bukti** bahwa modem sudah menerapkan konfigurasi.
- Setelah membuat WAN, selalu periksa ulang parameter di GenieACS dan lakukan pengujian koneksi PPPoE.
- Sebelum digunakan massal, uji pada ONT cadangan atau unit yang dapat dipulihkan secara lokal.
- Versi ini tidak menggantikan backup konfigurasi ONT maupun prosedur pemulihan perangkat.

---
**WAN Provisioning V1.1.9**  
Telegram Bot · GenieACS NBI · FiberHome + Huawei · PHP 8+ · JSON storage · Tanpa MySQL


## Pembaruan V1.1.8

- Daftar modem ditampilkan maksimal 25 modem dalam satu pesan Telegram.
- Jika jumlah modem lebih dari 25, gunakan tombol **TAMPILKAN SELANJUTNYA** untuk melihat kelompok berikutnya sampai seluruh modem tampil.
- Tombol **HALAMAN SEBELUMNYA** tersedia untuk kembali ke kelompok sebelumnya.
- Data daftar disimpan sebagai snapshot ketika menu Daftar Modem dibuka. Tombol halaman berikutnya menggunakan snapshot tersebut agar tidak melakukan pembacaan ulang ke GenieACS.
- Tombol **MUAT ULANG DAFTAR** mengambil data terbaru dari GenieACS.


## Pembaruan V1.1.9

- Judul menu awal Telegram diubah menjadi **WAN Provisioning**.
- Menu **List FiberHome** digunakan untuk menampilkan daftar modem FiberHome.
- Menu **Cari Modem** digunakan untuk pencarian manual berdasarkan Serial Number atau PPP Username.
- Setelah admin membuka daftar WAN pada modem, tersedia pilihan **Buat WAN Hotspot (WAN IP 2)**.
- Tombol WAN Hotspot saat ini merupakan kerangka menu saja. Proses konfigurasi belum dijalankan sampai parameter WAN Hotspot ditentukan.
- Fitur WAN Internet PPPoE, VLAN ID manual, status/IP PPPoE otomatis, dan pagination 25 modem tetap dipertahankan.


## Perbaikan Huawei PPP — V1.3.2
Profil PPP Huawei yang sudah dibuat sebelumnya dapat memiliki nilai default seperti
`Enable=true`, `ConnectionType=IP_Routed`, `NATEnabled=false`, VLAN `1`, dan
nama profil awal. Nilai default tersebut tidak lagi dianggap sebagai PPP yang
sudah dikonfigurasi. Bot menggunakan ulang objek hanya jika parameter `Username`
dan `Password` sama-sama tersedia dan kosong. Jika salah satu berisi nilai,
objek dianggap telah digunakan dan bot mencari instance PPP berikutnya.
Jika data Username/Password belum lengkap dari GenieACS, bot tidak akan
menganggap objek kosong sehingga mencegah penimpaan yang tidak aman.


## Huawei WAN Hotspot — V1.3.3
Menu Huawei menyediakan pembuatan WAN Hotspot (Bridge). Bot hanya melanjutkan
jika tidak menemukan WAN PPP yang sudah dikonfigurasi (Username atau Password
PPP terisi). Profil PPP bawaan yang Username dan Password-nya kosong tidak
dianggap sebagai koneksi PPP aktif/terkonfigurasi.

Konfigurasi Hotspot Huawei:
- `Enable=true`
- `ConnectionType=IP_Bridged`
- `AddressingType=DHCP`
- `NATEnabled=false`
- `X_HW_SERVICELIST=OTHER`
- `X_HW_VLAN=<VLAN admin>`
- `X_HW_LANBIND.Lan1Enable` s.d. `Lan4Enable` = `0`
- `X_HW_LANBIND.SSID1Enable=1`
- `X_HW_LANBIND.SSID5Enable=1` jika parameter SSID5 tersedia
- Parameter `Name` sengaja tidak dikirim; nama WAN mengikuti otomatisasi modem.

Bot mencari objek WAN IP kosong yang aman digunakan. Objek yang mengandung
indikator `TR069` pada Name atau ServiceList dilindungi; objek WAN IP lain yang
sudah memiliki Name/ServiceList/IP juga tidak ditimpa. Jika tidak ada objek
kosong, bot menambahkan instance WANIPConnection di WCD.2. Bot tidak mengubah
LAN binding pada level interface dan tidak mengubah objek WAN PPP.


## Perbaikan Huawei Hotspot — V1.3.4
Bot tidak lagi menebak nomor instance WANIPConnection sebelum ONT melaporkan hasil AddObject.
Bot menyimpan daftar instance yang sudah ada di WCD.2, lalu setelah Inform mendeteksi
tepat satu instance baru. Nilai default firmware seperti Name atau ServiceList tidak
lagi salah dianggap sebagai WAN lama yang sedang digunakan. Objek baru tetap diperiksa
agar tidak terindikasi TR069. Pencarian dan penambahan Hotspot dibatasi ke WCD.2.
Pesan konfirmasi Telegram kini menggunakan baris baru yang benar, bukan teks literal \\n.


## Dukungan ZTE PPPoE — V1.3.5
Mendukung deteksi perangkat ZTE (termasuk F670L, F609, F670, F660, F672Y) dan menu List ZTE. Konfigurasi PPP memakai parameter GenieACS ZTE yang terverifikasi dari ekspor F672Y:
- `Enable=true`, `Name=INTERNET`, `ConnectionType=IP_Routed`
- `X_ZTE-COM_ServiceList=INTERNET`
- `Username`, `Password` dari admin
- `NATEnabled=true`
- `X_ZTE-COM_VLANEnable=true`, `X_ZTE-COM_VLANID=<VLAN>`

PPP berada di `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection`. Bot memakai objek yang Username dan Password-nya sama-sama kosong; jika tidak ada, AddObject pada tabel WANPPPConnection dan menunggu instance baru terdeteksi setelah Inform. WAN IP tidak dihapus. Untuk setiap WAN IP dengan Service List `INTERNET_TR069_VoIP`, bot menghapus token `INTERNET` dan mempertahankan `TR069_VoIP`. Fitur Hotspot ZTE belum disediakan.


## Cek Modem Baru — V1.3.8
Menu **Cek Modem Baru** memeriksa daftar perangkat GenieACS berdasarkan `_lastInform`.
Perangkat ditampilkan jika melakukan Inform dalam 30 menit terakhir (default), Device ID belum tercatat pada registry lokal WAN Provisioning (`data/state.json`), dan belum memiliki Username pada objek WANPPPConnection.
Modem yang sudah memiliki WAN PPP dengan Username terisi tidak ditampilkan. Instance WAN PPP yang masih kosong tetap dianggap belum dikonfigurasi. Saat pertama kali ditampilkan, perangkat yang memenuhi kriteria langsung dicatat sehingga tidak muncul berulang saat tombol refresh ditekan. Daftar panjang otomatis dipecah menjadi beberapa pesan agar tidak melewati batas panjang pesan Telegram. Pengaturan jendela waktu dapat ditambahkan
ke `config.php` sebagai `new_modem_window_minutes` (default 30). Registry lokal
tidak menghapus atau mengubah data perangkat di GenieACS.


## Registrasi Pelanggan PHPNuxBill (tambahan integrasi)

Versi ini menambahkan menu Telegram **Registrasi Pelanggan** yang terpisah dari menu ONT.

### Konfigurasi
Tambahkan/isi tiga pengaturan berikut di `config.php` (jangan isi di `config.example.php` untuk produksi):
```php
'billing_base_url' => 'https://taglis.tlfiber.my.id',
'billing_api_secret' => 'SECRET_BRIDGE_ANDA',
'billing_payment_method' => 'Cash',
```
`billing_api_secret` harus sama dengan `api_secret` di file `wanprovisioning.config.php` pada server PHPNuxBill. Jangan kirim secret ke pihak lain.

### Alur
1. Admin membuka **Registrasi Pelanggan**.
2. Bot meminta nama lengkap, nomor HP, alamat, email (boleh `/skip`), dan password PPPoE.
3. Bot mengambil paket PPPoE aktif dari PHPNuxBill.
4. Admin memilih paket dan mengonfirmasi.
5. Bot mengirim data ke PHPNuxBill Bridge. Username Billing dan PPPoE dibentuk dari nama lengkap dengan spasi menjadi underscore.
6. Setelah berhasil, bot menampilkan ID pelanggan dan pintasan untuk melanjutkan pemilihan ONT.

Masa aktif ditampilkan sebagai **31 hari** sesuai ketentuan instalasi. Password akun Billing dibuat acak oleh bot dan tidak ditampilkan; password PPPoE diisi admin.

### Endpoint yang digunakan
- GET `/?_route=plugin/wanprovisioning_api/plans`
- POST `/?_route=plugin/wanprovisioning_api/register`

Header autentikasi: `X-WANPROV-KEY`.


## Pembaruan V1.3.9
- Menambahkan menu terpisah Registrasi Pelanggan yang terhubung ke PHPNuxBill Bridge.
- Mengambil daftar paket PPPoE aktif secara dinamis dari PHPNuxBill.
- Membentuk Username Billing dan PPPoE dari nama lengkap dengan spasi menjadi underscore.
- Menambahkan konfirmasi sebelum pembuatan pelanggan dan perlindungan penanganan aktivasi paket yang gagal.
- Mempertahankan menu dan alur provisioning modem dari V1.3.8.


## Perintah offline V1.4.2
- `/offline` menampilkan daftar seluruh ONT yang terdeteksi dengan status PPP OFFLINE, termasuk model, serial number, dan redaman optik.
- Menu utama menyediakan tombol **List Modem Offline**.


## WAN Provisioning V1.4.3 — Pilihan Vendor Setelah Registrasi

Setelah pelanggan berhasil dibuat dan paket aktif di PHPNuxBill, bot menampilkan pilihan untuk melanjutkan pemilihan ONT dari daftar FiberHome, Huawei, atau ZTE, serta tombol Cek Modem Baru dan Menu Utama. Callback menggunakan handler daftar perangkat vendor yang sudah tersedia.


## Perubahan V1.5.2 — WiFi SSID1/SSID5

- Menu konfigurasi WiFi hanya menampilkan `WLANConfiguration.1` (SSID1/2.4 GHz) dan `WLANConfiguration.5` (SSID5/5 GHz) jika parameter masing-masing tersedia.
- Instance lain, termasuk `WLANConfiguration.8`, diabaikan. Tidak dilakukan pemetaan otomatis berdasarkan tebakan frekuensi.
- Saat memilih ubah WiFi, operator dapat memilih untuk mengganti nama SSID atau mempertahankan nama saat ini.
- Jika nama dipertahankan, bot langsung meminta password baru; hanya parameter password yang dikirim ke GenieACS.
- Jika SSID5 tidak tersedia, SSID5 tidak ditampilkan dan tidak ada task untuk SSID5.
- Fitur lain dipertahankan dari V1.5.1.

## V1.5.3 - Wi-Fi open network option
- Added an explicit `Nonaktifkan Password` action for Wi-Fi SSID1 and SSID5 only.
- Requires a confirmation because the selected Wi-Fi network becomes open and can be used by nearby devices without a password.
- TR-098 uses `BeaconType=None`; TR-181 fallback uses `Device.WiFi.AccessPoint.{1|5}.Security.Mode=None`.
- Device/firmware must expose the relevant parameter as writable. Check the GenieACS task result and verify on the ONT; not every vendor firmware accepts the same parameter.
