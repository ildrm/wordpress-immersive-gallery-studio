<?php
defined( 'ABSPATH' ) || exit;
return array(
    'gap' => array( 'type' => 'integer', 'min' => 0, 'max' => 48, 'default' => 14 ),
    'autoplay' => array( 'type' => 'boolean', 'default' => false ),
    'autoplay_speed' => array( 'type' => 'integer', 'min' => 1500, 'max' => 15000, 'default' => 4500 ),
);
