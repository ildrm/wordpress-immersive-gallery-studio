<?php

defined( 'ABSPATH' ) || exit;

return array(
    'id' => 'story',
    'order' => 40,
    'label' => __( 'Story', 'immersive-gallery-studio' ),
    'renderer' => __DIR__ . '/renderer.php',
    'settings' => __DIR__ . '/settings.php',
    'css' => 'frontend.css',
    'js' => '',
);
