<?php
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/llm-interface',
			[
				'label'               => __( 'LLM interface contract', 'model-context-polytechnic' ),
				'description'         => __( 'Returns the machine-readable contract for using Model Context Polytechnic as an LLM-first MCP server.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function (): array {
					return Server::llm_interface_contract();
				},
				'meta'                => [
					'mcp' => [
						'uri'      => 'mcp://model-context-polytechnic/llm-interface',
						'mimeType' => 'application/json',
						'annotations' => [
							'audience' => [ 'assistant' ],
							'priority' => 1.0,
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
		$resources[] = Server::ABILITY_PREFIX . '/llm-interface';
		return $resources;
	}
);
