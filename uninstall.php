<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
$settings=(array)get_option('ktickets_settings',[]);
if (empty($settings['delete_on_uninstall'])) { return; }
global $wpdb;
foreach (['projects','tickets','messages','access','sessions','events','privacy','abuse','replays'] as $name) {
    $table=$wpdb->prefix.'ktickets_'.$name;
    $wpdb->query("DROP TABLE IF EXISTS `$table`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal suffix and WordPress prefix.
}
foreach (['ktickets_settings','ktickets_schema','ktickets_migration_error','ktickets_attack','ktickets_health'] as $name) { delete_option($name); }
$caps=['manage_ktickets_settings','view_ktickets','view_all_ktickets','reply_ktickets','assign_ktickets','view_ktickets_privacy','manage_ktickets_privacy','delete_ktickets'];
// Paginated cleanup also removes explicit grants to future agents.
$page=1;
do {
    $users=get_users(['number'=>100,'paged'=>$page++,'fields'=>'all']);
    foreach ($users as $user) { foreach ($caps as $cap) { $user->remove_cap($cap); } }
} while (count($users)===100);
wp_clear_scheduled_hook('ktickets_cleanup');
