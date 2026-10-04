<?php
defined('ABSPATH') || exit;

class RKS_Analyzer {

    public function __construct() {
        add_action('wp_ajax_rks_analyze_site', [$this, 'run']);
        add_action('wp_ajax_rks_fix_issue', [$this, 'fix']);
    }

    public function run(): void {
        RKS_Helper::require_admin();
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 50]);
        $issues = [];
        $score  = 100;
        foreach ($posts as $p) {
            $wc = RKS_Helper::word_count($p->post_content);
            if ($wc < 600) {
                $issues[] = ['post_id' => $p->ID, 'title' => $p->post_title, 'type' => 'short', 'msg' => 'المقال قصير (' . $wc . ' كلمة)'];
                $score -= 3;
            }
            if (!get_the_post_thumbnail_url($p->ID)) {
                $issues[] = ['post_id' => $p->ID, 'title' => $p->post_title, 'type' => 'thumb', 'msg' => 'لا توجد صورة بارزة'];
                $score -= 2;
            }
            $seo = RKS_DB::get_row('SELECT meta_desc FROM ' . RKS_DB::t('seo') . ' WHERE post_id=%d', [$p->ID]);
            if (!$seo || $seo->meta_desc === '') {
                $issues[] = ['post_id' => $p->ID, 'title' => $p->post_title, 'type' => 'meta', 'msg' => 'وصف ميتا ناقص'];
                $score -= 2;
            }
        }
        if (!RKS_Helper::opt('phone')) {
            $issues[] = ['post_id' => 0, 'title' => 'الإعدادات', 'type' => 'phone', 'msg' => 'رقم الهاتف غير مضبوط'];
            $score -= 5;
        }
        $report = ['score' => max(0, $score), 'issues' => array_slice($issues, 0, 40), 'posts' => count($posts)];
        update_option('rks_last_analysis', wp_json_encode($report), false);
        wp_send_json_success($report);
    }

    public function fix(): void {
        RKS_Helper::require_admin();
        $id   = (int) ($_POST['post_id'] ?? 0);
        $type = sanitize_key($_POST['type'] ?? '');
        $post = get_post($id);
        if (!$post || $type !== 'meta' || !RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'لا يمكن الإصلاح التلقائي لهذا البند']);
        }
        $data = RKS_AI::instance()->ask_json('اكتب meta_title و meta_desc لمقال: ' . $post->post_title);
        if ($data) {
            global $wpdb;
            $wpdb->replace(RKS_DB::t('seo'), [
                'post_id'    => $id,
                'meta_title' => sanitize_text_field($data['meta_title'] ?? $post->post_title),
                'meta_desc'  => sanitize_text_field($data['meta_desc'] ?? ''),
                'last_ai'    => current_time('mysql'),
            ]);
        }
        wp_send_json_success(['msg' => 'تم توليد الميتا']);
    }
}
