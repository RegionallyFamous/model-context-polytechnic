<?php
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/voice-guide',
			[
				'label'               => __( 'Institutional voice guide', 'model-context-polytechnic' ),
				'description'         => __( 'Returns the Model Context Polytechnic response voice and product metaphor.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'voice'        => [ 'type' => 'object' ],
						'instructions' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function (): array {
					return [
						'voice'        => Server::voice_profile(),
						'instructions' => Server::server_instructions(),
					];
				},
				'meta'                => [
					'mcp'         => [
						'uri'      => 'mcp://model-context-polytechnic/voice-guide',
						'mimeType' => 'application/json',
						'annotations' => [
							'audience' => [ 'assistant' ],
							'priority' => 0.7,
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
		$resources[] = Server::ABILITY_PREFIX . '/voice-guide';
		return $resources;
	}
);
