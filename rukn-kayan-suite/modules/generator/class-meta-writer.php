<?php
defined('ABSPATH') || exit;

class RKS_Meta_Writer {

    public function build(array $a): array {
        $service  = $a['service'] ?? '';
        $city     = $a['city'] ?? '';
        $bare     = RKS_Helper::city_bare($city);
        $core     = RKS_Helper::service_core($service);
        $title    = $a['title'] ?? ($service . ' ' . $city);
        $desc     = $a['meta_desc'] ?? '';
        $phone    = (string) RKS_Helper::opt('phone');
        $wa       = (string) RKS_Helper::opt('whatsapp');
        $brand    = RKS_Helper::company();
        $faq      = is_array($a['faq'] ?? null) ? $a['faq'] : [];
        $features = is_array($a['features'] ?? null) ? $a['features'] : [];
        $steps    = is_array($a['steps'] ?? null) ? $a['steps'] : [];

        $faq_meta = [];
        $faq_old  = [];
        foreach (array_slice($faq, 0, 10) as $i => $item) {
            $q = sanitize_text_field($item['q'] ?? $item['question'] ?? '');
            $ans = wp_kses_post($item['a'] ?? $item['answer'] ?? '');
            $faq_meta['faq' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)] = ['question' => $q, 'answer' => $ans];
            $faq_old[$i] = ['question' => $q, 'answer' => $ans];
        }

        $feat_items = [];
        foreach (array_slice($features, 0, 8) as $i => $f) {
            $feat_items['feat' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)] = [
                'title'   => sanitize_text_field($f['title'] ?? ''),
                'content' => wp_kses_post($f['content'] ?? ''),
                'icon'    => '<i class="fa-duotone fa-circle-check"></i>',
            ];
        }
        $step_items = [];
        foreach (array_slice($steps, 0, 8) as $i => $s) {
            $step_items['step' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)] = [
                'title'   => sanitize_text_field($s['title'] ?? ''),
                'content' => wp_kses_post($s['content'] ?? ''),
            ];
        }

        $price_min = 80 + (crc32($title) % 140);
        $price_max = $price_min + 250 + (crc32($bare) % 400);

        return [
            '_rks_service'                  => $service,
            '_rks_city'                     => $city,
            '_rks_angle'                    => $a['angle'] ?? '',
            '_rks_persona'                  => $a['persona'] ?? '',
            '_rks_structure'                => $a['structure'] ?? '',
            '_rks_fingerprint'              => $a['fingerprint'] ?? '',
            '_rks_uniqueness'               => (int) ($a['uniqueness'] ?? 0),
            'rank_math_title'               => $a['meta_title'] ?? $title,
            'rank_math_description'         => $desc,
            'rank_math_focus_keyword'       => $title,
            '_yoast_wpseo_title'            => $a['meta_title'] ?? $title,
            '_yoast_wpseo_metadesc'         => $desc,
            '_yoast_wpseo_focuskw'          => $title,
            '_aioseo_title'                 => $a['meta_title'] ?? $title,
            '_aioseo_description'           => $desc,
            '_vip_meta_title'               => $a['meta_title'] ?? $title,
            '_vip_meta_description'         => $desc,
            'phone'                         => $phone,
            'phone_number'                  => $phone,
            'whatsapp'                      => $wa,
            'whatsapp_number'               => $wa,
            'YourColor_Service'             => [
                'priceRange'      => $price_min . '–' . $price_max,
                'description'     => $desc,
                'addressLocality' => $bare,
                'telephone'       => $phone,
                'areaServed'      => $bare,
                'identifier'      => $title,
                'additionalType'  => $core . ' في ' . $bare,
            ],
            'YourColor_Article'             => ['headline' => $bare, 'description' => $title],
            'post__features__data'          => [
                'features__title'          => 'مميزات ' . $service . ' ' . $city,
                'yourcolor__post_features' => $feat_items,
            ],
            'post__work_steps__data'        => [
                'work_steps__title' => 'خطوات ' . $core,
                'work_steps_items'  => $step_items,
            ],
            'yourcolor__faqs'               => $faq_meta,
            'faq'                           => $faq_old,
            'post__call_section__data'      => [
                'call_section_title'    => 'تواصل مع ' . $brand,
                'call_section_content'  => $desc,
                'call_section_phone'    => $phone,
                'call_section_whatsapp' => $wa,
            ],
            'last_update'                   => gmdate('Y-m-d'),
        ];
    }
}
