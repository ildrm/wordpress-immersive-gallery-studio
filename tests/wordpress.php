<?php
/** Run only against a disposable WordPress installation: IGS_WP_ROOT=/path php tests/wordpress.php */
$root = getenv( 'IGS_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/wp-load.php' ) ) { fwrite( STDERR, "Set IGS_WP_ROOT to a disposable WordPress installation.\n" ); exit( 1 ); }
require $root . '/wp-load.php';
if ( 'local' !== wp_get_environment_type() ) { fwrite( STDERR, "Tests require WP_ENVIRONMENT_TYPE=local.\n" ); exit( 1 ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
activate_plugin( 'immersive-gallery-studio/immersive-gallery-studio.php' );
$plugin = IGS_Plugin::instance(); IGS_Plugin::activate();
$checks = 0;
function check( $condition, string $message ): void {
    global $checks; $checks++;
    if ( ! $condition ) throw new RuntimeException( "FAIL: $message" );
    echo "PASS: $message\n";
}
function invoke( string $method, ...$args ) { global $plugin; return ( new ReflectionMethod( $plugin, $method ) )->invoke( $plugin, ...$args ); }
function gallery( array $args = array() ): int {
    $id = wp_insert_post( array_merge( array( 'post_type' => 'igs_gallery', 'post_status' => 'publish', 'post_title' => 'IGS review gallery', 'post_author' => 1 ), $args ) );
    if ( is_wp_error( $id ) ) throw new RuntimeException( $id->get_error_message() );
    return $id;
}
wp_set_current_user( 1 );
update_option( 'timezone_string', 'Asia/Tehran' );
$registry = new IGS_Template_Registry();
check( count( $registry->labels() ) === 10, 'all ten template modules are discoverable' );
$sanitized = invoke( 'sanitize_settings', array( 'columns_desktop' => 0, 'gap' => -10, 'inertia' => 9, 'rotation_speed' => 'INF', 'page_height' => array( 1 ), 'template' => array( 'bad' ), 'accent' => 'url(javascript:alert(1))', 'show_counter' => '1' ) );
check( 1 === $sanitized['columns_desktop'] && 0 === $sanitized['gap'] && .99 === $sanitized['inertia'], 'invalid numbers are clamped to safe bounds' );
check( 'grid' === $sanitized['template'] && 480 === $sanitized['page_height'] && '#7c3aed' === $sanitized['accent'], 'malformed settings fall back without warnings' );
check( 1 === invoke( 'sanitize_settings', array() )['show_counter'], 'omitted settings preserve defaults' );
$upload = wp_upload_dir(); $file = $upload['path'] . '/igs-review-image.png';
if ( function_exists( 'imagecreatetruecolor' ) ) {
    $image = imagecreatetruecolor( 960, 640 );
    for ( $y = 0; $y < 640; $y++ ) { $color = imagecolorallocate( $image, 25 + (int)($y/6), 70 + (int)($y/9), 150 ); imageline( $image, 0, $y, 960, $y, $color ); }
    imagepng( $image, $file );
} else {
    file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6ioAAAAASUVORK5CYII=' ) );
}
$media = array();
foreach ( array( 'Alpha coast', 'Beta forest', 'Gamma mountain' ) as $title ) {
    $image_id = wp_insert_attachment( array( 'post_title' => $title, 'post_excerpt' => $title . ' caption', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file );
    wp_update_attachment_metadata( $image_id, wp_generate_attachment_metadata( $image_id, $file ) );
    update_post_meta( $image_id, '_wp_attachment_image_alt', $title ); $media[] = $image_id;
}
$id = gallery();
$_POST = array( 'igs_nonce' => wp_create_nonce( 'igs_save_gallery' ), 'igs_media_ids' => array_merge( $media, array( $media[0], $id, 999999 ) ), 'igs_settings' => array( 'template' => 'grid', 'show_counter' => 1 ), 'igs_access' => 'password', 'igs_gallery_password' => 'test \\ pass <&>', 'igs_expires' => '2027-02-30T15:00' );
$plugin->save_gallery( $id, get_post( $id ) );
check( get_post_meta( $id, '_igs_media_ids', true ) === $media, 'save removes duplicate, missing and non-image attachment IDs' );
check( '' === get_post_meta( $id, '_igs_expires', true ), 'impossible calendar dates are rejected' );
$hash = get_post_meta( $id, '_igs_password_hash', true );
check( $hash && ! str_contains( $hash, 'pass' ), 'passwords are hashed' );
$_POST = array( 'igs_nonce' => 'invalid', 'igs_media_ids' => array() );
$plugin->save_gallery( $id, get_post( $id ) );
check( $media === get_post_meta( $id, '_igs_media_ids', true ), 'invalid save nonce cannot modify media' );
$_POST = array();
wp_set_current_user( 0 );
check( str_contains( $plugin->render_gallery( $id ), 'igs-password' ) && ! str_contains( $plugin->render_gallery( $id ), 'data-full' ), 'locked gallery renders a form without media URLs' );
$expiry = time() + 1000;
$_COOKIE[ 'igs_access_' . $id ] = $expiry . '.' . hash_hmac( 'sha256', "$id|$hash|$expiry", wp_salt( 'auth' ) );
check( invoke( 'has_password_access', $id ), 'valid signed cookie permits access' );
update_post_meta( $id, '_igs_password_hash', wp_hash_password( 'changed' ) );
check( ! invoke( 'has_password_access', $id ), 'changing the password invalidates existing access cookies' );
update_post_meta( $id, '_igs_password_hash', $hash );
$expired = time() - 1;
$_COOKIE[ 'igs_access_' . $id ] = $expired . '.' . hash_hmac( 'sha256', "$id|$hash|$expired", wp_salt( 'auth' ) );
check( ! invoke( 'has_password_access', $id ), 'expired signed cookie is rejected server-side' );
$_COOKIE[ 'igs_access_' . $id ] = hash_hmac( 'sha256', "$id|$hash", wp_salt( 'auth' ) );
check( ! invoke( 'has_password_access', $id ), 'old non-expiring cookie is rejected' );
$_COOKIE[ 'igs_access_' . $id ] = array( 'bad' );
check( ! invoke( 'has_password_access', $id ), 'malformed cookies are safely rejected' );
unset( $_COOKIE[ 'igs_access_' . $id ] );
$album = gallery( array( 'post_type' => 'igs_album', 'post_title' => 'Review album' ) );
update_post_meta( $album, '_igs_album_galleries', array( $id ) );
$album_html = $plugin->shortcode_album( array( 'id' => $album ) );
check( str_contains( $album_html, 'igs-album-card' ) && ! str_contains( $album_html, '<img' ), 'album never exposes a locked gallery cover' );
wp_update_post( array( 'ID' => $album, 'post_status' => 'draft' ) );
check( '' === $plugin->shortcode_album( array( 'id' => $album ) ), 'draft albums cannot be embedded publicly' );
wp_update_post( array( 'ID' => $album, 'post_status' => 'private' ) );
check( '' === $plugin->shortcode_album( array( 'id' => $album ) ), 'private albums cannot be embedded anonymously' );
wp_update_post( array( 'ID' => $album, 'post_status' => 'publish' ) );
check( '' === $plugin->shortcode_album( array( 'id' => $id ) ), 'album shortcode rejects gallery IDs' );
update_post_meta( $id, '_igs_access', 'public' );
wp_update_post( array( 'ID' => $id, 'post_password' => 'native-password' ) );
check( ! str_contains( $plugin->render_gallery( $id ), 'data-full' ), 'native WordPress post passwords also protect shortcode media' );
wp_update_post( array( 'ID' => $id, 'post_password' => '' ) );
update_post_meta( $id, '_igs_expires', wp_date( 'Y-m-d\TH:i', time() - 60, wp_timezone() ) );
check( ! invoke( 'can_view', $id ), 'expiration uses the WordPress timezone' );
delete_post_meta( $id, '_igs_expires' );
$author = wp_insert_user( array( 'user_login' => 'igs_author_' . wp_rand(), 'role' => 'author', 'user_pass' => wp_generate_password() ) );
wp_set_current_user( $author );
check( ! current_user_can( 'edit_post', $id ) && ! current_user_can( 'edit_post', $album ), 'ordinary authors cannot edit galleries or albums' );
$_POST = array( 'igs_nonce' => wp_create_nonce( 'igs_save_gallery' ), 'igs_media_ids' => array() );
$plugin->save_gallery( $id, get_post( $id ) );
check( $media === get_post_meta( $id, '_igs_media_ids', true ), 'valid nonce does not bypass editing capability' );
$_POST = array();
add_role( 'igs_test_owner', 'IGS owner', array( 'read' => true, 'edit_igs_galleries' => true, 'edit_published_igs_galleries' => true ) );
$owner = wp_insert_user( array( 'user_login' => 'igs_owner_' . wp_rand(), 'role' => 'igs_test_owner', 'user_pass' => wp_generate_password() ) );
$own = gallery( array( 'post_author' => $owner ) ); wp_set_current_user( $owner );
check( current_user_can( 'edit_post', $own ) && ! current_user_can( 'edit_post', $id ), 'mapped capabilities distinguish own galleries from others' );
wp_set_current_user( 1 );
$fixtures = array( 'templates' => array(), 'media' => $media, 'album' => $album );
foreach ( $registry->labels() as $template => $label ) {
    $gallery = gallery( array( 'post_title' => $label ) );
    update_post_meta( $gallery, '_igs_media_ids', $media );
    update_post_meta( $gallery, '_igs_settings', invoke( 'sanitize_settings', array( 'template' => $template, 'show_search' => 1, 'show_counter' => 1, 'download' => 1 ) ) );
    $html = $plugin->render_gallery( $gallery );
    check( substr_count( $html, '<figure' ) === 3 && str_contains( $html, 'data-template="' . $template . '"' ), "$template renders all selected images" );
    $fixtures['templates'][ $template ] = $gallery;
    foreach ( $registry->settings_schema( $template ) as $field => $schema ) check( isset( IGS_Settings::defaults()[ $field ] ), "$template setting $field is supported" );
}
$locked = gallery( array( 'post_title' => 'Locked review', 'post_content' => 'Private gallery description secret' ) );
update_post_meta( $locked, '_igs_media_ids', $media ); update_post_meta( $locked, '_igs_access', 'password' ); update_post_meta( $locked, '_igs_password_hash', wp_hash_password( 'review-pass' ) );
$fixtures['locked'] = $locked;
$fixtures['logged_in'] = gallery( array( 'post_title' => 'Members review' ) );
update_post_meta( $fixtures['logged_in'], '_igs_media_ids', $media ); update_post_meta( $fixtures['logged_in'], '_igs_access', 'logged_in' );
$fixtures['private'] = gallery( array( 'post_status' => 'private' ) ); update_post_meta( $fixtures['private'], '_igs_media_ids', $media );
update_post_meta( $album, '_igs_album_galleries', array( $locked, $fixtures['templates']['grid'] ) );
$fixtures['page'] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Gallery shortcode review', 'post_content' => '[immersive_gallery id="' . $fixtures['templates']['grid'] . '"]' ) );
wp_set_current_user( 0 );
$rest = rest_do_request( '/wp/v2/igs_gallery/' . $locked );
check( 200 === $rest->get_status() && '' === $rest->get_data()['content']['rendered'], 'public WordPress REST does not leak locked description' );
$rest = rest_do_request( '/igs/v1/gallery/' . $locked );
check( 401 === $rest->get_status(), 'custom REST endpoint requires editing permission' );
wp_set_current_user( 1 );
$rest = rest_do_request( '/igs/v1/gallery/' . $fixtures['templates']['grid'] );
check( 200 === $rest->get_status() && $media === $rest->get_data()['media'], 'authorized custom REST request returns media' );
$rest = rest_do_request( '/igs/v1/gallery/' . $album );
check( 403 === $rest->get_status(), 'custom REST endpoint rejects wrong post types' );
$unsafe = IGS_Template_View::item( array( 'id' => 1, 'src' => 'javascript:alert(1)', 'full' => 'javascript:alert(1)', 'alt' => '" onload="alert(1)', 'title' => '<script>alert(1)</script>', 'caption' => '<img onerror=alert(1)>', 'width' => 1, 'height' => 1 ), 0, IGS_Settings::defaults() );
check( ! str_contains( $unsafe, '<script>' ) && ! str_contains( $unsafe, 'javascript:' ) && ! str_contains( $unsafe, '<img onerror' ), 'media URLs and captions cannot inject executable markup' );
$svg = invoke( 'code128_svg', 'https://example.test/گالری' );
check( ! str_contains( $svg, 'گالری' ) && str_contains( $svg, '%D' ), 'barcode percent-encodes Unicode without changing the destination' );
check( str_contains( $svg, '<rect x="20"' ), 'barcode has a ten-module quiet zone' );
file_put_contents( sys_get_temp_dir() . '/igs-review-fixtures.json', wp_json_encode( $fixtures ) );
echo "$checks checks passed; browser fixtures: " . sys_get_temp_dir() . "/igs-review-fixtures.json\n";
