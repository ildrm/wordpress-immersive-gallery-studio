<?php
$root = getenv( 'IGS_WP_ROOT' );
if ( ! $root ) exit( 1 );
define( 'DOING_AJAX', true );
require $root . '/wp-load.php';
if ( 'local' !== wp_get_environment_type() ) exit( 1 );
if ( 'attempt' === ( $argv[2] ?? '' ) ) {
    $allowed = ( new ReflectionMethod( IGS_Plugin::instance(), 'password_attempt_allowed' ) )->invoke( IGS_Plugin::instance(), (string) $argv[1] );
    echo json_encode( $allowed ); exit;
}
$id = (int) ( $argv[1] ?? 0 );
if ( 'read' === ( $argv[2] ?? '' ) ) {
    echo (int) get_post_meta( $id, '_igs_interactions', true ); exit;
}
$_POST = $_REQUEST = array( 'gallery' => $id, 'event' => 'interaction', 'nonce' => wp_create_nonce( 'igs_track' ) );
IGS_Plugin::instance()->track_ajax();
