<?php
use ModelContextPolytechnic\Mcp\Auth;
use ModelContextPolytechnic\Mcp\Learning;
use ModelContextPolytechnic\Mcp\Server;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			Server::ABILITY_PREFIX . '/add-module',
			[
				'label'               => __( 'Add course module', 'model-context-polytechnic' ),
				'description'         => __( 'Adds or updates a syllabus module for an MCP course.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug' => [ 'type' => 'string' ],
						'slug'        => [ 'type' => 'string' ],
						'title'       => [ 'type' => 'string' ],
						'summary'     => [ 'type' => 'string' ],
						'position'    => [ 'type' => 'integer', 'default' => 0 ],
						'status'      => [ 'type' => 'string', 'enum' => [ 'draft', 'published', 'archived' ], 'default' => 'published' ],
					],
					'required'   => [ 'course_slug', 'title' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Learning::add_module( $input );
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
			Server::ABILITY_PREFIX . '/add-lesson',
			[
				'label'               => __( 'Add course lesson', 'model-context-polytechnic' ),
				'description'         => __( 'Adds or updates a lesson within a course syllabus.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug' => [ 'type' => 'string' ],
						'module_slug' => [ 'type' => 'string' ],
						'slug'        => [ 'type' => 'string' ],
						'title'       => [ 'type' => 'string' ],
						'body'        => [ 'type' => 'string' ],
						'objectives'  => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'position'    => [ 'type' => 'integer', 'default' => 0 ],
						'status'      => [ 'type' => 'string', 'enum' => [ 'draft', 'published', 'archived' ], 'default' => 'published' ],
					],
					'required'   => [ 'course_slug', 'title', 'body' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Learning::add_lesson( $input );
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
			Server::ABILITY_PREFIX . '/add-exercise',
			[
				'label'               => __( 'Add course exercise', 'model-context-polytechnic' ),
				'description'         => __( 'Adds or updates a rubric-graded exercise for a course lesson or module.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug'            => [ 'type' => 'string' ],
						'module_slug'            => [ 'type' => 'string' ],
						'lesson_slug'            => [ 'type' => 'string' ],
						'slug'                   => [ 'type' => 'string' ],
						'title'                  => [ 'type' => 'string' ],
						'prompt'                 => [ 'type' => 'string' ],
						'rubric'                 => [
							'type'        => 'object',
							'description' => 'Rubric object. Use criteria[] with name, points, required_terms, and/or any_terms for deterministic grading.',
						],
						'expected_output_schema' => [ 'type' => 'object' ],
						'hints'                  => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'passing_score'          => [ 'type' => 'number', 'default' => 0.7 ],
						'position'               => [ 'type' => 'integer', 'default' => 0 ],
						'status'                 => [ 'type' => 'string', 'enum' => [ 'draft', 'published', 'archived' ], 'default' => 'published' ],
					],
					'required'   => [ 'course_slug', 'title', 'prompt' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Learning::add_exercise( $input );
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
			Server::ABILITY_PREFIX . '/set-rubric',
			[
				'label'               => __( 'Set exercise rubric', 'model-context-polytechnic' ),
				'description'         => __( 'Updates the grading rubric for a course exercise.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug'    => [ 'type' => 'string' ],
						'exercise_slug'  => [ 'type' => 'string' ],
						'rubric'         => [
							'type'        => 'object',
							'description' => 'Rubric object. criteria[] supports name, points, required_terms, and any_terms.',
						],
						'passing_score'  => [ 'type' => 'number', 'default' => 0.7 ],
					],
					'required'   => [ 'course_slug', 'exercise_slug', 'rubric' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Learning::set_rubric( $input );
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
			Server::ABILITY_PREFIX . '/describe-syllabus',
			[
				'label'               => __( 'Describe syllabus', 'model-context-polytechnic' ),
				'description'         => __( 'Returns the full syllabus, including draft learning records, for private setup review.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'course_slug' => [ 'type' => 'string' ],
					],
					'required'   => [ 'course_slug' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => [ Auth::class, 'require_write_access' ],
				'execute_callback'    => static function ( array $input ) {
					return Learning::describe_syllabus( $input );
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
		if ( ! Server::authoring_tools_enabled() ) {
			return $tools;
		}

		return array_merge(
			$tools,
			[
				Server::ABILITY_PREFIX . '/add-module',
				Server::ABILITY_PREFIX . '/add-lesson',
				Server::ABILITY_PREFIX . '/add-exercise',
				Server::ABILITY_PREFIX . '/set-rubric',
				Server::ABILITY_PREFIX . '/describe-syllabus',
			]
		);
	}
);
