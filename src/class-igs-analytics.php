<?php

defined( 'ABSPATH' ) || exit;

final class IGS_Analytics {
    private const SCHEMA_VERSION = '1';

    public static function enabled(): bool {
        return defined( 'IGS_ENABLE_TRACKING' ) && true === IGS_ENABLE_TRACKING;
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'igs_analytics';
    }

    public static function install(): void {
        if ( self::SCHEMA_VERSION === get_option( 'igs_analytics_schema' ) ) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $suppress = $wpdb->suppress_errors( true );
        dbDelta( "CREATE TABLE $table (
            gallery_id bigint(20) unsigned NOT NULL,
            event_name varchar(16) NOT NULL,
            event_count bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (gallery_id,event_name)
        ) $charset;" );
        $columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
        $keys = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, 'PRIMARY' ), ARRAY_A );
        $wpdb->suppress_errors( $suppress );
        if ( ! array_diff( array( 'gallery_id', 'event_name', 'event_count' ), $columns )
            && array( 'gallery_id', 'event_name' ) === array_column( $keys ?: array(), 'Column_name' ) ) {
            update_option( 'igs_analytics_schema', self::SCHEMA_VERSION, false );
        }
    }

    public static function increment( int $id, string $event ): bool {
        if ( self::SCHEMA_VERSION !== get_option( 'igs_analytics_schema' ) ) return false;
        global $wpdb;
        $table = self::table();
        $affected = $wpdb->query( $wpdb->prepare(
            "INSERT INTO $table (gallery_id, event_name, event_count) VALUES (%d, %s, 1)
             ON DUPLICATE KEY UPDATE event_count = event_count + 1",
            $id, $event
        ) );
        return 1 === $affected || 2 === $affected;
    }

    public static function counts( int $id ): array {
        global $wpdb;
        $counts = array();
        foreach ( array( 'view', 'open', 'download', 'interaction' ) as $event ) {
            $counts[ $event ] = (int) get_post_meta( $id, '_igs_' . $event . 's', true );
        }
        if ( self::SCHEMA_VERSION === get_option( 'igs_analytics_schema' ) ) {
            $table = self::table();
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT event_name, event_count FROM $table WHERE gallery_id = %d", $id ), ARRAY_A );
            foreach ( $rows ?: array() as $row ) {
                $event = $row['event_name'];
                if ( isset( $counts[ $event ] ) ) $counts[ $event ] += (int) $row['event_count'];
            }
        }
        return $counts;
    }

    public static function delete_gallery( int $id ): void {
        if ( self::SCHEMA_VERSION === get_option( 'igs_analytics_schema' ) ) {
            global $wpdb;
            $wpdb->delete( self::table(), array( 'gallery_id' => $id ), array( '%d' ) );
        }
    }
}
