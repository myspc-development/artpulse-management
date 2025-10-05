<?php

namespace ArtPulse\Core;

/**
 * Helper utilities for database-level operations.
 */
class DatabaseUtils
{
    public const INDEX_RESULT_SUCCESS     = 'success';
    public const INDEX_RESULT_UNSUPPORTED = 'unsupported';
    public const INDEX_RESULT_FAILED      = 'failed';

    /**
     * Stores the last database error captured during an index creation attempt.
     */
    private static ?string $last_error = null;

    /**
     * Attempt to add the partial index used for letter filtering on the postmeta table.
     *
     * @return string One of the INDEX_RESULT_* constants describing the outcome.
     */
    public static function add_letter_meta_index(): string
    {
        global $wpdb;

        self::$last_error = null;

        if ( ! self::supports_letter_meta_index() ) {
            return self::INDEX_RESULT_UNSUPPORTED;
        }

        $table = $wpdb->postmeta;
        $query = "CREATE INDEX IF NOT EXISTS ap_letter_key_idx ON {$table} (meta_key(32), meta_value(4))";

        $result = $wpdb->query( $query );

        if ( false === $result ) {
            self::$last_error = $wpdb->last_error ?: __( 'Unknown database error.', 'artpulse-management' );

            return self::INDEX_RESULT_FAILED;
        }

        return self::INDEX_RESULT_SUCCESS;
    }

    /**
     * Whether the current database driver supports the partial index definition.
     */
    public static function supports_letter_meta_index(): bool
    {
        global $wpdb;

        if ( empty( $wpdb->is_mysql ) ) {
            return false;
        }

        $server_info = is_callable( [ $wpdb, 'db_server_info' ] ) ? $wpdb->db_server_info() : '';

        if ( stripos( $server_info, 'mariadb' ) !== false ) {
            $version = self::extract_version_number( $server_info );

            return null !== $version && version_compare( $version, '10.5.0', '>=' );
        }

        $version = is_callable( [ $wpdb, 'db_version' ] ) ? $wpdb->db_version() : '';

        return ! empty( $version ) && version_compare( $version, '8.0.0', '>=' );
    }

    /**
     * Retrieve the last database error recorded by the helper.
     */
    public static function get_last_error(): ?string
    {
        return self::$last_error;
    }

    private static function extract_version_number( string $input ): ?string
    {
        if ( preg_match( '/(\d+\.\d+\.\d+)/', $input, $matches ) ) {
            return $matches[1];
        }

        if ( preg_match( '/(\d+\.\d+)/', $input, $matches ) ) {
            return $matches[1];
        }

        return null;
    }
}
