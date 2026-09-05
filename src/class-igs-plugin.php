<?php

defined( 'ABSPATH' ) || exit;

final class IGS_Plugin {
    private static ?self $instance = null;
    private array $rendered_gallery_ids = array();
    private ?IGS_Template_Registry $template_registry = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function template_registry(): IGS_Template_Registry {
        if ( null === $this->template_registry ) {
            $this->template_registry = new IGS_Template_Registry();
        }
        return $this->template_registry;
    }

    public static function activate(): void {
        self::register_post_types_static();
        self::add_caps();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function add_caps(): void {
        $caps = array(
            'read_igs_gallery', 'read_private_igs_galleries', 'edit_igs_gallery', 'edit_igs_galleries',
            'edit_others_igs_galleries', 'edit_private_igs_galleries', 'edit_published_igs_galleries',
            'publish_igs_galleries', 'delete_igs_gallery', 'delete_igs_galleries', 'delete_private_igs_galleries',
            'delete_published_igs_galleries', 'delete_others_igs_galleries', 'manage_igs_settings', 'view_igs_analytics',
        );
        foreach ( array( 'administrator', 'editor' ) as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }
            foreach ( $caps as $cap ) {
                $role->add_cap( $cap );
            }
        }
    }

    public function boot(): void {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_igs_gallery', array( $this, 'save_gallery' ), 10, 2 );
        add_action( 'save_post_igs_album', array( $this, 'save_album' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
        add_action( 'template_redirect', array( $this, 'process_gallery_unlock' ), 1 );
        add_shortcode( 'immersive_gallery', array( $this, 'shortcode_gallery' ) );
        add_shortcode( 'immersive_album', array( $this, 'shortcode_album' ) );
        add_filter( 'the_content', array( $this, 'append_single_gallery' ) );
        add_filter( 'manage_igs_gallery_posts_columns', array( $this, 'gallery_columns' ) );
        add_action( 'manage_igs_gallery_posts_custom_column', array( $this, 'gallery_column_content' ), 10, 2 );
        add_action( 'rest_api_init', array( $this, 'register_rest' ) );
        add_action( 'wp_ajax_igs_track', array( $this, 'track_ajax' ) );
        add_action( 'wp_ajax_nopriv_igs_track', array( $this, 'track_ajax' ) );
        add_action( 'wp_ajax_igs_barcode', array( $this, 'barcode_ajax' ) );
        add_action( 'wp_footer', array( $this, 'print_late_styles' ), 5 );
        add_action( 'wp_ajax_igs_preview', array( $this, 'preview_ajax' ) );
        add_action( 'template_redirect', array( $this, 'protect_response_cache' ), 0 );
        add_filter( 'rest_prepare_igs_gallery', array( $this, 'protect_rest_content' ), 10, 3 );
        add_filter( 'the_content', array( $this, 'protect_content' ), 1 );
        add_filter( 'post_thumbnail_html', array( $this, 'protect_thumbnail' ), 10, 2 );
        add_filter( 'get_the_excerpt', array( $this, 'protect_excerpt' ), 1, 2 );
    }

    public static function register_post_types_static(): void {
        $gallery_caps = array(
            'edit_post' => 'edit_igs_gallery', 'read_post' => 'read_igs_gallery', 'delete_post' => 'delete_igs_gallery',
            'edit_posts' => 'edit_igs_galleries', 'edit_others_posts' => 'edit_others_igs_galleries',
            'publish_posts' => 'publish_igs_galleries', 'read_private_posts' => 'read_private_igs_galleries',
            'delete_posts' => 'delete_igs_galleries', 'delete_private_posts' => 'delete_private_igs_galleries',
            'delete_published_posts' => 'delete_published_igs_galleries', 'delete_others_posts' => 'delete_others_igs_galleries',
            'edit_private_posts' => 'edit_private_igs_galleries', 'edit_published_posts' => 'edit_published_igs_galleries',
            'create_posts' => 'edit_igs_galleries',
        );
        register_post_type( 'igs_gallery', array(
            'labels' => array(
                'name' => __( 'Galleries', 'immersive-gallery-studio' ), 'singular_name' => __( 'Gallery', 'immersive-gallery-studio' ),
                'add_new_item' => __( 'Create Gallery', 'immersive-gallery-studio' ), 'edit_item' => __( 'Edit Gallery', 'immersive-gallery-studio' ),
                'menu_name' => __( 'Gallery Studio', 'immersive-gallery-studio' ),
            ),
            'public' => true, 'show_ui' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-format-gallery',
            'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'revisions', 'page-attributes' ),
            'rewrite' => array( 'slug' => 'gallery' ), 'has_archive' => true, 'capability_type' => array( 'igs_gallery', 'igs_galleries' ),
            'capabilities' => $gallery_caps, 'map_meta_cap' => true,
        ) );
        register_post_type( 'igs_album', array(
            'labels' => array( 'name' => __( 'Albums', 'immersive-gallery-studio' ), 'singular_name' => __( 'Album', 'immersive-gallery-studio' ) ),
            'public' => true, 'show_ui' => true, 'show_in_rest' => true, 'show_in_menu' => 'edit.php?post_type=igs_gallery',
            'capability_type' => array( 'igs_gallery', 'igs_galleries' ), 'capabilities' => $gallery_caps, 'map_meta_cap' => true,
            'supports' => array( 'title', 'editor', 'thumbnail', 'page-attributes' ), 'rewrite' => array( 'slug' => 'gallery-album' ),
        ) );
        register_taxonomy( 'igs_gallery_category', array( 'igs_gallery' ), array(
            'label' => __( 'Gallery Categories', 'immersive-gallery-studio' ), 'public' => true, 'hierarchical' => true, 'show_in_rest' => true,
        ) );
        register_taxonomy( 'igs_gallery_tag', array( 'igs_gallery' ), array(
            'label' => __( 'Gallery Tags', 'immersive-gallery-studio' ), 'public' => true, 'hierarchical' => false, 'show_in_rest' => true,
        ) );
    }

    public function register_post_types(): void { self::register_post_types_static(); }

    public function add_meta_boxes(): void {
        add_meta_box( 'igs_builder', __( 'Immersive Gallery Builder', 'immersive-gallery-studio' ), array( $this, 'builder_box' ), 'igs_gallery', 'normal', 'high' );
        add_meta_box( 'igs_access', __( 'Access & Sharing', 'immersive-gallery-studio' ), array( $this, 'access_box' ), 'igs_gallery', 'side', 'default' );
        if ( current_user_can( 'view_igs_analytics' ) ) add_meta_box( 'igs_analytics', __( 'Gallery Activity', 'immersive-gallery-studio' ), array( $this, 'analytics_box' ), 'igs_gallery', 'side', 'default' );
        add_meta_box( 'igs_album_galleries', __( 'Album Galleries', 'immersive-gallery-studio' ), array( $this, 'album_box' ), 'igs_album', 'normal', 'high' );
    }

    private function defaults(): array {
        return IGS_Settings::defaults();
    }

    public function builder_box( WP_Post $post ): void {
        wp_nonce_field( 'igs_save_gallery', 'igs_nonce' );
        $ids = array_map( 'absint', (array) get_post_meta( $post->ID, '_igs_media_ids', true ) );
        $settings = $this->sanitize_settings( (array) get_post_meta( $post->ID, '_igs_settings', true ) );
        $templates = $this->templates();
        ?>
        <div class="igs-builder" data-post="<?php echo esc_attr( $post->ID ); ?>">
            <div class="igs-builder__topbar">
                <div><strong><?php esc_html_e( 'Visual Gallery Builder', 'immersive-gallery-studio' ); ?></strong><span><?php esc_html_e( 'Drag media to reorder. Changes are previewed instantly.', 'immersive-gallery-studio' ); ?></span></div>
                <button type="button" class="button button-primary" id="igs-add-media"><?php esc_html_e( 'Add images', 'immersive-gallery-studio' ); ?></button>
            </div>
            <div class="igs-builder__body">
                <aside class="igs-panel">
                    <label class="igs-label"><?php esc_html_e( 'Template', 'immersive-gallery-studio' ); ?></label>
                    <div class="igs-template-grid">
                    <?php foreach ( $templates as $key => $label ) : ?>
                        <label class="igs-template-card <?php echo $settings['template'] === $key ? 'is-active' : ''; ?>">
                            <input type="radio" name="igs_settings[template]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['template'], $key ); ?>>
                            <span class="igs-template-card__icon"></span><b><?php echo esc_html( $label ); ?></b>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <?php
                    $labels = array(
                        'columns_desktop' => __( 'Desktop columns', 'immersive-gallery-studio' ),
                        'columns_tablet' => __( 'Tablet columns', 'immersive-gallery-studio' ),
                        'columns_mobile' => __( 'Mobile columns', 'immersive-gallery-studio' ),
                        'gap' => __( 'Gap', 'immersive-gallery-studio' ),
                        'thumb_height' => __( 'Image height', 'immersive-gallery-studio' ),
                        'border_radius' => __( 'Corner radius', 'immersive-gallery-studio' ),
                        'radius' => __( 'Ring radius', 'immersive-gallery-studio' ),
                        'card_width' => __( 'Card width', 'immersive-gallery-studio' ),
                        'card_height' => __( 'Card height', 'immersive-gallery-studio' ),
                        'rotation_speed' => __( 'Drag speed', 'immersive-gallery-studio' ),
                        'inertia' => __( 'Inertia', 'immersive-gallery-studio' ),
                        'page_width' => __( 'Page width', 'immersive-gallery-studio' ),
                        'page_height' => __( 'Page height', 'immersive-gallery-studio' ),
                        'page_stiffness' => __( 'Page stiffness', 'immersive-gallery-studio' ),
                        'autoplay_speed' => __( 'Slide interval (ms)', 'immersive-gallery-studio' ),
                        'cinematic_duration' => __( 'Cinematic interval (ms)', 'immersive-gallery-studio' ),
                    );
                    foreach ( IGS_Settings::schema() as $key => $field ) {
                        $this->range_field( $key, $labels[ $key ], $settings, $field[1], $field[2], $field[3] );
                    }
                    ?>
                    <div class="igs-switches">
                        <?php $this->check_field( 'captions', __( 'Captions', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'lightbox', __( 'Fullscreen viewer', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'autoplay', __( 'Auto play', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'download', __( 'Allow downloads', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'randomize', __( 'Random order', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'show_counter', __( 'Image counter', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'reduced_motion_fallback', __( 'Simple layout for reduced motion', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'show_search', __( 'Gallery search', 'immersive-gallery-studio' ), $settings ); ?>
                    </div>
                    <div class="igs-colors"><label><?php esc_html_e( 'Background', 'immersive-gallery-studio' ); ?><input type="color" name="igs_settings[background]" value="<?php echo esc_attr( $settings['background'] ); ?>"></label><label><?php esc_html_e( 'Accent', 'immersive-gallery-studio' ); ?><input type="color" name="igs_settings[accent]" value="<?php echo esc_attr( $settings['accent'] ); ?>"></label></div>
                </aside>
                <main class="igs-workspace">
                    <div class="igs-preview" id="igs-preview" aria-live="polite"><div class="igs-preview__empty"><?php esc_html_e( 'Add images to start building your gallery.', 'immersive-gallery-studio' ); ?></div></div>
                    <div class="igs-media-grid" id="igs-media-grid">
                    <?php foreach ( $ids as $id ) : $url = wp_get_attachment_image_url( $id, 'thumbnail' ); if ( ! $url ) continue; ?>
                        <div class="igs-media-item" draggable="true" data-id="<?php echo esc_attr( $id ); ?>"><img src="<?php echo esc_url( $url ); ?>" alt=""><button type="button" class="igs-remove" aria-label="<?php esc_attr_e( 'Remove', 'immersive-gallery-studio' ); ?>">×</button><input type="hidden" name="igs_media_ids[]" value="<?php echo esc_attr( $id ); ?>"></div>
                    <?php endforeach; ?>
                    </div>
                </main>
            </div>
        </div>
        <?php
    }

    private function range_field( string $key, string $label, array $settings, float $min, float $max, float $step, string $suffix = '' ): void {
        ?><label class="igs-control"><span><?php echo esc_html( $label ); ?> <output><?php echo esc_html( $settings[ $key ] . $suffix ); ?></output></span><input type="range" name="igs_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" data-suffix="<?php echo esc_attr( $suffix ); ?>"></label><?php
    }

    private function check_field( string $key, string $label, array $settings ): void {
        ?><label class="igs-switch"><input type="hidden" name="igs_settings[<?php echo esc_attr( $key ); ?>]" value="0"><input type="checkbox" name="igs_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>><span></span><?php echo esc_html( $label ); ?></label><?php
    }

    public function access_box( WP_Post $post ): void {
        $access = get_post_meta( $post->ID, '_igs_access', true ) ?: 'public';
        $expires = get_post_meta( $post->ID, '_igs_expires', true );
        $url = get_permalink( $post );
        ?>
        <p><button type="button" class="button" id="igs-open-builder"><?php esc_html_e( 'Open visual builder', 'immersive-gallery-studio' ); ?></button></p>
        <?php if ( 'password' === $access && ! get_post_meta( $post->ID, '_igs_password_hash', true ) ) : ?>
        <p role="alert"><strong><?php esc_html_e( 'Set a gallery password before sharing. Access is blocked until a password is saved or visibility is changed.', 'immersive-gallery-studio' ); ?></strong></p>
        <?php endif; ?>
        <p><label><strong><?php esc_html_e( 'Visibility', 'immersive-gallery-studio' ); ?></strong><select name="igs_access" class="widefat">
            <option value="public" <?php selected( $access, 'public' ); ?>><?php esc_html_e( 'Public', 'immersive-gallery-studio' ); ?></option>
            <option value="password" <?php selected( $access, 'password' ); ?>><?php esc_html_e( 'Password protected', 'immersive-gallery-studio' ); ?></option>
            <option value="logged_in" <?php selected( $access, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users', 'immersive-gallery-studio' ); ?></option>
        </select></label></p>
        <p><label><strong><?php esc_html_e( 'Gallery password', 'immersive-gallery-studio' ); ?></strong><input class="widefat" type="password" name="igs_gallery_password" value="" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep existing password', 'immersive-gallery-studio' ); ?>"></label></p>
        <p><label><input type="checkbox" name="igs_clear_password" value="1"> <?php esc_html_e( 'Remove saved gallery password', 'immersive-gallery-studio' ); ?></label></p>
        <p class="description"><?php esc_html_e( 'Passwords are stored as one-way hashes. They are never placed in gallery URLs or barcodes.', 'immersive-gallery-studio' ); ?></p>
        <p><label><strong><?php esc_html_e( 'Expire at', 'immersive-gallery-studio' ); ?></strong><input class="widefat" type="datetime-local" name="igs_expires" value="<?php echo esc_attr( $expires ); ?>"></label></p>
        <?php if ( $url ) : ?>
        <div class="igs-barcode-box"><strong><?php esc_html_e( 'Gallery barcode', 'immersive-gallery-studio' ); ?></strong><div id="igs-barcode-preview" data-url="<?php echo esc_url( $url ); ?>"></div><button type="button" class="button" id="igs-print-barcode"><?php esc_html_e( 'Print barcode', 'immersive-gallery-studio' ); ?></button></div>
        <?php endif; ?>
        <?php
    }

    public function album_box( WP_Post $post ): void {
        wp_nonce_field( 'igs_save_album', 'igs_album_nonce' );
        $selected = array_map( 'absint', (array) get_post_meta( $post->ID, '_igs_album_galleries', true ) );
        $galleries = get_posts( array( 'post_type' => 'igs_gallery', 'post_status' => array( 'publish','draft','private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        echo '<div class="igs-album-select">';
        foreach ( $galleries as $gallery ) {
            if ( ! current_user_can( 'read_post', $gallery->ID ) || ( 'publish' !== $gallery->post_status && ! current_user_can( 'edit_post', $gallery->ID ) ) ) continue;
            printf( '<label><input type="checkbox" name="igs_album_galleries[]" value="%d" %s> %s</label>', esc_attr( $gallery->ID ), checked( in_array( $gallery->ID, $selected, true ), true, false ), esc_html( $gallery->post_title ) );
        }
        echo '</div>';
    }

    public function analytics_box( WP_Post $post ): void {
        if ( ! current_user_can( 'view_igs_analytics' ) || ! current_user_can( 'edit_post', $post->ID ) ) return;
        $labels = array(
            'views' => __( 'Gallery views', 'immersive-gallery-studio' ),
            'opens' => __( 'Viewer opens', 'immersive-gallery-studio' ),
            'downloads' => __( 'Download clicks', 'immersive-gallery-studio' ),
            'interactions' => __( 'Navigation and gestures', 'immersive-gallery-studio' ),
        );
        echo '<dl>';
        foreach ( $labels as $key => $label ) {
            echo '<dt>' . esc_html( $label ) . '</dt><dd><strong>' . esc_html( number_format_i18n( (int) get_post_meta( $post->ID, '_igs_' . $key, true ) ) ) . '</strong></dd>';
        }
        echo '</dl><p class="description">' . esc_html__( 'Aggregate events, including repeat visits. Preview activity is excluded.', 'immersive-gallery-studio' ) . '</p>';
    }

    public function save_album( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['igs_album_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['igs_album_nonce'] ) ), 'igs_save_album' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( 'igs_album' !== $post->post_type || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;
        $ids = isset( $_POST['igs_album_galleries'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['igs_album_galleries'] ) ) ) ) ) : array();
        $ids = array_values( array_filter( $ids, static fn( int $id ): bool => 'igs_gallery' === get_post_type( $id ) && current_user_can( 'read_post', $id ) ) );
        update_post_meta( $post_id, '_igs_album_galleries', $ids );
    }

    public function save_gallery( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['igs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['igs_nonce'] ) ), 'igs_save_gallery' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( 'igs_gallery' !== $post->post_type || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;

        $ids = isset( $_POST['igs_media_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['igs_media_ids'] ) ) ) ) ) : array();
        $ids = $this->image_ids( $ids );
        update_post_meta( $post_id, '_igs_media_ids', $ids );

        $raw = isset( $_POST['igs_settings'] ) ? (array) wp_unslash( $_POST['igs_settings'] ) : array();
        update_post_meta( $post_id, '_igs_settings', $this->sanitize_settings( $raw ) );
        $access = isset( $_POST['igs_access'] ) ? sanitize_key( wp_unslash( $_POST['igs_access'] ) ) : 'public';
        update_post_meta( $post_id, '_igs_access', in_array( $access, array( 'public', 'password', 'logged_in' ), true ) ? $access : 'public' );
        $expires = isset( $_POST['igs_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['igs_expires'] ) ) : '';
        $date = $expires ? DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $expires, wp_timezone() ) : false;
        $expires = $date && $date->format( 'Y-m-d\TH:i' ) === $expires ? $expires : '';
        update_post_meta( $post_id, '_igs_expires', $expires );
        if ( ! empty( $_POST['igs_clear_password'] ) ) {
            delete_post_meta( $post_id, '_igs_password_hash' );
        } elseif ( isset( $_POST['igs_gallery_password'] ) ) {
            $password = is_string( $_POST['igs_gallery_password'] ) ? wp_unslash( $_POST['igs_gallery_password'] ) : '';
            if ( '' !== $password ) update_post_meta( $post_id, '_igs_password_hash', wp_hash_password( $password ) );
        }
    }

    private function sanitize_settings( array $raw ): array {
        return IGS_Settings::sanitize( $raw, $this->templates() );
    }

    public function admin_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'igs_gallery', 'igs_album' ), true ) || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
        wp_enqueue_media();
        wp_enqueue_style( 'igs-admin', IGS_URL . 'assets/css/admin.css', array(), IGS_VERSION );
        wp_enqueue_script( 'igs-admin', IGS_URL . 'assets/js/admin.js', array( 'jquery' ), IGS_VERSION, true );
        wp_localize_script( 'igs-admin', 'IGSAdmin', array( 'barcodeNonce' => wp_create_nonce( 'igs_barcode' ), 'previewNonce' => wp_create_nonce( 'igs_preview' ), 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'strings' => $this->ui_strings() ) );
    }

    public function register_front_assets(): void {
        if ( wp_script_is( 'igs-front', 'registered' ) ) return;
        wp_register_style( 'igs-front', IGS_URL . 'assets/css/frontend.css', array(), IGS_VERSION );
        wp_register_script( 'igs-front', IGS_URL . 'assets/js/frontend.js', array(), IGS_VERSION, true );
        wp_localize_script( 'igs-front', 'IGSFront', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'igs_track' ), 'strings' => $this->ui_strings() ) );
    }

    private function templates(): array {
        return $this->template_registry()->labels();
    }

    public function append_single_gallery( string $content ): string {
        if ( is_singular( 'igs_gallery' ) && in_the_loop() && is_main_query() ) return $content . $this->render_gallery( get_the_ID() );
        if ( is_singular( 'igs_album' ) && in_the_loop() && is_main_query() ) return $content . $this->render_album( get_the_ID() );
        return $content;
    }

    public function shortcode_gallery( array|string $atts ): string {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'immersive_gallery' );
        return $this->render_gallery( absint( $atts['id'] ) );
    }

    public function shortcode_album( array|string $atts ): string {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'immersive_album' );
        return $this->render_album( absint( $atts['id'] ) );
    }

    public function process_gallery_unlock(): void {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || empty( $_POST['igs_unlock_gallery'] ) ) return;
        $id = isset( $_POST['igs_gallery_id'] ) ? absint( $_POST['igs_gallery_id'] ) : 0;
        $nonce = isset( $_POST['igs_unlock_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['igs_unlock_nonce'] ) ) : '';
        $password = isset( $_POST['igs_gallery_password'] ) && is_string( $_POST['igs_gallery_password'] ) ? wp_unslash( $_POST['igs_gallery_password'] ) : '';
        if ( ! $this->published_access( $id, 'igs_gallery' ) || ! $this->can_view( $id ) || 'password' !== get_post_meta( $id, '_igs_access', true ) || ! wp_verify_nonce( $nonce, 'igs_unlock_' . $id ) ) return;
        $hash = (string) get_post_meta( $id, '_igs_password_hash', true );
        $target = wp_get_referer() ?: get_permalink( $id );
        $remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
        $rate_key = 'igs_pw_' . md5( wp_salt( 'nonce' ) . '|' . $id . '|' . $remote );
        if ( ! $this->password_attempt_allowed( $rate_key ) ) { wp_safe_redirect( add_query_arg( 'igs_error', 'rate', $target ) ); exit; }
        if ( $hash && wp_check_password( $password, $hash ) ) {
            $expires = time() + DAY_IN_SECONDS;
            $token = $expires . '.' . hash_hmac( 'sha256', $id . '|' . $hash . '|' . $expires, wp_salt( 'auth' ) );
            delete_transient( $rate_key );
            setcookie( 'igs_access_' . $id, $token, array( 'expires' => $expires, 'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', 'domain' => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
            wp_safe_redirect( remove_query_arg( 'igs_error', $target ) );
            exit;
        }
        wp_safe_redirect( add_query_arg( 'igs_error', '1', $target ) );
        exit;
    }

    private function password_attempt_allowed( string $key ): bool {
        global $wpdb;
        $lock = 'igs_pw_' . md5( $wpdb->prefix . $key );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock ) ) ) return false;
        try {
            $attempts = (int) get_transient( $key );
            if ( $attempts >= 8 ) return false;
            set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
            return true;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    private function has_password_access( int $id ): bool {
        $hash = (string) get_post_meta( $id, '_igs_password_hash', true );
        if ( ! $hash ) return false;
        $cookie = $_COOKIE[ 'igs_access_' . $id ] ?? '';
        if ( ! is_string( $cookie ) || ! preg_match( '/^(\d{10})\.([a-f0-9]{64})$/D', $cookie, $parts ) ) return false;
        $expires = (int) $parts[1];
        if ( $expires <= time() || $expires > time() + DAY_IN_SECONDS ) return false;
        $expected = hash_hmac( 'sha256', $id . '|' . $hash . '|' . $expires, wp_salt( 'auth' ) );
        return hash_equals( $expected, $parts[2] );
    }

    private function password_form( int $id ): string {
        $error_code = isset( $_GET['igs_error'] ) ? sanitize_key( wp_unslash( $_GET['igs_error'] ) ) : '';
        $error = '1' === $error_code;
        $rate_limited = 'rate' === $error_code;
        ob_start(); ?>
        <div class="igs-password"><form method="post">
            <h3><?php esc_html_e( 'Protected gallery', 'immersive-gallery-studio' ); ?></h3>
            <p><?php esc_html_e( 'Enter the gallery password to continue.', 'immersive-gallery-studio' ); ?></p>
            <?php if ( $error ) : ?><p role="alert"><strong><?php esc_html_e( 'Incorrect password.', 'immersive-gallery-studio' ); ?></strong></p><?php endif; ?>
            <?php if ( $rate_limited ) : ?><p role="alert"><strong><?php esc_html_e( 'Too many attempts. Try again later.', 'immersive-gallery-studio' ); ?></strong></p><?php endif; ?>
            <input type="hidden" name="igs_unlock_gallery" value="1"><input type="hidden" name="igs_gallery_id" value="<?php echo esc_attr( $id ); ?>">
            <input type="hidden" name="igs_unlock_nonce" value="<?php echo esc_attr( wp_create_nonce( 'igs_unlock_' . $id ) ); ?>">
            <label><span class="screen-reader-text"><?php esc_html_e( 'Gallery password', 'immersive-gallery-studio' ); ?></span><input type="password" name="igs_gallery_password" required autocomplete="current-password"></label>
            <button type="submit"><?php esc_html_e( 'Open gallery', 'immersive-gallery-studio' ); ?></button>
        </form></div>
        <?php return (string) ob_get_clean();
    }

    private function can_view( int $id ): bool {
        $expires = get_post_meta( $id, '_igs_expires', true );
        if ( $expires ) {
            if ( ! is_string( $expires ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $expires ) ) return false;
            $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $expires, wp_timezone() );
            if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $expires || time() >= $date->getTimestamp() ) return false;
        }
        $access = get_post_meta( $id, '_igs_access', true ) ?: 'public';
        if ( ! in_array( $access, array( 'public', 'password', 'logged_in' ), true ) ) return false;
        if ( 'logged_in' === $access && ! is_user_logged_in() ) return false;
        return true;
    }

    private function image_ids( array $ids ): array {
        $ids = array_values( array_unique( array_filter( array_map( static fn( $id ): int => is_scalar( $id ) ? absint( $id ) : 0, $ids ) ) ) );
        if ( ! $ids ) return array();
        // Prime attachment and metadata caches before rendering image derivatives.
        $attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post__in' => $ids, 'posts_per_page' => count( $ids ), 'orderby' => 'post__in', 'update_post_term_cache' => false ) );
        return array_values( array_map( static fn( WP_Post $post ): int => $post->ID, array_filter( $attachments, static fn( WP_Post $post ): bool => wp_attachment_is_image( $post ) ) ) );
    }

    private function published_access( int $id, string $type ): bool {
        $post = get_post( $id );
        return $post && $post->post_type === $type && ( 'publish' === $post->post_status || ( 'private' === $post->post_status && current_user_can( 'read_post', $id ) ) );
    }

    private function content_access( int $id ): bool {
        return $this->can_view( $id ) && ! post_password_required( $id ) &&
            ( 'password' !== get_post_meta( $id, '_igs_access', true ) || $this->has_password_access( $id ) );
    }

    public function protect_content( string $content ): string {
        if ( 'igs_gallery' === get_post_type() && ! $this->content_access( get_the_ID() ) ) return '';
        return $content;
    }

    public function protect_thumbnail( string $html, int $post_id ): string {
        return 'igs_gallery' === get_post_type( $post_id ) && ! $this->content_access( $post_id ) ? '' : $html;
    }

    private function ui_strings(): array {
        return array(
            'viewer' => __( 'Image viewer', 'immersive-gallery-studio' ),
            'download' => __( 'Download', 'immersive-gallery-studio' ),
            'close' => __( 'Close', 'immersive-gallery-studio' ),
            'play' => __( 'Play', 'immersive-gallery-studio' ),
            'pause' => __( 'Pause', 'immersive-gallery-studio' ),
            'imageError' => __( 'This image could not be loaded.', 'immersive-gallery-studio' ),
            'noResults' => __( 'No matching images.', 'immersive-gallery-studio' ),
            'addImages' => __( 'Add images to start building your gallery.', 'immersive-gallery-studio' ),
            'selectImages' => __( 'Select gallery images', 'immersive-gallery-studio' ),
            'addToGallery' => __( 'Add to gallery', 'immersive-gallery-studio' ),
            'remove' => __( 'Remove image', 'immersive-gallery-studio' ),
            'earlier' => __( 'Move image earlier', 'immersive-gallery-studio' ),
            'later' => __( 'Move image later', 'immersive-gallery-studio' ),
            'preview' => __( 'Gallery preview', 'immersive-gallery-studio' ),
            'previewError' => __( 'Preview could not load. Change a setting to retry.', 'immersive-gallery-studio' ),
            'barcode' => __( 'Gallery barcode', 'immersive-gallery-studio' ),
            'popupError' => __( 'Allow popups to print the barcode.', 'immersive-gallery-studio' ),
        );
    }

    public function protect_excerpt( string $excerpt, WP_Post $post ): string {
        return 'igs_gallery' === $post->post_type && ! $this->content_access( $post->ID ) ? '' : $excerpt;
    }

    public function protect_rest_content( WP_REST_Response $response, WP_Post $post, WP_REST_Request $request ): WP_REST_Response {
        if ( 'edit' !== $request->get_param( 'context' ) && ! $this->content_access( $post->ID ) ) {
            $data = $response->get_data();
            $data['content'] = array( 'rendered' => '', 'protected' => true );
            $data['excerpt'] = array( 'rendered' => '', 'protected' => true );
            $data['featured_media'] = 0;
            $response->set_data( $data );
            $response->remove_link( 'https://api.w.org/featuredmedia' );
        }
        $response->header( 'Cache-Control', 'private, no-store' );
        return $response;
    }

    public function protect_response_cache(): void {
        global $wp_query;
        foreach ( (array) ( $wp_query->posts ?? array() ) as $post ) {
            if ( ! $post instanceof WP_Post ) continue;
            if ( in_array( $post->post_type, array( 'igs_gallery', 'igs_album' ), true ) || has_shortcode( $post->post_content, 'immersive_gallery' ) || has_shortcode( $post->post_content, 'immersive_album' ) ) {
                $this->no_cache();
                return;
            }
        }
    }

    private function no_cache(): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
        }
    }

    public function print_late_styles(): void {
        global $wp_styles;
        if ( ! $wp_styles ) return;
        $handles = array_filter( $wp_styles->queue, static fn( string $handle ): bool => 'igs-front' === $handle || str_starts_with( $handle, 'igs-template-' ) );
        if ( $handles ) wp_print_styles( $handles );
    }

    public function preview_ajax(): void {
        check_ajax_referer( 'igs_preview', 'nonce' );
        $id = isset( $_POST['gallery'] ) && is_scalar( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
        if ( 'igs_gallery' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_send_json_error( null, 403 );
        $preview = array(
            'media' => $this->image_ids( (array) ( $_POST['media'] ?? array() ) ),
            'settings' => $this->sanitize_settings( (array) wp_unslash( $_POST['settings'] ?? array() ) ),
        );
        $template = $this->template_registry()->get( $preview['settings']['template'] );
        wp_send_json_success( array(
            'html' => $this->render_gallery( $id, $preview ),
            'css' => array( IGS_URL . 'assets/css/frontend.css', $template['url'] . $template['css'] ),
            'js' => array_values( array_filter( array( IGS_URL . 'assets/js/frontend.js', empty( $template['js'] ) ? '' : $template['url'] . $template['js'] ) ) ),
        ) );
    }

    public function render_gallery( int $id, ?array $preview = null ): string {
        if ( ! $preview ) $this->no_cache();
        $this->register_front_assets();
        wp_enqueue_style( 'igs-front' );
        $post = get_post( $id );
        if ( $preview && ( ! $post || 'igs_gallery' !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) ) return '';
        if ( ! $preview && ! $this->published_access( $id, 'igs_gallery' ) ) return '';
        if ( ! $preview && post_password_required( $post ) ) return get_the_password_form( $post );
        if ( ! $preview && ! $this->can_view( $id ) ) return '<div class="igs-notice">' . esc_html__( 'This gallery is unavailable or has expired.', 'immersive-gallery-studio' ) . '</div>';
        $access = get_post_meta( $id, '_igs_access', true ) ?: 'public';
        if ( ! $preview && 'password' === $access && ! $this->has_password_access( $id ) ) return $this->password_form( $id );

        $ids = $this->image_ids( $preview['media'] ?? (array) get_post_meta( $id, '_igs_media_ids', true ) );
        if ( ! $ids ) return '<div class="igs-notice">' . esc_html__( 'This gallery has no media yet.', 'immersive-gallery-studio' ) . '</div>';
        $settings = $this->sanitize_settings( $preview['settings'] ?? (array) get_post_meta( $id, '_igs_settings', true ) );
        if ( ! empty( $settings['randomize'] ) ) shuffle( $ids );
        $items = array();
        foreach ( $ids as $attachment_id ) {
            $full = wp_get_attachment_image_url( $attachment_id, 'full' );
            $large = wp_get_attachment_image_url( $attachment_id, 'large' );
            if ( ! $full || ! $large ) continue;
            $metadata = wp_get_attachment_metadata( $attachment_id );
            $items[] = array(
                'srcset' => wp_get_attachment_image_srcset( $attachment_id, 'large' ) ?: '',
                'id' => $attachment_id, 'src' => $large, 'full' => $full,
                'thumb' => wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: $large,
                'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                'title' => get_the_title( $attachment_id ), 'caption' => wp_get_attachment_caption( $attachment_id ),
                'width' => (int) ( $metadata['width'] ?? 0 ),
                'height' => (int) ( $metadata['height'] ?? 0 ),
            );
        }
        if ( ! $items ) return '';
        wp_enqueue_style( 'igs-front' );
        wp_enqueue_script( 'igs-front' );
        $this->template_registry()->enqueue( (string) $settings['template'] );
        $uid = 'igs-' . $id . '-' . wp_unique_id();
        $this->rendered_gallery_ids[] = $id;
        $style = sprintf( '--igs-gap:%dpx;--igs-h:%dpx;--igs-radius:%dpx;--igs-card:%dpx;--igs-card-h:%dpx;--igs-bg:%s;--igs-accent:%s;--igs-round:%dpx;--igs-cols:%d;--igs-page-w:%dpx;--igs-page-h:%dpx;--igs-cols-t:%d;--igs-cols-m:%d;',
            $settings['gap'], $settings['thumb_height'], $settings['radius'], $settings['card_width'], $settings['card_height'], $settings['background'], $settings['accent'], $settings['border_radius'], $settings['columns_desktop'], $settings['page_width'], $settings['page_height'], $settings['columns_tablet'], $settings['columns_mobile'] );
        $classes = 'igs-gallery igs-template-' . sanitize_html_class( $settings['template'] );
        ob_start();
        ?>
        <section id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( $classes ); ?>" style="<?php echo esc_attr( $style ); ?>" data-gallery-id="<?php echo esc_attr( $id ); ?>" data-template="<?php echo esc_attr( $settings['template'] ); ?>" data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>">
            <?php if ( ! empty( $settings['show_search'] ) ) : ?><div class="igs-toolbar"><label><span class="screen-reader-text"><?php esc_html_e( 'Search gallery', 'immersive-gallery-studio' ); ?></span><input class="igs-search" type="search" placeholder="<?php esc_attr_e( 'Search gallery…', 'immersive-gallery-studio' ); ?>"></label></div><?php endif; ?>
            <?php
            echo $this->template_registry()->render(
                (string) $settings['template'],
                array(
                    'gallery_id' => $id,
                    'items' => $items,
                    'settings' => $settings,
                    'label' => get_the_title( $id ),
                )
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <?php if ( ! empty( $settings['show_counter'] ) ) : ?><div class="igs-counter" aria-live="polite">1 / <?php echo count( $items ); ?></div><?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function render_album( int $id ): string {
        $this->no_cache();
        if ( ! $this->published_access( $id, 'igs_album' ) ) return '';
        if ( post_password_required( $id ) ) return get_the_password_form( $id );
        $this->register_front_assets();
        wp_enqueue_style( 'igs-front' );
        $galleries = array_map( 'absint', (array) get_post_meta( $id, '_igs_album_galleries', true ) );
        if ( ! $galleries ) return '<div class="igs-notice">' . esc_html__( 'This album is empty.', 'immersive-gallery-studio' ) . '</div>';
        $out = '<div class="igs-album">';
        foreach ( $galleries as $gallery_id ) {
            if ( ! $this->published_access( $gallery_id, 'igs_gallery' ) || ! $this->can_view( $gallery_id ) ) continue;
            $cover = get_the_post_thumbnail_url( $gallery_id, 'large' );
            if ( ! $cover ) {
                $media = (array) get_post_meta( $gallery_id, '_igs_media_ids', true );
                $cover = $media ? wp_get_attachment_image_url( (int) reset( $media ), 'large' ) : '';
            }
            if ( ! $this->content_access( $gallery_id ) ) $cover = '';
            $out .= '<a class="igs-album-card" href="' . esc_url( get_permalink( $gallery_id ) ) . '">';
            if ( $cover ) $out .= '<img loading="lazy" src="' . esc_url( $cover ) . '" alt="">';
            $out .= '<strong>' . esc_html( get_the_title( $gallery_id ) ) . '</strong></a>';
        }
        return $out . '</div>';
    }

    public function gallery_columns( array $columns ): array {
        $columns['igs_template'] = __( 'Template', 'immersive-gallery-studio' );
        $columns['igs_images'] = __( 'Images', 'immersive-gallery-studio' );
        $columns['igs_access'] = __( 'Access', 'immersive-gallery-studio' );
        if ( current_user_can( 'view_igs_analytics' ) ) $columns['igs_views'] = __( 'Views', 'immersive-gallery-studio' );
        $columns['igs_shortcode'] = __( 'Shortcode', 'immersive-gallery-studio' );
        return $columns;
    }

    public function gallery_column_content( string $column, int $post_id ): void {
        if ( 'igs_template' === $column ) { $s = $this->sanitize_settings( (array) get_post_meta( $post_id, '_igs_settings', true ) ); echo esc_html( $this->templates()[ $s['template'] ] ?? $s['template'] ); }
        if ( 'igs_images' === $column ) echo esc_html( count( (array) get_post_meta( $post_id, '_igs_media_ids', true ) ) );
        if ( 'igs_access' === $column ) echo esc_html( ucfirst( str_replace( '_', ' ', get_post_meta( $post_id, '_igs_access', true ) ?: 'public' ) ) );
        if ( 'igs_views' === $column && current_user_can( 'view_igs_analytics' ) ) echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_igs_views', true ) ) );
        if ( 'igs_shortcode' === $column ) echo '<code>[immersive_gallery id=&quot;' . esc_html( $post_id ) . '&quot;]</code>';
    }

    public function register_rest(): void {
        register_rest_route( 'igs/v1', '/gallery/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_gallery' ),
            'permission_callback' => function( WP_REST_Request $r ) { $id = absint( $r['id'] ); return 'igs_gallery' === get_post_type( $id ) && current_user_can( 'edit_post', $id ); },
            'args' => array( 'id' => array( 'validate_callback' => static fn( $value ) => is_scalar( $value ) && ctype_digit( (string) $value ) && (int) $value > 0 ) ),
        ) );
    }

    public function rest_gallery( WP_REST_Request $request ): WP_REST_Response {
        $id = absint( $request['id'] );
        return rest_ensure_response( array( 'id' => $id, 'media' => array_map( 'absint', (array) get_post_meta( $id, '_igs_media_ids', true ) ), 'settings' => $this->sanitize_settings( (array) get_post_meta( $id, '_igs_settings', true ) ) ) );
    }

    public function track_ajax(): void {
        check_ajax_referer( 'igs_track', 'nonce' );
        $id = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
        $event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : 'view';
        if ( ! $this->published_access( $id, 'igs_gallery' ) || ! $this->content_access( $id ) || ! in_array( $event, array( 'view','open','download','interaction' ), true ) ) wp_send_json_error( null, 403 );
        $key = '_igs_' . $event . 's'; if ( 'view' === $event ) $key = '_igs_views';
        // Serialize the increment so simultaneous requests cannot lose events.
        global $wpdb;
        $lock = 'igs_count_' . md5( DB_NAME . '|' . $wpdb->prefix . '|' . $id );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock ) ) ) wp_send_json_error( null, 503 );
        try {
            wp_cache_delete( $id, 'post_meta' );
            update_post_meta( $id, $key, (int) get_post_meta( $id, $key, true ) + 1 );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
        wp_send_json_success();
    }

    public function barcode_ajax(): void {
        check_ajax_referer( 'igs_barcode', 'nonce' );
        if ( ! current_user_can( 'edit_igs_galleries' ) ) wp_die( 'Forbidden', '', array( 'response' => 403 ) );
        $url = isset( $_GET['url'] ) && is_string( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
        if ( ! $url || strlen( $url ) > 2048 ) wp_die( 'Invalid', '', array( 'response' => 400 ) );
        header( 'Content-Type: image/svg+xml; charset=UTF-8' );
        echo $this->code128_svg( $url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated SVG is internally escaped.
        exit;
    }

    private function code128_svg( string $text ): string {
        // Code 128 B pattern table (0-106). Each digit is a bar/space module width.
        $patterns = array('212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112');
        $text = preg_replace_callback( '/[^\x20-\x7E]/', static fn( array $match ): string => rawurlencode( $match[0] ), $text );
        $codes = array( 104 ); $checksum = 104; $pos = 1;
        foreach ( str_split( $text ) as $char ) {
            $ord = ord( $char ); if ( $ord < 32 || $ord > 126 ) $ord = 63;
            $code = $ord - 32; $codes[] = $code; $checksum += $code * $pos; $pos++;
        }
        $codes[] = $checksum % 103; $codes[] = 106;
        $x = 20; $height = 70; $rects = '';
        foreach ( $codes as $code ) {
            $p = $patterns[ $code ]; $bar = true;
            foreach ( str_split( $p ) as $w ) { $width = (int) $w * 2; if ( $bar ) $rects .= '<rect x="' . $x . '" y="8" width="' . $width . '" height="' . $height . '"/>'; $x += $width; $bar = ! $bar; }
        }
        $safe = esc_html( $text ); $width = $x + 20;
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="100" viewBox="0 0 ' . $width . ' 100" role="img"><rect width="100%" height="100%" fill="white"/><g fill="black">' . $rects . '</g><text x="50%" y="94" font-size="10" text-anchor="middle" font-family="monospace">' . $safe . '</text></svg>';
    }


}
