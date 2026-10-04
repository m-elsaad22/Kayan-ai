<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;
$tables = [
    'rks_visitors', 'rks_sessions', 'rks_conversions', 'rks_numbers', 'rks_dni_rules',
    'rks_heatmap', 'rks_blacklist', 'rks_reports', 'rks_articles', 'rks_seo',
    'rks_links', 'rks_competitors', 'rks_offers', 'rks_logs', 'rks_import_log',
];
foreach ($tables as $t) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . $t);
}
delete_option('rks_settings');
delete_option('rks_db_version');
delete_option('rks_import_session');
delete_option('rks_last_analysis');
