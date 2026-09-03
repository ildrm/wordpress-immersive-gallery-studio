<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'museum3d',
    'order' => 100,
    'label' => __( '3D Exhibition', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => 'frontend.js',
);
