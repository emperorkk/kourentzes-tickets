<?php
namespace KTickets;
if (!defined('ABSPATH')) { exit; }

final class UI {
    public static function boot(): void {
        add_action('admin_menu',[self::class,'menu']);
        add_action('admin_notices',[self::class,'notices']);
        add_action('admin_post_ktickets_setup',[self::class,'setup']);
        add_action('admin_post_ktickets_owner',[self::class,'save_owner']);
        add_filter('plugin_action_links_'.plugin_basename(KTICKETS_FILE),[self::class,'plugin_links']);
        add_action('admin_post_ktickets_settings',[self::class,'save_settings']);
        add_action('admin_post_ktickets_project',[self::class,'save_project']);
        add_action('admin_post_ktickets_cleanup',static function () { self::guard_settings();check_admin_referer('ktickets');Service::cleanup();wp_safe_redirect(self::settings_url());exit; });
        add_action('template_redirect',[self::class,'standalone'],0);
        add_shortcode('ktickets_form',static fn($a)=>self::form(shortcode_atts(['project'=>'soft1-code-assistant'],$a),false));
        add_shortcode('ktickets_privacy_form',static fn($a)=>self::form(shortcode_atts(['project'=>'soft1-code-assistant'],$a),true));
        add_shortcode('ktickets_portal',static fn()=>self::portal());
        add_filter('wp_sitemaps_posts_query_args',[self::class,'exclude'],10,2);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids',static fn($ids)=>array_unique(array_merge($ids,self::pages())));
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Exclude only the three configured ticket page shells for privacy.
        add_action('pre_get_posts',static function ($q) { if (!is_admin() && ($q->is_search() || $q->is_feed())) { $q->set('post__not_in',array_unique(array_merge((array)$q->get('post__not_in'),self::pages()))); } });
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Exclude only the three configured private page shells from public REST collections.
        add_filter('rest_page_query',static function ($args) { $args['post__not_in']=array_unique(array_merge((array)($args['post__not_in']??[]),self::pages()));return $args; });
    }
    public static function pages(): array { $s=Store::settings();return array_values(array_filter(array_map('intval',[$s['form_page'],$s['privacy_page'],$s['portal_page']]))); }
    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Fixed three-page privacy exclusion, not an unbounded ID query.
    public static function exclude(array $args,string $type): array { if ($type==='page') { $args['post__not_in']=array_merge((array)($args['post__not_in']??[]),self::pages()); }return $args; }
    public static function standalone(): void {
        if (!is_singular('page')) { return; } $post=get_post();if (!$post) { return; }
        $content=$post->post_content;
        if (!has_shortcode($content,'ktickets_portal') && !has_shortcode($content,'ktickets_form') && !has_shortcode($content,'ktickets_privacy_form')) { return; }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin interoperability constant; its name is externally defined.
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed's documented integration hook, not a new plugin-owned hook.
        do_action('litespeed_control_set_nocache','Kourentzes Tickets private surface');
        nocache_headers();header('Cache-Control: no-store, private, max-age=0');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow, noarchive');header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        $appearance=Appearance::config();
        ?><!doctype html><html data-kt-appearance="<?php echo esc_attr(wp_json_encode($appearance)); ?>" data-kt-theme="<?php echo esc_attr($appearance['mode']==='dark'||($appearance['mode']==='site'&&$appearance['site']==='dark')?'dark':'light'); ?>" lang="<?php echo esc_attr(get_bloginfo('language')); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?php echo esc_html($post->post_title); ?></title><?php self::print_standalone_assets(false); ?></head><body class="kt-page"><?php self::site_header(); ?><div class="kt-layout"><?php self::site_sidebar(); ?><main class="ktickets kt-workspace" id="kt-main"><?php self::hero($content); ?><?php
        // Render only ticket shortcodes, never arbitrary page/theme blocks or third-party shortcode callbacks.
        preg_match_all('/\[(ktickets_form|ktickets_privacy_form|ktickets_portal)\b([^\]]*)\]/',$content,$matches,PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attrs=shortcode_parse_atts($match[2]);$attrs=is_array($attrs)?$attrs:[];
            echo $match[1]==='ktickets_portal'?self::portal():self::form(['project'=>$attrs['project']??'soft1-code-assistant'],$match[1]==='ktickets_privacy_form'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped render methods.
        }
        ?></main></div><footer class="kt-site-footer"><span><?php echo esc_html(get_bloginfo('name')); ?></span><span><?php esc_html_e('Private by design. Human at the other end.','kourentzes-tickets'); ?></span></footer><?php self::print_standalone_assets(true); ?></body></html><?php exit;
    }
    private static function site_header(): void {
        ?><a class="kt-skip" href="#kt-main"><?php esc_html_e('Skip to content','kourentzes-tickets'); ?></a><header class="kt-site-header"><a class="kt-brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="kt-monogram" aria-hidden="true"><?php echo esc_html(mb_strtoupper(mb_substr(get_bloginfo('name'),0,1))); ?>.</span><span><?php echo esc_html(get_bloginfo('name')); ?><small><?php esc_html_e('SUPPORT & CONVERSATIONS','kourentzes-tickets'); ?></small></span></a><div class="kt-header-actions"><?php Appearance::controls(); ?><a class="kt-back-site" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to website','kourentzes-tickets'); ?><span aria-hidden="true"> ↗</span></a></div></header><?php
    }
    private static function site_sidebar(): void {
        $settings=Store::settings();$current=get_queried_object_id();
        ?><aside class="kt-sidebar"><span class="kt-eyebrow"><?php esc_html_e('YOUR REQUEST CENTER','kourentzes-tickets'); ?></span><nav aria-label="<?php esc_attr_e('Ticket navigation','kourentzes-tickets'); ?>"><?php foreach (['form_page'=>__('New request','kourentzes-tickets'),'portal_page'=>__('Your conversation','kourentzes-tickets'),'privacy_page'=>__('Privacy & data','kourentzes-tickets')] as $field=>$label) { if (!$settings[$field]) { continue; } ?><a class="kt-nav-item" href="<?php echo esc_url(get_permalink((int)$settings[$field])); ?>" <?php if ($current===(int)$settings[$field]) { ?>aria-current="page"<?php } ?>><span class="kt-nav-mark" aria-hidden="true"><?php echo esc_html($field==='form_page'?'+':($field==='privacy_page'?'◇':'↗')); ?></span><?php echo esc_html($label); ?></a><?php } ?></nav><div class="kt-sidebar-note"><span class="kt-security-dot" aria-hidden="true"></span><strong><?php esc_html_e('Just between us.','kourentzes-tickets'); ?></strong><p><?php esc_html_e('No public threads. No account required. Your private link is your access key.','kourentzes-tickets'); ?></p></div><a class="kt-sidebar-policy" href="<?php echo esc_url($settings['policy_url']); ?>"><?php esc_html_e('Privacy notice','kourentzes-tickets'); ?> ↗</a></aside><?php
    }
    private static function hero(string $content): void {
        $privacy=has_shortcode($content,'ktickets_privacy_form');$portal=has_shortcode($content,'ktickets_portal');
        ?><header class="kt-hero"><div><p class="kt-eyebrow"><?php echo esc_html($privacy?__('PRIVACY & PERSONAL DATA','kourentzes-tickets'):($portal?__('YOUR PRIVATE CONVERSATION','kourentzes-tickets'):__('A DIRECT LINE TO US','kourentzes-tickets'))); ?></p><h1><?php echo esc_html($privacy?__('Your data. Your voice.','kourentzes-tickets'):($portal?__('A little space for a better conversation.','kourentzes-tickets'):__('Let’s work through it.','kourentzes-tickets'))); ?></h1><p class="kt-hero-description"><?php echo esc_html($privacy?__('Questions about your personal data deserve a clear, private answer. Start here.','kourentzes-tickets'):($portal?__('Keep the details together, follow the progress and pick up where you left off.','kourentzes-tickets'):__('A question, an idea, something that isn’t working? Tell us what’s on your mind.','kourentzes-tickets'))); ?></p></div><div class="kt-orbit" aria-hidden="true"><div class="kt-orbit-ring"></div><div class="kt-orbit-ticket"><span>↗</span><i></i><i></i><i></i></div><span class="kt-orbit-spark">✦</span></div></header><?php
    }
    public static function print_standalone_assets(bool $script): void {
        // Separate registries keep theme/analytics queues out; do not invoke wp_head/wp_footer.
        if ($script) {
            $registry=new \WP_Scripts();
            $registry->add('ktickets-standalone',plugins_url('assets/tickets.js',KTICKETS_FILE),[],KTICKETS_VERSION);
        } else {
            $registry=new \WP_Styles();
            $registry->add('ktickets-standalone',plugins_url('assets/tickets.css',KTICKETS_FILE),[],KTICKETS_VERSION);
        }
        $registry->enqueue('ktickets-standalone');
        $registry->do_items(['ktickets-standalone']);
        if (!$script) { $boot=new \WP_Scripts();$boot->add('ktickets-appearance',plugins_url('assets/theme.js',KTICKETS_FILE),[],KTICKETS_VERSION);$boot->do_items(['ktickets-appearance']); }
    }
    private static function config(string $mode, array $extra=[]): array {
        $strings=[
            'invalidLink'=>__('Paste a valid private link from this website.','kourentzes-tickets'),
            'emptyTitle'=>__('A clear inbox. A fresh start.','kourentzes-tickets'),
            'emptyBody'=>__('No tickets match these filters. Try another view or check back later.','kourentzes-tickets'),
            'flagged'=>__('Review flag','kourentzes-tickets'),
            'received'=>__('Received','kourentzes-tickets'),
            'working'=>__('Working…','kourentzes-tickets'),'error'=>__('The request failed. Retry the same submission.','kourentzes-tickets'),
            'saved'=>__('Request received. Save your private link now. There are no email notifications; return here to check for replies. Lost links cannot be recovered.','kourentzes-tickets'),
            'pending'=>__('This request is awaiting review.','kourentzes-tickets'),'copy'=>__('Copy private link','kourentzes-tickets'),'download'=>__('Download access file','kourentzes-tickets'),
            'copied'=>__('Copied.','kourentzes-tickets'),'needKey'=>__('Open your saved private link to access this ticket.','kourentzes-tickets'),
            'staff'=>__('Staff','kourentzes-tickets'),'guest'=>__('You','kourentzes-tickets'),'internal'=>__('Internal note','kourentzes-tickets'),
            'closed'=>__('Closed: read-only. Use the new request link to contact us again.','kourentzes-tickets'),'success'=>__('Saved.','kourentzes-tickets'),
            'deleteConfirm'=>__('Permanently delete this ticket and every related message, key and session?','kourentzes-tickets'),
            'rotateConfirm'=>__('Replace the private key and revoke every existing guest session?','kourentzes-tickets'),
            'status'=>__('Status','kourentzes-tickets'),'retention'=>__('Scheduled deletion','kourentzes-tickets'),'noPurge'=>__('No closure retention countdown yet.','kourentzes-tickets'),
            'expired'=>__('The pending submission expired. Reload to start again.','kourentzes-tickets'),
        ];
        return array_merge(['mode'=>$mode,'api'=>rest_url('ktickets/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'strings'=>$strings,'labels'=>self::enum_labels()+['technical'=>__('Technical issue','kourentzes-tickets'),'suggestion'=>__('Suggestion','kourentzes-tickets'),'privacy'=>__('Privacy','kourentzes-tickets')]],$extra);
    }
    public static function form(array $attrs, bool $privacy): string {
        $s=Store::settings();
        try { $project=Service::project((string)$attrs['project']);$allowed=(array)json_decode($project['allowed_categories'],true); } catch (\Throwable $e) { return '<p>'.esc_html__('This ticket project is unavailable.','kourentzes-tickets').'</p>'; }
        if ($privacy && !in_array('privacy',$allowed,true)) { return '<p>'.esc_html__('Privacy intake is unavailable for this project.','kourentzes-tickets').'</p>'; }
        ob_start();
        ?><section class="kt-surface" data-kt-config="<?php echo esc_attr(wp_json_encode(self::config('form',['project'=>$attrs['project'],'privacy'=>$privacy]))); ?>"><div class="kt-form-heading"><span class="kt-step-label">01 / <?php esc_html_e('START A CONVERSATION','kourentzes-tickets'); ?></span><h2><?php echo esc_html($privacy?__('Your privacy request','kourentzes-tickets') : __('What can we help with?','kourentzes-tickets')); ?></h2><p><?php esc_html_e('A few details now make a better answer possible.','kourentzes-tickets'); ?></p></div><p><?php esc_html_e('Please don’t share passwords, access tokens, databases or unnecessary personal data about other people.','kourentzes-tickets'); ?></p><p><?php esc_html_e('No email or account is required. Save the private link after submission and return to check for replies.','kourentzes-tickets'); ?></p><?php
        if (!$s['enabled'] || !Security::secure()) { ?><p role="status"><?php esc_html_e('New submissions are temporarily disabled.','kourentzes-tickets'); ?></p></section><?php return ob_get_clean(); }
        ?><form class="kt-create"><label><?php esc_html_e('Subject','kourentzes-tickets'); ?><input name="subject" minlength="5" maxlength="160" required></label><?php if (!$privacy) { ?><label><?php esc_html_e('Category','kourentzes-tickets'); ?><select name="category"><?php self::options(array_intersect_key(['technical'=>__('Technical issue','kourentzes-tickets'),'suggestion'=>__('Suggestion','kourentzes-tickets'),'other'=>__('Other','kourentzes-tickets')],array_flip($allowed))); ?></select></label><?php } else { ?><label><?php esc_html_e('Request type','kourentzes-tickets'); ?><select name="request_type"><?php self::options(array_combine(Service::TYPES,Service::TYPES)); ?></select></label><?php } ?><label><?php esc_html_e('Details','kourentzes-tickets'); ?><textarea name="body" minlength="20" maxlength="20000" rows="8" required></textarea></label><?php if (!$privacy) { ?><details class="kt-environment"><summary><?php esc_html_e('Add optional details','kourentzes-tickets'); ?></summary><div class="kt-environment-fields"><?php } ?><label><?php esc_html_e('Alias (optional)' ,'kourentzes-tickets'); ?><input name="alias" maxlength="80" autocomplete="off"></label><?php if (!$privacy) { foreach (['plugin_version'=>__('Plugin version','kourentzes-tickets'),'soft1_version'=>__('Soft1 version','kourentzes-tickets'),'runtime'=>__('Execution environment','kourentzes-tickets')] as $name=>$label) { ?><label><?php echo esc_html($label); ?><input name="<?php echo esc_attr($name); ?>" maxlength="160"></label><?php } } ?><?php if (!$privacy) { ?></div></details><?php } ?><div class="kt-honey" aria-hidden="true"><label><?php esc_html_e('Website','kourentzes-tickets'); ?><input name="website" tabindex="-1" autocomplete="off"></label></div><label class="kt-check"><input type="checkbox" name="acknowledge" required><?php esc_html_e('I understand that the private link is my access key and cannot be recovered if lost.','kourentzes-tickets'); ?></label><p><a href="<?php echo esc_url($s['policy_url']); ?>" rel="noreferrer"><?php esc_html_e('Privacy notice','kourentzes-tickets'); ?></a><?php if ($s['terms_url']) { ?> · <a href="<?php echo esc_url($s['terms_url']); ?>" rel="noreferrer"><?php esc_html_e('Terms','kourentzes-tickets'); ?></a><?php } ?></p><button class="kt-primary" type="submit" disabled><?php esc_html_e('Submit private request','kourentzes-tickets'); ?></button></form><p class="kt-service-notice"><?php esc_html_e('Technical reports do not create a support contract or guarantee a resolution.','kourentzes-tickets'); ?></p><div class="kt-result" role="status" aria-live="polite" tabindex="-1"></div><?php if (!$privacy && $s['privacy_page']) { ?><p><a href="<?php echo esc_url(get_permalink((int)$s['privacy_page'])); ?>"><?php esc_html_e('Privacy / personal data request','kourentzes-tickets'); ?></a></p><?php } ?></section><?php return ob_get_clean();
    }
    public static function portal(): string {
        ob_start();$s=Store::settings();?><section class="kt-surface" data-kt-config="<?php echo esc_attr(wp_json_encode(self::config('portal'))); ?>"><p><?php esc_html_e('This private link grants access to the conversation. No email notifications are sent. Do not share the link.','kourentzes-tickets'); ?></p><div class="kt-result" role="status" aria-live="polite" tabindex="-1"></div><div class="kt-conversation"></div><button type="button" class="kt-older" hidden><?php esc_html_e('Load older messages','kourentzes-tickets'); ?></button><form class="kt-open-link"><div class="kt-form-heading"><span class="kt-step-label"><?php esc_html_e('WELCOME BACK','kourentzes-tickets'); ?></span><h2><?php esc_html_e('Pick up the conversation.','kourentzes-tickets'); ?></h2><p><?php esc_html_e('Paste the private link you saved when you submitted your request.','kourentzes-tickets'); ?></p></div><label><?php esc_html_e('Your saved private link','kourentzes-tickets'); ?><input type="password" name="private_link" autocomplete="off" spellcheck="false" required></label><button type="submit" class="kt-primary"><?php esc_html_e('Open conversation','kourentzes-tickets'); ?></button></form><form class="kt-reply" hidden><label><?php esc_html_e('Reply (plain text)','kourentzes-tickets'); ?><textarea name="body" maxlength="20000" rows="6" required></textarea></label><button type="submit"><?php esc_html_e('Send reply','kourentzes-tickets'); ?></button><button type="button" class="kt-privacy-request"><?php esc_html_e('Submit this message as a privacy / deletion request','kourentzes-tickets'); ?></button></form><button type="button" class="kt-refresh" hidden><?php esc_html_e('Refresh conversation','kourentzes-tickets'); ?></button> <button type="button" class="kt-logout" hidden><?php esc_html_e('End this ticket session','kourentzes-tickets'); ?></button><p><a href="<?php echo esc_url(get_permalink((int)$s['form_page'])); ?>"><?php esc_html_e('New request','kourentzes-tickets'); ?></a> · <a href="<?php echo esc_url(get_permalink((int)$s['privacy_page'])); ?>"><?php esc_html_e('Privacy request','kourentzes-tickets'); ?></a></p></section><?php return ob_get_clean();
    }
    private static function enum_labels(): array {
        return [
            'open'=>__('Open','kourentzes-tickets'),
            'in_progress'=>__('In progress','kourentzes-tickets'),
            'waiting_requester'=>__('Waiting for requester','kourentzes-tickets'),
            'resolved'=>__('Resolved','kourentzes-tickets'),
            'closed'=>__('Closed','kourentzes-tickets'),
            'access'=>__('Access','kourentzes-tickets'),
            'rectification'=>__('Rectification','kourentzes-tickets'),
            'erasure'=>__('Erasure','kourentzes-tickets'),
            'restriction'=>__('Restriction','kourentzes-tickets'),
            'objection'=>__('Objection','kourentzes-tickets'),
            'portability'=>__('Portability','kourentzes-tickets'),
            'other'=>__('Other','kourentzes-tickets'),
        ];
    }
    private static function options(array $items, string $selected=''): void { $labels=self::enum_labels(); foreach ($items as $v=>$label) { ?><option value="<?php echo esc_attr($v); ?>" <?php selected($v,$selected); ?>><?php echo esc_html($labels[$v]??$label); ?></option><?php } }
    public static function menu(): void {
        if (!Store::settings()['owner']) { add_options_page(__('Ticket setup','kourentzes-tickets'),__('Ticket setup','kourentzes-tickets'),'manage_options','ktickets-setup',[self::class,'setup_page']); }
        add_options_page(__('Ticket settings','kourentzes-tickets'),__('Ticket settings','kourentzes-tickets'),current_user_can('manage_options')?'manage_options':'manage_ktickets_settings','ktickets-settings',[self::class,'settings_page']);
        add_menu_page(__('Tickets','kourentzes-tickets'),__('Tickets','kourentzes-tickets'),'view_ktickets','ktickets',[self::class,'admin_page'],'dashicons-tickets-alt');
    }
    public static function settings_url(): string { return admin_url('options-general.php?page=ktickets-settings'); }
    public static function plugin_links(array $links): array {
        if (Security::can_settings()) { array_unshift($links,'<a href="'.esc_url(self::settings_url()).'">'.esc_html__('Settings','kourentzes-tickets').'</a>'); }
        return $links;
    }
    private static function guard_settings(): void {
        if (!Security::can_settings()) { wp_die(esc_html__('Access denied.','kourentzes-tickets'),'', ['response'=>403]); }
    }
    public static function save_owner(): void {
        self::guard_settings();
        check_admin_referer('ktickets_owner');
        $id=absint(wp_unslash($_POST['owner']??0));
        $expected=absint(wp_unslash($_POST['expected_owner']??0));
        $confirmed=isset($_POST['confirm_transfer']);
        $result=REST::run(static function () use ($id,$expected,$confirmed) { Service::change_owner($id,$expected,$confirmed);return ['ok'=>true]; });
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'', ['response'=>(int)($result->get_error_data()['status']??400)]); }
        wp_safe_redirect(Security::can_settings()?self::settings_url():admin_url());exit;
    }
    private static function owner_form(array $settings): void {
        if (!(int)$settings['owner']) { ?><p><a href="<?php echo esc_url(admin_url('options-general.php?page=ktickets-setup')); ?>"><?php esc_html_e('Complete initial owner setup','kourentzes-tickets'); ?></a></p><?php return; }
        ?><h2><?php esc_html_e('Ticket owner','kourentzes-tickets'); ?></h2><p><?php esc_html_e('WordPress administrators retain access to settings. Changing the owner grants ticket permissions to the selected account and removes them from the previous owner. Tickets, visitor links, messages and WordPress roles are preserved.','kourentzes-tickets'); ?></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ktickets_owner"><input type="hidden" name="expected_owner" value="<?php echo esc_attr($settings['owner']); ?>"><?php wp_nonce_field('ktickets_owner'); ?><label for="ktickets-owner"><?php esc_html_e('Responsible WordPress user','kourentzes-tickets'); ?></label><?php wp_dropdown_users(['name'=>'owner','id'=>'ktickets-owner','selected'=>(int)$settings['owner'],'show_option_none'=>__('Select an existing user','kourentzes-tickets')]); ?><p><label><input type="checkbox" name="confirm_transfer" required><?php esc_html_e('I confirm the transfer of ticket-management access to this user.','kourentzes-tickets'); ?></label></p><?php submit_button(__('Change ticket owner','kourentzes-tickets')); ?></form><?php
        if (!empty($settings['owner_change'])) { ?><p><?php esc_html_e('Last ownership change (UTC)','kourentzes-tickets'); ?>: <?php echo esc_html($settings['owner_change']['at']); ?></p><?php }
    }
    public static function notices(): void {
        if (!current_user_can('manage_options') && !current_user_can('manage_ktickets_settings')) { return; }
        $s=Store::settings();$error=get_option('ktickets_migration_error');
        if (!$s['owner'] || !$s['enabled'] || !Security::secure() || $error) { ?><div class="notice notice-warning"><p><?php esc_html_e('Kourentzes Tickets: complete owner setup and live cache/privacy verification before enabling public submissions. HTTPS is required.','kourentzes-tickets'); ?> <?php echo esc_html($error?:''); ?></p></div><?php }
    }
    public static function guard(string $cap): void { if (!current_user_can($cap)) { wp_die(esc_html__('Access denied.','kourentzes-tickets')); } }
    public static function setup_page(): void {
        if (!current_user_can('manage_options') || Store::settings()['owner']) { return; }
        ?><div class="wrap"><h1><?php esc_html_e('Select the ticket owner','kourentzes-tickets'); ?></h1><p><?php esc_html_e('Select Konstantinos Kourentzes’ existing WordPress account explicitly. Only this account receives ticket capabilities. Infrastructure administrators still have technical access.','kourentzes-tickets'); ?></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ktickets_setup"><?php wp_nonce_field('ktickets');wp_dropdown_users(['name'=>'owner','show_option_none'=>__('Select owner','kourentzes-tickets')]); ?><p><button class="button button-primary"><?php esc_html_e('Assign owner and create ticket pages','kourentzes-tickets'); ?></button></p></form></div><?php
    }
    public static function setup(): void {
        self::guard('manage_options');
        check_admin_referer('ktickets');
        $id=absint(wp_unslash($_POST['owner']??0));
        Security::serialized(static function () use ($id) {
            if (Store::settings()['owner']) { wp_die(esc_html__('Owner setup is already complete.','kourentzes-tickets')); }
            $user=get_user_by('id',$id);if (!$user) { wp_die(esc_html__('Select an existing account.','kourentzes-tickets')); }
            $s=Store::settings();$s['owner']=$id;
            foreach (Store::caps() as $cap) { $user->add_cap($cap); }
            foreach (['form_page'=>['Submit a private request','[ktickets_form project="soft1-code-assistant"]'],'privacy_page'=>['Privacy request','[ktickets_privacy_form project="soft1-code-assistant"]'],'portal_page'=>['Private ticket portal','[ktickets_portal]']] as $field=>$config) {
                $page=wp_insert_post(['post_type'=>'page','post_title'=>$config[0],'post_content'=>$config[1],'post_status'=>'publish','comment_status'=>'closed','ping_status'=>'closed'],true);
                if (is_wp_error($page)) { wp_die(esc_html__('Page creation failed. Intake remains disabled.','kourentzes-tickets')); }
                $s[$field]=$page;
            }
            update_option('ktickets_settings',$s,false);
        });
        wp_safe_redirect(self::settings_url());exit;
    }
    public static function save_settings(): void {
        self::guard_settings();
        check_admin_referer('ktickets');
        $s=Store::settings();
        $appearance=sanitize_key(wp_unslash($_POST['appearance']??$s['appearance']));
        $s['appearance']=in_array($appearance,Appearance::MODES,true)?$appearance:'site';
        $key=sanitize_text_field(wp_unslash($_POST['site_theme_key']??$s['site_theme_key']));
        $s['site_theme_key']=preg_match('/^[A-Za-z0-9_.:-]{0,80}$/D',$key)?$key:'';
        foreach (['enabled','verified','manual_attack','delete_on_uninstall'] as $field) { $s[$field]=isset($_POST[$field]); }
        foreach (['portal_page','form_page','privacy_page'] as $field) { $s[$field]=absint(wp_unslash($_POST[$field]??0)); }
        foreach (['policy_url','terms_url'] as $field) { $s[$field]=esc_url_raw(wp_unslash($_POST[$field]??''),['https']); }
        foreach (['create_short','create_day','attack_valid','attack_failed','quarantine_capacity','network_reply'] as $field) { $s[$field]=max(1,min(1000,absint(wp_unslash($_POST[$field]??1)))); }
        if (!$s['verified'] || !$s['policy_url'] || !Security::secure() || get_option('ktickets_migration_error')) { $s['enabled']=false; }
        foreach (['portal_page'=>'ktickets_portal','form_page'=>'ktickets_form','privacy_page'=>'ktickets_privacy_form'] as $field=>$shortcode) {
            $page=get_post($s[$field]);if (!$page || $page->post_status!=='publish' || !has_shortcode($page->post_content,$shortcode)) { $s['enabled']=false; }
        }
        Security::serialized(static function () use ($s) {
            $current=Store::settings();$s['owner']=$current['owner'];
            if (isset($current['owner_change'])) { $s['owner_change']=$current['owner_change']; }
            update_option('ktickets_settings',$s,false);
        });wp_safe_redirect(self::settings_url());exit;
    }
    public static function save_project(): void {
        self::guard_settings();
        check_admin_referer('ktickets');
        $slug=sanitize_title(wp_unslash($_POST['slug']??''));$name=sanitize_text_field(wp_unslash($_POST['name']??''));
        if (!$slug || strlen($slug)>80 || !$name || mb_strlen($name)>160) { wp_die(esc_html__('Invalid project.','kourentzes-tickets')); }
        $cats=array_values(array_intersect(['technical','suggestion','privacy','other'],array_map('sanitize_key',(array)wp_unslash($_POST['categories']??[]))));
        if (!$cats) { wp_die(esc_html__('Select at least one category.','kourentzes-tickets')); }
        $data=['slug'=>$slug,'name'=>$name,'enabled'=>isset($_POST['enabled'])?1:0,'allowed_categories'=>wp_json_encode($cats)];
        $t=Store::table('projects');$db=Store::db();$id=(int)$db->get_var($db->prepare("SELECT id FROM $t WHERE slug=%s",$slug));
        if ($id) { $db->update($t,$data,['id'=>$id]); }else { Store::insert('projects',$data); }
        wp_safe_redirect(self::settings_url());exit;
    }
    private static function admin_assets(): void {
        wp_enqueue_script('ktickets-theme',plugins_url('assets/theme.js',KTICKETS_FILE),[],KTICKETS_VERSION,true);
        wp_enqueue_style('ktickets',plugins_url('assets/tickets.css',KTICKETS_FILE),[],KTICKETS_VERSION);
        wp_enqueue_script('ktickets',plugins_url('assets/tickets.js',KTICKETS_FILE),[],KTICKETS_VERSION,true);
    }
    public static function settings_page(): void {
        if (!Security::can_settings()) { return; }self::admin_assets();$s=Store::settings();$db=Store::db();$t=Store::table('tickets');$p=Store::table('privacy');
        ?><div class="wrap ktickets kt-admin-app kt-settings-app" data-kt-appearance="<?php echo esc_attr(wp_json_encode(Appearance::config())); ?>"><div class="kt-admin-topbar"><?php Appearance::controls(); ?></div><h1><?php esc_html_e('Ticket settings and health','kourentzes-tickets'); ?></h1><?php self::owner_form($s); ?><p><?php esc_html_e('Intake is disabled by default. Verify live cache exclusions, analytics isolation, hosting logs/backups, privacy notice and HTTPS before enabling it.','kourentzes-tickets'); ?></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ktickets_settings"><?php wp_nonce_field('ktickets');Appearance::settings_fields($s); ?>
        <?php foreach (['policy_url'=>__('Published privacy notice URL','kourentzes-tickets'),'terms_url'=>__('Terms URL (optional)','kourentzes-tickets'),'portal_page'=>__('Portal page ID','kourentzes-tickets'),'form_page'=>__('General form page ID','kourentzes-tickets'),'privacy_page'=>__('Privacy form page ID','kourentzes-tickets'),'create_short'=>__('Creation limit / 10 minutes','kourentzes-tickets'),'create_day'=>__('Creation limit / rolling 24 hours','kourentzes-tickets'),'attack_valid'=>__('Attack threshold / valid attempts in 10 minutes','kourentzes-tickets'),'attack_failed'=>__('Attack threshold / failed attempts in 5 minutes','kourentzes-tickets'),'quarantine_capacity'=>__('General quarantine capacity','kourentzes-tickets'),'network_reply'=>__('Network replies / 10 minutes','kourentzes-tickets')] as $field=>$label) { ?><p><label><?php echo esc_html($label); ?><br><input class="regular-text" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($s[$field]); ?>" type="<?php echo str_ends_with($field,'url')?'url':'number'; ?>"></label></p><?php } ?>
        <?php foreach (['verified'=>__('I verified live caching, analytics isolation, privacy notice, hosting logs/backups and HTTPS.','kourentzes-tickets'),'enabled'=>__('Enable public intake','kourentzes-tickets'),'manual_attack'=>__('Proactively enable attack mode for general intake','kourentzes-tickets'),'delete_on_uninstall'=>__('Permanently delete ALL ticket data and capabilities on uninstall (irreversible)','kourentzes-tickets')] as $field=>$label) { ?><p><label><input type="checkbox" name="<?php echo esc_attr($field); ?>" <?php checked($s[$field]); ?>><?php echo esc_html($label); ?></label></p><?php }submit_button(); ?></form><h2><?php esc_html_e('Operational health','kourentzes-tickets'); ?></h2><pre><?php
        $scope=current_user_can('view_ktickets')?Service::scope():'0=1';
        $health=['cleanup'=>get_option('ktickets_health',[]),'next_cleanup_utc'=>wp_next_scheduled('ktickets_cleanup')?gmdate('c',wp_next_scheduled('ktickets_cleanup')):null,'attack_active'=>Security::attack(),'attack_trigger'=>get_option('ktickets_attack',[]),'overdue_purges'=>(int)$db->get_var($db->prepare("SELECT COUNT(*) FROM $t t WHERE $scope AND t.purge_at<=%s",Store::now())),'inactive_90_days'=>(int)$db->get_var($db->prepare("SELECT COUNT(*) FROM $t t WHERE $scope AND t.status<>'closed' AND t.updated_at<%s",gmdate('Y-m-d H:i:s',time()-90*86400)))];
        if (current_user_can('view_ktickets_privacy')) { $health['overdue_privacy']=(int)$db->get_var($db->prepare("SELECT COUNT(*) FROM $p p JOIN $t t ON t.id=p.ticket_id WHERE $scope AND p.received_at IS NOT NULL AND p.target_due_at<%s AND t.status<>'closed'",Store::now())); }
        if (current_user_can('manage_ktickets_privacy')) { $health['holds_due_review']=(int)$db->get_var($db->prepare("SELECT COUNT(*) FROM $p WHERE hold_reason<>'' AND hold_review_at<=%s",Store::now())); }
        echo esc_html(wp_json_encode($health,JSON_PRETTY_PRINT)); ?></pre><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ktickets_cleanup"><?php wp_nonce_field('ktickets');submit_button(__('Run one cleanup batch','kourentzes-tickets')); ?></form><h2><?php esc_html_e('Projects and categories','kourentzes-tickets'); ?></h2><p><?php esc_html_e('Use an existing slug to update a project. Add new form pages with that project slug. Privacy uses its separate form.','kourentzes-tickets'); ?></p><pre><?php $projects=Store::table('projects');echo esc_html(wp_json_encode($db->get_results("SELECT slug,name,enabled,allowed_categories FROM $projects LIMIT 100",ARRAY_A),JSON_PRETTY_PRINT)); ?></pre><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ktickets_project"><?php wp_nonce_field('ktickets'); ?><p><label><?php esc_html_e('Slug','kourentzes-tickets'); ?><input name="slug" maxlength="80" required></label></p><p><label><?php esc_html_e('Project name','kourentzes-tickets'); ?><input name="name" maxlength="160" required></label></p><p><label><input type="checkbox" name="enabled" checked><?php esc_html_e('Enabled','kourentzes-tickets'); ?></label></p><?php foreach (['technical','suggestion','privacy','other'] as $cat) { ?><label><input type="checkbox" name="categories[]" value="<?php echo esc_attr($cat); ?>" checked><?php echo esc_html($cat); ?></label> <?php }submit_button(__('Save project','kourentzes-tickets')); ?></form><p><?php esc_html_e('Grant future agent capabilities on individual WordPress users using your user-management tooling. No role receives automatic access. Agents need view_ktickets and reply_ktickets, and see assigned general tickets only unless explicitly granted view_all_ktickets.','kourentzes-tickets'); ?></p></div><?php
    }
    public static function admin_page(): void {
        if (!current_user_can('view_ktickets')) { return; }
        self::admin_assets();
        ?><div class="wrap ktickets kt-admin-app" data-kt-appearance="<?php echo esc_attr(wp_json_encode(Appearance::config())); ?>"><div class="kt-admin-topbar"><?php Appearance::controls(); ?></div><h1><?php esc_html_e('Tickets','kourentzes-tickets'); ?></h1><section data-kt-config="<?php echo esc_attr(wp_json_encode(self::config('admin',['privacy'=>current_user_can('manage_ktickets_privacy'),'settings'=>current_user_can('manage_ktickets_settings'),'delete'=>current_user_can('delete_ktickets'),'assign'=>current_user_can('assign_ktickets')]))); ?>"><form class="kt-filters"><label><?php esc_html_e('Search subject/reference','kourentzes-tickets'); ?><input name="search" maxlength="160"></label><label><?php esc_html_e('Status','kourentzes-tickets'); ?><select name="status"><option value=""><?php esc_html_e('All','kourentzes-tickets'); ?></option><?php self::options(array_combine(Service::STATUSES,Service::STATUSES)); ?></select></label><label><?php esc_html_e('Category','kourentzes-tickets'); ?><select name="category"><option value=""><?php esc_html_e('All permitted','kourentzes-tickets'); ?></option><?php self::options(['technical'=>__('Technical issue','kourentzes-tickets'),'suggestion'=>__('Suggestion','kourentzes-tickets'),'other'=>__('Other','kourentzes-tickets')]+(current_user_can('view_ktickets_privacy')?['privacy'=>__('Privacy / personal data','kourentzes-tickets')]:[])); ?></select></label><label><?php esc_html_e('Moderation','kourentzes-tickets'); ?><select name="moderation_state"><option value=""><?php esc_html_e('All','kourentzes-tickets'); ?></option><?php self::options(['normal'=>__('Normal','kourentzes-tickets'),'quarantine'=>__('Quarantine','kourentzes-tickets')]); ?></select></label><button><?php esc_html_e('Filter','kourentzes-tickets'); ?></button></form><div class="kt-result" role="status" aria-live="polite" tabindex="-1"></div><div class="kt-admin-list"></div><button class="kt-prev" type="button"><?php esc_html_e('Previous page','kourentzes-tickets'); ?></button> <button class="kt-next" type="button"><?php esc_html_e('Next page','kourentzes-tickets'); ?></button><div class="kt-admin-detail" hidden><h2 class="kt-subject" tabindex="-1"></h2><div class="kt-conversation"></div><button class="kt-older" type="button" hidden><?php esc_html_e('Load older messages','kourentzes-tickets'); ?></button><div class="kt-compose-tabs" role="tablist" aria-label="<?php esc_attr_e('Message visibility','kourentzes-tickets'); ?>"><button id="kt-public-tab" type="button" role="tab" aria-selected="true" aria-controls="kt-public-panel" data-kt-compose="public"><?php esc_html_e('Public reply','kourentzes-tickets'); ?></button><button id="kt-note-tab" type="button" role="tab" aria-selected="false" aria-controls="kt-note-panel" tabindex="-1" data-kt-compose="internal"><?php esc_html_e('Internal note','kourentzes-tickets'); ?></button></div><form class="kt-admin-reply" id="kt-public-panel" role="tabpanel" aria-labelledby="kt-public-tab"><label><?php esc_html_e('Public reply','kourentzes-tickets'); ?><textarea name="body" maxlength="20000" rows="5" required></textarea></label><label><?php esc_html_e('Reply status','kourentzes-tickets'); ?><select name="status"><?php self::options(['waiting_requester'=>__('Waiting for requester','kourentzes-tickets'),'in_progress'=>__('In progress','kourentzes-tickets')]); ?></select></label><button><?php esc_html_e('Send public reply','kourentzes-tickets'); ?></button></form><form class="kt-admin-note" id="kt-note-panel" role="tabpanel" aria-labelledby="kt-note-tab" hidden><label><?php esc_html_e('Internal note — never visible to visitor','kourentzes-tickets'); ?><textarea name="body" maxlength="20000" rows="4" required></textarea></label><button><?php esc_html_e('Save internal note','kourentzes-tickets'); ?></button></form><details class="kt-ticket-settings"><summary><?php esc_html_e('Ticket settings & assignment','kourentzes-tickets'); ?></summary><form class="kt-admin-update"><label><?php esc_html_e('Category','kourentzes-tickets'); ?><select name="category"><?php self::options(['technical'=>__('Technical issue','kourentzes-tickets'),'suggestion'=>__('Suggestion','kourentzes-tickets'),'other'=>__('Other','kourentzes-tickets')]+(current_user_can('manage_ktickets_privacy')?['privacy'=>__('Privacy / personal data','kourentzes-tickets')]:[])); ?></select></label><label><?php esc_html_e('Lifecycle status','kourentzes-tickets'); ?><select name="status"><?php self::options(array_combine(Service::STATUSES,Service::STATUSES)); ?></select></label><label><?php esc_html_e('Reason for reopening (required when closed)','kourentzes-tickets'); ?><input name="reason" maxlength="500"></label><?php if (current_user_can('assign_ktickets')) { ?><label><?php esc_html_e('Assigned team member','kourentzes-tickets'); ?><select name="assigned_to"><option value="0"><?php esc_html_e('Unassigned','kourentzes-tickets'); ?></option><?php foreach (get_users(['number'=>100]) as $agent) { if (!user_can($agent,'view_ktickets')) { continue; } ?><option value="<?php echo esc_attr($agent->ID); ?>"><?php echo esc_html($agent->display_name); ?></option><?php } ?></select></label><?php } ?><label class="kt-check"><input name="abuse_flag" type="checkbox"><?php esc_html_e('Flag for review','kourentzes-tickets'); ?></label><label><?php esc_html_e('Moderation state','kourentzes-tickets'); ?><select name="moderation_state"><?php self::options(['normal'=>__('Normal','kourentzes-tickets'),'quarantine'=>__('Quarantine','kourentzes-tickets')]); ?></select></label><button><?php esc_html_e('Save ticket changes','kourentzes-tickets'); ?></button></form></details><?php if (current_user_can('manage_ktickets_privacy')) { ?><details class="kt-privacy-tools"><summary><?php esc_html_e('Privacy handling & retention','kourentzes-tickets'); ?></summary><form class="kt-admin-privacy"><h3><?php esc_html_e('Privacy handling and retention hold','kourentzes-tickets'); ?></h3><p class="kt-received"></p><label><?php esc_html_e('Request type','kourentzes-tickets'); ?><select name="request_type"><?php self::options(array_combine(Service::TYPES,Service::TYPES)); ?></select></label><label><?php esc_html_e('Verification status','kourentzes-tickets'); ?><select name="verification_status"><?php self::options(['unverified'=>__('Unverified','kourentzes-tickets'),'pending'=>__('Pending','kourentzes-tickets'),'verified'=>__('Verified','kourentzes-tickets'),'not_required'=>__('Not required','kourentzes-tickets')]); ?></select></label><?php foreach (['target_due_at'=>__('Target due date (UTC: YYYY-MM-DD HH:MM:SS)','kourentzes-tickets'),'extension_reason'=>__('Extension reason','kourentzes-tickets'),'outcome'=>__('Outcome','kourentzes-tickets'),'hold_reason'=>__('Necessary retention hold reason (empty removes hold)','kourentzes-tickets'),'hold_review_at'=>__('Hold review date (UTC: YYYY-MM-DD HH:MM:SS)','kourentzes-tickets')] as $field=>$label) { ?><label><?php echo esc_html($label); ?><input name="<?php echo esc_attr($field); ?>" maxlength="2000"></label><?php } ?><button><?php esc_html_e('Save privacy handling','kourentzes-tickets'); ?></button></form></details><?php } ?><div class="kt-actions"><?php if (current_user_can('manage_ktickets_settings')) { ?><button type="button" class="kt-rotate"><?php esc_html_e('Replace private key','kourentzes-tickets'); ?></button><button type="button" class="kt-export"><?php esc_html_e('Export this message page (JSON, including notes)','kourentzes-tickets'); ?></button><?php }if (current_user_can('delete_ktickets')) { ?><button type="button" class="kt-delete"><?php esc_html_e('Permanently delete ticket','kourentzes-tickets'); ?></button><?php } ?></div><details class="kt-audit-panel"><summary><?php esc_html_e('Activity & technical context','kourentzes-tickets'); ?></summary><pre class="kt-audit"></pre></details></div></section></div><?php
    }
}
