<?php
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/troubleshoot-connection',
			[
				'label'               => __( 'Troubleshoot MCP connection', 'model-context-polytechnic' ),
				'description'         => __( 'Returns a prompt template for debugging MCP client connectivity to a public course or registrar endpoint.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'endpoint_url' => [
							'type'        => 'string',
							'description' => __( 'The MCP endpoint the client is trying to reach.', 'model-context-polytechnic' ),
						],
						'client'       => [
							'type'        => 'string',
							'description' => __( 'Client name, such as Claude Desktop, Cursor, VS Code, or other.', 'model-context-polytechnic' ),
						],
						'symptom'      => [
							'type'        => 'string',
							'description' => __( 'Observed problem.', 'model-context-polytechnic' ),
							'enum'        => [ '404', '401', 'tools-missing', 'timeout', 'other' ],
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'text' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( array $input ): array {
					$endpoint = isset( $input['endpoint_url'] ) && $input['endpoint_url'] !== ''
						? esc_url_raw( (string) $input['endpoint_url'] )
						: Server::vanity_endpoint();
					$client   = sanitize_text_field( (string) ( $input['client'] ?? 'the MCP client' ) );
					$symptom  = sanitize_text_field( (string) ( $input['symptom'] ?? 'other' ) );

					return [
						'text' => "You are troubleshooting a public WordPress-hosted MCP learning server.\n\n"
							. "Client: {$client}\n"
							. "Endpoint: {$endpoint}\n"
							. "Observed symptom: {$symptom}\n\n"
							. "Check these items in order: confirm the plugin is active, confirm Composer dependencies are installed, flush WordPress rewrite rules, test the canonical REST endpoint, test the vanity /mcp endpoint, and list MCP tools without authentication. "
							. "For a course endpoint, call begin-course first and then search-course for a targeted topic. If optional authoring tools are enabled and a write request returns 401, check for whitespace in the secret, confirm the host forwards the Authorization header, and confirm the WordPress user can perform the requested write.",
					];
				},
			]
		);
	}
);

add_filter(
	Server::FILTER_PREFIX . '_prompts',
	static function ( array $prompts ): array {
		$prompts[] = Server::ABILITY_PREFIX . '/troubleshoot-connection';
		return $prompts;
	}
);
