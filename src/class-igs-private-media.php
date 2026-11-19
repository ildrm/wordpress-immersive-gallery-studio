<?php

defined( 'ABSPATH' ) || exit;

final class IGS_Private_Media {
    public const POST_TYPE = 'igs_private_media';
    public const MAX_BYTES = 12 * MB_IN_BYTES;
    private const MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' );
    private static array $pending_deletions = array();

    public static function register(): void {
        register_post_type( self::POST_TYPE, array(
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'query_var' => false,
            'rewrite' => false,
            'capabilities' => array( 'create_posts' => 'do_not_allow' ),
        ) );
    }

    public static function directory(): string {
        if ( ! defined( 'IGS_PRIVATE_MEDIA_DIR' ) || ! is_string( IGS_PRIVATE_MEDIA_DIR ) || ! path_is_absolute( IGS_PRIVATE_MEDIA_DIR ) ) return '';
        $path = realpath( IGS_PRIVATE_MEDIA_DIR );
        if ( false === $path || ! is_dir( $path ) ) return '';
        $path = trailingslashit( wp_normalize_path( $path ) );
        if ( '/' === $path || preg_match( '#^[A-Za-z]:/$#D', $path ) ) return '';
        $uploads = wp_get_upload_dir();
        $web_roots = array( ABSPATH, WP_CONTENT_DIR, $_SERVER['DOCUMENT_ROOT'] ?? '', $uploads['basedir'] );
        foreach ( $web_roots as $root ) {
            $root = $root ? realpath( $root ) : false;
            if ( false !== $root && str_starts_with( $path, trailingslashit( wp_normalize_path( $root ) ) ) ) return '';
        }
        return $path;
    }

    public static function url( int $id ): string {
        return add_query_arg( 'igs_private_image', $id, home_url( '/' ) );
    }

    public static function describe( int $id ): array {
        $data = get_post_meta( $id, '_igs_private_file', true );
        if ( ! is_array( $data ) || ! is_string( $data['name'] ?? null ) || ! preg_match( '/^[a-f0-9]{40}$/D', $data['name'] ) || ! in_array( $data['mime'] ?? '', self::MIMES, true ) ) return array();
        return $data;
    }

    public static function file( array $data ): string {
        $directory = self::directory();
        if ( '' === $directory ) return '';
        $path = $directory . $data['name'];
        $real = realpath( $path );
        return $real && wp_normalize_path( $real ) === $path && is_file( $path ) && is_readable( $path ) ? $path : '';
    }

    public static function upload_ajax(): void {
        check_ajax_referer( 'igs_private_upload', 'nonce' );
        $gallery = isset( $_POST['gallery'] ) && is_scalar( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
        if ( 'igs_gallery' !== get_post_type( $gallery ) || ! current_user_can( 'edit_post', $gallery ) || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( null, 403 );
        }
        $directory = self::directory();
        if ( '' === $directory || ! is_writable( $directory ) ) wp_send_json_error( __( 'Private media storage is unavailable.', 'immersive-gallery-studio' ), 503 );
        $file = $_FILES['image'] ?? array();
        $tmp = $file['tmp_name'] ?? null;
        if ( ! is_string( $tmp ) || UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_uploaded_file( $tmp ) || filesize( $tmp ) > self::MAX_BYTES ) {
            wp_send_json_error( __( 'Choose an image no larger than 12 MB.', 'immersive-gallery-studio' ), 400 );
        }
        $info = wp_getimagesize( $tmp );
        if ( ! $info || ! in_array( $info['mime'], self::MIMES, true ) || ! in_array( $info['mime'], get_allowed_mime_types(), true ) ) {
            wp_send_json_error( __( 'Choose a supported raster image.', 'immersive-gallery-studio' ), 400 );
        }
        $title = is_string( $file['name'] ?? null ) ? sanitize_text_field( pathinfo( wp_basename( $file['name'] ), PATHINFO_FILENAME ) ) : '';
        $name = bin2hex( random_bytes( 20 ) );
        $target = $directory . $name;
        $id = wp_insert_post( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => wp_slash( $title ?: __( 'Private image', 'immersive-gallery-studio' ) ),
            'post_parent' => $gallery,
        ), true );
        if ( is_wp_error( $id ) || ! $id ) wp_send_json_error( __( 'The image record could not be saved.', 'immersive-gallery-studio' ), 500 );
        $data = array( 'name' => $name, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1] );
        if ( ! add_post_meta( $id, '_igs_private_file', $data, true ) || ! move_uploaded_file( $tmp, $target ) ) {
            wp_delete_post( $id, true );
            wp_send_json_error( __( 'The image could not be saved.', 'immersive-gallery-studio' ), 500 );
        }
        chmod( $target, 0600 );
        wp_send_json_success( array( 'id' => $id, 'url' => self::url( $id ), 'title' => $title ) );
    }

    public static function remember_file( int $id, WP_Post $post ): void {
        if ( self::POST_TYPE !== $post->post_type ) return;
        $data = self::describe( $id );
        if ( $data ) self::$pending_deletions[ $id ] = self::file( $data );
    }

    public static function delete_files( int $id, WP_Post $post ): void {
        $file = self::$pending_deletions[ $id ] ?? '';
        unset( self::$pending_deletions[ $id ] );
        if ( $file ) wp_delete_file( $file );
        if ( 'igs_gallery' === $post->post_type ) {
            $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'post_parent' => $id, 'fields' => 'ids', 'numberposts' => -1 ) );
            foreach ( $ids as $media_id ) wp_delete_post( $media_id, true );
        }
    }
}
