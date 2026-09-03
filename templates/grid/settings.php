<?php
defined( 'ABSPATH' ) || exit;
return array(
    'columns_desktop' => array( 'type' => 'integer', 'min' => 1, 'max' => 8, 'default' => 4 ),
    'columns_tablet' => array( 'type' => 'integer', 'min' => 1, 'max' => 6, 'default' => 3 ),
    'columns_mobile' => array( 'type' => 'integer', 'min' => 1, 'max' => 4, 'default' => 2 ),
    'gap' => array( 'type' => 'integer', 'min' => 0, 'max' => 48, 'default' => 14 ),
    'thumb_height' => array( 'type' => 'integer', 'min' => 100, 'max' => 600, 'default' => 240 ),
);
