<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'ring3d',
    'order' => 80,
    'label' => __( '3D Circular Ring', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => 'frontend.js',
);
