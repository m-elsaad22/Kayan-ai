<?php
defined('ABSPATH') || exit;

class RKS_DNI {

    public function __construct() {
        add_action('wp_ajax_rks_get_dni', [$this, 'get']);
        add_action('wp_ajax_nopriv_rks_get_dni', [$this, 'get']);
        add_action('wp_ajax_rks_save_dni', [$this, 'save']);
        add_action('wp_ajax_rks_delete_dni', [$this, 'delete']);
        add_action('wp_ajax_rks_list_dni', [$this, 'list']);
    }

    public function get(): void {
        if (!check_ajax_referer('rks_track', 'nonce', false)) {
            wp_send_json_error(['msg' => 'nonce'], 403);
        }
        $src   = sanitize_key($_POST['traffic_src'] ?? 'direct');
        $utm_s = sanitize_text_field(wp_unslash($_POST['utm_source'] ?? ''));
        $utm_c = sanitize_text_field(wp_unslash($_POST['utm_campaign'] ?? ''));
        $rules = RKS_DB::get_results(
            'SELECT r.*, n.phone, n.wa_number, n.label FROM ' . RKS_DB::t('dni_rules') . ' r
             JOIN ' . RKS_DB::t('numbers') . ' n ON n.id=r.number_id
             WHERE r.active=1 AND n.active=1 ORDER BY r.priority DESC, r.id ASC'
        );
        foreach ($rules as $rule) {
            $ok = false;
            if ($rule->source_type && $rule->source_type === $src) {
                $ok = true;
            }
            if ($rule->utm_source && $utm_s && stripos($utm_s, $rule->utm_source) !== false) {
                $ok = true;
            }
            if ($rule->utm_campaign && $utm_c && stripos($utm_c, $rule->utm_campaign) !== false) {
                $ok = true;
            }
            if ($ok) {
                wp_send_json_success([
                    'phone'     => $rule->phone,
                    'wa_number' => $rule->wa_number,
                    'display'   => RKS_Helper::format_phone($rule->phone),
                    'label'     => $rule->label,
                ]);
            }
        }
        wp_send_json_success(null);
    }

    public function save(): void {
        RKS_Helper::require_admin();
        $id   = (int) ($_POST['id'] ?? 0);
        $data = [
            'number_id'    => (int) ($_POST['number_id'] ?? 0),
            'source_type'  => sanitize_key($_POST['source_type'] ?? 'direct'),
            'utm_source'   => sanitize_text_field(wp_unslash($_POST['utm_source'] ?? '')),
            'utm_campaign' => sanitize_text_field(wp_unslash($_POST['utm_campaign'] ?? '')),
            'priority'     => (int) ($_POST['priority'] ?? 0),
            'active'       => 1,
        ];
        if ($id) {
            RKS_DB::update('dni_rules', $data, ['id' => $id]);
        } else {
            RKS_DB::insert('dni_rules', $data + ['created_at' => current_time('mysql')]);
        }
        wp_send_json_success(['msg' => 'تم الحفظ']);
    }

    public function delete(): void {
        RKS_Helper::require_admin();
        global $wpdb;
        $wpdb->delete(RKS_DB::t('dni_rules'), ['id' => (int) ($_POST['id'] ?? 0)]);
        wp_send_json_success(['msg' => 'تم الحذف']);
    }

    public function list(): void {
        RKS_Helper::require_admin();
        wp_send_json_success(RKS_DB::get_results(
            'SELECT r.*, n.label, n.phone, n.color FROM ' . RKS_DB::t('dni_rules') . ' r
             JOIN ' . RKS_DB::t('numbers') . ' n ON n.id=r.number_id ORDER BY r.priority DESC'
        ));
    }
}
