<?php
/**
 * Plugin Name: Kourentzes Tickets
 * Description: Private anonymous ticket conversations with local abuse protection. No email or accounts required.
 * Version: 0.1.0
 * Requires at least: 7.1.3
 * Requires PHP: 8.2
 * Author: Konstantinos Kourentzes
 * License: GPL-2.0-or-later
 * Text Domain: kourentzes-tickets
 */
if (!defined('ABSPATH')) { exit; }
define('KTICKETS_VERSION', '0.1.0');
define('KTICKETS_FILE', __FILE__);
foreach (['store', 'security', 'service', 'rest', 'ui'] as $part) {
    require_once __DIR__ . '/includes/class-' . $part . '.php';
}
register_activation_hook(__FILE__, ['KTickets\\Store', 'install']);
register_deactivation_hook(__FILE__, static function () { wp_clear_scheduled_hook('ktickets_cleanup'); });
add_action('plugins_loaded', static function () {
    if (get_option('ktickets_schema') !== KTICKETS_VERSION) { KTickets\Store::install(); }
    load_plugin_textdomain('kourentzes-tickets', false, dirname(plugin_basename(__FILE__)) . '/languages');
    KTickets\REST::boot();
    KTickets\UI::boot();
});
add_action('ktickets_cleanup', ['KTickets\\Service', 'cleanup']);
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('ktickets cleanup', static function () { KTickets\Service::cleanup(); WP_CLI::success('Cleanup completed. Inspect Tickets health for results.'); });
}
