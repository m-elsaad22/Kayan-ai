<?php
defined('ABSPATH') || exit;

class RKS_Slug {

    public function __construct() {
        add_filter('wp_insert_post_data', [$this, 'maybe_slug'], 20, 2);
        add_action('wp_ajax_rks_preview_slug', [$this, 'preview']);
    }

    public function maybe_slug(array $data, array $postarr): array {
        if (($data['post_type'] ?? '') !== 'post') {
            return $data;
        }
        if (!empty($data['post_name']) && !preg_match('/[%\p{Arabic}]/u', rawurldecode($data['post_name']))) {
            return $data;
        }
        if (!empty($data['post_title'])) {
            $data['post_name'] = RKS_Helper::slug($data['post_title']);
        }
        return $data;
    }

    public function preview(): void {
        RKS_Helper::require_admin();
        $t = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        wp_send_json_success(['slug' => RKS_Helper::slug($t)]);
    }
}
