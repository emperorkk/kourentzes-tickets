<?php
namespace KTickets;
if (!defined('ABSPATH')) { exit; }

final class Appearance {
    public const MODES=['site','system','light','dark'];
    public static function config(): array {
        $settings=Store::settings();
        $mode=in_array($settings['appearance'],self::MODES,true)?$settings['appearance']:'site';
        $theme=wp_get_theme();
        $custom=get_theme_mod('color_scheme','');
        $site=in_array($custom,['light','dark','system'],true)?$custom:(str_contains(strtolower($theme->get_stylesheet()),'dark')?'dark':'system');
        // Sites with a custom central theme switch can explicitly bridge its canonical value.
        $site=apply_filters('ktickets_site_theme',$site);
        if (!in_array($site,['light','dark','system'],true)) { $site='system'; }
        return ['mode'=>$mode,'site'=>$site,'key'=>$settings['site_theme_key']];
    }
    public static function controls(): void {
        ?><label class="kt-theme-label"><span><?php esc_html_e('Appearance','kourentzes-tickets'); ?></span><select data-kt-theme-control aria-label="<?php esc_attr_e('Appearance','kourentzes-tickets'); ?>"><option value="auto"><?php esc_html_e('Automatic','kourentzes-tickets'); ?></option><option value="light"><?php esc_html_e('Light','kourentzes-tickets'); ?></option><option value="dark"><?php esc_html_e('Dark','kourentzes-tickets'); ?></option></select></label><?php
    }
    public static function settings_fields(array $settings): void {
        ?><h2><?php esc_html_e('Appearance and site integration','kourentzes-tickets'); ?></h2><p><?php esc_html_e('Choose the central appearance for every ticket page. Automatic visitor mode follows this setting; visitors may explicitly choose light or dark.','kourentzes-tickets'); ?></p><p><label for="kt-appearance"><?php esc_html_e('Central ticket appearance','kourentzes-tickets'); ?></label><select id="kt-appearance" name="appearance"><?php foreach (['site'=>__('Follow the WordPress site theme','kourentzes-tickets'),'system'=>__('Follow the visitor’s device','kourentzes-tickets'),'light'=>__('Light','kourentzes-tickets'),'dark'=>__('Dark','kourentzes-tickets')] as $value=>$label) { ?><option value="<?php echo esc_attr($value); ?>" <?php selected($settings['appearance'],$value); ?>><?php echo esc_html($label); ?></option><?php } ?></select></p><p><label for="kt-site-theme-key"><?php esc_html_e('Existing site theme preference key (optional)','kourentzes-tickets'); ?></label><br><input id="kt-site-theme-key" name="site_theme_key" value="<?php echo esc_attr($settings['site_theme_key']); ?>" maxlength="80" class="regular-text"><br><?php esc_html_e('Only needed if your site already stores its central light/dark choice in localStorage. Enter that exact key; supported values are light, dark, auto or system. Leave blank for the WordPress theme setting.','kourentzes-tickets'); ?></p><?php
    }
}
