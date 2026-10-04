<?php
define('ABSPATH', '/tmp/');
define('RKS_VERSION', '1.0.0');
define('RKS_FILE', __DIR__ . '/../rukn-kayan-suite.php');
define('RKS_DIR', dirname(__DIR__) . '/');
define('RKS_URL', 'http://example.test/');
define('RKS_SLUG', 'rukn-kayan-suite');
define('RKS_DB_VERSION', '1.0.0');
define('DAY_IN_SECONDS', 86400);

function wp_json_encode($d, $f = 0) { return json_encode($d, $f | JSON_UNESCAPED_UNICODE); }
function wp_strip_all_tags($t) { return trim(preg_replace('/<[^>]+>/', ' ', (string) $t)); }
function wp_kses_post($t) { return $t; }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function sanitize_title($t) { return strtolower(preg_replace('/[^a-z0-9\-]+/', '-', (string) $t)); }
function sanitize_key($t) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $t)); }
function sanitize_textarea_field($t) { return trim((string) $t); }
function esc_url_raw($t) { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function current_time($t) { return date('Y-m-d H:i:s'); }
function home_url($p = '') { return 'https://example.test' . $p; }
function get_bloginfo($k) { return 'شركة الاختبار'; }
function get_option($k, $d = false) { return $d; }
function wp_generate_password($n = 8, $s = true) { return substr(bin2hex(random_bytes(8)), 0, $n); }
function add_action($a, $b, $c = 10, $d = 1) {}
function add_filter($a, $b, $c = 10, $d = 1) {}
function apply_filters($t, $v) { return $v; }
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return 'http://example.test/'; }
function is_admin() { return false; }

require_once RKS_DIR . 'includes/class-helper.php';
require_once RKS_DIR . 'modules/generator/class-unique-generator.php';

$g = new ReflectionClass('RKS_Unique_Generator');
$angles = $g->getConstant('ANGLES');
$personas = $g->getConstant('PERSONAS');
$structs = $g->getConstant('STRUCTURES');
if (count($angles) < 10 || count($personas) < 6 || count($structs) < 6) {
    fwrite(STDERR, "not enough uniqueness dimensions\n");
    exit(1);
}

$a = '<p>نقدم في دبي فحص تسربات المياه بأجهزة إلكترونية دقيقة مع تقرير مكتوب وضمان على المعالجة لكل عميل.</p>';
$b = '<p>فريق السلامة في أبوظبي يبدأ بتقييم المخاطر ثم يعزل المنطقة قبل أي إصلاح ويوثق كل خطوة للعميل بوضوح.</p>';
$score_same = RKS_Helper::jaccard(RKS_Helper::trigrams($a), RKS_Helper::trigrams($a));
$score_diff = RKS_Helper::jaccard(RKS_Helper::trigrams($a), RKS_Helper::trigrams($b));
if ($score_same < 90) {
    fwrite(STDERR, "expected high similarity for identical text: $score_same\n");
    exit(1);
}
if ($score_diff > 40) {
    fwrite(STDERR, "expected low similarity for different text: $score_diff\n");
    exit(1);
}

$phone = RKS_Helper::clean_phone('+971 50 123 4567');
if ($phone !== '971501234567') {
    fwrite(STDERR, "phone clean failed: $phone\n");
    exit(1);
}
$slug = RKS_Helper::slug('كشف تسربات في دبي');
if ($slug === '' || preg_match('/[\x{0600}-\x{06FF}]/u', $slug)) {
    fwrite(STDERR, "slug failed: $slug\n");
    exit(1);
}

echo "smoke_ok angles=" . count($angles) . " personas=" . count($personas) . " structs=" . count($structs) . "\n";
echo "sim_same=$score_same sim_diff=$score_diff slug=$slug\n";
exit(0);
