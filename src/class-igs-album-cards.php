<?php

defined( 'ABSPATH' ) || exit;

final class IGS_Album_Cards {
    public static function render( array $galleries, callable $visible, callable $allow_cover ): string {
        $out = '<div class="igs-album">';
        foreach ( array_chunk( $galleries, 100 ) as $batch ) {
            _prime_post_caches( $batch, false, true );
            $cards = array();
            $cover_ids = array();
            foreach ( $batch as $gallery_id ) {
                if ( ! $visible( $gallery_id ) ) continue;
                $allowed = $allow_cover( $gallery_id );
                if ( $allowed ) {
                    $thumbnail = (int) get_post_meta( $gallery_id, '_thumbnail_id', true );
                    $cover_ids[] = $thumbnail ?: self::first_media_id( $gallery_id );
                }
                $cards[] = array( $gallery_id, $allowed );
            }
            $cover_ids = array_unique( array_filter( $cover_ids ) );
            if ( $cover_ids ) _prime_post_caches( $cover_ids, false, true );
            foreach ( $cards as list( $gallery_id, $allowed ) ) {
                $cover = $allowed ? self::cover( $gallery_id ) : '';
                $out .= '<a class="igs-album-card" href="' . esc_url( get_permalink( $gallery_id ) ) . '">';
                if ( $cover ) $out .= '<img loading="lazy" src="' . esc_url( $cover ) . '" alt="">';
                $out .= '<strong>' . esc_html( get_the_title( $gallery_id ) ) . '</strong></a>';
            }
        }
        return $out . '</div>';
    }

    private static function first_media_id( int $gallery_id ): int {
        $media = (array) get_post_meta( $gallery_id, '_igs_media_ids', true );
        return (int) reset( $media );
    }

    private static function cover( int $gallery_id ): string {
        $cover = get_the_post_thumbnail_url( $gallery_id, 'large' );
        if ( $cover ) return $cover;
        $fallback = self::first_media_id( $gallery_id );
        return $fallback ? (string) wp_get_attachment_image_url( $fallback, 'large' ) : '';
    }
}
