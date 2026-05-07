<?php
use ModelContextPolytechnic\Mcp\Auth;
use ModelContextPolytechnic\Mcp\Registry;
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/create-course',
			[
				'label'               => __( 'Create MCP course', 'model-context-polytechnic' ),
				'description'         => __( 'Creates a draft or published database-backed MCP server course.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'name'         => [ 'type' => 'string' ],
						'slug'         => [ 'type' => 'string' ],
						'description'  => [ 'type' => 'string' ],
						'voice'        => [ 'type' => 'object' ],
						'instructions' => [ 'type' => 'string' ],
						'status'       => [ 'type' => 'string', 'enum' => [ 'draft', 'published', 'archived' ], 'default' => 'draft' ],
					],
					'required'   => [ 'name' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Registry::create_course( $input );
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					],
				],
			]
		);

		wp_register_ability(
			Server::ABILITY_PREFIX . '/update-course',
			[
				'label'               => __( 'Update MCP course', 'model-context-polytechnic' ),
				'description'         => __( 'Updates course metadata, voice, instructions, or status.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug'         => [ 'type' => 'string' ],
						'name'         => [ 'type' => 'string' ],
						'description'  => [ 'type' => 'string' ],
						'voice'        => [ 'type' => 'object' ],
						'instructions' => [ 'type' => 'string' ],
						'status'       => [ 'type' => 'string', 'enum' => [ 'draft', 'published', 'archived' ] ],
					],
					'required'   => [ 'slug' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Registry::update_course( $input );
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					],
				],
			]
		);

		wp_register_ability(
			Server::ABILITY_PREFIX . '/publish-course',
			[
				'label'               => __( 'Publish MCP course', 'model-context-polytechnic' ),
				'description'         => __( 'Publishes a course so public clients can connect to its MCP endpoint.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug' => [ 'type' => 'string' ],
					],
					'required'   => [ 'slug' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Registry::publish_course( $input );
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					],
				],
			]
		);

		wp_register_ability(
			Server::ABILITY_PREFIX . '/add-ability',
			[
				'label'               => __( 'Add course ability', 'model-context-polytechnic' ),
				'description'         => __( 'Adds or updates a no-code tool, resource, or prompt for a course.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug'       => [ 'type' => 'string' ],
						'type'              => [ 'type' => 'string', 'enum' => [ 'tool', 'resource', 'prompt' ], 'default' => 'tool' ],
						'slug'              => [ 'type' => 'string' ],
						'label'             => [ 'type' => 'string' ],
						'description'       => [ 'type' => 'string' ],
						'input_schema'      => [ 'type' => 'object' ],
						'output_schema'     => [ 'type' => 'object' ],
						'response_template' => [ 'type' => 'string', 'description' => 'Static or templated response. Supports {{input}}, {{input.field}}, {{course.name}}, {{course.slug}}, {{ability.slug}}, and {{ability.name}}.' ],
						'content'           => [ 'type' => 'string' ],
						'requires_auth'     => [ 'type' => 'boolean', 'default' => false ],
						'status'            => [ 'type' => 'string', 'enum' => [ 'draft', 'published', 'archived' ], 'default' => 'published' ],
					],
					'required'   => [ 'course_slug', 'type', 'label' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Registry::add_ability( $input );
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					],
				],
			]
		);

		wp_register_ability(
			Server::ABILITY_PREFIX . '/add-content',
			[
				'label'               => __( 'Add course content', 'model-context-polytechnic' ),
				'description'         => __( 'Adds or updates a public or private content record for a course.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug' => [ 'type' => 'string' ],
						'slug'        => [ 'type' => 'string' ],
						'title'       => [ 'type' => 'string' ],
						'body'        => [ 'type' => 'string' ],
						'mime_type'   => [ 'type' => 'string', 'default' => 'text/plain' ],
						'visibility'  => [ 'type' => 'string', 'enum' => [ 'public', 'private' ], 'default' => 'public' ],
					],
					'required'   => [ 'course_slug', 'title', 'body' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Registry::add_content( $input );
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					],
				],
			]
		);

		wp_register_ability(
			Server::ABILITY_PREFIX . '/describe-course',
			[
				'label'               => __( 'Describe MCP course', 'model-context-polytechnic' ),
				'description'         => __( 'Returns a course with its saved abilities and content records for setup review.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug' => [ 'type' => 'string' ],
					],
					'required'   => [ 'slug' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Registry::describe_course( $input );
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

		wp_register_ability(
			Server::ABILITY_PREFIX . '/course-catalog',
			[
				'label'               => __( 'Course catalog', 'model-context-polytechnic' ),
				'description'         => __( 'Lists published MCP courses available for public enrollment.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function (): array {
					return Registry::course_catalog();
				},
				'meta'                => [
					'mcp' => [
						'uri'      => 'mcp://model-context-polytechnic/course-catalog',
						'mimeType' => 'application/json',
						'annotations' => [
							'audience' => [ 'user', 'assistant' ],
							'priority' => 0.75,
						],
					],
				],
			]
		);
	}
);

add_filter(
	Server::FILTER_PREFIX . '_tools',
	static function ( array $tools ): array {
		if ( ! Server::authoring_tools_enabled() ) {
			return $tools;
		}

		return array_merge(
			$tools,
			[
				Server::ABILITY_PREFIX . '/create-course',
				Server::ABILITY_PREFIX . '/update-course',
				Server::ABILITY_PREFIX . '/publish-course',
				Server::ABILITY_PREFIX . '/add-ability',
				Server::ABILITY_PREFIX . '/add-content',
				Server::ABILITY_PREFIX . '/describe-course',
			]
		);
	}
);

add_filter(
	Server::FILTER_PREFIX . '_resources',
	static function ( array $resources ): array {
		$resources[] = Server::ABILITY_PREFIX . '/course-catalog';
		return $resources;
	}
);
