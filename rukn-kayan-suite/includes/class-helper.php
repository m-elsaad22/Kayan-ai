<?php
defined('ABSPATH') || exit;

class RKS_Helper {

    public static function opt(string $key, $default = '') {
        $all = self::settings();
        return $all[$key] ?? $default;
    }

    public static function settings(): array {
        $saved = get_option('rks_settings', []);
        return is_array($saved) ? array_merge(self::defaults(), $saved) : self::defaults();
    }

    public static function defaults(): array {
        return [
            'enabled'           => true,
            'cookie_consent'    => true,
            'heatmap_enabled'   => true,
            'dni_enabled'       => false,
            'tracking_enabled'  => true,
            'sticky_bar'        => true,
            'smart_popup'       => true,
            'auto_schema'       => true,
            'data_retention'    => 90,
            'fraud_threshold'   => 3,
            'auto_blacklist'    => 5,
            'cooldown_mins'     => 30,
            'report_expiry'     => 7,
            'ai_provider'       => 'auto',
            'gemini_key'        => '',
            'claude_key'        => '',
            'claude_model'      => 'claude-sonnet-4-20250514',
            'company_name'      => '',
            'phone'             => '',
            'whatsapp'          => '',
            'country'           => 'ae',
            'telegram_token'    => '',
            'telegram_chat'     => '',
            'notify_telegram'   => false,
            'word_count'        => 2200,
            'tone'              => 'احترافي ومقنع',
            'uniqueness_min'    => 62,
        ];
    }

    public static function set_opt(string $key, $value): void {
        $all = self::settings();
        $all[$key] = $value;
        update_option('rks_settings', $all, false);
    }

    public static function update_settings(array $patch): array {
        $all = array_merge(self::settings(), $patch);
        update_option('rks_settings', $all, false);
        return $all;
    }

    public static function country_name(string $code = ''): string {
        $code = $code ?: (string) self::opt('country', 'ae');
        $map  = [
            'ae' => 'الإمارات',
            'sa' => 'السعودية',
            'qa' => 'قطر',
            'kw' => 'الكويت',
            'bh' => 'البحرين',
            'om' => 'عُمان',
            'eg' => 'مصر',
        ];
        return $map[$code] ?? $code;
    }

    public static function company(): string {
        $name = (string) self::opt('company_name');
        return $name !== '' ? $name : (string) get_bloginfo('name');
    }

    public static function clean_phone(string $raw): string {
        $p = preg_replace('/[^0-9]/', '', ltrim(trim($raw), '+'));
        return $p ?: $raw;
    }

    public static function format_phone(string $phone): string {
        $c = self::clean_phone($phone);
        return strlen($c) >= 9 ? '+' . $c : $c;
    }

    public static function client_ip(): string {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = apply_filters('rks_trusted_proxy', !empty($_SERVER['HTTP_CF_CONNECTING_IP']));
        if ($trusted && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public static function geo(string $ip): array {
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
            return ['country' => 'Local', 'city' => 'Local'];
        }
        $cached = get_transient('rks_geo_' . md5($ip));
        if (is_array($cached)) {
            return $cached;
        }
        $res = wp_remote_get('https://ipapi.co/' . rawurlencode($ip) . '/json/', [
            'timeout' => 4,
        ]);
        if (is_wp_error($res)) {
            return ['country' => '', 'city' => ''];
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        $geo  = [
            'country' => sanitize_text_field($data['country_name'] ?? ''),
            'city'    => sanitize_text_field($data['city'] ?? ''),
        ];
        set_transient('rks_geo_' . md5($ip), $geo, DAY_IN_SECONDS);
        return $geo;
    }

    public static function device(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/iPad|Tablet/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobi|Android|iPhone/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function is_bot(): bool {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        foreach (['bot', 'spider', 'crawl', 'semrush', 'ahrefs', 'mj12', 'bytespider', 'curl', 'wget'] as $b) {
            if (str_contains($ua, $b)) {
                return true;
            }
        }
        return false;
    }

    public static function slug(string $text): string {
        $map = [
            'أ' => 'a', 'إ' => 'a', 'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th',
            'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z',
            'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
            'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'a', 'ء' => '', 'ئ' => 'y',
            'ؤ' => 'w', ' ' => '-',
        ];
        $text = preg_replace('/^(في\s+|بـ|إلى\s+|الى\s+)/u', '', trim($text));
        $text = strtr($text, $map);
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        $text = preg_replace('/[^a-zA-Z0-9\-]/', '', $text);
        $text = preg_replace('/-+/', '-', $text);
        return strtolower(trim((string) $text, '-'));
    }

    public static function city_bare(string $city): string {
        return trim((string) preg_replace('/^(في\s+|بـ|ب(?=[^ ])|إلى\s+|الى\s+)/u', '', trim($city)));
    }

    public static function service_core(string $service): string {
        return trim((string) preg_replace('/^شركة\s+/u', '', trim($service)));
    }

    public static function parse_json(string $raw): array {
        $raw = preg_replace('/```json\s*/i', '', $raw);
        $raw = preg_replace('/```\s*/', '', $raw);
        $raw = trim((string) $raw);
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false) {
            $raw = substr($raw, $start, $end - $start + 1);
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function datetime_range(int $days = 30, string $from = '', string $to = ''): array {
        $days = max(1, min(365, $days));
        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $since = $from . ' 00:00:00';
        } else {
            $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
            $since = get_date_from_gmt($since);
        }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $until = $to . ' 23:59:59';
        } else {
            $until = current_time('mysql');
        }
        return [$since, $until];
    }

    public static function require_admin(string $nonce_action = 'rks_admin'): void {
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(['msg' => 'رمز الأمان غير صالح'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['msg' => 'غير مصرح'], 403);
        }
    }

    public static function rate_limit(string $key, int $max = 40, int $window = 60): bool {
        $ip  = self::client_ip();
        $id  = 'rks_rl_' . md5($key . '|' . $ip);
        $n   = (int) get_transient($id);
        if ($n >= $max) {
            return false;
        }
        set_transient($id, $n + 1, $window);
        return true;
    }

    public static function word_count(string $html): int {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
        if ($text === '') {
            return 0;
        }
        if (function_exists('mb_strlen') && preg_match('/[\p{Arabic}]/u', $text)) {
            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            return is_array($words) ? count($words) : 0;
        }
        return str_word_count($text);
    }

    public static function trigrams(string $text): array {
        $text = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', wp_strip_all_tags($text)));
        $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $grams = [];
        $count = count($words);
        if ($count < 3) {
            return $words;
        }
        for ($i = 0; $i < $count - 2; $i++) {
            $grams[] = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
        }
        return array_values(array_unique($grams));
    }

    public static function jaccard(array $a, array $b): float {
        if (!$a || !$b) {
            return 0.0;
        }
        $inter = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union > 0 ? round(($inter / $union) * 100, 2) : 0.0;
    }
}
