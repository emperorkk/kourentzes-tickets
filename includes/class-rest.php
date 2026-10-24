<?php
namespace KTickets;
if (!defined('ABSPATH')) { exit; }

final class REST {
    public static function boot(): void {
        add_action('rest_api_init',[self::class,'routes']);
        add_filter('rest_pre_serve_request',static function ($served,$response,$request) {
            if (str_starts_with($request->get_route(),'/ktickets/v1')) {
                foreach (['Access-Control-Allow-Origin','Access-Control-Allow-Credentials','Access-Control-Allow-Methods','Access-Control-Allow-Headers','Access-Control-Expose-Headers'] as $header) { header_remove($header); }
                if (Security::origin($request)) { header('Access-Control-Allow-Origin: '.$request->get_header('origin'));header('Vary: Origin',false); }
            }
            return $served;
        },20,3);
        add_filter('rest_post_dispatch',static function ($response,$server,$request) {
            if (str_starts_with($request->get_route(),'/ktickets/v1')) {
                $response->header('Cache-Control','no-store, private, max-age=0');
                $response->header('Referrer-Policy','no-referrer');
                $response->header('X-Robots-Tag','noindex, nofollow, noarchive');
                if ($response->get_status()===429 || $response->get_status()===503) { $response->header('Retry-After','900'); }
            } return $response;
        },10,3);
        add_filter('rest_pre_dispatch',static function ($result,$server,$request) {
            if (!str_starts_with($request->get_route(),'/ktickets/v1')) { return $result; }
            if (strlen($request->get_body())>100000 || (int)($_SERVER['CONTENT_LENGTH']??0)>100000) { return new \WP_Error('payload_too_large',__('The request is too large.','kourentzes-tickets'),['status'=>413]); }
            return $result;
        },10,3);
    }
    public static function guest_permission(\WP_REST_Request $r) {
        if (!Security::secure() || get_option('ktickets_schema')!==KTICKETS_VERSION || get_option('ktickets_migration_error')) { return new \WP_Error('unavailable',__('Secure ticket access is unavailable.','kourentzes-tickets'),['status'=>503]); }
        if (($r->get_method()!=='GET' || $r->get_header('origin')) && !Security::origin($r)) { return new \WP_Error('origin_rejected',__('This request origin is not permitted.','kourentzes-tickets'),['status'=>403]); }
        return true;
    }
    public static function admin_permission(\WP_REST_Request $r) {
        if (!current_user_can('view_ktickets') || !wp_verify_nonce($r->get_header('x-wp-nonce'),'wp_rest')) { return new \WP_Error('access_denied',__('Access denied.','kourentzes-tickets'),['status'=>403]); }
        return true;
    }
    public static function intake(): void {
        $s=Store::settings();
        if (!$s['enabled'] || !$s['verified'] || !$s['owner'] || !$s['policy_url'] || get_post_status((int)$s['portal_page'])!=='publish') { Security::fail('intake_disabled',503); }
    }
    public static function run(callable $fn, bool $creation=false, bool $privacy=false) {
        $previous=Store::db()->suppress_errors(true);
        try { return rest_ensure_response($fn()); }
        catch (Failure $e) {
            if ($creation && !$privacy && !in_array($e->getMessage(),['intake_disabled','idempotency_conflict'],true)) {
                try { Security::serialized(static function () { Security::attempt(true); }); } catch (\Throwable $ignored) { /* Controlled failure below remains authoritative. */ }
            }
            return new \WP_Error($e->getMessage(),self::message($e->getMessage()),['status'=>$e->status]);
        } catch (\Throwable $e) { return new \WP_Error('temporarily_unavailable',__('The service is temporarily unavailable. Your request has not been confirmed. Retry the same submission.','kourentzes-tickets'),['status'=>503]); }
        finally { Store::db()->suppress_errors($previous); }
    }
    public static function message(string $code): string {
        $messages=[
            'access_denied'=>__('Access denied. Check your private link.','kourentzes-tickets'),
            'rate_limited'=>__('Too many requests. Wait before trying again. Your request has not been received.','kourentzes-tickets'),
            'intake_disabled'=>__('New submissions are temporarily disabled.','kourentzes-tickets'),
            'form_expired'=>__('The form expired or has already been used. Reload the form before starting a new request.','kourentzes-tickets'),
            'ticket_closed'=>__('This ticket is closed and read-only. Please submit a new request.','kourentzes-tickets'),
            'revision_conflict'=>__('This ticket changed. Reload before saving.','kourentzes-tickets'),
            'idempotency_conflict'=>__('This retry differs from the original submission. Restore the original values or start a new request.','kourentzes-tickets'),
            'quarantine_full'=>__('The review queue is full. Your request has not been received. Please try again later. The separate privacy form remains available.','kourentzes-tickets'),
            'invalid_fields'=>__('Check the required fields and their length limits.','kourentzes-tickets'),
            'hold_review_required'=>__('A retention hold requires a reason and a future review date.','kourentzes-tickets'),
        ];
        return $messages[$code]??__('The request could not be completed. Check your input or retry later.','kourentzes-tickets');
    }
    private static function route(string $path, string $methods, callable $fn, bool $admin=false): void {
        register_rest_route('ktickets/v1',$path,['methods'=>$methods,'permission_callback'=>[self::class,$admin?'admin_permission':'guest_permission'],'callback'=>$fn]);
    }
    public static function routes(): void {
        self::route('/form','POST',static function ($r) { return self::run(static function () use ($r) {
            self::intake(); $project=Service::project(Service::text($r->get_param('project')??'',1,80));
            return Security::form($project['slug'],$r->get_param('privacy')===true);
        }); });
        foreach (['/tickets'=>false,'/privacy/tickets'=>true] as $route=>$privacy) {
            self::route($route,'POST',static function ($r) use ($privacy) { return self::run(static function () use ($r,$privacy) { self::intake(); return Service::create($r->get_json_params()?:[],$privacy); },true,$privacy); });
        }
        self::route('/access/exchange','POST',static function ($r) { return self::run(static fn()=>Service::exchange($r->get_json_params()?:[])); });
        self::route('/access/logout','POST',static function ($r) { return self::run(static function () use ($r) {
            return Security::serialized(static function () use ($r) {
                $t=Store::ticket((string)$r->get_param('ticket'));if (!$t) { Security::fail('access_denied',403); }
                $s=Security::session($t,$r,true); $table=Store::table('sessions');
                Store::sql(Store::db()->prepare("DELETE FROM $table WHERE session_hash=%s",$s['session_hash']));
                Security::cookie('kt_s_'.$t['public_id'],'',time()-3600);return ['ok'=>true];
            });
        }); });
        self::route('/tickets/(?P<public>[a-f0-9]{32})','GET',static function ($r) { return self::run(static function () use ($r) {
            $t=Store::ticket($r['public']); if (!$t) { Security::fail('access_denied',403); }
            $s=Security::session($t,$r,false);
            Security::serialized(static function () use ($s) { Security::quota([$s['session_hash']],'read',60,30); });
            return Service::guest($t,max(0,(int)$r->get_param('before')));
        }); });
        foreach (['messages'=>false,'privacy-request'=>true] as $suffix=>$privacy) {
            self::route('/tickets/(?P<public>[a-f0-9]{32})/'.$suffix,'POST',static fn($r)=>self::run(static fn()=>Service::guest_write($r['public'],$r,$privacy)));
        }
        self::route('/admin/tickets','GET',static fn($r)=>self::run(static fn()=>Service::admin_list($r)),true);
        self::route('/admin/tickets/(?P<id>\d+)','GET',static fn($r)=>self::run(static function () use ($r) {
            $t=Store::by_id((int)$r['id']);if (!$t || !Security::can_view($t)) { Security::fail('access_denied',403); }return Service::detail($t,max(0,(int)$r->get_param('before')));
        }),true);
        foreach (['messages'=>['POST','message'],'rotate-access'=>['POST','rotate'],'export'=>['POST','export']] as $suffix=>$config) {
            self::route('/admin/tickets/(?P<id>\d+)/'.$suffix,$config[0],static fn($r)=>self::run(static fn()=>Service::admin_action((int)$r['id'],$config[1],$r->get_json_params()?:[])),true);
        }
        self::route('/admin/tickets/(?P<id>\d+)','PATCH',static fn($r)=>self::run(static fn()=>Service::admin_action((int)$r['id'],'update',$r->get_json_params()?:[])),true);
        self::route('/admin/tickets/(?P<id>\d+)','DELETE',static fn($r)=>self::run(static fn()=>Service::admin_action((int)$r['id'],'delete',$r->get_json_params()?:[])),true);
    }
}
