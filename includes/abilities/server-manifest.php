<?php
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/server-manifest',
			[
				'label'               => __( 'Server manifest', 'model-context-polytechnic' ),
				'description'         => __( 'Returns a read-only manifest for the public Model Context Polytechnic MCP learning server.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'plugin'    => [ 'type' => 'object' ],
						'server'    => [ 'type' => 'object' ],
						'urls'      => [ 'type' => 'object' ],
						'abilities' => [ 'type' => 'object' ],
						'llm_interface' => [ 'type' => 'object' ],
						'courses'   => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
						'voice'     => [ 'type' => 'object' ],
						'instructions' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function (): array {
					$manifest = Server::manifest();

					return [
						'plugin'    => $manifest['plugin'],
						'server'    => $manifest['server'],
						'urls'      => $manifest['urls'],
						'abilities' => $manifest['abilities'],
						'llm_interface' => $manifest['llm_interface'],
						'courses'   => $manifest['courses'],
						'voice'     => $manifest['voice'],
						'instructions' => $manifest['instructions'],
					];
				},
				'meta'                => [
					'mcp'         => [
						'uri'      => 'mcp://model-context-polytechnic/server-manifest',
						'mimeType' => 'application/json',
						'annotations' => [
							'audience' => [ 'user', 'assistant' ],
							'priority' => 0.8,
						],
					],
				],
			]
		);
	}
);

add_filter(
	Server::FILTER_PREFIX . '_resources',
	static function ( array $resources ): array {
		$resources[] = Server::ABILITY_PREFIX . '/server-manifest';
		return $resources;
	}
);
