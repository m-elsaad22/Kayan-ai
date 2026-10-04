<?php
defined('ABSPATH') || exit;

class RKS_Competitor {

    public function __construct() {
        add_action('wp_ajax_rks_analyze_url', [$this, 'analyze']);
    }

    public function analyze(): void {
        RKS_Helper::require_admin();
        $url = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$url || !$host) {
            wp_send_json_error(['msg' => 'رابط غير صالح']);
        }
        $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'];
        if (in_array($host, $blocked, true) || filter_var($host, FILTER_VALIDATE_IP)) {
            wp_send_json_error(['msg' => 'هذا الرابط غير مسموح']);
        }
        $res = wp_remote_get($url, ['timeout' => 12, 'redirection' => 3, 'user-agent' => 'RuknKayanSuite/1.0']);
        if (is_wp_error($res)) {
            wp_send_json_error(['msg' => $res->get_error_message()]);
        }
        $html  = wp_remote_retrieve_body($res);
        $title = '';
        $h1    = '';
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $title = wp_strip_all_tags($m[1]);
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $h1 = wp_strip_all_tags($m[1]);
        }
        $text = wp_strip_all_tags($html);
        $wc   = RKS_Helper::word_count($text);
        $faq  = substr_count(strtolower($html), 'faq') + substr_count($html, '<details');
        $score = min(100, (int) (($wc / 30) + ($faq * 5) + (strlen($title) > 20 ? 10 : 0)));
        $id = RKS_DB::insert('competitors', [
            'keyword'    => sanitize_text_field(wp_unslash($_POST['keyword'] ?? '')),
            'url'        => $url,
            'title'      => $title,
            'word_count' => $wc,
            'score'      => $score,
            'raw_data'   => wp_json_encode(['h1' => $h1, 'faq' => $faq]),
            'created_at' => current_time('mysql'),
        ]);
        $tips = [];
        if (RKS_AI::instance()->is_ready()) {
            $tips = RKS_AI::instance()->ask_json("حلّل منافساً بعنوان: {$title} وH1: {$h1} وعدد كلمات تقريباً {$wc}. أعط JSON: strengths[], gaps[], outline[] لنكتب مقالاً أفضل وأحصى.");
        }
        wp_send_json_success(['id' => $id, 'title' => $title, 'h1' => $h1, 'word_count' => $wc, 'score' => $score, 'tips' => $tips]);
    }
}
