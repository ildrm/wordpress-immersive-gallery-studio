<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'cinematic',
    'order' => 70,
    'label' => __( 'Cinematic', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => 'frontend.js',
);
