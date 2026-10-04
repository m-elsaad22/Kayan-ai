<?php
defined('ABSPATH') || exit;

class RKS_Clicks {

    public function __construct() {
        add_action('wp_ajax_rks_track_click', [$this, 'record']);
        add_action('wp_ajax_nopriv_rks_track_click', [$this, 'record']);
        add_action('wp_ajax_rks_block_ip', [$this, 'block']);
        add_action('wp_ajax_rks_unblock_ip', [$this, 'unblock']);
    }

    public function record(): void {
        if (!check_ajax_referer('rks_track', 'nonce', false)) {
            wp_send_json_error(['msg' => 'nonce'], 403);
        }
        if (!RKS_Helper::rate_limit('click', 20, 60)) {
            wp_send_json_success(['limited' => true]);
        }
        $ip = RKS_Helper::client_ip();
        if (RKS_DB::is_blacklisted($ip)) {
            wp_send_json_success(['blocked' => true]);
        }
        $phone = RKS_Helper::clean_phone(sanitize_text_field(wp_unslash($_POST['phone_number'] ?? '')));
        $type  = sanitize_key($_POST['click_type'] ?? '');
        $fp    = sanitize_text_field(wp_unslash($_POST['fp'] ?? ''));
        if ($type === '') {
            wp_send_json_success(['skip' => true]);
        }
        $cool = (int) RKS_Helper::opt('cooldown_mins', 30);
        if ($fp && $phone) {
            $dup = RKS_DB::get_var(
                'SELECT id FROM ' . RKS_DB::t('conversions') . ' WHERE fingerprint=%s AND phone_raw=%s AND created_at >= %s LIMIT 1',
                [$fp, $phone, gmdate('Y-m-d H:i:s', time() - ($cool * 60))]
            );
            if ($dup) {
                wp_send_json_success(['duplicate' => true]);
            }
        }
        $cnt = (int) RKS_DB::get_var(
            'SELECT COUNT(*) FROM ' . RKS_DB::t('conversions') . ' WHERE ip=%s AND created_at >= %s',
            [$ip, gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)]
        );
        $sus = $cnt >= (int) RKS_Helper::opt('fraud_threshold', 3) ? 1 : 0;
        $geo = RKS_Helper::geo($ip);
        $num = $phone ? RKS_DB::find_number($phone) : null;
        RKS_DB::insert('conversions', [
            'number_id'    => $num ? (int) $num->id : null,
            'phone_raw'    => $phone,
            'session_id'   => sanitize_text_field(wp_unslash($_POST['sid'] ?? '')),
            'fingerprint'  => $fp,
            'ip'           => $ip,
            'country'      => $geo['country'],
            'city'         => $geo['city'],
            'device_type'  => sanitize_text_field(wp_unslash($_POST['device_type'] ?? RKS_Helper::device())),
            'browser'      => sanitize_text_field(wp_unslash($_POST['browser'] ?? '')),
            'os'           => sanitize_text_field(wp_unslash($_POST['os'] ?? '')),
            'click_type'   => $type,
            'page_url'     => esc_url_raw(wp_unslash($_POST['page_url'] ?? '')),
            'page_title'   => sanitize_text_field(wp_unslash($_POST['page_title'] ?? '')),
            'referrer'     => esc_url_raw(wp_unslash($_POST['referrer'] ?? '')),
            'utm_source'   => sanitize_text_field(wp_unslash($_POST['utm_source'] ?? '')),
            'utm_medium'   => sanitize_text_field(wp_unslash($_POST['utm_medium'] ?? '')),
            'utm_campaign' => sanitize_text_field(wp_unslash($_POST['utm_campaign'] ?? '')),
            'traffic_src'  => sanitize_key($_POST['traffic_src'] ?? 'direct'),
            'gclid'        => sanitize_text_field(wp_unslash($_POST['gclid'] ?? '')),
            'is_suspicious'=> $sus,
            'created_at'   => current_time('mysql'),
        ]);
        if ($cnt + 1 >= (int) RKS_Helper::opt('auto_blacklist', 5)) {
            RKS_DB::insert('blacklist', [
                'ip'          => $ip,
                'reason'      => 'auto',
                'click_count' => $cnt + 1,
                'blocked_by'  => 'system',
                'created_at'  => current_time('mysql'),
            ]);
        }
        $this->maybe_telegram($phone, $type);
        wp_send_json_success(['suspicious' => $sus]);
    }

    public function block(): void {
        RKS_Helper::require_admin();
        $ip = filter_var(sanitize_text_field(wp_unslash($_POST['ip'] ?? '')), FILTER_VALIDATE_IP);
        if (!$ip) {
            wp_send_json_error(['msg' => 'IP غير صالح']);
        }
        RKS_DB::insert('blacklist', [
            'ip' => $ip, 'reason' => 'manual', 'blocked_by' => 'admin', 'created_at' => current_time('mysql'),
        ]);
        wp_send_json_success(['msg' => 'تم الحظر']);
    }

    public function unblock(): void {
        RKS_Helper::require_admin();
        global $wpdb;
        $ip = filter_var(sanitize_text_field(wp_unslash($_POST['ip'] ?? '')), FILTER_VALIDATE_IP);
        $wpdb->delete(RKS_DB::t('blacklist'), ['ip' => $ip]);
        wp_send_json_success(['msg' => 'رُفع الحظر']);
    }

    private function maybe_telegram(string $phone, string $type): void {
        if (!RKS_Helper::opt('notify_telegram')) {
            return;
        }
        $token = (string) RKS_Helper::opt('telegram_token');
        $chat  = (string) RKS_Helper::opt('telegram_chat');
        if ($token === '' || $chat === '') {
            return;
        }
        $msg = "تحويل جديد\n" . RKS_Helper::format_phone($phone) . "\n" . ($type === 'call' ? 'اتصال' : 'واتساب');
        wp_remote_post('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage', [
            'timeout'  => 5,
            'blocking' => false,
            'body'     => ['chat_id' => $chat, 'text' => $msg],
        ]);
    }
}
