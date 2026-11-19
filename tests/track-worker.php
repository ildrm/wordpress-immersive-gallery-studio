<?php
$root = getenv( 'IGS_WP_ROOT' );
if ( ! $root ) exit( 1 );
define( 'DOING_AJAX', true );
define( 'IGS_ENABLE_TRACKING', true );
require $root . '/wp-load.php';
if ( 'local' !== wp_get_environment_type() ) exit( 1 );
if ( 'setup' === ( $argv[2] ?? '' ) ) { IGS_Analytics::install(); exit( 0 ); }
if ( 'attempt' === ( $argv[2] ?? '' ) ) {
    $allowed = ( new ReflectionMethod( IGS_Plugin::instance(), 'password_attempt_allowed' ) )->invoke( IGS_Plugin::instance(), (string) $argv[1] );
    echo json_encode( $allowed ); exit;
}
$id = (int) ( $argv[1] ?? 0 );
if ( 'read' === ( $argv[2] ?? '' ) ) {
    echo IGS_Analytics::counts( $id )['interaction']; exit;
}
$_POST = $_REQUEST = array( 'gallery' => $id, 'event' => 'interaction', 'nonce' => wp_create_nonce( 'igs_track' ) );
IGS_Plugin::instance()->track_ajax();
