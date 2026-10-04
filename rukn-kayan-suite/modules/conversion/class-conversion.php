<?php
defined('ABSPATH') || exit;

class RKS_Conversion {

    public function __construct() {
        add_action('wp_footer', [$this, 'widgets'], 80);
        add_action('wp_ajax_rks_save_offer', [$this, 'save']);
        add_action('wp_ajax_rks_list_offers', [$this, 'list']);
        add_action('wp_ajax_rks_delete_offer', [$this, 'delete']);
    }

    public function widgets(): void {
        if (is_admin()) {
            return;
        }
        $phone = (string) RKS_Helper::opt('phone');
        $wa    = (string) RKS_Helper::opt('whatsapp');
        if (!$phone && !$wa) {
            return;
        }
        if (RKS_Helper::opt('sticky_bar', true)) {
            echo '<div id="rks-sticky" style="position:fixed;bottom:0;inset-inline:0;z-index:9990;background:#0f172a;color:#fff;display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 14px;transform:translateY(110%);transition:.3s">';
            echo '<span>احجز خدمتك الآن — ' . esc_html(RKS_Helper::company()) . '</span><span style="display:flex;gap:8px">';
            if ($wa) {
                echo '<a href="' . esc_url('https://wa.me/' . RKS_Helper::clean_phone($wa)) . '" style="background:#25d366;color:#fff;padding:8px 14px;border-radius:999px;text-decoration:none;font-weight:700">واتساب</a>';
            }
            if ($phone) {
                echo '<a href="tel:' . esc_attr($phone) . '" style="background:#1a6bff;color:#fff;padding:8px 14px;border-radius:999px;text-decoration:none;font-weight:700">اتصال</a>';
            }
            echo '</span></div><script>setTimeout(function(){var e=document.getElementById("rks-sticky");if(e)e.style.transform="translateY(0)";},2500);</script>';
        }
        if (RKS_Helper::opt('smart_popup', true) && is_singular()) {
            $offer = RKS_DB::get_row('SELECT * FROM ' . RKS_DB::t('offers') . ' WHERE active=1 ORDER BY id DESC LIMIT 1');
            $msg = $offer ? $offer->body : 'طلب معاينة مجانية خلال دقائق.';
            echo '<div id="rks-pop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9998;align-items:center;justify-content:center"><div style="background:#fff;border-radius:16px;max-width:380px;padding:22px;width:92%"><strong style="font-size:18px">' . esc_html(RKS_Helper::company()) . '</strong><p>' . esc_html($msg) . '</p>';
            if ($wa) {
                echo '<a href="' . esc_url('https://wa.me/' . RKS_Helper::clean_phone($wa)) . '" style="display:block;text-align:center;background:#25d366;color:#fff;padding:10px;border-radius:10px;text-decoration:none;font-weight:700">تواصل واتساب</a>';
            }
            echo '<button type="button" onclick="document.getElementById(\'rks-pop\').style.display=\'none\'" style="margin-top:8px;width:100%;border:0;background:#eee;padding:8px;border-radius:8px">لاحقاً</button></div></div>';
            echo '<script>setTimeout(function(){var p=document.getElementById("rks-pop");if(p&&!sessionStorage.getItem("rks_pop")){p.style.display="flex";sessionStorage.setItem("rks_pop","1");}},8000);</script>';
        }
    }

    public function save(): void {
        RKS_Helper::require_admin();
        RKS_DB::insert('offers', [
            'title'        => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'body'         => sanitize_textarea_field(wp_unslash($_POST['body'] ?? '')),
            'city'         => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
            'trigger_type' => 'time',
            'active'       => 1,
            'created_at'   => current_time('mysql'),
        ]);
        wp_send_json_success(['msg' => 'تم حفظ العرض']);
    }

    public function list(): void {
        RKS_Helper::require_admin();
        wp_send_json_success(RKS_DB::get_results('SELECT * FROM ' . RKS_DB::t('offers') . ' ORDER BY id DESC LIMIT 40'));
    }

    public function delete(): void {
        RKS_Helper::require_admin();
        global $wpdb;
        $wpdb->delete(RKS_DB::t('offers'), ['id' => (int) ($_POST['id'] ?? 0)]);
        wp_send_json_success();
    }
}
