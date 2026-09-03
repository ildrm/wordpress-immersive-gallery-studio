<?php

defined( 'ABSPATH' ) || exit;

final class IGS_Template_Registry {
    /** @var array<string,array<string,mixed>> */
    private array $templates = array();

    public function __construct() {
        $this->discover();
    }

    private function discover(): void {
        $root = trailingslashit( IGS_DIR . 'templates' );
        if ( ! is_dir( $root ) ) {
            return;
        }

        $manifests = glob( $root . '*/manifest.php' );
        if ( ! is_array( $manifests ) ) {
            return;
        }

        foreach ( $manifests as $manifest_file ) {
            $manifest = require $manifest_file;
            if ( ! is_array( $manifest ) || empty( $manifest['id'] ) || empty( $manifest['label'] ) || empty( $manifest['renderer'] ) ) {
                continue;
            }

            $id = sanitize_key( (string) $manifest['id'] );
            if ( '' === $id || isset( $this->templates[ $id ] ) ) {
                continue;
            }

            $renderer = wp_normalize_path( (string) $manifest['renderer'] );
            $template_root = wp_normalize_path( trailingslashit( dirname( $manifest_file ) ) );
            if ( ! str_starts_with( $renderer, $template_root ) || ! is_file( $renderer ) ) {
                continue;
            }

            $manifest['id'] = $id;
            $manifest['dir'] = trailingslashit( dirname( $manifest_file ) );
            $manifest['url'] = IGS_URL . 'templates/' . basename( dirname( $manifest_file ) ) . '/';
            $this->templates[ $id ] = $manifest;
        }

        uasort( $this->templates, static function ( array $a, array $b ): int {
            return (int) ( $a['order'] ?? 1000 ) <=> (int) ( $b['order'] ?? 1000 );
        } );
    }

    /** @return array<string,string> */
    public function labels(): array {
        $labels = array();
        foreach ( $this->templates as $id => $template ) {
            $labels[ $id ] = (string) $template['label'];
        }
        return $labels;
    }

    public function exists( string $id ): bool {
        return isset( $this->templates[ sanitize_key( $id ) ] );
    }

    /** @return array<string,mixed>|null */
    public function get( string $id ): ?array {
        $id = sanitize_key( $id );
        return $this->templates[ $id ] ?? null;
    }

    /** @return array<string,mixed> */
    public function settings_schema( string $id ): array {
        $template = $this->get( $id );
        if ( ! $template || empty( $template['settings'] ) || ! is_file( $template['settings'] ) ) {
            return array();
        }
        $schema = require $template['settings'];
        return is_array( $schema ) ? $schema : array();
    }

    public function enqueue( string $id ): void {
        $template = $this->get( $id );
        if ( ! $template ) {
            return;
        }

        $handle = 'igs-template-' . $id;
        if ( ! empty( $template['css'] ) && is_file( $template['dir'] . $template['css'] ) ) {
            wp_enqueue_style( $handle, $template['url'] . $template['css'], array( 'igs-front' ), IGS_VERSION );
        }
        if ( ! empty( $template['js'] ) && is_file( $template['dir'] . $template['js'] ) ) {
            wp_enqueue_script( $handle, $template['url'] . $template['js'], array( 'igs-front' ), IGS_VERSION, true );
        }
    }

    /**
     * @param array<string,mixed> $context Template rendering context.
     */
    public function render( string $id, array $context ): string {
        $template = $this->get( $id );
        if ( ! $template ) {
            return '';
        }

        $renderer = require $template['renderer'];
        if ( ! is_callable( $renderer ) ) {
            return '';
        }

        return (string) $renderer( $context );
    }
}
