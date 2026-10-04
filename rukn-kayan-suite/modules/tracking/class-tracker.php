<?php
defined('ABSPATH') || exit;

class RKS_Tracker {

    public function __construct() {
        add_action('wp_footer', [$this, 'inject'], 99);
        add_action('wp_head', [$this, 'consent_banner'], 1);
        add_action('wp_ajax_rks_register_visit', [$this, 'register_visit']);
        add_action('wp_ajax_nopriv_rks_register_visit', [$this, 'register_visit']);
        add_action('wp_ajax_rks_session_end', [$this, 'session_end']);
        add_action('wp_ajax_nopriv_rks_session_end', [$this, 'session_end']);
        add_action('wp_ajax_rks_admin_data', [$this, 'admin_data']);
    }

    public function inject(): void {
        if (is_admin() || RKS_Helper::is_bot() || !RKS_Helper::opt('tracking_enabled', true)) {
            return;
        }
        if (RKS_Helper::opt('cookie_consent') && (($_COOKIE['rks_consent'] ?? '') === '0')) {
            return;
        }
        wp_enqueue_script('rks-tracker', RKS_URL . 'admin/assets/tracker.js', [], RKS_VERSION, true);
        wp_localize_script('rks-tracker', 'RKS_Track', [
            'ajax'     => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rks_track'),
            'hm'       => (bool) RKS_Helper::opt('heatmap_enabled', true),
            'dni'      => (bool) RKS_Helper::opt('dni_enabled'),
            'cooldown' => (int) RKS_Helper::opt('cooldown_mins', 30),
            'blocked'  => RKS_DB::is_blacklisted(RKS_Helper::client_ip()),
        ]);
    }

    public function consent_banner(): void {
        if (is_admin() || !RKS_Helper::opt('cookie_consent') || isset($_COOKIE['rks_consent'])) {
            return;
        }
        echo '<style>#rks-cb{position:fixed;bottom:1rem;inset-inline:1rem;max-width:460px;margin:auto;background:#111827;color:#e5e7eb;border-radius:14px;padding:1rem;z-index:99999;display:flex;gap:.7rem;align-items:center;font-size:13px}#rks-cb button{border:0;border-radius:8px;padding:6px 12px;font-weight:700;cursor:pointer}#rks-ok{background:#1a6bff;color:#fff}#rks-no{background:#374151;color:#d1d5db}</style>';
        echo '<div id="rks-cb"><p style="margin:0;flex:1">نستخدم التتبع لقياس المكالمات والحملات فقط.</p><button id="rks-no" type="button">رفض</button><button id="rks-ok" type="button">موافق</button></div>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("rks-cb");if(!b)return;function s(v){document.cookie="rks_consent="+v+";path=/;max-age=31536000";b.remove();}document.getElementById("rks-ok").onclick=function(){s(1)};document.getElementById("rks-no").onclick=function(){s(0)};});</script>';
    }

    public function register_visit(): void {
        if (!check_ajax_referer('rks_track', 'nonce', false)) {
            wp_send_json_error(['msg' => 'nonce'], 403);
        }
        if (!RKS_Helper::rate_limit('visit', 30, 60)) {
            wp_send_json_success(['limited' => true]);
        }
        $ip = RKS_Helper::client_ip();
        $fp = sanitize_text_field(wp_unslash($_POST['fp'] ?? ''));
        $sid = sanitize_text_field(wp_unslash($_POST['sid'] ?? ''));
        if ($fp === '' || $sid === '') {
            wp_send_json_success();
        }
        $geo = RKS_Helper::geo($ip);
        $ex  = RKS_DB::get_row('SELECT id, visit_count FROM ' . RKS_DB::t('visitors') . ' WHERE fingerprint=%s LIMIT 1', [$fp]);
        if ($ex) {
            RKS_DB::update('visitors', [
                'session_id' => $sid,
                'last_visit' => current_time('mysql'),
                'visit_count'=> (int) $ex->visit_count + 1,
                'is_new'     => 0,
            ], ['id' => $ex->id]);
        } else {
            RKS_DB::insert('visitors', [
                'fingerprint'  => $fp,
                'session_id'   => $sid,
                'ip'           => $ip,
                'country'      => $geo['country'],
                'city'         => $geo['city'],
                'language'     => sanitize_text_field(wp_unslash($_POST['lang'] ?? '')),
                'device_type'  => sanitize_text_field(wp_unslash($_POST['device_type'] ?? '')),
                'os'           => sanitize_text_field(wp_unslash($_POST['os'] ?? '')),
                'browser'      => sanitize_text_field(wp_unslash($_POST['browser'] ?? '')),
                'screen_res'   => sanitize_text_field(wp_unslash($_POST['screen'] ?? '')),
                'referrer'     => esc_url_raw(wp_unslash($_POST['referrer'] ?? '')),
                'utm_source'   => sanitize_text_field(wp_unslash($_POST['utm_source'] ?? '')),
                'utm_medium'   => sanitize_text_field(wp_unslash($_POST['utm_medium'] ?? '')),
                'utm_campaign' => sanitize_text_field(wp_unslash($_POST['utm_campaign'] ?? '')),
                'traffic_src'  => sanitize_key($_POST['traffic_src'] ?? 'direct'),
                'first_visit'  => current_time('mysql'),
                'last_visit'   => current_time('mysql'),
            ]);
        }
        RKS_DB::insert('sessions', [
            'session_id'  => $sid,
            'fingerprint' => $fp,
            'ip'          => $ip,
            'start_time'  => current_time('mysql'),
            'is_new'      => $ex ? 0 : 1,
        ]);
        wp_send_json_success();
    }

    public function session_end(): void {
        if (!check_ajax_referer('rks_track', 'nonce', false)) {
            wp_die('', 403);
        }
        $sid = sanitize_text_field(wp_unslash($_POST['sid'] ?? ''));
        if ($sid) {
            RKS_DB::update('sessions', [
                'end_time'   => current_time('mysql'),
                'duration'   => (int) ($_POST['duration'] ?? 0),
                'scroll_pct' => min(100, (int) ($_POST['scroll'] ?? 0)),
            ], ['session_id' => $sid]);
        }
        wp_die();
    }

    public function admin_data(): void {
        RKS_Helper::require_admin();
        $action = sanitize_key($_POST['rks_action'] ?? 'dashboard');
        [$since, $until] = RKS_Helper::datetime_range(
            (int) ($_POST['days'] ?? 30),
            sanitize_text_field(wp_unslash($_POST['date_from'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['date_to'] ?? ''))
        );
        $ct = RKS_DB::t('conversions');
        $vt = RKS_DB::t('visitors');
        $st = RKS_DB::t('sessions');
        $nt = RKS_DB::t('numbers');
        $bl = RKS_DB::t('blacklist');

        switch ($action) {
            case 'dashboard':
                wp_send_json_success([
                    'total'      => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE created_at>=%s AND created_at<=%s", [$since, $until]),
                    'calls'      => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE click_type='call' AND created_at>=%s AND created_at<=%s", [$since, $until]),
                    'whatsapp'   => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE click_type='whatsapp' AND created_at>=%s AND created_at<=%s", [$since, $until]),
                    'unique'     => (int) RKS_DB::get_var("SELECT COUNT(DISTINCT fingerprint) FROM $ct WHERE created_at>=%s AND created_at<=%s", [$since, $until]),
                    'suspicious' => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $ct WHERE is_suspicious=1 AND created_at>=%s AND created_at<=%s", [$since, $until]),
                    'visitors'   => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $vt WHERE last_visit>=%s AND last_visit<=%s", [$since, $until]),
                    'sessions'   => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $st WHERE start_time>=%s AND start_time<=%s", [$since, $until]),
                    'nums_count' => (int) RKS_DB::get_var("SELECT COUNT(*) FROM $nt WHERE active=1"),
                    'articles'   => (int) RKS_DB::get_var('SELECT COUNT(*) FROM ' . RKS_DB::t('articles')),
                    'daily'      => RKS_DB::get_results("SELECT DATE(created_at) d, COUNT(*) tot, SUM(click_type='whatsapp') wa, SUM(click_type='call') `call` FROM $ct WHERE created_at>=%s AND created_at<=%s GROUP BY DATE(created_at) ORDER BY d ASC", [$since, $until]),
                    'by_device'  => RKS_DB::get_results("SELECT device_type, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY device_type", [$since]),
                    'by_source'  => RKS_DB::get_results("SELECT traffic_src, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY traffic_src", [$since]),
                    'top_pages'  => RKS_DB::get_results("SELECT page_title, page_url, COUNT(*) cnt FROM $ct WHERE created_at>=%s GROUP BY page_url ORDER BY cnt DESC LIMIT 8", [$since]),
                    'sus_ips'    => RKS_DB::get_results("SELECT ip, COUNT(*) cnt, MAX(city) city FROM $ct WHERE created_at>=%s AND is_suspicious=1 GROUP BY ip HAVING cnt>=2 ORDER BY cnt DESC LIMIT 8", [$since]),
                ]);
                break;
            case 'conversions':
                $this->paged($ct, $since, $until, ['click_type', 'traffic_src', 'ip', 'phone_raw', 'city', 'page_title']);
                break;
            case 'visitors':
                $this->paged($vt, $since, $until, ['device_type', 'ip', 'city'], 'last_visit');
                break;
            case 'fraud':
                wp_send_json_success([
                    'suspicious' => RKS_DB::get_results("SELECT ip, COUNT(*) cnt, MAX(city) city FROM $ct WHERE created_at>=%s AND is_suspicious=1 GROUP BY ip ORDER BY cnt DESC LIMIT 40", [$since]),
                    'blacklist'  => RKS_DB::get_results("SELECT * FROM $bl ORDER BY created_at DESC LIMIT 100"),
                ]);
                break;
            case 'numbers_summary':
                wp_send_json_success([
                    'numbers' => RKS_DB::all_numbers(true, $since),
                    'unknown' => RKS_DB::get_results("SELECT phone_raw, COUNT(*) total, SUM(click_type='call') calls, SUM(click_type='whatsapp') wa FROM $ct WHERE number_id IS NULL AND phone_raw!='' AND created_at>=%s GROUP BY phone_raw ORDER BY total DESC LIMIT 20", [$since]),
                ]);
                break;
            default:
                wp_send_json_error(['msg' => 'unknown']);
        }
    }

    private function paged(string $table, string $since, string $until, array $search_cols, string $date_col = 'created_at'): void {
        global $wpdb;
        $page = max(1, (int) ($_POST['page'] ?? 1));
        $per  = 50;
        $off  = ($page - 1) * $per;
        $sr   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $tf   = sanitize_key($_POST['click_type'] ?? '');
        $where = "WHERE $date_col >= %s AND $date_col <= %s";
        $args  = [$since, $until];
        if ($tf && in_array($tf, ['call', 'whatsapp', 'form', 'cta'], true)) {
            $where .= ' AND click_type = %s';
            $args[] = $tf;
        }
        if ($sr !== '') {
            $likes = [];
            foreach ($search_cols as $col) {
                $likes[] = "$col LIKE %s";
                $args[]  = '%' . $wpdb->esc_like($sr) . '%';
            }
            $where .= ' AND (' . implode(' OR ', $likes) . ')';
        }
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table $where", $args));
        $rows  = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table $where ORDER BY $date_col DESC LIMIT %d OFFSET %d", array_merge($args, [$per, $off])));
        wp_send_json_success(['rows' => $rows, 'total' => $total]);
    }
}
