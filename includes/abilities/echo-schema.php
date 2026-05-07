<?php
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/echo-schema',
			[
				'label'               => __( 'Echo schema payload', 'model-context-polytechnic' ),
				'description'         => __( 'Echoes structured input with validation metadata so MCP clients can confirm argument passing.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'message' => [
							'type'        => 'string',
							'description' => __( 'Short message to echo.', 'model-context-polytechnic' ),
						],
						'payload' => [
							'type'        => 'object',
							'description' => __( 'Arbitrary structured object to round-trip.', 'model-context-polytechnic' ),
						],
						'tags'    => [
							'type'        => 'array',
							'description' => __( 'Optional labels for the test call.', 'model-context-polytechnic' ),
							'items'       => [ 'type' => 'string' ],
						],
					],
					'required'   => [ 'message' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'received'         => [ 'type' => 'object' ],
						'message_length'   => [ 'type' => 'integer' ],
						'payload_keys'     => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'tag_count'        => [ 'type' => 'integer' ],
						'server_time'      => [ 'type' => 'string' ],
						'schema_version'   => [ 'type' => 'string' ],
						'validation_notes' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( array $input ): array {
					$payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : [];
					$tags    = isset( $input['tags'] ) && is_array( $input['tags'] ) ? array_values( $input['tags'] ) : [];
					$message = (string) ( $input['message'] ?? '' );

					return [
						'received'         => [
							'message' => $message,
							'payload' => $payload,
							'tags'    => $tags,
						],
						'message_length'   => strlen( $message ),
						'payload_keys'     => array_keys( $payload ),
						'tag_count'        => count( $tags ),
						'server_time'      => current_time( 'mysql' ),
						'schema_version'   => '1.0',
						'validation_notes' => [
							'The message field is required by the input schema.',
							'The payload field round-trips object-shaped JSON.',
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
		$tools[] = Server::ABILITY_PREFIX . '/echo-schema';
		return $tools;
	}
);
