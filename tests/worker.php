<?php
if (!getenv('KTICKETS_TESTING') || !getenv('KTICKETS_WP_PATH')) { exit(2); }
require rtrim(getenv('KTICKETS_WP_PATH'),'/').'/wp-load.php';
$f=json_decode(file_get_contents($argv[1]),true);$_COOKIE['kt_browser']=$f['browser'];$_SERVER['REMOTE_ADDR']='192.0.2.55';$_SERVER['HTTPS']='on';
if ($argv[2]==='cleanup') { KTickets\Service::cleanup();echo json_encode(['ok'=>true]);exit; }
if ($argv[2]==='quarantine') { $_SERVER['REMOTE_ADDR']='192.0.2.'.(100+(int)$argv[3]);$id=KTickets\Security::random();$expiry=time()+86400;$_COOKIE['kt_browser']=$id.'.'.$expiry.'.'.KTickets\Security::hmac('browser:'.$id.'.'.$expiry); }
if (in_array($argv[2],['quota','quarantine'],true)) { $f['data']['idempotency']=KTickets\Security::random(24);$f['data']['secret']=KTickets\Security::random();$f['data']['form_token']=KTickets\Security::form('soft1-code-assistant',false)['token']; }
try { echo wp_json_encode(KTickets\Service::create($f['data'],false)); }
catch (KTickets\Failure $e) { echo wp_json_encode(['error'=>$e->getMessage()]); }
