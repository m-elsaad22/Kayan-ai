<?php
defined('ABSPATH') || exit;

class RKS_Translate {

    public function __construct() {
        add_action('wp_ajax_rks_translate', [$this, 'text']);
        add_action('wp_ajax_rks_translate_post', [$this, 'post']);
    }

    public function text(): void {
        RKS_Helper::require_admin();
        $text = sanitize_textarea_field(wp_unslash($_POST['text'] ?? ''));
        $from = sanitize_key($_POST['from'] ?? 'ar');
        $to   = sanitize_key($_POST['to'] ?? 'en');
        if ($text === '' || !RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'النص فارغ أو AI غير متصل']);
        }
        $out = RKS_AI::instance()->ask("ترجم من {$from} إلى {$to}. أعد النص المترجم فقط:\n\n{$text}", 4000);
        wp_send_json_success(['text' => $out]);
    }

    public function post(): void {
        RKS_Helper::require_admin();
        $id = (int) ($_POST['post_id'] ?? 0);
        $to = sanitize_key($_POST['to'] ?? 'en');
        $p  = get_post($id);
        if (!$p || !RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'مقال غير موجود']);
        }
        $title = RKS_AI::instance()->ask('ترجم العنوان إلى ' . $to . ' فقط:\n' . $p->post_title, 200);
        $body  = RKS_AI::instance()->ask('ترجم المحتوى التالي إلى ' . $to . ' مع الإبقاء على HTML:\n' . $p->post_content, 6000);
        wp_send_json_success(['title' => $title, 'content' => $body]);
    }
}
