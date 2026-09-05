<?php

defined( 'ABSPATH' ) || exit;

return static function ( array $context ): string {
    $items = $context['items'];
    $settings = $context['settings'];
    $label = $context['label'];
    return IGS_Template_View::stage( $items, $settings, $label ) . IGS_Template_View::nav();
};
