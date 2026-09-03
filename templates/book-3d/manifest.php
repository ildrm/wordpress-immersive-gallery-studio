<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'book3d',
    'order' => 90,
    'label' => __( '3D Photo Book', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => 'frontend.js',
);
