<?php
defined( 'ABSPATH' ) || exit;
return array(
    'autoplay' => array( 'type' => 'boolean', 'default' => true ),
    'cinematic_duration' => array( 'type' => 'integer', 'min' => 2500, 'max' => 20000, 'default' => 6000 ),
    'captions' => array( 'type' => 'boolean', 'default' => true ),
);
