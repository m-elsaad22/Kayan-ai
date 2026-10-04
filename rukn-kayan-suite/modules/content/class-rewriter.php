<?php
defined('ABSPATH') || exit;

class RKS_Rewriter {

    public function __construct() {
        add_action('wp_ajax_rks_rewrite_post', [$this, 'rewrite']);
        add_action('wp_ajax_rks_list_posts', [$this, 'list_posts']);
    }

    public function list_posts(): void {
        RKS_Helper::require_admin();
        $cat = (int) ($_POST['cat'] ?? 0);
        $q   = [
            'post_type'      => 'post',
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => 80,
            'fields'         => 'ids',
        ];
        if ($cat) {
            $q['cat'] = $cat;
        }
        $ids = get_posts($q);
        $out = [];
        foreach ($ids as $id) {
            $out[] = ['id' => $id, 'title' => get_the_title($id), 'edit' => get_edit_post_link($id, 'raw')];
        }
        wp_send_json_success($out);
    }

    public function rewrite(): void {
        RKS_Helper::require_admin();
        $id = (int) ($_POST['post_id'] ?? 0);
        $p  = get_post($id);
        if (!$p || !RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'تعذّر إعادة الكتابة']);
        }
        $svc = (string) get_post_meta($id, '_rks_service', true);
        $city = (string) get_post_meta($id, '_rks_city', true);
        $gen = new RKS_Unique_Generator();
        $res = $gen->generate($svc ?: $p->post_title, $city ?: RKS_Helper::city_bare($p->post_title), [
            'force'      => true,
            'rewrite_of' => $id,
            'word_count' => max(1400, RKS_Helper::word_count($p->post_content)),
        ]);
        if (empty($res['ok'])) {
            wp_send_json_error(['msg' => $res['error'] ?? 'فشل']);
        }
        if (!empty($_POST['apply']) && $_POST['apply'] === '1') {
            wp_update_post(['ID' => $id, 'post_content' => $res['html'], 'post_title' => $res['title']]);
        }
        wp_send_json_success($res);
    }
}
