<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'grid',
    'order' => 10,
    'label' => __( 'Smart Grid', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => '',
);
