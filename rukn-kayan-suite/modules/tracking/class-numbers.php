<?php
defined('ABSPATH') || exit;

class RKS_Numbers {

    public function __construct() {
        add_action('wp_ajax_rks_save_number', [$this, 'save']);
        add_action('wp_ajax_rks_delete_number', [$this, 'delete']);
        add_action('wp_ajax_rks_list_numbers', [$this, 'list']);
    }

    public function save(): void {
        RKS_Helper::require_admin();
        $phone = RKS_Helper::clean_phone(sanitize_text_field(wp_unslash($_POST['phone'] ?? '')));
        if ($phone === '') {
            wp_send_json_error(['msg' => 'رقم الهاتف مطلوب']);
        }
        $id   = (int) ($_POST['id'] ?? 0);
        $data = [
            'label'     => sanitize_text_field(wp_unslash($_POST['label'] ?? '')),
            'phone'     => $phone,
            'wa_number' => RKS_Helper::clean_phone(sanitize_text_field(wp_unslash($_POST['wa_number'] ?? ''))) ?: $phone,
            'type'      => sanitize_key($_POST['type'] ?? 'both'),
            'color'     => sanitize_hex_color(wp_unslash($_POST['color'] ?? '')) ?: '#1a6bff',
            'note'      => sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')),
        ];
        if ($id) {
            RKS_DB::update('numbers', $data, ['id' => $id]);
            wp_send_json_success(['id' => $id, 'msg' => 'تم التحديث']);
        }
        $new = RKS_DB::insert('numbers', $data + ['created_at' => current_time('mysql')]);
        wp_send_json_success(['id' => $new, 'msg' => 'تمت الإضافة']);
    }

    public function delete(): void {
        RKS_Helper::require_admin();
        RKS_DB::update('numbers', ['active' => 0], ['id' => (int) ($_POST['id'] ?? 0)]);
        wp_send_json_success(['msg' => 'تم الحذف']);
    }

    public function list(): void {
        RKS_Helper::require_admin();
        $days  = max(1, (int) ($_POST['days'] ?? 30));
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        wp_send_json_success(RKS_DB::all_numbers(true, $since));
    }
}
