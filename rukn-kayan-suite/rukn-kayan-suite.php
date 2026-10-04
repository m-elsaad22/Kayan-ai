<?php
/**
 * Plugin Name:  ركن كيان الشامل — Rukn Kayan Suite
 * Plugin URI:   https://rukn-eltatawer.com
 * Description:  إضافة واحدة تجمع: توليد مقالات حصرية بالذكاء الاصطناعي، تتبع مكالمات/واتساب، DNI، احتيال، Heatmap، استيراد CSV، Schema، ترجمة، ربط داخلي، تحويل زوار، تحليل منافسين.
 * Version:      1.0.0
 * Author:       Mahmoud Elsaad — ركن التطور
 * Author URI:   https://rukn-eltatawer.com
 * Text Domain:  rukn-kayan-suite
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * License:      GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle): bool {
        return $needle === '' || strpos((string) $haystack, (string) $needle) !== false;
    }
}

define('RKS_VERSION',    '1.0.0');
define('RKS_FILE',       __FILE__);
define('RKS_DIR',        plugin_dir_path(__FILE__));
define('RKS_URL',        plugin_dir_url(__FILE__));
define('RKS_SLUG',       'rukn-kayan-suite');
define('RKS_DB_VERSION', '1.0.0');

spl_autoload_register(static function (string $class): void {
    $map = [
        'RKS_Plugin'            => 'includes/class-plugin.php',
        'RKS_DB'                => 'includes/class-db.php',
        'RKS_Helper'            => 'includes/class-helper.php',
        'RKS_AI'                => 'includes/class-ai.php',
        'RKS_Gemini'            => 'includes/class-gemini.php',
        'RKS_Claude'            => 'includes/class-claude.php',
        'RKS_Unique_Generator'  => 'modules/generator/class-unique-generator.php',
        'RKS_Meta_Writer'       => 'modules/generator/class-meta-writer.php',
        'RKS_Tracker'           => 'modules/tracking/class-tracker.php',
        'RKS_Clicks'            => 'modules/tracking/class-clicks.php',
        'RKS_Numbers'           => 'modules/tracking/class-numbers.php',
        'RKS_DNI'               => 'modules/tracking/class-dni.php',
        'RKS_Heatmap'           => 'modules/tracking/class-heatmap.php',
        'RKS_Reports'           => 'modules/tracking/class-reports.php',
        'RKS_CSV'               => 'modules/csv/class-csv.php',
        'RKS_Schema'            => 'modules/schema/class-schema.php',
        'RKS_SEO'               => 'modules/seo/class-seo.php',
        'RKS_Translate'         => 'modules/translate/class-translate.php',
        'RKS_Rewriter'          => 'modules/content/class-rewriter.php',
        'RKS_Linking'           => 'modules/linking/class-linking.php',
        'RKS_Competitor'        => 'modules/competitor/class-competitor.php',
        'RKS_Conversion'        => 'modules/conversion/class-conversion.php',
        'RKS_Analyzer'          => 'modules/analyzer/class-analyzer.php',
        'RKS_Slug'              => 'modules/slug/class-slug.php',
        'RKS_Admin'             => 'admin/class-admin.php',
    ];
    if (isset($map[$class])) {
        $file = RKS_DIR . $map[$class];
        if (is_readable($file)) {
            require_once $file;
        }
    }
});

register_activation_hook(RKS_FILE, static function (): void {
    RKS_DB::install();
    RKS_Plugin::seed_defaults();
    flush_rewrite_rules();
});

register_deactivation_hook(RKS_FILE, static function (): void {
    flush_rewrite_rules();
});

add_action('plugins_loaded', static function (): void {
    RKS_Plugin::instance();
});
