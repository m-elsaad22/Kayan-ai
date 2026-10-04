<?php
defined('ABSPATH') || exit;

class RKS_CSV {

    public function __construct() {
        add_action('wp_ajax_rks_upload_csv', [$this, 'upload']);
        add_action('wp_ajax_rks_process_csv', [$this, 'batch']);
        add_action('wp_ajax_rks_reset_csv', [$this, 'reset']);
    }

    public function upload(): void {
        RKS_Helper::require_admin();
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(['msg' => 'لم يُرفع ملف']);
        }
        $file = $_FILES['csv_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            wp_send_json_error(['msg' => 'يجب أن يكون الملف CSV']);
        }
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (!empty($check['ext']) && $check['ext'] !== 'csv') {
            wp_send_json_error(['msg' => 'نوع الملف غير مسموح']);
        }
        $dir = trailingslashit(wp_upload_dir()['basedir']) . 'rks-imports';
        wp_mkdir_p($dir);
        $this->protect_dir($dir);
        $sid  = 'rks_' . time() . '_' . wp_generate_password(8, false);
        $dest = $dir . '/' . $sid . '.csv';
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            wp_send_json_error(['msg' => 'فشل نقل الملف']);
        }
        $total = 0;
        $headers = [];
        if (($fh = fopen($dest, 'r')) !== false) {
            $headers = fgetcsv($fh, 0, ',', '"', '\\') ?: [];
            while (fgetcsv($fh, 0, ',', '"', '\\') !== false) {
                $total++;
            }
            fclose($fh);
        }
        update_option('rks_import_session', [
            'session_id' => $sid,
            'file_path'  => $dest,
            'total'      => $total,
            'headers'    => $headers,
            'offset'     => 0,
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'errors'     => 0,
        ], false);
        wp_send_json_success(['session_id' => $sid, 'total' => $total, 'headers' => $headers]);
    }

    public function batch(): void {
        RKS_Helper::require_admin();
        $session = get_option('rks_import_session', []);
        if (empty($session['file_path']) || !is_readable($session['file_path'])) {
            wp_send_json_error(['msg' => 'لا توجد جلسة']);
        }
        $skip   = !empty($_POST['skip_existing']) && $_POST['skip_existing'] === 'true';
        $update = !empty($_POST['update_existing']) && $_POST['update_existing'] === 'true';
        $dry    = !empty($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        $fh = fopen($session['file_path'], 'r');
        $headers = fgetcsv($fh, 0, ',', '"', '\\');
        for ($i = 0; $i < (int) $session['offset']; $i++) {
            fgetcsv($fh, 0, ',', '"', '\\');
        }
        $results = [];
        $n = 0;
        while ($n < 5) {
            $row = fgetcsv($fh, 0, ',', '"', '\\');
            if ($row === false) {
                break;
            }
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $r    = $this->row($data, $skip, $update, $dry, $session['session_id']);
            $results[] = $r;
            $session[$r['status']] = ($session[$r['status']] ?? 0) + 1;
            $n++;
        }
        fclose($fh);
        $session['offset'] += $n;
        $done = $session['offset'] >= $session['total'];
        update_option('rks_import_session', $session, false);
        wp_send_json_success($session + ['results' => $results, 'done' => $done]);
    }

    public function reset(): void {
        RKS_Helper::require_admin();
        $s = get_option('rks_import_session', []);
        if (!empty($s['file_path']) && file_exists($s['file_path'])) {
            wp_delete_file($s['file_path']);
        }
        delete_option('rks_import_session');
        wp_send_json_success();
    }

    private function row(array $data, bool $skip, bool $update, bool $dry, string $sid): array {
        global $wpdb;
        $name  = sanitize_title($data['post_name'] ?? '');
        $title = wp_strip_all_tags($data['post_title'] ?? '');
        $exist = $name ? (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name=%s AND post_type='post' LIMIT 1", $name)) : 0;
        if ($exist && $skip && !$update) {
            $this->log($sid, null, $title, 'skipped', 'موجود');
            return ['status' => 'skipped', 'title' => $title];
        }
        if ($dry) {
            return ['status' => 'created', 'title' => $title, 'message' => 'dry'];
        }
        $arr = [
            'post_title'   => $title,
            'post_content' => wp_kses_post($data['post_content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($data['post_excerpt'] ?? ''),
            'post_status'  => in_array($data['post_status'] ?? 'draft', ['draft', 'publish', 'pending', 'private'], true) ? $data['post_status'] : 'draft',
            'post_name'    => $name,
            'post_type'    => 'post',
            'post_author'  => get_current_user_id() ?: 1,
        ];
        if (!empty($data['post_date'])) {
            $arr['post_date'] = sanitize_text_field($data['post_date']);
        }
        if ($exist && $update) {
            $arr['ID'] = $exist;
            $id = wp_update_post($arr, true);
            $st = 'updated';
        } else {
            $id = wp_insert_post($arr, true);
            $st = 'created';
        }
        if (is_wp_error($id)) {
            $this->log($sid, null, $title, 'error', $id->get_error_message());
            return ['status' => 'error', 'title' => $title, 'message' => $id->get_error_message()];
        }
        $allowed = $this->meta_keys();
        foreach ($allowed as $key) {
            if (!isset($data[$key]) || $data[$key] === '') {
                continue;
            }
            update_post_meta($id, $key, wp_kses_post($data[$key]));
        }
        $this->log($sid, $id, $title, $st, '');
        return ['status' => $st, 'title' => $title, 'post_id' => $id];
    }

    private function log(string $sid, $post_id, string $title, string $status, string $msg): void {
        RKS_DB::insert('import_log', [
            'session_id' => $sid,
            'post_id'    => $post_id,
            'post_title' => $title,
            'status'     => $status,
            'message'    => $msg,
            'created_at' => current_time('mysql'),
        ]);
    }

    private function protect_dir(string $dir): void {
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            file_put_contents($ht, "Deny from all\n");
        }
        $idx = $dir . '/index.php';
        if (!file_exists($idx)) {
            file_put_contents($idx, "<?php\n// silence\n");
        }
    }

    private function meta_keys(): array {
        return [
            'phone', 'whatsapp', 'whatsapp_number', 'phone_number',
            'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword',
            '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw',
            '_aioseo_title', '_aioseo_description', 'faq', 'yourcolor__faqs',
            'YourColor_Service', 'YourColor_Article', 'YourColor__Rating',
            'post__features__data', 'post__work_steps__data', 'post__services__data',
            'cover', 'articon', 'tie_primary_category',
        ];
    }
}
