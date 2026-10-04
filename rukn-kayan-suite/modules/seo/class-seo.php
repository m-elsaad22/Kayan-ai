<?php
defined('ABSPATH') || exit;

class RKS_SEO {

    public function __construct() {
        add_action('wp_head', [$this, 'meta_tags'], 1);
        add_action('wp_ajax_rks_gen_seo', [$this, 'ajax']);
        add_action('add_meta_boxes', [$this, 'box']);
        add_action('save_post', [$this, 'save'], 20, 2);
    }

    public function meta_tags(): void {
        if (!is_singular()) {
            return;
        }
        $id  = get_the_ID();
        $row = RKS_DB::get_row('SELECT meta_title, meta_desc FROM ' . RKS_DB::t('seo') . ' WHERE post_id=%d', [$id]);
        $title = $row->meta_title ?? get_post_meta($id, 'rank_math_title', true);
        $desc  = $row->meta_desc ?? get_post_meta($id, 'rank_math_description', true);
        if ($title) {
            echo '<meta property="og:title" content="' . esc_attr($title) . "\" />\n";
        }
        if ($desc) {
            echo '<meta name="description" content="' . esc_attr($desc) . "\" />\n";
        }
    }

    public function ajax(): void {
        RKS_Helper::require_admin();
        $kw = sanitize_text_field(wp_unslash($_POST['keyword'] ?? ''));
        if ($kw === '' || !RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'أدخل كلمة مفتاحية وتأكد من مفتاح AI']);
        }
        $data = RKS_AI::instance()->ask_json(
            "اكتب حزمة SEO عربية للكلمة: {$kw} لموقع " . RKS_Helper::company() . " في " . RKS_Helper::country_name() .
            "\nأرجع JSON: meta_title, meta_desc, h1, intro, faq (مصفوفة q/a), lsi (مصفوفة)"
        );
        wp_send_json_success($data);
    }

    public function box(): void {
        add_meta_box('rks_seo_box', 'ركن كيان — SEO', [$this, 'render_box'], 'post', 'normal');
    }

    public function render_box(WP_Post $post): void {
        wp_nonce_field('rks_seo_box', 'rks_seo_nonce');
        $row = RKS_DB::get_row('SELECT * FROM ' . RKS_DB::t('seo') . ' WHERE post_id=%d', [$post->ID]);
        echo '<p><label>Meta Title</label><input class="widefat" name="rks_meta_title" value="' . esc_attr($row->meta_title ?? '') . '"></p>';
        echo '<p><label>Meta Description</label><textarea class="widefat" name="rks_meta_desc" rows="3">' . esc_textarea($row->meta_desc ?? '') . '</textarea></p>';
        echo '<p><label>الكلمة المفتاحية</label><input class="widefat" name="rks_focus_kw" value="' . esc_attr($row->focus_kw ?? '') . '"></p>';
    }

    public function save(int $post_id, WP_Post $post): void {
        if (!isset($_POST['rks_seo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rks_seo_nonce'])), 'rks_seo_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        global $wpdb;
        $wpdb->replace(RKS_DB::t('seo'), [
            'post_id'    => $post_id,
            'meta_title' => sanitize_text_field(wp_unslash($_POST['rks_meta_title'] ?? '')),
            'meta_desc'  => sanitize_textarea_field(wp_unslash($_POST['rks_meta_desc'] ?? '')),
            'focus_kw'   => sanitize_text_field(wp_unslash($_POST['rks_focus_kw'] ?? '')),
            'last_ai'    => current_time('mysql'),
        ]);
    }
}
