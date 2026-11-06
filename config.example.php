<?php
// Salin menjadi config.php, lalu isi kredensial. Jangan commit config.php ke GitHub.
return [
  'genieacs_url' => 'http://127.0.0.1:7557',
  'genieacs_user' => '',
  'genieacs_pass' => '',
  'telegram_token' => '',
  'telegram_admin_ids' => [], // contoh: [123456789]
  'telegram_webhook_secret' => 'GANTI_DENGAN_SECRET_PANJANG',
  'device_manufacturer' => 'FiberHome',
  'device_model' => 'HG6145D2',
  'request_timeout' => 15,
  'verify_after_seconds' => 8,
  // PHPNuxBill WAN Provisioning Bridge
  'billing_base_url' => 'https://taglis.tlfiber.my.id',
  'billing_api_secret' => '', // isi sama dengan api_secret di wanprovisioning.config.php
  'billing_payment_method' => 'Cash',
];
