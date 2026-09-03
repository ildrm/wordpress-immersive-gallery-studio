<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'masonry',
    'order' => 20,
    'label' => __( 'Masonry', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => '',
);
