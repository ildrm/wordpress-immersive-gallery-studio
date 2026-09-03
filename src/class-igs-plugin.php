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
        add_filter( 'post_password_expires', array( $this, 'password_expiry' ) );
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
            'capabilities' => $gallery_caps, 'map_meta_cap' => false,
        ) );
        register_post_type( 'igs_album', array(
            'labels' => array( 'name' => __( 'Albums', 'immersive-gallery-studio' ), 'singular_name' => __( 'Album', 'immersive-gallery-studio' ) ),
            'public' => true, 'show_ui' => true, 'show_in_rest' => true, 'show_in_menu' => 'edit.php?post_type=igs_gallery',
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
        add_meta_box( 'igs_album_galleries', __( 'Album Galleries', 'immersive-gallery-studio' ), array( $this, 'album_box' ), 'igs_album', 'normal', 'high' );
    }

    private function defaults(): array {
        return array(
            'template' => 'grid', 'columns_desktop' => 4, 'columns_tablet' => 3, 'columns_mobile' => 2, 'gap' => 14,
            'thumb_height' => 240, 'radius' => 420, 'card_width' => 220, 'card_height' => 150, 'rotation_speed' => 0.006,
            'inertia' => 0.93, 'autoplay' => 0, 'autoplay_speed' => 4500, 'captions' => 1, 'lightbox' => 1,
            'download' => 0, 'randomize' => 0, 'background' => '#0b1020', 'accent' => '#7c3aed', 'border_radius' => 18,
            'page_width' => 360, 'page_height' => 480, 'page_stiffness' => 0.65, 'cinematic_duration' => 6000,
            'reduced_motion_fallback' => 1, 'show_counter' => 1, 'show_search' => 0, 'show_filters' => 0,
        );
    }

    public function builder_box( WP_Post $post ): void {
        wp_nonce_field( 'igs_save_gallery', 'igs_nonce' );
        $ids = array_map( 'absint', (array) get_post_meta( $post->ID, '_igs_media_ids', true ) );
        $settings = wp_parse_args( (array) get_post_meta( $post->ID, '_igs_settings', true ), $this->defaults() );
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
                    <?php $this->range_field( 'columns_desktop', __( 'Desktop columns', 'immersive-gallery-studio' ), $settings, 1, 8, 1 ); ?>
                    <?php $this->range_field( 'columns_tablet', __( 'Tablet columns', 'immersive-gallery-studio' ), $settings, 1, 6, 1 ); ?>
                    <?php $this->range_field( 'columns_mobile', __( 'Mobile columns', 'immersive-gallery-studio' ), $settings, 1, 4, 1 ); ?>
                    <?php $this->range_field( 'gap', __( 'Gap', 'immersive-gallery-studio' ), $settings, 0, 48, 1, 'px' ); ?>
                    <?php $this->range_field( 'thumb_height', __( 'Image height', 'immersive-gallery-studio' ), $settings, 100, 600, 10, 'px' ); ?>
                    <?php $this->range_field( 'border_radius', __( 'Corner radius', 'immersive-gallery-studio' ), $settings, 0, 42, 1, 'px' ); ?>
                    <details><summary><?php esc_html_e( '3D Ring', 'immersive-gallery-studio' ); ?></summary>
                        <?php $this->range_field( 'radius', __( 'Ring radius', 'immersive-gallery-studio' ), $settings, 180, 800, 10 ); ?>
                        <?php $this->range_field( 'card_width', __( 'Card width', 'immersive-gallery-studio' ), $settings, 120, 360, 10 ); ?>
                        <?php $this->range_field( 'rotation_speed', __( 'Drag speed', 'immersive-gallery-studio' ), $settings, 0.001, 0.02, 0.001 ); ?>
                        <?php $this->range_field( 'inertia', __( 'Inertia', 'immersive-gallery-studio' ), $settings, 0.80, 0.99, 0.01 ); ?>
                    </details>
                    <details><summary><?php esc_html_e( '3D Book', 'immersive-gallery-studio' ); ?></summary>
                        <?php $this->range_field( 'page_width', __( 'Page width', 'immersive-gallery-studio' ), $settings, 220, 520, 10 ); ?>
                        <?php $this->range_field( 'page_height', __( 'Page height', 'immersive-gallery-studio' ), $settings, 300, 680, 10 ); ?>
                        <?php $this->range_field( 'page_stiffness', __( 'Page stiffness', 'immersive-gallery-studio' ), $settings, 0.2, 1, 0.05 ); ?>
                    </details>
                    <div class="igs-switches">
                        <?php $this->check_field( 'captions', __( 'Captions', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'lightbox', __( 'Fullscreen viewer', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'autoplay', __( 'Auto play', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'download', __( 'Allow downloads', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'randomize', __( 'Random order', 'immersive-gallery-studio' ), $settings ); ?>
                        <?php $this->check_field( 'show_search', __( 'Gallery search', 'immersive-gallery-studio' ), $settings ); ?>
                    </div>
                    <div class="igs-colors"><label><?php esc_html_e( 'Background', 'immersive-gallery-studio' ); ?><input type="color" name="igs_settings[background]" value="<?php echo esc_attr( $settings['background'] ); ?>"></label><label><?php esc_html_e( 'Accent', 'immersive-gallery-studio' ); ?><input type="color" name="igs_settings[accent]" value="<?php echo esc_attr( $settings['accent'] ); ?>"></label></div>
                </aside>
                <main class="igs-workspace">
                    <div class="igs-preview" id="igs-preview"><div class="igs-preview__empty"><?php esc_html_e( 'Add images to start building your gallery.', 'immersive-gallery-studio' ); ?></div></div>
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
            printf( '<label><input type="checkbox" name="igs_album_galleries[]" value="%d" %s> %s</label>', esc_attr( $gallery->ID ), checked( in_array( $gallery->ID, $selected, true ), true, false ), esc_html( $gallery->post_title ) );
        }
        echo '</div>';
    }

    public function save_album( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['igs_album_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['igs_album_nonce'] ) ), 'igs_save_album' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        $ids = isset( $_POST['igs_album_galleries'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['igs_album_galleries'] ) ) ) ) ) : array();
        $ids = array_values( array_filter( $ids, static fn( int $id ): bool => 'igs_gallery' === get_post_type( $id ) ) );
        update_post_meta( $post_id, '_igs_album_galleries', $ids );
    }

    public function save_gallery( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['igs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['igs_nonce'] ) ), 'igs_save_gallery' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_igs_gallery', $post_id ) ) return;

        $ids = isset( $_POST['igs_media_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['igs_media_ids'] ) ) ) ) ) : array();
        update_post_meta( $post_id, '_igs_media_ids', $ids );

        $raw = isset( $_POST['igs_settings'] ) ? (array) wp_unslash( $_POST['igs_settings'] ) : array();
        update_post_meta( $post_id, '_igs_settings', $this->sanitize_settings( $raw ) );
        $access = isset( $_POST['igs_access'] ) ? sanitize_key( wp_unslash( $_POST['igs_access'] ) ) : 'public';
        update_post_meta( $post_id, '_igs_access', in_array( $access, array( 'public', 'password', 'logged_in' ), true ) ? $access : 'public' );
        $expires = isset( $_POST['igs_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['igs_expires'] ) ) : '';
        update_post_meta( $post_id, '_igs_expires', $expires );
        if ( ! empty( $_POST['igs_clear_password'] ) ) {
            delete_post_meta( $post_id, '_igs_password_hash' );
        } elseif ( isset( $_POST['igs_gallery_password'] ) ) {
            $password = (string) wp_unslash( $_POST['igs_gallery_password'] );
            if ( '' !== $password ) update_post_meta( $post_id, '_igs_password_hash', wp_hash_password( $password ) );
        }
    }

    private function sanitize_settings( array $raw ): array {
        $d = $this->defaults(); $out = $d;
        $out['template'] = isset( $raw['template'] ) && array_key_exists( sanitize_key( $raw['template'] ), $this->templates() ) ? sanitize_key( $raw['template'] ) : $d['template'];
        foreach ( array( 'columns_desktop','columns_tablet','columns_mobile','gap','thumb_height','radius','card_width','card_height','autoplay_speed','border_radius','page_width','page_height','cinematic_duration' ) as $k ) {
            if ( isset( $raw[ $k ] ) ) $out[ $k ] = absint( $raw[ $k ] );
        }
        foreach ( array( 'rotation_speed','inertia','page_stiffness' ) as $k ) {
            if ( isset( $raw[ $k ] ) ) $out[ $k ] = (float) $raw[ $k ];
        }
        foreach ( array( 'autoplay','captions','lightbox','download','randomize','reduced_motion_fallback','show_counter','show_search','show_filters' ) as $k ) {
            $out[ $k ] = empty( $raw[ $k ] ) ? 0 : 1;
        }
        foreach ( array( 'background','accent' ) as $k ) $out[ $k ] = isset( $raw[ $k ] ) ? sanitize_hex_color( $raw[ $k ] ) ?: $d[ $k ] : $d[ $k ];
        return $out;
    }

    public function admin_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'igs_gallery', 'igs_album' ), true ) ) return;
        wp_enqueue_media();
        wp_enqueue_style( 'igs-admin', IGS_URL . 'assets/css/admin.css', array(), IGS_VERSION );
        wp_enqueue_script( 'igs-admin', IGS_URL . 'assets/js/admin.js', array( 'jquery' ), IGS_VERSION, true );
        wp_localize_script( 'igs-admin', 'IGSAdmin', array( 'barcodeNonce' => wp_create_nonce( 'igs_barcode' ), 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
    }

    public function register_front_assets(): void {
        wp_register_style( 'igs-front', IGS_URL . 'assets/css/frontend.css', array(), IGS_VERSION );
        wp_register_script( 'igs-front', IGS_URL . 'assets/js/frontend.js', array(), IGS_VERSION, true );
        wp_localize_script( 'igs-front', 'IGSFront', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'igs_track' ) ) );
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
        $password = isset( $_POST['igs_gallery_password'] ) ? (string) wp_unslash( $_POST['igs_gallery_password'] ) : '';
        if ( ! $id || ! wp_verify_nonce( $nonce, 'igs_unlock_' . $id ) ) return;
        $hash = (string) get_post_meta( $id, '_igs_password_hash', true );
        $target = wp_get_referer() ?: get_permalink( $id );
        $remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
        $rate_key = 'igs_pw_' . md5( wp_salt( 'nonce' ) . '|' . $id . '|' . $remote );
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 8 ) { wp_safe_redirect( add_query_arg( 'igs_error', 'rate', $target ) ); exit; }
        if ( $hash && wp_check_password( $password, $hash ) ) {
            $token = hash_hmac( 'sha256', $id . '|' . $hash, wp_salt( 'auth' ) );
            delete_transient( $rate_key );
            setcookie( 'igs_access_' . $id, $token, array( 'expires' => time() + DAY_IN_SECONDS, 'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', 'domain' => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
            wp_safe_redirect( remove_query_arg( 'igs_error', $target ) );
            exit;
        }
        set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( 'igs_error', '1', $target ) );
        exit;
    }

    private function has_password_access( int $id ): bool {
        $hash = (string) get_post_meta( $id, '_igs_password_hash', true );
        if ( ! $hash ) return false;
        $expected = hash_hmac( 'sha256', $id . '|' . $hash, wp_salt( 'auth' ) );
        $cookie = isset( $_COOKIE[ 'igs_access_' . $id ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ 'igs_access_' . $id ] ) ) : '';
        return $cookie && hash_equals( $expected, $cookie );
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
        if ( $expires && strtotime( $expires ) && time() > strtotime( $expires ) ) return false;
        $access = get_post_meta( $id, '_igs_access', true ) ?: 'public';
        if ( 'logged_in' === $access && ! is_user_logged_in() ) return false;
        return true;
    }

    public function render_gallery( int $id ): string {
        $post = get_post( $id );
        if ( ! $post || 'igs_gallery' !== $post->post_type || ! in_array( $post->post_status, array( 'publish','private' ), true ) ) return '';
        if ( 'private' === $post->post_status && ! current_user_can( 'read_post', $id ) ) return '';
        if ( ! $this->can_view( $id ) ) return '<div class="igs-notice">' . esc_html__( 'This gallery is unavailable or has expired.', 'immersive-gallery-studio' ) . '</div>';
        $access = get_post_meta( $id, '_igs_access', true ) ?: 'public';
        if ( 'password' === $access && ! $this->has_password_access( $id ) ) return $this->password_form( $id );

        $ids = array_map( 'absint', (array) get_post_meta( $id, '_igs_media_ids', true ) );
        if ( ! $ids ) return '<div class="igs-notice">' . esc_html__( 'This gallery has no media yet.', 'immersive-gallery-studio' ) . '</div>';
        $settings = wp_parse_args( (array) get_post_meta( $id, '_igs_settings', true ), $this->defaults() );
        if ( ! empty( $settings['randomize'] ) ) shuffle( $ids );
        $items = array();
        foreach ( $ids as $attachment_id ) {
            $full = wp_get_attachment_image_url( $attachment_id, 'full' );
            $large = wp_get_attachment_image_url( $attachment_id, 'large' );
            if ( ! $full || ! $large ) continue;
            $items[] = array(
                'id' => $attachment_id, 'src' => $large, 'full' => $full,
                'thumb' => wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: $large,
                'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                'title' => get_the_title( $attachment_id ), 'caption' => wp_get_attachment_caption( $attachment_id ),
                'width' => (int) ( wp_get_attachment_metadata( $attachment_id )['width'] ?? 0 ),
                'height' => (int) ( wp_get_attachment_metadata( $attachment_id )['height'] ?? 0 ),
            );
        }
        if ( ! $items ) return '';
        wp_enqueue_style( 'igs-front' );
        wp_enqueue_script( 'igs-front' );
        $this->template_registry()->enqueue( (string) $settings['template'] );
        $uid = 'igs-' . $id . '-' . wp_rand( 1000, 9999 );
        $this->rendered_gallery_ids[] = $id;
        $style = sprintf( '--igs-gap:%dpx;--igs-h:%dpx;--igs-radius:%dpx;--igs-card:%dpx;--igs-bg:%s;--igs-accent:%s;--igs-round:%dpx;--igs-cols:%d;--igs-page-w:%dpx;--igs-page-h:%dpx;--igs-cols-t:%d;--igs-cols-m:%d;',
            $settings['gap'], $settings['thumb_height'], $settings['radius'], $settings['card_width'], $settings['background'], $settings['accent'], $settings['border_radius'], $settings['columns_desktop'], $settings['page_width'], $settings['page_height'], $settings['columns_tablet'], $settings['columns_mobile'] );
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
        $galleries = array_map( 'absint', (array) get_post_meta( $id, '_igs_album_galleries', true ) );
        if ( ! $galleries ) return '<div class="igs-notice">' . esc_html__( 'This album is empty.', 'immersive-gallery-studio' ) . '</div>';
        $out = '<div class="igs-album">';
        foreach ( $galleries as $gallery_id ) {
            if ( 'publish' !== get_post_status( $gallery_id ) ) continue;
            $cover = get_the_post_thumbnail_url( $gallery_id, 'large' );
            if ( ! $cover ) {
                $media = (array) get_post_meta( $gallery_id, '_igs_media_ids', true );
                $cover = $media ? wp_get_attachment_image_url( (int) reset( $media ), 'large' ) : '';
            }
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
        $columns['igs_views'] = __( 'Views', 'immersive-gallery-studio' );
        $columns['igs_shortcode'] = __( 'Shortcode', 'immersive-gallery-studio' );
        return $columns;
    }

    public function gallery_column_content( string $column, int $post_id ): void {
        if ( 'igs_template' === $column ) { $s = wp_parse_args( (array) get_post_meta( $post_id, '_igs_settings', true ), $this->defaults() ); echo esc_html( $this->templates()[ $s['template'] ] ?? $s['template'] ); }
        if ( 'igs_images' === $column ) echo esc_html( count( (array) get_post_meta( $post_id, '_igs_media_ids', true ) ) );
        if ( 'igs_access' === $column ) echo esc_html( ucfirst( str_replace( '_', ' ', get_post_meta( $post_id, '_igs_access', true ) ?: 'public' ) ) );
        if ( 'igs_views' === $column ) echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_igs_views', true ) ) );
        if ( 'igs_shortcode' === $column ) echo '<code>[immersive_gallery id=&quot;' . esc_html( $post_id ) . '&quot;]</code>';
    }

    public function register_rest(): void {
        register_rest_route( 'igs/v1', '/gallery/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_gallery' ),
            'permission_callback' => function( WP_REST_Request $r ) { $id = absint( $r['id'] ); return current_user_can( 'edit_igs_gallery', $id ); },
            'args' => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
        ) );
    }

    public function rest_gallery( WP_REST_Request $request ): WP_REST_Response {
        $id = absint( $request['id'] );
        return rest_ensure_response( array( 'id' => $id, 'media' => array_map( 'absint', (array) get_post_meta( $id, '_igs_media_ids', true ) ), 'settings' => wp_parse_args( (array) get_post_meta( $id, '_igs_settings', true ), $this->defaults() ) ) );
    }

    public function track_ajax(): void {
        check_ajax_referer( 'igs_track', 'nonce' );
        $id = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
        $event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : 'view';
        if ( ! $id || 'igs_gallery' !== get_post_type( $id ) || ! in_array( $event, array( 'view','open','download','interaction' ), true ) ) wp_send_json_error();
        $key = '_igs_' . $event . 's'; if ( 'view' === $event ) $key = '_igs_views';
        update_post_meta( $id, $key, (int) get_post_meta( $id, $key, true ) + 1 );
        wp_send_json_success();
    }

    public function barcode_ajax(): void {
        check_ajax_referer( 'igs_barcode', 'nonce' );
        if ( ! current_user_can( 'edit_igs_galleries' ) ) wp_die( 'Forbidden', 403 );
        $url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
        if ( ! $url ) wp_die( 'Invalid', 400 );
        header( 'Content-Type: image/svg+xml; charset=UTF-8' );
        echo $this->code128_svg( $url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated SVG is internally escaped.
        exit;
    }

    private function code128_svg( string $text ): string {
        // Code 128 B pattern table (0-106). Each digit is a bar/space module width.
        $patterns = array('212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112');
        $codes = array( 104 ); $checksum = 104; $pos = 1;
        foreach ( str_split( $text ) as $char ) {
            $ord = ord( $char ); if ( $ord < 32 || $ord > 126 ) $ord = 63;
            $code = $ord - 32; $codes[] = $code; $checksum += $code * $pos; $pos++;
        }
        $codes[] = $checksum % 103; $codes[] = 106;
        $x = 10; $height = 70; $rects = '';
        foreach ( $codes as $code ) {
            $p = $patterns[ $code ]; $bar = true;
            foreach ( str_split( $p ) as $w ) { $width = (int) $w * 2; if ( $bar ) $rects .= '<rect x="' . $x . '" y="8" width="' . $width . '" height="' . $height . '"/>'; $x += $width; $bar = ! $bar; }
        }
        $safe = esc_html( $text ); $width = $x + 10;
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="100" viewBox="0 0 ' . $width . ' 100" role="img"><rect width="100%" height="100%" fill="white"/><g fill="black">' . $rects . '</g><text x="50%" y="94" font-size="10" text-anchor="middle" font-family="monospace">' . $safe . '</text></svg>';
    }

    public function password_expiry( int $expires ): int { return time() + DAY_IN_SECONDS; }
}
