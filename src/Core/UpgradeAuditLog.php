<?php

namespace ArtPulse\Core;

use wpdb;

/**
 * Records structured audit information for the membership upgrade workflow.
 */
class UpgradeAuditLog
{
    private const TABLE_SUFFIX = 'ap_audit_log';
    private const ERROR_PREFIX = '[AP_UPGRADE_AUDIT] ';

    /** @var bool|null */
    private static $tableAvailable = null;

    /**
     * Create or update the storage table for audit log entries.
     */
    public static function install_table(): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $table_name      = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset_collate = $wpdb->get_charset_collate();

        $schema = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(191) NOT NULL,
            status varchar(100) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            related_id bigint(20) unsigned NOT NULL DEFAULT 0,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY status (status),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($schema);
        self::$tableAvailable = null;
    }

    /**
     * Log that a new upgrade request was created.
     *
     * @param array<string, mixed> $context
     */
    public static function log_request_created(int $user_id, string $type, int $request_id, array $context = []): void
    {
        self::persist('request', 'created', $user_id, $request_id, array_merge($context, [
            'type'       => $type,
            'request_id' => $request_id,
        ]));
    }

    /**
     * Log that a duplicate upgrade request was rejected.
     *
     * @param array<string, mixed> $context
     */
    public static function log_duplicate_rejected(int $user_id, string $type, int $request_id, array $context = []): void
    {
        self::persist('request', 'duplicate_rejected', $user_id, $request_id, array_merge($context, [
            'type'       => $type,
            'request_id' => $request_id,
        ]));
    }

    /**
     * Log that an upgrade review was approved by an administrator.
     *
     * @param array<string, mixed> $context
     */
    public static function log_approved(int $user_id, int $request_id, int $actor_id, array $context = []): void
    {
        self::persist('decision', 'approved', $user_id, $request_id, array_merge($context, [
            'request_id' => $request_id,
            'actor_id'   => $actor_id,
        ]));
    }

    /**
     * Log that an upgrade review was denied by an administrator.
     *
     * @param array<string, mixed> $context
     */
    public static function log_denied(int $user_id, int $request_id, int $actor_id, array $context = []): void
    {
        self::persist('decision', 'denied', $user_id, $request_id, array_merge($context, [
            'request_id' => $request_id,
            'actor_id'   => $actor_id,
        ]));
    }

    /**
     * Log that a profile was automatically created for a member.
     *
     * @param array<string, mixed> $context
     */
    public static function log_profile_autocreated(int $user_id, int $post_id, array $context = []): void
    {
        self::persist('profile', 'autocreated', $user_id, $post_id, array_merge($context, [
            'post_id' => $post_id,
        ]));
    }

    /**
     * Log that notifications were dispatched for an upgrade decision.
     *
     * @param array<string, mixed> $context
     */
    public static function log_notifications_sent(int $user_id, string $status, array $context = []): void
    {
        self::persist('notification', 'sent', $user_id, isset($context['request_id']) ? (int) $context['request_id'] : 0, array_merge($context, [
            'decision_status' => $status,
        ]));
    }

    /**
     * Retrieve the latest log entries with optional filters.
     *
     * @param array{user_id?:int,type?:string,status?:string,date_from?:string,date_to?:string,limit?:int} $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_entries(array $filters = []): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb || !self::table_exists()) {
            return [];
        }

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'event_type = %s';
            $params[] = sanitize_text_field($filters['type']);
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 500;
        if ($limit <= 0 || $limit > 500) {
            $limit = 500;
        }

        $sql = sprintf(
            'SELECT id, event_type, status, user_id, related_id, context, created_at FROM %s WHERE %s ORDER BY created_at DESC, id DESC LIMIT %d',
            $table,
            implode(' AND ', $where),
            $limit
        );

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            $context = [];
            if (isset($row['context']) && is_string($row['context']) && $row['context'] !== '') {
                $decoded = json_decode($row['context'], true);
                if (is_array($decoded)) {
                    $context = $decoded;
                }
            }

            return [
                'id'         => (int) $row['id'],
                'event_type' => (string) $row['event_type'],
                'status'     => (string) $row['status'],
                'user_id'    => (int) $row['user_id'],
                'related_id' => (int) $row['related_id'],
                'context'    => $context,
                'created_at' => (string) $row['created_at'],
            ];
        }, $rows);
    }

    /**
     * Retrieve distinct values for a given column.
     *
     * @return string[]
     */
    public static function get_distinct_values(string $column): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb || !in_array($column, ['event_type', 'status'], true) || !self::table_exists()) {
            return [];
        }

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $sql   = sprintf('SELECT DISTINCT %1$s FROM %2$s ORDER BY %1$s ASC LIMIT 100', $column, $table);
        $values = $wpdb->get_col($sql);

        if (!is_array($values)) {
            return [];
        }

        $values = array_filter(array_map('strval', $values));

        return array_values($values);
    }

    /**
     * Check whether the audit log storage is available.
     */
    public static function table_exists(): bool
    {
        if (null !== self::$tableAvailable) {
            return (bool) self::$tableAvailable;
        }

        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            self::$tableAvailable = false;

            return false;
        }

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        self::$tableAvailable = ($found === $table);

        return (bool) self::$tableAvailable;
    }

    /**
     * Persist a structured audit entry.
     *
     * @param array<string, mixed> $context
     */
    private static function persist(string $type, string $status, int $user_id, int $related_id, array $context): void
    {
        $payload = [
            'timestamp' => gmdate('c'),
            'type'      => $type,
            'status'    => $status,
            'user_id'   => $user_id,
            'related_id'=> $related_id,
            'context'   => $context,
        ];

        $json = wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            $json = '';
        }

        if (self::table_exists()) {
            self::insert_row($type, $status, $user_id, $related_id, $json);

            return;
        }

        if (function_exists('error_log')) {
            error_log(self::ERROR_PREFIX . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    private static function insert_row(string $type, string $status, int $user_id, int $related_id, string $json): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $inserted = $wpdb->insert(
            $table,
            [
                'event_type' => $type,
                'status'     => $status,
                'user_id'    => max(0, $user_id),
                'related_id' => max(0, $related_id),
                'context'    => $json,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s']
        );

        if (false === $inserted && function_exists('error_log')) {
            $fallback = [
                'type'      => $type,
                'status'    => $status,
                'user_id'   => $user_id,
                'related_id'=> $related_id,
                'context'   => json_decode($json, true),
                'error'     => $wpdb->last_error,
            ];

            error_log(self::ERROR_PREFIX . wp_json_encode($fallback, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
