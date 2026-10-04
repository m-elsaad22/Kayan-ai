<?php
defined('ABSPATH') || exit;

class RKS_Reports {

    public function __construct() {
        add_action('wp_ajax_rks_generate_report', [$this, 'generate']);
        add_action('wp_ajax_rks_list_reports', [$this, 'list']);
        add_action('wp_ajax_rks_export_csv', [$this, 'export']);
    }

    public function generate(): void {
        RKS_Helper::require_admin();
        $days  = max(1, min(365, (int) ($_POST['days'] ?? 30)));
        $exp   = max(1, min(90, (int) ($_POST['expiry_days'] ?? 7)));
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? 'تقرير'));
        [$since, $until] = RKS_Helper::datetime_range($days);
        $ct = RKS_DB::t('conversions');
        $vt = RKS_DB::t('visitors');
        $data = [
            'title'     => $title,
            'generated' => current_time('mysql'),
            'days'      => $days,
            'summary'   => [
                'conversions' => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE created_at>=%s AND created_at<=%s", [$since, $until]),
                'calls'       => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE click_type='call' AND created_at>=%s AND created_at<=%s", [$since, $until]),
                'whatsapps'   => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE click_type='whatsapp' AND created_at>=%s AND created_at<=%s", [$since, $until]),
                'visitors'    => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $vt WHERE last_visit>=%s AND last_visit<=%s", [$since, $until]),
            ],
            'top_pages'  => RKS_DB::get_results("SELECT page_title, page_url, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY page_url ORDER BY cnt DESC LIMIT 8", [$since]),
            'top_cities' => RKS_DB::get_results("SELECT city, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY city ORDER BY cnt DESC LIMIT 8", [$since]),
            'by_day'     => RKS_DB::get_results("SELECT DATE(created_at) dt, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY DATE(created_at) ORDER BY dt ASC", [$since]),
            'by_type'    => RKS_DB::get_results("SELECT click_type, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY click_type", [$since]),
            'by_device'  => RKS_DB::get_results("SELECT device_type, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY device_type", [$since]),
        ];
        $token = bin2hex(random_bytes(32));
        RKS_DB::insert('reports', [
            'token'        => $token,
            'title'        => $title,
            'filters_json' => wp_json_encode(['days' => $days]),
            'data_json'    => wp_json_encode($data),
            'created_by'   => get_current_user_id(),
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + ($exp * DAY_IN_SECONDS)),
            'created_at'   => current_time('mysql'),
        ]);
        wp_send_json_success(['url' => home_url('/?rks_report=' . $token)]);
    }

    public function list(): void {
        RKS_Helper::require_admin();
        wp_send_json_success(RKS_DB::get_results('SELECT id, token, title, expires_at, view_count, created_at FROM ' . RKS_DB::t('reports') . ' ORDER BY id DESC LIMIT 30'));
    }

    public function export(): void {
        check_admin_referer('rks_export', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('forbidden');
        }
        $days = max(1, (int) ($_GET['days'] ?? 30));
        [$since] = RKS_Helper::datetime_range($days);
        $rows = RKS_DB::get_results('SELECT * FROM ' . RKS_DB::t('conversions') . ' WHERE created_at>=%s ORDER BY id DESC LIMIT 10000', [$since]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rks-' . gmdate('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'الرقم', 'النوع', 'IP', 'المدينة', 'الصفحة', 'المصدر', 'مشبوه', 'التاريخ']);
        foreach ($rows as $r) {
            fputcsv($out, [$r->id, $r->phone_raw, $r->click_type, $r->ip, $r->city, $r->page_title, $r->traffic_src, $r->is_suspicious, $r->created_at]);
        }
        fclose($out);
        exit;
    }
}
