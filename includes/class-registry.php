<?php
namespace ModelContextPolytechnic\Mcp;

class Registry {
	const COURSES_TABLE   = 'model_context_polytechnic_courses';
	const ABILITIES_TABLE = 'model_context_polytechnic_abilities';
	const CONTENT_TABLE   = 'model_context_polytechnic_content';
	const LOGS_TABLE      = 'model_context_polytechnic_logs';
	const SCHEMA_VERSION  = '1';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_install_tables' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_dynamic_abilities' ] );
	}

	public static function maybe_install_tables(): void {
		if ( get_option( 'model_context_polytechnic_registry_schema_version' ) === self::SCHEMA_VERSION ) {
			return;
		}

		self::install_tables();
	}

	public static function install_tables(): void {
		global $wpdb;

		$charset  = $wpdb->get_charset_collate();
		$courses  = $wpdb->prefix . self::COURSES_TABLE;
		$abilities = $wpdb->prefix . self::ABILITIES_TABLE;
		$content  = $wpdb->prefix . self::CONTENT_TABLE;
		$logs     = $wpdb->prefix . self::LOGS_TABLE;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE $courses (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				slug VARCHAR(100) NOT NULL,
				name VARCHAR(191) NOT NULL,
				description LONGTEXT NULL,
				voice LONGTEXT NULL,
				instructions LONGTEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY slug (slug),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $abilities (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				type VARCHAR(20) NOT NULL DEFAULT 'tool',
				slug VARCHAR(100) NOT NULL,
				ability_name VARCHAR(191) NOT NULL,
				label VARCHAR(191) NOT NULL,
				description LONGTEXT NULL,
				input_schema LONGTEXT NULL,
				output_schema LONGTEXT NULL,
				response_template LONGTEXT NULL,
				requires_auth TINYINT(1) NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'published',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY course_slug (course_id, slug),
				UNIQUE KEY ability_name (ability_name),
				KEY course_type (course_id, type),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $content (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				slug VARCHAR(100) NOT NULL,
				resource_name VARCHAR(191) NOT NULL,
				title VARCHAR(191) NOT NULL,
				body LONGTEXT NULL,
				mime_type VARCHAR(100) NOT NULL DEFAULT 'text/plain',
				visibility VARCHAR(20) NOT NULL DEFAULT 'public',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY course_slug (course_id, slug),
				UNIQUE KEY resource_name (resource_name),
				KEY visibility (visibility)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $logs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NULL,
				action VARCHAR(100) NOT NULL,
				actor VARCHAR(191) NULL,
				data LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY course_id (course_id),
				KEY action (action)
			) $charset;"
		);

		update_option( 'model_context_polytechnic_registry_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function published_courses(): array {
		global $wpdb;
		$table = $wpdb->prefix . self::COURSES_TABLE;

		return $wpdb->get_results(
			"SELECT * FROM $table WHERE status = 'published' ORDER BY name ASC",
			ARRAY_A
		) ?: [];
	}

	public static function course_by_slug( string $slug ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::COURSES_TABLE;
		$slug  = self::sanitize_slug( $slug );

		if ( $slug === '' ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
			ARRAY_A
		);

		return $row ?: null;
	}

	public static function course_server_id( string $slug ): string {
		return Server::SERVER_ID . '-course-' . self::sanitize_slug( $slug );
	}

	public static function course_route( string $slug ): string {
		return 'courses/' . self::sanitize_slug( $slug );
	}

	public static function course_ability_name( string $course_slug, string $ability_slug ): string {
		return Server::ABILITY_PREFIX . '/' . self::sanitize_slug( $course_slug ) . '/' . self::sanitize_slug( $ability_slug );
	}

	public static function content_resource_name( string $course_slug, string $content_slug ): string {
		return Server::ABILITY_PREFIX . '/' . self::sanitize_slug( $course_slug ) . '/content/' . self::sanitize_slug( $content_slug );
	}

	public static function course_rest_endpoint( string $slug ): string {
		return rest_url( Server::REST_NS . '/' . self::course_route( $slug ) );
	}

	public static function course_vanity_endpoint( string $slug, string $site_url = '' ): string {
		$base = $site_url !== '' ? $site_url : home_url();
		return trailingslashit( Server::normalize_site_url( $base ) ) . Server::VANITY_PATH . '/' . self::sanitize_slug( $slug );
	}

	public static function course_components( int $course_id, string $course_slug ): array {
		$components = [
			'tools'     => [
				self::course_ability_name( $course_slug, 'search-course' ),
			],
			'resources' => [],
			'prompts'   => [],
		];

		foreach ( self::abilities_for_course( $course_id, true ) as $ability ) {
			if ( ! isset( $components[ $ability['type'] . 's' ] ) ) {
				continue;
			}

			$components[ $ability['type'] . 's' ][] = $ability['ability_name'];
		}

		foreach ( self::content_for_course( $course_id, 'public' ) as $item ) {
			$components['resources'][] = $item['resource_name'];
		}

		return $components;
	}

	public static function search_course( array $course, array $input ) {
		$query = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		if ( $query === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_query', __( 'query is required.', 'model-context-polytechnic' ), [ 'status' => 400 ] );
		}

		$limit = isset( $input['limit'] ) && is_numeric( $input['limit'] )
			? max( 1, min( 12, (int) $input['limit'] ) )
			: 6;
		$terms = self::search_terms( $query );
		$items = array_merge(
			self::search_course_lessons( (int) $course['id'], $terms ),
			self::search_course_exercises( (int) $course['id'], $terms ),
			self::search_course_content( (int) $course['id'], $terms )
		);

		usort(
			$items,
			static function ( array $a, array $b ): int {
				if ( $a['score'] === $b['score'] ) {
					return strcmp( $a['title'], $b['title'] );
				}

				return $b['score'] <=> $a['score'];
			}
		);

		return [
			'course'       => self::course_summary( $course ),
			'query'        => $query,
			'result_count' => count( $items ),
			'results'      => array_slice( $items, 0, $limit ),
			'note'         => __( 'Use search-course before get-lesson/get-exercise when the syllabus is larger than the current context window.', 'model-context-polytechnic' ),
		];
	}

	public static function register_dynamic_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( self::published_courses() as $course ) {
			self::register_search_course_ability( $course );

			foreach ( self::abilities_for_course( (int) $course['id'], true ) as $ability ) {
				self::register_dynamic_ability( $course, $ability );
			}

			foreach ( self::content_for_course( (int) $course['id'], 'public' ) as $content ) {
				self::register_content_resource( $course, $content );
			}
		}
	}

	private static function register_dynamic_ability( array $course, array $ability ): void {
		$type = $ability['type'];
		$meta = [
			'annotations' => [
				'readOnlyHint'    => ! (bool) $ability['requires_auth'],
				'destructiveHint' => false,
				'idempotentHint'  => true,
				'openWorldHint'   => false,
			],
		];

		if ( $type === 'resource' ) {
			$meta['mcp'] = [
				'uri'      => 'mcp://' . $course['slug'] . '/' . $ability['slug'],
				'mimeType' => 'application/json',
				'annotations' => [
					'audience' => [ 'user', 'assistant' ],
					'priority' => 0.6,
				],
			];
		}

		wp_register_ability(
			$ability['ability_name'],
			[
				'label'               => $ability['label'],
				'description'         => $ability['description'] ?: '',
				'category'            => Server::CATEGORY,
				'input_schema'        => self::decode_schema( $ability['input_schema'], self::empty_input_schema() ),
				'output_schema'       => self::decode_schema( $ability['output_schema'], self::default_output_schema( $type ) ),
				'permission_callback' => (bool) $ability['requires_auth'] ? [ Auth::class, 'require_write_access' ] : '__return_true',
				'execute_callback'    => static function ( array $input = [] ) use ( $course, $ability ) {
					if ( class_exists( Learning::class ) ) {
						return Learning::execute_public_course_tool(
							$course,
							$ability['slug'],
							$input,
							static function ( array $input ) use ( $course, $ability ) {
								return Registry::execute_dynamic_ability( $course, $ability, $input );
							}
						);
					}

					return Registry::execute_dynamic_ability( $course, $ability, $input );
				},
				'meta'                => $meta,
			]
		);
	}

	public static function register_search_course_ability( array $course ): void {
		wp_register_ability(
			self::course_ability_name( $course['slug'], 'search-course' ),
			[
				'label'               => __( 'Search course', 'model-context-polytechnic' ),
				'description'         => __( 'Searches public lessons, exercises, and course references by keyword.', 'model-context-polytechnic' ),
				'category'            => Server::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'query' => [ 'type' => 'string' ],
						'limit' => [ 'type' => 'integer', 'default' => 6, 'minimum' => 1, 'maximum' => 12 ],
					],
					'required'   => [ 'query' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function ( array $input ) use ( $course ) {
					if ( class_exists( Learning::class ) ) {
						return Learning::execute_public_course_tool(
							$course,
							'search-course',
							$input,
							static function ( array $input ) use ( $course ) {
								return Registry::search_course( $course, $input );
							}
						);
					}

					return Registry::search_course( $course, $input );
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

	private static function register_content_resource( array $course, array $content ): void {
		wp_register_ability(
			$content['resource_name'],
			[
				'label'               => $content['title'],
				'description'         => sprintf(
					/* translators: %s: course name. */
					__( 'Public course material for %s.', 'model-context-polytechnic' ),
					$course['name']
				),
				'category'            => Server::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => [
					'type' => 'string',
				],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function () use ( $course, $content ) {
					if ( class_exists( Learning::class ) ) {
						return Learning::execute_public_course_tool(
							$course,
							'content-' . $content['slug'],
							[
								'target_type' => 'resource',
								'target_slug' => $content['slug'],
							],
							static function () use ( $content ): string {
								return (string) $content['body'];
							}
						);
					}

					return (string) $content['body'];
				},
				'meta'                => [
					'mcp'         => [
						'uri'      => 'mcp://' . $course['slug'] . '/content/' . $content['slug'],
						'mimeType' => $content['mime_type'],
						'annotations' => [
							'audience' => [ 'user', 'assistant' ],
							'priority' => 0.5,
						],
					],
				],
			]
		);
	}

	public static function execute_dynamic_ability( array $course, array $ability, array $input = [] ) {
		$rendered = self::render_template( (string) $ability['response_template'], $course, $ability, $input );

		if ( $ability['type'] === 'prompt' ) {
			return [ 'text' => $rendered ];
		}

		if ( $ability['type'] === 'resource' ) {
			return $rendered;
		}

		$decoded = json_decode( $rendered, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		return [
			'course'  => $course['slug'],
			'ability' => $ability['slug'],
			'text'    => $rendered,
			'input'   => $input,
		];
	}

	public static function create_course( array $input ) {
		global $wpdb;

		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_name', __( 'Course name is required.', 'model-context-polytechnic' ) );
		}

		$slug = self::sanitize_slug( (string) ( $input['slug'] ?? '' ), $name );
		if ( $slug === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_slug', __( 'Course slug is required.', 'model-context-polytechnic' ) );
		}

		if ( self::course_by_slug( $slug ) ) {
			return new \WP_Error( 'model_context_polytechnic_course_exists', __( 'A course with that slug already exists.', 'model-context-polytechnic' ) );
		}

		$status = self::sanitize_status( (string) ( $input['status'] ?? 'draft' ) );
		$now    = current_time( 'mysql' );
		$voice  = self::encode_json_value( $input['voice'] ?? Server::voice_profile() );
		$instructions = sanitize_textarea_field( (string) ( $input['instructions'] ?? self::default_course_instructions( $name ) ) );

		$wpdb->insert(
			$wpdb->prefix . self::COURSES_TABLE,
			[
				'slug'         => $slug,
				'name'         => $name,
				'description'  => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
				'voice'        => $voice,
				'instructions' => $instructions,
				'status'       => $status,
				'created_at'   => $now,
				'updated_at'   => $now,
			]
		);

		$course = self::course_by_slug( $slug );
		self::log( $course ? (int) $course['id'] : null, 'course.created', [ 'slug' => $slug, 'status' => $status ] );

		return self::course_response( $course );
	}

	public static function update_course( array $input ) {
		global $wpdb;

		$course = self::course_by_slug( (string) ( $input['slug'] ?? '' ) );
		if ( ! $course ) {
			return new \WP_Error( 'model_context_polytechnic_course_not_found', __( 'Course not found.', 'model-context-polytechnic' ) );
		}

		$updates = [ 'updated_at' => current_time( 'mysql' ) ];
		foreach ( [ 'name', 'description', 'instructions' ] as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$updates[ $field ] = $field === 'name'
					? sanitize_text_field( (string) $input[ $field ] )
					: sanitize_textarea_field( (string) $input[ $field ] );
			}
		}

		if ( array_key_exists( 'voice', $input ) ) {
			$updates['voice'] = self::encode_json_value( $input['voice'] );
		}

		if ( array_key_exists( 'status', $input ) ) {
			$updates['status'] = self::sanitize_status( (string) $input['status'] );
		}

		$wpdb->update( $wpdb->prefix . self::COURSES_TABLE, $updates, [ 'id' => (int) $course['id'] ] );
		$course = self::course_by_slug( $course['slug'] );
		self::log( (int) $course['id'], 'course.updated', [ 'fields' => array_keys( $updates ) ] );

		return self::course_response( $course );
	}

	public static function publish_course( array $input ) {
		$input['status'] = 'published';
		return self::update_course( $input );
	}

	public static function add_ability( array $input ) {
		global $wpdb;

		$course = self::course_by_slug( (string) ( $input['course_slug'] ?? '' ) );
		if ( ! $course ) {
			return new \WP_Error( 'model_context_polytechnic_course_not_found', __( 'Course not found.', 'model-context-polytechnic' ) );
		}

		$type = sanitize_key( (string) ( $input['type'] ?? 'tool' ) );
		if ( ! in_array( $type, [ 'tool', 'resource', 'prompt' ], true ) ) {
			return new \WP_Error( 'model_context_polytechnic_invalid_ability_type', __( 'Ability type must be tool, resource, or prompt.', 'model-context-polytechnic' ) );
		}

		$label = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		if ( $label === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_label', __( 'Ability label is required.', 'model-context-polytechnic' ) );
		}

		$slug = self::sanitize_slug( (string) ( $input['slug'] ?? '' ), $label );
		if ( $slug === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_ability_slug', __( 'Ability slug is required.', 'model-context-polytechnic' ) );
		}

		$ability_name = self::course_ability_name( $course['slug'], $slug );
		$now          = current_time( 'mysql' );
		$row          = [
			'course_id'         => (int) $course['id'],
			'type'              => $type,
			'slug'              => $slug,
			'ability_name'      => $ability_name,
			'label'             => $label,
			'description'       => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
			'input_schema'      => self::encode_json_value( $input['input_schema'] ?? self::empty_input_schema() ),
			'output_schema'     => self::encode_json_value( $input['output_schema'] ?? self::default_output_schema( $type ) ),
			'response_template' => (string) ( $input['response_template'] ?? $input['content'] ?? '' ),
			'requires_auth'     => ! empty( $input['requires_auth'] ) ? 1 : 0,
			'status'            => self::sanitize_status( (string) ( $input['status'] ?? 'published' ) ),
			'updated_at'        => $now,
		];

		$existing = self::ability_by_name( $ability_name );
		if ( $existing ) {
			$wpdb->update( $wpdb->prefix . self::ABILITIES_TABLE, $row, [ 'id' => (int) $existing['id'] ] );
			$action = 'ability.updated';
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $wpdb->prefix . self::ABILITIES_TABLE, $row );
			$action = 'ability.created';
		}

		self::log( (int) $course['id'], $action, [ 'ability' => $ability_name, 'type' => $type ] );

		return [
			'course'       => self::course_summary( $course ),
			'ability'      => self::ability_by_name( $ability_name ),
			'course_url'   => self::course_vanity_endpoint( $course['slug'] ),
			'needs_publish' => $course['status'] !== 'published',
			'refresh_required' => $course['status'] === 'published',
			'note'         => $course['status'] === 'published'
				? __( 'Course abilities are registered when the MCP server is created. Reconnect or refresh the MCP client to see this change.', 'model-context-polytechnic' )
				: __( 'Publish the course when its syllabus is ready.', 'model-context-polytechnic' ),
		];
	}

	public static function add_content( array $input ) {
		global $wpdb;

		$course = self::course_by_slug( (string) ( $input['course_slug'] ?? '' ) );
		if ( ! $course ) {
			return new \WP_Error( 'model_context_polytechnic_course_not_found', __( 'Course not found.', 'model-context-polytechnic' ) );
		}

		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_title', __( 'Content title is required.', 'model-context-polytechnic' ) );
		}

		$slug = self::sanitize_slug( (string) ( $input['slug'] ?? '' ), $title );
		$now  = current_time( 'mysql' );
		$row  = [
			'course_id'     => (int) $course['id'],
			'slug'          => $slug,
			'resource_name' => self::content_resource_name( $course['slug'], $slug ),
			'title'         => $title,
			'body'          => (string) ( $input['body'] ?? '' ),
			'mime_type'     => sanitize_text_field( (string) ( $input['mime_type'] ?? 'text/plain' ) ),
			'visibility'    => in_array( (string) ( $input['visibility'] ?? 'public' ), [ 'public', 'private' ], true ) ? (string) $input['visibility'] : 'public',
			'updated_at'    => $now,
		];

		$existing = self::content_by_resource_name( $row['resource_name'] );
		if ( $existing ) {
			$wpdb->update( $wpdb->prefix . self::CONTENT_TABLE, $row, [ 'id' => (int) $existing['id'] ] );
			$action = 'content.updated';
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $wpdb->prefix . self::CONTENT_TABLE, $row );
			$action = 'content.created';
		}

		self::log( (int) $course['id'], $action, [ 'resource' => $row['resource_name'] ] );

		return [
			'course'       => self::course_summary( $course ),
			'content'      => self::content_by_resource_name( $row['resource_name'] ),
			'course_url'   => self::course_vanity_endpoint( $course['slug'] ),
			'needs_publish' => $course['status'] !== 'published',
			'refresh_required' => $course['status'] === 'published',
			'note'         => $course['status'] === 'published'
				? __( 'Course resources are registered when the MCP server is created. Reconnect or refresh the MCP client to see this change.', 'model-context-polytechnic' )
				: __( 'Publish the course when its archive is ready.', 'model-context-polytechnic' ),
		];
	}

	public static function describe_course( array $input ) {
		$course = self::course_by_slug( (string) ( $input['slug'] ?? '' ) );
		if ( ! $course ) {
			return new \WP_Error( 'model_context_polytechnic_course_not_found', __( 'Course not found.', 'model-context-polytechnic' ) );
		}

		return self::course_response( $course, true );
	}

	public static function course_catalog(): array {
		return array_map(
			static function ( array $course ): array {
				return self::course_summary( $course );
			},
			self::published_courses()
		);
	}

	public static function abilities_for_course( int $course_id, bool $published_only = false ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::ABILITIES_TABLE;

		if ( $published_only ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND status = 'published' ORDER BY type ASC, label ASC", $course_id ),
				ARRAY_A
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d ORDER BY type ASC, label ASC", $course_id ),
			ARRAY_A
		) ?: [];
	}

	public static function content_for_course( int $course_id, string $visibility = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::CONTENT_TABLE;

		if ( $visibility !== '' ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND visibility = %s ORDER BY title ASC", $course_id, $visibility ),
				ARRAY_A
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d ORDER BY title ASC", $course_id ),
			ARRAY_A
		) ?: [];
	}

	private static function search_terms( string $query ): array {
		$query = function_exists( 'mb_strtolower' ) ? mb_strtolower( $query ) : strtolower( $query );
		$parts = preg_split( '/[^a-z0-9_@.:-]+/i', $query ) ?: [];

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( string $term ): string {
							return trim( $term );
						},
						$parts
					),
					static function ( string $term ): bool {
						return strlen( $term ) >= 2;
					}
				)
			)
		);
	}

	private static function search_course_lessons( int $course_id, array $terms ): array {
		global $wpdb;
		$table = $wpdb->prefix . Learning::LESSONS_TABLE;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND status = 'published' ORDER BY position ASC, title ASC", $course_id ),
			ARRAY_A
		) ?: [];

		$results = [];
		foreach ( $rows as $row ) {
			$haystack = implode( ' ', [ $row['slug'], $row['title'], $row['body'], $row['objectives'] ] );
			$score = self::search_score( $haystack, $terms );
			if ( $score <= 0 ) {
				continue;
			}

			$results[] = [
				'type'           => 'lesson',
				'slug'           => $row['slug'],
				'title'          => $row['title'],
				'score'          => $score,
				'excerpt'        => self::search_excerpt( (string) $row['body'], $terms ),
				'next_tool'      => self::course_ability_name( self::course_slug_for_id( $course_id ), 'get-lesson' ),
				'next_arguments' => [ 'lesson_slug' => $row['slug'] ],
			];
		}

		return $results;
	}

	private static function search_course_exercises( int $course_id, array $terms ): array {
		global $wpdb;
		$table = $wpdb->prefix . Learning::EXERCISES_TABLE;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND status = 'published' ORDER BY position ASC, title ASC", $course_id ),
			ARRAY_A
		) ?: [];

		$course_slug = self::course_slug_for_id( $course_id );
		$results = [];
		foreach ( $rows as $row ) {
			$haystack = implode( ' ', [ $row['slug'], $row['title'], $row['prompt'], $row['rubric'], $row['hints'] ] );
			$score = self::search_score( $haystack, $terms );
			if ( $score <= 0 ) {
				continue;
			}

			$results[] = [
				'type'           => 'exercise',
				'slug'           => $row['slug'],
				'title'          => $row['title'],
				'score'          => $score,
				'excerpt'        => self::search_excerpt( (string) $row['prompt'], $terms ),
				'next_tool'      => self::course_ability_name( $course_slug, 'get-exercise' ),
				'next_arguments' => [ 'exercise_slug' => $row['slug'] ],
			];
		}

		return $results;
	}

	private static function search_course_content( int $course_id, array $terms ): array {
		$rows = self::content_for_course( $course_id, 'public' );
		$results = [];

		foreach ( $rows as $row ) {
			$haystack = implode( ' ', [ $row['slug'], $row['title'], $row['body'] ] );
			$score = self::search_score( $haystack, $terms );
			if ( $score <= 0 ) {
				continue;
			}

			$results[] = [
				'type'          => 'resource',
				'slug'          => $row['slug'],
				'title'         => $row['title'],
				'score'         => $score,
				'excerpt'       => self::search_excerpt( (string) $row['body'], $terms ),
				'resource_name' => $row['resource_name'],
			];
		}

		return $results;
	}

	private static function search_score( string $text, array $terms ): int {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$score = 0;

		foreach ( $terms as $term ) {
			$count = substr_count( $text, $term );
			if ( $count > 0 ) {
				$score += min( 10, $count );
			}
		}

		return $score;
	}

	private static function search_excerpt( string $text, array $terms ): string {
		$text = preg_replace( '/\s+/', ' ', self::plain_text( $text ) );
		$text = is_string( $text ) ? trim( $text ) : '';
		if ( $text === '' ) {
			return '';
		}

		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$position = 0;
		foreach ( $terms as $term ) {
			$found = strpos( $lower, $term );
			if ( $found !== false ) {
				$position = max( 0, $found - 80 );
				break;
			}
		}

		return substr( $text, $position, 260 );
	}

	private static function plain_text( string $text ): string {
		return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
	}

	private static function course_slug_for_id( int $course_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . self::COURSES_TABLE;
		$slug = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM $table WHERE id = %d", $course_id ) );

		return self::sanitize_slug( (string) $slug );
	}

	private static function ability_by_name( string $ability_name ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::ABILITIES_TABLE;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE ability_name = %s", $ability_name ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function content_by_resource_name( string $resource_name ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::CONTENT_TABLE;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE resource_name = %s", $resource_name ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function course_response( ?array $course, bool $include_children = false ) {
		if ( ! $course ) {
			return new \WP_Error( 'model_context_polytechnic_course_missing', __( 'Course could not be loaded.', 'model-context-polytechnic' ) );
		}

		$response = [
			'course'      => self::course_summary( $course ),
			'endpoints'   => [
				'vanity' => self::course_vanity_endpoint( $course['slug'] ),
				'rest'   => self::course_rest_endpoint( $course['slug'] ),
			],
			'availability' => [
				'published' => $course['status'] === 'published',
				'note'      => $course['status'] === 'published'
					? __( 'The public course endpoint is available on the next MCP/REST request. Reconnect the MCP client if it was already open.', 'model-context-polytechnic' )
					: __( 'This course is still private to the registrar. Publish it before sharing the course endpoint.', 'model-context-polytechnic' ),
			],
			'instructions' => self::course_instructions( $course ),
		];

		if ( $include_children ) {
			$response['abilities'] = self::abilities_for_course( (int) $course['id'] );
			$response['content']   = self::content_for_course( (int) $course['id'] );
		}

		return $response;
	}

	public static function course_summary( array $course ): array {
		return [
			'id'          => (int) $course['id'],
			'slug'        => $course['slug'],
			'name'        => $course['name'],
			'description' => $course['description'],
			'status'      => $course['status'],
			'voice'       => self::decode_schema( $course['voice'], Server::voice_profile() ),
			'url'         => self::course_vanity_endpoint( $course['slug'] ),
		];
	}

	public static function course_instructions( array $course ): string {
		$custom = trim( (string) $course['instructions'] );
		$instructions = $custom !== '' ? $custom : self::default_course_instructions( $course['name'] );

		if ( strpos( $instructions, 'begin-course' ) !== false ) {
			return $instructions;
		}

		return sprintf(
			"First call begin-course. Use the returned enrollment_key for attempt-exercise, get-next-work, get-progress, and get-learning-memory so the course can remember this learner.\n%s",
			$instructions
		);
	}

	private static function default_course_instructions( string $name ): string {
		return sprintf(
			'You are attending %s at Model Context Polytechnic. Call begin-course, use get-next-work for the next exact move, read lessons, attempt exercises, revise against rubric feedback, recover prior progress with get-learning-memory, and keep responses precise, useful, and warmly professorial.',
			$name
		);
	}

	private static function render_template( string $template, array $course, array $ability, array $input ): string {
		$replacements = [
			'{{course.slug}}'  => $course['slug'],
			'{{course.name}}'  => $course['name'],
			'{{ability.slug}}' => $ability['slug'],
			'{{ability.name}}' => $ability['ability_name'],
			'{{input}}'        => self::encode_json_value( $input ),
		];

		foreach ( $input as $key => $value ) {
			if ( is_scalar( $value ) || $value === null ) {
				$replacements[ '{{input.' . sanitize_key( (string) $key ) . '}}' ] = (string) $value;
			}
		}

		return strtr( $template, $replacements );
	}

	private static function empty_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		];
	}

	private static function default_output_schema( string $type ): array {
		if ( $type === 'prompt' ) {
			return [
				'type'       => 'object',
				'properties' => [
					'text' => [ 'type' => 'string' ],
				],
			];
		}

		if ( $type === 'resource' ) {
			return [ 'type' => 'string' ];
		}

		return [
			'type'       => 'object',
			'properties' => [
				'text'  => [ 'type' => 'string' ],
				'input' => [ 'type' => 'object' ],
			],
		];
	}

	private static function decode_schema( $json, array $default ): array {
		if ( is_array( $json ) ) {
			return $json;
		}

		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : $default;
	}

	private static function encode_json_value( $value ): string {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$json = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES );
				return is_string( $json ) ? $json : '{}';
			}
		}

		$json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '{}';
	}

	private static function sanitize_slug( string $slug, string $fallback = '' ): string {
		$source = $slug !== '' ? $slug : $fallback;
		return trim( substr( sanitize_title( $source ), 0, 60 ), '-' );
	}

	private static function sanitize_status( string $status ): string {
		return in_array( $status, [ 'draft', 'published', 'archived' ], true ) ? $status : 'draft';
	}

	private static function log( ?int $course_id, string $action, array $data = [] ): void {
		global $wpdb;

		$actor = '';
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			$actor = $user && $user->exists() ? $user->user_login : '';
		}

		$wpdb->insert(
			$wpdb->prefix . self::LOGS_TABLE,
			[
				'course_id'  => $course_id,
				'action'     => $action,
				'actor'      => $actor,
				'data'       => wp_json_encode( $data, JSON_UNESCAPED_SLASHES ),
				'created_at' => current_time( 'mysql' ),
			]
		);
	}
}
