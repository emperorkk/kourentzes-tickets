<?php
namespace KTickets;
if (!defined('ABSPATH')) { exit; }

final class Service {
    public const STATUSES=['open','in_progress','waiting_requester','resolved','closed'];
    public const TYPES=['access','rectification','erasure','restriction','objection','portability','other'];
    public static function text($value, int $min, int $max): string {
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || str_contains($value,"\0")) { Security::fail('invalid_fields'); }
        $value=str_replace(["\r\n","\r"],"\n",trim($value));
        if (mb_strlen($value)<$min || mb_strlen($value)>$max) { Security::fail('invalid_fields'); }
        return $value;
    }
    public static function project(string $slug): array {
        $p=Store::table('projects');
        $row=Store::db()->get_row(Store::db()->prepare("SELECT * FROM $p WHERE slug=%s AND enabled=1",$slug),ARRAY_A);
        if (!$row) { Security::fail('invalid_project'); } return $row;
    }
    public static function month(string $date, int $months): string {
        $d=new \DateTimeImmutable($date,new \DateTimeZone('UTC'));
        $base=$d->modify('first day of this month')->modify('+'.$months.' months');
        $day=min((int)$d->format('d'),(int)$base->format('t'));
        return $base->setDate((int)$base->format('Y'),(int)$base->format('m'),$day)->format('Y-m-d H:i:s');
    }
    public static function has_privacy(int $id): bool {
        $p=Store::table('privacy');
        return (bool)Store::db()->get_var(Store::db()->prepare("SELECT ticket_id FROM $p WHERE ticket_id=%d AND received_at IS NOT NULL",$id));
    }
    public static function create(array $d, bool $privacy): array {
        $browser=Security::browser(); $project=self::project(self::text($d['project']??'',1,80));
        $category=$privacy?'privacy':self::text($d['category']??'',1,24);
        if ((!$privacy && $category==='privacy') || !in_array($category,(array)json_decode($project['allowed_categories'],true),true)) { Security::fail('invalid_category'); }
        $subject=self::text($d['subject']??'',5,160); $body=self::text($d['body']??'',20,20000); $alias=self::text($d['alias']??'',0,80);
        $environment=[];
        foreach (['plugin_version','soft1_version','runtime'] as $field) { $environment[$field]=$privacy?'':self::text($d[$field]??'',0,160); }
        if (($d['acknowledge']??null)!==true || !preg_match('/^[A-Za-z0-9_-]{43}$/D',(string)($d['secret']??'')) || !preg_match('/^[A-Za-z0-9_-]{22,64}$/D',(string)($d['idempotency']??''))) { Security::fail('invalid_fields'); }
        $form=Security::verify_form((string)($d['form_token']??''),$project['slug'],$privacy,$browser);
        $type=$privacy?(string)($d['request_type']??'other'):'other';
        if (!in_array($type,self::TYPES,true)) { Security::fail('invalid_fields'); }
        $payload=Security::hmac(wp_json_encode([$project['id'],$category,$subject,$body,$alias,$environment,$type,Security::hash($d['secret'])]));
        $replay=Security::hmac('replay:'.$browser.':'.$d['idempotency']);
        return Security::serialized(static function () use ($d,$privacy,$browser,$project,$category,$subject,$body,$alias,$environment,$type,$form,$payload,$replay) {
            $r=Store::table('replays'); $db=Store::db();
            $old=$db->get_row($db->prepare("SELECT * FROM $r WHERE replay_hash=%s AND expires_at>%s",$replay,Store::now()),ARRAY_A);
            if ($old) {
                if (!hash_equals($old['payload_hash'],$payload)) { Security::fail('idempotency_conflict',409); }
                $t=Store::by_id((int)$old['ticket_id']); if (!$t) { Security::fail('access_denied',403); }
                return self::receipt($t);
            }
            if (Security::count([Security::hmac('form:'.$form['n'])],'used')) { Security::fail('form_expired',403); }
            if (!$privacy) { Security::attempt(false); }
            $attack=!$privacy && Security::attack();
            Security::creation_limits($privacy?'privacy-':'general-',$browser,$attack);
            $tickets=Store::table('tickets');
            if ($attack && (int)$db->get_var("SELECT COUNT(*) FROM $tickets WHERE moderation_state='quarantine' AND category<>'privacy'") >= (int)Store::settings()['quarantine_capacity']) { Security::fail('quarantine_full',503); }
            $reasons=[];
            if (Security::count(Security::networks(),($privacy?'privacy-':'general-').'short')>1) { $reasons[]='frequent_submissions'; }
            if (!empty($d['website'])) { $reasons[]='honeypot'; }
            if (time()-$form['t']<3) { $reasons[]='fast_completion'; }
            if (substr_count(strtolower($body),'http')>8) { $reasons[]='link_density'; }
            $fingerprint=Security::hmac('content:'.$category.':'.$subject.':'.$body);
            if (Security::count([$fingerprint],'duplicate')) { $reasons[]='duplicate_content'; }
            if ($attack) { $reasons[]='attack_mode'; }
            return Store::transaction(static function () use ($d,$privacy,$project,$category,$subject,$body,$alias,$environment,$type,$form,$payload,$replay,$attack,$reasons,$fingerprint) {
                $now=Store::now();
                $id=Store::insert('tickets',['public_id'=>bin2hex(random_bytes(16)),'project_id'=>$project['id'],'category'=>$category,'subject'=>$subject,'status'=>'open','moderation_state'=>$attack?'quarantine':'normal','abuse_flag'=>(int)(bool)$reasons,'abuse_reason_codes'=>wp_json_encode($reasons),'alias'=>$alias,'environment'=>wp_json_encode($environment),'created_at'=>$now,'updated_at'=>$now,'revision'=>1]);
                Store::insert('messages',['ticket_id'=>$id,'author_type'=>'guest','visibility'=>'public','body'=>$body,'created_at'=>$now]);
                Store::insert('access',['ticket_id'=>$id,'secret_hash'=>Security::hash($d['secret']),'created_at'=>$now]);
                if ($privacy) { self::privacy_row($id,$type); }
                Store::insert('replays',['replay_hash'=>$replay,'payload_hash'=>$payload,'ticket_id'=>$id,'expires_at'=>gmdate('Y-m-d H:i:s',time()+900)]);
                Security::record(Security::hmac('form:'.$form['n']),'used',900);
                Security::record($fingerprint,'duplicate',600);
                Store::audit($id,'created');
                return self::receipt(Store::by_id($id));
            });
        });
    }
    public static function receipt(array $t): array {
        return ['public_id'=>$t['public_id'],'moderation_state'=>$t['moderation_state'],'portal_url'=>get_permalink((int)Store::settings()['portal_page'])];
    }
    public static function privacy_row(int $id, string $type): void {
        Store::insert('privacy',['ticket_id'=>$id,'request_type'=>$type,'received_at'=>Store::now(),'target_due_at'=>self::month(Store::now(),1),'verification_status'=>'unverified','extension_reason'=>'','outcome'=>'','hold_reason'=>'']);
    }
    public static function exchange(array $d): array {
        return Security::serialized(static function () use ($d) {
            $network=Security::networks();
            if (Security::count($network,'exchange-failed')>=10) { Security::fail('rate_limited',429); }
            Security::quota($network,'exchange-all',60,30);
            $t=Store::ticket((string)($d['ticket']??'')); $a=Store::table('access');
            $access=$t?Store::db()->get_row(Store::db()->prepare("SELECT * FROM $a WHERE ticket_id=%d AND revoked_at IS NULL",$t['id']),ARRAY_A):null;
            $hash=Security::hash((string)($d['key']??''));
            if (!$access || !hash_equals($access['secret_hash'],$hash)) { Security::record($network[0],'exchange-failed',600); Security::fail('access_denied',403); }
            $sessions=Store::table('sessions');
            $previous=(string)($_COOKIE['kt_s_'.$t['public_id']]??'');
            if (preg_match('/^[A-Za-z0-9_-]{43}$/D',$previous)) { Store::sql(Store::db()->prepare("DELETE FROM $sessions WHERE ticket_id=%d AND session_hash=%s",$t['id'],Security::hash($previous))); }
            Store::sql(Store::db()->prepare("DELETE FROM $sessions WHERE expires_at<=%s",Store::now()));
            $total=(int)Store::db()->get_var(Store::db()->prepare("SELECT COUNT(*) FROM $sessions WHERE ticket_id=%d",$t['id']));
            if ($total>=20) { Security::fail('session_capacity',429); }
            $session=Security::random(); $csrf=Security::random();
            Store::insert('sessions',['session_hash'=>Security::hash($session),'ticket_id'=>$t['id'],'csrf_hash'=>Security::hash($csrf),'expires_at'=>gmdate('Y-m-d H:i:s',time()+43200)]);
            Security::cookie('kt_s_'.$t['public_id'],$session,time()+43200);
            return ['public_id'=>$t['public_id'],'csrf'=>$csrf];
        });
    }
    public static function guest(array $t, int $before=0): array {
        $m=Store::table('messages'); $args=[$t['id']]; $where='';
        if ($before>0) { $where=' AND id<%d'; $args[]=$before; }
        $messages=Store::db()->get_results(Store::db()->prepare("SELECT id,author_type,body,created_at FROM $m WHERE ticket_id=%d AND visibility='public'$where ORDER BY id DESC LIMIT 21",...$args),ARRAY_A);
        $more=count($messages)>20; $messages=array_slice($messages,0,20);
        return ['public_id'=>$t['public_id'],'subject'=>$t['subject'],'status'=>$t['status'],'moderation_state'=>$t['moderation_state'],'purge_at'=>$t['purge_at'],'revision'=>(int)$t['revision'],'messages'=>array_reverse($messages),'next_before'=>$more?(int)end($messages)['id']:null];
    }
    public static function guest_write(string $public, \WP_REST_Request $r, bool $privacy=false): array {
        return Security::serialized(static function () use ($public,$r,$privacy) {
            return Store::transaction(static function () use ($public,$r,$privacy) {
                $t=Store::ticket($public,true); if (!$t) { Security::fail('access_denied',403); }
                $session=Security::session($t,$r,true);
                if ($t['status']==='closed') { Security::fail('ticket_closed',409); }
                $body=self::text($r->get_param('body')??'',1,20000);
                Security::quota([$session['session_hash']],'reply-minute',60,5);
                Security::quota([Security::hmac('ticket:'.$public)],'reply-ten',600,20);
                Security::quota(Security::networks(),'reply-network',600,(int)Store::settings()['network_reply']);
                if ((int)$r->get_param('revision')!==(int)$t['revision']) { Security::fail('revision_conflict',409); }
                if ($privacy && !self::has_privacy((int)$t['id'])) {
                    $p=Store::table('privacy');
                    // A general ticket may already have a retention-hold row.
                    $existing=Store::db()->get_var(Store::db()->prepare("SELECT ticket_id FROM $p WHERE ticket_id=%d",$t['id']));
                    if ($existing) { Store::sql(Store::db()->prepare("UPDATE $p SET received_at=%s,request_type='erasure',target_due_at=%s WHERE ticket_id=%d",Store::now(),self::month(Store::now(),1),$t['id'])); }
                    else { self::privacy_row((int)$t['id'],'erasure'); }
                }
                Store::insert('messages',['ticket_id'=>$t['id'],'author_type'=>'guest','visibility'=>'public','body'=>$body,'created_at'=>Store::now()]);
                $table=Store::table('tickets');
                Store::sql(Store::db()->prepare("UPDATE $table SET status='open',revision=revision+1,updated_at=%s WHERE id=%d",Store::now(),$t['id']));
                Store::audit((int)$t['id'],$privacy?'privacy_requested':'guest_reply');
                return ['ok'=>true];
            });
        });
    }
    public static function scope(): string {
        $sql='1=1';
        $p=Store::table('privacy');
        if (!current_user_can('view_ktickets_privacy')) { $sql.=" AND t.category<>'privacy' AND NOT EXISTS (SELECT 1 FROM $p p WHERE p.ticket_id=t.id AND p.received_at IS NOT NULL)"; }
        if (!current_user_can('view_all_ktickets')) { $sql.=Store::db()->prepare(' AND t.assigned_to=%d',get_current_user_id()); }
        return $sql;
    }
    public static function admin_list(\WP_REST_Request $r): array {
        $db=Store::db(); $t=Store::table('tickets'); $where=self::scope();
        foreach (['status','category','moderation_state'] as $field) {
            $v=$r->get_param($field);
            if ($field==='category' && $v==='privacy') { $p=Store::table('privacy');$where.=" AND (t.category='privacy' OR EXISTS (SELECT 1 FROM $p p WHERE p.ticket_id=t.id AND p.received_at IS NOT NULL))";continue; }
            if (is_string($v) && strlen($v)<30 && $v!=='') { $where.=$db->prepare(" AND t.$field=%s",$v); }
        }
        $search=$r->get_param('search');
        if (is_string($search) && $search!=='' && mb_strlen($search)<=160) { $where.=$db->prepare(' AND (t.subject LIKE %s OR t.public_id=%s)','%'.$db->esc_like($search).'%',$search); }
        $page=max(1,min(10000,(int)($r->get_param('page')?:1)));
        return $db->get_results($db->prepare("SELECT t.* FROM $t t WHERE $where ORDER BY t.updated_at DESC,t.id DESC LIMIT 20 OFFSET %d",($page-1)*20),ARRAY_A);
    }
    public static function detail(array $t, int $before=0): array {
        $m=Store::table('messages');$p=Store::table('privacy');$e=Store::table('events');
        $t['messages']=Store::db()->get_results(Store::db()->prepare("SELECT * FROM $m WHERE ticket_id=%d AND (%d=0 OR id<%d) ORDER BY id DESC LIMIT 20",$t['id'],$before,$before),ARRAY_A);
        $t['privacy']=current_user_can('manage_ktickets_privacy')?Store::db()->get_row(Store::db()->prepare("SELECT * FROM $p WHERE ticket_id=%d",$t['id']),ARRAY_A):null;
        $t['events']=Store::db()->get_results(Store::db()->prepare("SELECT event_type,actor_user_id,created_at,redacted_metadata FROM $e WHERE ticket_id=%d ORDER BY id DESC LIMIT 30",$t['id']),ARRAY_A);
        return $t;
    }
    public static function admin_action(int $id, string $action, array $d): array {
        return Security::serialized(static function () use ($id,$action,$d) {
            return Store::transaction(static function () use ($id,$action,$d) {
                $table=Store::table('tickets'); $db=Store::db();
                $t=$db->get_row($db->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$id),ARRAY_A);
                $cap=match($action) { 'delete'=>'delete_ktickets','rotate','export'=>'manage_ktickets_settings',default=>'reply_ktickets' };
                if (!$t || !Security::can_manage($t,$cap)) { Security::fail('access_denied',403); }
                if ((int)($d['revision']??0)!==(int)$t['revision']) { Security::fail('revision_conflict',409); }
                if ($action==='delete') {
                    if (($d['confirm']??false)!==true) { Security::fail('confirmation_required'); }
                    Store::audit($id,'deleted'); Store::purge($id); return ['ok'=>true];
                }
                if ($action==='rotate') {
                    $raw=Security::random(); $a=Store::table('access');$s=Store::table('sessions');
                    Store::sql($db->prepare("UPDATE $a SET secret_hash=%s,revoked_at=NULL,created_at=%s WHERE ticket_id=%d",Security::hash($raw),Store::now(),$id));
                    Store::sql($db->prepare("DELETE FROM $s WHERE ticket_id=%d",$id));
                    Store::audit($id,'rotated');
                    Store::sql($db->prepare("UPDATE $table SET revision=revision+1 WHERE id=%d",$id));
                    return ['public_id'=>$t['public_id'],'key'=>$raw,'portal_url'=>get_permalink((int)Store::settings()['portal_page'])];
                }
                if ($action==='export') {
                    Store::audit($id,'exported');
                    // Bounded export pages, no access/session/abuse identifiers.
                    return self::detail($t,max(0,(int)($d['before']??0)));
                }
                $changes=['updated_at'=>Store::now(),'revision'=>(int)$t['revision']+1];
                if ($action==='message') {
                    $visibility=($d['visibility']??'public');
                    if (!in_array($visibility,['public','internal'],true)) { Security::fail('invalid_fields'); }
                    if ($t['status']==='closed' && $visibility==='public') { Security::fail('ticket_closed',409); }
                    Store::insert('messages',['ticket_id'=>$id,'author_type'=>'staff','author_user_id'=>get_current_user_id(),'visibility'=>$visibility,'body'=>self::text($d['body']??'',1,20000),'created_at'=>Store::now()]);
                    if ($visibility==='public') {
                        $status=$d['status']??'waiting_requester';
                        if (!in_array($status,['in_progress','waiting_requester'],true)) { Security::fail('invalid_status'); }
                        $changes['status']=$status;
                    }
                    Store::audit($id,$visibility==='internal'?'internal_note':'staff_reply');
                } else {
                    if (isset($d['status'])) {
                        $status=$d['status']; if (!in_array($status,self::STATUSES,true)) { Security::fail('invalid_status'); }
                        if ($t['status']==='closed' && $status!=='closed') {
                            $reason=self::text($d['reason']??'',5,500); $changes['closed_at']=null; $changes['purge_at']=null; Store::audit($id,'reopened',['reason'=>$reason]);
                        }
                        if ($status==='closed' && $t['status']!=='closed') { $changes['closed_at']=Store::now();$changes['purge_at']=self::month(Store::now(),6); }
                        $changes['status']=$status;
                    }
                    if (isset($d['category']) && $d['category']!==$t['category']) {
                        $projects=Store::table('projects');$allowed=json_decode((string)$db->get_var($db->prepare("SELECT allowed_categories FROM $projects WHERE id=%d",$t['project_id'])),true);
                        if (!in_array($d['category'],(array)$allowed,true)) { Security::fail('invalid_category'); }
                        if (($d['category']==='privacy' || $t['category']==='privacy') && !current_user_can('manage_ktickets_privacy')) { Security::fail('access_denied',403); }
                        if ($d['category']==='privacy' && !self::has_privacy($id)) {
                            $p=Store::table('privacy');$existing=$db->get_var($db->prepare("SELECT ticket_id FROM $p WHERE ticket_id=%d",$id));
                            if ($existing) { Store::sql($db->prepare("UPDATE $p SET received_at=%s,target_due_at=%s WHERE ticket_id=%d",Store::now(),self::month(Store::now(),1),$id)); }
                            else { self::privacy_row($id,'other'); }
                        }
                        $changes['category']=$d['category'];
                    }
                    if (isset($d['assigned_to'])) {
                        if (!current_user_can('assign_ktickets')) { Security::fail('access_denied',403); }
                        $user=(int)$d['assigned_to'];
                        if ($user && (!user_can($user,'view_ktickets') || (($t['category']==='privacy'||self::has_privacy($id))&&!user_can($user,'view_ktickets_privacy')))) { Security::fail('invalid_assignee'); }
                        $changes['assigned_to']=$user?:null;
                    }
                    if (isset($d['abuse_flag'])) { $changes['abuse_flag']=(int)(bool)$d['abuse_flag']; }
                    if (isset($d['moderation_state'])) { if (!in_array($d['moderation_state'],['normal','quarantine'],true) || $t['category']==='privacy') { Security::fail('invalid_fields'); } $changes['moderation_state']=$d['moderation_state']; }
                    if (isset($d['privacy'])) { self::update_privacy($id,$d['privacy']); }
                    Store::audit($id,'updated',['fields'=>array_keys($changes)]);
                }
                if ($db->update($table,$changes,['id'=>$id])===false) { throw new \RuntimeException('storage_unavailable'); }
                return ['ok'=>true,'revision'=>$changes['revision']];
            });
        });
    }
    public static function update_privacy(int $id, array $d): void {
        if (!current_user_can('manage_ktickets_privacy')) { Security::fail('access_denied',403); }
        $p=Store::table('privacy'); $db=Store::db();
        $old=$db->get_row($db->prepare("SELECT * FROM $p WHERE ticket_id=%d",$id),ARRAY_A);
        if (!$old) { Store::insert('privacy',['ticket_id'=>$id,'request_type'=>'other','verification_status'=>'unverified','extension_reason'=>'','outcome'=>'','hold_reason'=>'']); }
        $changes=[];
        foreach (['extension_reason','outcome','hold_reason'] as $field) { if (isset($d[$field])) { $changes[$field]=self::text($d[$field],0,2000); } }
        if (isset($d['request_type'])) { if (!in_array($d['request_type'],self::TYPES,true)) { Security::fail('invalid_fields'); }$changes['request_type']=$d['request_type']; }
        if (isset($d['verification_status'])) { if (!in_array($d['verification_status'],['unverified','pending','verified','not_required'],true)) { Security::fail('invalid_fields'); }$changes['verification_status']=$d['verification_status']; }
        foreach (['target_due_at','hold_review_at'] as $field) {
            if (isset($d[$field])) {
                if ($d[$field]!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$d[$field]) || !strtotime($d[$field].' UTC'))) { Security::fail('invalid_date'); }
                $changes[$field]=$d[$field]?:null;
            }
        }
        if (isset($changes['hold_reason'])) {
            $review=$changes['hold_review_at']??null;
            if ($changes['hold_reason']!=='' && (!$review || strtotime($review.' UTC')<=time())) { Security::fail('hold_review_required'); }
            $changes['hold_actor']=$changes['hold_reason']!==''?get_current_user_id():null;
            if ($changes['hold_reason']==='') { $changes['hold_review_at']=null; }
        }
        if ($changes && $db->update($p,$changes,['ticket_id'=>$id])===false) { throw new \RuntimeException('storage_unavailable'); }
        Store::audit($id,'privacy_updated',['fields'=>array_keys($changes)]);
    }
    public static function cleanup(): void {
        try {
            Security::serialized(static function () {
                Store::transaction(static function () {
                    $db=Store::db(); $t=Store::table('tickets');$p=Store::table('privacy');
                    $ids=$db->get_col($db->prepare("SELECT t.id FROM $t t LEFT JOIN $p p ON p.ticket_id=t.id WHERE t.purge_at<=%s AND (p.hold_reason IS NULL OR p.hold_reason='') ORDER BY t.purge_at LIMIT 20 FOR UPDATE",Store::now()));
                    if ($db->last_error) { throw new \RuntimeException('cleanup_failed'); }
                    foreach ($ids as $id) { Store::purge((int)$id); }
                    foreach (['abuse','replays','sessions'] as $name) { $table=Store::table($name);$limit=$name==='abuse'?10000:1000; Store::sql($db->prepare("DELETE FROM $table WHERE expires_at<=%s LIMIT $limit",Store::now())); }
                });
                update_option('ktickets_health',['success'=>Store::now(),'error'=>''],false);
            });
        } catch (\Throwable $e) { $h=(array)get_option('ktickets_health',[]);$h['error']='Cleanup failed; retry and inspect database availability.';update_option('ktickets_health',$h,false); }
    }
}
