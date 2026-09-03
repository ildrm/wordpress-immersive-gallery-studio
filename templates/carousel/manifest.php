<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'carousel',
    'order' => 50,
    'label' => __( 'Carousel', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => 'frontend.js',
);
