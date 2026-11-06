<?php
declare(strict_types=1);
$configFile = dirname(__DIR__).'/config.php';
if (!is_file($configFile)) { http_response_code(500); exit('Konfigurasi belum dibuat. Salin config.example.php menjadi config.php.'); }
$GLOBALS['cfg'] = require $configFile;
function cfg(string $k, $default=null) { return $GLOBALS['cfg'][$k] ?? $default; }
function base_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  return ($https?'https':'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}
function ensure_dirs(): void { foreach ([dirname(__DIR__).'/data',dirname(__DIR__).'/logs'] as $d) if(!is_dir($d)) @mkdir($d,0750,true); }
function log_event(string $event, array $meta=[]): void {
  ensure_dirs(); if (($meta['field'] ?? '') === 'Password') $meta['value'] = '[REDACTED]'; $meta = redact($meta);
  file_put_contents(dirname(__DIR__).'/logs/audit.log', json_encode(['time'=>date('c'),'event'=>$event,'meta'=>$meta],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND|LOCK_EX);
}
function redact(array $a): array { foreach($a as $k=>$v){ if(preg_match('/pass|secret|token/i',(string)$k)) $a[$k]='[REDACTED]'; elseif(is_array($v)) $a[$k]=redact($v); } return $a; }
function http_json(string $url, ?array $body=null, array $headers=[]): array {
  $ch=curl_init($url); $h=array_merge(['Accept: application/json'],$headers);
  if($body!==null){$h[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));}
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>cfg('request_timeout',15),CURLOPT_HTTPHEADER=>$h]);
  if($body!==null) curl_setopt($ch,CURLOPT_POST,true);
  if(cfg('genieacs_user')!=='') curl_setopt($ch,CURLOPT_USERPWD,cfg('genieacs_user').':'.cfg('genieacs_pass',''));
  $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($raw===false) throw new RuntimeException('Koneksi gagal: '.$err);
  $data=json_decode($raw,true);
  if($code<200||$code>=300) throw new RuntimeException('GenieACS HTTP '.$code.': '.substr($raw,0,250));
  return is_array($data)?$data:['raw'=>$raw];
}
function devices(string $search='', string $vendor='fiberhome'): array {
  // GenieACS device metadata lives in the reserved _deviceId object.
  // Also request InternetGatewayDevice for TR-069 parameter fallbacks.
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/?projection='.rawurlencode('_id,_deviceId,InternetGatewayDevice,VirtualParameters');
  $rows=http_json($url);
  // PHP 8.0 compatibility: array_is_list() was added in PHP 8.1.
  // GenieACS normally returns a JSON list; tolerate a single object too.
  $isList = ($rows === [] || array_keys($rows) === range(0, count($rows) - 1));
  if (!$isList) {
    $rows=isset($rows['_id'])?[$rows]:[];
  }
  $out=[];
  foreach($rows as $d){
    if(!is_array($d)) continue;
    $did=$d['_deviceId']??[];
    $root=$d['InternetGatewayDevice']??[];
    $man=$did['_Manufacturer']??($root['DeviceInfo']['Manufacturer']['_value']??($d['DeviceID']['Manufacturer']??''));
    $model=$did['_ProductClass']??($root['DeviceInfo']['ModelName']['_value']??($d['DeviceID']['ProductClass']??''));
    $sn=$did['_SerialNumber']??($root['DeviceInfo']['SerialNumber']['_value']??($d['DeviceID']['SerialNumber']??''));
    $id=$d['_id']??'';
    $user=first_username($root);
    $rx=first_rx_power($root,$d['VirtualParameters']??[]);
    $isFiberHome=stripos((string)$man,'FiberHome')!==false || stripos((string)$model,'HG6145D2')!==false || stripos((string)$id,'HG6145D2')!==false;
    $isHuawei=preg_match('/HG8145V5|HG8245H5/i',(string)$model.' '.$id)===1;
    $isZte=stripos((string)$man,'ZTE')!==false || preg_match('/F670L|F609|F670|F660|F672Y/i',(string)$model.' '.$id)===1;
    if($vendor==='huawei' ? !$isHuawei : ($vendor==='zte' ? !$isZte : ($vendor==='fiberhome' ? !$isFiberHome : false))) continue;
    if($search!=='' && stripos($id.' '.$sn.' '.$user.' '.$model,$search)===false) continue;
    $ppp=first_ppp_status($root);
    $out[]=['id'=>$id,'sn'=>$sn,'model'=>$model,'username'=>$user,'rx'=>$rx,'ppp_ip'=>$ppp['ip'],'ppp_status'=>$ppp['status']];
  }
  return $out;
}
function first_username(array $root): string {
  // Ambil Username hanya dari objek WAN PPPoE/IP, jangan dari ManagementServer (mis. 'acs').
  $found='';
  $walk=function($node) use (&$walk,&$found){
    if($found!=='') return;
    foreach($node as $key=>$value){
      if(is_array($value) && preg_match('/^WAN(PPP|IP)Connection$/',(string)$key)){
        foreach($value as $instance){
          if(is_array($instance) && isset($instance['Username']['_value']) && (string)$instance['Username']['_value']!==''){ $found=(string)$instance['Username']['_value']; return; }
        }
      }
      if(is_array($value)) $walk($value);
    }
  };
  $walk($root); return $found;
}

function first_ppp_status(array $root): array {
  $best=['ip'=>'','status'=>'OFFLINE'];
  $walk=function($node) use (&$walk,&$best) {
    foreach($node as $key=>$value) {
      if($key==='WANPPPConnection' && is_array($value)) {
        foreach($value as $obj) {
          if(!is_array($obj)) continue;
          $status=(string)($obj['ConnectionStatus']['_value']??'');
          $ip=(string)($obj['ExternalIPAddress']['_value']??'');
          if($ip!=='' && $ip!=='0.0.0.0' && filter_var($ip,FILTER_VALIDATE_IP)) { $best=['ip'=>$ip,'status'=>'ONLINE']; return; }
          if(strcasecmp($status,'Connected')===0) $best=['ip'=>'','status'=>'ONLINE (IP belum terbaca)'];
        }
      }
      if(is_array($value)) $walk($value);
    }
  };
  $walk($root); return $best;
}
function first_rx_power(array $root, array $virtual=[]): string {
  // Prioritaskan VirtualParameter RXPower (jika disediakan GenieACS), lalu
  // cari RXPower pada seluruh struktur WANDevice agar kompatibel FiberHome,
  // Huawei, dan ZTE yang memakai nama parameter vendor berbeda.
  $v=$virtual['RXPower']['_value']??'';
  if ($v!=='' && $v!==null) {
    $n=trim((string)$v);
    return preg_match('/dBm/i',$n)?$n:$n.' dBm';
  }
  $values=[];
  $walk=function($node) use (&$walk,&$values) {
    foreach($node as $key=>$value) {
      if(strcasecmp((string)$key,'RXPower')===0 && is_array($value) && array_key_exists('_value',$value)) {
        $candidate=trim((string)$value['_value']);
        if($candidate!=='' && is_numeric(str_replace(',', '.', preg_replace('/\s*dBm\s*/i','',$candidate)))) $values[]=$candidate;
      }
      if(is_array($value)) $walk($value);
    }
  };
  $walk($root);
  if(!$values) return '-';
  $n=$values[0];
  return preg_match('/dBm/i',$n)?$n:$n.' dBm';
}
function wan_connections(string $deviceId): array {
  // GenieACS NBI membaca perangkat melalui endpoint koleksi /devices/ dengan filter _id.
  // Hindari GET /devices/{id}, yang pada beberapa versi NBI membalas HTTP 405.
  $query=json_encode(['_id'=>$deviceId],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/?query='.rawurlencode($query).'&projection='.rawurlencode('InternetGatewayDevice.WANDevice');
  $rows=http_json($url);
  $d=(isset($rows[0]) && is_array($rows[0]))?$rows[0]:$rows;
  $root=$d['InternetGatewayDevice']['WANDevice']??[];
  $out=[]; $walk=function($node,$path='') use (&$walk,&$out){
    foreach($node as $k=>$v){ if(!is_array($v))continue; $p=$path===''?$k:$path.'.'.$k;
      if(preg_match('/^WAN(PPP|IP)Connection$/',(string)$k)){
        foreach($v as $idx=>$obj){ if(!is_array($obj))continue; $base='InternetGatewayDevice.WANDevice.1.WANConnectionDevice.'.$idx.'.'.$k; 
          // Path reconstructed below using full nested path
          $out[]=['base'=>$p.'.'.$idx,'kind'=>$k,'index'=>$idx,'obj'=>$obj];
        }
      } else $walk($v,$p);
    }
  }; $walk($root,'InternetGatewayDevice.WANDevice');
  $result=[];
  foreach($out as $w){
    $base=$w['base']; $o=$w['obj'];
    $val=fn($key)=>is_array($o[$key]??null)?($o[$key]['_value']??''):'';
    $result[]=['base'=>$base,'kind'=>$w['kind'],'enable'=>$val('Enable'),'mode'=>$val('ConnectionType'),'name'=>$val('Name'),'service'=>($val('X_FH_ServiceList')!==''?$val('X_FH_ServiceList'):$val('X_HW_SERVICELIST')),'username'=>$val('Username'),'nat'=>$val('NATEnabled'),'vlan_enable'=>($val('VLANEnable')!==''?$val('VLANEnable'):'-'),'vlan_id'=>($val('VLANID')!==''?$val('VLANID'):$val('X_HW_VLAN')),'lan_binding'=>($val('X_FH_LanInterface')!==''?$val('X_FH_LanInterface'):'-'),'status'=>$val('ConnectionStatus')];
  }
  return $result;
}
function set_wan(string $deviceId,string $base,array $values): string {
  $params=[]; foreach($values as $k=>$v)$params[]=[$base.'.'.$k,$v];
  $task=['name'=>'setParameterValues','parameterValues'=>$params];
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/'.rawurlencode($deviceId).'/tasks?connection_request';
  $res=http_json($url,$task); return (string)($res['_id']??$res['id']??'task dikirim');
}

function wan_tree(string $deviceId): array {
  $query=json_encode(['_id'=>$deviceId],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/?query='.rawurlencode($query).'&projection='.rawurlencode('InternetGatewayDevice.WANDevice');
  $rows=http_json($url); $d=(isset($rows[0])&&is_array($rows[0]))?$rows[0]:$rows;
  return $d['InternetGatewayDevice']['WANDevice']??[];
}
function huawei_tree(string $deviceId): array {
  $query=json_encode(['_id'=>$deviceId],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $projection='InternetGatewayDevice.WANDevice,InternetGatewayDevice.LANDevice';
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/?query='.rawurlencode($query).'&projection='.rawurlencode($projection);
  $rows=http_json($url); $d=(isset($rows[0])&&is_array($rows[0]))?$rows[0]:$rows;
  return ['wan'=>$d['InternetGatewayDevice']['WANDevice']??[], 'lan'=>$d['InternetGatewayDevice']['LANDevice']??[]];
}
function huawei_tr069_candidates(array $wanRoot): array {
  $found=[];
  $walk=function($node,$path='InternetGatewayDevice.WANDevice') use (&$walk,&$found) {
    foreach($node as $k=>$v) {
      if(!is_array($v)) continue;
      $next=$path.'.'.$k;
      if($k==='WANIPConnection') {
        foreach($v as $idx=>$obj) {
          if(!is_array($obj)) continue;
          $name=(string)($obj['Name']['_value']??'');
          $service=(string)($obj['X_HW_SERVICELIST']['_value']??$obj['X_FH_ServiceList']['_value']??'');
          if(stripos($name,'TR069')!==false || stripos($service,'TR069')!==false || stripos($service,'Management')!==false) {
            $found[]=['path'=>$next.'.'.$idx,'name'=>$name,'service'=>$service];
          }
        }
      } else $walk($v,$next);
    }
  };
  $walk($wanRoot); return $found;
}
function set_parameter_values_task(string $deviceId,array $params): string {
  if(!$params) throw new RuntimeException('Tidak ada parameter yang perlu diubah.');
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/'.rawurlencode($deviceId).'/tasks?connection_request';
  $res=http_json($url,['name'=>'setParameterValues','parameterValues'=>$params]);
  return (string)($res['_id']??$res['id']??'task dikirim');
}
function wan_connection_devices(string $deviceId): array {
  $root=wan_tree($deviceId); $out=[];
  foreach(($root[1]['WANConnectionDevice']??[]) as $idx=>$obj){
    if(!is_array($obj)) continue;
    $out[]=['index'=>(string)$idx,'base'=>'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.'.$idx,'obj'=>$obj];
  }
  return $out;
}
function numeric_object_instances(array $obj): array {
  $ids=[];
  foreach(array_keys($obj) as $key) {
    if (ctype_digit((string)$key)) $ids[]=(int)$key;
  }
  sort($ids);
  return $ids;
}
function add_object_task(string $deviceId,string $objectName): string {
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/'.rawurlencode($deviceId).'/tasks?connection_request';
  $res=http_json($url,['name'=>'addObject','objectName'=>$objectName]);
  return (string)($res['_id']??$res['id']??'task dikirim');
}
function standard_lan_binding(): string {
  return implode(',', [
    'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5'
  ]);
}
function format_lan_binding($value): string {
  // GenieACS may return a comma-separated string or a list. Normalize only
  // separators/whitespace; do not invent or rewrite instance numbers.
  if (is_array($value)) $items=$value;
  else $items=explode(',', (string)$value);
  $items=array_values(array_filter(array_map(static function($v) {
    if (is_array($v) && array_key_exists('_value',$v)) $v=$v['_value'];
    return trim((string)$v);
  }, $items), static fn($v)=>$v!==''));
  return $items ? implode("\n", $items) : '-';
}
function apply_internet_template(string $deviceId,string $base,string $username,string $password,int $vlanId): string {
  if($vlanId<1 || $vlanId>4094) throw new RuntimeException('VLAN ID harus 1-4094.');
  $values=['Enable'=>true,'Name'=>'2_INTERNET_R_VID_'.$vlanId,'ConnectionType'=>'IP_Routed','X_FH_ServiceList'=>'INTERNET','Username'=>$username,'Password'=>$password,'NATEnabled'=>true,'VLANEnable'=>true,'VLANID'=>$vlanId,'X_FH_LanInterface'=>standard_lan_binding()];
  return set_wan($deviceId,$base,$values);
}


/** Read Wi-Fi profiles from TR-098 and TR-181 trees. Instance numbers are
 * discovered from the device; labels are based on frequency band/channel. */
function wifi_tree(string $deviceId): array {
  $query=json_encode(['_id'=>$deviceId],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $projection='InternetGatewayDevice.LANDevice.1.WLANConfiguration,Device.WiFi';
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/?query='.rawurlencode($query).'&projection='.rawurlencode($projection);
  $rows=http_json($url); $d=(isset($rows[0])&&is_array($rows[0]))?$rows[0]:$rows;
  return ['tr098'=>$d['InternetGatewayDevice']['LANDevice'][1]['WLANConfiguration']??[], 'tr181'=>$d['Device']['WiFi']??[]];
}
function wifi_read_value(array $obj,string $key): string {
  $v=$obj[$key]??''; return is_array($v)?(string)($v['_value']??''):(string)$v;
}
function wifi_profiles(string $deviceId): array {
  $tree=wifi_tree($deviceId); $out=[];
  // Strict policy: expose only TR-098 WLANConfiguration.1 and .5.
  // Other instances (for example WLANConfiguration.8) are deliberately ignored.
  foreach([1,5] as $n){
    $o=$tree['tr098'][$n]??null;
    if(!is_array($o)) continue;
    $ssid=wifi_read_value($o,'SSID'); if($ssid==='') continue;
    $pass=wifi_read_value($o,'KeyPassphrase');
    if($pass===''&&isset($o['PreSharedKey'][1])&&is_array($o['PreSharedKey'][1])) $pass=wifi_read_value($o['PreSharedKey'][1],'PreSharedKey');
    $label=$n===1?'2.4 GHz (SSID1)':'5 GHz (SSID5)';
    $out[$n]=['index'=>$n,'key'=>'tr098:'.$n,'label'=>$label,'ssid'=>$ssid,'password'=>$pass,'enabled'=>wifi_read_value($o,'Enable'),'base'=>'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$n,'ssid_path'=>'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$n.'.SSID','pass_path'=>isset($o['KeyPassphrase'])?'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$n.'.KeyPassphrase':(isset($o['PreSharedKey'][1]['PreSharedKey'])?'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$n.'.PreSharedKey.1.PreSharedKey':'')];
  }
  // TR-181 fallback is also restricted to exact instances 1 and 5;
  // never infer that another instance (e.g. 8) is SSID1/SSID5 by frequency.
  $w=$tree['tr181']??[]; $ssids=$w['SSID']??[]; $aps=$w['AccessPoint']??[];
  foreach([1,5] as $n){
    if(isset($out[$n]) || !isset($ssids[$n]) || !is_array($ssids[$n])) continue;
    $o=$ssids[$n]; $ssid=wifi_read_value($o,'SSID'); if($ssid==='') continue;
    $ap=$aps[$n]??[]; $sec=$ap['Security']??[]; $pass=wifi_read_value($sec,'KeyPassphrase');
    if($pass===''&&isset($sec['PreSharedKey'])) $pass=wifi_read_value($sec,'PreSharedKey');
    $label=$n===1?'2.4 GHz (SSID1)':'5 GHz (SSID5)';
    $out[$n]=['index'=>$n,'key'=>'tr181:'.$n,'label'=>$label,'ssid'=>$ssid,'password'=>$pass,'enabled'=>wifi_read_value($o,'Enable'),'base'=>'Device.WiFi.SSID.'.$n,'ssid_path'=>'Device.WiFi.SSID.'.$n.'.SSID','pass_path'=>array_key_exists('KeyPassphrase',$sec)?'Device.WiFi.AccessPoint.'.$n.'.Security.KeyPassphrase':(array_key_exists('PreSharedKey',$sec)?'Device.WiFi.AccessPoint.'.$n.'.Security.PreSharedKey':'')];
  }
  return $out;
}
function wifi_set_task(string $deviceId,int $idx,string $ssid,string $password): string {
  if(mb_strlen($ssid,'UTF-8')<1||mb_strlen($ssid,'UTF-8')>32)throw new RuntimeException('SSID harus 1–32 karakter.');
  if(strlen($password)<8||strlen($password)>63)throw new RuntimeException('Password Wi-Fi harus 8–63 karakter.');
  $profiles=wifi_profiles($deviceId); $key=$idx===5?5:1; $w=$profiles[$key]??null;
  if(!$w)throw new RuntimeException('Jaringan WiFi tidak tersedia pada modem ini.');
  if($w['pass_path']==='')throw new RuntimeException('Parameter password WiFi tidak tersedia untuk ditulis. Tidak ada perubahan dikirim.');
  return set_parameter_values_task($deviceId,[[$w['ssid_path'],$ssid],[$w['pass_path'],$password]]);
}
function reboot_task(string $deviceId): string {
  $url=rtrim(cfg('genieacs_url'),'/').'/devices/'.rawurlencode($deviceId).'/tasks?connection_request';
  $res=http_json($url,['name'=>'reboot']); return (string)($res['_id']??$res['id']??'task dikirim');
}

function tg(string $method,array $data=[]): array {
  $url='https://api.telegram.org/bot'.cfg('telegram_token').'/'.$method;
  return http_json($url,$data);
}
function tg_send(int|string $chat,string $text,?array $keyboard=null): void {
  // Normalize literal escaped line breaks from older message templates.
  $text=str_replace('\\n', "\n", $text);
  $text=trim($text);
  $d=['chat_id'=>$chat,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true];
  if($keyboard)$d['reply_markup']=['inline_keyboard'=>$keyboard];
  tg('sendMessage',$d);
}
function allowed(int|string $id): bool { return in_array((string)$id,array_map('strval',cfg('telegram_admin_ids',[])),true); }
function state_path(): string { ensure_dirs(); return dirname(__DIR__).'/data/state.json'; }
function state_get(string $key): array { $d=is_file(state_path())?json_decode(file_get_contents(state_path()),true):[]; return $d[$key]??[]; }
function state_set(string $key,array $value): void { $d=is_file(state_path())?json_decode(file_get_contents(state_path()),true):[]; $d[$key]=$value; file_put_contents(state_path(),json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX); }
