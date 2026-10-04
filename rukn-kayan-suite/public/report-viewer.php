<?php
defined('ABSPATH') || exit;

function rks_render_public_report(string $token): void {
    $row = RKS_DB::get_row('SELECT * FROM ' . RKS_DB::t('reports') . ' WHERE token=%s LIMIT 1', [$token]);
    if (!$row) {
        status_header(404);
        echo '<!doctype html><html lang="ar" dir="rtl"><body style="font-family:Tahoma;background:#0b1220;color:#fff;padding:40px;text-align:center"><p>التقرير غير موجود</p></body></html>';
        return;
    }
    if ($row->expires_at && strtotime($row->expires_at) < time()) {
        status_header(410);
        echo '<!doctype html><html lang="ar" dir="rtl"><body style="font-family:Tahoma;background:#0b1220;color:#fff;padding:40px;text-align:center"><p>انتهت صلاحية التقرير</p></body></html>';
        return;
    }
    RKS_DB::update('reports', ['view_count' => (int) $row->view_count + 1], ['id' => $row->id]);
    $d = json_decode($row->data_json, true) ?: [];
    $s = $d['summary'] ?? [];
    $title = esc_html($d['title'] ?? $row->title);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title>';
    echo '<style>body{margin:0;background:#0b1220;color:#e8eef9;font-family:Tahoma,Arial,sans-serif}.w{max-width:960px;margin:auto;padding:28px}.c{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}.b{background:#121a2b;border:1px solid #243049;border-radius:12px;padding:14px}.b b{font-size:28px;display:block}li{margin:6px 0}</style></head><body><div class="w">';
    echo '<h1>' . $title . '</h1><p>آخر ' . (int) ($d['days'] ?? 30) . ' يوم — ' . esc_html($d['generated'] ?? '') . '</p>';
    echo '<div class="c">';
    foreach ([['conversions', 'تحويلات'], ['calls', 'اتصال'], ['whatsapps', 'واتساب'], ['visitors', 'زوار']] as $k) {
        echo '<div class="b"><b>' . number_format((int) ($s[$k[0]] ?? 0)) . '</b>' . $k[1] . '</div>';
    }
    echo '</div><div class="b" style="margin-top:16px"><h3>أعلى الصفحات</h3><ol>';
    foreach (($d['top_pages'] ?? []) as $p) {
        $p = (array) $p;
        echo '<li>' . esc_html($p['page_title'] ?? $p['page_url'] ?? '') . ' — ' . (int) ($p['cnt'] ?? 0) . '</li>';
    }
    echo '</ol></div></div></body></html>';
}
