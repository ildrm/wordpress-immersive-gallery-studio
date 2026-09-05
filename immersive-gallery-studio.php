<?php
/**
 * Plugin Name: Immersive Gallery Studio
 * Plugin URI:  https://github.com/ildrm/wordpress-immersive-gallery-studio
 * Description: Advanced WordPress image galleries with ten responsive 2D/3D presentation templates, protected galleries, barcodes, albums, analytics, and a visual gallery builder.
 * Version: 1.0.2
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author:     Shahin Ilderemi
 * Author URI: https://ildrm.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: immersive-gallery-studio
 */

defined( 'ABSPATH' ) || exit;

define( 'IGS_VERSION', '1.0.2' );
define( 'IGS_FILE', __FILE__ );
define( 'IGS_DIR', plugin_dir_path( __FILE__ ) );
define( 'IGS_URL', plugin_dir_url( __FILE__ ) );

require_once IGS_DIR . 'src/class-igs-settings.php';
require_once IGS_DIR . 'src/class-igs-template-view.php';
require_once IGS_DIR . 'src/class-igs-template-registry.php';
require_once IGS_DIR . 'src/class-igs-plugin.php';

register_activation_hook( __FILE__, array( 'IGS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IGS_Plugin', 'deactivate' ) );

IGS_Plugin::instance()->boot();
