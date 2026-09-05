<?php

defined( 'ABSPATH' ) || exit;

/** Shared bounds for persisted values, the builder and frontend rendering. */
final class IGS_Settings {
    public static function schema(): array {
        return array(
            'columns_desktop' => array( 4, 1, 8, 1 ),
            'columns_tablet' => array( 3, 1, 6, 1 ),
            'columns_mobile' => array( 2, 1, 4, 1 ),
            'gap' => array( 14, 0, 96, 1 ),
            'thumb_height' => array( 240, 100, 600, 10 ),
            'radius' => array( 420, 180, 800, 10 ),
            'card_width' => array( 220, 120, 360, 10 ),
            'card_height' => array( 150, 80, 360, 10 ),
            'rotation_speed' => array( 0.006, 0.001, 0.02, 0.001 ),
            'inertia' => array( 0.93, 0.80, 0.99, 0.01 ),
            'autoplay_speed' => array( 4500, 1500, 15000, 100 ),
            'border_radius' => array( 18, 0, 42, 1 ),
            'page_width' => array( 360, 220, 620, 10 ),
            'page_height' => array( 480, 280, 760, 10 ),
            'page_stiffness' => array( 0.65, 0.1, 1, 0.05 ),
            'cinematic_duration' => array( 6000, 2500, 20000, 100 ),
        );
    }

    public static function defaults(): array {
        return array_merge( array_map( static fn( array $field ) => $field[0], self::schema() ), array(
            'template' => 'grid', 'autoplay' => 0, 'captions' => 1, 'lightbox' => 1,
            'download' => 0, 'randomize' => 0, 'background' => '#0b1020', 'accent' => '#7c3aed',
            'reduced_motion_fallback' => 1, 'show_counter' => 1, 'show_search' => 0,
        ) );
    }

    public static function fields( array $keys ): array {
        $fields = array();
        $defaults = self::defaults();
        $numbers = self::schema();
        foreach ( $keys as $key ) {
            if ( isset( $numbers[ $key ] ) ) {
                $field = $numbers[ $key ];
                $fields[ $key ] = array( 'type' => is_int( $field[0] ) ? 'integer' : 'number', 'min' => $field[1], 'max' => $field[2], 'default' => $field[0] );
            } elseif ( isset( $defaults[ $key ] ) ) {
                $fields[ $key ] = array( 'type' => is_string( $defaults[ $key ] ) ? 'color' : 'boolean', 'default' => $defaults[ $key ] );
            }
        }
        return $fields;
    }

    public static function sanitize( array $raw, array $templates ): array {
        $out = self::defaults();
        if ( isset( $raw['template'] ) && is_string( $raw['template'] ) && isset( $templates[ $raw['template'] ] ) ) {
            $out['template'] = $raw['template'];
        }
        foreach ( self::schema() as $key => $field ) {
            if ( ! isset( $raw[ $key ] ) || ! is_scalar( $raw[ $key ] ) || ! is_numeric( $raw[ $key ] ) ) continue;
            $value = (float) $raw[ $key ];
            if ( ! is_finite( $value ) ) continue;
            $value = max( $field[1], min( $field[2], $value ) );
            $out[ $key ] = is_int( $field[0] ) ? (int) $value : $value;
        }
        foreach ( array( 'autoplay', 'captions', 'lightbox', 'download', 'randomize', 'reduced_motion_fallback', 'show_counter', 'show_search' ) as $key ) {
            if ( array_key_exists( $key, $raw ) ) $out[ $key ] = in_array( $raw[ $key ], array( true, 1, '1' ), true ) ? 1 : 0;
        }
        foreach ( array( 'background', 'accent' ) as $key ) {
            if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) $out[ $key ] = sanitize_hex_color( $raw[ $key ] ) ?: $out[ $key ];
        }
        return $out;
    }
}
