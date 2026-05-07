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
			Server::ABILITY_PREFIX . '/client-config',
			[
				'label'               => __( 'Generate client config', 'model-context-polytechnic' ),
				'description'         => __( 'Builds public learner MCP client configurations for the vanity HTTP endpoint.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'site_url' => [
							'type'        => 'string',
							'description' => __( 'Base WordPress site URL, for example https://example.com.', 'model-context-polytechnic' ),
						],
						'client'   => [
							'type'        => 'string',
							'description' => __( 'Target MCP client family.', 'model-context-polytechnic' ),
							'enum'        => [ 'claude', 'cursor', 'vscode', 'generic' ],
							'default'     => 'generic',
						],
						'access'   => [
							'type'        => 'string',
							'description' => __( 'Whether the returned primary config should be public learner mode or opt-in write-enabled authoring mode.', 'model-context-polytechnic' ),
							'enum'        => [ 'read_only', 'write_enabled' ],
							'default'     => 'read_only',
						],
						'auth_method' => [
							'type'        => 'string',
							'description' => __( 'Write authentication style to show when access is write-enabled.', 'model-context-polytechnic' ),
							'enum'        => [ 'plugin_bearer', 'wp_application_password' ],
							'default'     => 'plugin_bearer',
						],
						'course_slug' => [
							'type'        => 'string',
							'description' => __( 'Optional published course slug. When provided, config points at that public course server instead of the registrar.', 'model-context-polytechnic' ),
						],
					],
					'required'   => [ 'site_url' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'client'     => [ 'type' => 'string' ],
						'server_id'  => [ 'type' => 'string' ],
						'course_slug' => [ 'type' => 'string' ],
						'endpoint'   => [ 'type' => 'string' ],
						'auth_mode'  => [ 'type' => 'string' ],
						'config'     => [ 'type' => 'object' ],
						'read_only'  => [ 'type' => 'object' ],
						'write_enabled' => [ 'type' => 'object' ],
						'plugin_bearer' => [ 'type' => 'object' ],
						'wp_application_password' => [ 'type' => 'object' ],
						'direct'     => [ 'type' => 'object' ],
						'proxy'      => [ 'type' => 'object' ],
						'json'       => [ 'type' => 'string' ],
						'proxy_json' => [ 'type' => 'string' ],
						'notes'      => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( array $input ): array {
					$client   = sanitize_key( $input['client'] ?? 'generic' );
					$access   = sanitize_key( $input['access'] ?? 'read_only' );
					$auth_method = sanitize_key( $input['auth_method'] ?? 'plugin_bearer' );
					$course_slug = isset( $input['course_slug'] ) ? sanitize_title( (string) $input['course_slug'] ) : '';
					$endpoint = $course_slug !== ''
						? Registry::course_vanity_endpoint( $course_slug, (string) ( $input['site_url'] ?? '' ) )
						: Server::vanity_endpoint( (string) ( $input['site_url'] ?? '' ) );
					$server_id = $course_slug !== '' ? Registry::course_server_id( $course_slug ) : Server::SERVER_ID;
					$write_enabled = $access === 'write_enabled';
					$bearer_headers = [
						'Authorization' => 'Bearer mcpoly_replace_with_token_from_wp_cli',
					];
					$wp_app_password_headers = [
						'Authorization' => 'Basic base64(wordpress_username:application_password_from_user_profile)',
					];
					$read_only = [
						'mcpServers' => [
							$server_id => [
								'url' => $endpoint,
							],
						],
					];
					$bearer_direct = [
						'mcpServers' => [
							$server_id => [
								'url'     => $endpoint,
								'headers' => $bearer_headers,
							],
						],
					];
					$wp_app_password_direct = [
						'mcpServers' => [
							$server_id => [
								'url'     => $endpoint,
								'headers' => $wp_app_password_headers,
							],
						],
					];
					$proxy_read_only = [
						'mcpServers' => [
							$server_id => [
								'command' => 'npx',
								'args'    => [ '-y', Server::REMOTE_PROXY ],
								'env'     => [
									'WP_API_URL'    => $endpoint,
									'OAUTH_ENABLED' => 'false',
								],
							],
						],
					];
					$bearer_proxy = [
						'mcpServers' => [
							$server_id => [
								'command' => 'npx',
								'args'    => [ '-y', Server::REMOTE_PROXY ],
								'env'     => [
									'WP_API_URL'     => $endpoint,
									'OAUTH_ENABLED'  => 'false',
									'CUSTOM_HEADERS' => wp_json_encode( $bearer_headers, JSON_UNESCAPED_SLASHES ),
								],
							],
						],
					];
					$wp_app_password_proxy = [
						'mcpServers' => [
							$server_id => [
								'command' => 'npx',
								'args'    => [ '-y', Server::REMOTE_PROXY ],
								'env'     => [
									'WP_API_URL'       => $endpoint,
									'OAUTH_ENABLED'    => 'false',
									'WP_API_USERNAME'  => 'wordpress_username',
									'WP_API_PASSWORD'  => 'application_password_from_user_profile',
								],
							],
						],
					];
					$use_wp_app_password = $auth_method === 'wp_application_password';
					$direct = $use_wp_app_password ? $wp_app_password_direct : $bearer_direct;
					$proxy  = $use_wp_app_password ? $wp_app_password_proxy : $bearer_proxy;
					$config   = in_array( $client, [ 'claude', 'cursor', 'vscode' ], true )
						? ( $write_enabled ? $proxy : $proxy_read_only )
						: ( $write_enabled ? $direct : $read_only );

					return [
						'client'     => in_array( $client, [ 'claude', 'cursor', 'vscode', 'generic' ], true ) ? $client : 'generic',
						'server_id'  => $server_id,
						'course_slug' => $course_slug,
						'endpoint'   => $endpoint,
						'auth_mode'  => Server::authoring_tools_enabled() ? 'public-learning/authoring-enabled' : 'public-learning',
						'config'     => $config,
						'read_only'  => $read_only,
						'write_enabled' => $direct,
						'plugin_bearer' => [
							'direct' => $bearer_direct,
							'proxy'  => $bearer_proxy,
						],
						'wp_application_password' => [
							'direct' => $wp_app_password_direct,
							'proxy'  => $wp_app_password_proxy,
						],
							'direct'     => $direct,
							'proxy'      => $proxy,
							'json'       => wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
							'proxy_json' => wp_json_encode( $proxy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
							'post_connect_verification' => $course_slug !== ''
								? [
										'List tools and confirm begin-course, get-next-work, and get-certificate are present.',
									'Call begin-course with no arguments.',
									'Preserve enrollment_key.',
									'Call get-next-work with enrollment_key.',
								]
								: [
									'List tools and confirm orient is present.',
									'Call orient with your goal.',
									'Use client-config again with course_slug for a course endpoint.',
								],
							'notes'      => [
								'Public learner MCP calls work without a token.',
									$course_slug !== '' ? 'This config points at a public course endpoint. Begin with begin-course, preserve enrollment_key, then use get-next-work and search-course as needed. When get-next-work reports complete, call get-certificate.' : 'This config points at the public registrar endpoint. Call orient, or add course_slug to generate a public course endpoint config.',
							'Write/authoring tools are hidden by default. If the host enables them, they accept either a plugin bearer token or a WordPress Application Password-authenticated user with the required capability.',
							'Mint plugin bearer tokens with: wp model-context-polytechnic token mint --email=user@example.com --label=client-name',
							'Do not send real secrets to this public read tool; replace placeholders directly in your MCP client config.',
							'OAUTH_ENABLED is false because write access uses this plugin permission callback.',
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
		$tools[] = Server::ABILITY_PREFIX . '/client-config';
		return $tools;
	}
);
