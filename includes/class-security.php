<?php
namespace KTickets;
if (!defined('ABSPATH')) { exit; }

final class Security {
    public static function random(int $bytes=32): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }
    public static function hash(string $value): string { return hash('sha256', $value); }
    public static function hmac(string $value): string { return hash_hmac('sha256', $value, wp_salt('auth')); }
    public static function fail(string $code, int $status=400): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal machine code and typed HTTP status, caught by REST::run; not HTML output.
        throw new Failure($code, $status);
    }
    public static function secure(): bool { return is_ssl() || (defined('KTICKETS_LOCAL_DEV') && KTICKETS_LOCAL_DEV && wp_get_environment_type()==='local'); }
    public static function origin(\WP_REST_Request $r): bool {
        $origin = $r->get_header('origin');
        if (!$origin || $origin === 'null') { return false; }
        $parts = wp_parse_url(home_url());
        $expected = $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        return hash_equals(strtolower($expected), strtolower(rtrim($origin, '/')));
    }
    public static function cookie(string $name, string $value, int $expiry): void {
        $path = wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/';
        setcookie($name, $value, ['expires'=>$expiry,'path'=>$path,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Strict']);
    }
    public static function browser(bool $issue=false): string {
        $cookie = sanitize_text_field(wp_unslash($_COOKIE['kt_browser'] ?? ''));
        $parts = explode('.', $cookie);
        if (count($parts)===3 && preg_match('/^[A-Za-z0-9_-]{43}$/D', $parts[0]) && ctype_digit($parts[1]) && (int)$parts[1]>time() && hash_equals(self::hmac('browser:'.$parts[0].'.'.$parts[1]), $parts[2])) { return $parts[0]; }
        if (!$issue) { self::fail('form_expired',403); }
        $id=self::random(); $expiry=time()+86400;
        self::cookie('kt_browser', $id.'.'.$expiry.'.'.self::hmac('browser:'.$id.'.'.$expiry), $expiry);
        return $id;
    }
    // Daily derived keys overlap for a full rolling 24-hour window. Forwarded headers are never trusted.
    public static function networks(): array {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $day=(int)floor(time()/86400);
        return [self::hmac('network:'.$day.':'.$ip), self::hmac('network:'.($day-1).':'.$ip)];
    }
    public static function form(string $project, bool $privacy): array {
        $browser=self::browser(true);
        self::serialized(static function () use ($privacy) {
            self::quota(self::networks(), $privacy?'privacy-bootstrap':'general-bootstrap', 60, 20);
        });
        $data=wp_json_encode(['p'=>$project,'v'=>$privacy,'b'=>self::hash($browser),'t'=>time(),'n'=>self::random(16)]);
        $encoded=rtrim(strtr(base64_encode($data),'+/','-_'),'=');
        return ['token'=>$encoded.'.'.self::hmac('form:'.$encoded),'expires_in'=>900];
    }
    public static function verify_form(string $token, string $project, bool $privacy, string $browser): array {
        $p=explode('.', $token);
        if (count($p)!==2 || !hash_equals(self::hmac('form:'.$p[0]),$p[1])) { self::fail('form_expired',403); }
        $data=json_decode(base64_decode(strtr($p[0],'-_','+/'),true) ?: '',true);
        if (!is_array($data) || ($data['p']??null)!==$project || ($data['v']??null)!==$privacy || ($data['b']??null)!==self::hash($browser) || ($data['t']??0)<time()-900 || $data['t']>time()) { self::fail('form_expired',403); }
        return $data;
    }
    public static function serialized(callable $action) {
        $previous=Store::db()->suppress_errors(true);
        try {
            Store::lock('writes');
            $table=Store::table('abuse');
            Store::sql(Store::db()->prepare("DELETE FROM $table WHERE expires_at<=%s LIMIT 1000",Store::now()));
            return $action();
        } finally { Store::unlock('writes'); Store::db()->suppress_errors($previous); }
    }
    public static function count(array $keys, string $bucket): int {
        $t=Store::table('abuse'); $db=Store::db();
        $in=implode(',',array_fill(0,count($keys),'%s'));
        $v=$db->get_var($db->prepare("SELECT COALESCE(SUM(count),0) FROM $t WHERE scoped_hash IN ($in) AND bucket LIKE %s AND expires_at>%s", ...array_merge($keys,[$db->esc_like($bucket.':').'%',Store::now()])));
        if ($db->last_error) { throw new \RuntimeException('limiter_unavailable'); }
        return (int)$v;
    }
    public static function record(string $key, string $bucket, int $ttl, int $count=1): void {
        $t=Store::table('abuse');
        // A fixed ceiling prevents random-network attacks from growing temporary state without bound.
        $size=Store::db()->get_var("SELECT COUNT(*) FROM $t");
        if (Store::db()->last_error || (int)$size>=10000) { self::fail('temporarily_unavailable',503); }
        Store::insert('abuse',['scoped_hash'=>$key,'bucket'=>$bucket.':'.bin2hex(random_bytes(8)),'count'=>$count,'expires_at'=>gmdate('Y-m-d H:i:s',time()+min(86400,$ttl))]);
    }
    public static function quota(array $keys, string $bucket, int $seconds, int $limit): void {
        if (self::count($keys,$bucket)>=$limit) { self::fail('rate_limited',429); }
        self::record($keys[0],$bucket,$seconds);
    }
    public static function creation_limits(string $scope, string $browser, bool $attack): void {
        $s=Store::settings(); $network=self::networks(); $bk=[self::hmac('browser-quota:'.$browser)];
        foreach ([$network,$bk] as $keys) {
            if (self::count($keys,$scope.'cooldown')) { self::fail('rate_limited',429); }
            if (self::count($keys,$scope.'short') >= (int)$s['create_short'] || self::count($keys,$scope.'day') >= (int)$s['create_day'] || ($attack && self::count($keys,$scope.'attack')>=1)) {
                $strikes=self::count($keys,$scope.'strike');
                self::record($keys[0],$scope.'cooldown',min(86400,900*(2**min(7,$strikes))));
                self::record($keys[0],$scope.'strike',86400);
                self::fail('rate_limited',429);
            }
        }
        foreach ([$network,$bk] as $keys) {
            self::record($keys[0],$scope.'short',600); self::record($keys[0],$scope.'day',86400); self::record($keys[0],$scope.'attack',1800);
        }
    }
    public static function attack(): bool {
        $s=Store::settings(); $a=(array)get_option('ktickets_attack',[]);
        $table=Store::table('abuse'); $db=Store::db();
        foreach (['valid'=>'attack_valid','failed'=>'attack_failed'] as $bucket=>$setting) {
            // The Nth latest expiry is the exact moment this rolling aggregate drops below N.
            $expiry=$db->get_var($db->prepare("SELECT expires_at FROM $table WHERE scoped_hash=%s AND bucket LIKE %s AND expires_at>%s ORDER BY expires_at DESC LIMIT 1 OFFSET %d",self::hmac('aggregate'),$db->esc_like($bucket.':').'%',Store::now(),max(0,(int)$s[$setting]-1)));
            if ($db->last_error) { throw new \RuntimeException('limiter_unavailable'); }
            if ($expiry) {
                $until=strtotime($expiry.' UTC')+1800;
                if ($until>(int)($a['until']??0)) {
                    $a=['until'=>$until,'reason'=>$bucket,'at'=>(int)($a['until']??0)>time()?($a['at']??Store::now()):Store::now()];
                    update_option('ktickets_attack',$a,false);
                }
            }
        }
        return (bool)$s['manual_attack'] || (int)($a['until']??0)>time();
    }
    public static function attempt(bool $failed): void {
        self::record(self::hmac('aggregate'),$failed?'failed':'valid',$failed?300:600);
        self::attack();
    }
    public static function session(array $ticket, \WP_REST_Request $r, bool $mutation): array {
        $name='kt_s_'.$ticket['public_id']; $raw=sanitize_text_field(wp_unslash($_COOKIE[$name]??''));
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D',$raw)) { self::fail('access_denied',403); }
        $t=Store::table('sessions');
        $session=Store::db()->get_row(Store::db()->prepare("SELECT * FROM $t WHERE session_hash=%s AND ticket_id=%d AND expires_at>%s AND revoked_at IS NULL",self::hash($raw),$ticket['id'],Store::now()),ARRAY_A);
        if (!$session || ($mutation && (!self::origin($r) || !hash_equals($session['csrf_hash'],self::hash((string)$r->get_header('x-kt-csrf')))))) { self::fail('access_denied',403); }
        return $session;
    }
    public static function can_view(array $t): bool {
        if (!current_user_can('view_ktickets')) { return false; }
        if ($t['category']==='privacy' || Service::has_privacy((int)$t['id'])) { return current_user_can('view_ktickets_privacy'); }
        return current_user_can('view_all_ktickets') || (int)$t['assigned_to']===get_current_user_id();
    }
    public static function can_manage(array $t, string $cap): bool {
        return self::can_view($t) && current_user_can($cap) && (!( $t['category']==='privacy' || Service::has_privacy((int)$t['id'])) || current_user_can('manage_ktickets_privacy'));
    }
}
final class Failure extends \RuntimeException {
    public int $status;
    public function __construct(string $code,int $status) { parent::__construct($code); $this->status=$status; }
}
