<?php
defined('ABSPATH') || exit;

class RKS_Linking {

    public function __construct() {
        add_action('wp_ajax_rks_scan_links', [$this, 'scan']);
        add_action('wp_ajax_rks_apply_link', [$this, 'apply']);
    }

    public function scan(): void {
        RKS_Helper::require_admin();
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 80]);
        $pairs = [];
        foreach ($posts as $src) {
            $svc = (string) get_post_meta($src->ID, '_rks_service', true);
            if ($svc === '') {
                continue;
            }
            foreach ($posts as $dst) {
                if ($src->ID === $dst->ID) {
                    continue;
                }
                $dst_svc = (string) get_post_meta($dst->ID, '_rks_service', true);
                if ($dst_svc && $dst_svc !== $svc && mb_stripos($src->post_content, $dst_svc) !== false && mb_stripos($src->post_content, get_permalink($dst)) === false) {
                    $pairs[] = [
                        'source_id' => $src->ID,
                        'target_id' => $dst->ID,
                        'source'    => $src->post_title,
                        'target'    => $dst->post_title,
                        'anchor'    => $dst_svc,
                    ];
                }
            }
        }
        wp_send_json_success(array_slice($pairs, 0, 60));
    }

    public function apply(): void {
        RKS_Helper::require_admin();
        $src = (int) ($_POST['source_id'] ?? 0);
        $dst = (int) ($_POST['target_id'] ?? 0);
        $anchor = sanitize_text_field(wp_unslash($_POST['anchor'] ?? ''));
        $post = get_post($src);
        $target = get_post($dst);
        if (!$post || !$target || $anchor === '') {
            wp_send_json_error(['msg' => 'بيانات ناقصة']);
        }
        $link = '<a href="' . esc_url(get_permalink($target)) . '">' . esc_html($anchor) . '</a>';
        $html = preg_replace('/' . preg_quote($anchor, '/') . '/u', $link, $post->post_content, 1);
        wp_update_post(['ID' => $src, 'post_content' => $html]);
        RKS_DB::insert('links', [
            'source_id'   => $src,
            'target_id'   => $dst,
            'anchor_text' => $anchor,
            'created_at'  => current_time('mysql'),
        ]);
        wp_send_json_success(['msg' => 'تم الربط']);
    }
}
