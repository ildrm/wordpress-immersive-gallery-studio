<?php

defined( 'ABSPATH' ) || exit;

final class IGS_Template_View {
    /** @param array<string,mixed> $item */
    public static function item( array $item, int $index, array $settings, string $extra_class = '' ): string {
        $classes = trim( 'igs-item ' . sanitize_html_class( $extra_class ) );
        ob_start();
        ?>
        <figure class="<?php echo esc_attr( $classes ); ?>" tabindex="0"
            data-index="<?php echo esc_attr( $index ); ?>"
            data-full="<?php echo esc_url( $item['full'] ); ?>"
            data-title="<?php echo esc_attr( $item['title'] ); ?>"
            data-caption="<?php echo esc_attr( $item['caption'] ); ?>"
            data-w="<?php echo esc_attr( $item['width'] ); ?>"
            data-h="<?php echo esc_attr( $item['height'] ); ?>">
            <img loading="lazy" decoding="async" src="<?php echo esc_url( $item['src'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" draggable="false">
            <?php if ( ! empty( $settings['captions'] ) && ( $item['title'] || $item['caption'] ) ) : ?>
                <figcaption>
                    <b><?php echo esc_html( $item['title'] ); ?></b>
                    <?php if ( $item['caption'] ) : ?><span><?php echo esc_html( $item['caption'] ); ?></span><?php endif; ?>
                </figcaption>
            <?php endif; ?>
        </figure>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<int,array<string,mixed>> $items */
    public static function stage( array $items, array $settings, string $label ): string {
        ob_start();
        ?>
        <div class="igs-stage" role="region" aria-label="<?php echo esc_attr( $label ); ?>">
            <?php foreach ( $items as $index => $item ) : ?>
                <?php echo self::item( $item, (int) $index, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function nav(): string {
        return '<button class="igs-nav igs-prev" type="button" aria-label="' . esc_attr__( 'Previous', 'immersive-gallery-studio' ) . '">‹</button>'
            . '<button class="igs-nav igs-next" type="button" aria-label="' . esc_attr__( 'Next', 'immersive-gallery-studio' ) . '">›</button>';
    }
}
