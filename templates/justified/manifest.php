<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'justified',
    'order' => 30,
    'label' => __( 'Justified Mosaic', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => 'frontend.js',
);
