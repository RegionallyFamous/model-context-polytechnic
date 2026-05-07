<?php
use ModelContextPolytechnic\Mcp\Registry;
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/connection-playbook',
			[
				'label'               => __( 'Connection playbook', 'model-context-polytechnic' ),
				'description'         => __( 'Returns MCP client connection, verification, and troubleshooting guidance optimized for LLM use.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'client'      => [
							'type'    => 'string',
							'enum'    => [ 'claude', 'cursor', 'vscode', 'generic' ],
							'default' => 'generic',
						],
						'course_slug' => [ 'type' => 'string' ],
						'site_url'    => [ 'type' => 'string' ],
					],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( array $input = [] ): array {
					$client      = sanitize_key( (string) ( $input['client'] ?? 'generic' ) );
					$course_slug = sanitize_title( (string) ( $input['course_slug'] ?? '' ) );
					$site_url    = (string) ( $input['site_url'] ?? '' );
					$endpoint    = $course_slug !== ''
						? Registry::course_vanity_endpoint( $course_slug, $site_url )
						: Server::vanity_endpoint( $site_url );

					return [
						'client'        => in_array( $client, [ 'claude', 'cursor', 'vscode', 'generic' ], true ) ? $client : 'generic',
						'endpoint'      => $endpoint,
						'course_slug'   => $course_slug,
						'transport'     => [
							'direct_http' => [
								'best_for' => __( 'Clients that accept a remote MCP server URL directly.', 'model-context-polytechnic' ),
								'config_hint' => [ 'url' => $endpoint ],
							],
							'remote_proxy' => [
								'best_for' => __( 'Clients that prefer a local command wrapping a remote WordPress MCP endpoint.', 'model-context-polytechnic' ),
								'command'  => 'npx',
								'args'     => [ '-y', Server::REMOTE_PROXY ],
								'env'      => [
									'WP_API_URL'    => $endpoint,
									'OAUTH_ENABLED' => 'false',
								],
							],
						],
						'post_connect_verification' => $course_slug !== ''
							? [
								'List available tools and confirm begin-course, get-next-work, get-lesson, get-exercise, attempt-exercise, get-progress, get-learning-memory, and get-certificate are present.',
								'Call begin-course with no arguments.',
								'Preserve the returned enrollment_key.',
								'Call get-next-work with enrollment_key.',
								'Call get-lesson using the suggested lesson_slug.',
							]
							: [
								'List available tools and confirm orient, client-config, server-status, and echo-schema are present.',
								'Call orient with an optional goal.',
								'If you want a course endpoint, call client-config with course_slug.',
							],
						'common_failures' => [
							'404 on /mcp means rewrite rules may need flushing or the plugin is inactive.',
							'Tools missing after course changes usually means reconnect or refresh the MCP client.',
							'401 should not happen for public learner tools; if it does, confirm you are connected to the public course endpoint and not an authoring surface.',
							'If Authorization headers are stripped on write-enabled installs, configure the host to forward them.',
						],
						'recovery_tool_calls' => [
							[
								'tool'      => Server::mcp_tool_name( Server::ABILITY_PREFIX . '/server-status' ),
								'arguments' => new \stdClass(),
							],
							[
								'tool'      => Server::mcp_tool_name( Server::ABILITY_PREFIX . '/client-config' ),
								'arguments' => [
									'site_url'    => $site_url !== '' ? $site_url : 'https://yoursite.com',
									'client'      => $client,
									'course_slug' => $course_slug,
								],
							],
						],
					];
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					],
				],
			]
		);
	}
);

add_filter(
	Server::FILTER_PREFIX . '_tools',
	static function ( array $tools ): array {
		$tools[] = Server::ABILITY_PREFIX . '/connection-playbook';
		return $tools;
	}
);
