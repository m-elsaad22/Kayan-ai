<?php
defined('ABSPATH') || exit;

class RKS_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_rks_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_rks_test_ai', [$this, 'test_ai']);
    }

    public function menu(): void {
        add_menu_page('ركن كيان', 'ركن كيان', 'manage_options', 'rks', [$this, 'page'], 'dashicons-superhero', 3);
        $pages = [
            'rks'            => 'الرئيسية',
            'rks-generator'  => 'مولّد حصري',
            'rks-rewrite'    => 'إعادة كتابة',
            'rks-csv'        => 'استيراد CSV',
            'rks-translate'  => 'ترجمة',
            'rks-tracking'   => 'التتبع',
            'rks-numbers'    => 'الأرقام و DNI',
            'rks-fraud'      => 'الاحتيال',
            'rks-heatmap'    => 'Heatmap',
            'rks-reports'    => 'التقارير',
            'rks-seo'        => 'SEO و Schema',
            'rks-linking'    => 'ربط داخلي',
            'rks-competitor' => 'منافسون',
            'rks-offers'     => 'عروض التحويل',
            'rks-analyzer'   => 'محلل الموقع',
            'rks-settings'   => 'الإعدادات',
        ];
        foreach ($pages as $slug => $title) {
            add_submenu_page('rks', $title, $title, 'manage_options', $slug, [$this, 'page']);
        }
    }

    public function assets(string $hook): void {
        if (!str_contains($hook, 'rks')) {
            return;
        }
        wp_enqueue_style('rks-admin', RKS_URL . 'admin/assets/admin.css', [], RKS_VERSION);
        wp_enqueue_script('rks-admin', RKS_URL . 'admin/assets/admin.js', ['jquery'], RKS_VERSION, true);
        wp_localize_script('rks-admin', 'RKS', [
            'ajax'    => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('rks_admin'),
            'exp'     => wp_create_nonce('rks_export'),
            'ready'   => RKS_AI::instance()->is_ready() ? 1 : 0,
            'ai'      => RKS_AI::instance()->name(),
            'page'    => sanitize_key($_GET['page'] ?? 'rks'),
        ]);
    }

    public function page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('forbidden');
        }
        $page = sanitize_key($_GET['page'] ?? 'rks');
        echo '<div class="rks-app" dir="rtl">';
        $this->nav($page);
        echo '<main class="rks-main">';
        switch ($page) {
            case 'rks-generator': $this->page_generator(); break;
            case 'rks-rewrite': $this->page_rewrite(); break;
            case 'rks-csv': $this->page_csv(); break;
            case 'rks-translate': $this->page_translate(); break;
            case 'rks-tracking': $this->page_tracking(); break;
            case 'rks-numbers': $this->page_numbers(); break;
            case 'rks-fraud': $this->page_fraud(); break;
            case 'rks-heatmap': $this->page_heatmap(); break;
            case 'rks-reports': $this->page_reports(); break;
            case 'rks-seo': $this->page_seo(); break;
            case 'rks-linking': $this->page_linking(); break;
            case 'rks-competitor': $this->page_competitor(); break;
            case 'rks-offers': $this->page_offers(); break;
            case 'rks-analyzer': $this->page_analyzer(); break;
            case 'rks-settings': $this->page_settings(); break;
            default: $this->page_dash();
        }
        echo '</main></div>';
    }

    private function nav(string $page): void {
        $ai = RKS_AI::instance();
        $items = [
            'rks' => ['الرئيسية', '🏠'],
            'rks-generator' => ['مولّد حصري', '✍️'],
            'rks-rewrite' => ['إعادة كتابة', '🔁'],
            'rks-csv' => ['CSV', '📥'],
            'rks-translate' => ['ترجمة', '🌐'],
            'rks-tracking' => ['التتبع', '📊'],
            'rks-numbers' => ['الأرقام', '📞'],
            'rks-fraud' => ['احتيال', '🛡️'],
            'rks-heatmap' => ['حرارة', '🔥'],
            'rks-reports' => ['تقارير', '📤'],
            'rks-seo' => ['SEO', '🎯'],
            'rks-linking' => ['ربط', '🔗'],
            'rks-competitor' => ['منافس', '🕵️'],
            'rks-offers' => ['عروض', '🎁'],
            'rks-analyzer' => ['محلل', '🔍'],
            'rks-settings' => ['إعدادات', '⚙️'],
        ];
        echo '<aside class="rks-nav"><div class="rks-brand"><strong>ركن كيان</strong><small>v' . esc_html(RKS_VERSION) . '</small></div>';
        echo '<div class="rks-ai ' . ($ai->is_ready() ? 'on' : 'off') . '">' . esc_html($ai->name()) . '</div><nav>';
        foreach ($items as $slug => [$label, $ico]) {
            $cls = $page === $slug ? 'active' : '';
            echo '<a class="' . $cls . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . $ico . ' ' . esc_html($label) . '</a>';
        }
        echo '</nav></aside>';
    }

    private function page_dash(): void {
        echo '<header class="rks-head"><h1>لوحة ركن كيان الشاملة</h1><p>كل الأدوات في إضافة واحدة. المقالات تُكتب بالذكاء الاصطناعي لتكون حصرية.</p></header>';
        echo '<div id="rks-dash-cards" class="rks-cards">جاري التحميل…</div>';
        echo '<div class="rks-grid"><section class="rks-card"><h2>إجراء سريع</h2>';
        echo '<a class="rks-btn" href="' . esc_url(admin_url('admin.php?page=rks-generator')) . '">توليد مقال حصري</a> ';
        echo '<button class="rks-btn ghost" data-act="analyze">تحليل الموقع</button></section>';
        echo '<section class="rks-card"><h2>آخر تحليل</h2><div id="rks-last-an">';
        $an = json_decode((string) get_option('rks_last_analysis', '{}'), true);
        echo isset($an['score']) ? 'النقاط: <b>' . (int) $an['score'] . '</b>' : 'لم يُشغَّل بعد';
        echo '</div></section></div>';
    }

    private function page_generator(): void {
        echo '<header class="rks-head"><h1>مولّد المقالات الحصري</h1><p>كل مقال يأخذ زاوية وشخصية وهيكل مختلف، ثم يُقاس تشابهه مع مقالاتك السابقة ويُعاد توليده إن لزم.</p></header>';
        if (!RKS_AI::instance()->is_ready()) {
            echo '<div class="rks-warn">أضف مفتاح Gemini أو Claude من الإعدادات أولاً.</div>';
        }
        echo '<section class="rks-card"><div class="rks-2">';
        echo '<div><label>الخدمات (سطر لكل خدمة)</label><textarea id="rks-svcs" rows="10" placeholder="كشف تسربات المياه&#10;تنظيف منازل"></textarea></div>';
        echo '<div><label>المدن كما تريدها في العنوان</label><textarea id="rks-cities" rows="10" placeholder="في دبي&#10;في الرياض"></textarea></div>';
        echo '</div><div class="rks-row">';
        echo '<label>كلمات <select id="rks-wc"><option value="1600">1600</option><option value="2200" selected>2200</option><option value="3000">3000</option></select></label>';
        echo '<label>نبرة <select id="rks-tone"><option>احترافي ومقنع</option><option>ودي وبسيط</option><option>تسويقي وجذاب</option></select></label>';
        echo '<label>تصنيف <input id="rks-cat" placeholder="خدمات"></label>';
        echo '<label>تأخير<select id="rks-delay"><option value="2500">2.5ث</option><option value="4000" selected>4ث</option></select></label>';
        echo '</div><p id="rks-matrix-count"></p>';
        echo '<button class="rks-btn" id="rks-check">فحص الموجود</button> ';
        echo '<button class="rks-btn primary" id="rks-start"' . (RKS_AI::instance()->is_ready() ? '' : ' disabled') . '>ابدأ التوليد الحصري</button> ';
        echo '<button class="rks-btn danger" id="rks-stop" hidden>إيقاف</button>';
        echo '<div id="rks-prog" hidden><div class="rks-bar"><i id="rks-bar"></i></div><p id="rks-prog-txt"></p></div>';
        echo '</section><div id="rks-results"></div>';
    }

    private function page_rewrite(): void {
        echo '<header class="rks-head"><h1>إعادة كتابة حصرية</h1></header><section class="rks-card">';
        echo '<button class="rks-btn" id="rks-load-posts">تحميل المقالات</button><div id="rks-posts"></div></section>';
    }

    private function page_csv(): void {
        echo '<header class="rks-head"><h1>استيراد CSV</h1><p>يدعم أعمدة YourColor و Yoast و Rank Math. المحتوى يُنقّى قبل الحفظ.</p></header>';
        echo '<section class="rks-card" id="rks-drop"><p>اسحب ملف CSV أو <label class="rks-btn">اختر<input type="file" id="rks-csv-file" accept=".csv" hidden></label></p></section>';
        echo '<label><input type="checkbox" id="rks-skip" checked> تخطي الموجود</label> ';
        echo '<label><input type="checkbox" id="rks-upd"> تحديث الموجود</label> ';
        echo '<label><input type="checkbox" id="rks-dry"> تجربة فقط</label>';
        echo '<div id="rks-csv-info"></div><button class="rks-btn primary" id="rks-csv-go" disabled>ابدأ</button>';
        echo '<div id="rks-csv-log"></div>';
    }

    private function page_translate(): void {
        echo '<header class="rks-head"><h1>ترجمة بالذكاء الاصطناعي</h1></header><section class="rks-card">';
        echo '<textarea id="rks-tr-in" rows="8" placeholder="النص…"></textarea>';
        echo '<div class="rks-row"><select id="rks-tr-from"><option value="ar">عربي</option><option value="en">English</option></select>';
        echo '<select id="rks-tr-to"><option value="en">English</option><option value="ur">اردو</option><option value="hi">हिन्दी</option><option value="fr">Français</option></select>';
        echo '<button class="rks-btn" id="rks-tr-go">ترجم</button></div><div id="rks-tr-out" class="rks-out"></div></section>';
    }

    private function page_tracking(): void {
        echo '<header class="rks-head"><h1>تتبع الزيارات والتحويلات</h1></header>';
        echo '<div class="rks-row"><select id="rks-days"><option>7</option><option selected>30</option><option>90</option></select>';
        echo '<input type="search" id="rks-q" placeholder="بحث"><select id="rks-type"><option value="">الكل</option><option value="whatsapp">واتساب</option><option value="call">اتصال</option></select>';
        echo '<button class="rks-btn" id="rks-load-conv">عرض</button>';
        echo '<a class="rks-btn ghost" id="rks-exp" target="_blank">تصدير CSV</a></div>';
        echo '<div id="rks-table"></div>';
    }

    private function page_numbers(): void {
        echo '<header class="rks-head"><h1>الأرقام و DNI</h1></header><section class="rks-card">';
        echo '<div class="rks-row"><input id="nm-label" placeholder="التصنيف"><input id="nm-phone" placeholder="9715…"><input id="nm-wa" placeholder="واتساب">';
        echo '<select id="nm-type"><option value="both">الاثنان</option><option value="call">اتصال</option><option value="whatsapp">واتساب</option></select>';
        echo '<button class="rks-btn" id="nm-save">حفظ رقم</button></div><div id="nm-list"></div></section>';
        echo '<section class="rks-card"><h2>قواعد DNI</h2><div class="rks-row">';
        echo '<select id="dm-num"></select><select id="dm-src"><option value="google_ads">Google Ads</option><option value="paid">مدفوع</option><option value="social">سوشيال</option><option value="organic">بحث</option><option value="direct">مباشر</option></select>';
        echo '<input id="dm-us" placeholder="utm_source"><button class="rks-btn" id="dm-save">حفظ قاعدة</button></div><div id="dm-list"></div></section>';
    }

    private function page_fraud(): void {
        echo '<header class="rks-head"><h1>كشف الاحتيال</h1></header><div class="rks-row"><input id="bl-ip" placeholder="IP"><button class="rks-btn danger" id="bl-go">حظر</button></div><div id="fr-box"></div>';
    }

    private function page_heatmap(): void {
        echo '<header class="rks-head"><h1>Heatmap</h1></header><div class="rks-row"><input id="hm-url" placeholder="URL اختياري"><button class="rks-btn" id="hm-go">عرض</button></div>';
        echo '<div class="rks-heat"><canvas id="hm-c" width="900" height="480"></canvas></div>';
    }

    private function page_reports(): void {
        echo '<header class="rks-head"><h1>تقارير عامة للعملاء</h1></header>';
        echo '<div class="rks-row"><input id="rp-t" placeholder="عنوان التقرير"><select id="rp-d"><option value="7">7 أيام</option><option value="30" selected>30</option></select>';
        echo '<button class="rks-btn" id="rp-go">إنشاء رابط</button></div><div id="rp-box"></div>';
    }

    private function page_seo(): void {
        echo '<header class="rks-head"><h1>SEO و Schema</h1></header><section class="rks-card">';
        echo '<input id="seo-kw" class="wide" placeholder="كلمة مفتاحية"><button class="rks-btn" id="seo-go">توليد حزمة SEO</button><div id="seo-out" class="rks-out"></div></section>';
    }

    private function page_linking(): void {
        echo '<header class="rks-head"><h1>الربط الداخلي</h1></header><button class="rks-btn" id="lk-scan">فحص الروابط المقترحة</button><div id="lk-box"></div>';
    }

    private function page_competitor(): void {
        echo '<header class="rks-head"><h1>تحليل منافس</h1></header><div class="rks-row"><input id="cp-url" placeholder="https://"><input id="cp-kw" placeholder="الكلمة"><button class="rks-btn" id="cp-go">تحليل</button></div><div id="cp-out" class="rks-out"></div>';
    }

    private function page_offers(): void {
        echo '<header class="rks-head"><h1>عروض التحويل</h1></header><div class="rks-row"><input id="of-t" placeholder="عنوان"><input id="of-b" placeholder="نص العرض"><button class="rks-btn" id="of-save">حفظ</button></div><div id="of-list"></div>';
    }

    private function page_analyzer(): void {
        echo '<header class="rks-head"><h1>محلل الموقع</h1></header><button class="rks-btn" id="an-go">تشغيل التحليل</button><div id="an-out"></div>';
    }

    private function page_settings(): void {
        $s = RKS_Helper::settings();
        echo '<header class="rks-head"><h1>الإعدادات</h1></header><form id="rks-set" class="rks-card">';
        $this->field('company_name', 'اسم الشركة', $s['company_name']);
        $this->field('phone', 'الهاتف', $s['phone']);
        $this->field('whatsapp', 'واتساب', $s['whatsapp']);
        echo '<label>الدولة <select name="country">';
        foreach (['ae' => 'الإمارات', 'sa' => 'السعودية', 'qa' => 'قطر', 'kw' => 'الكويت', 'eg' => 'مصر'] as $k => $v) {
            echo '<option value="' . $k . '" ' . selected($s['country'], $k, false) . '>' . $v . '</option>';
        }
        echo '</select></label>';
        echo '<label>مزود AI <select name="ai_provider"><option value="auto"' . selected($s['ai_provider'], 'auto', false) . '>تلقائي</option><option value="gemini"' . selected($s['ai_provider'], 'gemini', false) . '>Gemini</option><option value="claude"' . selected($s['ai_provider'], 'claude', false) . '>Claude</option></select></label>';
        $this->field('gemini_key', 'مفتاح Gemini', $s['gemini_key'], 'password');
        $this->field('claude_key', 'مفتاح Claude', $s['claude_key'], 'password');
        $this->field('uniqueness_min', 'حد الحصرية الأدنى %', $s['uniqueness_min']);
        echo '<label><input type="checkbox" name="tracking_enabled" ' . checked(!empty($s['tracking_enabled']), true, false) . '> تفعيل التتبع</label>';
        echo '<label><input type="checkbox" name="cookie_consent" ' . checked(!empty($s['cookie_consent']), true, false) . '> شريط موافقة الكوكيز</label>';
        echo '<label><input type="checkbox" name="heatmap_enabled" ' . checked(!empty($s['heatmap_enabled']), true, false) . '> Heatmap</label>';
        echo '<label><input type="checkbox" name="dni_enabled" ' . checked(!empty($s['dni_enabled']), true, false) . '> DNI</label>';
        echo '<label><input type="checkbox" name="sticky_bar" ' . checked(!empty($s['sticky_bar']), true, false) . '> شريط لاصق</label>';
        echo '<label><input type="checkbox" name="smart_popup" ' . checked(!empty($s['smart_popup']), true, false) . '> نافذة تحويل</label>';
        echo '<label><input type="checkbox" name="notify_telegram" ' . checked(!empty($s['notify_telegram']), true, false) . '> تنبيه تيليجرام</label>';
        $this->field('telegram_token', 'توكن تيليجرام', $s['telegram_token'], 'password');
        $this->field('telegram_chat', 'Chat ID', $s['telegram_chat']);
        echo '<p><button class="rks-btn primary">حفظ</button> <button type="button" class="rks-btn" id="rks-test-g">اختبار Gemini</button> <button type="button" class="rks-btn" id="rks-test-c">اختبار Claude</button></p>';
        echo '<div id="rks-set-msg"></div></form>';
    }

    private function field(string $name, string $label, $val, string $type = 'text'): void {
        echo '<label>' . esc_html($label) . ' <input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '"></label>';
    }

    public function save_settings(): void {
        RKS_Helper::require_admin();
        $keys = array_keys(RKS_Helper::defaults());
        $patch = [];
        foreach ($keys as $k) {
            if (in_array($k, ['enabled', 'cookie_consent', 'heatmap_enabled', 'dni_enabled', 'tracking_enabled', 'sticky_bar', 'smart_popup', 'auto_schema', 'notify_telegram'], true)) {
                $patch[$k] = !empty($_POST[$k]) && $_POST[$k] !== 'false';
                continue;
            }
            if (isset($_POST[$k])) {
                $patch[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
            }
        }
        RKS_Helper::update_settings($patch);
        wp_send_json_success(['msg' => 'تم الحفظ']);
    }

    public function test_ai(): void {
        RKS_Helper::require_admin();
        $which = sanitize_key($_POST['which'] ?? 'gemini');
        $key   = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
        if ($which === 'claude') {
            $api = new RKS_Claude();
            if ($key) {
                $api->set_key($key);
            }
            wp_send_json_success($api->test());
        }
        $api = new RKS_Gemini();
        if ($key) {
            $api->set_key($key);
        }
        wp_send_json_success($api->test());
    }
}
