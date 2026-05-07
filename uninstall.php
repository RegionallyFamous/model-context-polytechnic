<?php
/**
 * Uninstall Model Context Polytechnic.
 *
 * Removes plugin-owned tables and schema options. Deactivation keeps data; uninstall
 * is the explicit removal path.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$model_context_polytechnic_drop_site_data = static function () use ( $wpdb ): void {
	$tables = [
		'model_context_polytechnic_mcp_tokens',
		'model_context_polytechnic_courses',
		'model_context_polytechnic_abilities',
		'model_context_polytechnic_content',
		'model_context_polytechnic_logs',
		'model_context_polytechnic_modules',
		'model_context_polytechnic_lessons',
		'model_context_polytechnic_exercises',
		'model_context_polytechnic_attempts',
		'model_context_polytechnic_enrollments',
		'model_context_polytechnic_learning_events',
		'model_context_polytechnic_feedback',
		'model_context_polytechnic_certificates',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $table ) . '`' );
	}

	delete_option( 'model_context_polytechnic_auth_schema_version' );
	delete_option( 'model_context_polytechnic_registry_schema_version' );
	delete_option( 'model_context_polytechnic_learning_schema_version' );
	delete_option( 'model_context_polytechnic_bundled_courses_version' );
	delete_option( 'model_context_polytechnic_public_session_user_id' );
	delete_option( 'model_context_polytechnic_public_session_lock' );

	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_model_context_polytechnic_rl_%'
		OR option_name LIKE '_transient_timeout_model_context_polytechnic_rl_%'"
	);
};

if ( is_multisite() && function_exists( 'get_sites' ) ) {
	foreach ( get_sites( [ 'fields' => 'ids' ] ) as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		$model_context_polytechnic_drop_site_data();
		restore_current_blog();
	}
} else {
	$model_context_polytechnic_drop_site_data();
}

$model_context_polytechnic_public_user = get_user_by( 'login', 'model_context_polytechnic_public_session' );
if ( $model_context_polytechnic_public_user ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	if ( is_multisite() ) {
		require_once ABSPATH . 'wp-admin/includes/ms.php';
	}

	if ( is_multisite() && function_exists( 'wpmu_delete_user' ) ) {
		wpmu_delete_user( (int) $model_context_polytechnic_public_user->ID );
	} elseif ( function_exists( 'wp_delete_user' ) ) {
		wp_delete_user( (int) $model_context_polytechnic_public_user->ID );
	}
}
