<?php
/**
 * Plugin Name: GodamBunny
 * Plugin URI: 
 * Description: Godam WP UI with Bunny Backend
 * Version: 1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Text Domain: godam
 * Author: rtCamp
 * Author URI: 
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package GoDAM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Basis-Konstanten des Plugins.
 */
if ( ! defined( 'RTGODAM_PATH' ) ) {
    // Server-Pfad zum Plugin-Verzeichnis.
    define( 'RTGODAM_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RTGODAM_URL' ) ) {
    // URL zum Plugin-Verzeichnis.
    define( 'RTGODAM_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'RTGODAM_BASE_NAME' ) ) {
    // Basisname der Plugin-Datei.
    define( 'RTGODAM_BASE_NAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'RTGODAM_VERSION' ) ) {
    // Plugin-Version.
    define( 'RTGODAM_VERSION', '1.3.5' );
}
if ( ! defined( 'RTGODAM_NO_MAIL' ) && defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
    define( 'RTGODAM_NO_MAIL', true );
}

/**
 * Ursprüngliche GoDAM-Endpunkte (dürfen stehen bleiben; werden nicht mehr genutzt,
 * da der Transcoder unten NICHT mehr geladen/instanziiert wird).
 */
if ( ! defined( 'RTGODAM_API_BASE' ) ) {
    define( 'RTGODAM_API_BASE', 'https://app.godam.io' );
}
if ( ! defined( 'RTGODAM_ANALYTICS_BASE' ) ) {
    define( 'RTGODAM_ANALYTICS_BASE', 'https://analytics.godam.io' );
}
if ( ! defined( 'RTGODAM_IO_API_BASE' ) ) {
    define( 'RTGODAM_IO_API_BASE', 'https://godam.io' );
}

/**
 * Kern-Helfer von GoDAM beibehalten.
 */
require_once RTGODAM_PATH . 'inc/helpers/autoloader.php';       // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
require_once RTGODAM_PATH . 'inc/helpers/custom-functions.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant

/**
 * --- WICHTIG: GoDAM-Transcoder DEAKTIVIERT ---
 *
 * Die folgenden Includes/Initialisierungen sind ABSICHTLICH entfernt,
 * damit keine Uploads/Transcodes mehr gegen godam.io laufen:
 *
 *   require_once RTGODAM_PATH . 'admin/godam-transcoder-functions.php';
 *   require_once RTGODAM_PATH . 'admin/class-rtgodam-transcoder-admin.php';
 *   $rtgodam_transcoder_admin = new RTGODAM_Transcoder_Admin();
 */

/**
 * === Bunny Stream Integration (Storage/Upload/Embed/Analytics/TUS) ===
 */
require_once RTGODAM_PATH . 'inc/Providers/VideoProviderInterface.php';
require_once RTGODAM_PATH . 'inc/Providers/BunnyStreamProvider.php';
require_once RTGODAM_PATH . 'admin/class-godam-admin-bunny.php';
require_once RTGODAM_PATH . 'admin/class-godam-admin-bunny-api.php';     // REST-Endpunkte: Create Video + Presign (TUS)
require_once RTGODAM_PATH . 'public/class-godam-bunny-shortcodes.php';   // Shortcodes inkl. [bunny_video] & [godam_video]

/**
 * Filesystem & Plugin-Kern von GoDAM weiter nutzen (CPTs, Blöcke etc.).
 */
\RTGODAM\Inc\FileSystem::get_instance();
\RTGODAM\Inc\Plugin::get_instance();

/**
 * Bunny-Initialisierung nach Plugin-Boot:
 * - Admin-Settings (Library ID / Access Key)
 * - Shortcodes/Embed
 * - REST-API für TUS-Presign
 */
add_action(
    'plugins_loaded',
    static function () {
        \GoDAM\Admin\Admin_Bunny_Settings::init();
        \GoDAM\Publics\BunnyShortcodes::init();

        if ( class_exists( '\GoDAM\Admin\Admin_Bunny_API' ) ) {
            \GoDAM\Admin\Admin_Bunny_API::init();
        }
    }
);

/**
 * Add Settings/Docs link to plugins area.
 *
 * @since 1.1.2
 *
 * @param array  $links Links array in which we would prepend our link.
 * @param string $file  Current plugin basename.
 *
 * @return array Processed links.
 */
function rtgodam_action_links( $links, $file ) {
    // Return normal links if not plugin.
    if ( plugin_basename( 'c1video/godam.php' ) !== $file ) {
        return $links;
    }

    // Add a few links to the existing links array.
    $settings_url = sprintf(
        '<a href="%1$s">%2$s</a>',
        esc_url( admin_url( 'admin.php?page=rtgodam_settings' ) ),
        esc_html__( 'Settings', 'godam' )
    );

    return array_merge(
        $links,
        array(
            'settings' => $settings_url,
        )
    );
}

add_filter( 'plugin_action_links', 'rtgodam_action_links', 11, 2 );
add_filter( 'network_admin_plugin_action_links', 'rtgodam_action_links', 11, 2 );

/**
 * Runs when the plugin is activated.
 */
function rtgodam_plugin_activate() {
    update_option( 'rtgodam_plugin_activation_time', time() );

    // Explicitly register post types to ensure they are available before flushing.
    $godam_video = \RTGODAM\Inc\Post_Types\GoDAM_Video::get_instance();
    $godam_video->register_post_type();

    // Flush rewrite rules to ensure CPT rules are applied.
    flush_rewrite_rules( true );
}
register_activation_hook( __FILE__, 'rtgodam_plugin_activate' );

/**
 * Runs when the plugin is deactivated.
 */
function rtgodam_plugin_deactivate() {
    delete_option( 'rtgodam_plugin_activation_time' );
    delete_option( 'rtgodam_video_metadata_migration_completed' );

    // Flush rewrite rules to remove CPT rules.
    flush_rewrite_rules( true );
}
register_deactivation_hook( __FILE__, 'rtgodam_plugin_deactivate' );
