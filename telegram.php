<?php
require __DIR__.'/lib/app.php';
$secret=cfg('telegram_webhook_secret','');
if($secret!=='' && !hash_equals($secret,$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'')){http_response_code(403);exit('Forbidden');}
$u=json_decode(file_get_contents('php://input'),true)??[];
try {
 if(isset($u['message'])) {
  $m=$u['message']; $chat=$m['chat']['id']??0; $from=$m['from']['id']??0; $txt=trim($m['text']??'');
  if(!allowed($from)){tg_send($chat,'Akses ditolak. Telegram ID kamu: <code>'.htmlspecialchars((string)$from).'</code>');exit('ok');}
  $command=strtolower(explode(' ', $txt, 2)[0]);
  $argument=trim(substr($txt, strlen($command)));
  if($command==='/start'||$command==='/help'){
    show_main_menu($chat);
  } elseif($command==='/fiberhome') show_devices($chat);
  elseif($command==='/huawei') show_huawei_devices($chat);
  elseif($command==='/zte') show_zte_devices($chat);
  elseif($command==='/newonu') show_new_modems($chat);
  elseif($command==='/offline') show_offline_devices($chat);
  elseif($command==='/onu' && $argument!=='') show_search_devices($chat, ltrim($argument));
  elseif($command==='/onu') { state_set('await_'.$chat,['action'=>'onu_search']); tg_send($chat,'Kirim Serial Number atau PPP Username yang dicari.'); }
  else {
    $st=state_get('await_'.$chat);
    if(($st['action']??'')==='onu_search'){state_set('await_'.$chat,[]);show_search_devices($chat,$txt);}
    elseif(($st['action']??'')==='reg_name'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Registrasi dibatalkan.');}
      elseif(mb_strlen($txt,'UTF-8')<2 || mb_strlen($txt,'UTF-8')>36){tg_send($chat,'Nama lengkap harus 2–36 karakter. Kirim ulang atau /cancel.');}
      else {$st['fullname']=$txt;$st['action']='reg_phone';state_set('await_'.$chat,$st);tg_send($chat,'Kirim nomor HP pelanggan. Contoh: 081234567890');}
    }
    elseif(($st['action']??'')==='reg_phone'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Registrasi dibatalkan.');}
      elseif(!preg_match('/^[0-9+(). -]{8,20}$/',$txt)){tg_send($chat,'Nomor HP tidak valid. Kirim ulang atau /cancel.');}
      else {$st['phonenumber']=$txt;$st['action']='reg_address';state_set('await_'.$chat,$st);tg_send($chat,'Kirim alamat pemasangan pelanggan.');}
    }
    elseif(($st['action']??'')==='reg_address'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Registrasi dibatalkan.');}
      elseif(mb_strlen($txt,'UTF-8')<1 || mb_strlen($txt,'UTF-8')>255){tg_send($chat,'Alamat wajib diisi minimal 1 dan maksimal 255 karakter. Kirim ulang atau /cancel.');}
      else {$st['address']=$txt;$st['action']='reg_email';state_set('await_'.$chat,$st);tg_send($chat,'Kirim email pelanggan, atau ketik /skip jika tidak ada.');}
    }
    elseif(($st['action']??'')==='reg_email'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Registrasi dibatalkan.');}
      elseif($txt!=='/skip' && !filter_var($txt,FILTER_VALIDATE_EMAIL)){tg_send($chat,'Format email tidak valid. Kirim ulang, /skip, atau /cancel.');}
      else {$st['email']=$txt==='/skip'?'':$txt;$st['action']='reg_ppp_pass';state_set('await_'.$chat,$st);tg_send($chat,'Kirim password PPPoE pelanggan (minimal 1 karakter, maksimal 64 karakter).');}
    }
    elseif(($st['action']??'')==='reg_ppp_pass'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Registrasi dibatalkan.');}
      elseif(strlen($txt)<1 || strlen($txt)>64){tg_send($chat,'Password PPPoE wajib diisi minimal 1 dan maksimal 64 karakter. Kirim ulang atau /cancel.');}
      else {$st['pppoe_password']=$txt;state_set('await_'.$chat,[]);state_set('registration_'.$chat,$st);show_registration_plans($chat);}
    }
    elseif(($st['action']??'')==='wifi_ssid'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);state_set('wifi_'.$chat,[]);tg_send($chat,'<b>✖️ Konfigurasi WiFi dibatalkan.</b>');}
      elseif(mb_strlen($txt,'UTF-8')<1 || mb_strlen($txt,'UTF-8')>32){tg_send($chat,'SSID harus 1–32 karakter. Silakan kirim ulang atau /cancel.');}
      else{$st['new_ssid']=$txt;$st['action']='wifi_password';state_set('await_'.$chat,$st);tg_send($chat,'<b>🔐 Password WiFi baru</b>\nKirim password baru (8–63 karakter).\n\nKetik /cancel untuk membatalkan.');}
    }
    elseif(($st['action']??'')==='wifi_password'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);state_set('wifi_'.$chat,[]);tg_send($chat,'<b>✖️ Konfigurasi WiFi dibatalkan.</b>');}
      elseif(strlen($txt)<8 || strlen($txt)>63){tg_send($chat,'Password WiFi harus 8–63 karakter. Silakan kirim ulang atau /cancel.');}
      else{$st['new_password']=$txt;state_set('await_'.$chat,[]);state_set('wifi_'.$chat,$st);$pw=str_repeat('•',8);tg_send($chat,'<b>🧾 KONFIRMASI PERUBAHAN WIFI</b>\n\n<b>Modem:</b> '.htmlspecialchars($st['model']).'\n<b>Serial Number:</b> <code>'.htmlspecialchars($st['sn']).'</code>\n<b>Frekuensi:</b> '.htmlspecialchars($st['label']).'\n<b>SSID lama:</b> <code>'.htmlspecialchars($st['old_ssid']).'</code>\n<b>SSID baru:</b> <code>'.htmlspecialchars($st['new_ssid']).'</code>\n<b>Password baru:</b> '.$pw.'\n\nPerubahan hanya dikirim ke parameter WiFi yang dipilih. Lanjutkan?',[[['text'=>'✅ KONFIRMASI & TERAPKAN','callback_data'=>'wifiapply|'.$chat],['text'=>'✖️ BATAL','callback_data'=>'wificancel|'.$chat]]]);}
    }
    elseif(($st['action']??'')==='edit') handle_edit_value($chat,$txt,$st);
    elseif(($st['action']??'')==='zte_ppp_user'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif($txt===''){tg_send($chat,'Username PPPoE tidak boleh kosong.');} else {$st['username']=$txt;$st['action']='zte_ppp_pass';state_set('await_'.$chat,$st);tg_send($chat,'Kirim password PPPoE ZTE.');} }
    elseif(($st['action']??'')==='zte_ppp_pass'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} else {$st['password']=$txt;$st['action']='zte_ppp_vlan';state_set('await_'.$chat,$st);tg_send($chat,'Kirim VLAN ID WAN PPP ZTE (1–4094). Kirim /cancel untuk batal.');} }
    elseif(($st['action']??'')==='zte_ppp_vlan'){
      if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');}
      elseif(!ctype_digit($txt)||(int)$txt<1||(int)$txt>4094){tg_send($chat,'VLAN ID tidak valid. Masukkan angka 1 sampai 4094.');}
      else {
        $st['vlan_id']=(int)$txt; state_set('await_'.$chat,[]); state_set('zte_ppp_'.$chat,$st);
        $msg='<b>🧾 KONFIRMASI WAN PPP ZTE</b>' . "\n━━━━━━━━━━━━━━━━━━━━\n" .
          'Model: <b>'.htmlspecialchars($st['model']).'</b>' . "\n" .
          'Username: <code>'.htmlspecialchars($st['username']).'</code>' . "\n" .
          'Password: ••••••••' . "\n" .
          'VLAN ID: <b>'.$st['vlan_id'].'</b>' . "\n" .
          'Mode: <code>IP_Routed</code>' . "\n" .
          'Service List: <code>INTERNET</code>' . "\n" .
          'NAT: <code>true</code>' . "\n" .
          'VLAN Enable: <code>true</code>' . "\n" .
          'WAN IP: INTERNET akan dihapus dari Service List INTERNET_TR069_VoIP; TR069_VoIP dipertahankan.' . "\n\nLanjutkan?";
        tg_send($chat,$msg,[[['text'=>'✅ YA, BUAT WAN PPP','callback_data'=>'ztepppapply|'.$chat],['text'=>'✖️ BATAL','callback_data'=>'ztepppcancel|'.$chat]]]);
      }
    }
    elseif(($st['action']??'')==='hw_hotspot_vlan'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif(!ctype_digit($txt)||(int)$txt<1||(int)$txt>4094){tg_send($chat,'VLAN ID tidak valid. Masukkan angka 1 sampai 4094.');} else { $st['vlan_id']=(int)$txt; state_set('await_'.$chat,[]); state_set('hw_hotspot_'.$chat,$st); tg_send($chat,"<b>🧾 KONFIRMASI WAN HOTSPOT HUAWEI</b>\n━━━━━━━━━━━━━━━━━━━━\nModel: <b>".htmlspecialchars($st['model'])."</b>\nVLAN ID: <b>".$st['vlan_id']."</b>\nMode: <code>IP_Bridged</code>\nIP Type: <code>DHCP</code>\nService List: <code>OTHER</code>\nNAT: <code>false</code>\nWAN Name: dibuat otomatis oleh modem\nSSID1 Binding: aktif\nSSID5 Binding: ".(!empty($st['has_ssid5'])?'aktif':'tidak tersedia, dilewati')."\nLAN1–LAN4: nonaktif\nTR069: objek yang terdeteksi akan dilindungi\n\nLanjutkan?",[[['text'=>'✅ YA, BUAT HOTSPOT','callback_data'=>'hwhotspotapply|'.$chat],['text'=>'✖️ BATAL','callback_data'=>'hwhotspotcancel|'.$chat]]]); } }
    elseif(($st['action']??'')==='hw_ppp_user'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif($txt===''){tg_send($chat,'Username PPPoE tidak boleh kosong.');} else { $st['username']=$txt; $st['action']='hw_ppp_pass'; state_set('await_'.$chat,$st); tg_send($chat,'Kirim password PPPoE Huawei.'); } }
    elseif(($st['action']??'')==='hw_ppp_pass'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} else { $st['password']=$txt; $st['action']='hw_ppp_vlan'; state_set('await_'.$chat,$st); tg_send($chat,'Kirim VLAN ID WAN PPP Huawei (1–4094). Kirim /cancel untuk batal.'); } }
    elseif(($st['action']??'')==='hw_ppp_vlan'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif(!ctype_digit($txt)||(int)$txt<1||(int)$txt>4094){tg_send($chat,'VLAN ID tidak valid. Masukkan angka 1 sampai 4094.');} else { $st['vlan_id']=(int)$txt; state_set('await_'.$chat,[]); state_set('hw_ppp_'.$chat,$st); $tr=(int)($st['tr069_count']??0); tg_send($chat,"<b>🧾 KONFIRMASI WAN PPP HUAWEI</b>\n━━━━━━━━━━━━━━━━━━━━\nModel: <b>".htmlspecialchars($st['model'])."</b>\nUsername: <code>".htmlspecialchars($st['username'])."</code>\nPassword: ••••••••\nVLAN ID: <b>".$st['vlan_id']."</b>\nMode: <code>IP_Routed</code>\nService: <code>INTERNET</code>\nNAT: Aktif\nTarget: <code>WCD.2 / WANPPPConnection.".(int)($st['target_index']??1)."</code> (".(($st['needs_add']??false)?'objek baru':'objek kosong dipakai ulang').")\nWi-Fi: aktifkan SSID1 dan SSID5 jika tersedia\nTR069: ".($tr?"terdeteksi pada $tr objek WAN IP; tidak akan diubah":'tidak terdeteksi dari indikator nama/layanan; seluruh WAN IP tetap tidak diubah')."\nLAN binding: tidak diubah.\n\nLanjutkan?",[[['text'=>'✅ YA, BUAT WAN PPP','callback_data'=>'hwpppapply|'.$chat],['text'=>'✖️ BATAL','callback_data'=>'hwpppcancel|'.$chat]]]); } }
    elseif(($st['action']??'')==='newwan_user'){ $st['username']=$txt; $st['action']='newwan_pass'; state_set('await_'.$chat,$st); tg_send($chat,'Kirim password PPPoE. Pesan ini tidak akan ditampilkan kembali.'); }
    elseif(($st['action']??'')==='newwan_pass'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} else { $st['password']=$txt; $st['action']='newwan_vlan'; state_set('await_'.$chat,$st); tg_send($chat,'Kirim VLAN ID untuk WAN Internet baru (1-4094). Kirim /cancel untuk batal.'); } }
    elseif(($st['action']??'')==='newwan_vlan'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif(!ctype_digit($txt)||(int)$txt<1||(int)$txt>4094){tg_send($chat,'VLAN ID tidak valid. Masukkan angka 1 sampai 4094.');} else { $st['vlan_id']=(int)$txt; state_set('await_'.$chat,[]); state_set('newwan_'.$chat,$st); tg_send($chat,"<b>🧾 KONFIRMASI WAN INTERNET</b>\n━━━━━━━━━━━━━━━━━━━━\n<b>Username PPPoE</b>\n<code>".htmlspecialchars($st['username'])."</code>\n<b>Password</b> ••••••••\n\n<b>Template konfigurasi</b>\n• Mode: <code>IP_Routed</code>\n• Service: <code>INTERNET</code>\n• NAT: <b>Aktif</b>\n• VLAN ID: <b>".$st['vlan_id']."</b>\n• Binding: LAN 1, WLAN 1, WLAN 5\n\nLanjutkan membuat WAN Internet?",[[['text'=>'✅ YA, BUAT WAN','callback_data'=>'newwanapply|'.$chat],['text'=>'✖️ BATAL','callback_data'=>'cancelnewwan|'.$chat]]]); } }
    elseif(($st['action']??'')==='hotspot_vlan'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif(!ctype_digit($txt)||(int)$txt<1||(int)$txt>4094){tg_send($chat,'VLAN ID tidak valid. Masukkan angka 1 sampai 4094.');} else { $st['vlan_id']=(int)$txt; $st['action']='hotspot_ssid_a'; state_set('await_'.$chat,$st); tg_send($chat,'Kirim nama SSID untuk WLAN '.$st['wlan_a'].'.'); } }
    elseif(($st['action']??'')==='hotspot_ssid_a'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif($txt===''||mb_strlen($txt,'UTF-8')>32){tg_send($chat,'SSID harus 1–32 karakter. Silakan kirim ulang.');} else { $st['ssid_a']=$txt; $st['action']='hotspot_ssid_b'; state_set('await_'.$chat,$st); tg_send($chat,'Kirim nama SSID untuk WLAN '.$st['wlan_b'].'.'); } }
    elseif(($st['action']??'')==='hotspot_ssid_b'){ if($txt==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');} elseif($txt===''||mb_strlen($txt,'UTF-8')>32){tg_send($chat,'SSID harus 1–32 karakter. Silakan kirim ulang.');} else { $st['ssid_b']=$txt; $st['action']='hotspot_confirm'; state_set('await_'.$chat,[]); state_set('hotspot_'.$chat,$st); tg_send($chat,"<b>🧾 KONFIRMASI WAN HOTSPOT</b>\n━━━━━━━━━━━━━━━━━━━━\nWAN IP: <b>".$st['wan_slot']."</b>\nVLAN ID: <b>".$st['vlan_id']."</b>\nMode: <code>IP_Bridged</code>\nService: <code>Other</code>\nAddressing: <code>DHCP</code>\nNAT: <b>Nonaktif</b>\nLAN Binding: LAN".$st['lan'].", WLAN".$st['wlan_a'].", WLAN".$st['wlan_b']."\nSSID WLAN".$st['wlan_a'].": <code>".htmlspecialchars($st['ssid_a'])."</code>\nSSID WLAN".$st['wlan_b'].": <code>".htmlspecialchars($st['ssid_b'])."</code>\nKeamanan Wi-Fi: <code>BeaconType=None</code> (terbuka/tanpa password)\n\nLanjutkan?",[[['text'=>'✅ YA, BUAT HOTSPOT','callback_data'=>'hotspotapply|'.$chat],['text'=>'✖️ BATAL','callback_data'=>'cancelhotspot|'.$chat]]]); } }
    else tg_send($chat,'Perintah tersedia: /start, /fiberhome, /huawei, /zte, /onu, /newonu, /offline, /help.');
  }
 } elseif(isset($u['callback_query'])) {
  $c=$u['callback_query']; $chat=$c['message']['chat']['id']; $from=$c['from']['id']; $data=$c['data']??'';
  if(!allowed($from)){tg('answerCallbackQuery',['callback_query_id'=>$c['id'],'text'=>'Akses ditolak']);exit('ok');}
  tg('answerCallbackQuery',['callback_query_id'=>$c['id']]);
  $p=explode('|',$data);
  if($p[0]==='home')show_main_menu($chat);
   elseif($p[0]==='customers')show_customer_list($chat,(int)($p[1]??1));
  elseif($p[0]==='register') start_registration($chat);
  elseif($p[0]==='regplan') choose_registration_plan($chat,(int)($p[1]??0));
  elseif($p[0]==='regconfirm') submit_registration($chat);
  elseif($p[0]==='regcancel'){state_set('registration_'.$chat,[]);state_set('await_'.$chat,[]);tg_send($chat,'Registrasi dibatalkan.',[[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);}
  elseif($p[0]==='devices'||$p[0]==='fiberhomedevices')show_devices($chat);
  elseif($p[0]==='devicesnext')show_devices_page($chat,(int)($p[1]??0));
  elseif($p[0]==='huaweidevices')show_huawei_devices($chat);
  elseif($p[0]==='ztedevices')show_zte_devices($chat);
  elseif($p[0]==='newmodems')show_new_modems($chat);
  elseif($p[0]==='offlinedevices')show_offline_devices($chat);
  elseif($p[0]==='offlinenext')show_offline_devices_page($chat,(int)($p[1]??0));
  elseif($p[0]==='ztedevicesnext')show_zte_devices_page($chat,(int)($p[1]??0));
  elseif($p[0]==='ztedevice'){ $r=token_get($p[1]); show_zte_wans($chat,$r['device']); }
  elseif($p[0]==='ztepppstart'){ $r=token_get($p[1]); start_zte_ppp_wizard($chat,$r['device']); }
  elseif($p[0]==='ztepppapply') create_zte_ppp($chat);
  elseif($p[0]==='ztepppcheck') continue_zte_ppp($chat);
  elseif($p[0]==='ztepppcancel'){state_set('zte_ppp_'.$chat,[]);state_set('await_'.$chat,[]);tg_send($chat,'Pembuatan WAN PPP ZTE dibatalkan.');}
  elseif($p[0]==='huaweidevicesnext')show_huawei_devices_page($chat,(int)($p[1]??0));
  elseif($p[0]==='search'){state_set('await_'.$chat,['action'=>'onu_search']);tg_send($chat,'Kirim Serial Number atau PPP Username. Gunakan /onu untuk pencarian langsung.');}
  elseif($p[0]==='help'){tg_send($chat,'<b>Bantuan</b>\\nGunakan Daftar modem untuk memilih ONT, atau Cari modem berdasarkan Serial Number / PPP Username.');}
  elseif($p[0]==='device'){ $r=token_get($p[1]); show_wans($chat,$r['device']); }
  elseif($p[0]==='wifimenu'){ $r=token_get($p[1]); show_wifi_menu($chat,$r['device']); }
  elseif($p[0]==='wifiselect'){ $r=token_get($p[1]); start_wifi_change($chat,$r['device'],(int)($p[2]??0)); }
  elseif($p[0]==='wifi_edit'){ $r=token_get($p[1]); begin_wifi_edit($chat,$r['device'],(int)($p[2]??0)); }
  elseif($p[0]==='wifi_keep_ssid'){ $r=token_get($p[1]); begin_wifi_password_only($chat,$r['device'],(int)($p[2]??0)); }
  elseif($p[0]==='wifi_ssid_change'){ $r=token_get($p[1]); begin_wifi_ssid_prompt($chat,$r['device'],(int)($p[2]??0)); }
  elseif($p[0]==='wifiapply') apply_wifi_change($chat);
  elseif($p[0]==='wifiverify'){ $r=token_get($p[1]); verify_wifi_change($chat,$r['device'],(int)($p[2]??0),rawurldecode($p[3]??'')); }
  elseif($p[0]==='wificancel'){state_set('wifi_'.$chat,[]);state_set('await_'.$chat,[]);tg_send($chat,'<b>✖️ Perubahan WiFi dibatalkan.</b>');}
  elseif($p[0]==='modemmenu'){ $r=token_get($p[1]); show_modem_settings($chat,$r['device']); }
  elseif($p[0]==='modeminfo'){ $r=token_get($p[1]); show_modem_info($chat,$r['device']); }
  elseif($p[0]==='modemreboot'){ $r=token_get($p[1]); confirm_modem_reboot($chat,$r['device']); }
  elseif($p[0]==='rebootapply'){ $r=token_get($p[1]); apply_modem_reboot($chat,$r['device']); }
  elseif($p[0]==='huaweidevice'){ $r=token_get($p[1]); show_huawei_wans($chat,$r['device']); }
  elseif($p[0]==='hwpppstart'){ $r=token_get($p[1]); start_huawei_ppp_wizard($chat,$r['device']); }
  elseif($p[0]==='hwpppapply') create_huawei_ppp($chat);
  elseif($p[0]==='hwpppcheck') continue_huawei_ppp($chat);
  elseif($p[0]==='hwpppcancel'){state_set('hw_ppp_'.$chat,[]);state_set('await_'.$chat,[]);tg_send($chat,'Pembuatan WAN PPP Huawei dibatalkan.');}
  elseif($p[0]==='hwhotspotstart'){ $r=token_get($p[1]); start_huawei_hotspot_wizard($chat,$r['device']); }
  elseif($p[0]==='hwhotspotapply') create_huawei_hotspot($chat);
  elseif($p[0]==='hwhotspotcheck') continue_huawei_hotspot($chat);
  elseif($p[0]==='hwhotspotcancel'){state_set('hw_hotspot_'.$chat,[]);state_set('await_'.$chat,[]);tg_send($chat,'Pembuatan WAN Hotspot Huawei dibatalkan.');}
  elseif($p[0]==='wan'){ $r=token_get($p[1]); show_wan($chat,$r['device'],$r['base']); }
  elseif($p[0]==='edit'){ $r=token_get($p[1]); edit_menu($chat,$r['device'],$p[1]); }
  elseif($p[0]==='field'){ $r=token_get($p[1]); state_set('await_'.$chat,['action'=>'edit','device'=>$r['device'],'base'=>$r['base'],'field'=>$p[2]]);tg_send($chat,'Kirim nilai baru untuk <b>'.htmlspecialchars($p[2]).'</b>. Kirim /cancel untuk batal.');}
  elseif($p[0]==='apply')apply_change($chat,$p[1]);
  elseif($p[0]==='newwan'){ state_set('await_'.$chat,['action'=>'newwan_user','device'=>$p[1]]); tg_send($chat,'Kirim username PPPoE untuk WAN Internet baru.'); }
  elseif($p[0]==='hotspotwan'){ start_hotspot_wan($chat,$p[1]); }
  elseif($p[0]==='hotspotapply') create_hotspot_wan($chat);
  elseif($p[0]==='hotspotcheck') continue_hotspot_wan($chat);
  elseif($p[0]==='cancelhotspot'){state_set('hotspot_'.$chat,[]);state_set('await_'.$chat,[]);tg_send($chat,'Pembuatan WAN Hotspot dibatalkan.');}
  elseif($p[0]==='newwanapply') create_internet_wan($chat);
  elseif($p[0]==='newwancheck') continue_internet_wan($chat);
  elseif($p[0]==='cancelnewwan'){ state_set('newwan_'.$chat,[]); tg_send($chat,'Pembuatan WAN dibatalkan.'); }
  elseif($p[0]==='cancel'){state_set('pending_'.$chat,[]);tg_send($chat,'Perubahan dibatalkan.');}
 }
} catch(Throwable $e){log_event('telegram_error',['error'=>$e->getMessage()]); if(isset($chat))tg_send($chat,'Terjadi kesalahan: '.htmlspecialchars($e->getMessage()));}
echo 'ok';
function new_modem_registry(): array {
 $r=state_get('new_modem_registry'); return is_array($r)?$r:[];
}
function new_modem_has_configured_ppp(array $root): bool {
 // Modem dianggap sudah memiliki WAN PPP jika ada objek WANPPPConnection
 // dengan Username terisi. Instance kosong/default masih boleh ditawarkan.
 $has=false;
 $walk=function($node) use (&$walk,&$has) {
  if($has) return;
  foreach($node as $key=>$value) {
   if($key==='WANPPPConnection' && is_array($value)) {
    foreach($value as $obj) {
     if(!is_array($obj)) continue;
     $username=$obj['Username']['_value']??'';
     if(trim((string)$username)!=='') { $has=true; return; }
    }
   }
   if(is_array($value)) $walk($value);
  }
 };
 $walk($root); return $has;
}
function show_new_modems($chat): void {
 $window=max(1,(int)cfg('new_modem_window_minutes',30));
 $cutoff=time()-($window*60);
 $projection='_id,_deviceId,_lastInform,InternetGatewayDevice.DeviceInfo,InternetGatewayDevice.WANDevice,VirtualParameters';
 $url=rtrim(cfg('genieacs_url'),'/').'/devices/?projection='.rawurlencode($projection);
 $rows=http_json($url);
 if(!is_array($rows)) $rows=[];
 if(isset($rows['_id'])) $rows=[$rows];
 $registry=new_modem_registry(); $found=[];
 foreach($rows as $d){
  if(!is_array($d)) continue;
  $id=(string)($d['_id']??''); if($id==='') continue;
  $last=$d['_lastInform']??'';
  $ts=is_numeric($last)?(int)$last:(($last!=='')?strtotime((string)$last):false);
  if(!$ts || $ts<$cutoff || isset($registry[$id])) continue;
  $root=$d['InternetGatewayDevice']??[];
  if(new_modem_has_configured_ppp($root)) continue;
  $did=$d['_deviceId']??[]; $di=$root['DeviceInfo']??[];
  $manufacturer=(string)($did['_Manufacturer']??($di['Manufacturer']['_value']??''));
  $model=(string)($did['_ProductClass']??($di['ModelName']['_value']??''));
  $sn=(string)($did['_SerialNumber']??($di['SerialNumber']['_value']??''));
  $rx=first_rx_power($root,$d['VirtualParameters']??[]);
  $found[]=['id'=>$id,'manufacturer'=>$manufacturer,'model'=>$model,'sn'=>$sn,'lastInform'=>$ts,'rx'=>$rx];
 }
 usort($found,static fn($a,$b)=>$b['lastInform']<=>$a['lastInform']);
 if(!$found){
  tg_send($chat,'<b>🆕 CEK MODEM BARU</b>' . "\n\nTidak ada modem baru terdeteksi yang perlu di config.",[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'newmodems'],['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);
  return;
 }
 // Tandai hanya modem yang benar-benar memenuhi kriteria: Inform terbaru,
 // belum pernah ditampilkan, dan belum memiliki username WAN PPP.
 foreach($found as $d){$registry[$d['id']]=['first_seen'=>date('c'),'last_inform'=>$d['lastInform'],'manufacturer'=>$d['manufacturer'],'model'=>$d['model'],'sn'=>$d['sn']];}
 state_set('new_modem_registry',$registry);
 // Batasi panjang pesan Telegram. Pecah menjadi beberapa pesan agar daftar
 // modem dalam jumlah besar tidak menghasilkan Bad Request: message is too long.
 $header='<b>🆕 MODEM BARU TERDETEKSI</b>' . "\nJumlah: <b>".count($found)."</b>" . "\nKriteria: Inform ".$window." menit terakhir, belum tercatat, dan belum memiliki WAN PPP.\n\n";
 $parts=[]; $body=$header; $n=0;
 foreach($found as $d){
  $n++;
  $entry='<b>'.$n.'. '.htmlspecialchars(trim($d['manufacturer'].' '.$d['model'])?:'Perangkat tidak dikenal').'</b>' . "\n" .
   'Serial: <code>'.htmlspecialchars($d['sn']?:'-').'</code>' . "\n" .
   'Redaman Optik: '.rx_status_html($d['rx']!==''?$d['rx']:'-') . "\n" .
   'Last Inform: <code>'.date('d-m-Y H:i:s',$d['lastInform']).'</code>' . "\n\n";
  if(mb_strlen($body.$entry,'UTF-8')>3500 && $body!==$header){$parts[]=$body;$body='';}
  $body.=$entry;
 }
 if($body!=='') $parts[]=$body;
 $lastIndex=count($parts)-1;
 foreach($parts as $i=>$part){
  $kb=($i===$lastIndex)?[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'newmodems'],['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]:null;
  tg_send($chat,$part,$kb);
 }
 log_event('new_modems_registered',['count'=>count($found),'device_ids'=>array_column($found,'id')]);
}

function show_main_menu($chat): void {
 tg_send($chat,"<b>🌐 RAKAN-PRJ WAN PROVISIONING</b>\n<i>Manajemen ONT, jaringan, dan pelanggan</i>\n━━━━━━━━━━━━━━━━━━━━\n<b>📡 MONITORING MODEM</b>\nPilih vendor atau status perangkat:",[
  [['text'=>'🟦 FiberHome','callback_data'=>'devices'],['text'=>'🟦 Huawei','callback_data'=>'huaweidevices']],
  [['text'=>'🔷 ZTE','callback_data'=>'ztedevices']],
  [['text'=>'🔴 Modem Offline','callback_data'=>'offlinedevices'],['text'=>'🆕 Modem Baru','callback_data'=>'newmodems']],
  [['text'=>'🔎 Cari Modem / ONT','callback_data'=>'search']],
  [['text'=>'👤 DETAIL PELANGGAN','callback_data'=>'customers|1']],
  [['text'=>'📝 Registrasi Pelanggan Baru','callback_data'=>'register']],
  [['text'=>'ℹ️ Bantuan & Panduan','callback_data'=>'help']]
 ]);
}
function token_put(string $device,string $base=''): string { $t=substr(hash('sha256',$device.'|'.$base),0,16); $all=state_get('tokens'); $all[$t]=['device'=>$device,'base'=>$base]; state_set('tokens',$all); return $t; }
function token_get(string $t): array { $all=state_get('tokens'); if(!isset($all[$t])) throw new RuntimeException('Pilihan sudah kedaluwarsa, buka menu kembali.'); return $all[$t]; }
function show_offline_devices($chat): void {
 $all=devices('', 'all');
 $ds=array_values(array_filter($all,static fn($d)=>strtoupper((string)($d['ppp_status']??''))==='OFFLINE'));
 usort($ds,static fn($a,$b)=>strnatcasecmp($a['sn'],$b['sn']));
 state_set('offline_device_list_'.$chat,$ds);
 show_offline_devices_page($chat,0);
}
function show_offline_devices_page($chat,int $offset=0): void {
 $ds=state_get('offline_device_list_'.$chat);
 $total=count($ds);
 if($total===0){tg_send($chat,'<b>🔴 DAFTAR MODEM OFFLINE</b>' . "\n\nTidak ada modem dengan status PPP OFFLINE.",[[['text'=>'🔄 MUAT ULANG','callback_data'=>'offlinedevices']],[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);return;}
 $offset=max(0,min($offset,max(0,$total-1)));$size=20;$slice=array_slice($ds,$offset,$size);$end=$offset+count($slice);
 $lines=['<b>🔴 DAFTAR MODEM OFFLINE</b>','Jumlah modem offline: <b>'.$total.'</b>','Menampilkan: <b>'.($offset+1).'–'.$end.'</b>',''];
 foreach($slice as $i=>$d){
  $n=$offset+$i+1;$user=$d['username']!==''?$d['username']:'PPP belum terbaca';$sn=$d['sn']!==''?$d['sn']:'-';$model=$d['model']!==''?$d['model']:'-';
  $lines[]='<b>'.$n.'. '.htmlspecialchars($user).'</b>';
  $lines[]='Model: '.htmlspecialchars($model);
  $lines[]='SN: <code>'.htmlspecialchars($sn).'</code> | Status: <b>OFFLINE</b>';
  $lines[]='Redaman: '.rx_status_html($d['rx']!==''?$d['rx']:'-');
  $lines[]='';
 }
 $kb=[];if($end<$total)$kb[]=[['text'=>'➡️ TAMPILKAN SELANJUTNYA','callback_data'=>'offlinenext|'.$end]];
 if($offset>0)$kb[]=[['text'=>'⬅️ HALAMAN SEBELUMNYA','callback_data'=>'offlinenext|'.max(0,$offset-$size)]];
 $kb[]=[['text'=>'🔄 MUAT ULANG DAFTAR','callback_data'=>'offlinedevices']];
 $kb[]=[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']];
 tg_send($chat,implode("\n",$lines),$kb);
}

function rx_status_html($value): string {
 $raw=trim((string)$value);
 if($raw==='' || $raw==='-') return '⚪ Tidak tersedia';
 $number=str_replace(',','.',preg_replace('/[^0-9,.-]/','',$raw));
 if(!is_numeric($number)) return '⚪ '.htmlspecialchars($raw);
 $dbm=(float)$number;
 if($dbm>=-20.99) return '🟢 <b>'.htmlspecialchars($raw).' (Sangat Bagus)</b>';
 if($dbm>=-23.99) return '🟡 <b>'.htmlspecialchars($raw).' (Perlu Diperhatikan)</b>';
 return '🔴 <b>'.htmlspecialchars($raw).' (Buruk)</b>';
}
function show_search_devices($chat,string $search): void {
 $all=[];
 foreach(['fiberhome','huawei','zte'] as $vendor){
  foreach(devices($search,$vendor) as $d){ $d['vendor']=$vendor; $all[]=$d; }
 }
 if(!$all){tg_send($chat,'<b>🔎 HASIL PENCARIAN ONT</b>' . "\n\nTidak ada perangkat yang cocok dengan Serial Number atau PPP Username tersebut.",[[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);return;}
 foreach($all as $d){
  $tok=token_put($d['id']);
  $label=strtoupper($d['vendor']);
  $status=strtoupper((string)($d['ppp_status']??'UNKNOWN'));
  $statusHtml=(strpos($status,'ONLINE')===0?'🟢 <b>'.htmlspecialchars($status).'</b>':($status==='OFFLINE'?'🔴 <b>OFFLINE</b>':'⚪ '.htmlspecialchars($status)));
  $username=trim((string)($d['username']??''));
  $customer=null;
  $billingError='';
  if($username!==''){
   try {
    $billingData=billing_customer_lookup($username);
    $customer=$billingData['customer']??null;
    if(!is_array($customer)) $billingError='Respons detail pelanggan tidak memiliki data customer.';
   } catch(Throwable $e) {
    $billingError=$e->getMessage();
   }
  } else {
   $billingError='Username PPPoE tidak ditemukan pada data GenieACS.';
  }

  $message='<b>🔎 HASIL PENCARIAN MODEM — '.$label.'</b>' . "\n━━━━━━━━━━━━━━━━━━━━\n";
  if(is_array($customer)){
   $billingStatus=trim((string)($customer['billing_status']??'Tidak diketahui'));
   $billingStatusKey=strtolower($billingStatus);
   $billingStatusHtml=in_array($billingStatusKey,['active','aktif','enabled','1'],true)
     ?'🟢 <b>'.htmlspecialchars($billingStatus).'</b>'
     :(in_array($billingStatusKey,['inactive','nonaktif','disabled','0'],true)?'🔴 <b>'.htmlspecialchars($billingStatus).'</b>':'🟡 <b>'.htmlspecialchars($billingStatus?:'Tidak diketahui').'</b>');
   $message.='<b>👤 INFORMASI PELANGGAN PHPNuxBill</b>' . "\n".
    'Nama: <b>'.htmlspecialchars((string)($customer['fullname']??'-'))."</b>\n".
    'Username: <code>'.htmlspecialchars((string)($customer['pppoe_username']??$customer['username']??$username))."</code>\n".
    'Status Billing: '.$billingStatusHtml."\n".
    'Layanan: '.htmlspecialchars((string)($customer['service_type']??'-'))."\n".
    'Nama Paket: '.htmlspecialchars((string)($customer['plan_name']??'-'))."\n".
    'Kadaluarsa: '.htmlspecialchars((string)($customer['expiration']??'-'))."\n".
    'Jenis Akun: '.htmlspecialchars((string)($customer['account_type']??'-'))."\n".
    'Telepon: '.htmlspecialchars((string)($customer['phonenumber']??'-'))."\n".
    'Alamat: '.htmlspecialchars((string)($customer['address']??'-'))."\n".
    "━━━━━━━━━━━━━━━━━━━━\n";
  } else {
   $message.='<b>👤 INFORMASI PELANGGAN PHPNuxBill</b>' . "\n".
    '<i>Data pelanggan belum berhasil diambil: '.htmlspecialchars($billingError)."</i>\n".
    "━━━━━━━━━━━━━━━━━━━━\n";
  }

  $message.='<b>📡 INFORMASI PERANGKAT GENIEACS</b>' . "\n".
   'Model: <b>'.htmlspecialchars($d['model']?:$label)."</b>\n".
   'Serial Number: <code>'.htmlspecialchars($d['sn']?:'-')."</code>\n".
   'Username PPPoE: <code>'.htmlspecialchars($username?:'-')."</code>\n".
   'Status: '.$statusHtml."\n".
   'IP Address: <code>'.htmlspecialchars($d['ppp_ip']?:'Belum tersedia')."</code>\n".
   'Redaman RX: '.rx_status_html($d['rx']?:'-')."\n".
   "━━━━━━━━━━━━━━━━━━━━\n";
  if(is_array($customer)){
   $message.='✅ <b>Pelanggan dan perangkat berhasil dicocokkan</b>';
  } else {
   $message.='⚠️ <b>Perangkat ditemukan, data billing belum cocok</b>';
  }
  $callback=$d['vendor']==='huawei'?'huaweidevice':($d['vendor']==='zte'?'ztedevice':'device');
  tg_send($chat,$message,[[['text'=>'📋 LIHAT DETAIL WAN','callback_data'=>$callback.'|'.$tok]],[['text'=>'⚙️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.$tok]]]);
 }
 tg_send($chat,'<i>Pencarian selesai.</i>',[[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);
}

function show_devices($chat,$search=''){
 $ds=devices($search);
 if(!$ds){
  tg_send($chat,'<b>📡 DAFTAR MODEM FIBERHOME</b>'."\n\nModem FiberHome tidak ditemukan.",[[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']]]);
  return;
 }
 usort($ds,static fn($a,$b)=>strnatcasecmp($a['sn'],$b['sn']));
 if($search!==''){
  foreach($ds as $d){
   $tok=token_put($d['id']);
   $model=$d['model']!==''?$d['model']:'FiberHome';
   $message="<b>📡 HASIL PENCARIAN MODEM</b>\n\n".
    "<b>Model</b>\n".htmlspecialchars($model)."\n\n".
    "<b>Serial Number</b>\n<code>".htmlspecialchars($d['sn']!==''?$d['sn']:'-')."</code>\n\n".
    "<b>Device ID</b>\n<code>".htmlspecialchars($d['id'])."</code>\n\n".
    "<b>PPP Username</b>\n<code>".htmlspecialchars($d['username']!==''?$d['username']:'-')."</code>\n\n".
    "<b>IP PPPoE</b>\n<code>".htmlspecialchars($d['ppp_ip']!==''?$d['ppp_ip']:'Belum tersedia')."</code>\n<b>Status PPPoE</b>: ".htmlspecialchars($d['ppp_status'])."\n\n<b>Redaman Optik</b>\n<b>".htmlspecialchars($d['rx']!==''?$d['rx']:'-')."</b>";
   tg_send($chat,$message,[[['text'=>'📋 LIHAT DETAIL WAN','callback_data'=>'device|'.$tok]]]);
  }
  tg_send($chat,'<i>Hasil pencarian ditampilkan di atas.</i>',[[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']]]);
  return;
 }
 // Simpan snapshot hasil pembacaan ACS agar tombol halaman berikutnya tidak
 // memicu pembacaan ulang semua modem. Refresh otomatis saat menu Daftar Modem dibuka.
 state_set('device_list_'.$chat, $ds);
 show_devices_page($chat, 0);
}

function show_devices_page($chat, int $offset=0): void {
 $ds=state_get('device_list_'.$chat);
 if(!$ds){ show_devices($chat); return; }
 $total=count($ds);
 $offset=max(0,min($offset, max(0,$total-1)));
 $pageSize=25;
 $slice=array_slice($ds,$offset,$pageSize);
 $end=$offset+count($slice);
 $lines=["<b>📡 DAFTAR MODEM FIBERHOME</b>","Jumlah modem: <b>".$total."</b>","Menampilkan: <b>".($offset+1)."–".$end."</b>",""];
 foreach($slice as $i=>$d){
  $number=$offset+$i+1;
  $user=$d['username']!==''?$d['username']:'PPP belum terbaca';
  $sn=$d['sn']!==''?$d['sn']:'-';
  $ip=$d['ppp_ip']!==''?$d['ppp_ip']:'Belum tersedia';
  $status=$d['ppp_status']??'UNKNOWN';
  $lines[]="<b>".$number.". ".htmlspecialchars($user)."</b>";
  $lines[]="SN: <code>".htmlspecialchars($sn)."</code> | IP: <code>".htmlspecialchars($ip)."</code> | ".htmlspecialchars($status);
  $lines[]='Redaman: '.rx_status_html($d['rx']!==''?$d['rx']:'-');
  $lines[]="";
 }
 $keyboard=[];
 if($end<$total) $keyboard[]=[['text'=>'➡️ TAMPILKAN SELANJUTNYA ('.min($pageSize,$total-$end).')','callback_data'=>'devicesnext|'.$end]];
 if($offset>0) $keyboard[]=[['text'=>'⬅️ HALAMAN SEBELUMNYA','callback_data'=>'devicesnext|'.max(0,$offset-$pageSize)]];
 $keyboard[]=[['text'=>'🔄 MUAT ULANG DAFTAR','callback_data'=>'devices']];
 $keyboard[]=[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']];
 $message=implode("\n",$lines);
 if(mb_strlen($message,'UTF-8')>4000){
  // Pengaman batas Telegram: pertahankan maksimal 25 modem per halaman.
  // Hilangkan redaman dari halaman yang sangat panjang agar pesan terkirim.
  $compact=["<b>📡 DAFTAR MODEM FIBERHOME</b>","Jumlah: <b>".$total."</b> | Menampilkan: <b>".($offset+1)."–".$end."</b>",""];
  foreach($slice as $i=>$d){
   $compact[]="<b>".($offset+$i+1).". ".htmlspecialchars(($d['username']??'')!==''?$d['username']:'PPP belum terbaca')."</b>";
   $compact[]="SN: <code>".htmlspecialchars(($d['sn']??'')!==''?$d['sn']:'-')."</code> | IP: <code>".htmlspecialchars(($d['ppp_ip']??'')!==''?$d['ppp_ip']:'-')."</code> | ".htmlspecialchars($d['ppp_status']??'UNKNOWN');
  }
  $message=implode("\n",$compact);
 }
 tg_send($chat,$message,$keyboard);
}

function show_zte_devices($chat,$search=''): void {
 $ds=devices($search,'zte');
 if(!$ds){tg_send($chat,'<b>🔷 DAFTAR MODEM ZTE</b>' . "\n\nF670L / F609 / F670 / F660 / F672Y tidak ditemukan.",[[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);return;}
 usort($ds,static fn($a,$b)=>strnatcasecmp($a['sn'],$b['sn']));state_set('zte_device_list_'.$chat,$ds);show_zte_devices_page($chat,0);
}
function show_zte_devices_page($chat,int $offset=0): void {
 $ds=state_get('zte_device_list_'.$chat);if(!$ds){show_zte_devices($chat);return;}
 $total=count($ds);$offset=max(0,min($offset,max(0,$total-1)));$size=25;$slice=array_slice($ds,$offset,$size);$end=$offset+count($slice);
 $lines=['<b>🔷 DAFTAR MODEM ZTE</b>','Jumlah modem: <b>'.$total.'</b>','Menampilkan: <b>'.($offset+1).'–'.$end.'</b>',''];
 foreach($slice as $i=>$d){
  $n=$offset+$i+1;$user=$d['username']!==''?$d['username']:'PPP belum terbaca';$sn=$d['sn']!==''?$d['sn']:'-';$ip=$d['ppp_ip']!==''?$d['ppp_ip']:'Belum tersedia';
  $lines[]='<b>'.$n.'. '.htmlspecialchars($user).'</b>';
  $lines[]='SN: <code>'.htmlspecialchars($sn).'</code> | IP: <code>'.htmlspecialchars($ip).'</code> | '.htmlspecialchars($d['ppp_status']??'UNKNOWN');
  $lines[]='Redaman: '.rx_status_html($d['rx']!==''?$d['rx']:'-');
  $lines[]='';
 }
 $kb=[];if($end<$total)$kb[]=[['text'=>'➡️ TAMPILKAN SELANJUTNYA ('.min($size,$total-$end).')','callback_data'=>'ztedevicesnext|'.$end]];
 if($offset>0)$kb[]=[['text'=>'⬅️ HALAMAN SEBELUMNYA','callback_data'=>'ztedevicesnext|'.max(0,$offset-$size)]];
 $kb[]=[['text'=>'🔄 MUAT ULANG DAFTAR','callback_data'=>'ztedevices']];$kb[]=[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']];
 $message=implode("\n",$lines);
 if(mb_strlen($message,'UTF-8')>4000){$compact=['<b>🔷 DAFTAR MODEM ZTE</b>','Jumlah: <b>'.$total.'</b> | Menampilkan: <b>'.($offset+1).'–'.$end.'</b>',''];foreach($slice as $i=>$d){$compact[]='<b>'.($offset+$i+1).'. '.htmlspecialchars(($d['username']??'')!==''?$d['username']:'PPP belum terbaca').'</b>';$compact[]='SN: <code>'.htmlspecialchars(($d['sn']??'')!==''?$d['sn']:'-').'</code> | IP: <code>'.htmlspecialchars(($d['ppp_ip']??'')!==''?$d['ppp_ip']:'-').'</code> | '.htmlspecialchars($d['ppp_status']??'UNKNOWN');}$message=implode("\n",$compact);}
 tg_send($chat,$message,$kb);
}
function show_zte_wans($chat,string $id): void {
 $all=wan_connections($id);$lines=['<b>📶 DETAIL WAN ZTE</b>'];
 foreach($all as $i=>$w){$lines[]='';$lines[]='<b>'.htmlspecialchars($w['kind']).'</b> — '.htmlspecialchars($w['name']?:'-');$lines[]='Mode: '.htmlspecialchars((string)$w['mode']).' | Enable: '.htmlspecialchars((string)$w['enable']);$lines[]='Service: '.htmlspecialchars((string)$w['service']).' | Username: <code>'.htmlspecialchars((string)$w['username']).'</code>';}
 if(!$all)$lines[]='Objek WAN belum terbaca.';$tok=token_put($id);$kb=[[['text'=>'➕ BUAT WAN INTERNET (PPPoE)','callback_data'=>'ztepppstart|'.$tok]],[['text'=>'⚙️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.$tok]],[['text'=>'⬅️ DAFTAR ZTE','callback_data'=>'ztedevices']]];tg_send($chat,implode("\n",$lines),$kb);
}
function zte_ppp_is_empty(array $obj): bool {
 if(!array_key_exists('Username',$obj)||!array_key_exists('Password',$obj))return false;
 return trim((string)($obj['Username']['_value']??''))==='' && trim((string)($obj['Password']['_value']??''))==='';
}
function zte_ppp_instances(array $wan): array { return $wan[1]['WANConnectionDevice'][1]['WANPPPConnection']??[]; }
function zte_model_for_device(string $id): string { foreach(devices('','zte') as $d)if($d['id']===$id)return $d['model']?:'ZTE';return 'ZTE'; }
function start_zte_ppp_wizard($chat,string $device): void {
 $tree=wan_tree($device);$wcd=$tree[1]['WANConnectionDevice'][1]??null;if(!is_array($wcd))throw new RuntimeException('WANConnectionDevice.1 tidak ditemukan pada modem ZTE.');
 $ppp=$wcd['WANPPPConnection']??[];$ids=numeric_object_instances(is_array($ppp)?$ppp:[]);$target=null;
 foreach($ids as $idx)if(is_array($ppp[$idx]??null)&&zte_ppp_is_empty($ppp[$idx])){$target=$idx;break;}
 $needsAdd=$target===null;if($needsAdd)$target=$ids?max($ids)+1:1;
 $model=zte_model_for_device($device);$path='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection';
 state_set('await_'.$chat,['action'=>'zte_ppp_user','device'=>$device,'model'=>$model,'target_index'=>$target,'needs_add'=>$needsAdd,'before_ids'=>$ids]);
 $status = $needsAdd ? 'objek PPP baru akan ditambahkan' : 'objek PPP kosong akan digunakan kembali';
 $message = '<b>➕ BUAT WAN PPP ZTE</b>' . "\n\n" .
   'Model: <b>'.htmlspecialchars($model).'</b>' . "\n" .
   'Target: <code>WCD.1 / WANPPPConnection.'.$target.'</code>' . "\n" .
   'Status: '.$status . "\n" .
   'WAN IP: Service List INTERNET akan dibersihkan dari INTERNET_TR069_VoIP; TR069_VoIP dipertahankan.' . "\n\nKirim username PPPoE. Kirim /cancel untuk batal.";
 tg_send($chat, $message);
}
function zte_wanip_service_cleanup(array $wan): array {
 $updates=[];
 $walk=function($node,$path='InternetGatewayDevice.WANDevice') use (&$walk,&$updates) {
  foreach($node as $k=>$v) {
   if(!is_array($v)) continue;
   $next=$path.'.'.$k;
   if($k==='WANIPConnection') {
    foreach($v as $idx=>$obj) {
     if(!is_array($obj)) continue;
     $fieldKey='X_ZTE-COM_ServiceList';
     $field=(string)($obj[$fieldKey]['_value']??'');
     if(strtoupper(trim($field))==='INTERNET_TR069_VOIP') {
      $paramPath=$next.'.'.$idx.'.'.$fieldKey;
      array_push($updates,array($paramPath,'TR069_VoIP'));
     }
    }
   } else $walk($v,$next);
  }
 };
 $walk($wan);
 return $updates;
}
function create_zte_ppp($chat): void {
 $p = state_get('zte_ppp_' . $chat);
 if (!is_array($p) || empty($p['device']) || empty($p['username']) || !array_key_exists('password',$p) || !array_key_exists('vlan_id',$p)) { throw new RuntimeException('Data WAN PPP ZTE belum lengkap.'); }
 $v = (int)$p['vlan_id'];
 if ($v < 1 || $v > 4094) { throw new RuntimeException('VLAN ID harus 1–4094.'); }
 $tree=wan_tree($p['device']);$wcd=$tree[1]['WANConnectionDevice'][1]??null;if(!is_array($wcd))throw new RuntimeException('WCD.1 tidak tersedia.');$ppp=$wcd['WANPPPConnection']??[];$ids=numeric_object_instances(is_array($ppp)?$ppp:[]);$target=null;foreach($ids as $idx)if(is_array($ppp[$idx]??null)&&zte_ppp_is_empty($ppp[$idx])){$target=$idx;break;}
 if($target===null){$target=$ids?max($ids)+1:1;$p['target_index']=$target;$p['before_ids']=$ids;$p['phase']='add';$task=add_object_task($p['device'],'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection');$p['task']=$task;state_set('zte_ppp_'.$chat,$p);tg_send($chat,'<b>⏳ MENAMBAHKAN INSTANCE WAN PPP ZTE</b>' . "\n\nLokasi: <code>WCD.1 / WANPPPConnection</code>\nTask: <code>".htmlspecialchars($task)."</code>\n\nTunggu modem melakukan Inform, lalu tekan Periksa &amp; Lanjutkan.",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'ztepppcheck|'.$chat]]]);return;}
 $p['target_index']=$target;apply_zte_ppp_template($chat,$p);
}
function continue_zte_ppp($chat): void {
 $p=state_get('zte_ppp_'.$chat);if(!$p)throw new RuntimeException('Tidak ada proses WAN PPP ZTE yang menunggu.');$tree=wan_tree($p['device']);$ppp=zte_ppp_instances($tree);$ids=numeric_object_instances(is_array($ppp)?$ppp:[]);$new=array_values(array_diff($ids,array_map('intval',$p['before_ids']??[])));
 if(count($new)===0){tg_send($chat,'Instance WAN PPP baru belum terdeteksi di GenieACS. Pastikan ONT sudah Inform, lalu periksa kembali.',[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'ztepppcheck|'.$chat]]]);return;} if(count($new)>1) throw new RuntimeException('Lebih dari satu instance PPP baru terdeteksi. Bot berhenti agar tidak salah memilih objek.');$idx=(int)$new[0];$obj=$ppp[$idx]??[];if(!zte_ppp_is_empty($obj))throw new RuntimeException('Instance PPP baru tidak kosong. Tidak ada konfigurasi yang dikirim.');$p['target_index']=$idx;apply_zte_ppp_template($chat,$p);
}
function apply_zte_ppp_template($chat,array $p): void {
 $base='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.'.$p['target_index'];$v=(int)$p['vlan_id'];$params=[[$base.'.Enable',true],[$base.'.Name','INTERNET'],[$base.'.ConnectionType','IP_Routed'],[$base.'.X_ZTE-COM_ServiceList','INTERNET'],[$base.'.Username',$p['username']],[$base.'.Password',$p['password']],[$base.'.NATEnabled',true],[$base.'.X_ZTE-COM_VLANEnable',true],[$base.'.X_ZTE-COM_VLANID',$v]];
 $cleanup=zte_wanip_service_cleanup(wan_tree($p['device']));foreach($cleanup as $x)$params[]=$x;$task=set_parameter_values_task($p['device'],$params);log_event('zte_ppp_template_requested',['device'=>$p['device'],'base'=>$base,'username'=>$p['username'],'vlan_id'=>$v,'task'=>$task,'wanip_cleanup_count'=>count($cleanup)]);state_set('zte_ppp_'.$chat,[]);
 tg_send($chat,'<b>✅ KONFIGURASI WAN PPP ZTE DIKIRIM</b>' . "\n\nModel: <b>".htmlspecialchars($p['model'])."</b>\nPath: <code>".htmlspecialchars($base)."</code>\nName: <code>INTERNET</code>\nMode: <code>IP_Routed</code>\nService List: <code>INTERNET</code>\nNAT: <code>true</code>\nVLAN Enable: <code>true</code>\nVLAN ID: <b>".$v."</b>\nWAN IP Service List dibersihkan: <b>".count($cleanup)." objek</b> (INTERNET dihapus; TR069_VoIP dipertahankan).\nUsername: <code>".htmlspecialchars($p['username'])."</code>\nPassword: ••••••••\n\nTask GenieACS: <code>".htmlspecialchars($task)."</code>\nPeriksa hasil task pada GenieACS setelah modem menyelesaikan konfigurasi.");
}

function show_huawei_devices($chat,$search='') {
 $ds=devices($search,'huawei');
 if(!$ds){tg_send($chat,'<b>📡 DAFTAR MODEM HUAWEI</b>' . "\n\nHG8145V5 / HG8245H5 tidak ditemukan.",[[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']]]);return;}
 usort($ds,static fn($a,$b)=>strnatcasecmp($a['sn'],$b['sn']));
 state_set('huawei_device_list_'.$chat,$ds); show_huawei_devices_page($chat,0);
}
function show_huawei_devices_page($chat,int $offset=0): void {
 $ds=state_get('huawei_device_list_'.$chat); if(!$ds){show_huawei_devices($chat);return;}
 $total=count($ds);$offset=max(0,min($offset,max(0,$total-1)));$size=25;$slice=array_slice($ds,$offset,$size);$end=$offset+count($slice);
 $lines=['<b>📡 DAFTAR MODEM HUAWEI</b>','Jumlah modem: <b>'.$total.'</b>','Menampilkan: <b>'.($offset+1).'–'.$end.'</b>',''];
 foreach($slice as $i=>$d){
  $user=$d['username']!==''?$d['username']:'PPP belum terbaca';$sn=$d['sn']!==''?$d['sn']:'-';$ip=$d['ppp_ip']!==''?$d['ppp_ip']:'Belum tersedia';
  $lines[]='<b>'.($offset+$i+1).'. '.htmlspecialchars($user).'</b>';
  $lines[]='SN: <code>'.htmlspecialchars($sn).'</code> | IP: <code>'.htmlspecialchars($ip).'</code> | '.htmlspecialchars($d['ppp_status']??'UNKNOWN');
  $lines[]='Redaman: '.rx_status_html($d['rx']!==''?$d['rx']:'-');
  $lines[]='';
 }
 $kb=[];if($end<$total)$kb[]=[['text'=>'➡️ TAMPILKAN SELANJUTNYA ('.min($size,$total-$end).')','callback_data'=>'huaweidevicesnext|'.$end]];
 if($offset>0)$kb[]=[['text'=>'⬅️ HALAMAN SEBELUMNYA','callback_data'=>'huaweidevicesnext|'.max(0,$offset-$size)]];
 $kb[]=[['text'=>'🔄 MUAT ULANG DAFTAR','callback_data'=>'huaweidevices']];$kb[]=[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']];
 $message=implode("\n",$lines);
 if(mb_strlen($message,'UTF-8')>4000){$compact=['<b>📡 DAFTAR MODEM HUAWEI</b>','Jumlah: <b>'.$total.'</b> | Menampilkan: <b>'.($offset+1).'–'.$end.'</b>',''];foreach($slice as $i=>$d){$compact[]='<b>'.($offset+$i+1).'. '.htmlspecialchars(($d['username']??'')!==''?$d['username']:'PPP belum terbaca').'</b>';$compact[]='SN: <code>'.htmlspecialchars(($d['sn']??'')!==''?$d['sn']:'-').'</code> | IP: <code>'.htmlspecialchars(($d['ppp_ip']??'')!==''?$d['ppp_ip']:'-').'</code> | '.htmlspecialchars($d['ppp_status']??'UNKNOWN');}$message=implode("\n",$compact);}
 tg_send($chat,$message,$kb);
}
function show_huawei_wans($chat,string $id): void {
 $all=wan_connections($id);$tree=huawei_tree($id);$tr=huawei_tr069_candidates($tree['wan']);
 $lines=['<b>📶 DETAIL WAN HUAWEI</b>'];
 foreach($all as $i=>$w){$name=$w['name']?:$w['kind'];$lines[]='';$lines[]='<b>WAN '.($i+1).'</b> — '.htmlspecialchars($name);$lines[]='Tipe: '.htmlspecialchars($w['kind']).' | Enable: '.htmlspecialchars((string)$w['enable']);$lines[]='Mode: '.htmlspecialchars((string)$w['mode']).' | Username: <code>'.htmlspecialchars((string)$w['username']).'</code>';}
 if(!$all)$lines[]='Belum ada objek WAN yang terdeteksi.';
 if($tr){$lines[]='';$lines[]='<b>TR069 terdeteksi (dilindungi)</b>';foreach($tr as $x)$lines[]='<code>'.htmlspecialchars($x['path']).'</code> — '.htmlspecialchars($x['name']?:$x['service']);}else{$lines[]='';$lines[]='TR069 tidak dikenali dari Name/ServiceList. Semua WAN IP tetap tidak akan diubah.';}
 $tok=token_put($id);$kb=[[['text'=>'➕ BUAT WAN INTERNET (PPPoE)','callback_data'=>'hwpppstart|'.$tok]],
      [['text'=>'🌐 BUAT WAN HOTSPOT (BRIDGE)','callback_data'=>'hwhotspotstart|'.$tok]]];
 foreach($all as $w){$wt=token_put($id,$w['base']);$kb[]=[['text'=>'Detail '.($w['name']?:$w['kind']),'callback_data'=>'wan|'.$wt]];}
 $kb[]=[['text'=>'⚙️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($id)]];$kb[]=[['text'=>'⬅️ DAFTAR HUAWEI','callback_data'=>'huaweidevices']];tg_send($chat,implode("\n",$lines),$kb);
}
function huawei_ppp_object_is_empty(array $obj): bool {
 // Huawei ONTs can expose a pre-created PPP profile with defaults already set
 // (Enable=true, ConnectionType=IP_Routed, NAT=false, VLAN=1 and a default
 // Name). Those defaults do not mean that the PPP profile is provisioned.
 // The decisive credentials are Username and Password: reuse the object only
 // when BOTH parameters are discovered and BOTH are blank.
 if (!array_key_exists('Username',$obj) || !array_key_exists('Password',$obj)) {
  return false; // incomplete GenieACS data: fail safe, never overwrite
 }
 $username=trim((string)($obj['Username']['_value']??''));
 $password=trim((string)($obj['Password']['_value']??''));
 return $username==='' && $password==='';
}
function huawei_ppp_next_instance(array $instances): int {
 return $instances ? (max($instances)+1) : 1;
}
function start_huawei_ppp_wizard($chat,string $device): void {
 $tree=huawei_tree($device);$wcds=$tree['wan'][1]['WANConnectionDevice']??[];$wcd2=$wcds[2]??[];
 if(!is_array($wcd2)||!$wcd2) throw new RuntimeException('WANConnectionDevice.2 belum tersedia pada modem. Bot tidak akan membuat WCD otomatis.');
 $ppp=$wcd2['WANPPPConnection']??[];$instances=numeric_object_instances(is_array($ppp)?$ppp:[]);
 $target=null;$needsAdd=false;
 // Reuse the first existing empty PPP object (especially PPP.1); otherwise
 // append a new instance without touching configured PPP objects.
 foreach($instances as $idx){$obj=$ppp[$idx]??[];if(is_array($obj)&&huawei_ppp_object_is_empty($obj)){$target=$idx;break;}}
 if($target===null){$target=huawei_ppp_next_instance($instances);$needsAdd=true;}
 $deviceRow=null;foreach(devices('','huawei') as $d)if($d['id']===$device){$deviceRow=$d;break;}
 $model=$deviceRow['model']??'Huawei';$tr=huawei_tr069_candidates($tree['wan']);
 $base='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.'.$target;
 state_set('await_'.$chat,['action'=>'hw_ppp_user','device'=>$device,'model'=>$model,'tr069_count'=>count($tr),'lan_snapshot'=>$tree['lan'],'target_index'=>$target,'target_base'=>$base,'needs_add'=>$needsAdd,'before'=>array_column(wan_connections($device),'base')]);
 $targetLabel=$needsAdd?'objek baru PPP.'.$target:'objek kosong PPP.'.$target;
 tg_send($chat,'<b>➕ BUAT WAN PPP HUAWEI</b>' . "\n\nModel: <b>".htmlspecialchars($model)."</b>\nTarget: <code>WCD.2 / WANPPPConnection.".$target."</code> (".$targetLabel.")\n".($needsAdd?'PPP yang sudah terisi akan dipertahankan; bot menambahkan instance baru.':'Username dan password PPP masih kosong; profil yang sudah tersedia akan digunakan kembali meskipun memiliki nilai default.')."\nTR069 terdeteksi: <b>".count($tr)." objek</b>; seluruh WAN IP tidak akan diubah.\n\nKirim username PPPoE. Kirim /cancel untuk batal.");
}
function create_huawei_ppp($chat): void {
 $p=state_get('hw_ppp_'.$chat);if(!$p||empty($p['device'])||empty($p['username'])||!isset($p['password'],$p['vlan_id']))throw new RuntimeException('Data WAN PPP Huawei belum lengkap.');
 if((int)$p['vlan_id']<1||(int)$p['vlan_id']>4094)throw new RuntimeException('VLAN ID harus 1–4094.');
 // Re-read the live tree immediately before acting to avoid overwriting a PPP
 // instance that may have been provisioned since the wizard started.
 $tree=huawei_tree($p['device']);$wcd2=$tree['wan'][1]['WANConnectionDevice'][2]??[];
 if(!is_array($wcd2)||!$wcd2)throw new RuntimeException('WCD.2 tidak lagi tersedia.');
 $ppp=$wcd2['WANPPPConnection']??[];$instances=numeric_object_instances(is_array($ppp)?$ppp:[]);
 $target=null;$needsAdd=false;
 foreach($instances as $idx){$obj=$ppp[$idx]??[];if(is_array($obj)&&huawei_ppp_object_is_empty($obj)){$target=$idx;break;}}
 if($target===null){$target=huawei_ppp_next_instance($instances);$needsAdd=true;}
 $p['target_index']=$target;$p['expected_base']='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.'.$target;
 $p['before']=array_column(wan_connections($p['device']),'base');$p['lan_snapshot']=$tree['lan'];
 if(!$needsAdd){
  state_set('hw_ppp_'.$chat,$p);
  apply_huawei_ppp_template($chat,$p,$p['expected_base']);
  return;
 }
 $object='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection';
 $task=add_object_task($p['device'],$object);$p['phase']='add_ppp';$p['object']=$object;$p['task']=$task;
 state_set('hw_ppp_'.$chat,$p);log_event('huawei_ppp_add_requested',['device'=>$p['device'],'model'=>$p['model'],'object'=>$p['expected_base'],'vlan_id'=>$p['vlan_id'],'task'=>$task]);
 tg_send($chat,'<b>⏳ MEMBUAT OBJEK WAN PPP.'.$target.'</b>' . "\n\nGenieACS menerima perintah menambahkan <code>".htmlspecialchars($p['expected_base'])."</code>. Setelah modem melakukan Inform dan objek muncul, tekan Periksa &amp; Lanjutkan.\n\nTask: <code>".htmlspecialchars($task)."</code>",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'hwpppcheck|'.$chat]]]);
}
function continue_huawei_ppp($chat): void {
 $p=state_get('hw_ppp_'.$chat);if(!$p)throw new RuntimeException('Tidak ada proses WAN PPP Huawei yang menunggu.');
 $all=wan_connections($p['device']);$new=null;foreach($all as $w)if($w['kind']==='WANPPPConnection'&&$w['base']===$p['expected_base']){$new=$w;break;}
 if(!$new){tg_send($chat,'<b>⌛ OBJEK PPP BELUM TERDETEKSI</b>' . "\n\nPastikan ONT online dan sudah melakukan Inform ke GenieACS, lalu periksa kembali.",[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'hwpppcheck|'.$chat]]]);return;}
 // Confirm the object is still blank before writing credentials.
 $tree=huawei_tree($p['device']);$idx=(int)$p['target_index'];$obj=$tree['wan'][1]['WANConnectionDevice'][2]['WANPPPConnection'][$idx]??[];
 if(!is_array($obj)||!huawei_ppp_object_is_empty($obj)){throw new RuntimeException('Username atau password pada objek PPP target sudah terisi. Untuk keamanan, konfigurasi dihentikan.');}
 apply_huawei_ppp_template($chat,$p,$p['expected_base']);
}
function apply_huawei_ppp_template($chat,array $p,string $base): void {
 $v=(int)$p['vlan_id'];
 $params=[];foreach(['Enable'=>true,'Name'=>'2_INTERNET_R_VID_'.$v,'ConnectionType'=>'IP_Routed','Username'=>$p['username'],'Password'=>$p['password'],'NATEnabled'=>true,'X_HW_VLAN'=>$v,'X_HW_SERVICELIST'=>'INTERNET','X_HW_IPv4Enable'=>true,'X_HW_IPv6Enable'=>false,'ConnectionTrigger'=>'AlwaysOn','X_HW_DefaultPri'=>0] as $k=>$value)$params[]=[$base.'.'.$k,$value];
 $wanTask=set_parameter_values_task($p['device'],$params);
 $lan=$p['lan_snapshot']??huawei_tree($p['device'])['lan'];$ssidTasks=[];$ssidUsed=[];
 foreach([1,5] as $idx){$obj=$lan[1]['WLANConfiguration'][$idx]??null;if(is_array($obj)&&array_key_exists('Enable',$obj)&&array_key_exists('SSID',$obj)){$ssidTasks[]=set_parameter_values_task($p['device'],[['InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$idx.'.Enable',true]]);$ssidUsed[]=$idx;}}
 $tr=huawei_tr069_candidates(huawei_tree($p['device'])['wan']);
 log_event('huawei_ppp_template_requested',['device'=>$p['device'],'base'=>$base,'username'=>$p['username'],'vlan_id'=>$v,'wan_task'=>$wanTask,'ssid_tasks'=>$ssidTasks,'tr069_count'=>count($tr)]);
 state_set('hw_ppp_'.$chat,[]);
 tg_send($chat,'<b>✅ KONFIGURASI WAN PPP HUAWEI DIKIRIM</b>' . "\n\nPath: <code>".htmlspecialchars($base)."</code>\nNama: <code>2_INTERNET_R_VID_".$v."</code>\nMode: <code>IP_Routed</code>\nService: <code>INTERNET</code>\nNAT: Aktif\nVLAN: <b>".$v."</b>\nUsername: <code>".htmlspecialchars($p['username'])."</code>\nPassword: ••••••••\nWi-Fi: ".($ssidUsed?'SSID'.implode(' dan SSID',$ssidUsed).' diperintahkan Enable=true':'parameter SSID tidak ditemukan; Wi-Fi tidak diubah')."\nLAN binding: tidak diubah.\nTR069: tidak diubah; seluruh WAN IP dilindungi.\n\nTask WAN: <code>".htmlspecialchars($wanTask)."</code>\nTask Wi-Fi: <code>".htmlspecialchars(implode(', ',$ssidTasks)?:'-')."</code>\n\nPeriksa hasil task di GenieACS setelah modem menyelesaikan konfigurasi.");
}


function huawei_has_configured_ppp(array $wanRoot): bool {
 $found=false;
 $walk=function($node) use (&$walk,&$found) {
  foreach($node as $key=>$value) {
   if(!is_array($value)) continue;
   if($key==='WANPPPConnection') {
    foreach($value as $obj) {
     if(!is_array($obj)) continue;
     $username=trim((string)($obj['Username']['_value']??''));
     $password=trim((string)($obj['Password']['_value']??''));
     // Pre-created Huawei PPP rows can contain defaults (Name, Enable,
     // IP_Routed, Service INTERNET) while credentials are still empty.
     if($username!=='' || $password!=='') { $found=true; return; }
    }
   } else $walk($value);
   if($found) return;
  }
 };
 $walk($wanRoot); return $found;
}
function huawei_hotspot_tr069(array $obj): bool {
 $name=(string)($obj['Name']['_value']??'');
 $service=(string)($obj['X_HW_SERVICELIST']['_value']??$obj['X_FH_ServiceList']['_value']??'');
 return stripos($name,'TR069')!==false || stripos($service,'TR069')!==false || stripos($service,'Management')!==false;
}
function huawei_hotspot_empty_ip(array $obj): bool {
 if(huawei_hotspot_tr069($obj)) return false;
 $name=trim((string)($obj['Name']['_value']??''));
 $service=trim((string)($obj['X_HW_SERVICELIST']['_value']??$obj['X_FH_ServiceList']['_value']??''));
 $ip=trim((string)($obj['ExternalIPAddress']['_value']??''));
 // Existing named/service-bound WAN IPs are treated as occupied. Do not
 // overwrite other services even if they are not TR069.
 return $name==='' && $service==='' && $ip==='';
}
function start_huawei_hotspot_wizard($chat,string $device): void {
 $tree=huawei_tree($device);
 if(huawei_has_configured_ppp($tree['wan'])) {
  tg_send($chat,'<b>⛔ WAN HOTSPOT TIDAK DIBUAT</b>' . "\n\nWAN PPP sudah terdeteksi pada modem ini. Sesuai aturan, bot tidak akan membuat WAN Hotspot.");
  return;
 }
 $model='Huawei'; foreach(devices('','huawei') as $d) if($d['id']===$device){$model=$d['model']?:'Huawei';break;}
 $ssid1=$tree['lan'][1]['WLANConfiguration'][1]??null;
 if(!is_array($ssid1) || !array_key_exists('SSID',$ssid1)) throw new RuntimeException('Parameter WLAN SSID1 tidak tersedia. Hotspot tidak dapat dibuat.');
 $ssid5=$tree['lan'][1]['WLANConfiguration'][5]??null;
 $state=['device'=>$device,'model'=>$model,'phase'=>'prepare','has_ssid5'=>is_array($ssid5)&&array_key_exists('SSID',$ssid5)];
 state_set('await_'.$chat,['action'=>'hw_hotspot_vlan']+$state);
 tg_send($chat,'<b>🌐 BUAT WAN HOTSPOT HUAWEI</b>' . "\n\nModel: <b>".htmlspecialchars($model)."</b>\nWAN PPP: <b>tidak terdeteksi</b>\nSSID1: tersedia\nSSID5: ".($state['has_ssid5']?'tersedia':'tidak tersedia; hanya SSID1 yang digunakan')."\nLAN binding: tidak diaktifkan.\nWAN Name: mengikuti nama otomatis dari modem.\n\nKirim VLAN ID (1–4094). Kirim /cancel untuk batal.");
}
function huawei_hotspot_find_target(array $wanRoot): array {
 $wcds=$wanRoot[1]['WANConnectionDevice']??[];
 if(!isset($wcds[2]) || !is_array($wcds[2]))
  throw new RuntimeException('WCD.2 belum tersedia. Bot tidak akan menebak lokasi WAN Hotspot.');
 $ips=$wcds[2]['WANIPConnection']??[];
 $ids=numeric_object_instances(is_array($ips)?$ips:[]);
 foreach($ids as $idx) {
  $obj=$ips[$idx]??[];
  if(is_array($obj) && huawei_hotspot_empty_ip($obj))
   return ['wcd'=>2,'index'=>$idx,'needs_add'=>false,'before_ids'=>$ids];
 }
 return ['wcd'=>2,'index'=>null,'needs_add'=>true,'before_ids'=>$ids];
}
function create_huawei_hotspot($chat): void {
 $p=state_get('hw_hotspot_'.$chat);
 if(!$p||empty($p['device'])||!isset($p['vlan_id'])) throw new RuntimeException('Data WAN Hotspot Huawei belum lengkap.');
 $v=(int)$p['vlan_id']; if($v<1||$v>4094) throw new RuntimeException('VLAN ID harus 1–4094.');
 $tree=huawei_tree($p['device']);
 if(huawei_has_configured_ppp($tree['wan'])) throw new RuntimeException('WAN PPP terdeteksi. Pembuatan Hotspot dibatalkan.');
 $target=huawei_hotspot_find_target($tree['wan']);
 $p['wcd']=2; $p['before_ids']=$target['before_ids'];
 $p['phase']=$target['needs_add']?'add_ip':'apply';
 if(!$target['needs_add']) {
  $p['index']=$target['index'];
  $p['base']='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANIPConnection.'.$p['index'];
 }
 state_set('hw_hotspot_'.$chat,$p);
 if($target['needs_add']) {
  $obj='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANIPConnection';
  $task=add_object_task($p['device'],$obj); $p['task']=$task; state_set('hw_hotspot_'.$chat,$p);
  tg_send($chat,'<b>⏳ MEMBUAT OBJEK WAN IP HUAWEI</b>' . "\n\nLokasi: <code>WCD.2 / WANIPConnection</code>\nTask GenieACS: <code>".htmlspecialchars($task)."</code>\n\nTunggu modem melakukan Inform, lalu tekan <b>Periksa &amp; Lanjutkan</b>. Bot akan mendeteksi nomor instance yang benar secara otomatis.",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'hwhotspotcheck|'.$chat]]]);
  return;
 }
 apply_huawei_hotspot_template($chat,$p);
}
function continue_huawei_hotspot($chat): void {
 $p=state_get('hw_hotspot_'.$chat);
 if(!$p) throw new RuntimeException('Tidak ada proses WAN Hotspot Huawei yang menunggu.');
 $tree=huawei_tree($p['device']);
 if(huawei_has_configured_ppp($tree['wan']))
  throw new RuntimeException('WAN PPP terdeteksi saat pemeriksaan ulang. Pembuatan Hotspot dihentikan.');
 $ips=$tree['wan'][1]['WANConnectionDevice'][2]['WANIPConnection']??[];
 if(!is_array($ips)) $ips=[];
 if(($p['phase']??'')==='add_ip') {
  $before=array_map('intval',$p['before_ids']??[]);
  $current=numeric_object_instances($ips);
  $newIds=array_values(array_diff($current,$before));
  if(!$newIds) {
   tg_send($chat,'Objek WAN IP baru belum terlihat di GenieACS. Pastikan ONT sudah online dan melakukan Inform, lalu coba periksa kembali.',[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'hwhotspotcheck|'.$chat]]]);
   return;
  }
  if(count($newIds)!==1)
   throw new RuntimeException('Lebih dari satu objek WAN IP baru terdeteksi di WCD.2. Bot berhenti agar tidak salah memilih objek.');
  $idx=(int)$newIds[0]; $obj=$ips[$idx]??null;
  if(!is_array($obj)) throw new RuntimeException('Objek WAN IP baru belum dapat dibaca.');
  if(huawei_hotspot_tr069($obj))
   throw new RuntimeException('Objek baru terindikasi TR069. Bot menghentikan konfigurasi untuk melindungi koneksi management.');
  $p['index']=$idx;
  $p['base']='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANIPConnection.'.$idx;
  $p['phase']='apply';
  state_set('hw_hotspot_'.$chat,$p);
 } else {
  $idx=(int)($p['index']??0); $obj=$ips[$idx]??null;
  if(!is_array($obj)) throw new RuntimeException('Objek WAN IP target tidak lagi tersedia.');
  if(huawei_hotspot_tr069($obj))
   throw new RuntimeException('Target WAN IP teridentifikasi sebagai TR069. Bot menghentikan konfigurasi untuk melindungi koneksi management.');
 }
 apply_huawei_hotspot_template($chat,$p);
}
function apply_huawei_hotspot_template($chat,array $p): void {
 $base=$p['base']; $v=(int)$p['vlan_id'];
 // Huawei-specific fields confirmed in the HG8145V5 GenieACS export.
 $params=[
  [$base.'.Enable',true],
  [$base.'.ConnectionType','IP_Bridged'],
  [$base.'.AddressingType','DHCP'],
  [$base.'.NATEnabled',false],
  [$base.'.X_HW_SERVICELIST','OTHER'],
  [$base.'.X_HW_VLAN',$v],
  [$base.'.X_HW_LANBIND.Lan1Enable',0],
  [$base.'.X_HW_LANBIND.Lan2Enable',0],
  [$base.'.X_HW_LANBIND.Lan3Enable',0],
  [$base.'.X_HW_LANBIND.Lan4Enable',0],
  [$base.'.X_HW_LANBIND.SSID1Enable',1]
 ];
 if(!empty($p['has_ssid5'])) $params[]=[$base.'.X_HW_LANBIND.SSID5Enable',1];
 $task=set_parameter_values_task($p['device'],$params);
 log_event('huawei_hotspot_requested',['device'=>$p['device'],'base'=>$base,'vlan_id'=>$v,'task'=>$task,'ssid5'=>!empty($p['has_ssid5'])]);
 state_set('hw_hotspot_'.$chat,[]);
 tg_send($chat,'<b>✅ KONFIGURASI WAN HOTSPOT HUAWEI DIKIRIM</b>' . "\n\nModel: <b>".htmlspecialchars($p['model'])."</b>\nPath: <code>".htmlspecialchars($base)."</code>\nMode: <code>IP_Bridged</code>\nIP Type: <code>DHCP</code>\nService List: <code>OTHER</code>\nNAT: <code>false</code>\nVLAN ID: <b>".$v."</b>\nWAN Name: dibuat otomatis oleh modem\nLAN1–LAN4: <code>0</code>\nSSID1 Binding: <code>1</code>\nSSID5 Binding: ".(!empty($p['has_ssid5'])?'<code>1</code>':'tidak tersedia; dilewati')."\nTR069: objek management dilindungi dan tidak diubah.\n\nTask GenieACS: <code>".htmlspecialchars($task)."</code>\n\nPeriksa hasil setelah modem menyelesaikan task.");
}

function show_wans($chat,$id){
 $w=wan_connections($id); if(!$w){tg_send($chat,'Objek WAN tidak ditemukan.');return;} $hasInternet=false;
 foreach($w as $i=>$x){$tok=token_put($id,$x['base']); if($x['kind']==='WANPPPConnection' && (stripos($x['service'],'INTERNET')!==false || stripos($x['name'],'INTERNET')!==false || $x['username']!==''))$hasInternet=true; tg_send($chat,'WAN '.($i+1).': '.htmlspecialchars($x['name']?:$x['kind']),[[['text'=>'Detail','callback_data'=>'wan|'.$tok]]]);}
 if(!$hasInternet){$t=token_put($id);tg_send($chat,'WAN PPP Internet belum terdeteksi. Pilih jenis WAN yang akan dibuat:',[
  [['text'=>'➕ Buat WAN Internet (PPPoE)','callback_data'=>'newwan|'.$id]],
  [['text'=>'➕ Buat WAN Hotspot (otomatis IP 2/3)','callback_data'=>'hotspotwan|'.$id]]
 ]);}
 else {
  tg_send($chat,'Pilih jenis WAN tambahan:',[
   [['text'=>'➕ Buat WAN Hotspot (otomatis IP 2/3)','callback_data'=>'hotspotwan|'.$id]],
   [['text'=>'⚙️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($id)]]
  ]);
 }
 if($hasInternet) tg_send($chat,'<b>⚙️ PENGATURAN MODEM</b>',[[['text'=>'⚙️ BUKA PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($id)]]]);
}

function start_ppp2_creation($chat,array $p,array $wcd): void {
 $ppp=$wcd['obj']['WANPPPConnection']??[];
 $instances=numeric_object_instances(is_array($ppp)?$ppp:[]);
 // PPP instance numbering is local to each WANConnectionDevice.
 // The Internet WAN is the second WAN connection device, so its first child is PPP.1.
 if($instances) {
  throw new RuntimeException('WANConnectionDevice.2 sudah memiliki objek WAN PPP (instance '.implode(', ',$instances).'). Pembuatan dibatalkan agar tidak menimpa konfigurasi yang ada.');
 }
 $before=[]; foreach(wan_connections($p['device']) as $w) $before[]=$w['base'];
 $object=$wcd['base'].'.WANPPPConnection';
 $expected=$object.'.1';
 $task=add_object_task($p['device'],$object);
 $p['before']=$before;
 $p['object']=$object;
 $p['expected_base']=$expected;
 $p['phase']='add_ppp';
 $p['task']=$task;
 state_set('newwan_'.$chat,$p);
 log_event('wan_add_internet_ppp_requested',['device'=>$p['device'],'object'=>$object,'expected'=>$expected,'task'=>$task,'username'=>$p['username']]);
 tg_send($chat,"<b>⏳ PEMBUATAN WAN INTERNET DIKIRIM</b>\n\nWAN Internet dibuat di dalam <code>WANConnectionDevice.2</code>. Karena nomor instance PPP dimulai ulang pada setiap WANConnectionDevice, objek pertama di dalam WCD.2 adalah <code>WANPPPConnection.1</code> — ini merupakan WAN PPP kedua secara keseluruhan.\n\nWAN PPP pada WCD.1 (TR-069) tidak diubah.\n\n<b>ID Task</b>\n<code>".htmlspecialchars($task)."</code>\n\nSetelah modem menghubungi GenieACS, tekan Periksa &amp; Lanjutkan.",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'newwancheck|'.$chat]]]);
}

function create_internet_wan($chat){
 $p=state_get('newwan_'.$chat);
 if(!$p || empty($p['device']) || empty($p['username']) || !isset($p['password'])) throw new RuntimeException('Data pembuatan WAN tidak lengkap.');
 $id=$p['device'];
 foreach(wan_connections($id) as $w){
  if($w['kind']==='WANPPPConnection' && (stripos($w['service'],'INTERNET')!==false || stripos($w['name'],'INTERNET')!==false || $w['username']!=='')) {
   throw new RuntimeException('WAN PPP Internet sudah ada. Pembuatan duplikat dibatalkan.');
  }
 }
 $wcds=wan_connection_devices($id); $target=null;
 foreach($wcds as $wcd) if(($wcd['index']??'')==='2'){$target=$wcd;break;}
 if($target){ start_ppp2_creation($chat,$p,$target); return; }
 // WCD.2 is a required parent object. Create it first, then wait for the modem/ACS to report it.
 $task=add_object_task($id,'InternetGatewayDevice.WANDevice.1.WANConnectionDevice');
 $p['phase']='add_wcd';
 $p['expected_wcd']='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2';
 $p['task']=$task;
 state_set('newwan_'.$chat,$p);
 log_event('wan_add_wcd2_requested',['device'=>$id,'expected'=>$p['expected_wcd'],'task'=>$task,'username'=>$p['username']]);
 tg_send($chat,"<b>⏳ MEMBUAT WAN CONNECTION DEVICE 2</b>\n\nObjek induk <code>WANConnectionDevice.2</code> belum tersedia. GenieACS diminta membuatnya terlebih dahulu.\n\nSetelah modem menghubungi ACS dan objek WCD 2 muncul, tekan tombol di bawah untuk melanjutkan pembuatan WAN PPP 2.\n\n<b>ID Task</b>\n<code>".htmlspecialchars($task)."</code>",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'newwancheck|'.$chat]]]);
}
function continue_internet_wan($chat){
 $p=state_get('newwan_'.$chat);
 if(!$p) throw new RuntimeException('Tidak ada proses pembuatan WAN yang menunggu.');
 if(($p['phase']??'')==='add_wcd'){
  $wcds=wan_connection_devices($p['device']); $target=null;
  foreach($wcds as $wcd) if(($wcd['index']??'')==='2'){$target=$wcd;break;}
  if(!$target){
   tg_send($chat,"<b>⌛ WAN CONNECTION DEVICE 2 BELUM TERDETEKSI</b>\n\nModem belum melaporkan objek induk WCD 2 ke GenieACS. Pastikan modem online dan sudah melakukan Inform, lalu periksa kembali.",[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'newwancheck|'.$chat]]]);
   return;
  }
  start_ppp2_creation($chat,$p,$target);
  return;
 }
 if(($p['phase']??'')!=='add_ppp') throw new RuntimeException('Tahap pembuatan WAN tidak dikenal.');
 $all=wan_connections($p['device']); $new=null;
 foreach($all as $w) if($w['kind']==='WANPPPConnection' && $w['base']===($p['expected_base']??'') && !in_array($w['base'],$p['before']??[],true)){$new=$w;break;}
 if(!$new){
  tg_send($chat,"<b>⌛ WAN INTERNET BELUM TERDETEKSI</b>\n\nObjek Objek WAN Internet pada WANConnectionDevice.2 belum muncul di GenieACS. Pastikan modem online dan sudah melakukan Inform, lalu periksa kembali.",[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'newwancheck|'.$chat]]]);
  return;
 }
 $task=apply_internet_template($p['device'],$new['base'],$p['username'],$p['password'],(int)$p['vlan_id']);
 log_event('wan_template_requested',['device'=>$p['device'],'base'=>$new['base'],'username'=>$p['username'],'task'=>$task]);
 state_set('newwan_'.$chat,[]);
 tg_send($chat,"<b>✅ KONFIGURASI WAN DIKIRIM</b>\n━━━━━━━━━━━━━━━━━━━━\nTemplate WAN Internet telah dikirim ke GenieACS pada <code>WANConnectionDevice.2.WANPPPConnection.1</code>. Modem perlu menyelesaikan task sebelum konfigurasi dapat dipastikan aktif.\n\n<b>Ringkasan WAN</b>\nNama: <code>2_INTERNET_R_VID_".(int)$p['vlan_id']."</code>\nMode: <code>IP_Routed</code>\nService: <code>INTERNET</code>\nNAT: Aktif\nVLAN ID: <b>".(int)$p['vlan_id']."</b>\nLAN Binding: LAN 1, WLAN 1, WLAN 5\nUsername: <code>".htmlspecialchars($p['username'])."</code>\nPassword: ••••••••\n\n<b>ID Task</b>\n<code>".htmlspecialchars($task)."</code>\n\nSetelah modem menyelesaikan task, buka Detail WAN untuk memeriksa hasilnya.",[[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']]]);
}

function start_hotspot_wan($chat,string $device): void {
 $tree=wan_tree($device); $wcd2=$tree[1]['WANConnectionDevice'][2]??[];
 $ppp2=$wcd2['WANPPPConnection']??[]; $hasPpp2=count(numeric_object_instances(is_array($ppp2)?$ppp2:[]))>0;
 $wcd=$hasPpp2?3:2; $lan=$hasPpp2?2:1; $wa=$hasPpp2?2:1; $wb=$hasPpp2?6:5;
 $state=['device'=>$device,'wcd'=>$wcd,'lan'=>$lan,'wlan_a'=>$wa,'wlan_b'=>$wb,'wan_slot'=>$hasPpp2?3:2,'phase'=>'prepare'];
 state_set('await_'.$chat,['action'=>'hotspot_vlan']+$state);
 tg_send($chat,"<b>➕ PEMBUATAN WAN HOTSPOT — WAN IP ".$state['wan_slot']."</b>\n\n".($hasPpp2?'WAN PPP 2 sudah terdeteksi. Hotspot akan dibuat sebagai WAN IP 3 dengan binding LAN2, WLAN2, WLAN6.':'WAN PPP 2 belum terdeteksi. Hotspot akan dibuat sebagai WAN IP 2 dengan binding LAN1, WLAN1, WLAN5.')."\n\nKirim VLAN ID (1–4094). Kirim /cancel untuk batal.");
}
function hotspot_binding(int $lan,int $wlanA,int $wlanB): string {
 return implode(',',[
  'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.'.$lan,
  'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$wlanA,
  'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$wlanB
 ]);
}
function request_hotspot_objects(int|string $chat,array $p): void {
 $tree=wan_tree($p['device']); $wcds=$tree[1]['WANConnectionDevice']??[]; $wcd=(int)$p['wcd'];
 if(!isset($wcds[$wcd])) {
  $task=add_object_task($p['device'],'InternetGatewayDevice.WANDevice.1.WANConnectionDevice');
  $p['phase']='add_wcd'; $p['task']=$task; state_set('hotspot_'.$chat,$p);
  tg_send($chat,"<b>⏳ MEMBUAT WAN CONNECTION DEVICE ".$wcd." </b>\n\nObjek induk belum ada. Setelah modem melakukan Inform dan objek terdeteksi, tekan Periksa &amp; Lanjutkan.\nID Task: <code>".htmlspecialchars($task)."</code>",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'hotspotcheck|'.$chat]]]);
  return;
 }
 $ips=$wcds[$wcd]['WANIPConnection']??[];
 if(numeric_object_instances(is_array($ips)?$ips:[])) throw new RuntimeException('WAN IP pada slot tujuan sudah ada. Pembuatan dibatalkan agar konfigurasi yang ada tidak tertimpa.');
 $object='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.'.$wcd.'.WANIPConnection';
 $task=add_object_task($p['device'],$object); $p['phase']='add_ip'; $p['object']=$object; $p['expected_base']=$object.'.1'; $p['task']=$task;
 state_set('hotspot_'.$chat,$p);
 tg_send($chat,"<b>⏳ MEMBUAT OBJEK WAN IP</b>\n\nGenieACS diminta menambahkan WAN IP pada slot ".$p['wan_slot'].". Setelah objek terdeteksi, tekan Periksa &amp; Lanjutkan.\nID Task: <code>".htmlspecialchars($task)."</code>",[[['text'=>'🔄 PERIKSA & LANJUTKAN','callback_data'=>'hotspotcheck|'.$chat]]]);
}
function create_hotspot_wan($chat): void {
 $p=state_get('hotspot_'.$chat); if(!$p||empty($p['device'])||empty($p['ssid_a'])||empty($p['ssid_b'])) throw new RuntimeException('Data WAN Hotspot belum lengkap.');
 if((int)$p['vlan_id']<1||(int)$p['vlan_id']>4094) throw new RuntimeException('VLAN ID harus 1–4094.');
 request_hotspot_objects($chat,$p);
}
function continue_hotspot_wan($chat): void {
 $p=state_get('hotspot_'.$chat); if(!$p) throw new RuntimeException('Tidak ada proses WAN Hotspot yang menunggu.');
 $tree=wan_tree($p['device']); $wcds=$tree[1]['WANConnectionDevice']??[]; $wcd=(int)$p['wcd'];
 if(($p['phase']??'')==='add_wcd' && !isset($wcds[$wcd])) { tg_send($chat,'Objek WCD '.$wcd.' belum terdeteksi. Pastikan ONT online/Inform, lalu periksa kembali.',[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'hotspotcheck|'.$chat]]]); return; }
 if(($p['phase']??'')==='add_wcd') { request_hotspot_objects($chat,$p); return; }
 $base=$p['expected_base']??''; $ips=$wcds[$wcd]['WANIPConnection']??[];
 if(($p['phase']??'')!=='add_ip' || !isset($ips[1])) { tg_send($chat,'Objek WAN IP belum terdeteksi. Pastikan ONT online/Inform, lalu periksa kembali.',[[['text'=>'🔄 PERIKSA LAGI','callback_data'=>'hotspotcheck|'.$chat]]]); return; }
 $values=['Enable'=>true,'Name'=>$p['wan_slot'].'_INTERNET_B_VID_'.$p['vlan_id'],'ConnectionType'=>'IP_Bridged','X_FH_ServiceList'=>'Other','AddressingType'=>'DHCP','ExternalIPAddress'=>'','SubnetMask'=>'','DefaultGateway'=>'','DNSServers'=>'','NATEnabled'=>false,'VLANEnable'=>true,'VLANID'=>(int)$p['vlan_id'],'X_FH_LanInterface'=>hotspot_binding((int)$p['lan'],(int)$p['wlan_a'],(int)$p['wlan_b'])];
 $task=set_wan($p['device'],$base,$values);
 $ssidBase='InternetGatewayDevice.LANDevice.1.WLANConfiguration.';
 $ssidTask=set_wan($p['device'],$ssidBase.$p['wlan_a'],['Enable'=>true,'SSID'=>$p['ssid_a'],'BeaconType'=>'None']);
 $ssidTask2=set_wan($p['device'],$ssidBase.$p['wlan_b'],['Enable'=>true,'SSID'=>$p['ssid_b'],'BeaconType'=>'None']);
 log_event('wan_hotspot_requested',['device'=>$p['device'],'base'=>$base,'wan_slot'=>$p['wan_slot'],'vlan_id'=>$p['vlan_id'],'task'=>$task,'ssid_task_a'=>$ssidTask,'ssid_task_b'=>$ssidTask2]);
 state_set('hotspot_'.$chat,[]);
 tg_send($chat,"<b>✅ KONFIGURASI WAN HOTSPOT DIKIRIM</b>\n━━━━━━━━━━━━━━━━━━━━\nWAN IP: <b>".$p['wan_slot']."</b>\nNama: <code>".$p['wan_slot'].'_INTERNET_B_VID_'.$p['vlan_id']."</code>\nMode: <code>IP_Bridged</code>\nService: <code>Other</code>\nAddressing: <code>DHCP</code>\nNAT: Nonaktif\nVLAN ID: <b>".$p['vlan_id']."</b>\nLAN Binding: LAN".$p['lan'].', WLAN'.$p['wlan_a'].', WLAN'.$p['wlan_b']."\nSSID WLAN".$p['wlan_a'].': <code>'.htmlspecialchars($p['ssid_a'])."</code>\nSSID WLAN".$p['wlan_b'].': <code>'.htmlspecialchars($p['ssid_b'])."</code>\nKeamanan: <code>BeaconType=None</code> (Wi-Fi terbuka)\n\nTask WAN: <code>".htmlspecialchars($task)."</code>\nTask SSID: <code>".htmlspecialchars($ssidTask)."</code> / <code>".htmlspecialchars($ssidTask2)."</code>\n\nGenieACS telah menerima perintah. Pastikan modem menyelesaikan semua task.",[[['text'=>'🏠 KEMBALI KE MENU UTAMA','callback_data'=>'home']]]);
}

function show_wan($chat,$id,$base){
  if(!$base||!str_starts_with($base,'InternetGatewayDevice.WANDevice.'))throw new RuntimeException('Jalur WAN tidak valid');
 $all=wan_connections($id); $w=null; foreach($all as $x)if($x['base']===$base)$w=$x;
 if(!$w){tg_send($chat,'WAN tidak ditemukan.');return;}
 $t="<b>Detail WAN</b>\nNama: ".htmlspecialchars($w['name'])."\nTipe: ".htmlspecialchars($w['kind'])."\nEnable: ".htmlspecialchars($w['enable'])."\nMode: ".htmlspecialchars($w['mode'])."\nService: ".htmlspecialchars($w['service'])."\nUsername: ".htmlspecialchars($w['username'])."\nPassword: ••••••••\nNAT: ".htmlspecialchars($w['nat'])."\nVLAN: ".htmlspecialchars($w['vlan_enable'])."\nVLAN ID: ".htmlspecialchars($w['vlan_id'])."\nLAN Binding:\n<code>".htmlspecialchars(format_lan_binding($w['lan_binding']))."</code>";
 $tok=token_put($id,$base); tg_send($chat,$t,[[['text'=>'✏️ Ubah konfigurasi','callback_data'=>'edit|'.$tok]]]);
}
function edit_menu($chat,$id,$token){
 $fields=['Enable','ConnectionType','X_FH_ServiceList','Username','Password','NATEnabled','VLANEnable','VLANID','X_FH_LanInterface'];$rows=[];
 foreach($fields as $f)$rows[]=[['text'=>$f,'callback_data'=>'field|'.$token.'|'.$f]];
 tg_send($chat,'Pilih parameter yang akan diubah:',$rows);
}
function handle_edit_value($chat,$value,$st){
 if($value==='/cancel'){state_set('await_'.$chat,[]);tg_send($chat,'Dibatalkan.');return;}
 $field=$st['field']; $allowedFields=['Enable','ConnectionType','X_FH_ServiceList','Username','Password','NATEnabled','VLANEnable','VLANID','X_FH_LanInterface'];
 if(!in_array($field,$allowedFields,true))throw new RuntimeException('Parameter tidak diizinkan');
 if(in_array($field,['Enable','NATEnabled','VLANEnable'],true) && !in_array(strtolower($value),['true','false','1','0'],true))throw new RuntimeException('Gunakan true atau false.');
 if($field==='VLANID' && (!ctype_digit($value)||(int)$value>4094))throw new RuntimeException('VLAN ID harus 1-4094.');
 if($field==='Password' && strlen($value)>128)throw new RuntimeException('Password terlalu panjang.');
 $pending=['device'=>$st['device'],'base'=>$st['base'],'field'=>$field,'value'=>$value];
 state_set('pending_'.$chat,$pending);state_set('await_'.$chat,[]);
 $display=$field==='Password'?'••••••••':$value;
 tg_send($chat,"Konfirmasi perubahan:\n<b>".htmlspecialchars($field)."</b> = <code>".htmlspecialchars($display)."</code>\n\nTerapkan?",[[['text'=>'✅ TERAPKAN','callback_data'=>'apply|'.$chat],['text'=>'BATAL','callback_data'=>'cancel']]]);
}
function apply_change($chat,$key){
 $p=state_get('pending_'.$chat);if(!$p)throw new RuntimeException('Tidak ada perubahan tertunda');
 $base=$p['base'];$field=$p['field'];$value=$p['value'];
 $check=wan_connections($p['device']);$exists=false;foreach($check as $w)if($w['base']===$base)$exists=true;
 if(!$exists)throw new RuntimeException('Objek WAN sudah tidak ditemukan');
 $task=set_wan($p['device'],$base,[$field=>$value]);
 log_event('wan_change_requested',['chat_id'=>$chat,'device'=>$p['device'],'base'=>$base,'field'=>$field,'value'=>$value,'task'=>$task]);
 state_set('pending_'.$chat,[]);
 tg_send($chat,'Task konfigurasi dikirim ke GenieACS. Task ID: <code>'.htmlspecialchars($task).'</code>'."\nGenieACS akan menerapkannya saat modem terhubung. Gunakan /fiberhome, /huawei, atau /zte untuk memeriksa ulang.");
}

/* ================= REGISTRASI PELANGGAN PHPNuxBill ================= */
function billing_customer_lookup(string $username): array {
  $base=rtrim((string)cfg('billing_base_url',''),'/');
  $secret=(string)cfg('billing_api_secret','');
  if($base==='' || $secret==='') throw new RuntimeException('Konfigurasi PHPNuxBill belum diisi (billing_base_url / billing_api_secret).');
  $url=$base.'/?_route=plugin/wanprovisioning_api/customer&username='.rawurlencode($username);
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>cfg('request_timeout',15),
    CURLOPT_HTTPHEADER=>['Accept: application/json','X-WANPROV-KEY: '.$secret],
    CURLOPT_FOLLOWLOCATION=>false
  ]);
  $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($raw===false) throw new RuntimeException('Koneksi PHPNuxBill gagal: '.$err);
  $data=json_decode($raw,true);
  if($code<200||$code>=300){
    $message=is_array($data)?($data['error']??('HTTP '.$code)):('HTTP '.$code.' dari PHPNuxBill');
    throw new RuntimeException('PHPNuxBill: '.$message);
  }
  if(!is_array($data)||empty($data['ok'])) throw new RuntimeException(is_array($data)?($data['error']??'Respons PHPNuxBill tidak valid.'):'Respons PHPNuxBill bukan JSON.');
  return $data;
}

function billing_customers_page(int $page): array {
  $base=rtrim((string)cfg('billing_base_url',''),'/');
  $secret=(string)cfg('billing_api_secret','');
  if($base==='' || $secret==='') throw new RuntimeException('Konfigurasi PHPNuxBill belum diisi (billing_base_url / billing_api_secret).');
  $url=$base.'/?_route=plugin/wanprovisioning_api/customers&page='.max(1,$page);
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>cfg('request_timeout',15),
    CURLOPT_HTTPHEADER=>['Accept: application/json','X-WANPROV-KEY: '.$secret],
    CURLOPT_FOLLOWLOCATION=>false
  ]);
  $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($raw===false) throw new RuntimeException('Koneksi PHPNuxBill gagal: '.$err);
  $data=json_decode($raw,true);
  if($code<200||$code>=300) throw new RuntimeException('PHPNuxBill: '.(is_array($data)?($data['error']??('HTTP '.$code)):('HTTP '.$code)));
  if(!is_array($data)||empty($data['ok'])||!isset($data['customers'])||!is_array($data['customers'])) throw new RuntimeException('Respons daftar pelanggan PHPNuxBill tidak valid.');
  return $data;
}
function show_customer_list($chat,int $page=1): void {
  try {
    $data=billing_customers_page($page);
  } catch(Throwable $e) {
    tg_send($chat,'<b>👤 DETAIL PELANGGAN</b>'."\n\nTidak dapat mengambil daftar pelanggan.\n<code>".htmlspecialchars($e->getMessage())."</code>",[[['text'=>'🔄 COBA LAGI','callback_data'=>'customers|'.$page]],[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);
    return;
  }
  $customers=$data['customers'];
  $total=count($customers);
  if($total===0) {
    tg_send($chat,'<b>👤 DETAIL PELANGGAN</b>'."\n\nTidak ada data pelanggan pada halaman ini.",[[['text'=>'⬅️ SEBELUMNYA','callback_data'=>'customers|'.max(1,$page-1)]],[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);
    return;
  }
  $msg='<b>👤 DETAIL PELANGGAN PHPNuxBill</b>'."\n".
       '<i>Halaman '.$page.' · Maksimal 25 pelanggan per pesan</i>'."\n".
       "━━━━━━━━━━━━━━━━━━━━\n";
  foreach($customers as $idx=>$c) {
    $st=strtolower(trim((string)($c['status']??'')));
    $dot=in_array($st,['active','aktif','enabled','1'],true)?'🟢':(in_array($st,['inactive','nonaktif','disabled','0','expired','kadaluarsa','kedaluwarsa'],true)?'🔴':'🟡');
    $name=trim((string)($c['fullname']??'')) ?: '-';
    $username=trim((string)($c['username']??'')) ?: '-';
    $plan=trim((string)($c['plan_name']??'')) ?: '-';
    $expiry=trim((string)($c['expiration']??'')) ?: '-';
    $msg.=($idx+1).'. '.$dot.' <b>'.htmlspecialchars($name)."</b>\n".
          '   Username: <code>'.htmlspecialchars($username)."</code>\n".
          '   Paket: '.htmlspecialchars($plan)."\n".
          '   Masa aktif: '.htmlspecialchars($expiry)."\n".
          "────────────────────\n";
  }
  $msg.='<i>Menampilkan '.$total.' pelanggan pada halaman '.$page.'.</i>';
  $buttons=[];
  if($page>1) $buttons[]=['text'=>'⬅️ SEBELUMNYA','callback_data'=>'customers|'.($page-1)];
  if(!empty($data['has_more'])) $buttons[]=['text'=>'➡️ BERIKUTNYA','callback_data'=>'customers|'.($page+1)];
  $rows=[];
  if($buttons) $rows[]=$buttons;
  $rows[]=[['text'=>'🔄 MUAT ULANG','callback_data'=>'customers|'.$page],['text'=>'🏠 MENU UTAMA','callback_data'=>'home']];
  tg_send($chat,$msg,$rows);
}

function billing_api(string $action, ?array $body=null): array {
  $base=rtrim((string)cfg('billing_base_url',''),'/');
  $secret=(string)cfg('billing_api_secret','');
  if($base==='' || $secret==='') throw new RuntimeException('Konfigurasi PHPNuxBill belum diisi (billing_base_url / billing_api_secret).');
  $url=$base.'/?_route=plugin/wanprovisioning_api/'.$action;
  $ch=curl_init($url);
  $headers=['Accept: application/json','X-WANPROV-KEY: '.$secret];
  if($body!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>cfg('request_timeout',15),CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false]);
  if($body!==null) curl_setopt($ch,CURLOPT_POST,true);
  $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($raw===false) throw new RuntimeException('Koneksi PHPNuxBill gagal: '.$err);
  $data=json_decode($raw,true);
  if($code<200||$code>=300) {
    if(is_array($data) && ($data['stage']??'')==='customer_created_recharge_failed') {
      throw new RuntimeException('PARTIAL_REGISTRATION|'.(string)($data['customer_id']??''). '|'.(string)($data['error']??'Aktivasi paket gagal.'));
    }
    $message=is_array($data)?($data['error']??('HTTP '.$code)):('HTTP '.$code.' dari PHPNuxBill');
    throw new RuntimeException('PHPNuxBill: '.$message);
  }
  if(!is_array($data)||empty($data['ok'])) throw new RuntimeException(is_array($data)?($data['error']??'Respons PHPNuxBill tidak valid.'):'Respons PHPNuxBill bukan JSON.');
  return $data;
}
function start_registration($chat): void {
  state_set('registration_'.$chat,[]);
  state_set('await_'.$chat,['action'=>'reg_name']);
  tg_send($chat,'<b>📝 REGISTRASI PELANGGAN BARU</b>' . "\n\nKirim nama lengkap pelanggan. Nama ini akan menjadi Username Billing dan Username PPPoE dengan spasi diganti underscore.\n\nKirim /cancel untuk membatalkan.");
}
function show_registration_plans($chat): void {
  $data=billing_api('plans');$plans=$data['plans']??[];
  if(!$plans){tg_send($chat,'Tidak ada paket PPPoE aktif di PHPNuxBill.',[[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);return;}
  $kb=[];
  foreach($plans as $plan){
    $id=(int)($plan['id']??0);if($id<1)continue;
    $label=(string)($plan['name']??('Paket '.$id)).' — Rp'.number_format((float)($plan['price']??0),0,',','.');
    $kb[]=[['text'=>$label,'callback_data'=>'regplan|'.$id]];
  }
  $kb[]=[['text'=>'✖️ BATAL','callback_data'=>'regcancel']];
  state_set('registration_plans_'.$chat,$plans);
  tg_send($chat,'<b>PILIH PAKET INTERNET</b>' . "\nPaket diambil langsung dari PHPNuxBill.\nMasa aktif sesuai ketentuan: <b>31 hari</b>.",$kb);
}
function choose_registration_plan($chat,int $planId): void {
  $st=state_get('registration_'.$chat);
  if(!$st || empty($st['fullname']))throw new RuntimeException('Data registrasi tidak ditemukan. Mulai ulang.');
  $plans=state_get('registration_plans_'.$chat);$selected=null;
  foreach($plans as $plan)if((int)($plan['id']??0)===$planId){$selected=$plan;break;}
  if(!$selected)throw new RuntimeException('Paket tidak ditemukan. Silakan buka menu registrasi lagi.');
  $st['plan_id']=$planId;$st['plan_name']=(string)$selected['name'];$st['plan_price']=(string)$selected['price'];
  $st['using']=(string)cfg('billing_payment_method','Cash');state_set('registration_'.$chat,$st);
  $username=registration_username($st['fullname']);
  $summary='<b>🧾 KONFIRMASI REGISTRASI PELANGGAN</b>' . "\n\n".
    'Nama: <b>'.htmlspecialchars($st['fullname']).'</b>' . "\n".
    'Username Billing: <code>'.htmlspecialchars($username).'</code>' . "\n".
    'Username PPPoE: <code>'.htmlspecialchars($username).'</code>' . "\n".
    'No. HP: '.htmlspecialchars($st['phonenumber'])."\n".
    'Alamat: '.htmlspecialchars($st['address'])."\n".
    'Email: '.htmlspecialchars($st['email']?:'-')."\n".
    'Paket: <b>'.htmlspecialchars($st['plan_name']).'</b>' . "\n".
    'Harga: <b>Rp'.number_format((float)$st['plan_price'],0,',','.').'</b>' . "\n".
    'Masa aktif: <b>31 hari</b>' . "\n".
    'Password PPPoE: ••••••••' . "\n\nPastikan data benar. Setelah dikonfirmasi, pelanggan akan dibuat di PHPNuxBill.";
  tg_send($chat,$summary,[[['text'=>'✅ KONFIRMASI & DAFTARKAN','callback_data'=>'regconfirm'],['text'=>'✖️ BATAL','callback_data'=>'regcancel']]]);
}
function registration_username(string $fullname): string {
  $name=preg_replace('/\s+/u','_',trim($fullname));
  $name=preg_replace('/[^A-Za-z0-9_.@+-]/','',$name);
  if(strlen($name)<3 || strlen($name)>54)throw new RuntimeException('Nama tidak dapat dijadikan username. Gunakan nama 2–55 karakter dengan huruf/angka.');
  return $name;
}
function submit_registration($chat): void {
  $st=state_get('registration_'.$chat);
  if(!$st || empty($st['plan_id']))throw new RuntimeException('Data registrasi tidak lengkap. Silakan mulai ulang.');
  if(!empty($st['processing']))throw new RuntimeException('Registrasi sedang diproses. Tunggu sebentar.');
  $username=registration_username((string)$st['fullname']);
  $billingPassword=bin2hex(random_bytes(8));
  $payload=['fullname'=>$st['fullname'],'phonenumber'=>$st['phonenumber'],'address'=>$st['address'],
    'email'=>$st['email']??'','pppoe_password'=>$st['pppoe_password'],'billing_password'=>$billingPassword,
    'plan_id'=>(int)$st['plan_id'],'using'=>(string)($st['using']??cfg('billing_payment_method','Cash'))];
  $st['processing']=true;
  state_set('registration_'.$chat,$st);
  try {
    $result=billing_api('register',$payload);
  } catch(Throwable $e) {
    if(str_starts_with($e->getMessage(),'PARTIAL_REGISTRATION|')) {
      state_set('registration_'.$chat,[]);
      $parts=explode('|',$e->getMessage(),3);
      throw new RuntimeException('PHPNuxBill sudah membuat akun pelanggan (ID '.($parts[1]?:'-').'), tetapi aktivasi paket gagal. Jangan tekan daftar ulang. Periksa pelanggan tersebut di PHPNuxBill dan aktifkan paket secara manual. Detail: '.($parts[2]??''));
    }
    $st['processing']=false;
    state_set('registration_'.$chat,$st);
    throw $e;
  }
  state_set('registration_'.$chat,[]);
  $customer=$result['customer']??[];
  $returnedUsername=(string)($customer['username']??$username);
  log_event('billing_customer_registered',['chat_id'=>$chat,'username'=>$returnedUsername,'plan_id'=>$st['plan_id']]);
  tg_send($chat,'<b>✅ REGISTRASI PELANGGAN BERHASIL</b>' . "\n━━━━━━━━━━━━━━━━━━━━\n".
    '<b>👤 DATA PELANGGAN</b>' . "\n".
    'Nama: <b>'.htmlspecialchars((string)$st['fullname']).'</b>' . "\n".
    'Username Billing / PPPoE: <code>'.htmlspecialchars($returnedUsername).'</code>' . "\n".
    'Nomor HP: <code>'.htmlspecialchars((string)$st['phonenumber']).'</code>' . "\n".
    'Alamat: '.htmlspecialchars((string)$st['address'])."\n".
    'Paket: <b>'.htmlspecialchars((string)$st['plan_name']).'</b>' . "\n".
    'Biaya: <b>Rp'.number_format((float)$st['plan_price'],0,',','.').'</b>' . "\n".
    'Masa aktif: <b>31 hari</b>' . "\n".
    'ID pelanggan: <code>'.htmlspecialchars((string)($customer['id']??'-')).'</code>' . "\n━━━━━━━━━━━━━━━━━━━━\n".
    'Selanjutnya pilih modem/ONT berdasarkan Serial Number untuk konfigurasi WAN PPPoE melalui GenieACS.',
    [[['text'=>'📡 PILIH DARI LIST FIBERHOME','callback_data'=>'devices']],[['text'=>'📶 PILIH DARI LIST HUAWEI','callback_data'=>'huaweidevices']],[['text'=>'🔷 PILIH DARI LIST ZTE','callback_data'=>'ztedevices']],[['text'=>'🆕 CEK MODEM BARU','callback_data'=>'newmodems']],[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);
}


function show_modem_settings($chat,string $device): void {
  $meta=device_meta($device); $t=token_put($device);
  tg_send($chat,'<b>⚙️ PENGATURAN MODEM</b>\n\n<b>Model:</b> '.htmlspecialchars($meta['model']).'\n<b>Serial Number:</b> <code>'.htmlspecialchars($meta['sn']).'</code>\n\nPilih pengaturan:',[
    [['text'=>'📶 KONFIGURASI WIFI','callback_data'=>'wifimenu|'.$t]],
    [['text'=>'🔄 RESTART MODEM','callback_data'=>'modemreboot|'.$t]],
    [['text'=>'ℹ️ INFORMASI MODEM','callback_data'=>'modeminfo|'.$t]],
    [['text'=>'⬅️ KEMBALI','callback_data'=>'device|'.$t]]
  ]);
}
function show_modem_info($chat,string $device): void {
  $meta=device_meta($device); $profiles=wifi_profiles($device); $bands=[];
  foreach($profiles as $i=>$w)$bands[]=$w['label'];
  $lines=['<b>ℹ️ INFORMASI MODEM</b>','', '<b>Model:</b> '.htmlspecialchars($meta['model']),'<b>Serial Number:</b> <code>'.htmlspecialchars($meta['sn']).'</code>','<b>Vendor:</b> '.htmlspecialchars($meta['manufacturer']).'\n<b>Firmware:</b> '.htmlspecialchars($meta['firmware']).'\n<b>Uptime:</b> '.htmlspecialchars($meta['uptime']).'\n<b>WiFi:</b> '.($bands?htmlspecialchars(implode(', ',$bands)):'Parameter belum tersedia')];
  tg_send($chat,implode("\n",$lines),[[['text'=>'⬅️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($device)]]]);
}
function confirm_modem_reboot($chat,string $device): void {
  $meta=device_meta($device); $t=token_put($device);
  tg_send($chat,'<b>⚠️ KONFIRMASI RESTART MODEM</b>\n\nModel: <b>'.htmlspecialchars($meta['model']).'</b>\nSN: <code>'.htmlspecialchars($meta['sn']).'</code>\n\nModem akan terputus sementara saat restart. Lanjutkan?',[[['text'=>'✅ YA, RESTART','callback_data'=>'rebootapply|'.$t],['text'=>'✖️ BATAL','callback_data'=>'modemmenu|'.$t]]]);
}
function apply_modem_reboot($chat,string $device): void {
  $task=reboot_task($device); log_event('modem_reboot_requested',['device'=>$device,'task'=>$task]);
  tg_send($chat,'<b>🔄 PERINTAH RESTART DIKIRIM</b>\n\nTask GenieACS: <code>'.htmlspecialchars($task).'</code>\nModem akan offline sementara dan tersambung kembali setelah proses restart.',[[['text'=>'⚙️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($device)]]]);
}
function show_wifi_menu($chat,string $device): void {
  $profiles=wifi_profiles($device); if(!$profiles){tg_send($chat,'<b>📶 KONFIGURASI WIFI</b>\n\nParameter WiFi belum ditemukan di GenieACS atau modem belum melakukan Inform.',[[['text'=>'⬅️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($device)]]]);return;}
  $meta=device_meta($device); $kb=[];
  foreach($profiles as $idx=>$w)$kb[]=[['text'=>'📡 '.$w['label'].' — '.$w['ssid'],'callback_data'=>'wifiselect|'.token_put($device).'|'.$idx]];
  $kb[]=[['text'=>'⬅️ PENGATURAN MODEM','callback_data'=>'modemmenu|'.token_put($device)]];
  tg_send($chat,'<b>📶 KONFIGURASI WIFI</b>\n\n<b>Model:</b> '.htmlspecialchars($meta['model']).'\n<b>SN:</b> <code>'.htmlspecialchars($meta['sn']).'</code>\n\nPilih jaringan WiFi:', $kb);
}
function device_meta(string $id): array {
  $query=json_encode(['_id'=>$id],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $projection='_deviceId,InternetGatewayDevice.DeviceInfo,Device.DeviceInfo';
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/?query='.rawurlencode($query).'&projection='.rawurlencode($projection);
  $rows=http_json($url); $d=(isset($rows[0])&&is_array($rows[0]))?$rows[0]:$rows;
  $did=$d['_deviceId']??[]; $ig=$d['InternetGatewayDevice']['DeviceInfo']??[]; $di=$d['Device']['DeviceInfo']??[];
  $get=static function($a,$b,$c='') { return (string)($a['_value']??$b['_value']??$c); };
  return ['model'=>(string)($did['_ProductClass']??$get($ig['ModelName']??[],$di['ModelName']??[],'ONT')),'sn'=>(string)($did['_SerialNumber']??$get($ig['SerialNumber']??[],$di['SerialNumber']??[],'-')),'manufacturer'=>(string)($did['_Manufacturer']??$get($ig['Manufacturer']??[],$di['Manufacturer']??[],'-')),'firmware'=>$get($ig['SoftwareVersion']??[],$di['SoftwareVersion']??[],'-'),'uptime'=>$get($ig['UpTime']??[],$di['UpTime']??[],'-')];
}
function start_wifi_change($chat,string $device,int $idx): void {
  if(!in_array($idx,[1,5],true)) throw new RuntimeException('Pilihan SSID tidak valid.');
  $profiles=wifi_profiles($device); if(!isset($profiles[$idx])) throw new RuntimeException('SSID'.$idx.' tidak tersedia pada modem ini.');
  $w=$profiles[$idx];$meta=device_meta($device);
  $pass=$w['password']!==''?'<code>'.htmlspecialchars($w['password']).'</code>':'<i>Password tidak dapat dibaca dari GenieACS.</i>';
  $kb=[[['text'=>'✏️ UBAH SSID & PASSWORD','callback_data'=>'wifi_edit|'.token_put($device).'|'.$idx]],[['text'=>'⬅️ PILIH WIFI','callback_data'=>'wifimenu|'.token_put($device)]]];
  tg_send($chat,'<b>📶 DETAIL WIFI</b>\n\n<b>Modem:</b> '.htmlspecialchars($meta['model']).'\n<b>SN:</b> <code>'.htmlspecialchars($meta['sn']).'</code>\n<b>Jaringan:</b> '.htmlspecialchars($w['label']).'\n<b>Status:</b> '.htmlspecialchars($w['enabled']==='true'?'Aktif':($w['enabled']==='false'?'Nonaktif':'Tidak diketahui')).'\n\n<b>SSID saat ini</b>\n<code>'.htmlspecialchars($w['ssid']).'</code>\n\n<b>Password saat ini</b>\n'.$pass,$kb);
}
function begin_wifi_edit($chat,string $device,int $idx): void {
  $profiles=wifi_profiles($device);if(!isset($profiles[$idx]))throw new RuntimeException('Jaringan WiFi tidak tersedia.');$w=$profiles[$idx];$meta=device_meta($device);$t=token_put($device);
  $base=['device'=>$device,'index'=>$idx,'label'=>$w['label'],'old_ssid'=>$w['ssid'],'model'=>$meta['model'],'sn'=>$meta['sn']];
  state_set('wifi_edit_choice_'.$chat,$base);
  tg_send($chat,'<b>✏️ PENGATURAN NAMA WIFI</b>\n\nJaringan: '.htmlspecialchars($w['label']).'\nSSID saat ini: <code>'.htmlspecialchars($w['ssid']).'</code>\n\nApakah nama SSID ingin diganti?',[[['text'=>'✏️ YA, GANTI NAMA SSID','callback_data'=>'wifi_ssid_change|'.$t.'|'.$idx]],[['text'=>'➡️ TIDAK, PERTAHANKAN NAMA','callback_data'=>'wifi_keep_ssid|'.$t.'|'.$idx]],[['text'=>'⬅️ KEMBALI','callback_data'=>'wifiselect|'.$t.'|'.$idx]]]);
}
function begin_wifi_ssid_prompt($chat,string $device,int $idx): void {
  $profiles=wifi_profiles($device);if(!isset($profiles[$idx]))throw new RuntimeException('Jaringan WiFi tidak tersedia.');$w=$profiles[$idx];$meta=device_meta($device);
  state_set('await_'.$chat,['action'=>'wifi_ssid','device'=>$device,'index'=>$idx,'label'=>$w['label'],'old_ssid'=>$w['ssid'],'model'=>$meta['model'],'sn'=>$meta['sn']]);
  tg_send($chat,'<b>✏️ UBAH NAMA WIFI</b>\n\nJaringan: '.htmlspecialchars($w['label']).'\nSSID saat ini: <code>'.htmlspecialchars($w['ssid']).'</code>\n\nKirim SSID baru (1–32 karakter). Ketik /cancel untuk batal.');
}
function begin_wifi_password_only($chat,string $device,int $idx): void {
  $profiles=wifi_profiles($device);if(!isset($profiles[$idx]))throw new RuntimeException('Jaringan WiFi tidak tersedia.');$w=$profiles[$idx];$meta=device_meta($device);
  state_set('await_'.$chat,['action'=>'wifi_password','device'=>$device,'index'=>$idx,'label'=>$w['label'],'old_ssid'=>$w['ssid'],'new_ssid'=>$w['ssid'],'model'=>$meta['model'],'sn'=>$meta['sn']]);
  tg_send($chat,'<b>🔐 PASSWORD WIFI BARU</b>\n\nNama SSID tetap: <code>'.htmlspecialchars($w['ssid']).'</code>\nKirim password baru (8–63 karakter).\n\nKetik /cancel untuk membatalkan.');
}
function apply_wifi_change($chat): void {
  $p=state_get('wifi_'.$chat);if(!$p||empty($p['device'])||!isset($p['index'],$p['new_ssid'],$p['new_password']))throw new RuntimeException('Data perubahan WiFi tidak lengkap atau sudah kedaluwarsa.');
  $task=wifi_set_task($p['device'],(int)$p['index'],(string)$p['new_ssid'],(string)$p['new_password']);
  // Do not retain credentials after task submission and never include them in logs.
  $device=$p['device'];$idx=(int)$p['index'];$newSsid=$p['new_ssid'];state_set('wifi_'.$chat,[]);
  log_event('wifi_change_task_sent',['device'=>$device,'index'=>$idx,'ssid'=>$newSsid,'task'=>$task]);
  tg_send($chat,'<b>✅ PERINTAH WIFI DIKIRIM</b>\n\n<b>SSID baru:</b> <code>'.htmlspecialchars($newSsid).'</code>\n<b>Jaringan:</b> SSID'.$idx.'\n<b>Task GenieACS:</b> <code>'.htmlspecialchars($task).'</code>\n\nTunggu modem melakukan Inform, lalu periksa kembali detail WiFi untuk memastikan perubahan sudah diterapkan.',[[['text'=>'🔍 VERIFIKASI PERUBAHAN','callback_data'=>'wifiverify|'.token_put($device).'|'.$idx.'|'.rawurlencode($newSsid)]],[['text'=>'📶 MENU WIFI','callback_data'=>'wifimenu|'.token_put($device)]],[['text'=>'🏠 MENU UTAMA','callback_data'=>'home']]]);
}

function verify_wifi_change($chat,string $device,int $idx,string $expected): void {
  $profiles=wifi_profiles($device); if(!isset($profiles[$idx]))throw new RuntimeException('SSID tidak lagi terbaca. Pastikan modem online dan sudah Inform ke GenieACS.');
  $actual=$profiles[$idx]['ssid'];
  if(hash_equals($expected,$actual)) tg_send($chat,'<b>✅ VERIFIKASI BERHASIL</b>\n\nSSID'.$idx.' sudah terbaca sesuai nilai baru:\n<code>'.htmlspecialchars($actual).'</code>\n\nKonfigurasi sudah diterapkan dan dilaporkan oleh modem.',[[['text'=>'📶 KEMBALI KE WIFI','callback_data'=>'wifimenu|'.token_put($device)]]]);
  else tg_send($chat,'<b>⏳ PERUBAHAN BELUM TERVERIFIKASI</b>\n\nSSID yang terbaca saat ini:\n<code>'.htmlspecialchars($actual).'</code>\n\nSSID yang diharapkan:\n<code>'.htmlspecialchars($expected).'</code>\n\nModem mungkin belum melakukan Inform atau task belum selesai. Tunggu sebentar lalu verifikasi kembali.',[[['text'=>'🔍 COBA VERIFIKASI LAGI','callback_data'=>'wifiverify|'.token_put($device).'|'.$idx.'|'.rawurlencode($expected)]],[['text'=>'📶 MENU WIFI','callback_data'=>'wifimenu|'.token_put($device)]]]);
}
