<?php
/**
 * Plugin Name: Model Context Polytechnic
 * Description: A public MCP learning and diagnostics server for WordPress.
 * Version: 1.0.11
 * Requires PHP: 8.1
 * Requires at least: 6.9
 * Author: Nick
 * Text Domain: model-context-polytechnic
 */

defined( 'ABSPATH' ) || exit;

define( 'MODEL_CONTEXT_POLYTECHNIC_VERSION', '1.0.11' );
define( 'MODEL_CONTEXT_POLYTECHNIC_FILE', __FILE__ );
define( 'MODEL_CONTEXT_POLYTECHNIC_DIR', __DIR__ );

// Use Jetpack Autoloader so multiple plugins using mcp-adapter do not clash.
$model_context_polytechnic_autoloader = __DIR__ . '/vendor/autoload_packages.php';
if ( ! file_exists( $model_context_polytechnic_autoloader ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p><strong>Model Context Polytechnic</strong>: run <code>composer install</code> in the plugin directory.</p></div>';
		}
	);
	return;
}
require_once $model_context_polytechnic_autoloader;

require_once __DIR__ . '/includes/class-server.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-public-session.php';
require_once __DIR__ . '/includes/class-registry.php';
require_once __DIR__ . '/includes/class-learning.php';
require_once __DIR__ . '/includes/class-course-pack.php';
require_once __DIR__ . '/includes/class-bundled-courses.php';
require_once __DIR__ . '/includes/class-feedback-cli.php';
require_once __DIR__ . '/includes/class-rewrite.php';

add_filter( 'mcp_adapter_create_default_server', '__return_false' );
add_action( 'wp_abilities_api_categories_init', [ 'ModelContextPolytechnic\\Mcp\\Server', 'register_ability_category' ] );

// Auto-load every ability file.
foreach ( glob( __DIR__ . '/includes/abilities/*.php' ) as $model_context_polytechnic_file ) {
	require_once $model_context_polytechnic_file;
}

add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\Server', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\Auth', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\PublicSession', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\Registry', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\Learning', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\BundledCourses', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\FeedbackCli', 'init' ] );
add_action( 'plugins_loaded', [ 'ModelContextPolytechnic\\Mcp\\Rewrite', 'init' ] );

register_activation_hook(
	__FILE__,
	static function () {
		ModelContextPolytechnic\Mcp\Auth::install_table();
		ModelContextPolytechnic\Mcp\PublicSession::install_user();
		ModelContextPolytechnic\Mcp\Registry::install_tables();
		ModelContextPolytechnic\Mcp\Learning::install_tables();
		ModelContextPolytechnic\Mcp\BundledCourses::seed_all();
		ModelContextPolytechnic\Mcp\Rewrite::add_rules();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		ModelContextPolytechnic\Mcp\Learning::clear_scheduled_cleanup();
		flush_rewrite_rules();
	}
);
