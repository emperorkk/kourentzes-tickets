<?php
/** Run only against a disposable WordPress database: wp eval-file tests/integration.php */
use KTickets\Store;
use KTickets\Security;
use KTickets\Service;
use KTickets\REST;

if (!defined('ABSPATH') || !getenv('KTICKETS_TESTING')) { throw new RuntimeException('Disposable test database required.'); }
ob_start();
$GLOBALS['passes']=[];
$GLOBALS['ticket_outbound']=0;
add_filter('pre_http_request',function ($pre) { $GLOBALS['ticket_outbound']++;return new WP_Error('test_no_network','Network forbidden in ticket tests.'); },99);
add_filter('pre_wp_mail',function ($pre) { $GLOBALS['ticket_outbound']++;return false; },99);
function check($condition,string $label): void { global $passes;if (!$condition) { throw new RuntimeException('FAIL: '.$label); } $passes[]=$label; }
function denied(callable $action,string $code): void { try { $action(); }catch (KTickets\Failure $e) { check($e->getMessage()===$code,'rejects '.$code);return; }throw new RuntimeException('Expected '.$code); }
function clear_abuse(): void { Store::sql('DELETE FROM '.Store::table('abuse'));delete_option('ktickets_attack'); }
function browser(string $value=''): string { $id=$value?:Security::random();$expiry=time()+86400;$_COOKIE['kt_browser']=$id.'.'.$expiry.'.'.Security::hmac('browser:'.$id.'.'.$expiry);return $id; }
function data(bool $privacy=false): array {
    $f=Security::form('soft1-code-assistant',$privacy);
    return ['project'=>'soft1-code-assistant','category'=>'technical','request_type'=>'erasure','subject'=>'Synthetic Unicode test 🦉','body'=>"SELECT '<script>alert(1)</script>';\nΕλληνικά 🦉 and https://example.test",'alias'=>'Test only','acknowledge'=>true,'secret'=>Security::random(),'idempotency'=>Security::random(24),'form_token'=>$f['token']];
}
function test_origin(): string { $p=wp_parse_url(home_url());return $p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:''); }
function guest_request(array $t,string $csrf,string $body='A valid synthetic reply'): WP_REST_Request {
    $r=new WP_REST_Request('POST');$r->set_header('origin',test_origin());$r->set_header('x-kt-csrf',$csrf);$r->set_param('revision',(int)$t['revision']);$r->set_param('body',$body);return $r;
}
Store::install();check(get_option('ktickets_schema')===KTICKETS_VERSION,'versioned migration');
Store::install();check(!get_option('ktickets_migration_error'),'repeat migration succeeds');
$owner=get_user_by('login','fixture_owner'); if (!$owner) { $owner=get_user_by('id',1); }
wp_set_current_user($owner->ID);
foreach (Store::caps() as $cap) { $owner->remove_cap($cap); }
wp_set_current_user(0);wp_set_current_user($owner->ID);
check(!current_user_can('view_ktickets'),'activation does not grant administrator ticket access');
foreach (Store::caps() as $cap) { $owner->add_cap($cap); }
wp_set_current_user(0);wp_set_current_user($owner->ID);
$other=wp_insert_user(['user_login'=>'fixture_other','user_pass'=>wp_generate_password(),'role'=>'administrator']); if (is_wp_error($other)) { $other=get_user_by('login','fixture_other')->ID; }
check(!user_can($other,'view_ktickets'),'other administrator has no ticket access');
$agent=wp_insert_user(['user_login'=>'fixture_agent','user_pass'=>wp_generate_password(),'role'=>'subscriber']);if (is_wp_error($agent)) { $agent=get_user_by('login','fixture_agent')->ID; }
$u=get_user_by('id',$agent);foreach (['view_ktickets','reply_ktickets'] as $cap) { $u->add_cap($cap); }
foreach (['projects','tickets','messages','access','sessions','events','privacy','abuse','replays'] as $name) { if ($name!=='projects') Store::sql('DELETE FROM '.Store::table($name)); }
$s=Store::defaults();$s['owner']=$owner->ID;$s['verified']=true;$s['enabled']=true;$s['policy_url']='https://example.test/privacy';
foreach (['portal_page'=>'[ktickets_portal]','form_page'=>'[ktickets_form]','privacy_page'=>'[ktickets_privacy_form]'] as $field=>$content) { $s[$field]=wp_insert_post(['post_title'=>$field,'post_content'=>$content,'post_type'=>'page','post_status'=>'publish']); }
update_option('ktickets_settings',$s,false);
$_SERVER['HTTPS']='on';$_SERVER['REMOTE_ADDR']='192.0.2.10';
$browser=browser();$d=data();$receipt=Service::create($d,false);$a=Store::ticket($receipt['public_id']);
check($a && $a['status']==='open','anonymous creation');
$again=Service::create($d,false);check($again['public_id']===$receipt['public_id'],'idempotent retry returns original reference');
$changed=$d;$changed['subject']='Changed subject';denied(fn()=>Service::create($changed,false),'idempotency_conflict');
$new=$d;$new['idempotency']=Security::random(24);denied(fn()=>Service::create($new,false),'form_expired');
$stored=Store::db()->get_var('SELECT secret_hash FROM '.Store::table('access').' LIMIT 1');check($stored===hash('sha256',$d['secret']),'only secret hash persisted');
$exchange=Service::exchange(['ticket'=>$a['public_id'],'key'=>$d['secret']]);
// Test code supplies the raw cookie known only to the Set-Cookie response by inserting a synthetic session.
$session=Security::random();$csrf=Security::random();Store::insert('sessions',['session_hash'=>Security::hash($session),'ticket_id'=>$a['id'],'csrf_hash'=>Security::hash($csrf),'expires_at'=>gmdate('Y-m-d H:i:s',time()+43200)]);$_COOKIE['kt_s_'.$a['public_id']]=$session;
$r=guest_request($a,$csrf);check((bool)Security::session($a,$r,true),'scoped session and CSRF accepted');
$r->set_header('origin','https://evil.test');denied(fn()=>Security::session($a,$r,true),'access_denied');$r->set_header('origin',test_origin());$r->set_header('x-kt-csrf','wrong');denied(fn()=>Security::session($a,$r,true),'access_denied');
clear_abuse();$d2=data();$b=Store::ticket(Service::create($d2,false)['public_id']);denied(fn()=>Security::session($b,guest_request($b,$csrf),true),'access_denied');
wp_set_current_user($owner->ID);$result=Service::admin_action((int)$a['id'],'message',['revision'=>1,'visibility'=>'internal','body'=>'SECRET INTERNAL NOTE']);
$a=Store::by_id((int)$a['id']);$dto=Service::guest($a);check(!str_contains(wp_json_encode($dto),'SECRET INTERNAL NOTE'),'notes absent from guest DTO');check(!isset($dto['abuse_reason_codes'])&&!isset($dto['assigned_to']),'guest DTO excludes staff metadata');
check($dto['messages'][0]['body']===$d['body'],'Unicode emoji SQL and HTML text preserved');
wp_set_current_user(0);Service::guest_write($a['public_id'],guest_request($a,$csrf));$a=Store::by_id((int)$a['id']);check($a['status']==='open','guest reply opens ticket');
wp_set_current_user($owner->ID);denied(fn()=>Service::admin_action((int)$a['id'],'update',['revision'=>1,'status'=>'closed']),'revision_conflict');
Service::admin_action((int)$a['id'],'update',['revision'=>$a['revision'],'status'=>'closed']);$a=Store::by_id((int)$a['id']);check($a['purge_at']===Service::month($a['closed_at'],6),'close starts calendar retention');denied(fn()=>Service::guest_write($a['public_id'],guest_request($a,$csrf)),'ticket_closed');
denied(fn()=>Service::admin_action((int)$a['id'],'update',['revision'=>$a['revision'],'status'=>'open']),'invalid_fields');
Service::admin_action((int)$a['id'],'update',['revision'=>$a['revision'],'status'=>'open','reason'=>'Synthetic reopen reason']);$a=Store::by_id((int)$a['id']);check(!$a['purge_at'],'reopen cancels purge');
check(Service::month('2023-08-31 12:34:56',6)==='2024-02-29 12:34:56','leap-year month clamp');check(Service::month('2024-08-31 12:34:56',6)==='2025-02-28 12:34:56','non-leap month clamp');
clear_abuse();$pd=data(true);$privacy=Store::ticket(Service::create($pd,true)['public_id']);check(Service::has_privacy((int)$privacy['id']),'privacy received metadata');
wp_set_current_user($agent);check(!Security::can_view($privacy),'agent cannot view privacy ticket');check(!Security::can_view($a),'agent cannot view unassigned general ticket');
Store::db()->update(Store::table('tickets'),['assigned_to'=>$agent],['id'=>$a['id']]);$a=Store::by_id((int)$a['id']);check(Security::can_view($a),'agent can view assigned general ticket');denied(fn()=>Service::admin_action((int)$a['id'],'update',['revision'=>$a['revision'],'category'=>'privacy']),'access_denied');$rows=Service::admin_list(new WP_REST_Request('GET'));check(count($rows)===1 && (int)$rows[0]['id']===(int)$a['id'],'list scope excludes privacy and unassigned tickets');
denied(fn()=>Service::admin_action((int)$privacy['id'],'export',['revision'=>1]),'access_denied');
wp_set_current_user($owner->ID);$a=Store::by_id((int)$a['id']);
denied(fn()=>Service::admin_action((int)$a['id'],'update',['revision'=>$a['revision'],'privacy'=>['hold_reason'=>'Necessary evidence']]),'hold_review_required');
Service::admin_action((int)$a['id'],'update',['revision'=>$a['revision'],'privacy'=>['hold_reason'=>'Necessary evidence','hold_review_at'=>gmdate('Y-m-d H:i:s',time()+86400)]]);$a=Store::by_id((int)$a['id']);
$rotation=Service::admin_action((int)$a['id'],'rotate',['revision'=>$a['revision']]);check(strlen($rotation['key'])===43,'rotation generates 32-byte key');denied(fn()=>Service::exchange(['ticket'=>$a['public_id'],'key'=>$d['secret']]),'access_denied');denied(fn()=>Security::session($a,guest_request($a,$csrf),true),'access_denied');
$a=Store::by_id((int)$a['id']);$export=Service::admin_action((int)$a['id'],'export',['revision'=>$a['revision']]);check(!str_contains(wp_json_encode($export),$rotation['key']) && !str_contains(wp_json_encode($export),$d['secret']),'exports contain no raw access secrets');
clear_abuse();browser($browser);$one=data();Service::create($one,false);$two=data();Service::create($two,false);$three=data();denied(fn()=>Service::create($three,false),'rate_limited');
browser();$four=data();denied(fn()=>Service::create($four,false),'rate_limited');$independent=data(true);check((bool)Service::create($independent,true),'privacy quotas independent of general cooldown and cookie reset');
clear_abuse();$s=Store::settings();$s['manual_attack']=true;$s['quarantine_capacity']=1;update_option('ktickets_settings',$s,false);browser();$q=data();$qt=Store::ticket(Service::create($q,false)['public_id']);check($qt['moderation_state']==='quarantine','attack mode quarantines accepted general ticket');
$_SERVER['REMOTE_ADDR']='192.0.2.11';browser();$q2=data();denied(fn()=>Service::create($q2,false),'quarantine_full');$p2=data(true);check((bool)Service::create($p2,true),'privacy intake survives full general quarantine');
$s['manual_attack']=false;update_option('ktickets_settings',$s,false);clear_abuse();
$a=Store::by_id((int)$a['id']);Store::db()->update(Store::table('tickets'),['status'=>'closed','purge_at'=>'2020-01-01 00:00:00'],['id'=>$a['id']]);Service::cleanup();check((bool)Store::by_id((int)$a['id']),'hold prevents deletion including expired review date');
Store::db()->update(Store::table('privacy'),['hold_reason'=>'','hold_review_at'=>null],['ticket_id'=>$a['id']]);Service::cleanup();check(!Store::by_id((int)$a['id']),'purge deletes ticket');
foreach (['messages','access','sessions','events','privacy','replays'] as $name) { $table=Store::table($name);check(!(int)Store::db()->get_var(Store::db()->prepare("SELECT COUNT(*) FROM $table WHERE ticket_id=%d",$a['id'])),'purge removes '.$name); }
// REST permission and direct bypass checks.
do_action('rest_api_init');wp_set_current_user(0);$bad=new WP_REST_Request('POST','/ktickets/v1/tickets');$bad->set_header('origin',test_origin());$bad->set_header('content-type','application/json');$bad->set_body(wp_json_encode(['project'=>'soft1-code-assistant']));$response=rest_do_request($bad);check($response->get_status()===400,'direct REST rejects missing form/key/fields');
$bad->set_header('origin','https://evil.test');check(rest_do_request($bad)->get_status()===403,'REST rejects cross-origin mutation');
$bad->set_header('origin',test_origin());$bad->set_body(str_repeat('x',100001));check(rest_do_request($bad)->get_status()===413,'REST payload bound');
$admin=new WP_REST_Request('GET','/ktickets/v1/admin/tickets');check(rest_do_request($admin)->get_status()===403,'REST admin rejects guest');
// Genuine parallel workers boot independent WordPress connections.
clear_abuse();$s=Store::settings();$s['manual_attack']=false;$s['attack_valid']=1000;update_option('ktickets_settings',$s,false);$_SERVER['REMOTE_ADDR']='192.0.2.55';$browser=browser();$concurrencyData=data();
$fixture=['browser'=>$_COOKIE['kt_browser'],'data'=>$concurrencyData];$path=tempnam(sys_get_temp_dir(),'kt-fixture');file_put_contents($path,wp_json_encode($fixture));
function parallel(string $file,string $mode): array {
    $workers=[];$script=__DIR__.'/worker.php';
    for ($i=0;$i<8;$i++) { $pipes=[];$p=proc_open([PHP_BINARY,$script,$file,$mode,(string)$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes]; }
    $results=[];foreach ($workers as [$p,$pipes]) { $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if ($exit!==0) { throw new RuntimeException('Worker failure: '.$err.$out); }$results[]=json_decode($out,true); }return $results;
}
$results=parallel($path,'replay');$ids=array_unique(array_column($results,'public_id'));check(count($ids)===1 && count(array_filter($results,fn($v)=>isset($v['public_id'])))===8,'8 concurrent retries create exactly one ticket');
clear_abuse();$fixture['data']=data();$fixture['browser']=$_COOKIE['kt_browser'];file_put_contents($path,wp_json_encode($fixture));$results=parallel($path,'quota');$ok=count(array_filter($results,fn($v)=>isset($v['public_id'])));check($ok===2,'8 concurrent distinct submissions cannot exceed quota 2');unlink($path);

// Concurrent quarantine capacity across independent networks and browsers.
clear_abuse();$s=Store::settings();$s['manual_attack']=true;$s['quarantine_capacity']=1;update_option('ktickets_settings',$s,false);
Store::sql("UPDATE ".Store::table('tickets')." SET moderation_state='normal'");$fixture=['browser'=>$_COOKIE['kt_browser'],'data'=>data()];$path=tempnam(sys_get_temp_dir(),'kt-fixture');file_put_contents($path,wp_json_encode($fixture));
$results=parallel($path,'quarantine');check(count(array_filter($results,fn($v)=>isset($v['public_id'])))===1,'8 independent concurrent requests reserve only one quarantine slot');
$s['manual_attack']=false;update_option('ktickets_settings',$s,false);Store::db()->update(Store::table('tickets'),['purge_at'=>'2000-01-01 00:00:00','status'=>'closed'],['id'=>$qt['id']]);$results=parallel($path,'cleanup');check(!Store::by_id((int)$qt['id']),'8 concurrent cleanup calls purge safely');unlink($path);
check($GLOBALS['ticket_outbound']===0,'ticket flows make no email or outbound HTTP calls');

// Attack entry/quiet-exit, expiration and daily key overlap.
clear_abuse();$s=Store::settings();$s['attack_valid']=2;update_option('ktickets_settings',$s,false);
Security::serialized(function () { Security::attempt(false);Security::attempt(false); });
check(Security::attack(),'automatic attack threshold');$state=get_option('ktickets_attack');check($state['until']>=time()+2398,'quiet exit starts after rolling threshold falls');
Store::sql("DELETE FROM ".Store::table('abuse'));update_option('ktickets_attack',['until'=>time()-1],false);check(!Security::attack(),'attack quiet interval ends');
$keys=Security::networks();Security::serialized(function () use ($keys) { Security::record($keys[1],'overlap',86400); });check(Security::count($keys,'overlap')===1,'previous daily key included in rolling quota');
Store::db()->update(Store::table('abuse'),['expires_at'=>'2000-01-01 00:00:00'],['scoped_hash'=>$keys[1]]);check(Security::count($keys,'overlap')===0,'expired abuse records ignored');
clear_abuse();
// Private requests raised on a general ticket disappear from agent lists and enter privacy queue.
wp_set_current_user($owner->ID);$b=Store::by_id((int)$b['id']);Store::db()->update(Store::table('tickets'),['assigned_to'=>$agent],['id'=>$b['id']]);$b=Store::by_id((int)$b['id']);
$raw=Security::random();$bcsrf=Security::random();Store::insert('sessions',['session_hash'=>Security::hash($raw),'ticket_id'=>$b['id'],'csrf_hash'=>Security::hash($bcsrf),'expires_at'=>gmdate('Y-m-d H:i:s',time()+43200)]);$_COOKIE['kt_s_'.$b['public_id']]=$raw;
Service::guest_write($b['public_id'],guest_request($b,$bcsrf,'Please erase personal data for this request.'),true);$b=Store::by_id((int)$b['id']);
wp_set_current_user($agent);check(!Security::can_view($b),'later privacy request revokes general agent access');wp_set_current_user($owner->ID);$filter=new WP_REST_Request('GET');$filter->set_param('category','privacy');$privacyIds=array_column(Service::admin_list($filter),'id');check(in_array($b['id'],$privacyIds),'later privacy request appears in privacy queue');
$req=guest_request($b,$bcsrf);$req->set_param('ticket',$b['public_id']);$logout=REST::run(function () use ($req) { $s=Security::session(Store::ticket($req->get_param('ticket')),$req,true);Store::sql(Store::db()->prepare('DELETE FROM '.Store::table('sessions').' WHERE session_hash=%s',$s['session_hash']));return ['ok'=>true]; });check(!is_wp_error($logout),'logout revokes one session');denied(fn()=>Security::session($b,guest_request($b,$bcsrf),false),'access_denied');
// Query error output is suppressed during sensitive writes, and failure is controlled.
clear_abuse();$rt=Store::by_id((int)$privacy['id']);$rr=Security::random();$rc=Security::random();Store::insert('sessions',['session_hash'=>Security::hash($rr),'ticket_id'=>$rt['id'],'csrf_hash'=>Security::hash($rc),'expires_at'=>gmdate('Y-m-d H:i:s',time()+43200)]);$_COOKIE['kt_s_'.$rt['public_id']]=$rr;
for ($i=0;$i<5;$i++) { $rt=Store::by_id((int)$rt['id']);Service::guest_write($rt['public_id'],guest_request($rt,$rc,'Quota synthetic reply '.$i)); }
$rt=Store::by_id((int)$rt['id']);denied(fn()=>Service::guest_write($rt['public_id'],guest_request($rt,$rc,'Sixth reply must not persist')),'rate_limited');
Store::db()->update(Store::table('sessions'),['expires_at'=>'2000-01-01 00:00:00'],['session_hash'=>Security::hash($rr)]);denied(fn()=>Security::session($rt,guest_request($rt,$rc),false),'access_denied');

$beforeSuppress=Store::db()->suppress_errors;
try { Security::serialized(function () { Store::sql('SELECT missing_column FROM '.Store::table('tickets')); }); } catch (RuntimeException $e) { check($e->getMessage()==='storage_unavailable','database failure is controlled'); }
check(Store::db()->suppress_errors===$beforeSuppress,'database error suppression restored');
check(wp_next_scheduled('ktickets_cleanup')!==false,'cleanup scheduled');
// Deactivation and uninstall lifecycle.
do_action('deactivate_kourentzes-tickets/kourentzes-tickets.php');check(!wp_next_scheduled('ktickets_cleanup'),'deactivation unschedules cleanup');check((bool)Store::by_id((int)$b['id']),'deactivation retains data');
if (!defined('WP_UNINSTALL_PLUGIN')) { define('WP_UNINSTALL_PLUGIN','kourentzes-tickets/kourentzes-tickets.php'); }
require dirname(__DIR__).'/uninstall.php';check((bool)Store::by_id((int)$b['id']),'uninstall preserves data by default');
$s=Store::settings();$s['delete_on_uninstall']=true;update_option('ktickets_settings',$s,false);require dirname(__DIR__).'/uninstall.php';
check(!get_option('ktickets_schema'),'explicit uninstall removes plugin options');check(!user_can($owner->ID,'view_ktickets'),'explicit uninstall removes user capabilities');
check(!Store::db()->get_var(Store::db()->prepare('SHOW TABLES LIKE %s',Store::db()->esc_like(Store::table('tickets')))),'explicit uninstall drops custom tables');
Store::install();

ob_end_clean();echo 'PASS '.count($GLOBALS['passes'])." assertions\n";foreach ($GLOBALS['passes'] as $pass) { echo "  ✓ $pass\n"; }
