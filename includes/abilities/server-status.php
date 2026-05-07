<?php
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/server-status',
			[
				'label'               => __( 'Server status', 'model-context-polytechnic' ),
				'description'         => __( 'Returns public Model Context Polytechnic server identity, endpoint, adapter, and ability status.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'plugin'      => [ 'type' => 'object' ],
						'server'      => [ 'type' => 'object' ],
						'urls'        => [ 'type' => 'object' ],
						'abilities'   => [ 'type' => 'object' ],
						'adapter'     => [ 'type' => 'object' ],
						'wordpress'   => [ 'type' => 'object' ],
						'voice'       => [ 'type' => 'object' ],
						'instructions' => [ 'type' => 'string' ],
						'llm_interface' => [ 'type' => 'object' ],
						'courses'     => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
						'annotations' => [ 'type' => 'object' ],
					],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function (): array {
					return Server::manifest();
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
		$tools[] = Server::ABILITY_PREFIX . '/server-status';
		return $tools;
	}
);
