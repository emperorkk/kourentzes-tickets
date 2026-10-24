<?php
namespace KTickets;
if (!defined('ABSPATH')) { exit; }

final class Store {
    public static function db() { global $wpdb; return $wpdb; }
    public static function table(string $name): string { return self::db()->prefix . 'ktickets_' . $name; }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function sql(string $query): int {
        $result = self::db()->query($query);
        if ($result === false) { throw new \RuntimeException('storage_unavailable'); }
        return $result;
    }
    public static function insert(string $table, array $data): int {
        if (self::db()->insert(self::table($table), $data) === false) { throw new \RuntimeException('storage_unavailable'); }
        return (int) self::db()->insert_id;
    }
    public static function lock(string $name, int $timeout = 3): void {
        $key = 'kt_' . substr(hash('sha256', self::db()->prefix . $name), 0, 50);
        if ((int) self::db()->get_var(self::db()->prepare('SELECT GET_LOCK(%s, %d)', $key, $timeout)) !== 1) { throw new \RuntimeException('storage_busy'); }
    }
    public static function unlock(string $name): void {
        $key = 'kt_' . substr(hash('sha256', self::db()->prefix . $name), 0, 50);
        self::db()->get_var(self::db()->prepare('SELECT RELEASE_LOCK(%s)', $key));
    }
    public static function transaction(callable $action) {
        self::sql('START TRANSACTION');
        try { $value = $action(); self::sql('COMMIT'); return $value; }
        catch (\Throwable $e) { self::db()->query('ROLLBACK'); throw $e; }
    }
    public static function ticket(string $public, bool $locked = false): ?array {
        $t = self::table('tickets');
        return self::db()->get_row(self::db()->prepare("SELECT * FROM $t WHERE public_id=%s" . ($locked ? ' FOR UPDATE' : ''), $public), ARRAY_A) ?: null;
    }
    public static function by_id(int $id): ?array {
        $t = self::table('tickets');
        return self::db()->get_row(self::db()->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A) ?: null;
    }
    public static function audit(int $id, string $event, array $metadata = []): void {
        self::insert('events', ['ticket_id'=>$id, 'event_type'=>$event, 'actor_user_id'=>get_current_user_id() ?: null, 'created_at'=>self::now(), 'redacted_metadata'=>wp_json_encode($metadata)]);
    }
    public static function caps(): array {
        return ['manage_ktickets_settings','view_ktickets','view_all_ktickets','reply_ktickets','assign_ktickets','view_ktickets_privacy','manage_ktickets_privacy','delete_ktickets'];
    }
    public static function defaults(): array {
        return ['enabled'=>false,'verified'=>false,'portal_page'=>0,'form_page'=>0,'privacy_page'=>0,'policy_url'=>'','terms_url'=>'','owner'=>0,'delete_on_uninstall'=>false,'manual_attack'=>false,'create_short'=>2,'create_day'=>5,'attack_valid'=>20,'attack_failed'=>50,'quarantine_capacity'=>100,'network_reply'=>40];
    }
    public static function settings(): array { return array_merge(self::defaults(), (array)get_option('ktickets_settings', [])); }
    public static function install(): void {
        if (is_multisite()) { wp_die(esc_html__('Kourentzes Tickets requires a single-site installation.', 'kourentzes-tickets')); }
        self::lock('migration', 10);
        try {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            $definitions = [
                'projects'=>"id bigint unsigned NOT NULL AUTO_INCREMENT,\nslug varchar(80) NOT NULL,\nname varchar(160) NOT NULL,\nenabled tinyint NOT NULL DEFAULT 1,\nallowed_categories text NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY slug (slug)",
                'tickets'=>"id bigint unsigned NOT NULL AUTO_INCREMENT,\npublic_id varchar(32) NOT NULL,\nproject_id bigint unsigned NOT NULL,\ncategory varchar(24) NOT NULL,\nsubject varchar(160) NOT NULL,\nstatus varchar(24) NOT NULL DEFAULT 'open',\nmoderation_state varchar(24) NOT NULL DEFAULT 'normal',\nabuse_flag tinyint NOT NULL DEFAULT 0,\nabuse_reason_codes text NOT NULL,\nalias varchar(80) NOT NULL DEFAULT '',\nenvironment text NOT NULL,\nassigned_to bigint unsigned NULL,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nclosed_at datetime NULL,\npurge_at datetime NULL,\nrevision bigint unsigned NOT NULL DEFAULT 1,\nPRIMARY KEY  (id),\nUNIQUE KEY public_id (public_id),\nKEY status_updated (status,updated_at),\nKEY assigned_status (assigned_to,status),\nKEY category_status (category,status),\nKEY moderation (moderation_state,category),\nKEY purge_at (purge_at)",
                'messages'=>"id bigint unsigned NOT NULL AUTO_INCREMENT,\nticket_id bigint unsigned NOT NULL,\nauthor_type varchar(12) NOT NULL,\nauthor_user_id bigint unsigned NULL,\nvisibility varchar(12) NOT NULL,\nbody mediumtext NOT NULL,\ncreated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nKEY ticket_created (ticket_id,created_at,id)",
                'access'=>"ticket_id bigint unsigned NOT NULL,\nsecret_hash char(64) NOT NULL,\nrevoked_at datetime NULL,\ncreated_at datetime NOT NULL,\nPRIMARY KEY  (ticket_id),\nUNIQUE KEY secret_hash (secret_hash)",
                'sessions'=>"session_hash char(64) NOT NULL,\nticket_id bigint unsigned NOT NULL,\ncsrf_hash char(64) NOT NULL,\nexpires_at datetime NOT NULL,\nrevoked_at datetime NULL,\nPRIMARY KEY  (session_hash),\nKEY ticket_id (ticket_id),\nKEY expires_at (expires_at)",
                'events'=>"id bigint unsigned NOT NULL AUTO_INCREMENT,\nticket_id bigint unsigned NOT NULL,\nevent_type varchar(40) NOT NULL,\nactor_user_id bigint unsigned NULL,\ncreated_at datetime NOT NULL,\nredacted_metadata text NOT NULL,\nPRIMARY KEY  (id),\nKEY ticket_created (ticket_id,created_at)",
                'privacy'=>"ticket_id bigint unsigned NOT NULL,\nrequest_type varchar(24) NOT NULL DEFAULT 'other',\nreceived_at datetime NULL,\nverification_status varchar(24) NOT NULL DEFAULT 'unverified',\ntarget_due_at datetime NULL,\nextension_reason text NOT NULL,\noutcome text NOT NULL,\nhold_reason text NOT NULL,\nhold_actor bigint unsigned NULL,\nhold_review_at datetime NULL,\nPRIMARY KEY  (ticket_id),\nKEY target_due_at (target_due_at)",
                'abuse'=>"scoped_hash char(64) NOT NULL,\nbucket varchar(48) NOT NULL,\ncount int unsigned NOT NULL DEFAULT 0,\nexpires_at datetime NOT NULL,\nPRIMARY KEY  (scoped_hash,bucket),\nKEY expires_at (expires_at)",
                'replays'=>"replay_hash char(64) NOT NULL,\npayload_hash char(64) NOT NULL,\nticket_id bigint unsigned NOT NULL,\nexpires_at datetime NOT NULL,\nPRIMARY KEY  (replay_hash),\nKEY ticket_id (ticket_id),\nKEY expires_at (expires_at)"
            ];
            foreach ($definitions as $name=>$definition) {
                $table = self::table($name);
                dbDelta("CREATE TABLE $table (\n$definition\n) ENGINE=InnoDB $charset;");
                $status = self::db()->get_row(self::db()->prepare('SHOW TABLE STATUS WHERE Name=%s', $table), ARRAY_A);
                if (!$status || strtoupper($status['Engine']) !== 'INNODB' || strpos($status['Collation'], 'utf8mb4') !== 0) { throw new \RuntimeException('InnoDB and utf8mb4 are required.'); }
            }
            $p = self::table('projects');
            if (!self::db()->get_var("SELECT id FROM $p LIMIT 1")) {
                self::insert('projects', ['slug'=>'soft1-code-assistant','name'=>'Soft1 Code assistant','enabled'=>1,'allowed_categories'=>wp_json_encode(['technical','suggestion','privacy','other'])]);
            }
            add_option('ktickets_settings', self::defaults(), '', false);
            update_option('ktickets_schema', KTICKETS_VERSION, false);
            delete_option('ktickets_migration_error');
            if (!wp_next_scheduled('ktickets_cleanup')) { wp_schedule_event(time()+300, 'daily', 'ktickets_cleanup'); }
        } catch (\Throwable $e) {
            update_option('ktickets_migration_error', 'Database migration failed. Verify InnoDB/utf8mb4 and database privileges.', false);
        } finally { self::unlock('migration'); }
    }
    public static function purge(int $id): void {
        foreach (['messages','access','sessions','events','privacy','replays'] as $name) {
            $t = self::table($name); self::sql(self::db()->prepare("DELETE FROM $t WHERE ticket_id=%d", $id));
        }
        $t=self::table('tickets'); self::sql(self::db()->prepare("DELETE FROM $t WHERE id=%d", $id));
    }
}
