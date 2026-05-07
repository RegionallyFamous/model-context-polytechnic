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
			Server::ABILITY_PREFIX . '/orient',
			[
				'label'               => __( 'Orient LLM', 'model-context-polytechnic' ),
				'description'         => __( 'Returns the LLM-first operating contract, public course catalog, and recommended next tool calls.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'goal'        => [ 'type' => 'string', 'description' => 'Optional learner or agent goal.' ],
						'course_slug' => [ 'type' => 'string', 'description' => 'Optional known course slug.' ],
					],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( array $input = [] ): array {
					$course_slug = sanitize_title( (string) ( $input['course_slug'] ?? '' ) );
					$courses     = class_exists( Registry::class ) ? Registry::course_catalog() : [];
					$course      = $course_slug !== '' && class_exists( Registry::class ) ? Registry::course_by_slug( $course_slug ) : null;

					$next_actions = [
						[
							'action' => __( 'Choose a course endpoint.', 'model-context-polytechnic' ),
							'why'    => __( 'Course endpoints expose begin-course, get-next-work, lessons, exercises, and memory.', 'model-context-polytechnic' ),
						],
					];

					if ( $course ) {
						$next_actions[] = [
							'action'    => __( 'Connect to the course endpoint and call begin-course.', 'model-context-polytechnic' ),
							'endpoint'  => Registry::course_vanity_endpoint( $course['slug'] ),
							'endpoint_required' => true,
							'current_endpoint_has_tool' => false,
							'tool'      => Server::ABILITY_PREFIX . '/' . $course['slug'] . '-begin-course',
							'arguments' => new \stdClass(),
						];
					} elseif ( ! empty( $courses[0]['slug'] ) ) {
						$next_actions[] = [
							'action'    => __( 'Start with the first published course.', 'model-context-polytechnic' ),
							'course'    => $courses[0],
							'endpoint'  => Registry::course_vanity_endpoint( $courses[0]['slug'] ),
							'endpoint_required' => true,
							'current_endpoint_has_tool' => false,
							'tool'      => Server::ABILITY_PREFIX . '/' . $courses[0]['slug'] . '-begin-course',
							'arguments' => new \stdClass(),
						];
					}

					return [
						'server'          => [
							'name'        => Server::PLUGIN_NAME,
							'description' => Server::DESCRIPTION,
							'endpoint'    => Server::vanity_endpoint(),
						],
						'llm_interface'   => Server::llm_interface_contract(),
						'goal_received'   => sanitize_text_field( (string) ( $input['goal'] ?? '' ) ),
						'courses'         => $courses,
						'next_actions'    => $next_actions,
						'recovery'        => [
							'If you have no enrollment_key, call begin-course.',
							'If you have an enrollment_key, call get-learning-memory, then get-next-work.',
							'If you do not know the right lesson, call search-course with a short technical phrase.',
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
		$tools[] = Server::ABILITY_PREFIX . '/orient';
		return $tools;
	}
);
