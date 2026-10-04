<?php
defined('ABSPATH') || exit;

final class RKS_Plugin {

    private static ?self $inst = null;
    public array $modules = [];

    public static function instance(): self {
        if (!self::$inst) {
            self::$inst = new self();
        }
        return self::$inst;
    }

    private function __construct() {
        if ((string) get_option('rks_db_version') !== RKS_DB_VERSION) {
            RKS_DB::install();
        }
        $this->boot();
    }

    public static function seed_defaults(): void {
        if (!get_option('rks_settings')) {
            $d = RKS_Helper::defaults();
            $d['company_name'] = get_bloginfo('name');
            update_option('rks_settings', $d, false);
        }
    }

    private function boot(): void {
        $s = RKS_Helper::settings();

        if (!empty($s['tracking_enabled'])) {
            $this->modules['tracker']  = new RKS_Tracker();
            $this->modules['clicks']   = new RKS_Clicks();
            $this->modules['numbers']  = new RKS_Numbers();
            $this->modules['dni']      = new RKS_DNI();
            $this->modules['heatmap']  = new RKS_Heatmap();
            $this->modules['reports']  = new RKS_Reports();
        }

        $this->modules['generator']   = new RKS_Unique_Generator();
        $this->modules['csv']         = new RKS_CSV();
        $this->modules['schema']      = new RKS_Schema();
        $this->modules['seo']         = new RKS_SEO();
        $this->modules['translate']   = new RKS_Translate();
        $this->modules['rewriter']    = new RKS_Rewriter();
        $this->modules['linking']     = new RKS_Linking();
        $this->modules['competitor']  = new RKS_Competitor();
        $this->modules['conversion']  = new RKS_Conversion();
        $this->modules['analyzer']    = new RKS_Analyzer();
        $this->modules['slug']        = new RKS_Slug();

        if (is_admin()) {
            $this->modules['admin'] = new RKS_Admin();
        }

        add_action('init', [$this, 'public_report']);
    }

    public function public_report(): void {
        $token = sanitize_text_field(wp_unslash($_GET['rks_report'] ?? ''));
        if ($token && strlen($token) === 64) {
            require_once RKS_DIR . 'public/report-viewer.php';
            rks_render_public_report($token);
            exit;
        }
    }
}
