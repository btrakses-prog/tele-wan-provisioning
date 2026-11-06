# PANDUAN INSTALASI WAN PROVISIONING V1.4.2

Telegram Bot · GenieACS · PHPNuxBill · FiberHome · Huawei · ZTE

## 1. Gambaran sistem

WAN Provisioning adalah aplikasi berbasis PHP yang menghubungkan Telegram Bot dengan GenieACS untuk memantau dan mengelola ONT.

Alur komunikasi:

1. Telegram mengirim perintah ke webhook.
2. Server WAN Provisioning memproses perintah.
3. WAN Provisioning mengambil data ONT dari GenieACS melalui NBI.
4. Jika diperlukan, WAN Provisioning mengirim task konfigurasi ke GenieACS.
5. Untuk registrasi pelanggan, WAN Provisioning berkomunikasi dengan PHPNuxBill melalui Bridge API.

**Catatan:** Container WAN Provisioning dan PHPNuxBill boleh berada di container berbeda. Keduanya harus bisa berkomunikasi melalui HTTP/HTTPS.

## 2. Persyaratan

Siapkan:

- Server Linux Ubuntu/Debian.
- PHP 8.0 atau lebih baru.
- Apache2.
- Ekstensi PHP CURL dan JSON.
- GenieACS NBI aktif, umumnya port 7557.
- Domain atau subdomain dengan HTTPS publik untuk webhook Telegram.
- Telegram Bot Token dari BotFather.
- Telegram Admin ID.
- File ZIP WAN-Provisioning-V1.4.2-List-Offline.zip.

Aplikasi WAN Provisioning menggunakan penyimpanan JSON lokal dan tidak membutuhkan MySQL.

## 3. Instalasi Apache dan PHP

Jalankan pada terminal server WAN Provisioning:

```bash
sudo apt update
sudo apt install apache2 php php-cli php-curl php-json unzip -y
sudo systemctl enable --now apache2
```

Periksa versi PHP:

```bash
php -v
```

Periksa ekstensi:

```bash
php -m | grep -E 'curl|json'
```

Pastikan ekstensi curl dan json muncul.

## 4. Upload dan ekstrak aplikasi

Upload ZIP V1.4.2 ke server, kemudian jalankan:

```bash
sudo mkdir -p /var/www/html/wan-provisioning
```

Ekstrak:

```bash
sudo unzip WAN-Provisioning-V1.4.2-List-Offline.zip -d /var/www/html/wan-provisioning
```

Masuk ke direktori:

```bash
cd /var/www/html/wan-provisioning
```

Pastikan file berikut tersedia:

- index.php
- telegram.php
- config.example.php
- lib/app.php
- folder data/
- folder logs/

## 5. Membuat konfigurasi aplikasi

Salin konfigurasi contoh:

```bash
sudo cp config.example.php config.php
```

Buka file:

```bash
sudo nano config.php
```

Isi sesuai server masing-masing:

```php
<?php
return [
  'genieacs_url' => 'http://IP-GENIEACS:7557',
  'genieacs_user' => '',
  'genieacs_pass' => '',

  'telegram_token' => 'TOKEN_BOT_TELEGRAM',
  'telegram_admin_ids' => [123456789],
  'telegram_webhook_secret' => 'SECRET_ACAK_PANJANG',

  'device_manufacturer' => 'FiberHome',
  'device_model' => 'HG6145D2',

  'request_timeout' => 15,
  'verify_after_seconds' => 8,

  'billing_base_url' => 'https://DOMAIN-BILLING',
  'billing_api_secret' => 'SECRET_BRIDGE_PHPNUXBILL',
  'billing_payment_method' => 'Cash',
];
```

Keterangan:

| Parameter | Keterangan |
|---|---|
| genieacs_url | URL GenieACS NBI yang dapat dijangkau dari container aplikasi |
| genieacs_user | Username autentikasi NBI jika digunakan |
| genieacs_pass | Password autentikasi NBI jika digunakan |
| telegram_token | Token dari BotFather |
| telegram_admin_ids | ID Telegram admin yang diizinkan |
| telegram_webhook_secret | Secret untuk validasi webhook |
| billing_base_url | Alamat website PHPNuxBill |
| billing_api_secret | Secret yang sama dengan konfigurasi Bridge PHPNuxBill |
| billing_payment_method | Metode pembayaran default |

Jika belum menggunakan PHPNuxBill Bridge, bagian billing mengikuti konfigurasi contoh dan tidak perlu diisi dengan kredensial palsu.

**Penting:** Jangan membagikan file config.php, token Telegram, password GenieACS, atau secret API kepada orang lain.

## 6. Mengatur hak akses folder

Jalankan:

```bash
sudo chown -R www-data:www-data /var/www/html/wan-provisioning
```

Atur izin folder:

```bash
sudo chmod 750 /var/www/html/wan-provisioning/data
sudo chmod 750 /var/www/html/wan-provisioning/logs
sudo chmod 640 /var/www/html/wan-provisioning/config.php
```

Jangan menggunakan chmod 777 sebagai pengaturan permanen.

## 7. Mengaktifkan HTTPS

Webhook Telegram membutuhkan alamat HTTPS publik.

Contoh alamat aplikasi:

```text
https://bot.domainanda.com/wan-provisioning/telegram.php
```

Pastikan:

- Domain mengarah ke server WAN Provisioning.
- Sertifikat SSL aktif.
- Port HTTPS 443 dapat diakses dari internet.
- URL webhook dapat dijangkau oleh server Telegram.

Jika menggunakan Cloudflare Tunnel, pastikan hostname diarahkan ke service Apache pada container WAN Provisioning.

## 8. Mengatur Webhook Telegram

Buka BotFather dan buat bot melalui `/newbot` jika belum tersedia.

Salin token bot dan masukkan ke config.php.

Buat secret acak untuk webhook. Contoh:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Masukkan hasilnya ke `telegram_webhook_secret`.

Daftarkan webhook menggunakan terminal:

```bash
curl -X POST "https://api.telegram.org/botTOKEN_BOT/setWebhook" \
-d "url=https://bot.domainanda.com/wan-provisioning/telegram.php" \
-d "secret_token=SECRET_WEBHOOK"
```

Ganti placeholder dengan token dan URL milik sendiri.

Periksa status webhook:

```text
https://api.telegram.org/botTOKEN_BOT/getWebhookInfo
```

Pastikan URL yang terdaftar benar dan tidak ada error pengiriman webhook.

Jangan membagikan URL yang mengandung token bot.

## 9. Mengatur Commands BotFather

Buka Telegram, cari `@BotFather`, kemudian kirim:

```text
/setcommands
```

Pilih bot WAN Provisioning, lalu kirim daftar berikut:

```text
start - Menu Utama
fiberhome - Daftar ONT FiberHome
huawei - Daftar ONT Huawei
zte - Daftar ONT ZTE
onu - Cari ONT berdasarkan SN atau PPP Username
newonu - Deteksi Modem Baru
offline - Daftar Modem Offline
help - Bantuan
```

Daftar perintah ini harus sesuai dengan fungsi yang tersedia pada aplikasi.

## 10. Menguji koneksi GenieACS

Buka alamat berikut dari browser atau terminal server aplikasi:

```text
http://IP-GENIEACS:7557/devices
```

Jika NBI memakai autentikasi, gunakan kredensial yang sesuai.

Pastikan server WAN Provisioning dapat mengakses GenieACS.

Jika GenieACS dan WAN Provisioning berada di container berbeda, gunakan alamat IP atau hostname jaringan Docker yang dapat dijangkau dari container WAN Provisioning. Jangan menggunakan 127.0.0.1 untuk menunjuk container lain.

## 11. Mengintegrasikan PHPNuxBill Bridge

Bagian ini diperlukan jika ingin menggunakan fitur Registrasi Pelanggan.

Pada server PHPNuxBill:

1. Upload file `wanprovisioning_api.php` ke direktori `system/plugin/`.
2. Upload `wanprovisioning.config.example.php` ke direktori utama PHPNuxBill.
3. Salin menjadi `wanprovisioning.config.php`.
4. Edit file konfigurasi tersebut.
5. Aktifkan Bridge dan masukkan secret API.

Contoh konfigurasi:

```php
return [
    'enabled' => true,
    'api_secret' => 'SECRET_ACAK_MINIMAL_64_HEX',
    'admin_id' => 1,
    'default_payment_method' => 'Cash',
];
```

Buat secret baru:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Masukkan secret yang sama pada:

- PHPNuxBill: `wanprovisioning.config.php` → `api_secret`.
- WAN Provisioning: `config.php` → `billing_api_secret`.

Gunakan URL billing tanpa menambahkan `.php` pada route API.

Contoh pemeriksaan paket:

```text
https://DOMAIN-BILLING/?_route=plugin/wanprovisioning_api/plans
```

Request harus menyertakan header:

```text
X-WANPROV-KEY: SECRET_API
```

Jika berhasil, API mengembalikan daftar paket dalam format JSON.

**Penting:** Jangan mengulang registrasi pelanggan secara sembarangan jika API mengembalikan status gagal setelah pelanggan dibuat. Periksa data billing terlebih dahulu untuk menghindari akun ganda.

## 12. Menguji bot

Buka Telegram dan kirim:

```text
/start
```

Pastikan menu utama tampil.

Uji perintah berikut:

| Perintah | Hasil yang diharapkan |
|---|---|
| /start | Menu utama |
| /fiberhome | Daftar ONT FiberHome |
| /huawei | Daftar ONT Huawei |
| /zte | Daftar ONT ZTE |
| /onu | Pencarian ONT |
| /newonu | Deteksi modem baru |
| /offline | Daftar modem offline |
| /help | Bantuan bot |

Uji juga pencarian SN dan PPP Username.

Pastikan informasi berikut terbaca jika tersedia:

- Model ONT.
- Serial Number.
- PPP Username.
- IP PPPoE.
- Status PPP.
- Redaman optik/RX Power.

## 13. Troubleshooting

| Masalah | Pemeriksaan |
|---|---|
| Bot tidak membalas /start | Periksa webhook, token, HTTPS, secret, dan log Apache |
| Unauthorized dari Telegram | Periksa token atau akses admin |
| Daftar modem kosong | Periksa koneksi NBI dan filter identitas perangkat |
| GenieACS tidak terhubung | Periksa alamat NBI, port, firewall, dan autentikasi |
| Redaman tidak muncul | Periksa apakah parameter RX Power dilaporkan ONT |
| Webhook gagal | Periksa URL HTTPS dan getWebhookInfo |
| API billing Unauthorized | Periksa header X-WANPROV-KEY dan secret Bridge |
| Daftar paket kosong | Periksa endpoint plans dan data paket PHPNuxBill |
| Bot tidak bisa menulis data | Periksa hak akses folder data dan logs |

Lokasi log aplikasi:

```text
/var/www/html/wan-provisioning/logs/audit.log
```

Log Apache:

```text
/var/log/apache2/error.log
```

## 14. Backup dan pemeliharaan

Sebelum update aplikasi, backup:

- config.php
- data/
- logs/

Contoh backup:

```bash
sudo tar -czf wanprovisioning-backup.tar.gz \
-c /var/www/html/wan-provisioning \
config.php data logs
```

Simpan backup di lokasi aman.

Untuk update versi:

1. Backup aplikasi.
2. Upload ZIP versi baru.
3. Ekstrak file aplikasi baru.
4. Timpa file aplikasi lama.
5. Pertahankan config.php, data/, dan logs/.
6. Periksa hak akses folder.
7. Uji `/start` dan seluruh menu.

## 15. Checklist instalasi perangkat baru

- [ ] Ubuntu/Debian terpasang.
- [ ] Apache2 aktif.
- [ ] PHP 8+ aktif.
- [ ] PHP CURL dan JSON tersedia.
- [ ] ZIP WAN Provisioning diekstrak.
- [ ] config.php dibuat.
- [ ] GenieACS NBI dapat diakses.
- [ ] Telegram Bot Token diisi.
- [ ] Telegram Admin ID diisi.
- [ ] Webhook secret dibuat.
- [ ] HTTPS aktif.
- [ ] Webhook berhasil didaftarkan.
- [ ] Commands BotFather diatur.
- [ ] Bot berhasil merespons `/start`.
- [ ] Menu FiberHome berhasil diuji.
- [ ] Menu Huawei berhasil diuji.
- [ ] Menu ZTE berhasil diuji.
- [ ] `/onu` berhasil diuji.
- [ ] `/newonu` berhasil diuji.
- [ ] `/offline` berhasil diuji.
- [ ] PHPNuxBill Bridge diuji jika digunakan.
- [ ] Backup konfigurasi dibuat.

---

**WAN Provisioning V1.4.2**  
Telegram Bot · GenieACS NBI · PHPNuxBill Bridge · FiberHome · Huawei · ZTE