<?php
defined('ABSPATH') || exit;

class RKS_Heatmap {

    public function __construct() {
        add_action('wp_ajax_rks_heatmap_batch', [$this, 'batch']);
        add_action('wp_ajax_nopriv_rks_heatmap_batch', [$this, 'batch']);
        add_action('wp_ajax_rks_heatmap_data', [$this, 'data']);
    }

    public function batch(): void {
        if (!check_ajax_referer('rks_track', 'nonce', false)) {
            wp_send_json_error(['msg' => 'nonce'], 403);
        }
        if (!RKS_Helper::rate_limit('heat', 15, 60)) {
            wp_send_json_success();
        }
        $pts = json_decode(sanitize_textarea_field(wp_unslash($_POST['points'] ?? '[]')), true);
        if (!is_array($pts)) {
            wp_send_json_success();
        }
        $url = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));
        $sid = sanitize_text_field(wp_unslash($_POST['sid'] ?? ''));
        foreach (array_slice($pts, 0, 80) as $p) {
            if (!isset($p['x'], $p['y'])) {
                continue;
            }
            RKS_DB::insert('heatmap', [
                'session_id' => $sid,
                'page_url'   => $url,
                'x_pct'      => (int) $p['x'],
                'y_pct'      => (int) $p['y'],
                'element'    => sanitize_text_field(substr((string) ($p['el'] ?? ''), 0, 255)),
                'created_at' => current_time('mysql'),
            ]);
        }
        wp_send_json_success();
    }

    public function data(): void {
        RKS_Helper::require_admin();
        $url = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));
        $sql = 'SELECT x_pct, y_pct, element FROM ' . RKS_DB::t('heatmap');
        $args = [];
        if ($url) {
            $sql .= ' WHERE page_url = %s';
            $args[] = $url;
        }
        $sql .= ' ORDER BY id DESC LIMIT 3000';
        wp_send_json_success(['points' => RKS_DB::get_results($sql, $args)]);
    }
}
