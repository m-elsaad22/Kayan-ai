<?php
defined('ABSPATH') || exit;

class RKS_DB {

    public static function t(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'rks_' . $name;
    }

    public static function install(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta('CREATE TABLE ' . self::t('visitors') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fingerprint VARCHAR(64) NOT NULL DEFAULT '',
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            country VARCHAR(100) NOT NULL DEFAULT '',
            city VARCHAR(100) NOT NULL DEFAULT '',
            language VARCHAR(20) NOT NULL DEFAULT '',
            device_type VARCHAR(20) NOT NULL DEFAULT '',
            os VARCHAR(60) NOT NULL DEFAULT '',
            browser VARCHAR(60) NOT NULL DEFAULT '',
            screen_res VARCHAR(20) NOT NULL DEFAULT '',
            referrer TEXT NOT NULL,
            utm_source VARCHAR(100) NOT NULL DEFAULT '',
            utm_medium VARCHAR(100) NOT NULL DEFAULT '',
            utm_campaign VARCHAR(200) NOT NULL DEFAULT '',
            traffic_src VARCHAR(40) NOT NULL DEFAULT 'direct',
            is_new TINYINT(1) NOT NULL DEFAULT 1,
            visit_count INT NOT NULL DEFAULT 1,
            first_visit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_visit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY fingerprint (fingerprint),
            KEY ip (ip),
            KEY last_visit (last_visit)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('sessions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            fingerprint VARCHAR(64) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            start_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            end_time DATETIME DEFAULT NULL,
            duration INT NOT NULL DEFAULT 0,
            pages_count INT NOT NULL DEFAULT 1,
            scroll_pct TINYINT NOT NULL DEFAULT 0,
            intent VARCHAR(20) NOT NULL DEFAULT '',
            is_new TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY start_time (start_time)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('conversions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number_id BIGINT UNSIGNED DEFAULT NULL,
            phone_raw VARCHAR(30) NOT NULL DEFAULT '',
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            fingerprint VARCHAR(64) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            country VARCHAR(100) NOT NULL DEFAULT '',
            city VARCHAR(100) NOT NULL DEFAULT '',
            device_type VARCHAR(20) NOT NULL DEFAULT '',
            browser VARCHAR(60) NOT NULL DEFAULT '',
            os VARCHAR(60) NOT NULL DEFAULT '',
            click_type VARCHAR(40) NOT NULL DEFAULT '',
            page_url TEXT NOT NULL,
            page_title VARCHAR(255) NOT NULL DEFAULT '',
            referrer TEXT NOT NULL,
            utm_source VARCHAR(100) NOT NULL DEFAULT '',
            utm_medium VARCHAR(100) NOT NULL DEFAULT '',
            utm_campaign VARCHAR(200) NOT NULL DEFAULT '',
            traffic_src VARCHAR(40) NOT NULL DEFAULT 'direct',
            gclid VARCHAR(255) NOT NULL DEFAULT '',
            is_suspicious TINYINT(1) NOT NULL DEFAULT 0,
            is_duplicate TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY number_id (number_id),
            KEY ip (ip),
            KEY created_at (created_at),
            KEY click_type (click_type)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('numbers') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            wa_number VARCHAR(30) NOT NULL DEFAULT '',
            type VARCHAR(20) NOT NULL DEFAULT 'both',
            color VARCHAR(20) NOT NULL DEFAULT '#1a6bff',
            note TEXT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY active (active)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('dni_rules') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number_id BIGINT UNSIGNED NOT NULL,
            source_type VARCHAR(40) NOT NULL DEFAULT 'direct',
            utm_source VARCHAR(100) NOT NULL DEFAULT '',
            utm_medium VARCHAR(100) NOT NULL DEFAULT '',
            utm_campaign VARCHAR(200) NOT NULL DEFAULT '',
            priority INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY number_id (number_id)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('heatmap') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            page_url TEXT NOT NULL,
            x_pct SMALLINT NOT NULL DEFAULT 0,
            y_pct SMALLINT NOT NULL DEFAULT 0,
            element VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('blacklist') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            reason VARCHAR(255) NOT NULL DEFAULT 'manual',
            click_count INT NOT NULL DEFAULT 0,
            blocked_by VARCHAR(60) NOT NULL DEFAULT 'admin',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ip (ip)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('reports') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            filters_json TEXT NOT NULL,
            data_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            view_count INT NOT NULL DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('articles') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            service VARCHAR(200) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            angle VARCHAR(80) NOT NULL DEFAULT '',
            persona VARCHAR(80) NOT NULL DEFAULT '',
            structure VARCHAR(80) NOT NULL DEFAULT '',
            fingerprint VARCHAR(64) NOT NULL DEFAULT '',
            uniqueness INT NOT NULL DEFAULT 0,
            word_count INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY service_city (service, city),
            KEY fingerprint (fingerprint)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('seo') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            meta_title VARCHAR(180) NOT NULL DEFAULT '',
            meta_desc TEXT NOT NULL,
            focus_kw VARCHAR(200) NOT NULL DEFAULT '',
            lsi_json TEXT NOT NULL,
            faq_json LONGTEXT NOT NULL,
            schema_json LONGTEXT NOT NULL,
            seo_score TINYINT NOT NULL DEFAULT 0,
            last_ai DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('links') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id BIGINT UNSIGNED NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            anchor_text VARCHAR(200) NOT NULL DEFAULT '',
            auto_added TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_id (source_id),
            KEY target_id (target_id)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('competitors') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword VARCHAR(200) NOT NULL DEFAULT '',
            url TEXT NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            word_count INT NOT NULL DEFAULT 0,
            score TINYINT NOT NULL DEFAULT 0,
            raw_data LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword (keyword)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('offers') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            city VARCHAR(100) NOT NULL DEFAULT '',
            trigger_type VARCHAR(30) NOT NULL DEFAULT 'time',
            active TINYINT(1) NOT NULL DEFAULT 1,
            impressions INT NOT NULL DEFAULT 0,
            clicks INT NOT NULL DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('logs') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module VARCHAR(50) NOT NULL DEFAULT '',
            action VARCHAR(100) NOT NULL DEFAULT '',
            post_id BIGINT UNSIGNED DEFAULT NULL,
            tokens INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'ok',
            note TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY module (module),
            KEY created_at (created_at)
        ) $c;");

        dbDelta('CREATE TABLE ' . self::t('import_log') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            post_id BIGINT DEFAULT NULL,
            post_title TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id)
        ) $c;");

        update_option('rks_db_version', RKS_DB_VERSION);
    }

    public static function insert(string $table, array $data) {
        global $wpdb;
        $ok = $wpdb->insert(self::t($table), $data);
        return $ok ? (int) $wpdb->insert_id : false;
    }

    public static function update(string $table, array $data, array $where): bool {
        global $wpdb;
        return (bool) $wpdb->update(self::t($table), $data, $where);
    }

    public static function get_var(string $sql, array $args = []) {
        global $wpdb;
        return $args ? $wpdb->get_var($wpdb->prepare($sql, $args)) : $wpdb->get_var($sql);
    }

    public static function get_row(string $sql, array $args = []) {
        global $wpdb;
        return $args ? $wpdb->get_row($wpdb->prepare($sql, $args)) : $wpdb->get_row($sql);
    }

    public static function get_results(string $sql, array $args = []): array {
        global $wpdb;
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
        return $rows ?: [];
    }

    public static function log(string $module, string $action, string $status = 'ok', string $note = '', int $post_id = 0, int $tokens = 0): void {
        self::insert('logs', [
            'module'     => $module,
            'action'     => $action,
            'post_id'    => $post_id ?: null,
            'tokens'     => $tokens,
            'status'     => $status,
            'note'       => $note,
            'created_at' => current_time('mysql'),
        ]);
    }

    public static function is_blacklisted(string $ip): bool {
        $id = self::get_var('SELECT id FROM ' . self::t('blacklist') . ' WHERE ip = %s LIMIT 1', [$ip]);
        return (bool) $id;
    }

    public static function find_number(string $phone) {
        $phone = RKS_Helper::clean_phone($phone);
        return self::get_row('SELECT * FROM ' . self::t('numbers') . ' WHERE (phone = %s OR wa_number = %s) AND active = 1 LIMIT 1', [$phone, $phone]);
    }

    public static function all_numbers(bool $with_stats = false, string $since = ''): array {
        $nums = self::get_results('SELECT * FROM ' . self::t('numbers') . ' WHERE active = 1 ORDER BY id ASC');
        if (!$with_stats) {
            return $nums;
        }
        $since = $since ?: gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
        foreach ($nums as $n) {
            $row = self::get_row(
                'SELECT COUNT(*) total, SUM(click_type=%s) calls, SUM(click_type=%s) wa, COUNT(DISTINCT fingerprint) uniq
                 FROM ' . self::t('conversions') . ' WHERE number_id = %d AND created_at >= %s',
                ['call', 'whatsapp', $n->id, $since]
            );
            $n->total = (int) ($row->total ?? 0);
            $n->calls = (int) ($row->calls ?? 0);
            $n->wa    = (int) ($row->wa ?? 0);
            $n->uniq  = (int) ($row->uniq ?? 0);
        }
        return $nums;
    }
}
