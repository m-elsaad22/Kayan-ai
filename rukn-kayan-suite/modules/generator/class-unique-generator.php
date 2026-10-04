<?php
defined('ABSPATH') || exit;

/**
 * مولّد مقالات حصرية بالذكاء الاصطناعي.
 * لا يستخدم قوالب ثابتة. كل مقال يأخذ زاوية وشخصية وهيكل مختلف،
 * ثم يُقارن مع المقالات السابقة ويُعاد توليده إن تشابه.
 */
class RKS_Unique_Generator {

    public const ANGLES = [
        'problem_first'  => 'ابدأ بمشكلة حقيقية يعيشها العميل في هذه المدينة ثم قدّم الحل',
        'neighborhoods'  => 'ركّز على أحياء وشوارع ومعالم محلية حقيقية في المدينة',
        'pricing'        => 'ادخل من زاوية الأسعار والعوامل التي تغيّر التكلفة بشفافية',
        'emergency'      => 'زاوية الطوارئ والاستجابة السريعة على مدار الساعة',
        'comparison'     => 'قارن بين الطرق الرديئة والاحترافية دون تسمية منافسين',
        'case_study'     => 'ابنِ المقال حول قصة حالة واقعية (بدون أسماء حقيقية)',
        'seasonal'       => 'اربط الخدمة بمناخ الموسم الحالي في الدولة',
        'quality'        => 'زاوية الضمان والجودة والمعايير المهنية',
        'howto'          => 'دليل عملي خطوة بخطوة يعلّم القارئ ماذا يتوقع',
        'mistakes'       => 'أكثر الأخطاء الشائعة التي يرتكبها العملاء أو الشركات الرديئة',
        'buyer_guide'    => 'دليل اختيار الشركة المناسبة بأسئلة يجب أن يسألها العميل',
        'aftercare'      => 'زاوية ما بعد الخدمة والصيانة الدورية والعقود',
    ];

    public const PERSONAS = [
        'field_engineer' => 'مهندس ميداني بخبرة 15 سنة، لغة عملية دقيقة',
        'ops_manager'    => 'مدير عمليات يهتم بالزمن والجودة والجدولة',
        'local_advisor'  => 'مستشار محلي يعرف عادات أهل المدينة',
        'cost_analyst'   => 'محلل تكلفة يشرح الأرقام بوضوح',
        'safety_officer' => 'مسؤول سلامة يركّز على المخاطر والمعايير',
        'customer_lead'  => 'قائد خدمة عملاء ودود ومباشر',
        'trainer'        => 'مدرب فنيين يشرح الإجراءات كأنه يعلّم فريقاً جديداً',
        'editor'         => 'محرر مجلة منزلية أسلوبه سلس وجذاب',
    ];

    public const STRUCTURES = [
        'story_arc'   => 'افتتاح قصصي → تشخيص → حل → دليل أحياء → أسئلة → خاتمة',
        'qa_heavy'    => 'مقدمة قصيرة → 8 أسئلة شائعة معمّقة كأبواب H2 → خاتمة',
        'local_map'   => 'مقدمة محلية → خريطة أحياء → تسعير → خطوات → شهادات → خاتمة',
        'process'     => 'مشكلة → 6 خطوات تنفيذ مفصلة → ضمان → تواصل',
        'contrast'    => 'خطأ شائع → الطريقة الصحيحة → مقارنة جدول → دعوة',
        'briefing'    => 'ملخص تنفيذي → تفاصيل تقنية → حالات → FAQ → CTA',
        'timeline'    => 'ماذا يحدث في أول ساعة / أول يوم / بعد أسبوع',
        'checklist'   => 'قوائم تحقق للعميل قبل وأثناء وبعد الخدمة',
    ];

    public function __construct() {
        add_action('wp_ajax_rks_generate_article', [$this, 'ajax_generate']);
        add_action('wp_ajax_rks_publish_article',  [$this, 'ajax_publish']);
        add_action('wp_ajax_rks_check_pairs',      [$this, 'ajax_check']);
        add_action('wp_ajax_rks_rewrite_unique',   [$this, 'ajax_rewrite']);
    }

    public function ajax_generate(): void {
        RKS_Helper::require_admin();
        if (!RKS_AI::instance()->is_ready()) {
            wp_send_json_error(['msg' => 'أضف مفتاح Gemini أو Claude أولاً']);
        }
        $service = sanitize_text_field(wp_unslash($_POST['service'] ?? ''));
        $city    = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
        if ($service === '' || $city === '') {
            wp_send_json_error(['msg' => 'الخدمة والمدينة مطلوبان']);
        }
        $opts = [
            'word_count' => max(800, min(4500, (int) ($_POST['word_count'] ?? RKS_Helper::opt('word_count', 2200)))),
            'tone'       => sanitize_text_field(wp_unslash($_POST['tone'] ?? RKS_Helper::opt('tone'))),
            'force'      => !empty($_POST['force']),
        ];
        $result = $this->generate($service, $city, $opts);
        if (empty($result['ok'])) {
            wp_send_json_error(['msg' => $result['error'] ?? 'فشل التوليد']);
        }
        wp_send_json_success($result);
    }

    public function ajax_publish(): void {
        RKS_Helper::require_admin();
        if (!current_user_can('publish_posts') && !current_user_can('manage_options')) {
            wp_send_json_error(['msg' => 'غير مصرح']);
        }
        $payload = [
            'title'      => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'html'       => wp_kses_post(wp_unslash($_POST['html'] ?? '')),
            'service'    => sanitize_text_field(wp_unslash($_POST['service'] ?? '')),
            'city'       => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
            'meta_title' => sanitize_text_field(wp_unslash($_POST['meta_title'] ?? '')),
            'meta_desc'  => sanitize_text_field(wp_unslash($_POST['meta_desc'] ?? '')),
            'slug'       => sanitize_title(wp_unslash($_POST['slug'] ?? '')),
            'faq'        => json_decode(stripslashes((string) ($_POST['faq'] ?? '[]')), true) ?: [],
            'features'   => json_decode(stripslashes((string) ($_POST['features'] ?? '[]')), true) ?: [],
            'steps'      => json_decode(stripslashes((string) ($_POST['steps'] ?? '[]')), true) ?: [],
            'angle'      => sanitize_key($_POST['angle'] ?? ''),
            'persona'    => sanitize_key($_POST['persona'] ?? ''),
            'structure'  => sanitize_key($_POST['structure'] ?? ''),
            'fingerprint'=> sanitize_text_field(wp_unslash($_POST['fingerprint'] ?? '')),
            'uniqueness' => (int) ($_POST['uniqueness'] ?? 0),
            'word_count' => (int) ($_POST['word_count'] ?? 0),
            'category'   => sanitize_text_field(wp_unslash($_POST['category'] ?? '')),
            'status'     => sanitize_key($_POST['status'] ?? 'draft'),
        ];
        $post_id = $this->publish($payload);
        if (!$post_id) {
            wp_send_json_error(['msg' => 'فشل حفظ المقال']);
        }
        wp_send_json_success([
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
        ]);
    }

    public function ajax_check(): void {
        RKS_Helper::require_admin();
        $pairs = json_decode(stripslashes((string) ($_POST['pairs'] ?? '[]')), true);
        if (!is_array($pairs)) {
            wp_send_json_error(['msg' => 'بيانات غير صالحة']);
        }
        $out = [];
        foreach (array_slice($pairs, 0, 400) as $p) {
            $service = sanitize_text_field($p['service'] ?? $p['sv'] ?? '');
            $city    = sanitize_text_field($p['city'] ?? $p['c'] ?? '');
            if (!$service || !$city) {
                continue;
            }
            $existing = $this->existing_post($service, $city);
            $out[] = [
                'service'  => $service,
                'city'     => $city,
                'exists'   => (bool) $existing,
                'post_id'  => $existing,
                'edit_url' => $existing ? get_edit_post_link($existing, 'raw') : '',
                'count'    => $this->pair_count($service, $city),
            ];
        }
        wp_send_json_success($out);
    }

    public function ajax_rewrite(): void {
        RKS_Helper::require_admin();
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $post    = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['msg' => 'المقال غير موجود']);
        }
        $service = sanitize_text_field(wp_unslash($_POST['service'] ?? get_post_meta($post_id, '_rks_service', true)));
        $city    = sanitize_text_field(wp_unslash($_POST['city'] ?? get_post_meta($post_id, '_rks_city', true)));
        $result  = $this->generate($service ?: $post->post_title, $city ?: '', [
            'word_count' => max(1200, RKS_Helper::word_count($post->post_content)),
            'force'      => true,
            'rewrite_of' => $post_id,
        ]);
        if (empty($result['ok'])) {
            wp_send_json_error(['msg' => $result['error'] ?? 'فشل إعادة الكتابة']);
        }
        wp_send_json_success($result);
    }

    public function generate(string $service, string $city, array $opts = []): array {
        $ai = RKS_AI::instance();
        if (!$ai->is_ready()) {
            return ['ok' => false, 'error' => 'AI غير متصل'];
        }

        $recipe     = $this->recipe($service, $city, (int) ($opts['rewrite_of'] ?? 0));
        $corpus     = $this->previous_corpus($service, $city, (int) ($opts['rewrite_of'] ?? 0));
        $min_unique = (int) RKS_Helper::opt('uniqueness_min', 62);
        $attempt    = 0;
        $best       = null;

        while ($attempt < 2) {
            $attempt++;
            $data = $ai->ask_json($this->prompt($service, $city, $recipe, $corpus, $opts, $attempt), 8192, $this->system_prompt());
            if (!$data || empty($data['content'])) {
                $recipe = $this->recipe($service, $city, $attempt + 17);
                continue;
            }
            $html = wp_kses_post((string) $data['content']);
            $score = $this->uniqueness_score($html, $corpus);
            $pack  = $this->normalize($data, $service, $city, $recipe, $html, $score);
            if (!$best || $pack['uniqueness'] > $best['uniqueness']) {
                $best = $pack;
            }
            if ($pack['uniqueness'] >= $min_unique || empty($corpus['titles'])) {
                break;
            }
            $recipe = $this->recipe($service, $city, $attempt + 31);
            $corpus['forbidden'][] = mb_substr(wp_strip_all_tags($html), 0, 280);
        }

        if (!$best) {
            RKS_DB::log('generator', 'generate', 'error', $service . ' × ' . $city);
            return ['ok' => false, 'error' => 'لم يُرجع الذكاء الاصطناعي مقالاً صالحاً'];
        }

        RKS_DB::log('generator', 'generate', 'ok', $service . ' × ' . $city . ' / ' . $best['angle'], 0, $best['word_count']);
        $best['ok']      = true;
        $best['attempts'] = $attempt;
        return $best;
    }

    public function publish(array $a): int {
        $status = in_array($a['status'] ?? '', ['draft', 'publish', 'pending'], true) ? $a['status'] : 'draft';
        $title  = $a['title'] ?: trim(($a['service'] ?? '') . ' ' . ($a['city'] ?? ''));
        $slug   = $a['slug'] ?: RKS_Helper::slug(($a['service'] ?? '') . '-' . RKS_Helper::city_bare($a['city'] ?? ''));
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $a['html'] ?? '',
            'post_status'  => $status,
            'post_name'    => $slug,
            'post_type'    => 'post',
            'post_excerpt' => $a['meta_desc'] ?? '',
            'post_author'  => get_current_user_id() ?: 1,
        ], true);
        if (is_wp_error($post_id)) {
            return 0;
        }

        if (!empty($a['category'])) {
            $term = get_term_by('name', $a['category'], 'category');
            $tid  = $term ? (int) $term->term_id : 0;
            if (!$tid) {
                $created = wp_insert_term($a['category'], 'category');
                $tid = is_wp_error($created) ? 0 : (int) $created['term_id'];
            }
            if ($tid) {
                wp_set_post_categories($post_id, [$tid]);
            }
        }

        $meta = (new RKS_Meta_Writer())->build($a);
        foreach ($meta as $k => $v) {
            update_post_meta($post_id, $k, $v);
        }

        RKS_DB::insert('articles', [
            'post_id'     => $post_id,
            'service'     => $a['service'] ?? '',
            'city'        => $a['city'] ?? '',
            'angle'       => $a['angle'] ?? '',
            'persona'     => $a['persona'] ?? '',
            'structure'   => $a['structure'] ?? '',
            'fingerprint' => $a['fingerprint'] ?? '',
            'uniqueness'  => (int) ($a['uniqueness'] ?? 0),
            'word_count'  => (int) ($a['word_count'] ?? 0),
            'status'      => $status,
            'created_at'  => current_time('mysql'),
        ]);

        global $wpdb;
        $wpdb->replace(RKS_DB::t('seo'), [
            'post_id'    => $post_id,
            'meta_title' => $a['meta_title'] ?? $title,
            'meta_desc'  => $a['meta_desc'] ?? '',
            'focus_kw'   => trim(($a['service'] ?? '') . ' ' . RKS_Helper::city_bare($a['city'] ?? '')),
            'lsi_json'   => wp_json_encode($a['lsi'] ?? []),
            'faq_json'   => wp_json_encode($a['faq'] ?? []),
            'schema_json'=> '',
            'seo_score'  => 0,
            'last_ai'    => current_time('mysql'),
        ]);

        return (int) $post_id;
    }

    public function recipe(string $service, string $city, int $salt = 0): array {
        $seed = crc32(home_url() . '|' . $service . '|' . $city . '|' . gmdate('Y-m-d') . '|' . $salt . '|' . wp_generate_password(6, false));
        $ak = array_keys(self::ANGLES);
        $pk = array_keys(self::PERSONAS);
        $sk = array_keys(self::STRUCTURES);
        $used = $this->used_combos($service, $city);
        $angle = $this->pick_unused($ak, $used['angle'] ?? [], $seed);
        $persona = $this->pick_unused($pk, $used['persona'] ?? [], $seed >> 3);
        $structure = $this->pick_unused($sk, $used['structure'] ?? [], $seed >> 7);
        return [
            'angle'      => $angle,
            'persona'    => $persona,
            'structure'  => $structure,
            'angle_txt'  => self::ANGLES[$angle],
            'persona_txt'=> self::PERSONAS[$persona],
            'struct_txt' => self::STRUCTURES[$structure],
            'seed'       => $seed,
        ];
    }

    private function pick_unused(array $keys, array $used, int $seed): string {
        $fresh = array_values(array_diff($keys, $used));
        $pool  = $fresh ?: $keys;
        return $pool[$seed % count($pool)];
    }

    private function used_combos(string $service, string $city): array {
        $rows = RKS_DB::get_results(
            'SELECT angle, persona, structure FROM ' . RKS_DB::t('articles') . ' WHERE service = %s AND city = %s ORDER BY id DESC LIMIT 40',
            [$service, $city]
        );
        $out = ['angle' => [], 'persona' => [], 'structure' => []];
        foreach ($rows as $r) {
            $out['angle'][]     = $r->angle;
            $out['persona'][]   = $r->persona;
            $out['structure'][] = $r->structure;
        }
        return $out;
    }

    private function previous_corpus(string $service, string $city, int $exclude = 0): array {
        global $wpdb;
        $like_s = '%' . $wpdb->esc_like($service) . '%';
        $like_c = '%' . $wpdb->esc_like(RKS_Helper::city_bare($city)) . '%';
        $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts}
                WHERE post_type='post' AND post_status IN ('publish','draft','pending')
                AND (post_title LIKE %s OR post_title LIKE %s)";
        $args = [$like_s, $like_c];
        if ($exclude) {
            $sql .= ' AND ID != %d';
            $args[] = $exclude;
        }
        $sql .= ' ORDER BY ID DESC LIMIT 12';
        $posts = $wpdb->get_results($wpdb->prepare($sql, $args)) ?: [];
        $titles = [];
        $excerpts = [];
        $grams = [];
        foreach ($posts as $p) {
            $titles[]   = $p->post_title;
            $excerpts[] = mb_substr(wp_strip_all_tags($p->post_content), 0, 220);
            $grams      = array_merge($grams, array_slice(RKS_Helper::trigrams($p->post_content), 0, 80));
        }
        return [
            'titles'    => $titles,
            'excerpts'  => $excerpts,
            'grams'     => array_values(array_unique($grams)),
            'forbidden' => [],
        ];
    }

    public function uniqueness_score(string $html, array $corpus): int {
        $grams = RKS_Helper::trigrams($html);
        if (!$grams) {
            return 0;
        }
        if (empty($corpus['grams'])) {
            return 100;
        }
        $sim = RKS_Helper::jaccard($grams, $corpus['grams']);
        return max(0, min(100, (int) round(100 - $sim)));
    }

    private function system_prompt(): string {
        return 'أنت كاتب عربي محترف يكتب مقالات حصرية لمواقع خدمات محلية. '
            . 'كل مقال يجب أن يبدو وكُتب من الصفر لهذا الموقع تحديداً. '
            . 'ممنوع تكرار فقرات جاهزة أو قوالب عامة. اكتب HTML نظيفاً فقط داخل JSON.';
    }

    private function prompt(string $service, string $city, array $recipe, array $corpus, array $opts, int $attempt): string {
        $company = RKS_Helper::company();
        $phone   = (string) RKS_Helper::opt('phone');
        $wa      = (string) RKS_Helper::opt('whatsapp');
        $country = RKS_Helper::country_name();
        $words   = (int) ($opts['word_count'] ?? 2200);
        $tone    = (string) ($opts['tone'] ?? 'احترافي ومقنع');
        $bare    = RKS_Helper::city_bare($city);

        $avoid  = '';
        if (!empty($corpus['titles'])) {
            $avoid .= "عناوين مقالات موجودة مسبقاً — لا تقلّدها:\n- " . implode("\n- ", array_slice($corpus['titles'], 0, 8)) . "\n";
        }
        if (!empty($corpus['excerpts'])) {
            $avoid .= "مقاطع سابقة — لا تعد استخدامها:\n- " . implode("\n- ", array_slice($corpus['excerpts'], 0, 5)) . "\n";
        }
        if (!empty($corpus['forbidden'])) {
            $avoid .= "محاولة سابقة رُفضت لتشابهها. أعد الكتابة من زاوية مختلفة تماماً.\n";
        }

        return "اكتب مقال SEO حصري بالعربية الفصحى.\n"
            . "الخدمة كما كتبها المستخدم: {$service}\n"
            . "المدينة كما كتبها المستخدم: {$city}\n"
            . "اسم المدينة الصافي: {$bare}\n"
            . "الدولة: {$country}\n"
            . "اسم الشركة: {$company}\n"
            . ($phone ? "الهاتف: {$phone}\n" : '')
            . ($wa ? "واتساب: {$wa}\n" : '')
            . "عدد الكلمات المستهدف: {$words}\n"
            . "النبرة: {$tone}\n"
            . "الزاوية الحصرية لهذه المرة: {$recipe['angle']} — {$recipe['angle_txt']}\n"
            . "شخصية الكاتب: {$recipe['persona']} — {$recipe['persona_txt']}\n"
            . "هيكل المقال: {$recipe['structure']} — {$recipe['struct_txt']}\n"
            . "بصمة التوليد: {$recipe['seed']} / محاولة {$attempt}\n\n"
            . $avoid
            . "قواعد إلزامية:\n"
            . "1) العنوان فريد وغير مكرر. لا تستخدم نفس صياغة «أفضل شركة … في …» إلا إذا كانت زاوية المقال تتطلبها بشكل طبيعي.\n"
            . "2) لا تستخدم قوالب جاهزة ولا فقرات عامة تصلح لأي مدينة.\n"
            . "3) اذكر معالم أو أحياء أو ظروف مناخ/سوق حقيقية قدر الإمكان. إن لم تكن متأكداً لا تختلق أسماء وهمية.\n"
            . "4) المحتوى HTML: استخدم h2/h3 و p و ul/ol و details/summary و table عند الحاجة. ممنوع markdown.\n"
            . "5) الأسئلة الشائعة 6 عناصر على الأقل، مختلفة عن أي مقال سابق.\n"
            . "6) لا تضع schema داخل المحتوى.\n"
            . "7) CTA واحد طبيعي في الخاتمة يذكر الشركة ووسيلة التواصل إن وُجدت.\n\n"
            . "أرجع JSON بهذا الشكل فقط:\n"
            . "{\n"
            . "  \"title\": \"عنوان المقال\",\n"
            . "  \"meta_title\": \"60 حرف\",\n"
            . "  \"meta_desc\": \"150-160 حرف\",\n"
            . "  \"slug_hint\": \"english-slug\",\n"
            . "  \"content\": \"HTML كامل\",\n"
            . "  \"faq\": [{\"q\":\"\",\"a\":\"\"}],\n"
            . "  \"features\": [{\"title\":\"\",\"content\":\"\"}],\n"
            . "  \"steps\": [{\"title\":\"\",\"content\":\"\"}],\n"
            . "  \"lsi\": [\"كلمة1\",\"كلمة2\"],\n"
            . "  \"testimonial\": {\"name\":\"اسم عام\",\"text\":\"\"}\n"
            . "}";
    }

    private function normalize(array $data, string $service, string $city, array $recipe, string $html, int $score): array {
        $title = sanitize_text_field($data['title'] ?? ($service . ' ' . $city));
        $slug  = sanitize_title($data['slug_hint'] ?? '') ?: RKS_Helper::slug($service . '-' . RKS_Helper::city_bare($city));
        $faq   = is_array($data['faq'] ?? null) ? $data['faq'] : [];
        return [
            'title'       => $title,
            'html'        => $html,
            'meta_title'  => sanitize_text_field($data['meta_title'] ?? $title),
            'meta_desc'   => sanitize_text_field($data['meta_desc'] ?? ''),
            'slug'        => $slug,
            'service'     => $service,
            'city'        => $city,
            'faq'         => $faq,
            'features'    => is_array($data['features'] ?? null) ? $data['features'] : [],
            'steps'       => is_array($data['steps'] ?? null) ? $data['steps'] : [],
            'lsi'         => is_array($data['lsi'] ?? null) ? $data['lsi'] : [],
            'testimonial' => is_array($data['testimonial'] ?? null) ? $data['testimonial'] : [],
            'angle'       => $recipe['angle'],
            'persona'     => $recipe['persona'],
            'structure'   => $recipe['structure'],
            'fingerprint' => hash('sha256', wp_strip_all_tags($html)),
            'uniqueness'  => $score,
            'word_count'  => RKS_Helper::word_count($html),
        ];
    }

    private function existing_post(string $service, string $city): int {
        $slug = RKS_Helper::slug($service . '-' . RKS_Helper::city_bare($city));
        $p    = get_page_by_path($slug, OBJECT, 'post');
        if ($p) {
            return (int) $p->ID;
        }
        $row = RKS_DB::get_row(
            'SELECT post_id FROM ' . RKS_DB::t('articles') . ' WHERE service = %s AND city = %s AND post_id IS NOT NULL ORDER BY id DESC LIMIT 1',
            [$service, $city]
        );
        return $row ? (int) $row->post_id : 0;
    }

    private function pair_count(string $service, string $city): int {
        return (int) RKS_DB::get_var(
            'SELECT COUNT(*) FROM ' . RKS_DB::t('articles') . ' WHERE service = %s AND city = %s',
            [$service, $city]
        );
    }
}
