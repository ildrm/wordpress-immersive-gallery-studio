<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'collage',
    'order' => 60,
    'label' => __( 'Polaroid Collage', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => '',
);
