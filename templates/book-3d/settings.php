<?php
defined( 'ABSPATH' ) || exit;
return array(
    'page_width' => array( 'type' => 'integer', 'min' => 220, 'max' => 620, 'default' => 360 ),
    'page_height' => array( 'type' => 'integer', 'min' => 280, 'max' => 760, 'default' => 480 ),
    'page_stiffness' => array( 'type' => 'number', 'min' => 0.1, 'max' => 1.0, 'default' => 0.65 ),
);
