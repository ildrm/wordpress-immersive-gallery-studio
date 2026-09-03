<?php
defined( 'ABSPATH' ) || exit;
return array(
    'radius' => array( 'type' => 'integer', 'min' => 180, 'max' => 800, 'default' => 420 ),
    'card_width' => array( 'type' => 'integer', 'min' => 120, 'max' => 360, 'default' => 220 ),
    'rotation_speed' => array( 'type' => 'number', 'min' => 0.001, 'max' => 0.02, 'default' => 0.006 ),
    'inertia' => array( 'type' => 'number', 'min' => 0.80, 'max' => 0.99, 'default' => 0.93 ),
);
