<?php
defined('ABSPATH') || exit;

class RKS_Schema {

    public function __construct() {
        add_action('wp_head', [$this, 'inject'], 2);
        add_action('wp_ajax_rks_gen_schema', [$this, 'ajax']);
    }

    public function inject(): void {
        if (!RKS_Helper::opt('auto_schema', true)) {
            return;
        }
        if (is_front_page()) {
            $this->out([
                '@context'  => 'https://schema.org',
                '@type'     => 'Organization',
                'name'      => RKS_Helper::company(),
                'url'       => home_url(),
                'telephone' => RKS_Helper::opt('phone'),
            ]);
            return;
        }
        if (!is_singular()) {
            return;
        }
        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return;
        }
        $this->out([
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => get_the_title($post),
            'url'           => get_permalink($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified'  => get_the_modified_date('c', $post),
            'author'        => ['@type' => 'Organization', 'name' => RKS_Helper::company()],
        ]);
        $city = get_post_meta($post->ID, '_rks_city', true);
        $svc  = get_post_meta($post->ID, '_rks_service', true);
        if ($city) {
            $this->out([
                '@context'  => 'https://schema.org',
                '@type'     => 'LocalBusiness',
                'name'      => RKS_Helper::company(),
                'telephone' => RKS_Helper::opt('phone'),
                'url'       => get_permalink($post),
                'areaServed'=> ['@type' => 'City', 'name' => RKS_Helper::city_bare((string) $city)],
                'description'=> $svc ? $svc . ' ' . $city : get_the_excerpt($post),
            ]);
        }
        $seo = RKS_DB::get_row('SELECT faq_json FROM ' . RKS_DB::t('seo') . ' WHERE post_id=%d', [$post->ID]);
        $faq = $seo ? json_decode($seo->faq_json, true) : [];
        if (is_array($faq) && $faq) {
            $items = [];
            foreach ($faq as $f) {
                $q = $f['q'] ?? $f['question'] ?? '';
                $a = $f['a'] ?? $f['answer'] ?? '';
                if ($q && $a) {
                    $items[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => wp_strip_all_tags($a)]];
                }
            }
            if ($items) {
                $this->out(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items]);
            }
        }
    }

    public function ajax(): void {
        RKS_Helper::require_admin();
        $id = (int) ($_POST['post_id'] ?? 0);
        if (!$id || !RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'بيانات ناقصة أو AI غير متصل']);
        }
        $post = get_post($id);
        $json = RKS_AI::instance()->ask_json('ولّد Schema.org JSON-LD لمقال: ' . $post->post_title . "\n" . mb_substr(wp_strip_all_tags($post->post_content), 0, 600));
        wp_send_json_success($json);
    }

    private function out(array $schema): void {
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}
