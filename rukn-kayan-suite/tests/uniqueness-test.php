<?php
/**
 * اختبار مستقل لخوارزمية الحصرية (بدون ووردبريس).
 */
function rks_test_trigrams(string $text): array {
    $text = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', strip_tags($text)));
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

function rks_test_jaccard(array $a, array $b): float {
    if (!$a || !$b) {
        return 0.0;
    }
    $inter = count(array_intersect($a, $b));
    $union = count(array_unique(array_merge($a, $b)));
    return $union > 0 ? round(($inter / $union) * 100, 2) : 0.0;
}

function rks_test_score(string $html, array $corpus_grams): int {
    $grams = rks_test_trigrams($html);
    if (!$grams) {
        return 0;
    }
    if (!$corpus_grams) {
        return 100;
    }
    return max(0, min(100, (int) round(100 - rks_test_jaccard($grams, $corpus_grams))));
}

$a = 'نقدم في دبي فحص تسربات المياه بأجهزة إلكترونية دقيقة مع تقرير مكتوب وضمان على المعالجة.';
$b = 'نقدم في دبي فحص تسربات المياه بأجهزة إلكترونية دقيقة مع تقرير مكتوب وضمان على المعالجة.';
$c = 'فريق السلامة في أبوظبي يبدأ بتقييم المخاطر ثم يعزل المنطقة قبل أي إصلاح، ويوثق كل خطوة للعميل.';

$same = rks_test_score($b, rks_test_trigrams($a));
$diff = rks_test_score($c, rks_test_trigrams($a));

$fail = 0;
if ($same > 40) {
    fwrite(STDERR, "FAIL: identical texts scored too unique ($same)\n");
    $fail++;
}
if ($diff < 50) {
    fwrite(STDERR, "FAIL: different texts scored too similar ($diff)\n");
    $fail++;
}

$angles = ['problem_first','neighborhoods','pricing','emergency','comparison','case_study','seasonal','quality','howto','mistakes','buyer_guide','aftercare'];
$personas = ['field_engineer','ops_manager','local_advisor','cost_analyst','safety_officer','customer_lead','trainer','editor'];
$structs = ['story_arc','qa_heavy','local_map','process','contrast','briefing','timeline','checklist'];
$combos = count($angles) * count($personas) * count($structs);
if ($combos < 700) {
    fwrite(STDERR, "FAIL: not enough exclusive recipes ($combos)\n");
    $fail++;
}

echo "identical_uniqueness=$same\n";
echo "different_uniqueness=$diff\n";
echo "recipes=$combos\n";
echo $fail === 0 ? "OK\n" : "FAILED $fail\n";
exit($fail === 0 ? 0 : 1);
