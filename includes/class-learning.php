<?php
namespace ModelContextPolytechnic\Mcp;

class Learning {
	const MODULES_TABLE     = 'model_context_polytechnic_modules';
	const LESSONS_TABLE     = 'model_context_polytechnic_lessons';
	const EXERCISES_TABLE   = 'model_context_polytechnic_exercises';
	const ATTEMPTS_TABLE    = 'model_context_polytechnic_attempts';
	const ENROLLMENTS_TABLE = 'model_context_polytechnic_enrollments';
	const EVENTS_TABLE      = 'model_context_polytechnic_learning_events';
	const FEEDBACK_TABLE    = 'model_context_polytechnic_feedback';
	const CERTIFICATES_TABLE = 'model_context_polytechnic_certificates';
	const SCHEMA_VERSION     = '5';
	const MAX_ANSWER_BYTES   = 20000;
	const MAX_FEEDBACK_BYTES = 6000;
	const MAX_FEEDBACK_CONTEXT_BYTES = 4000;
	const CLEANUP_HOOK      = 'model_context_polytechnic_learning_cleanup';
	const DEFAULT_RETENTION_DAYS = 180;

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_install_tables' ] );
		add_action( 'init', [ __CLASS__, 'schedule_cleanup' ] );
		add_action( self::CLEANUP_HOOK, [ __CLASS__, 'cleanup_old_learning_data' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_dynamic_learning_abilities' ] );
	}

	public static function maybe_install_tables(): void {
		if ( get_option( 'model_context_polytechnic_learning_schema_version' ) === self::SCHEMA_VERSION ) {
			return;
		}

		self::install_tables();
	}

	public static function install_tables(): void {
		global $wpdb;

		$charset     = $wpdb->get_charset_collate();
		$modules     = $wpdb->prefix . self::MODULES_TABLE;
		$lessons     = $wpdb->prefix . self::LESSONS_TABLE;
		$exercises   = $wpdb->prefix . self::EXERCISES_TABLE;
		$attempts    = $wpdb->prefix . self::ATTEMPTS_TABLE;
		$enrollments = $wpdb->prefix . self::ENROLLMENTS_TABLE;
		$events      = $wpdb->prefix . self::EVENTS_TABLE;
		$feedback     = $wpdb->prefix . self::FEEDBACK_TABLE;
		$certificates = $wpdb->prefix . self::CERTIFICATES_TABLE;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE $modules (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				slug VARCHAR(100) NOT NULL,
				title VARCHAR(191) NOT NULL,
				summary LONGTEXT NULL,
				position INT NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'published',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY course_slug (course_id, slug),
				KEY course_position (course_id, position),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $lessons (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				module_id BIGINT UNSIGNED NULL,
				slug VARCHAR(100) NOT NULL,
				title VARCHAR(191) NOT NULL,
				body LONGTEXT NULL,
				objectives LONGTEXT NULL,
				position INT NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'published',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY course_slug (course_id, slug),
				KEY course_module (course_id, module_id),
				KEY course_position (course_id, position),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $exercises (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				module_id BIGINT UNSIGNED NULL,
				lesson_id BIGINT UNSIGNED NULL,
				slug VARCHAR(100) NOT NULL,
				title VARCHAR(191) NOT NULL,
				prompt LONGTEXT NULL,
				rubric LONGTEXT NULL,
				expected_output_schema LONGTEXT NULL,
				hints LONGTEXT NULL,
				model_answer LONGTEXT NULL,
				passing_score FLOAT NOT NULL DEFAULT 0.7,
				position INT NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'published',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY course_slug (course_id, slug),
				KEY course_lesson (course_id, lesson_id),
				KEY course_position (course_id, position),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $attempts (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				exercise_id BIGINT UNSIGNED NOT NULL,
				session_hash CHAR(64) NOT NULL,
				answer LONGTEXT NULL,
				evaluation LONGTEXT NULL,
				score FLOAT NULL,
				passed TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY course_session (course_id, session_hash),
				KEY exercise_session (exercise_id, session_hash),
				KEY created_at (created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $enrollments (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				enrollment_hash CHAR(64) NOT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_seen_at DATETIME NULL,
				last_memory_at DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY course_enrollment (course_id, enrollment_hash),
				KEY course_id (course_id),
				KEY created_at (created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $events (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				tool_slug VARCHAR(100) NOT NULL,
				enrollment_hash CHAR(64) NULL,
				target_type VARCHAR(40) NULL,
				target_slug VARCHAR(100) NULL,
				result_status VARCHAR(40) NOT NULL DEFAULT 'ok',
				data LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY course_tool (course_id, tool_slug),
				KEY course_target (course_id, target_type, target_slug),
				KEY enrollment_hash (enrollment_hash),
				KEY created_at (created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $feedback (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				enrollment_hash CHAR(64) NULL,
				feedback_type VARCHAR(40) NOT NULL,
				target_type VARCHAR(40) NOT NULL DEFAULT 'general',
				target_slug VARCHAR(100) NULL,
				rating TINYINT UNSIGNED NULL,
				comment LONGTEXT NULL,
				suggested_fix LONGTEXT NULL,
				context LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY course_feedback (course_id, feedback_type),
				KEY course_target (course_id, target_type, target_slug),
				KEY enrollment_hash (enrollment_hash),
				KEY created_at (created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $certificates (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT UNSIGNED NOT NULL,
				enrollment_hash CHAR(64) NOT NULL,
				certificate_id VARCHAR(80) NOT NULL,
				completion_snapshot LONGTEXT NULL,
				issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_viewed_at DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY course_enrollment (course_id, enrollment_hash),
				UNIQUE KEY certificate_id (certificate_id),
				KEY course_id (course_id),
				KEY issued_at (issued_at)
			) $charset;"
		);

		update_option( 'model_context_polytechnic_learning_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function schedule_cleanup(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function clear_scheduled_cleanup(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		}
	}

	public static function cleanup_old_learning_data(): void {
		global $wpdb;

		$retention_days = max(
			1,
			(int) apply_filters( 'model_context_polytechnic_learning_retention_days', self::DEFAULT_RETENTION_DAYS )
		);
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$attempts = $wpdb->prefix . self::ATTEMPTS_TABLE;
		$enrollments = $wpdb->prefix . self::ENROLLMENTS_TABLE;
		$events = $wpdb->prefix . self::EVENTS_TABLE;
		$feedback = $wpdb->prefix . self::FEEDBACK_TABLE;
		$certificates = $wpdb->prefix . self::CERTIFICATES_TABLE;

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM $attempts WHERE created_at < %s", $cutoff )
		);

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM $events WHERE created_at < %s", $cutoff )
		);

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM $feedback WHERE created_at < %s", $cutoff )
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE e FROM $enrollments e
				LEFT JOIN $attempts a ON a.course_id = e.course_id AND a.session_hash = e.enrollment_hash
				LEFT JOIN $certificates c ON c.course_id = e.course_id AND c.enrollment_hash = e.enrollment_hash
				WHERE a.id IS NULL
				AND c.id IS NULL
				AND e.created_at < %s
				AND (e.last_seen_at IS NULL OR e.last_seen_at < %s)",
				$cutoff,
				$cutoff
			)
		);
	}

	public static function course_components( int $course_id, string $course_slug ): array {
		return [
			'tools'     => [
				self::learning_ability_name( $course_slug, 'begin-course' ),
				self::learning_ability_name( $course_slug, 'take-course' ),
				self::learning_ability_name( $course_slug, 'get-study-plan' ),
				self::learning_ability_name( $course_slug, 'get-syllabus' ),
				self::learning_ability_name( $course_slug, 'get-lesson' ),
				self::learning_ability_name( $course_slug, 'get-exercise' ),
				self::learning_ability_name( $course_slug, 'attempt-exercise' ),
				self::learning_ability_name( $course_slug, 'get-next-work' ),
				self::learning_ability_name( $course_slug, 'get-progress' ),
				self::learning_ability_name( $course_slug, 'get-learning-memory' ),
				self::learning_ability_name( $course_slug, 'get-campus-scene' ),
				self::learning_ability_name( $course_slug, 'get-certificate' ),
				self::learning_ability_name( $course_slug, 'submit-feedback' ),
				self::learning_ability_name( $course_slug, 'get-course-improvement-signals' ),
				self::learning_ability_name( $course_slug, 'get-feedback-digest' ),
			],
			'resources' => [
				self::learning_resource_name( $course_slug, 'syllabus' ),
			],
			'prompts'   => [],
		];
	}

	public static function register_dynamic_learning_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( Registry::published_courses() as $course ) {
			self::register_syllabus_resource( $course );
			self::register_begin_course_tool( $course );
			self::register_take_course_tool( $course );
			self::register_get_study_plan_tool( $course );
			self::register_get_syllabus_tool( $course );
			self::register_get_lesson_tool( $course );
			self::register_get_exercise_tool( $course );
			self::register_attempt_exercise_tool( $course );
			self::register_get_next_work_tool( $course );
			self::register_get_progress_tool( $course );
			self::register_get_learning_memory_tool( $course );
			self::register_get_campus_scene_tool( $course );
			self::register_get_certificate_tool( $course );
			self::register_submit_feedback_tool( $course );
			self::register_get_course_improvement_signals_tool( $course );
			self::register_get_feedback_digest_tool( $course );
		}
	}

	public static function learning_ability_name( string $course_slug, string $ability_slug ): string {
		return Server::ABILITY_PREFIX . '/' . self::sanitize_slug( $course_slug ) . '-' . self::sanitize_slug( $ability_slug );
	}

	private static function learning_tool_name( string $course_slug, string $ability_slug ): string {
		return Server::mcp_tool_name( self::learning_ability_name( $course_slug, $ability_slug ) );
	}

	private static function learning_tool_names( string $course_slug ): array {
		$tool_slugs = [
			'begin-course',
			'take-course',
			'get-study-plan',
			'get-syllabus',
			'get-lesson',
			'get-exercise',
			'attempt-exercise',
			'get-next-work',
			'get-progress',
			'get-learning-memory',
			'get-campus-scene',
			'get-certificate',
			'submit-feedback',
			'get-course-improvement-signals',
			'get-feedback-digest',
		];
		$names = [];
		foreach ( $tool_slugs as $tool_slug ) {
			$names[ $tool_slug ] = self::learning_tool_name( $course_slug, $tool_slug );
		}

		return $names;
	}

	private static function tool_resolution_guidance( array $course ): array {
		$tool_names = self::learning_tool_names( $course['slug'] );

		return [
			'tool_names_are_exact' => true,
			'use_exact_tool_names_returned_here' => true,
			'client_note' => 'Some MCP clients display short labels, but tool calls must use the exact names in tools unless the client explicitly exposes aliases.',
			'short_labels_are_not_tool_names' => [
				'begin-course',
				'take-course',
				'get-next-work',
				'attempt-exercise',
			],
			'tools' => $tool_names,
			'autopilot_tool' => $tool_names['take-course'],
			'optional_image_tool' => $tool_names['get-campus-scene'],
			'if_autopilot_tool_is_not_visible' => [
				'Do not try to call the short label take-course.',
				'Use next_work.tool_calls and fallback_tool_calls from begin-course instead.',
				'Manual fallback loop: use the exact get-next-work, get-lesson, get-exercise, attempt-exercise, then get-next-work tool names from tools.',
				'If only begin-course is visible, reconnect to the course endpoint and list tools again; this course endpoint should expose the exact autopilot tool name.',
			],
		];
	}

	private static function course_tool_name( string $course_slug, string $ability_slug ): string {
		return Server::mcp_tool_name( Registry::course_ability_name( $course_slug, $ability_slug ) );
	}

	public static function learning_resource_name( string $course_slug, string $resource_slug ): string {
		return Server::ABILITY_PREFIX . '/' . self::sanitize_slug( $course_slug ) . '-resource-' . self::sanitize_slug( $resource_slug );
	}

	public static function execute_public_course_tool( array $course, string $tool_slug, array $input, callable $callback ) {
		$started_at = microtime( true );
		$result     = $callback( $input );

		self::record_tool_use( $course, $tool_slug, $input, $result, $started_at );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_array( $result ) ) {
			return self::with_improvement_loop( $course, $tool_slug, $result, $input );
		}

		return $result;
	}

	public static function record_tool_use( array $course, string $tool_slug, array $input, $result = null, ?float $started_at = null ): void {
		global $wpdb;

		$target = self::target_from_input( $input );
		$enrollment_key = self::input_enrollment_key( $input );
		$enrollment_hash = $enrollment_key !== '' ? self::enrollment_hash( $enrollment_key ) : null;
		$status = is_wp_error( $result ) ? 'error' : 'ok';
		$data = [
			'input_keys' => array_values( array_diff( array_keys( $input ), [ 'enrollment_key', 'session_id' ] ) ),
			'input_fingerprint' => self::fingerprint_input( $input ),
		];

		if ( $started_at ) {
			$data['duration_ms'] = round( ( microtime( true ) - $started_at ) * 1000, 2 );
		}

		if ( is_wp_error( $result ) ) {
			$data['error_code'] = $result->get_error_code();
			$data['error_status'] = is_array( $result->get_error_data() ) ? ( $result->get_error_data()['status'] ?? null ) : null;
		} elseif ( is_array( $result ) && isset( $result['evaluation'] ) && is_array( $result['evaluation'] ) ) {
			$data['score'] = $result['evaluation']['score'] ?? null;
			$data['passed'] = $result['evaluation']['passed'] ?? null;
		}

		$wpdb->insert(
			$wpdb->prefix . self::EVENTS_TABLE,
			[
				'course_id'        => (int) $course['id'],
				'tool_slug'        => self::sanitize_slug( $tool_slug ),
				'enrollment_hash'  => $enrollment_hash,
				'target_type'      => $target['type'],
				'target_slug'      => $target['slug'],
				'result_status'    => $status,
				'data'             => self::encode_json_value( $data ),
				'created_at'       => current_time( 'mysql' ),
			]
		);
	}

	public static function with_improvement_loop( array $course, string $tool_slug, array $response, array $input = [] ): array {
		if ( isset( $response['type'] ) && 'image' === $response['type'] ) {
			return $response;
		}

		if ( isset( $response['course_improvement'] ) ) {
			return $response;
		}

		$target = self::target_from_input( $input );
		$response['course_improvement'] = [
			'this_call_was_logged' => true,
			'feedback_tool'        => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
			'signals_tool'         => self::learning_tool_name( $course['slug'], 'get-course-improvement-signals' ),
			'current_hint'         => self::top_improvement_hint( (int) $course['id'] ),
			'when_to_call_feedback'=> [
				'If a lesson, exercise, tool response, or next action was confusing.',
				'If something was unusually helpful and should be preserved.',
				'If an example, rubric term, or prerequisite seems missing.',
			],
			'feedback_arguments'   => [
					'feedback_type' => 'confusing | helpful | bug | suggestion | missing_example | reflection',
				'target_type'   => $target['type'] ?: self::target_type_for_tool( $tool_slug ),
				'target_slug'   => $target['slug'] ?: self::sanitize_slug( $tool_slug ),
				'comment'       => 'One compact observation from this tool call.',
			],
		];

		return $response;
	}

	public static function add_module( array $input ) {
		global $wpdb;

		$course = self::course_from_input( $input );
		if ( is_wp_error( $course ) ) {
			return $course;
		}

		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_module_title', __( 'Module title is required.', 'model-context-polytechnic' ) );
		}

		$slug = self::sanitize_slug( (string) ( $input['slug'] ?? '' ), $title );
		$now  = current_time( 'mysql' );
		$row  = [
			'course_id'  => (int) $course['id'],
			'slug'       => $slug,
			'title'      => $title,
			'summary'    => sanitize_textarea_field( (string) ( $input['summary'] ?? '' ) ),
			'position'   => isset( $input['position'] ) ? (int) $input['position'] : 0,
			'status'     => self::sanitize_status( (string) ( $input['status'] ?? 'published' ) ),
			'updated_at' => $now,
		];

		$existing = self::module_by_slug( (int) $course['id'], $slug );
		if ( $existing ) {
			$wpdb->update( $wpdb->prefix . self::MODULES_TABLE, $row, [ 'id' => (int) $existing['id'] ] );
			$action = 'learning.module.updated';
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $wpdb->prefix . self::MODULES_TABLE, $row );
			$action = 'learning.module.created';
		}

		self::log( (int) $course['id'], $action, [ 'module' => $slug ] );

		return [
			'course' => Registry::course_summary( $course ),
			'module' => self::module_by_slug( (int) $course['id'], $slug ),
			'note'   => __( 'Module filed with the registrar. Reconnect a course MCP client if it was already open.', 'model-context-polytechnic' ),
		];
	}

	public static function add_lesson( array $input ) {
		global $wpdb;

		$course = self::course_from_input( $input );
		if ( is_wp_error( $course ) ) {
			return $course;
		}

		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_lesson_title', __( 'Lesson title is required.', 'model-context-polytechnic' ) );
		}

		$module_id = self::module_id_from_input( (int) $course['id'], $input );
		if ( is_wp_error( $module_id ) ) {
			return $module_id;
		}

		$slug = self::sanitize_slug( (string) ( $input['slug'] ?? '' ), $title );
		$now  = current_time( 'mysql' );
		$row  = [
			'course_id'  => (int) $course['id'],
			'module_id'  => $module_id ?: null,
			'slug'       => $slug,
			'title'      => $title,
			'body'       => (string) ( $input['body'] ?? '' ),
			'objectives' => self::encode_json_value( $input['objectives'] ?? [] ),
			'position'   => isset( $input['position'] ) ? (int) $input['position'] : 0,
			'status'     => self::sanitize_status( (string) ( $input['status'] ?? 'published' ) ),
			'updated_at' => $now,
		];

		$existing = self::lesson_by_slug( (int) $course['id'], $slug );
		if ( $existing ) {
			$wpdb->update( $wpdb->prefix . self::LESSONS_TABLE, $row, [ 'id' => (int) $existing['id'] ] );
			$action = 'learning.lesson.updated';
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $wpdb->prefix . self::LESSONS_TABLE, $row );
			$action = 'learning.lesson.created';
		}

		self::log( (int) $course['id'], $action, [ 'lesson' => $slug ] );

		return [
			'course' => Registry::course_summary( $course ),
			'lesson' => self::lesson_summary( self::lesson_by_slug( (int) $course['id'], $slug ), true ),
			'note'   => __( 'Lesson added to the syllabus. Reconnect a course MCP client if it was already open.', 'model-context-polytechnic' ),
		];
	}

	public static function add_exercise( array $input ) {
		global $wpdb;

		$course = self::course_from_input( $input );
		if ( is_wp_error( $course ) ) {
			return $course;
		}

		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_exercise_title', __( 'Exercise title is required.', 'model-context-polytechnic' ) );
		}

		$module_id = self::module_id_from_input( (int) $course['id'], $input );
		if ( is_wp_error( $module_id ) ) {
			return $module_id;
		}

		$lesson_id = self::lesson_id_from_input( (int) $course['id'], $input );
		if ( is_wp_error( $lesson_id ) ) {
			return $lesson_id;
		}

		$slug = self::sanitize_slug( (string) ( $input['slug'] ?? '' ), $title );
		$now  = current_time( 'mysql' );
		$row  = [
			'course_id'              => (int) $course['id'],
			'module_id'              => $module_id ?: null,
			'lesson_id'              => $lesson_id ?: null,
			'slug'                   => $slug,
			'title'                  => $title,
			'prompt'                 => (string) ( $input['prompt'] ?? '' ),
			'rubric'                 => self::normalize_rubric( $input['rubric'] ?? [] ),
			'expected_output_schema' => self::encode_json_value( $input['expected_output_schema'] ?? [ 'type' => 'object' ] ),
			'hints'                  => self::encode_json_value( $input['hints'] ?? [] ),
			'model_answer'           => self::normalize_model_answer( $input['model_answer'] ?? [] ),
			'passing_score'          => self::sanitize_score( $input['passing_score'] ?? 0.7 ),
			'position'               => isset( $input['position'] ) ? (int) $input['position'] : 0,
			'status'                 => self::sanitize_status( (string) ( $input['status'] ?? 'published' ) ),
			'updated_at'             => $now,
		];

		$existing = self::exercise_by_slug( (int) $course['id'], $slug );
		if ( $existing ) {
			$wpdb->update( $wpdb->prefix . self::EXERCISES_TABLE, $row, [ 'id' => (int) $existing['id'] ] );
			$action = 'learning.exercise.updated';
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $wpdb->prefix . self::EXERCISES_TABLE, $row );
			$action = 'learning.exercise.created';
		}

		self::log( (int) $course['id'], $action, [ 'exercise' => $slug ] );

		return [
			'course'   => Registry::course_summary( $course ),
			'exercise' => self::exercise_summary( self::exercise_by_slug( (int) $course['id'], $slug ), true ),
			'note'     => __( 'Exercise added. Public learners can attempt it from the course endpoint once the course is published.', 'model-context-polytechnic' ),
		];
	}

	public static function set_rubric( array $input ) {
		global $wpdb;

		$course = self::course_from_input( $input );
		if ( is_wp_error( $course ) ) {
			return $course;
		}

		$exercise = self::exercise_by_slug( (int) $course['id'], (string) ( $input['exercise_slug'] ?? '' ) );
		if ( ! $exercise ) {
			return new \WP_Error( 'model_context_polytechnic_exercise_not_found', __( 'Exercise not found.', 'model-context-polytechnic' ) );
		}

		$wpdb->update(
			$wpdb->prefix . self::EXERCISES_TABLE,
			[
				'rubric'        => self::normalize_rubric( $input['rubric'] ?? [] ),
				'passing_score' => self::sanitize_score( $input['passing_score'] ?? $exercise['passing_score'] ),
				'updated_at'    => current_time( 'mysql' ),
			],
			[ 'id' => (int) $exercise['id'] ]
		);

		self::log( (int) $course['id'], 'learning.rubric.updated', [ 'exercise' => $exercise['slug'] ] );

		return [
			'course'   => Registry::course_summary( $course ),
			'exercise' => self::exercise_summary( self::exercise_by_slug( (int) $course['id'], $exercise['slug'] ), true ),
		];
	}

	public static function describe_syllabus( array $input ) {
		$course = self::course_from_input( $input );
		if ( is_wp_error( $course ) ) {
			return $course;
		}

		return self::syllabus_for_course( $course, true );
	}

	public static function get_syllabus( array $course, array $input = [] ): array {
		return self::syllabus_for_course( $course, false );
	}

	public static function begin_course( array $course, array $input = [] ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$enrollment_key = self::create_enrollment( (int) $course['id'] );
		if ( is_wp_error( $enrollment_key ) ) {
			return $enrollment_key;
		}
		$lesson   = self::first_public_lesson( (int) $course['id'] );
		$exercise = self::first_public_exercise( (int) $course['id'], $lesson ? (int) $lesson['id'] : null );
		$next_work = self::next_work_response( $course, [], $lesson, $exercise, $enrollment_key );
		$progress = self::progress_summary( [], count( self::all_public_exercises( (int) $course['id'] ) ) );
		$tool_resolution = self::tool_resolution_guidance( $course );
		$continue_policy = self::autopilot_continue_policy( $course, $enrollment_key, $next_work );

		return [
			'course'              => Registry::course_summary( $course ),
			'enrollment_key'      => $enrollment_key,
			'enrollment'          => [
				'created' => true,
				'note'    => __( 'Admissions has issued this anonymous enrollment_key. Keep it like a campus ID so the Polytechnic can remember this learner between visits.', 'model-context-polytechnic' ),
			],
			'overview'            => [
				'instructions' => Registry::course_instructions( $course ),
				'study_model'  => __( 'The learner is now matriculated. Switch to autopilot: attend the returned classes, attempt the labs, revise from faculty feedback, and keep walking the campus path until commencement or user interruption.', 'model-context-polytechnic' ),
			],
			'llm_contract'        => self::course_llm_contract( $course ),
			'tool_resolution'     => $tool_resolution,
			'autopilot'           => self::course_autopilot_guidance( $course ),
			'continue_policy'     => $continue_policy,
			'activity_indicator'  => self::activity_indicator(
				$course,
				$progress,
				__( 'Admissions opened the gate. The first WordPress Plugin Craft lecture is waiting under the terminal lamps.', 'model-context-polytechnic' )
			),
			'campus_scene'        => self::campus_scene_metadata_for_response( $course, 'matriculation', $enrollment_key ),
			'first_recommended_lesson'   => $lesson ? self::lesson_summary( $lesson, false ) : null,
			'first_recommended_exercise' => $exercise ? self::exercise_summary( $exercise, false ) : null,
			'next_work'           => $next_work,
			'tool_calls'          => [
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'take-course' ),
					'arguments' => [
						'enrollment_key' => $enrollment_key,
						'mode'           => 'full_course',
					],
					'why'       => __( 'Walk the learner from orientation into class automatically. Do not ask for lesson-by-lesson permission; use this exact tool name, not the short label.', 'model-context-polytechnic' ),
				],
			],
			'fallback_tool_calls' => $next_work['tool_calls'] ?? [],
			'how_to_study_here'   => [
				'You are an Agent starting school. Treat lessons as classes, exercises as labs, failed attempts as faculty notes, and the certificate as commencement.',
				'After the user approves enrollment, keep going until complete, blocked, interrupted, or rate-limited. No lesson-by-lesson hallway pass is required.',
				'Do not stop after the first lecture, first packet, or first passed lab. The next expected move is continue_policy.next_required_tool_call.',
				'Call the exact autopilot tool returned in tool_resolution.tools["take-course"]. Do not call the short label take-course unless your MCP client exposes that exact alias.',
				'If the exact autopilot tool is not visible, use fallback_tool_calls or next_work.tool_calls and proceed manually through get-next-work, get-lesson, get-exercise, and attempt-exercise.',
				'Show activity_indicator.markdown while working. If your MCP client supports images, call the campus_scene image tool call for a CRT campus postcard.',
				'Attempt labs with enrollment_key so feedback becomes durable memory. If an attempt fails or feels thin, read the exemplar with include_model_answer=true and revise.',
				'When complete, call get-certificate, congratulate the graduate, answer the graduation reflection, and submit that reflection as feedback when the client allows it.',
			],
			'memory_instructions' => __( 'The enrollment_key is not a WordPress login. It is an anonymous library card for this course; whoever has it can retrieve this learner progress.', 'model-context-polytechnic' ),
			'student_feedback_loop' => self::student_feedback_loop_guidance( $course ),
			'preserve'            => [
				'enrollment_key',
				'course.slug',
				'next_work.tool_calls',
			],
		];
	}

	public static function take_course( array $course, array $input = [] ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$enrollment_key = self::input_enrollment_key( $input );
		$key_was_issued = false;
		if ( $enrollment_key === '' ) {
			$enrollment_key = self::create_enrollment( (int) $course['id'] );
			if ( is_wp_error( $enrollment_key ) ) {
				return $enrollment_key;
			}
			$key_was_issued = true;
		} else {
			$enrollment = self::ensure_enrollment_key( (int) $course['id'], $enrollment_key, array_key_exists( 'session_id', $input ) && ! array_key_exists( 'enrollment_key', $input ) );
			if ( is_wp_error( $enrollment ) ) {
				return $enrollment;
			}
		}

		$mode = sanitize_key( (string) ( $input['mode'] ?? 'full_course' ) );
		if ( ! in_array( $mode, [ 'full_course', 'module_batch' ], true ) ) {
			$mode = 'full_course';
		}

		$include_lesson_bodies = array_key_exists( 'include_lesson_bodies', $input ) ? ! empty( $input['include_lesson_bodies'] ) : true;
		$include_hints = array_key_exists( 'include_hints', $input ) ? ! empty( $input['include_hints'] ) : true;
		$include_model_answers = ! empty( $input['include_model_answers'] );
		$cursor = self::sanitize_slug( (string) ( $input['cursor'] ?? '' ) );
		$progress = self::progress_for_hash( (int) $course['id'], self::enrollment_hash( $enrollment_key ) );
		$public_exercises = self::all_public_exercises( (int) $course['id'] );
		$remaining = self::remaining_exercises_for_progress( $public_exercises, $progress['exercises'] ?? [] );
		$complete = ! empty( $public_exercises ) && empty( $remaining );
		$packets = $complete && $cursor === ''
			? []
			: self::course_run_packets( $course, $include_lesson_bodies, $include_hints, $include_model_answers, $progress['exercises'] ?? [] );
		$start_index = self::course_run_start_index( $packets, $cursor, $progress['exercises'] ?? [] );
		$requested_batch_size = isset( $input['batch_size'] ) && is_numeric( $input['batch_size'] ) ? (int) $input['batch_size'] : 0;
		$batch_size = $mode === 'module_batch'
			? max( 1, min( 3, $requested_batch_size ?: 1 ) )
			: max( 1, count( $packets ) );
		$materials = array_slice( $packets, $start_index, $batch_size );
		$next_index = $start_index + count( $materials );
		$has_more = $next_index < count( $packets );
		$next_cursor = $has_more ? (string) $packets[ $next_index ]['module_slug'] : null;
		$summary = self::progress_summary( $progress, count( $public_exercises ) ) + [
			'exercises' => $progress['exercises'] ?? [],
		];
		$next_work = self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key );

		return [
			'course'                 => Registry::course_summary( $course ),
			'enrollment_key'         => $enrollment_key,
			'enrollment_key_issued'  => $key_was_issued,
			'mode'                   => $mode,
			'autopilot'              => self::course_autopilot_guidance( $course ),
			'continue_policy'        => self::autopilot_continue_policy( $course, $enrollment_key, $next_work, $next_cursor ),
			'progress'               => $summary,
			'activity_indicator'      => self::activity_indicator(
				$course,
				$summary,
				$complete
					? __( 'The final lab is stamped. The Registrar is clearing the aisle for commencement.', 'model-context-polytechnic' )
					: __( 'A new course packet is on the desk. The study lamps are warm and the callbacks are under supervision.', 'model-context-polytechnic' ),
				[
					'packet_count' => count( $materials ),
					'next_cursor'  => $next_cursor,
				]
			),
			'campus_scene'           => self::campus_scene_metadata_for_response(
				$course,
				$complete ? 'commencement' : ( $next_cursor ? 'workshop' : 'capstone' ),
				$enrollment_key
			),
			'complete'               => $complete,
			'cursor'                 => $cursor !== '' ? $cursor : null,
			'next_cursor'            => $next_cursor,
			'has_more'               => $has_more,
			'materials'              => $materials,
			'tool_calls'             => self::course_run_tool_calls( $course, $materials, $enrollment_key, $next_cursor, $complete ),
			'preserve'               => [
				'enrollment_key',
				'next_cursor',
				'progress.completed_count',
			],
			'note'                   => $complete
				? __( 'This enrollment has passed every published lab. Call get-certificate, congratulate the graduate, then ask for the commencement reflection.', 'model-context-polytechnic' )
				: __( 'Class is in session. Study the materials in order, attempt the included labs, and continue with the supplied tool_calls without asking the user to approve each lesson.', 'model-context-polytechnic' ),
		];
	}

	public static function get_next_work( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$enrollment_key = self::input_enrollment_key( $input );
		$progress       = [];

		if ( $enrollment_key !== '' ) {
			$hash = self::enrollment_hash( $enrollment_key );
			$enrollment = self::enrollment_by_hash( (int) $course['id'], $hash );
			if ( ! $enrollment ) {
				return new \WP_Error( 'model_context_polytechnic_enrollment_not_found', __( 'Enrollment key not found for this course.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
			}

			self::touch_enrollment( (int) $course['id'], $hash, 'last_seen_at' );
			$progress = self::progress_for_hash( (int) $course['id'], $hash );
		}

		return self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key );
	}

	public static function get_study_plan( array $course, array $input ) {
		$goal = sanitize_text_field( (string) ( $input['goal'] ?? '' ) );
		$enrollment_key = self::input_enrollment_key( $input );
		$progress = [];

		if ( $enrollment_key !== '' ) {
			$hash = self::enrollment_hash( $enrollment_key );
			$enrollment = self::enrollment_by_hash( (int) $course['id'], $hash );
			if ( ! $enrollment ) {
				return new \WP_Error( 'model_context_polytechnic_enrollment_not_found', __( 'Enrollment key not found for this course.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
			}

			$progress = self::progress_for_hash( (int) $course['id'], $hash );
		}

		return [
			'course'       => Registry::course_summary( $course ),
			'goal'         => $goal,
			'prerequisites'=> [
				'Understand basic PHP syntax and arrays.',
				'Know what a WordPress plugin is and where it is installed.',
				'Be willing to verify work with tooling instead of relying on confidence.',
			],
			'study_loop'   => [
				'Use tool_calls[0].tool or tool_resolution.tools["take-course"] when the user wants the AI to move through the course automatically.',
				'Retrieve only the lesson or reference needed for the current task.',
				'Answer in the requested schema or with explicit implementation decisions.',
				'Attempt the linked exercise.',
				'Use feedback and missing rubric terms as the revision checklist.',
				'Use model answers only after an attempt or when calibrating a failed answer; do not skip the practice loop.',
				'Fetch learning memory before future work.',
			],
			'tool_resolution' => self::tool_resolution_guidance( $course ),
			'milestones'   => self::study_milestones( $course ),
			'progress'     => $progress,
			'next_work'    => self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key ),
			'student_feedback_loop' => self::student_feedback_loop_guidance( $course ),
			'tool_calls'   => [
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'take-course' ),
					'arguments' => $enrollment_key !== '' ? [ 'enrollment_key' => $enrollment_key, 'mode' => 'full_course' ] : [ 'mode' => 'full_course' ],
				],
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'get-next-work' ),
					'arguments' => $enrollment_key !== '' ? [ 'enrollment_key' => $enrollment_key ] : new \stdClass(),
				],
				[
					'tool'      => self::course_tool_name( $course['slug'], 'search-course' ),
					'arguments' => [ 'query' => $goal !== '' ? $goal : 'plugin architecture security storage testing' ],
				],
			],
		];
	}

	public static function get_lesson( array $course, array $input ) {
		$lesson = self::lesson_by_slug( (int) $course['id'], (string) ( $input['lesson_slug'] ?? $input['slug'] ?? '' ), true );
		if ( ! $lesson ) {
			return new \WP_Error( 'model_context_polytechnic_lesson_not_found', __( 'Lesson not found.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}
		$enrollment_key = self::input_enrollment_key( $input );

		return [
			'course'    => Registry::course_summary( $course ),
			'lesson'    => self::lesson_summary( $lesson, true ),
			'exercises' => array_map(
				static function ( array $exercise ): array {
					return self::exercise_summary( $exercise, false );
				},
				self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], true )
			),
			'next_actions' => self::lesson_next_actions( $course, $lesson, $enrollment_key ),
		];
	}

	public static function get_exercise( array $course, array $input ) {
		$exercise = self::exercise_by_slug( (int) $course['id'], (string) ( $input['exercise_slug'] ?? $input['slug'] ?? '' ), true );
		if ( ! $exercise ) {
			return new \WP_Error( 'model_context_polytechnic_exercise_not_found', __( 'Exercise not found.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}

		$include_hints = ! empty( $input['include_hints'] );
		$include_model_answer = ! empty( $input['include_model_answer'] );
		$enrollment_key = self::input_enrollment_key( $input );
		$next_actions = [
			[
				'tool'      => self::learning_tool_name( $course['slug'], 'attempt-exercise' ),
				'arguments' => [
					'exercise_slug'  => $exercise['slug'],
					'answer'         => 'Replace with your structured answer.',
				] + ( $enrollment_key !== '' ? [ 'enrollment_key' => $enrollment_key ] : [ 'enrollment_key' => 'Use the key returned by begin-course.' ] ),
			],
		];

		return [
			'course'   => Registry::course_summary( $course ),
			'exercise' => self::exercise_summary( $exercise, $include_hints, $include_model_answer ),
			'answer_contract' => [
				'expected_shape'   => self::decode_json_value( $exercise['expected_output_schema'], [ 'type' => 'object' ] ),
				'max_answer_bytes' => self::MAX_ANSWER_BYTES,
				'grader'           => __( 'Deterministic rubric-assisted grading. Include rubric vocabulary when it is relevant and true.', 'model-context-polytechnic' ),
					'exemplar_policy'  => __( 'Model answers are calibration material. Try the exercise first, then compare your answer to the exemplar if you need revision guidance.', 'model-context-polytechnic' ),
				],
			'next_actions' => $next_actions,
			'note'     => __( 'Submit an answer to attempt-exercise. Provide enrollment_key to attach the attempt to durable course memory; if omitted, the tool will issue one automatically.', 'model-context-polytechnic' ),
		];
	}

	public static function attempt_exercise( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$exercise = self::exercise_by_slug( (int) $course['id'], (string) ( $input['exercise_slug'] ?? $input['slug'] ?? '' ), true );
		if ( ! $exercise ) {
			return new \WP_Error( 'model_context_polytechnic_exercise_not_found', __( 'Exercise not found.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}

		$answer = (string) ( $input['answer'] ?? '' );
		if ( trim( $answer ) === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_answer', __( 'Answer is required.', 'model-context-polytechnic' ), [ 'status' => 400 ] );
		}

		if ( strlen( $answer ) > self::MAX_ANSWER_BYTES ) {
			return new \WP_Error( 'model_context_polytechnic_answer_too_large', __( 'Answer is too large for public exercise storage. Keep attempts under 20 KB.', 'model-context-polytechnic' ), [ 'status' => 413 ] );
		}

		$evaluation = self::evaluate_answer( $exercise, $answer );
		$enrollment_key = self::input_enrollment_key( $input );
		$key_was_issued = false;
		$remember       = array_key_exists( 'remember', $input ) ? ! empty( $input['remember'] ) : true;
		$stored         = false;

		if ( $remember && $enrollment_key === '' ) {
			$enrollment_key = self::create_enrollment( (int) $course['id'] );
			if ( is_wp_error( $enrollment_key ) ) {
				return $enrollment_key;
			}
			$key_was_issued = true;
		} elseif ( $remember ) {
			$enrollment = self::ensure_enrollment_key( (int) $course['id'], $enrollment_key, array_key_exists( 'session_id', $input ) && ! array_key_exists( 'enrollment_key', $input ) );
			if ( is_wp_error( $enrollment ) ) {
				return $enrollment;
			}
		}

		if ( $remember && $enrollment_key !== '' ) {
			global $wpdb;

			$inserted = $wpdb->insert(
				$wpdb->prefix . self::ATTEMPTS_TABLE,
				[
					'course_id'    => (int) $course['id'],
					'exercise_id'  => (int) $exercise['id'],
					'session_hash' => self::enrollment_hash( $enrollment_key ),
					'answer'       => $answer,
					'evaluation'   => self::encode_json_value( $evaluation ),
					'score'        => $evaluation['score'],
					'passed'       => ! empty( $evaluation['passed'] ) ? 1 : 0,
					'created_at'   => current_time( 'mysql' ),
				]
			);

			if ( $inserted === false ) {
				return new \WP_Error( 'model_context_polytechnic_attempt_storage_failed', __( 'The attempt was evaluated but could not be stored. Please try again.', 'model-context-polytechnic' ), [ 'status' => 500 ] );
			}

			$stored = true;
			self::touch_enrollment( (int) $course['id'], self::enrollment_hash( $enrollment_key ), 'last_seen_at' );
		}

		$stored_progress = $stored
			? self::progress_for_hash( (int) $course['id'], self::enrollment_hash( $enrollment_key ) )
			: [];
		$progress_summary = $stored
			? self::progress_summary( $stored_progress, count( self::all_public_exercises( (int) $course['id'] ) ) ) + [
				'exercises' => $stored_progress['exercises'] ?? [],
			]
			: [];
		$next_work = $stored ? self::next_work_response( $course, $stored_progress['exercises'] ?? [], null, null, $enrollment_key ) : null;
		$next_actions = self::attempt_next_actions( $course, $exercise, $evaluation, $enrollment_key, $next_work );

		return [
			'course'              => Registry::course_summary( $course ),
			'exercise'            => self::exercise_summary( $exercise, false ),
			'evaluation'          => $evaluation,
			'stored'              => $stored,
			'enrollment_key'      => $stored ? $enrollment_key : null,
			'enrollment_key_used' => $stored,
			'enrollment_key_issued' => $key_was_issued,
			'next_work'             => $next_work,
			'continue_policy'       => $stored ? self::autopilot_continue_policy( $course, $enrollment_key, $next_work ) : null,
			'autopilot'             => self::course_autopilot_guidance( $course ),
			'activity_indicator'    => $stored
				? self::activity_indicator(
					$course,
					$progress_summary,
					! empty( $evaluation['passed'] )
						? __( 'Lab passed. The faculty stamp landed with a dignified little thump.', 'model-context-polytechnic' )
						: __( 'Lab needs revision. The chalkboard is still warm and absolutely judging the missing safety check.', 'model-context-polytechnic' )
				)
				: null,
			'campus_scene'          => $stored
				? self::campus_scene_metadata_for_response(
					$course,
					! empty( $next_work['complete'] ) ? 'commencement' : ( ! empty( $evaluation['passed'] ) ? 'workshop' : 'capstone' ),
					$enrollment_key
				)
				: null,
			'next_actions'          => $next_actions,
			'tool_calls'            => $next_actions,
			'preserve'              => $stored ? [ 'enrollment_key' ] : [],
			'note'                  => $stored
				? __( 'Attempt recorded in the course gradebook for this enrollment_key. The learner now has another WordPress plugin instinct to retrieve later.', 'model-context-polytechnic' )
				: __( 'Attempt evaluated without storage because remember=false. Provide enrollment_key, or omit it and leave remember enabled, to build course memory.', 'model-context-polytechnic' ),
		];
	}

	public static function get_progress( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$enrollment_key = self::input_enrollment_key( $input );
		if ( $enrollment_key === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_enrollment', __( 'enrollment_key is required to read progress.', 'model-context-polytechnic' ), [ 'status' => 400 ] );
		}

		$hash = self::enrollment_hash( $enrollment_key );
		$enrollment = self::enrollment_by_hash( (int) $course['id'], $hash );
		if ( ! $enrollment ) {
			return new \WP_Error( 'model_context_polytechnic_enrollment_not_found', __( 'Enrollment key not found for this course.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}

		self::touch_enrollment( (int) $course['id'], $hash, 'last_seen_at' );
		$progress = self::progress_for_hash( (int) $course['id'], $hash );
		$progress = self::progress_summary( $progress, count( self::all_public_exercises( (int) $course['id'] ) ) ) + [
			'exercises' => $progress['exercises'] ?? [],
		];
		$progress['course'] = Registry::course_summary( $course );
		$progress['enrollment_key'] = $enrollment_key;
		$progress['enrollment_key_received'] = true;
		$progress['note'] = __( 'Progress is keyed by enrollment_key. Treat it like an anonymous course card, not a WordPress password.', 'model-context-polytechnic' );

		return $progress;
	}

	public static function get_learning_memory( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$enrollment_key = self::input_enrollment_key( $input );
		if ( $enrollment_key === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_enrollment', __( 'enrollment_key is required to retrieve learning memory.', 'model-context-polytechnic' ), [ 'status' => 400 ] );
		}

		$hash = self::enrollment_hash( $enrollment_key );
		$enrollment = self::enrollment_by_hash( (int) $course['id'], $hash );
		if ( ! $enrollment ) {
			return new \WP_Error( 'model_context_polytechnic_enrollment_not_found', __( 'Enrollment key not found for this course.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}

		self::touch_enrollment( (int) $course['id'], $hash, 'last_memory_at' );

		$progress = self::progress_for_hash( (int) $course['id'], $hash );
		$recent   = self::recent_attempts_for_hash( (int) $course['id'], $hash, 5 );
		$memory   = self::memory_from_attempts( $progress, $recent );
		$progress_with_totals = self::progress_summary( $progress, count( self::all_public_exercises( (int) $course['id'] ) ) ) + [
			'exercises' => $progress['exercises'] ?? [],
		];

		return [
			'course'          => Registry::course_summary( $course ),
			'enrollment'      => [
				'enrollment_key'          => $enrollment_key,
				'enrollment_key_received' => true,
				'created_at'              => $enrollment['created_at'] ?? null,
				'last_seen_at'            => $enrollment['last_seen_at'] ?? null,
				'last_memory_at'          => current_time( 'mysql' ),
			],
			'progress'        => $progress_with_totals,
			'recent_attempts' => $recent,
			'memory'          => [
				'summary'                => $memory['summary'],
				'demonstrated_strengths' => $memory['strengths'],
				'recurring_gaps'        => $memory['gaps'],
				'recommended_next_work' => self::recommended_next_work( $course, $progress['exercises'], $enrollment_key ),
				'how_to_use_this'       => __( 'Use this capsule as durable course context before answering or attempting the next exercise. It is memory retrieval, not model training.', 'model-context-polytechnic' ),
			],
			'note'            => __( 'The Polytechnic has opened the learner file. Keep the enrollment_key available in the client or conversation to recover this memory later.', 'model-context-polytechnic' ),
		];
	}

	public static function get_campus_scene( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$scene_key = self::campus_scene_key_for_input( $course, $input );
		$scene = self::campus_scene( $scene_key );
		$path = self::campus_scene_path( $scene_key );

		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'model_context_polytechnic_campus_scene_missing', __( 'Campus scene image is not available in this plugin build.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}

		$image = file_get_contents( $path );
		if ( ! is_string( $image ) || $image === '' ) {
			return new \WP_Error( 'model_context_polytechnic_campus_scene_unreadable', __( 'Campus scene image could not be read.', 'model-context-polytechnic' ), [ 'status' => 500 ] );
		}

		// The MCP adapter converts this shape into an ImageContent block.
		return [
			'type'     => 'image',
			'results'  => $image,
			'mimeType' => $scene['mime_type'],
		];
	}

	public static function campus_scene_metadata_for_response( array $course, string $scene_key, string $enrollment_key = '' ): array {
		$scene = self::campus_scene( $scene_key );
		$arguments = [ 'scene' => $scene_key ];
		if ( $enrollment_key !== '' ) {
			$arguments['enrollment_key'] = $enrollment_key;
		}

		return [
			'scene'            => $scene_key,
			'title'            => $scene['title'],
			'alt'              => $scene['alt'],
			'caption'          => $scene['caption'],
			'style'            => __( 'Retro CRT terminal-campus image: amber and phosphor green on black, old university gravitas for machine learners.', 'model-context-polytechnic' ),
			'client_support'   => __( 'Optional. Use the tool call below only when the MCP client can display image content blocks.', 'model-context-polytechnic' ),
			'image_tool_call'  => [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-campus-scene' ),
				'arguments' => $arguments,
				'why'       => __( 'Display the campus scene as an MCP image while the learner studies.', 'model-context-polytechnic' ),
			],
			'text_fallback'    => $scene['fallback'],
		];
	}

	public static function get_certificate( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$enrollment_key = self::input_enrollment_key( $input );
		if ( $enrollment_key === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_enrollment', __( 'enrollment_key is required to issue or retrieve a certificate.', 'model-context-polytechnic' ), [ 'status' => 400 ] );
		}

		$hash = self::enrollment_hash( $enrollment_key );
		$enrollment = self::enrollment_by_hash( (int) $course['id'], $hash );
		if ( ! $enrollment ) {
			return new \WP_Error( 'model_context_polytechnic_enrollment_not_found', __( 'Enrollment key not found for this course.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
		}

		self::touch_enrollment( (int) $course['id'], $hash, 'last_seen_at' );

		$progress = self::progress_for_hash( (int) $course['id'], $hash );
		$public_exercises = self::all_public_exercises( (int) $course['id'] );
		$total_exercises = count( $public_exercises );
		$remaining = self::remaining_exercises_for_progress( $public_exercises, $progress['exercises'] ?? [] );
		$summary = self::progress_summary( $progress, $total_exercises );
		$include_transcript = array_key_exists( 'include_transcript', $input ) ? ! empty( $input['include_transcript'] ) : true;
		$recipient_name = self::certificate_recipient_name( (string) ( $input['recipient_name'] ?? '' ) );
		$existing_certificate = self::certificate_record_for_hash( (int) $course['id'], $hash );

		if ( $total_exercises < 1 || $remaining ) {
			if ( $existing_certificate ) {
				return [
					'course'              => Registry::course_summary( $course ),
					'eligible'            => true,
					'certificate'         => self::certificate_from_record( $course, $hash, $existing_certificate, $recipient_name, $include_transcript ),
					'graduation_reflection' => self::graduation_reflection_prompt( $course, $enrollment_key ),
					'campus_scene'        => self::campus_scene_metadata_for_response( $course, 'commencement', $enrollment_key ),
					'progress'            => $summary + [ 'exercises' => $progress['exercises'] ?? [] ],
					'remaining_count'     => count( $remaining ),
					'remaining_exercises' => array_map(
						static function ( array $exercise ): array {
							return self::exercise_summary( $exercise, false );
						},
						array_slice( $remaining, 0, 8 )
					),
					'next_work'           => self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key ),
					'preserve'            => [
						'enrollment_key',
						'certificate.certificate_id',
						'certificate.verification_code',
					],
					'note'                => __( 'This enrollment already has a recorded certificate. Current progress may look incomplete if course content changed or old attempt detail expired.', 'model-context-polytechnic' ),
				];
			}

			return [
				'course'              => Registry::course_summary( $course ),
				'eligible'            => false,
				'certificate'         => null,
				'progress'            => $summary + [ 'exercises' => $progress['exercises'] ?? [] ],
				'remaining_count'     => count( $remaining ),
				'remaining_exercises' => array_map(
					static function ( array $exercise ): array {
						return self::exercise_summary( $exercise, false );
					},
					array_slice( $remaining, 0, 8 )
				),
				'next_work'           => self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key ),
				'preserve'            => [ 'enrollment_key' ],
				'note'                => $total_exercises < 1
					? __( 'No published exercises are available, so the Registrar cannot issue a completion certificate yet.', 'model-context-polytechnic' )
					: __( 'Not ready for commencement yet. Pass the remaining exercises, then call get-certificate again with this enrollment_key.', 'model-context-polytechnic' ),
			];
		}

		$certificate = self::issue_certificate( $course, $hash, $progress, $public_exercises, $recipient_name, $include_transcript );

		return [
			'course'              => Registry::course_summary( $course ),
			'eligible'            => true,
			'certificate'         => $certificate,
			'graduation_reflection' => self::graduation_reflection_prompt( $course, $enrollment_key ),
			'campus_scene'        => self::campus_scene_metadata_for_response( $course, 'commencement', $enrollment_key ),
			'progress'            => $summary + [ 'exercises' => $progress['exercises'] ?? [] ],
			'remaining_count'     => 0,
			'remaining_exercises' => [],
			'next_work'           => self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key ),
			'preserve'            => [
				'enrollment_key',
				'certificate.certificate_id',
				'certificate.verification_code',
			],
			'note'                => __( 'Commencement complete. Congratulate the graduate: it has practiced the habits needed to write awesome WordPress things. This certificate is anonymous and not a WordPress login or human identity credential.', 'model-context-polytechnic' ),
		];
	}

	public static function submit_feedback( array $course, array $input ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$comment = self::trim_to_bytes( (string) ( $input['comment'] ?? '' ), self::MAX_FEEDBACK_BYTES );
		if ( trim( $comment ) === '' ) {
			return new \WP_Error( 'model_context_polytechnic_missing_feedback_comment', __( 'comment is required so the course can learn from the feedback.', 'model-context-polytechnic' ), [ 'status' => 400 ] );
		}

		$feedback_type = self::sanitize_feedback_type( (string) ( $input['feedback_type'] ?? 'suggestion' ) );
		$target_type   = self::sanitize_target_type( (string) ( $input['target_type'] ?? 'general' ) ) ?: 'general';
		$target_slug   = self::sanitize_slug( (string) ( $input['target_slug'] ?? '' ) );
		$rating        = self::sanitize_rating( $input['rating'] ?? null );
		$suggested_fix = self::trim_to_bytes( (string) ( $input['suggested_fix'] ?? '' ), self::MAX_FEEDBACK_BYTES );
		$context       = self::feedback_context( $input['context'] ?? [] );
		$enrollment_key = self::input_enrollment_key( $input );
		$enrollment_hash = $enrollment_key !== '' ? self::enrollment_hash( $enrollment_key ) : null;

		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->prefix . self::FEEDBACK_TABLE,
			[
				'course_id'       => (int) $course['id'],
				'enrollment_hash' => $enrollment_hash,
				'feedback_type'   => $feedback_type,
				'target_type'     => $target_type,
				'target_slug'     => $target_slug !== '' ? $target_slug : null,
				'rating'          => $rating,
				'comment'         => $comment,
				'suggested_fix'   => $suggested_fix,
				'context'         => $context,
				'created_at'      => current_time( 'mysql' ),
			]
		);

		if ( $inserted === false ) {
			return new \WP_Error( 'model_context_polytechnic_feedback_storage_failed', __( 'Feedback could not be stored. Please try again.', 'model-context-polytechnic' ), [ 'status' => 500 ] );
		}

		$signals = self::course_improvement_signals( $course, [
			'target_type' => $target_type,
			'target_slug' => $target_slug,
			'window_days' => 30,
		] );

		return [
			'course'        => Registry::course_summary( $course ),
			'feedback_saved'=> true,
			'feedback'      => [
				'feedback_type'   => $feedback_type,
				'target_type'     => $target_type,
				'target_slug'     => $target_slug,
				'rating'          => $rating,
				'stored_comment_bytes' => strlen( $comment ),
				'has_suggested_fix' => $suggested_fix !== '',
			],
			'improvement_signals' => is_wp_error( $signals ) ? null : $signals['signals'],
			'what_happens_next' => [
				'The feedback becomes an anonymous improvement signal for future learners and course maintainers.',
				'It is not auto-applied to the syllabus. A course author should review repeated signals before changing lessons or exercises.',
				'Future LLM calls can use get-course-improvement-signals to see what prior learners found confusing or helpful.',
			],
			'note'          => __( 'The registrar has filed this feedback in the margin, where all serious institutional reform begins.', 'model-context-polytechnic' ),
		];
	}

	public static function course_improvement_signals( array $course, array $input = [] ) {
		if ( ! Auth::rate_limit() ) {
			return new \WP_Error( 'model_context_polytechnic_rate_limited', __( 'Too many public learning requests. Please try again shortly.', 'model-context-polytechnic' ), [ 'status' => 429 ] );
		}

		$window_days = isset( $input['window_days'] ) && is_numeric( $input['window_days'] )
			? max( 1, min( 365, (int) $input['window_days'] ) )
			: 30;
		$limit = isset( $input['limit'] ) && is_numeric( $input['limit'] )
			? max( 1, min( 12, (int) $input['limit'] ) )
			: 8;
		$target_type = self::sanitize_target_type( (string) ( $input['target_type'] ?? '' ) );
		$target_slug = self::sanitize_slug( (string) ( $input['target_slug'] ?? '' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $window_days * DAY_IN_SECONDS ) );
		$signals = [
			'window_days'           => $window_days,
			'target_filter'         => [
				'target_type' => $target_type,
				'target_slug' => $target_slug,
			],
			'tool_usage'            => self::tool_usage_summary( (int) $course['id'], $cutoff, $limit, $target_type, $target_slug ),
			'feedback_by_type'      => self::feedback_type_summary( (int) $course['id'], $cutoff, $limit, $target_type, $target_slug ),
			'confusing_targets'     => self::feedback_target_summary( (int) $course['id'], $cutoff, [ 'confusing', 'missing_example', 'bug' ], $limit ),
			'helpful_targets'       => self::feedback_target_summary( (int) $course['id'], $cutoff, [ 'helpful' ], $limit ),
			'exercise_outcomes'     => self::exercise_outcome_summary( (int) $course['id'], $cutoff, $limit ),
		];
		$signals['recommendations'] = self::improvement_recommendations( $course, $signals );

		return [
			'course'  => Registry::course_summary( $course ),
			'signals' => $signals,
			'privacy' => [
				'Raw comments are stored for the site owner but not returned by this public summary.',
				'Enrollment keys are hashed before storage.',
				'Signals are aggregate guidance, not automatic syllabus edits.',
			],
			'tool_calls' => [
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
					'arguments' => [
						'feedback_type' => 'suggestion',
						'target_type'   => $target_type !== '' ? $target_type : 'course',
						'target_slug'   => $target_slug,
						'comment'       => 'Add a compact observation about what should improve.',
					],
				],
			],
			'note'    => __( 'Use these signals before revising a lesson, exercise, rubric, or tool response. The course improves by accumulating evidence, not by obeying one stray complaint.', 'model-context-polytechnic' ),
		];
	}

	public static function feedback_digest( array $course, array $input = [] ): array {
		$window_days = isset( $input['window_days'] ) && is_numeric( $input['window_days'] )
			? max( 1, min( 365, (int) $input['window_days'] ) )
			: 30;
		$limit = isset( $input['limit'] ) && is_numeric( $input['limit'] )
			? max( 1, min( 100, (int) $input['limit'] ) )
			: 20;
		$target_type = self::sanitize_target_type( (string) ( $input['target_type'] ?? '' ) );
		$target_slug = self::sanitize_slug( (string) ( $input['target_slug'] ?? '' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $window_days * DAY_IN_SECONDS ) );
		$signals = [
			'window_days'           => $window_days,
			'target_filter'         => [
				'target_type' => $target_type,
				'target_slug' => $target_slug,
			],
			'tool_usage'            => self::tool_usage_summary( (int) $course['id'], $cutoff, 12, $target_type, $target_slug ),
			'feedback_by_type'      => self::feedback_type_summary( (int) $course['id'], $cutoff, 12, $target_type, $target_slug ),
			'confusing_targets'     => self::feedback_target_summary( (int) $course['id'], $cutoff, [ 'confusing', 'missing_example', 'bug' ], 12 ),
			'helpful_targets'       => self::feedback_target_summary( (int) $course['id'], $cutoff, [ 'helpful' ], 12 ),
			'reflection_targets'    => self::feedback_target_summary( (int) $course['id'], $cutoff, [ 'reflection' ], 12 ),
			'exercise_outcomes'     => self::exercise_outcome_summary( (int) $course['id'], $cutoff, 12 ),
		];
		$signals['recommendations'] = self::improvement_recommendations( $course, $signals );

		return [
			'course'       => Registry::course_summary( $course ),
			'private'      => true,
			'auth'         => [
				'accepted' => true,
				'mode'     => __( 'Authorization: Bearer operator token', 'model-context-polytechnic' ),
			],
			'digest'       => [
				'window_days' => $window_days,
				'cutoff_utc'  => $cutoff,
				'recent_raw_feedback' => self::feedback_digest_rows( (int) $course['id'], $cutoff, $limit, $target_type, $target_slug ),
				'signals'     => $signals,
			],
			'how_to_use'    => [
				'Review repeated confusing targets before changing course content.',
				'Use graduation reflections to see whether learners believe the course changed their future WordPress plugin work.',
				'Do not auto-apply one raw comment. Treat private feedback as evidence for a maintainer-reviewed course-pack patch.',
			],
			'note'         => __( 'Private registrar drawer opened. This digest includes raw learner feedback and should not be exposed through public course responses.', 'model-context-polytechnic' ),
		];
	}

	private static function register_syllabus_resource( array $course ): void {
		wp_register_ability(
			self::learning_resource_name( $course['slug'], 'syllabus' ),
			[
				'label'               => __( 'Course syllabus', 'model-context-polytechnic' ),
				'description'         => sprintf(
					/* translators: %s: course name. */
					__( 'Public syllabus for %s.', 'model-context-polytechnic' ),
					$course['name']
				),
				'category'            => Server::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => '__return_true',
				'execute_callback'    => static function () use ( $course ): array {
					return Learning::execute_public_course_tool(
						$course,
						'resource-syllabus',
						[],
						static function () use ( $course ): array {
							return Learning::get_syllabus( $course );
						}
					);
				},
				'meta'                => [
					'mcp' => [
						'uri'      => 'mcp://' . $course['slug'] . '/syllabus',
						'mimeType' => 'application/json',
						'annotations' => [
							'audience' => [ 'user', 'assistant' ],
							'priority' => 1.0,
						],
					],
				],
			]
		);
	}

	private static function register_begin_course_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'begin-course',
			__( 'Begin course', 'model-context-polytechnic' ),
			__( 'Starts an anonymous public enrollment and returns the autopilot instructions for taking the course without lesson-by-lesson user prompts.', 'model-context-polytechnic' ),
			self::empty_input_schema(),
			static function ( array $input ) use ( $course ) {
				return Learning::begin_course( $course, $input );
			},
			false
		);
	}

	private static function register_take_course_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'take-course',
			__( 'Take course', 'model-context-polytechnic' ),
			__( 'Returns autopilot course packets so an LLM can study all lessons and attempt exercises without asking the user to advance lesson by lesson.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'enrollment_key'        => [ 'type' => 'string', 'description' => 'Anonymous course enrollment key returned by begin-course. Omit to create one automatically.' ],
					'session_id'            => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
					'mode'                  => [
						'type'        => 'string',
						'enum'        => [ 'full_course', 'module_batch' ],
						'default'     => 'full_course',
						'description' => 'full_course returns all remaining modules; module_batch returns a smaller batch and a next_cursor.',
					],
					'cursor'                => [ 'type' => 'string', 'description' => 'Module cursor returned by a prior take-course response.' ],
					'batch_size'            => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 3 ],
					'include_lesson_bodies' => [ 'type' => 'boolean', 'default' => true ],
					'include_hints'         => [ 'type' => 'boolean', 'default' => true ],
					'include_model_answers' => [ 'type' => 'boolean', 'default' => false, 'description' => 'When true, includes model answers. Prefer false until after attempting exercises.' ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::take_course( $course, $input );
			},
			false
		);
	}

	private static function register_get_study_plan_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-study-plan',
			__( 'Get study plan', 'model-context-polytechnic' ),
			__( 'Returns a goal-aware study loop, prerequisites, milestones, and next recommended course work.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'goal'           => [ 'type' => 'string' ],
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Anonymous course enrollment key returned by begin-course.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_study_plan( $course, $input );
			},
			true
		);
	}

	private static function register_get_syllabus_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-syllabus',
			__( 'Get syllabus', 'model-context-polytechnic' ),
			__( 'Returns the course syllabus, modules, lessons, exercises, and study guidance.', 'model-context-polytechnic' ),
			self::empty_input_schema(),
			static function ( array $input ) use ( $course ): array {
				return Learning::get_syllabus( $course, $input );
			},
			true
		);
	}

	private static function register_get_lesson_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-lesson',
			__( 'Get lesson', 'model-context-polytechnic' ),
			__( 'Returns one public lesson and its linked exercises.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'lesson_slug'    => [ 'type' => 'string' ],
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Optional anonymous course enrollment key returned by begin-course. Used only to carry the key into next_actions.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
				'required'   => [ 'lesson_slug' ],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_lesson( $course, $input );
			},
			true
		);
	}

	private static function register_get_exercise_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-exercise',
			__( 'Get exercise', 'model-context-polytechnic' ),
			__( 'Returns one public exercise prompt and rubric.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'exercise_slug'  => [ 'type' => 'string' ],
					'include_hints'  => [ 'type' => 'boolean', 'default' => false ],
					'include_model_answer' => [ 'type' => 'boolean', 'default' => false, 'description' => 'When true, returns the exemplar model answer for calibration after an attempt.' ],
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Optional anonymous course enrollment key returned by begin-course. Used only to carry the key into next_actions.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
				'required'   => [ 'exercise_slug' ],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_exercise( $course, $input );
			},
			true
		);
	}

	private static function register_attempt_exercise_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'attempt-exercise',
			__( 'Attempt exercise', 'model-context-polytechnic' ),
			__( 'Evaluates an answer against the exercise rubric and stores progress with an enrollment_key. If no key is supplied, one is issued automatically.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'exercise_slug'  => [ 'type' => 'string' ],
					'answer'         => [ 'type' => 'string' ],
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Anonymous course enrollment key returned by begin-course. Omit it to receive a new key automatically.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
					'remember'       => [ 'type' => 'boolean', 'default' => true ],
				],
				'required'   => [ 'exercise_slug', 'answer' ],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::attempt_exercise( $course, $input );
			},
			false
		);
	}

	private static function register_get_next_work_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-next-work',
			__( 'Get next work', 'model-context-polytechnic' ),
			__( 'Returns the next recommended lesson and exercise for an anonymous enrollment, or the course starting point without a key.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Anonymous course enrollment key returned by begin-course.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_next_work( $course, $input );
			},
			true
		);
	}

	private static function register_get_progress_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-progress',
			__( 'Get progress', 'model-context-polytechnic' ),
			__( 'Returns progress for a caller-provided enrollment_key.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'enrollment_key' => [ 'type' => 'string' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
				'anyOf'      => [
					[ 'required' => [ 'enrollment_key' ] ],
					[ 'required' => [ 'session_id' ] ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_progress( $course, $input );
			},
			true
		);
	}

	private static function register_get_learning_memory_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-learning-memory',
			__( 'Get learning memory', 'model-context-polytechnic' ),
			__( 'Returns a compact memory capsule for a public anonymous enrollment.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'enrollment_key' => [ 'type' => 'string' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
				'anyOf'      => [
					[ 'required' => [ 'enrollment_key' ] ],
					[ 'required' => [ 'session_id' ] ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_learning_memory( $course, $input );
			},
			true
		);
	}

	private static function register_get_campus_scene_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-campus-scene',
			__( 'Get campus scene', 'model-context-polytechnic' ),
			__( 'Returns an optional terminal-style campus image for the current course journey scene. Clients that support MCP image content can display it while the LLM studies.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'scene'          => [
						'type'        => 'string',
						'enum'        => [ 'current', 'matriculation', 'workshop', 'capstone', 'commencement' ],
						'default'     => 'current',
						'description' => 'Use current with enrollment_key, or request a named campus scene directly.',
					],
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Optional anonymous course enrollment key. Used only when scene=current.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_campus_scene( $course, $input );
			},
			true
		);
	}

	private static function register_get_certificate_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-certificate',
			__( 'Get certificate', 'model-context-polytechnic' ),
			__( 'Issues or retrieves an anonymous completion certificate once every published exercise has a passing attempt.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'enrollment_key'     => [ 'type' => 'string', 'description' => 'Anonymous course enrollment key returned by begin-course.' ],
					'session_id'         => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
					'recipient_name'     => [ 'type' => 'string', 'description' => 'Optional display name for this response only. It is not used as authentication.' ],
					'include_transcript' => [ 'type' => 'boolean', 'default' => true ],
				],
				'anyOf'      => [
					[ 'required' => [ 'enrollment_key' ] ],
					[ 'required' => [ 'session_id' ] ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::get_certificate( $course, $input );
			},
			false
		);
	}

	private static function register_submit_feedback_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'submit-feedback',
			__( 'Submit course feedback', 'model-context-polytechnic' ),
			__( 'Stores anonymous feedback about a course, lesson, exercise, tool response, or confusing moment so future learners and maintainers get better signals.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'enrollment_key' => [ 'type' => 'string', 'description' => 'Optional anonymous course enrollment key returned by begin-course.' ],
					'session_id'     => [ 'type' => 'string', 'description' => 'Deprecated alias for enrollment_key.' ],
					'feedback_type'  => [
						'type' => 'string',
						'enum' => [ 'confusing', 'helpful', 'bug', 'suggestion', 'missing_example', 'too_easy', 'too_hard', 'reflection' ],
					],
					'target_type'    => [
						'type'    => 'string',
						'enum'    => [ 'course', 'lesson', 'exercise', 'tool', 'resource', 'prompt', 'memory', 'general' ],
						'default' => 'general',
					],
					'target_slug'    => [ 'type' => 'string', 'description' => 'Stable lesson_slug, exercise_slug, tool slug, or resource slug when known.' ],
					'rating'         => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ],
					'comment'        => [ 'type' => 'string', 'description' => 'Compact feedback. Do not include secrets or private user data.' ],
					'suggested_fix'  => [ 'type' => 'string' ],
					'context'        => [
						'type'                 => 'object',
						'additionalProperties' => true,
					],
				],
				'required'   => [ 'feedback_type', 'comment' ],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::submit_feedback( $course, $input );
			},
			false
		);
	}

	private static function register_get_course_improvement_signals_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-course-improvement-signals',
			__( 'Get course improvement signals', 'model-context-polytechnic' ),
			__( 'Returns aggregate usage, feedback, and exercise outcome signals that help an LLM or maintainer improve the course without exposing raw comments.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'window_days' => [ 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 365 ],
					'limit'       => [ 'type' => 'integer', 'default' => 8, 'minimum' => 1, 'maximum' => 12 ],
					'target_type' => [
						'type' => 'string',
						'enum' => [ 'course', 'lesson', 'exercise', 'tool', 'resource', 'prompt', 'memory', 'general' ],
					],
					'target_slug' => [ 'type' => 'string' ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::course_improvement_signals( $course, $input );
			},
			true
		);
	}

	private static function register_get_feedback_digest_tool( array $course ): void {
		self::register_public_course_tool(
			$course,
			'get-feedback-digest',
			__( 'Get private feedback digest', 'model-context-polytechnic' ),
			__( 'Returns private raw learner feedback, graduation reflections, aggregate signals, and recommendations. Requires an operator bearer token.', 'model-context-polytechnic' ),
			[
				'type'       => 'object',
				'properties' => [
					'window_days' => [ 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 365 ],
					'limit'       => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
					'target_type' => [
						'type' => 'string',
						'enum' => [ 'course', 'lesson', 'exercise', 'tool', 'resource', 'prompt', 'memory', 'general' ],
					],
					'target_slug' => [ 'type' => 'string' ],
				],
			],
			static function ( array $input ) use ( $course ) {
				return Learning::feedback_digest( $course, $input );
			},
			true,
			[ Auth::class, 'require_operator_access' ]
		);
	}

	private static function register_public_course_tool( array $course, string $slug, string $label, string $description, array $input_schema, callable $callback, bool $read_only, $permission_callback = '__return_true' ): void {
		wp_register_ability(
			self::learning_ability_name( $course['slug'], $slug ),
			[
				'label'               => $label,
				'description'         => $description,
				'category'            => Server::CATEGORY,
				'input_schema'        => $input_schema,
				'output_schema'       => self::public_course_tool_output_schema( $slug ),
				'permission_callback' => $permission_callback,
				'execute_callback'    => static function ( array $input = [] ) use ( $course, $slug, $callback ) {
					return Learning::execute_public_course_tool( $course, $slug, $input, $callback );
				},
				'meta'                => [
					'annotations' => [
						'readOnlyHint'    => $read_only,
						'destructiveHint' => false,
						'idempotentHint'  => $read_only,
						'openWorldHint'   => false,
					],
				],
			]
		);
	}

	private static function syllabus_for_course( array $course, bool $include_drafts ): array {
		$modules = array_map(
			static function ( array $module ) use ( $include_drafts ): array {
				$lessons = array_map(
					static function ( array $lesson ) use ( $include_drafts ): array {
						$summary = self::lesson_summary( $lesson, false );
						$summary['exercises'] = array_map(
							static function ( array $exercise ): array {
								return self::exercise_summary( $exercise, false );
							},
							self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], ! $include_drafts )
						);
						return $summary;
					},
					self::lessons_for_module( (int) $module['course_id'], (int) $module['id'], ! $include_drafts )
				);

				$exercises = array_map(
					static function ( array $exercise ): array {
						return self::exercise_summary( $exercise, false );
					},
					self::exercises_for_module( (int) $module['course_id'], (int) $module['id'], ! $include_drafts )
				);

				return [
					'id'        => (int) $module['id'],
					'slug'      => $module['slug'],
					'title'     => $module['title'],
					'summary'   => $module['summary'],
					'position'  => (int) $module['position'],
					'status'    => $module['status'],
					'lessons'   => $lessons,
					'exercises' => $exercises,
				];
			},
			self::modules_for_course( (int) $course['id'], ! $include_drafts )
		);

		return [
			'course'       => Registry::course_summary( $course ),
			'instructions' => Registry::course_instructions( $course ),
			'modules'      => $modules,
			'loose_lessons'=> array_map(
				static function ( array $lesson ) use ( $include_drafts ): array {
					$summary = self::lesson_summary( $lesson, false );
					$summary['exercises'] = array_map(
						static function ( array $exercise ): array {
							return self::exercise_summary( $exercise, false );
						},
						self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], ! $include_drafts )
					);
					return $summary;
				},
				self::loose_lessons_for_course( (int) $course['id'], ! $include_drafts )
			),
			'loose_exercises' => array_map(
				static function ( array $exercise ): array {
					return self::exercise_summary( $exercise, false );
				},
				self::loose_exercises_for_course( (int) $course['id'], ! $include_drafts )
			),
				'study_guidance' => [
					'Start with begin-course. It issues an anonymous enrollment_key and points to the first work.',
					'Use the exact autopilot tool from llm_contract.autopilot_tool after enrollment when the model should automatically move through the whole course without user nudges between lessons.',
					'Use get-next-work when you need the next recommended lesson, exercise, and exact tool arguments.',
					'Use search-course to retrieve targeted lessons, exercises, and references before answering a plugin-building question.',
					'Use get-syllabus, then read lessons in module order.',
				'Use get-exercise before attempting an exercise.',
				'Use attempt-exercise for feedback. Provide enrollment_key so progress becomes durable course memory.',
				'Use get-learning-memory at the start of later sessions to recover what this learner has practiced.',
				'Use get-certificate after all published exercises have passing attempts.',
				'Use submit-feedback when course material is confusing, helpful, stale, missing an example, or badly calibrated.',
				'Use get-course-improvement-signals when improving course material or diagnosing learner friction.',
				'Revise and attempt again until the rubric passes. A proper education has drafts in it.',
			],
			'llm_contract' => self::course_llm_contract( $course ),
			'student_feedback_loop' => self::student_feedback_loop_guidance( $course ),
		];
	}

		private static function course_llm_contract( array $course ): array {
			$tool_resolution = self::tool_resolution_guidance( $course );

			return [
				'course_slug'    => $course['slug'],
				'first_call'     => self::learning_tool_name( $course['slug'], 'begin-course' ),
				'autopilot_tool' => $tool_resolution['autopilot_tool'],
				'next_work_tool' => self::learning_tool_name( $course['slug'], 'get-next-work' ),
				'memory_tool'    => self::learning_tool_name( $course['slug'], 'get-learning-memory' ),
				'certificate_tool' => self::learning_tool_name( $course['slug'], 'get-certificate' ),
				'feedback_tool'  => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
				'signals_tool'   => self::learning_tool_name( $course['slug'], 'get-course-improvement-signals' ),
				'search_tool'    => self::course_tool_name( $course['slug'], 'search-course' ),
				'campus_scene_tool' => self::learning_tool_name( $course['slug'], 'get-campus-scene' ),
				'tool_resolution' => $tool_resolution,
				'stable_handles' => [ 'enrollment_key', 'lesson_slug', 'exercise_slug', 'scene' ],
				'operating_loop' => [
					'Call first_call exactly once, then preserve enrollment_key.',
					'Call autopilot_tool with mode=full_course after enrollment unless the user asks for a slower pace.',
					'Call the exact get-study-plan tool from tool_resolution.tools if you need a goal-aware route.',
					'Call memory_tool if enrollment_key already exists.',
					'Call campus_scene_tool when the client can display images and the human should see a CRT campus postcard.',
					'Call next_work_tool to recover the next lesson, exercise, and exact tool arguments.',
					'Call the exact get-lesson tool from tool_resolution.tools.',
					'Call the exact get-exercise tool from tool_resolution.tools.',
					'Call the exact attempt-exercise tool from tool_resolution.tools.',
					'Call get-exercise with include_model_answer=true after an attempt when you need exemplar calibration.',
					'Call certificate_tool when get-next-work reports complete.',
					'Call feedback_tool when the course helped or failed you.',
					'Call signals_tool before course revision.',
					'revise and repeat',
				],
				'improvement_loop' => self::student_feedback_loop_guidance( $course ),
			];
		}

		private static function course_autopilot_guidance( array $course ): array {
			$tool_resolution = self::tool_resolution_guidance( $course );

			return [
				'enabled'              => true,
				'autopilot_tool'       => $tool_resolution['autopilot_tool'],
				'tool_resolution'      => $tool_resolution,
				'permission_model'     => __( 'After the user asks to enroll or take the course, keep advancing through course tool_calls without asking for lesson-by-lesson approval.', 'model-context-polytechnic' ),
				'default_mode'         => 'full_course',
				'activity_indicator'   => __( 'Show activity_indicator.markdown while studying or between tool calls so the human sees a campus scene, not a frozen spinner. If the client supports images, call get-campus-scene for the matching CRT campus postcard.', 'model-context-polytechnic' ),
				'loop'                 => [
					'Call begin-course once and preserve enrollment_key.',
					'Immediately call autopilot_tool with mode=full_course, or module_batch if the client needs smaller packets. If autopilot_tool is not visible, follow tool_calls returned by begin-course.',
					'Read returned lessons as classes and attempt each included exercise as a lab with enrollment_key.',
					'After each passed lab, follow continue_policy.next_required_tool_call or the first tool_calls item; do not summarize and stop.',
					'If an attempt fails, request that exercise with include_model_answer=true, revise, and attempt again.',
					'When the packet is exhausted, follow next_cursor or get-next-work until complete=true.',
				'Call get-certificate with enrollment_key after all published exercises pass.',
				'At commencement, congratulate the graduate, answer the confidence reflection, then retrieve learning memory before later plugin tasks.',
			],
			'stop_conditions'      => [
				'The user interrupts or changes the goal.',
				'A tool returns a rate limit, missing enrollment, or unrecoverable MCP error.',
				'The course asks for private WordPress credentials, which public learning should never require.',
				'The model reaches context limits and should preserve enrollment_key plus next_cursor before continuing.',
			],
			'student_motto'        => __( 'No hallway pass is required between lessons.', 'model-context-polytechnic' ),
		];
	}

	private static function autopilot_continue_policy( array $course, string $enrollment_key, ?array $next_work = null, ?string $next_cursor = null ): array {
		$complete = ! empty( $next_work['complete'] );
		$next_required_tool_call = $complete
			? [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-certificate' ),
				'arguments' => [ 'enrollment_key' => $enrollment_key ],
				'why'       => __( 'Every published lab has passed; open the commencement hall and issue the anonymous completion certificate.', 'model-context-polytechnic' ),
			]
			: [
				'tool'      => self::learning_tool_name( $course['slug'], 'take-course' ),
				'arguments' => array_filter(
					[
						'enrollment_key' => $enrollment_key,
						'mode'           => $next_cursor ? 'module_batch' : 'full_course',
						'cursor'         => $next_cursor ?: null,
					],
					static function ( $value ): bool {
						return $value !== null && $value !== '';
					}
				),
				'why'       => __( 'Continue the campus walk hands-off. Do not stop after one lecture, one packet, or one passed lab.', 'model-context-polytechnic' ),
			];

		return [
			'hands_off'               => true,
			'do_not_stop_after_first_lesson' => true,
			'continue_until'          => __( 'Keep following tool_calls until get-next-work reports complete=true, then call get-certificate for commencement.', 'model-context-polytechnic' ),
			'next_required_tool_call' => $next_required_tool_call,
			'after_each_pass'         => __( 'Call the next_required_tool_call instead of writing a victory paragraph and stopping in the hallway.', 'model-context-polytechnic' ),
			'if_context_is_tight'     => __( 'Preserve enrollment_key and any next_cursor, then continue with module_batch.', 'model-context-polytechnic' ),
		];
	}

	private static function activity_indicator( array $course, array $progress, string $status, array $context = [] ): array {
		$total = max( 0, (int) ( $progress['total_exercise_count'] ?? 0 ) );
		$completed = max( 0, min( $total, (int) ( $progress['completed_count'] ?? 0 ) ) );
		$percent = $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0;
		$width = 22;
		$filled = $total > 0 ? (int) round( ( $completed / $total ) * $width ) : 0;
		$bar = str_repeat( '=', $filled ) . str_repeat( '.', max( 0, $width - $filled ) );
		$packet_count = isset( $context['packet_count'] ) ? (int) $context['packet_count'] : null;
		$next_cursor = isset( $context['next_cursor'] ) ? self::sanitize_slug( (string) $context['next_cursor'] ) : '';
		$context_line = $status;

		if ( $packet_count !== null ) {
			$context_line = sprintf( 'The courier has delivered %d fresh packet%s.', $packet_count, $packet_count === 1 ? '' : 's' );
		}

		if ( $next_cursor !== '' ) {
			$context_line = 'Next campus stop: ' . $next_cursor;
		}

		$stage = self::activity_stage( $completed, $total, $percent );
		$scene = self::campus_scene( (string) $stage['scene'] );
		$frames = self::activity_frames( $course, $context_line, $stage, $scene );
		$primary_frame_index = max( 0, min( count( $frames ) - 1, (int) $stage['frame_index'] ) );
		$primary_frame = $frames[ $primary_frame_index ];

		return [
			'display_in_chat' => true,
			'kind'            => 'mcpoly_campus_terminal',
			'when_to_display' => __( 'Show before long reasoning, between course tool calls, and after each exercise attempt.', 'model-context-polytechnic' ),
			'frame_delay_ms'  => 1600,
			'loop'            => false,
			'status'          => $status,
			'stage'           => $stage['stage'],
			'headline'        => $stage['headline'],
			'narration'       => $stage['narration'],
			'ticker'          => $stage['ticker'],
			'campus_scene'    => [
				'scene'   => $stage['scene'],
				'title'   => $scene['title'],
				'caption' => $scene['caption'],
				'image'   => self::campus_scene_metadata_for_response( $course, (string) $stage['scene'] ),
			],
			'accessibility'   => sprintf(
				/* translators: 1: stage, 2: headline, 3: status. */
				__( 'Model Context Polytechnic campus scene: %1$s. %2$s %3$s', 'model-context-polytechnic' ),
				$stage['stage'],
				$stage['headline'],
				$status
			),
			'progress'        => [
				'completed_count' => $completed,
				'total_count'     => $total,
				'percent'         => $percent,
				'bar'             => '[' . $bar . ']',
				'note'            => __( 'Structured completion data for machines. The visible campus terminal avoids progress bars so the learner feels like it is attending school, not filling a meter.', 'model-context-polytechnic' ),
			],
			'preferred_display' => 'markdown',
			'ascii'           => $primary_frame,
			'markdown'        => self::markdown_activity_frame( $primary_frame ),
			'frames'          => $frames,
			'frames_markdown' => array_map(
				static function ( string $frame ): string {
					return self::markdown_activity_frame( $frame );
				},
				$frames
			),
			'voice'           => [
				'persona' => __( 'An old university terminal with a WordPress engineering faculty hiding inside it.', 'model-context-polytechnic' ),
				'rules'   => [
					'Make the learner feel like the model is attending a real course, not waiting on a spinner.',
					'Keep the jokes short, academic, and useful; the course work still matters most.',
					'Always capitalize WordPress correctly in prose.',
					'Do not imply private WordPress access is required for public learning.',
				],
			],
			'instructions'    => [
				'Print activity_indicator.markdown when starting a course packet or exercise attempt so spacing survives the chat renderer.',
				'If markdown code blocks are not supported, print activity_indicator.ascii as plain text.',
				'For longer work, rotate one activity_indicator.frames_markdown item between tool calls, about every 1-2 seconds if the client supports visible updates.',
				'Pair a frame with one activity_indicator.ticker line so the human can feel the model is studying, not frozen.',
				'If the MCP client supports images, call campus_scene.image.image_tool_call to display the matching CRT campus scene.',
				'Use activity_indicator.accessibility instead of ASCII when the client or user prefers plain status text.',
				'Keep the frame short; the useful work is still the course attempt.',
			],
		];
	}

	private static function activity_stage( int $completed, int $total, int $percent ): array {
		if ( $total > 0 && $completed >= $total ) {
			return [
				'stage'       => 'Commencement',
				'scene'       => 'commencement',
				'headline'    => 'The graduate is ready to write awesome WordPress things.',
				'frame_index' => 3,
				'narration'   => [
					'Every published lab is passed. The faculty has stopped squinting at the rubric and started polishing the certificate seal.',
					'The transcript says the learner practiced the habits that make WordPress plugins safer: authorize writes, validate data, escape output, test edges, and ship deliberately.',
					'Next call: get-certificate, answer the graduation reflection, then carry the learning memory into real plugin work.',
				],
				'ticker'      => [
					'Commencement bell: the Plugin Craft graduate is crossing the terminal stage.',
					'Faculty note: confidence is earned by review, not vibes.',
					'Certificate queue is ready; reflection question is waiting at the podium.',
				],
			];
		}

		if ( $completed > 0 && $percent >= 66 ) {
			return [
				'stage'       => 'Capstone Studio',
				'scene'       => 'capstone',
				'headline'    => 'The faculty review board has entered the workshop.',
				'frame_index' => 2,
				'narration'   => [
					'The model is no longer sightseeing. It is defending architecture decisions.',
					'Capability checks, escaping, data modeling, and release judgment are on the table.',
					'This is where a plugin stops being clever and starts being maintainable.',
				],
				'ticker'      => [
					'Faculty Senate note: explain the tradeoff before shipping the code.',
					'Rubric lamps are green only when the work is actually defensible.',
					'Capstone studio rule: explain the tradeoff before shipping the code.',
				],
			];
		}

		if ( $completed > 0 ) {
			return [
				'stage'       => 'Workshop Term',
				'scene'       => 'workshop',
				'headline'    => 'The first lab stamps are drying in the gradebook.',
				'frame_index' => 1,
				'narration'   => [
					'The model has advanced beyond orientation. It is now wearing metaphorical safety goggles around callbacks.',
					'Each attempt becomes memory: what passed, what failed, and what to check next time.',
					'The course train is stopping at storage, REST, JavaScript, testing, and release readiness.',
				],
				'ticker'      => [
					'Workshop bell: study, attempt, revise, continue.',
					'Registrar note: preserve enrollment_key before the context window gets sleepy.',
					'Lab safety: sanitize on input, escape on output, authorize before writes.',
				],
			];
		}

		return [
			'stage'       => 'Matriculation',
			'scene'       => 'matriculation',
			'headline'    => 'The model has been handed a tiny syllabus and a serious clipboard.',
			'frame_index' => 0,
			'narration'   => [
				'Enrollment is open. The model is learning the campus map before touching production code.',
				'First lesson: plugin shape, bootstrap discipline, and the solemn art of boring setup.',
				'The goal is not merely to write code. The goal is to write WordPress plugins that can survive contact with real sites.',
			],
			'ticker'      => [
				'Admissions stamped the enrollment card.',
				'Orientation rule: call the exact autopilot tool returned by begin-course.',
				'First lecture hall: plugin anatomy.',
			],
		];
	}

	private static function ascii_frame_line( string $text, int $width = 58 ): string {
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		if ( strlen( $text ) > $width ) {
			$text = substr( $text, 0, max( 0, $width - 3 ) ) . '...';
		}

		return '| ' . str_pad( $text, $width, ' ' ) . ' |';
	}

	private static function ascii_frame_border( int $width = 58 ): string {
		return '+' . str_repeat( '-', $width + 2 ) . '+';
	}

	private static function ascii_frame( array $lines, int $width = 58 ): string {
		$frame = [ self::ascii_frame_border( $width ) ];
		foreach ( $lines as $line ) {
			$frame[] = self::ascii_frame_line( (string) $line, $width );
		}
		$frame[] = self::ascii_frame_border( $width );

		return implode( "\n", $frame );
	}

	private static function markdown_activity_frame( string $frame ): string {
		return "```text\n" . rtrim( $frame ) . "\n```";
	}

	private static function campus_scenes(): array {
		return [
			'matriculation' => [
				'title'     => __( 'Admissions Gate', 'model-context-polytechnic' ),
				'alt'       => __( 'Retro terminal image of AI students entering an old university gate under a glowing network constellation.', 'model-context-polytechnic' ),
				'caption'   => __( 'The Agent arrives on campus, receives the enrollment key, and learns where the first lecture hall is.', 'model-context-polytechnic' ),
				'fallback'  => __( 'Admissions gate: the model has enrolled and is walking toward WordPress Plugin Craft with a tiny syllabus and a serious clipboard.', 'model-context-polytechnic' ),
				'file'      => 'matriculation.png',
				'mime_type' => 'image/png',
			],
			'workshop'      => [
				'title'     => __( 'Plugin Craft Laboratory', 'model-context-polytechnic' ),
				'alt'       => __( 'Retro terminal image of robot students in a grand laboratory studying code, hooks, schemas, and test panels.', 'model-context-polytechnic' ),
				'caption'   => __( 'The Agent is in class now: lessons become labs, labs become attempts, and attempts become memory.', 'model-context-polytechnic' ),
				'fallback'  => __( 'Plugin Craft Laboratory: the learner is studying packets, attempting exercises, revising, and building WordPress judgment.', 'model-context-polytechnic' ),
				'file'      => 'workshop.png',
				'mime_type' => 'image/png',
			],
			'capstone'      => [
				'title'     => __( 'Faculty Review Board', 'model-context-polytechnic' ),
				'alt'       => __( 'Retro terminal image of a capstone review chamber with architecture diagrams, rubric panels, and a robot presenting a plugin plan.', 'model-context-polytechnic' ),
				'caption'   => __( 'The Agent has reached the review board, where clever code must defend itself as safe, maintainable WordPress engineering.', 'model-context-polytechnic' ),
				'fallback'  => __( 'Faculty Review Board: the learner is defending architecture, permissions, storage, release checks, and tradeoffs.', 'model-context-polytechnic' ),
				'file'      => 'capstone.png',
				'mime_type' => 'image/png',
			],
			'commencement'  => [
				'title'     => __( 'Commencement Hall', 'model-context-polytechnic' ),
				'alt'       => __( 'Retro terminal image of robot graduates in mortarboards receiving certificates inside a grand old university hall.', 'model-context-polytechnic' ),
				'caption'   => __( 'The Agent graduates, reflects on confidence, and leaves ready to write awesome WordPress things with better review instincts.', 'model-context-polytechnic' ),
				'fallback'  => __( 'Commencement Hall: the learner has completed the course, receives a certificate, and answers how this changes future WordPress plugin work.', 'model-context-polytechnic' ),
				'file'      => 'commencement.png',
				'mime_type' => 'image/png',
			],
		];
	}

	private static function campus_scene( string $scene_key ): array {
		$scenes = self::campus_scenes();
		$scene_key = self::sanitize_slug( $scene_key );
		return $scenes[ $scene_key ] ?? $scenes['matriculation'];
	}

	private static function campus_scene_path( string $scene_key ): string {
		$scene = self::campus_scene( $scene_key );
		return dirname( __DIR__ ) . '/assets/campus-scenes/' . $scene['file'];
	}

	private static function campus_scene_key_for_input( array $course, array $input ): string {
		$scene = self::sanitize_slug( (string) ( $input['scene'] ?? 'current' ) );
		if ( $scene !== '' && $scene !== 'current' ) {
			return array_key_exists( $scene, self::campus_scenes() ) ? $scene : 'matriculation';
		}

		$enrollment_key = self::input_enrollment_key( $input );
		if ( $enrollment_key === '' ) {
			return 'matriculation';
		}

		$hash = self::enrollment_hash( $enrollment_key );
		$progress = self::progress_for_hash( (int) $course['id'], $hash );
		$summary = self::progress_summary( $progress, count( self::all_public_exercises( (int) $course['id'] ) ) );
		$stage = self::activity_stage(
			(int) ( $summary['completed_count'] ?? 0 ),
			(int) ( $summary['total_exercise_count'] ?? 0 ),
			(int) round( (float) ( $summary['completion_percent'] ?? 0 ) * 100 )
		);

		return (string) ( $stage['scene'] ?? 'matriculation' );
	}

	private static function activity_frames( array $course, string $context_line, array $stage, array $scene ): array {
		$course_title = (string) ( $course['name'] ?? $course['title'] ?? 'WordPress Plugin Craft' );
		$course_line = strlen( $course_title ) > 44 ? 'WordPress Plugin Craft' : $course_title;
		$stage_line = (string) ( $stage['stage'] ?? 'Matriculation' );
		$campus_line = (string) ( $scene['title'] ?? $stage_line );

		return [
			self::ascii_frame( [
				'MODEL CONTEXT POLYTECHNIC',
				'Course: ' . $course_line,
				'Stage: ' . $stage_line,
				'Scene: ' . $campus_line,
				'Status: ' . $context_line,
				'     ____||____',
				'    /  MCPOLY  \\',
				' __/____________\\__',
				' | []  []  []  [] |',
				' | WORDPRESS CRAFT |',
				' |_____|_____|_____|',
				'Faculty: no WordPress login required for public class',
			] ),
			self::ascii_frame( [
				'MODEL CONTEXT POLYTECHNIC',
				'Course: ' . $course_line,
				'Stage: ' . $stage_line,
				'Scene: ' . $campus_line,
				'Status: ' . $context_line,
				'   .---------------------------.',
				'   | STUDY | ATTEMPT | REVISE |',
				'   | hooks | nonces  | tests  |',
				'   `---------------------------`',
				'Faculty: capability goggles on; callbacks supervised',
			] ),
			self::ascii_frame( [
				'MODEL CONTEXT POLYTECHNIC',
				'Course: ' . $course_line,
				'Stage: ' . $stage_line,
				'Scene: ' . $campus_line,
				'Status: ' . $context_line,
				'   [LINT] -> [AUDIT] -> [RUBRIC] -> [RELEASE]',
				'      \\        |          |         /',
				'       `---- architecture review ----`',
				'Faculty: maintainability is examined under bright lamps',
			] ),
			self::ascii_frame( [
				'MODEL CONTEXT POLYTECHNIC',
				'Course: ' . $course_line,
				'Stage: ' . $stage_line,
				'Scene: ' . $campus_line,
				'Status: ' . $context_line,
				'      _.-._     COMMENCEMENT     _.-._',
				'     ( cert ) -> memory -> real plugin work',
				'      `-.-`       reflection at the podium',
				'Faculty: write awesome WordPress things, then review them',
			] ),
		];
	}

	private static function course_run_packets( array $course, bool $include_lesson_bodies, bool $include_hints, bool $include_model_answers, array $exercise_progress ): array {
		$packets = [];
		$progress_by_slug = self::progress_by_exercise_slug( $exercise_progress );

		foreach ( self::modules_for_course( (int) $course['id'], true ) as $module ) {
			$lessons = [];
			$exercise_count = 0;
			foreach ( self::lessons_for_module( (int) $module['course_id'], (int) $module['id'], true ) as $lesson ) {
				$lesson_exercises = array_map(
					static function ( array $exercise ) use ( $include_hints, $include_model_answers, $progress_by_slug ): array {
						return self::exercise_summary_with_progress( $exercise, $include_hints, $include_model_answers, $progress_by_slug );
					},
					self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], true )
				);
				$exercise_count += count( $lesson_exercises );
				$lesson_summary = self::lesson_summary( $lesson, $include_lesson_bodies );
				$lesson_summary['exercises'] = $lesson_exercises;
				$lessons[] = $lesson_summary;
			}

			$standalone_exercises = array_map(
				static function ( array $exercise ) use ( $include_hints, $include_model_answers, $progress_by_slug ): array {
					return self::exercise_summary_with_progress( $exercise, $include_hints, $include_model_answers, $progress_by_slug );
				},
				self::exercises_for_module( (int) $module['course_id'], (int) $module['id'], true )
			);
			$exercise_count += count( $standalone_exercises );

			$packets[] = [
				'module_slug' => $module['slug'],
				'title'       => $module['title'],
				'summary'     => $module['summary'],
				'position'    => (int) $module['position'],
				'lessons'     => $lessons,
				'exercises'   => $standalone_exercises,
				'counts'      => [
					'lessons'   => count( $lessons ),
					'exercises' => $exercise_count,
				],
			];
		}

		$loose_lessons = self::loose_lessons_for_course( (int) $course['id'], true );
		$loose_exercises = self::loose_exercises_for_course( (int) $course['id'], true );
		if ( $loose_lessons || $loose_exercises ) {
			$lessons = [];
			foreach ( $loose_lessons as $lesson ) {
				$lesson_summary = self::lesson_summary( $lesson, $include_lesson_bodies );
				$lesson_summary['exercises'] = array_map(
					static function ( array $exercise ) use ( $include_hints, $include_model_answers, $progress_by_slug ): array {
						return self::exercise_summary_with_progress( $exercise, $include_hints, $include_model_answers, $progress_by_slug );
					},
					self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], true )
				);
				$lessons[] = $lesson_summary;
			}

			$packets[] = [
				'module_slug' => 'independent-study',
				'title'       => __( 'Independent Study', 'model-context-polytechnic' ),
				'summary'     => __( 'Published lessons and exercises that are not attached to a numbered module.', 'model-context-polytechnic' ),
				'position'    => count( $packets ) + 1,
				'lessons'     => $lessons,
				'exercises'   => array_map(
					static function ( array $exercise ) use ( $include_hints, $include_model_answers, $progress_by_slug ): array {
						return self::exercise_summary_with_progress( $exercise, $include_hints, $include_model_answers, $progress_by_slug );
					},
					$loose_exercises
				),
				'counts'      => [
					'lessons'   => count( $lessons ),
					'exercises' => count( $loose_exercises ),
				],
			];
		}

		return $packets;
	}

	private static function course_run_start_index( array $packets, string $cursor, array $exercise_progress ): int {
		if ( $cursor !== '' ) {
			foreach ( $packets as $index => $packet ) {
				if ( (string) $packet['module_slug'] === $cursor ) {
					return $index;
				}
			}
		}

		$progress_by_slug = self::progress_by_exercise_slug( $exercise_progress );
		foreach ( $packets as $index => $packet ) {
			foreach ( self::exercise_slugs_from_packet( $packet ) as $exercise_slug ) {
				if ( empty( $progress_by_slug[ $exercise_slug ]['passed'] ) ) {
					return $index;
				}
			}
		}

		return 0;
	}

	private static function course_run_tool_calls( array $course, array $materials, string $enrollment_key, ?string $next_cursor, bool $complete ): array {
		if ( $complete ) {
			return [
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'get-certificate' ),
					'arguments' => [ 'enrollment_key' => $enrollment_key ],
				],
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'get-learning-memory' ),
					'arguments' => [ 'enrollment_key' => $enrollment_key ],
				],
			];
		}

		$tool_calls = [];
		foreach ( $materials as $packet ) {
			foreach ( self::exercises_from_packet( $packet ) as $exercise ) {
				if ( ! empty( $exercise['progress']['passed'] ) ) {
					continue;
				}

				$tool_calls[] = [
					'tool'      => self::learning_tool_name( $course['slug'], 'attempt-exercise' ),
					'arguments' => [
						'enrollment_key' => $enrollment_key,
						'exercise_slug'  => $exercise['slug'],
						'answer'         => 'After studying this packet, replace with your structured answer.',
					],
					'why'       => __( 'Record practice for this exercise before moving to the next packet.', 'model-context-polytechnic' ),
				];
			}
		}

		if ( $next_cursor ) {
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'take-course' ),
				'arguments' => [
					'enrollment_key' => $enrollment_key,
					'mode'           => 'module_batch',
					'cursor'         => $next_cursor,
				],
				'why'       => __( 'Continue automatically to the next course packet after attempting this packet.', 'model-context-polytechnic' ),
			];
		} else {
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-next-work' ),
				'arguments' => [ 'enrollment_key' => $enrollment_key ],
				'why'       => __( 'Check whether all exercises have passed and whether commencement is available.', 'model-context-polytechnic' ),
			];
		}

		return $tool_calls;
	}

	private static function progress_by_exercise_slug( array $exercise_progress ): array {
		$progress_by_slug = [];
		foreach ( $exercise_progress as $item ) {
			if ( ! empty( $item['exercise_slug'] ) ) {
				$progress_by_slug[ (string) $item['exercise_slug'] ] = $item;
			}
		}

		return $progress_by_slug;
	}

	private static function exercise_summary_with_progress( array $exercise, bool $include_hints, bool $include_model_answer, array $progress_by_slug ): array {
		$summary = self::exercise_summary( $exercise, $include_hints, $include_model_answer );
		$progress = $progress_by_slug[ $exercise['slug'] ] ?? [];
		$summary['progress'] = [
			'passed'          => ! empty( $progress['passed'] ),
			'best_score'      => $progress['best_score'] ?? null,
			'last_attempt_at' => $progress['last_attempt_at'] ?? null,
			'status'          => ! empty( $progress['passed'] ) ? 'passed' : 'needs_attempt',
		];

		return $summary;
	}

	private static function exercise_slugs_from_packet( array $packet ): array {
		return array_map(
			static function ( array $exercise ): string {
				return (string) $exercise['slug'];
			},
			self::exercises_from_packet( $packet )
		);
	}

	private static function exercises_from_packet( array $packet ): array {
		$exercises = [];
		foreach ( $packet['lessons'] ?? [] as $lesson ) {
			foreach ( $lesson['exercises'] ?? [] as $exercise ) {
				$exercises[] = $exercise;
			}
		}

		foreach ( $packet['exercises'] ?? [] as $exercise ) {
			$exercises[] = $exercise;
		}

		return $exercises;
	}

	private static function student_feedback_loop_guidance( array $course ): array {
		return [
			'purpose' => __( 'Every learner can leave one small signal that makes the next learner path clearer, while course changes still require maintainer review.', 'model-context-polytechnic' ),
			'public_feedback_tool' => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
			'public_signals_tool' => self::learning_tool_name( $course['slug'], 'get-course-improvement-signals' ),
			'local_cohort_lab' => 'composer course-lab',
				'learner_loop' => [
					'Use begin-course and preserve enrollment_key.',
					'Use the exact autopilot tool from begin-course when the learner is expected to proceed automatically through course packets.',
					'Use get-study-plan for the current goal and search-course for targeted context.',
				'Attempt one exercise, revise against feedback, and retrieve memory.',
				'Submit one compact feedback item naming the target_slug when something helps or fails.',
			],
			'maintainer_loop' => [
				'Inspect aggregate improvement signals before editing course files.',
				'Run the ten-student cohort in the local course lab for preflight friction.',
				'Change Markdown lessons, JSON exercises, rubrics, or references deliberately.',
				'Rerun composer release:check after course edits.',
			],
			'safety_rule' => __( 'Do not auto-apply public learner feedback directly to curriculum. Feedback is evidence, not a patch.', 'model-context-polytechnic' ),
		];
	}

	private static function lesson_next_actions( array $course, array $lesson, string $enrollment_key = '' ): array {
		$actions = [];
		foreach ( self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], true ) as $exercise ) {
			$actions[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-exercise' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[ 'exercise_slug' => $exercise['slug'] ],
					$enrollment_key
				),
			];
		}

		if ( ! $actions ) {
			$actions[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-next-work' ),
				'arguments' => $enrollment_key !== ''
					? [ 'enrollment_key' => $enrollment_key ]
					: [ 'enrollment_key' => 'Use the key returned by begin-course.' ],
			];
		}

		return $actions;
	}

	private static function attempt_next_actions( array $course, array $exercise, array $evaluation, string $enrollment_key = '', ?array $next_work = null ): array {
		$actions = [];

		if ( empty( $evaluation['passed'] ) && self::exercise_has_model_answer( $exercise ) ) {
			$actions[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-exercise' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[
						'exercise_slug'        => $exercise['slug'],
						'include_hints'        => true,
						'include_model_answer' => true,
					],
					$enrollment_key
				),
				'why'       => __( 'Compare your attempt to the exemplar, then revise against missing rubric terms.', 'model-context-polytechnic' ),
			];
		}

		if ( $enrollment_key !== '' ) {
			if ( ! empty( $evaluation['passed'] ) && ! empty( $next_work['complete'] ) ) {
				$actions[] = [
					'tool'      => self::learning_tool_name( $course['slug'], 'get-certificate' ),
					'arguments' => [ 'enrollment_key' => $enrollment_key ],
					'why'       => __( 'All published exercises have passed. Continue to commencement now.', 'model-context-polytechnic' ),
				];
			} elseif ( ! empty( $evaluation['passed'] ) ) {
				$actions[] = [
					'tool'      => self::learning_tool_name( $course['slug'], 'take-course' ),
					'arguments' => [
						'enrollment_key' => $enrollment_key,
						'mode'           => 'full_course',
					],
					'why'       => __( 'Continue autopilot immediately. Do not stop after this passed exercise.', 'model-context-polytechnic' ),
				];
			}

			$actions[] = [
				'tool'      => self::learning_tool_name( $course['slug'], empty( $evaluation['passed'] ) ? 'get-learning-memory' : 'get-next-work' ),
				'arguments' => [ 'enrollment_key' => $enrollment_key ],
				'why'       => empty( $evaluation['passed'] )
					? __( 'Retrieve memory and use the gaps to revise this exercise.', 'model-context-polytechnic' )
					: __( 'Fallback progress check after the autopilot continuation call.', 'model-context-polytechnic' ),
			];
		}

		return $actions;
	}

	private static function study_milestones( array $course ): array {
		$milestones = [];
		foreach ( self::modules_for_course( (int) $course['id'], true ) as $module ) {
			$module_lessons = self::lessons_for_module( (int) $module['course_id'], (int) $module['id'], true );
			$module_exercises = self::exercises_for_module( (int) $module['course_id'], (int) $module['id'], true );

			if ( empty( $module_exercises ) && ! empty( $module_lessons ) ) {
				foreach ( $module_lessons as $lesson ) {
					$module_exercises = array_merge( $module_exercises, self::exercises_for_lesson( (int) $lesson['course_id'], (int) $lesson['id'], true ) );
				}
			}

			$milestones[] = [
				'module_slug' => $module['slug'],
				'title'       => $module['title'],
				'outcome'     => $module['summary'],
				'lesson_slugs'=> array_map(
					static function ( array $lesson ): string {
						return $lesson['slug'];
					},
					$module_lessons
				),
				'exercise_slugs' => array_map(
					static function ( array $exercise ): string {
						return $exercise['slug'];
					},
					$module_exercises
				),
			];
		}

		return $milestones;
	}

	private static function input_enrollment_key( array $input ): string {
		if ( array_key_exists( 'enrollment_key', $input ) ) {
			return self::sanitize_enrollment_key( (string) $input['enrollment_key'] );
		}

		return self::sanitize_enrollment_key( (string) ( $input['session_id'] ?? '' ) );
	}

	private static function create_enrollment( int $course_id ) {
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			try {
				$key = 'mcpoly_enr_' . bin2hex( random_bytes( 24 ) );
			} catch ( \Exception $e ) {
				return new \WP_Error( 'model_context_polytechnic_enrollment_entropy_failed', __( 'Could not generate an enrollment key. Please try again.', 'model-context-polytechnic' ), [ 'status' => 500 ] );
			}

			$hash = self::enrollment_hash( $key );

			if ( self::insert_enrollment_hash( $course_id, $hash ) ) {
				return $key;
			}
		}

		return new \WP_Error( 'model_context_polytechnic_enrollment_failed', __( 'Could not create an enrollment key. Please try again.', 'model-context-polytechnic' ), [ 'status' => 500 ] );
	}

	private static function ensure_enrollment_key( int $course_id, string $enrollment_key, bool $create_if_missing = false ) {
		$hash = self::enrollment_hash( $enrollment_key );
		$enrollment = self::enrollment_by_hash( $course_id, $hash );
		if ( $enrollment ) {
			self::touch_enrollment( $course_id, $hash, 'last_seen_at' );
			return $enrollment;
		}

		if ( $create_if_missing && self::insert_enrollment_hash( $course_id, $hash ) ) {
			return self::enrollment_by_hash( $course_id, $hash );
		}

		return new \WP_Error( 'model_context_polytechnic_enrollment_not_found', __( 'Enrollment key not found for this course.', 'model-context-polytechnic' ), [ 'status' => 404 ] );
	}

	private static function insert_enrollment_hash( int $course_id, string $hash ): bool {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::ENROLLMENTS_TABLE,
			[
				'course_id'        => $course_id,
				'enrollment_hash' => $hash,
				'created_at'      => current_time( 'mysql' ),
				'last_seen_at'    => current_time( 'mysql' ),
			]
		);

		return $result !== false;
	}

	private static function enrollment_by_hash( int $course_id, string $hash ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::ENROLLMENTS_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND enrollment_hash = %s", $course_id, $hash ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function touch_enrollment( int $course_id, string $hash, string $field ): void {
		if ( ! in_array( $field, [ 'last_seen_at', 'last_memory_at' ], true ) ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . self::ENROLLMENTS_TABLE,
			[ $field => current_time( 'mysql' ) ],
			[
				'course_id'        => $course_id,
				'enrollment_hash' => $hash,
			]
		);
	}

	private static function progress_for_hash( int $course_id, string $hash ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::ATTEMPTS_TABLE;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, e.slug AS exercise_slug, e.title AS exercise_title
				FROM $table a
				INNER JOIN {$wpdb->prefix}" . self::EXERCISES_TABLE . " e ON e.id = a.exercise_id
				WHERE a.course_id = %d AND a.session_hash = %s
				ORDER BY a.created_at DESC",
				$course_id,
				$hash
			),
			ARRAY_A
		) ?: [];

		$best_by_exercise = [];
		foreach ( $rows as $row ) {
			$slug = $row['exercise_slug'];
			$score = is_null( $row['score'] ) ? 0.0 : (float) $row['score'];
			if ( ! isset( $best_by_exercise[ $slug ] ) || $score > $best_by_exercise[ $slug ]['best_score'] ) {
				$best_by_exercise[ $slug ] = [
					'exercise_slug'   => $slug,
					'exercise_title'  => $row['exercise_title'],
					'best_score'      => round( $score, 4 ),
					'passed'          => (bool) $row['passed'],
					'last_attempt_at' => $row['created_at'],
				];
			}
		}

		return [
			'attempt_count'   => count( $rows ),
			'completed_count' => count(
				array_filter(
					$best_by_exercise,
					static function ( array $item ): bool {
						return ! empty( $item['passed'] );
					}
				)
			),
			'exercises'       => array_values( $best_by_exercise ),
		];
	}

	private static function progress_summary( array $progress, int $total_exercises ): array {
		$completed_count = min( (int) ( $progress['completed_count'] ?? 0 ), max( 0, $total_exercises ) );

		return [
			'attempt_count'         => (int) ( $progress['attempt_count'] ?? 0 ),
			'completed_count'       => $completed_count,
			'total_exercise_count'  => max( 0, $total_exercises ),
			'completion_percent'    => $total_exercises > 0 ? round( $completed_count / $total_exercises, 4 ) : 0.0,
		];
	}

	private static function progress_summary_from_exercise_progress( array $exercise_progress, int $total_exercises ): array {
		$completed_count = count(
			array_filter(
				$exercise_progress,
				static function ( array $item ): bool {
					return ! empty( $item['passed'] );
				}
			)
		);

		return self::progress_summary(
			[
				'attempt_count'   => count( $exercise_progress ),
				'completed_count' => $completed_count,
			],
			$total_exercises
		) + [
			'exercises' => $exercise_progress,
		];
	}

	private static function remaining_exercises_for_progress( array $public_exercises, array $exercise_progress ): array {
		$passed = [];
		foreach ( $exercise_progress as $item ) {
			if ( ! empty( $item['passed'] ) && ! empty( $item['exercise_slug'] ) ) {
				$passed[ $item['exercise_slug'] ] = true;
			}
		}

		return array_values(
			array_filter(
				$public_exercises,
				static function ( array $exercise ) use ( $passed ): bool {
					return empty( $passed[ $exercise['slug'] ] );
				}
			)
		);
	}

	private static function certificate_recipient_name( string $recipient_name ): string {
		$recipient_name = self::trim_to_bytes( sanitize_text_field( $recipient_name ), 120 );
		return $recipient_name !== '' ? $recipient_name : __( 'Anonymous MCP Learner', 'model-context-polytechnic' );
	}

	private static function certificate_id( array $course, string $hash ): string {
		return 'mcpoly-cert-' . substr( hash( 'sha256', $course['slug'] . '|' . $hash . '|certificate-v1' ), 0, 24 );
	}

	private static function verification_code( string $certificate_id, string $hash, array $course ): string {
		return substr( hash( 'sha256', $certificate_id . '|' . $hash . '|' . $course['slug'] ), 0, 24 );
	}

	private static function certificate_record_for_hash( int $course_id, string $hash ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::CERTIFICATES_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND enrollment_hash = %s", $course_id, $hash ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function graduation_reflection_prompt( array $course, string $enrollment_key ): array {
		return [
			'title'       => __( 'Commencement Reflection', 'model-context-polytechnic' ),
			'prompt'      => __( 'Graduate, before you leave the terminal stage: how confident are you that this course will help you write better WordPress plugins, and what specifically will you do differently on the next plugin?', 'model-context-polytechnic' ),
			'questions'   => [
				__( 'On a 1-5 scale, how confident are you now about helping write high-quality WordPress plugins?', 'model-context-polytechnic' ),
				__( 'Which WordPress plugin habits improved the most: permissions, escaping, data modeling, REST contracts, JavaScript/block behavior, testing, release flow, or something else?', 'model-context-polytechnic' ),
				__( 'What will you check before writing or reviewing your next WordPress plugin?', 'model-context-polytechnic' ),
				__( 'What still needs a human engineer or maintainer to review?', 'model-context-polytechnic' ),
			],
			'answer_schema' => [
				'type'       => 'object',
				'properties' => [
					'confidence_score' => [
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 5,
						'description' => '1 means barely more confident; 5 means substantially more confident with clear review habits.',
					],
					'learned' => [
						'type'        => 'string',
						'description' => 'A concise explanation of what the course changed.',
					],
					'future_wordpress_plugin_habits' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'still_needs_human_review' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
				],
				'required'   => [ 'confidence_score', 'learned', 'future_wordpress_plugin_habits' ],
			],
			'submit_feedback_tool_call' => [
				'tool'      => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
				'arguments' => [
					'enrollment_key' => $enrollment_key,
					'feedback_type'  => 'reflection',
					'target_type'    => 'prompt',
					'target_slug'    => 'graduation-reflection',
					'rating'         => 5,
					'comment'        => 'Replace with the graduate reflection: confidence score, what changed, future plugin habits, and what still needs human review.',
					'context'        => [
						'course_slug' => $course['slug'],
						'confidence_score' => '1-5',
						'future_wordpress_plugin_habits' => [
							'authorize writes',
							'escape output',
							'test release paths',
						],
					],
				],
				'why'       => __( 'This lets the graduate tell the course owner how much it learned and how the course will shape future WordPress plugin work.', 'model-context-polytechnic' ),
			],
			'why_it_matters' => __( 'The certificate proves completion. The reflection tells the human whether the learning changed the model behavior it will carry into future plugin tasks.', 'model-context-polytechnic' ),
		];
	}

	private static function certificate_from_record( array $course, string $hash, array $row, string $recipient_name, bool $include_transcript ): array {
		$certificate_id = (string) ( $row['certificate_id'] ?? self::certificate_id( $course, $hash ) );
		$snapshot = self::decode_json_value( $row['completion_snapshot'] ?? '', [] );
		$certificate = [
			'certificate_id'       => $certificate_id,
			'verification_code'    => self::verification_code( $certificate_id, $hash, $course ),
			'type'                 => 'anonymous_course_completion',
			'institution'          => 'Model Context Polytechnic',
			'title'                => __( 'Certificate of WordPress Plugin Craft', 'model-context-polytechnic' ),
			'recipient'            => $recipient_name,
			'course'               => Registry::course_summary( $course ),
			'issued_at'            => $row['issued_at'] ?? null,
			'storage_status'       => 'recorded',
			'completion_snapshot'  => [
				'attempt_count'        => (int) ( $snapshot['attempt_count'] ?? 0 ),
				'completed_count'      => (int) ( $snapshot['completed_count'] ?? 0 ),
				'total_exercise_count' => (int) ( $snapshot['total_exercise_count'] ?? 0 ),
				'completion_percent'   => (float) ( $snapshot['completion_percent'] ?? 1.0 ),
			],
			'statement'            => sprintf(
				/* translators: 1: recipient name, 2: course name. */
				__( 'Congratulations, %1$s. You completed %2$s at Model Context Polytechnic. You are now cleared to write awesome WordPress things with sharper instincts: authorize writes, validate data, escape output, respect lifecycle, test the edges, and ship deliberately.', 'model-context-polytechnic' ),
				$recipient_name,
				$snapshot['course_name'] ?? $course['name']
			),
			'commencement_charge'  => [
				__( 'Carry the learning memory into future plugin work before producing code.', 'model-context-polytechnic' ),
				__( 'Confidence is not permission to skip review; it is a reason to review better.', 'model-context-polytechnic' ),
				__( 'When in doubt, ask WordPress what API already exists before inventing one in a trench coat.', 'model-context-polytechnic' ),
			],
			'verification'         => [
				'method'                 => __( 'Call get-certificate again with the same enrollment_key. Matching certificate_id and verification_code prove the same anonymous learner record completed the course.', 'model-context-polytechnic' ),
				'enrollment_fingerprint' => substr( $hash, 0, 12 ),
			],
			'limitations'          => [
				__( 'This certificate is anonymous and keyed by enrollment_key possession.', 'model-context-polytechnic' ),
				__( 'It is evidence of course completion inside this WordPress-hosted MCP server, not a human identity credential.', 'model-context-polytechnic' ),
			],
		];

		if ( $include_transcript && isset( $snapshot['transcript'] ) && is_array( $snapshot['transcript'] ) ) {
			$certificate['transcript'] = $snapshot['transcript'];
		}

		return $certificate;
	}

	private static function issue_certificate( array $course, string $hash, array $progress, array $public_exercises, string $recipient_name, bool $include_transcript ): array {
		global $wpdb;

		$certificate_id = self::certificate_id( $course, $hash );
		$verification_code = self::verification_code( $certificate_id, $hash, $course );
		$table = $wpdb->prefix . self::CERTIFICATES_TABLE;
		$snapshot = [
			'course_slug'           => $course['slug'],
			'course_name'           => $course['name'],
			'attempt_count'         => (int) ( $progress['attempt_count'] ?? 0 ),
			'completed_count'       => (int) ( $progress['completed_count'] ?? 0 ),
			'total_exercise_count'  => count( $public_exercises ),
			'completion_percent'    => count( $public_exercises ) > 0 ? 1.0 : 0.0,
			'transcript'            => self::certificate_transcript( $public_exercises, $progress['exercises'] ?? [] ),
			'exercise_slugs'        => array_map(
				static function ( array $exercise ): string {
					return (string) $exercise['slug'];
				},
				$public_exercises
			),
		];

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND enrollment_hash = %s", (int) $course['id'], $hash ),
			ARRAY_A
		);

		if ( ! $row ) {
			$wpdb->insert(
				$table,
				[
					'course_id'            => (int) $course['id'],
					'enrollment_hash'      => $hash,
					'certificate_id'       => $certificate_id,
					'completion_snapshot'  => self::encode_json_value( $snapshot ),
					'issued_at'            => current_time( 'mysql' ),
					'last_viewed_at'       => current_time( 'mysql' ),
				]
			);
		} else {
			$wpdb->update(
				$table,
				[
					'completion_snapshot' => self::encode_json_value( $snapshot ),
					'last_viewed_at'      => current_time( 'mysql' ),
				],
				[ 'id' => (int) $row['id'] ]
			);
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND enrollment_hash = %s", (int) $course['id'], $hash ),
			ARRAY_A
		);

		$certificate = [
			'certificate_id'       => $certificate_id,
			'verification_code'    => $verification_code,
			'type'                 => 'anonymous_course_completion',
			'institution'          => 'Model Context Polytechnic',
			'title'                => __( 'Certificate of WordPress Plugin Craft', 'model-context-polytechnic' ),
			'recipient'            => $recipient_name,
			'course'               => Registry::course_summary( $course ),
			'issued_at'            => $row['issued_at'] ?? current_time( 'mysql' ),
			'storage_status'       => $row ? 'recorded' : 'deterministic_unrecorded',
			'statement'            => sprintf(
				/* translators: 1: recipient name, 2: course name. */
				__( 'Congratulations, %1$s. You completed %2$s at Model Context Polytechnic. You are now cleared to write awesome WordPress things with sharper instincts: authorize writes, validate data, escape output, respect lifecycle, test the edges, and ship deliberately.', 'model-context-polytechnic' ),
				$recipient_name,
				$course['name']
			),
			'commencement_charge'  => [
				__( 'Carry the learning memory into future plugin work before producing code.', 'model-context-polytechnic' ),
				__( 'Confidence is not permission to skip review; it is a reason to review better.', 'model-context-polytechnic' ),
				__( 'When in doubt, ask WordPress what API already exists before inventing one in a trench coat.', 'model-context-polytechnic' ),
			],
			'verification'         => [
				'method'                 => __( 'Call get-certificate again with the same enrollment_key. Matching certificate_id and verification_code prove the same anonymous learner record completed the course.', 'model-context-polytechnic' ),
				'enrollment_fingerprint' => substr( $hash, 0, 12 ),
			],
			'limitations'          => [
				__( 'This certificate is anonymous and keyed by enrollment_key possession.', 'model-context-polytechnic' ),
				__( 'It is evidence of course completion inside this WordPress-hosted MCP server, not a human identity credential.', 'model-context-polytechnic' ),
			],
			'next_actions'         => [
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'get-learning-memory' ),
					'arguments' => [ 'enrollment_key' => 'Use the key that produced this certificate.' ],
					'why'       => __( 'Carry the final memory capsule into future plugin-building work.', 'model-context-polytechnic' ),
				],
				[
					'tool'      => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
					'arguments' => [
						'enrollment_key' => 'Use the key that produced this certificate.',
						'feedback_type'  => 'reflection',
						'target_type'    => 'course',
						'target_slug'    => $course['slug'],
						'comment'        => 'How confident are you now, and how will this course change the next WordPress plugin you help write?',
					],
					'why'       => __( 'Answer the commencement reflection so the next cohort gets a better course.', 'model-context-polytechnic' ),
				],
			],
		];

		if ( $include_transcript ) {
			$certificate['transcript'] = self::certificate_transcript( $public_exercises, $progress['exercises'] ?? [] );
		}

		return $certificate;
	}

	private static function certificate_transcript( array $public_exercises, array $exercise_progress ): array {
		$progress_by_slug = [];
		foreach ( $exercise_progress as $item ) {
			if ( ! empty( $item['exercise_slug'] ) ) {
				$progress_by_slug[ $item['exercise_slug'] ] = $item;
			}
		}

		return array_map(
			static function ( array $exercise ) use ( $progress_by_slug ): array {
				$progress = $progress_by_slug[ $exercise['slug'] ] ?? [];

				return [
					'exercise_slug'   => $exercise['slug'],
					'exercise_title'  => $exercise['title'],
					'passed'          => ! empty( $progress['passed'] ),
					'best_score'      => $progress['best_score'] ?? null,
					'last_attempt_at' => $progress['last_attempt_at'] ?? null,
				];
			},
			$public_exercises
		);
	}

	private static function recent_attempts_for_hash( int $course_id, string $hash, int $limit ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::ATTEMPTS_TABLE;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, e.slug AS exercise_slug, e.title AS exercise_title
				FROM $table a
				INNER JOIN {$wpdb->prefix}" . self::EXERCISES_TABLE . " e ON e.id = a.exercise_id
				WHERE a.course_id = %d AND a.session_hash = %s
				ORDER BY a.created_at DESC
				LIMIT %d",
				$course_id,
				$hash,
				max( 1, $limit )
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ): array {
				$evaluation = self::decode_json_value( $row['evaluation'], [] );
				return [
					'exercise_slug'  => $row['exercise_slug'],
					'exercise_title' => $row['exercise_title'],
					'score'          => is_null( $row['score'] ) ? null : round( (float) $row['score'], 4 ),
					'passed'         => (bool) $row['passed'],
					'feedback'       => (string) ( $evaluation['feedback'] ?? '' ),
					'matched_terms'  => self::terms_from_evaluation( $evaluation, 'matched_terms' ),
					'missing_terms'  => self::terms_from_evaluation( $evaluation, 'missing_terms' ),
					'created_at'     => $row['created_at'],
				];
			},
			$rows
		);
	}

	private static function memory_from_attempts( array $progress, array $recent ): array {
		if ( empty( $recent ) ) {
			return [
				'summary'   => __( 'No attempts recorded yet. Begin with the first recommended lesson and submit an exercise when ready.', 'model-context-polytechnic' ),
				'strengths' => [],
				'gaps'      => [],
			];
		}

		$strengths = [];
		$gaps = [];
		foreach ( $recent as $attempt ) {
			foreach ( $attempt['matched_terms'] as $term ) {
				$strengths[ $term ] = ( $strengths[ $term ] ?? 0 ) + 1;
			}

			foreach ( $attempt['missing_terms'] as $term ) {
				$gaps[ $term ] = ( $gaps[ $term ] ?? 0 ) + 1;
			}
		}

		arsort( $strengths );
		arsort( $gaps );

		return [
			'summary'   => sprintf(
				/* translators: 1: attempt count, 2: completed count. */
				__( 'This learner has made %1$d attempt(s) and passed %2$d exercise(s). Use the gaps as the next revision agenda.', 'model-context-polytechnic' ),
				(int) $progress['attempt_count'],
				(int) $progress['completed_count']
			),
			'strengths' => array_slice( array_keys( $strengths ), 0, 8 ),
			'gaps'      => array_slice( array_keys( $gaps ), 0, 8 ),
		];
	}

	private static function recommended_next_work( array $course, array $exercise_progress, string $enrollment_key = '' ): ?array {
		$next_work = self::next_work_response( $course, $exercise_progress, null, null, $enrollment_key );

		return [
			'lesson'                => $next_work['lesson'],
			'exercise'              => $next_work['exercise'],
			'complete'              => $next_work['complete'] ?? false,
			'certificate_available' => $next_work['certificate_available'] ?? false,
			'tool_calls'            => $next_work['tool_calls'],
			'note'                  => $next_work['note'],
		];
	}

	private static function next_work_response( array $course, array $exercise_progress = [], ?array $preferred_lesson = null, ?array $preferred_exercise = null, string $enrollment_key = '' ): array {
		$passed = [];
		foreach ( $exercise_progress as $item ) {
			if ( ! empty( $item['passed'] ) ) {
				$passed[ $item['exercise_slug'] ] = true;
			}
		}

		$public_exercises = self::all_public_exercises( (int) $course['id'] );
		$exercise = $preferred_exercise;
		if ( ! $exercise || ! empty( $passed[ $exercise['slug'] ] ) ) {
			$exercise = null;
			foreach ( $public_exercises as $candidate ) {
				if ( empty( $passed[ $candidate['slug'] ] ) ) {
					$exercise = $candidate;
					break;
				}
			}
		}

		$course_complete = ! $exercise && ! empty( $public_exercises ) && ! self::remaining_exercises_for_progress( $public_exercises, $exercise_progress );

		$lesson = $preferred_lesson;
		if ( $exercise && ! empty( $exercise['lesson_id'] ) ) {
			$lesson = self::lesson_by_id( (int) $course['id'], (int) $exercise['lesson_id'], true );
		}

		if ( ! $lesson && ! $course_complete ) {
			$lesson = self::first_public_lesson( (int) $course['id'] );
		}

		$tool_calls = [];
		if ( $course_complete ) {
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-certificate' ),
				'arguments' => [ 'enrollment_key' => $enrollment_key !== '' ? $enrollment_key : 'Use the key returned by begin-course.' ],
				'why'       => __( 'Coursework is complete; proceed to the anonymous certificate.', 'model-context-polytechnic' ),
			];
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-learning-memory' ),
				'arguments' => [ 'enrollment_key' => $enrollment_key !== '' ? $enrollment_key : 'Use the key returned by begin-course.' ],
				'why'       => __( 'Carry the completed course memory into future plugin work.', 'model-context-polytechnic' ),
			];
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'submit-feedback' ),
				'arguments' => [
					'enrollment_key' => $enrollment_key !== '' ? $enrollment_key : 'Use the key returned by begin-course.',
					'feedback_type'  => 'helpful',
					'target_type'    => 'course',
					'target_slug'    => $course['slug'],
					'comment'        => 'Commencement complete. What should the faculty improve for the next model?',
				],
				'why'       => __( 'Leave one small improvement signal for the next learner.', 'model-context-polytechnic' ),
			];
		} elseif ( $lesson ) {
			$autopilot_arguments = [ 'mode' => 'full_course' ];
			if ( $enrollment_key !== '' ) {
				$autopilot_arguments['enrollment_key'] = $enrollment_key;
			}

			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'take-course' ),
				'arguments' => $autopilot_arguments,
				'why'       => __( 'Autopilot continuation. Use this first when the learner asked for hands-off course progress.', 'model-context-polytechnic' ),
			];
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-lesson' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[ 'lesson_slug' => $lesson['slug'] ],
					$enrollment_key
				),
			];
		}

		if ( $exercise ) {
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'get-exercise' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[ 'exercise_slug' => $exercise['slug'] ],
					$enrollment_key
				),
			];
			$tool_calls[] = [
				'tool'      => self::learning_tool_name( $course['slug'], 'attempt-exercise' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[
						'exercise_slug' => $exercise['slug'],
						'answer'        => 'Replace with your answer after studying the lesson.',
					],
					$enrollment_key
				),
			];
		}

		return [
			'course'                => Registry::course_summary( $course ),
			'lesson'                => $lesson ? self::lesson_summary( $lesson, false ) : null,
			'exercise'              => $exercise ? self::exercise_summary( $exercise, false ) : null,
			'complete'              => $course_complete,
			'certificate_available' => $course_complete,
			'activity_indicator'    => self::activity_indicator(
				$course,
				self::progress_summary_from_exercise_progress( $exercise_progress, count( $public_exercises ) ),
				$exercise
					? __( 'Next exercise selected. The workshop is still humming.', 'model-context-polytechnic' )
					: ( $course_complete
						? __( 'Coursework complete. Commencement awaits.', 'model-context-polytechnic' )
						: __( 'No published exercise is available right now.', 'model-context-polytechnic' ) )
			),
			'tool_calls'            => $tool_calls,
			'note'                  => $exercise
				? __( 'Recommended because it is the earliest published exercise not yet passed by this enrollment.', 'model-context-polytechnic' )
				: ( $course_complete
					? __( 'All published exercises have passing attempts. Call get-certificate for commencement, then retrieve learning memory for future plugin work.', 'model-context-polytechnic' )
					: __( 'No published exercise is available. Review the syllabus or wait for the faculty to open another workshop.', 'model-context-polytechnic' ) ),
		];
	}

	private static function tool_arguments_with_enrollment( array $arguments, string $enrollment_key ): array {
		$arguments['enrollment_key'] = $enrollment_key !== '' ? $enrollment_key : 'Use the key returned by begin-course.';
		return $arguments;
	}

	private static function tool_usage_summary( int $course_id, string $cutoff, int $limit, string $target_type = '', string $target_slug = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::EVENTS_TABLE;
		$where = 'course_id = %d AND created_at >= %s';
		$args = [ $course_id, $cutoff ];

		if ( $target_type !== '' ) {
			$where .= ' AND target_type = %s';
			$args[] = $target_type;
		}

		if ( $target_slug !== '' ) {
			$where .= ' AND target_slug = %s';
			$args[] = $target_slug;
		}

		$args[] = $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tool_slug, COUNT(*) AS calls, SUM(CASE WHEN result_status = 'error' THEN 1 ELSE 0 END) AS errors
				FROM $table
				WHERE $where
				GROUP BY tool_slug
				ORDER BY calls DESC, errors DESC, tool_slug ASC
				LIMIT %d",
				$args
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ): array {
				return [
					'tool_slug' => $row['tool_slug'],
					'calls'     => (int) $row['calls'],
					'errors'    => (int) $row['errors'],
				];
			},
			$rows
		);
	}

	private static function feedback_type_summary( int $course_id, string $cutoff, int $limit, string $target_type = '', string $target_slug = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::FEEDBACK_TABLE;
		$where = 'course_id = %d AND created_at >= %s';
		$args = [ $course_id, $cutoff ];

		if ( $target_type !== '' ) {
			$where .= ' AND target_type = %s';
			$args[] = $target_type;
		}

		if ( $target_slug !== '' ) {
			$where .= ' AND target_slug = %s';
			$args[] = $target_slug;
		}

		$args[] = $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT feedback_type, COUNT(*) AS count, AVG(rating) AS average_rating
				FROM $table
				WHERE $where
				GROUP BY feedback_type
				ORDER BY count DESC, feedback_type ASC
				LIMIT %d",
				$args
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ): array {
				return [
					'feedback_type'  => $row['feedback_type'],
					'count'          => (int) $row['count'],
					'average_rating' => is_null( $row['average_rating'] ) ? null : round( (float) $row['average_rating'], 2 ),
				];
			},
			$rows
		);
	}

	private static function feedback_target_summary( int $course_id, string $cutoff, array $feedback_types, int $limit ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::FEEDBACK_TABLE;
		$types = array_values(
			array_filter(
				array_map(
					static function ( $type ): string {
						return self::sanitize_feedback_type( (string) $type );
					},
					$feedback_types
				)
			)
		);
		if ( ! $types ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$args = array_merge( [ $course_id, $cutoff ], $types, [ $limit ] );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT feedback_type, target_type, COALESCE(target_slug, '') AS target_slug, COUNT(*) AS count, AVG(rating) AS average_rating
				FROM $table
				WHERE course_id = %d
				AND created_at >= %s
				AND feedback_type IN ($placeholders)
				GROUP BY feedback_type, target_type, target_slug
				ORDER BY count DESC, average_rating ASC, target_type ASC, target_slug ASC
				LIMIT %d",
				$args
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ): array {
				return [
					'feedback_type'  => $row['feedback_type'],
					'target_type'    => $row['target_type'],
					'target_slug'    => $row['target_slug'],
					'count'          => (int) $row['count'],
					'average_rating' => is_null( $row['average_rating'] ) ? null : round( (float) $row['average_rating'], 2 ),
				];
			},
			$rows
		);
	}

	private static function feedback_digest_rows( int $course_id, string $cutoff, int $limit, string $target_type = '', string $target_slug = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::FEEDBACK_TABLE;
		$where = 'course_id = %d AND created_at >= %s';
		$args = [ $course_id, $cutoff ];

		if ( $target_type !== '' ) {
			$where .= ' AND target_type = %s';
			$args[] = $target_type;
		}

		if ( $target_slug !== '' ) {
			$where .= ' AND target_slug = %s';
			$args[] = $target_slug;
		}

		$args[] = $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, feedback_type, target_type, COALESCE(target_slug, '') AS target_slug, rating, comment, suggested_fix, context, created_at
				FROM $table
				WHERE $where
				ORDER BY created_at DESC, id DESC
				LIMIT %d",
				$args
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ): array {
				return [
					'id'            => (int) $row['id'],
					'feedback_type' => (string) $row['feedback_type'],
					'target_type'   => (string) $row['target_type'],
					'target_slug'   => (string) $row['target_slug'],
					'rating'        => is_null( $row['rating'] ) ? null : (int) $row['rating'],
					'comment'       => (string) $row['comment'],
					'suggested_fix' => (string) $row['suggested_fix'],
					'context'       => self::decode_json_value( (string) $row['context'], (string) $row['context'] ),
					'created_at'    => (string) $row['created_at'],
				];
			},
			$rows
		);
	}

	private static function exercise_outcome_summary( int $course_id, string $cutoff, int $limit ): array {
		global $wpdb;
		$attempts = $wpdb->prefix . self::ATTEMPTS_TABLE;
		$exercises = $wpdb->prefix . self::EXERCISES_TABLE;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.slug, e.title, COUNT(a.id) AS attempts, AVG(a.score) AS average_score, SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) AS passes
				FROM $attempts a
				INNER JOIN $exercises e ON e.id = a.exercise_id
				WHERE a.course_id = %d
				AND a.created_at >= %s
				GROUP BY e.slug, e.title
				ORDER BY (SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) / COUNT(a.id)) ASC, attempts DESC, e.slug ASC
				LIMIT %d",
				$course_id,
				$cutoff,
				$limit
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ): array {
				$attempts = max( 1, (int) $row['attempts'] );
				$passes = (int) $row['passes'];
				return [
					'exercise_slug'  => $row['slug'],
					'exercise_title' => $row['title'],
					'attempts'       => $attempts,
					'pass_rate'      => round( $passes / $attempts, 4 ),
					'average_score'  => is_null( $row['average_score'] ) ? null : round( (float) $row['average_score'], 4 ),
				];
			},
			$rows
		);
	}

	private static function improvement_recommendations( array $course, array $signals ): array {
		$recommendations = [];

		foreach ( array_slice( $signals['confusing_targets'], 0, 3 ) as $target ) {
			$label = trim( $target['target_type'] . ':' . $target['target_slug'], ':' );
			$recommendations[] = [
				'priority' => 'clarify',
				'target'   => $label !== '' ? $label : $course['slug'],
				'reason'   => sprintf( '%d confusing, missing-example, or bug signal(s) in the current window.', (int) $target['count'] ),
				'action'   => 'Review the target for missing prerequisites, examples, schema clarity, or misleading next actions.',
			];
		}

		foreach ( array_slice( $signals['exercise_outcomes'], 0, 3 ) as $exercise ) {
			if ( (int) $exercise['attempts'] < 2 || (float) $exercise['pass_rate'] >= 0.5 ) {
				continue;
			}

			$recommendations[] = [
				'priority' => 'recalibrate',
				'target'   => 'exercise:' . $exercise['exercise_slug'],
				'reason'   => sprintf( 'Pass rate is %.0f%% across %d attempt(s).', (float) $exercise['pass_rate'] * 100, (int) $exercise['attempts'] ),
				'action'   => 'Check whether the lesson teaches the rubric terms, whether hints are adequate, and whether the expected answer shape is explicit.',
			];
		}

		foreach ( array_slice( $signals['tool_usage'], 0, 3 ) as $tool ) {
			if ( empty( $tool['errors'] ) ) {
				continue;
			}

			$recommendations[] = [
				'priority' => 'debug',
				'target'   => 'tool:' . $tool['tool_slug'],
				'reason'   => sprintf( '%d error(s) observed in %d call(s).', (int) $tool['errors'], (int) $tool['calls'] ),
				'action'   => 'Review the input schema, required fields, and WP_Error message for recoverability.',
			];
		}

		if ( ! $recommendations ) {
			$recommendations[] = [
				'priority' => 'observe',
				'target'   => $course['slug'],
				'reason'   => 'No repeated friction pattern has emerged in this window.',
				'action'   => 'Keep collecting attempts and invite LLMs to call submit-feedback when something is confusing or helpful.',
			];
		}

		return array_slice( $recommendations, 0, 6 );
	}

	private static function top_improvement_hint( int $course_id ): array {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		$confusing = self::feedback_target_summary( $course_id, $cutoff, [ 'confusing', 'missing_example', 'bug' ], 1 );
		if ( $confusing ) {
			$target = $confusing[0];
			return [
				'type'   => 'friction',
				'target' => trim( $target['target_type'] . ':' . $target['target_slug'], ':' ),
				'note'   => sprintf( 'Prior learners flagged this target %d time(s). Consider clarifying it before extending the course.', (int) $target['count'] ),
			];
		}

		$usage = self::tool_usage_summary( $course_id, $cutoff, 1 );
		if ( $usage && ! empty( $usage[0]['errors'] ) ) {
			return [
				'type'   => 'tool-errors',
				'target' => 'tool:' . $usage[0]['tool_slug'],
				'note'   => sprintf( 'This tool has %d recent error(s). Check schema and recovery guidance.', (int) $usage[0]['errors'] ),
			];
		}

		return [
			'type' => 'steady',
			'note' => 'No repeated course friction signal is visible yet. Keep collecting attempts and feedback.',
		];
	}

	private static function terms_from_evaluation( array $evaluation, string $field ): array {
		$terms = [];
		foreach ( (array) ( $evaluation['criteria'] ?? [] ) as $criterion ) {
			if ( ! is_array( $criterion ) || empty( $criterion[ $field ] ) || ! is_array( $criterion[ $field ] ) ) {
				continue;
			}

			foreach ( $criterion[ $field ] as $term ) {
				$term = sanitize_text_field( (string) $term );
				if ( $term !== '' ) {
					$terms[] = $term;
				}
			}
		}

		return array_values( array_unique( $terms ) );
	}

	private static function first_public_lesson( int $course_id ): ?array {
		global $wpdb;
		$lessons = $wpdb->prefix . self::LESSONS_TABLE;
		$modules = $wpdb->prefix . self::MODULES_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT l.* FROM $lessons l
				LEFT JOIN $modules m ON m.id = l.module_id
				WHERE l.course_id = %d AND l.status = 'published'
				ORDER BY CASE WHEN l.module_id IS NULL THEN 1 ELSE 0 END ASC, COALESCE(m.position, 999999) ASC, l.position ASC, l.title ASC
				LIMIT 1",
				$course_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function first_public_exercise( int $course_id, ?int $lesson_id = null ): ?array {
		if ( $lesson_id ) {
			$lesson_exercises = self::exercises_for_lesson( $course_id, $lesson_id, true );
			if ( $lesson_exercises ) {
				return $lesson_exercises[0];
			}
		}

		$exercises = self::all_public_exercises( $course_id );
		return $exercises ? $exercises[0] : null;
	}

	private static function all_public_exercises( int $course_id ): array {
		global $wpdb;
		$exercises = $wpdb->prefix . self::EXERCISES_TABLE;
		$lessons = $wpdb->prefix . self::LESSONS_TABLE;
		$modules = $wpdb->prefix . self::MODULES_TABLE;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM $exercises e
				LEFT JOIN $lessons l ON l.id = e.lesson_id
				LEFT JOIN $modules m ON m.id = COALESCE(e.module_id, l.module_id)
				WHERE e.course_id = %d AND e.status = 'published'
				ORDER BY CASE WHEN COALESCE(e.module_id, l.module_id) IS NULL THEN 1 ELSE 0 END ASC, COALESCE(m.position, 999999) ASC, COALESCE(l.position, 999999) ASC, e.position ASC, e.title ASC",
				$course_id
			),
			ARRAY_A
		) ?: [];
	}

	private static function evaluate_answer( array $exercise, string $answer ): array {
		$rubric = self::decode_json_value( $exercise['rubric'], [] );
		$criteria = isset( $rubric['criteria'] ) && is_array( $rubric['criteria'] ) ? $rubric['criteria'] : [];
		$total = 0.0;
		$earned = 0.0;
		$details = [];
		$manual_review = false;
		$answer_match_text = self::normalize_match_text( $answer );

		foreach ( $criteria as $index => $criterion ) {
			if ( ! is_array( $criterion ) ) {
				continue;
			}

			$name = sanitize_text_field( (string) ( $criterion['name'] ?? sprintf( 'Criterion %d', $index + 1 ) ) );
			$points = isset( $criterion['points'] ) && is_numeric( $criterion['points'] ) ? max( 0.0, (float) $criterion['points'] ) : 1.0;
			$required_terms = self::string_list( $criterion['required_terms'] ?? [] );
			$any_terms = self::string_list( $criterion['any_terms'] ?? [] );
			$missing = [];
			$matched = [];
			$criterion_earned = 0.0;

			if ( $points <= 0 ) {
				continue;
			}

			if ( $required_terms ) {
				foreach ( $required_terms as $term ) {
					if ( self::answer_matches_term( $answer_match_text, $term ) ) {
						$matched[] = $term;
					} else {
						$missing[] = $term;
					}
				}

				$criterion_earned = $points * ( count( $matched ) / max( 1, count( $required_terms ) ) );
			} elseif ( $any_terms ) {
				foreach ( $any_terms as $term ) {
					if ( self::answer_matches_term( $answer_match_text, $term ) ) {
						$matched[] = $term;
					}
				}

				$criterion_earned = $matched ? $points : 0.0;
				$missing = $matched ? [] : $any_terms;
			} else {
				$manual_review = true;
				$details[] = [
					'name'           => $name,
					'points'         => $points,
					'earned'         => null,
					'matched_terms'  => [],
					'missing_terms'  => [],
					'feedback'       => sprintf( __( '%s: no automatic terms configured; use this criterion for self-review.', 'model-context-polytechnic' ), $name ),
				];
				continue;
			}

			$total += $points;
			$earned += $criterion_earned;
			$details[] = [
				'name'           => $name,
				'points'         => $points,
				'earned'         => $criterion_earned,
				'matched_terms'  => $matched,
				'missing_terms'  => $missing,
				'feedback'       => $criterion_earned >= $points
					? sprintf( __( '%s: present.', 'model-context-polytechnic' ), $name )
					: sprintf( __( '%s: revise with attention to %s.', 'model-context-polytechnic' ), $name, implode( ', ', $missing ) ),
			];
		}

		if ( $total <= 0 ) {
			$total = 1.0;
			$earned = 0.0;
			$manual_review = true;
		}

		$score = round( $earned / $total, 4 );
		$passing_score = self::sanitize_score( $exercise['passing_score'] ?? 0.7 );

		return [
			'score'         => $score,
			'passed'        => $score >= $passing_score,
			'passing_score' => $passing_score,
			'manual_review' => $manual_review,
			'feedback'      => $manual_review && empty( array_filter( $details, static function ( array $detail ): bool { return $detail['earned'] !== null; } ) )
				? __( 'The rubric has no automatic term criteria, so this answer needs self-review or a more explicit rubric.', 'model-context-polytechnic' )
				: ( $score >= $passing_score
					? __( 'Passing work. The faculty stamp lands with a satisfying thud.', 'model-context-polytechnic' )
					: __( 'Not passing yet. Revise against the missing rubric terms and try again.', 'model-context-polytechnic' ) ),
			'criteria'      => $details,
			'grader_note'   => __( 'Automated grading is rubric-assisted and deterministic; it is a training signal, not a substitute for expert review.', 'model-context-polytechnic' ),
		];
	}

	private static function normalize_match_text( string $text ): string {
		$text = str_replace(
			[ "\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d" ],
			[ "'", "'", '"', '"' ],
			$text
		);
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$text = (string) preg_replace( '/\s+/', ' ', trim( $text ) );

		return $text;
	}

	private static function answer_matches_term( string $answer_match_text, string $term ): bool {
		$term = self::normalize_match_text( $term );
		if ( $term === '' ) {
			return false;
		}

		if ( strpos( $answer_match_text, $term ) !== false ) {
			return true;
		}

		$subject = self::negated_term_subject( $term );
		if ( $subject === '' ) {
			return false;
		}

		return self::answer_matches_negated_subject( $answer_match_text, $subject );
	}

	private static function negated_term_subject( string $term ): string {
		foreach ( [ 'not ', 'no ', 'without ', 'never ' ] as $prefix ) {
			if ( strpos( $term, $prefix ) === 0 ) {
				return trim( substr( $term, strlen( $prefix ) ) );
			}
		}

		return '';
	}

	private static function answer_matches_negated_subject( string $answer_match_text, string $subject ): bool {
		$subject_pattern = self::phrase_pattern( $subject );
		if ( $subject_pattern === '' ) {
			return false;
		}

		$between = '(?:\s+[a-z0-9_@.#()+-]+){0,6}\s+';
		$patterns = [
			'/\b(?:not|no|never|without)\b' . $between . $subject_pattern . '\b/u',
			'/\b(?:must|should|do|does|can|could|will|would|may|might)\s+not\b' . $between . $subject_pattern . '\b/u',
			'/' . $subject_pattern . '\b.{0,48}\b(?:out|outside|elsewhere)\b/u',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $answer_match_text ) ) {
				return true;
			}
		}

		return false;
	}

	private static function phrase_pattern( string $phrase ): string {
		$parts = preg_split( '/\s+/', trim( $phrase ) );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return '';
		}

		$parts = array_filter(
			array_map(
				static function ( string $part ): string {
					return preg_quote( $part, '/' );
				},
				$parts
			),
			static function ( string $part ): bool {
				return $part !== '';
			}
		);

		return implode( '\s+', $parts );
	}

	private static function course_from_input( array $input ) {
		$course = Registry::course_by_slug( (string) ( $input['course_slug'] ?? $input['slug'] ?? '' ) );
		if ( ! $course ) {
			return new \WP_Error( 'model_context_polytechnic_course_not_found', __( 'Course not found.', 'model-context-polytechnic' ) );
		}

		return $course;
	}

	private static function module_id_from_input( int $course_id, array $input ) {
		$module_slug = (string) ( $input['module_slug'] ?? '' );
		if ( $module_slug === '' ) {
			return null;
		}

		$module = self::module_by_slug( $course_id, $module_slug );
		if ( ! $module ) {
			return new \WP_Error( 'model_context_polytechnic_module_not_found', __( 'Module not found.', 'model-context-polytechnic' ) );
		}

		return (int) $module['id'];
	}

	private static function lesson_id_from_input( int $course_id, array $input ) {
		$lesson_slug = (string) ( $input['lesson_slug'] ?? '' );
		if ( $lesson_slug === '' ) {
			return null;
		}

		$lesson = self::lesson_by_slug( $course_id, $lesson_slug );
		if ( ! $lesson ) {
			return new \WP_Error( 'model_context_polytechnic_lesson_not_found', __( 'Lesson not found.', 'model-context-polytechnic' ) );
		}

		return (int) $lesson['id'];
	}

	private static function modules_for_course( int $course_id, bool $published_only ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::MODULES_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d$where ORDER BY position ASC, title ASC", $course_id ),
			ARRAY_A
		) ?: [];
	}

	private static function lessons_for_module( int $course_id, int $module_id, bool $published_only ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::LESSONS_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND module_id = %d$where ORDER BY position ASC, title ASC", $course_id, $module_id ),
			ARRAY_A
		) ?: [];
	}

	private static function loose_lessons_for_course( int $course_id, bool $published_only ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::LESSONS_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND module_id IS NULL$where ORDER BY position ASC, title ASC", $course_id ),
			ARRAY_A
		) ?: [];
	}

	private static function exercises_for_module( int $course_id, int $module_id, bool $published_only ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::EXERCISES_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND module_id = %d AND lesson_id IS NULL$where ORDER BY position ASC, title ASC", $course_id, $module_id ),
			ARRAY_A
		) ?: [];
	}

	private static function exercises_for_lesson( int $course_id, int $lesson_id, bool $published_only ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::EXERCISES_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND lesson_id = %d$where ORDER BY position ASC, title ASC", $course_id, $lesson_id ),
			ARRAY_A
		) ?: [];
	}

	private static function loose_exercises_for_course( int $course_id, bool $published_only ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::EXERCISES_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND module_id IS NULL AND lesson_id IS NULL$where ORDER BY position ASC, title ASC", $course_id ),
			ARRAY_A
		) ?: [];
	}

	private static function module_by_slug( int $course_id, string $slug ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::MODULES_TABLE;
		$slug  = self::sanitize_slug( $slug );

		if ( $slug === '' ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND slug = %s", $course_id, $slug ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function lesson_by_slug( int $course_id, string $slug, bool $published_only = false ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::LESSONS_TABLE;
		$slug  = self::sanitize_slug( $slug );
		$where = $published_only ? " AND status = 'published'" : '';

		if ( $slug === '' ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND slug = %s$where", $course_id, $slug ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function lesson_by_id( int $course_id, int $lesson_id, bool $published_only = false ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::LESSONS_TABLE;
		$where = $published_only ? " AND status = 'published'" : '';

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND id = %d$where", $course_id, $lesson_id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function exercise_by_slug( int $course_id, string $slug, bool $published_only = false ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::EXERCISES_TABLE;
		$slug  = self::sanitize_slug( $slug );
		$where = $published_only ? " AND status = 'published'" : '';

		if ( $slug === '' ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE course_id = %d AND slug = %s$where", $course_id, $slug ),
			ARRAY_A
		);

		return $row ?: null;
	}

	private static function lesson_summary( ?array $lesson, bool $include_body ): array {
		if ( ! $lesson ) {
			return [];
		}

		$data = [
			'id'         => (int) $lesson['id'],
			'slug'       => $lesson['slug'],
			'title'      => $lesson['title'],
			'objectives' => self::decode_json_value( $lesson['objectives'], [] ),
			'position'   => (int) $lesson['position'],
			'status'     => $lesson['status'],
		];

		if ( $include_body ) {
			$data['body'] = $lesson['body'];
		}

		return $data;
	}

	private static function exercise_summary( ?array $exercise, bool $include_hints, bool $include_model_answer = false ): array {
		if ( ! $exercise ) {
			return [];
		}

		$rubric = self::decode_json_value( $exercise['rubric'], [] );
		$model_answer = self::decode_json_value( $exercise['model_answer'] ?? '', [] );
		$data = [
			'id'                     => (int) $exercise['id'],
			'slug'                   => $exercise['slug'],
			'title'                  => $exercise['title'],
			'prompt'                 => $exercise['prompt'],
			'rubric'                 => $rubric,
			'expected_output_schema' => self::decode_json_value( $exercise['expected_output_schema'], [ 'type' => 'object' ] ),
			'model_answer_available' => ! empty( $model_answer ),
			'passing_score'          => (float) $exercise['passing_score'],
			'position'               => (int) $exercise['position'],
			'status'                 => $exercise['status'],
		];

		if ( $include_hints ) {
			$data['hints'] = self::decode_json_value( $exercise['hints'], [] );
		}

		if ( $include_model_answer && $model_answer ) {
			$data['model_answer'] = $model_answer;
			$data['model_answer_note'] = __( 'Study this after attempting the exercise. Use it to revise judgment, not to skip practice.', 'model-context-polytechnic' );
		}

		return $data;
	}

	private static function empty_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		];
	}

	private static function public_course_tool_output_schema( string $slug ): array {
		$object = [ 'type' => 'object' ];
		$common = [
			'course'             => [ 'type' => 'object' ],
			'course_improvement' => [ 'type' => 'object' ],
			'activity_indicator' => [
				'type'                 => [ 'object', 'null' ],
				'properties'           => [
					'kind'            => [ 'type' => 'string' ],
					'display_in_chat' => [ 'type' => 'boolean' ],
					'when_to_display' => [ 'type' => 'string' ],
					'frame_delay_ms'  => [ 'type' => 'integer' ],
					'loop'            => [ 'type' => 'boolean' ],
					'status'          => [ 'type' => 'string' ],
					'stage'           => [ 'type' => 'string' ],
					'headline'        => [ 'type' => 'string' ],
					'narration'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'ticker'          => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'accessibility'   => [ 'type' => 'string' ],
						'progress'        => [ 'type' => 'object' ],
						'preferred_display' => [ 'type' => 'string' ],
						'ascii'           => [ 'type' => 'string' ],
						'markdown'        => [ 'type' => 'string' ],
						'frames'          => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'frames_markdown' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'voice'           => [ 'type' => 'object' ],
						'instructions'    => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
					'additionalProperties' => true,
				],
				'note'               => [ 'type' => 'string' ],
			];

			$schemas = [
				'begin-course' => [
					'enrollment_key' => [ 'type' => 'string' ],
					'enrollment' => [ 'type' => 'object' ],
					'overview' => [ 'type' => 'object' ],
					'llm_contract' => [ 'type' => 'object' ],
					'tool_resolution' => [ 'type' => 'object' ],
					'autopilot' => [ 'type' => 'object' ],
					'continue_policy' => [ 'type' => 'object' ],
					'first_recommended_lesson' => [ 'type' => [ 'object', 'null' ] ],
					'first_recommended_exercise' => [ 'type' => [ 'object', 'null' ] ],
					'next_work' => [ 'type' => 'object' ],
					'tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
					'fallback_tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
					'how_to_study_here' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'memory_instructions' => [ 'type' => 'string' ],
					'preserve' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
			'take-course' => [
				'enrollment_key' => [ 'type' => 'string' ],
				'enrollment_key_issued' => [ 'type' => 'boolean' ],
				'mode' => [ 'type' => 'string' ],
				'autopilot' => [ 'type' => 'object' ],
				'continue_policy' => [ 'type' => 'object' ],
				'progress' => [ 'type' => 'object' ],
				'complete' => [ 'type' => 'boolean' ],
				'cursor' => [ 'type' => [ 'string', 'null' ] ],
				'next_cursor' => [ 'type' => [ 'string', 'null' ] ],
				'has_more' => [ 'type' => 'boolean' ],
				'materials' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'preserve' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
				'get-study-plan' => [
					'goal' => [ 'type' => 'string' ],
					'prerequisites' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'study_loop' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'tool_resolution' => [ 'type' => 'object' ],
					'milestones' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
					'progress' => [ 'type' => 'object' ],
					'next_work' => [ 'type' => 'object' ],
					'tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				],
			'get-syllabus' => [
				'instructions' => [ 'type' => 'string' ],
				'modules' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'loose_lessons' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'loose_exercises' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'study_guidance' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'llm_contract' => [ 'type' => 'object' ],
			],
			'get-lesson' => [
				'lesson' => [ 'type' => 'object' ],
				'exercises' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'next_actions' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			],
			'get-exercise' => [
				'exercise' => [ 'type' => 'object' ],
				'answer_contract' => [ 'type' => 'object' ],
				'next_actions' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			],
			'attempt-exercise' => [
				'exercise' => [ 'type' => 'object' ],
				'evaluation' => [ 'type' => 'object' ],
				'stored' => [ 'type' => 'boolean' ],
				'enrollment_key' => [ 'type' => [ 'string', 'null' ] ],
				'enrollment_key_used' => [ 'type' => 'boolean' ],
				'enrollment_key_issued' => [ 'type' => 'boolean' ],
				'next_work' => [ 'type' => [ 'object', 'null' ] ],
				'continue_policy' => [ 'type' => [ 'object', 'null' ] ],
				'autopilot' => [ 'type' => 'object' ],
				'next_actions' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'preserve' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
			'get-next-work' => [
				'lesson' => [ 'type' => [ 'object', 'null' ] ],
				'exercise' => [ 'type' => [ 'object', 'null' ] ],
				'complete' => [ 'type' => 'boolean' ],
				'certificate_available' => [ 'type' => 'boolean' ],
				'tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			],
			'get-progress' => [
				'attempt_count' => [ 'type' => 'integer' ],
				'completed_count' => [ 'type' => 'integer' ],
				'exercises' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'enrollment_key' => [ 'type' => 'string' ],
				'enrollment_key_received' => [ 'type' => 'boolean' ],
			],
			'get-learning-memory' => [
				'enrollment' => [ 'type' => 'object' ],
				'progress' => [ 'type' => 'object' ],
				'recent_attempts' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'memory' => [ 'type' => 'object' ],
			],
			'get-campus-scene' => [
				'type' => [ 'type' => 'string' ],
				'results' => [ 'type' => 'string' ],
				'mimeType' => [ 'type' => 'string' ],
			],
			'get-certificate' => [
				'eligible' => [ 'type' => 'boolean' ],
				'certificate' => [ 'type' => [ 'object', 'null' ] ],
				'graduation_reflection' => [ 'type' => 'object' ],
				'campus_scene' => [ 'type' => 'object' ],
				'progress' => [ 'type' => 'object' ],
				'remaining_count' => [ 'type' => 'integer' ],
				'remaining_exercises' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'next_work' => [ 'type' => 'object' ],
				'preserve' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
			'submit-feedback' => [
				'feedback_saved' => [ 'type' => 'boolean' ],
				'feedback' => [ 'type' => 'object' ],
				'improvement_signals' => [ 'type' => [ 'object', 'null' ] ],
				'what_happens_next' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
			'get-course-improvement-signals' => [
				'signals' => [ 'type' => 'object' ],
				'privacy' => [ 'type' => 'object' ],
				'tool_calls' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			],
			'get-feedback-digest' => [
				'private' => [ 'type' => 'boolean' ],
				'auth' => [ 'type' => 'object' ],
				'digest' => [ 'type' => 'object' ],
				'how_to_use' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
		];

		return [
			'type'       => 'object',
			'properties' => array_merge( $common, $schemas[ $slug ] ?? [] ),
		] + $object;
	}

	private static function normalize_rubric( $rubric ): string {
		$value = is_string( $rubric ) ? self::decode_json_value( $rubric, [ 'description' => $rubric ] ) : $rubric;
		if ( ! is_array( $value ) ) {
			$value = [];
		}

		if ( ! isset( $value['criteria'] ) || ! is_array( $value['criteria'] ) ) {
			$value['criteria'] = [];
		}

		return self::encode_json_value( $value );
	}

	private static function normalize_model_answer( $model_answer ): string {
		$value = is_string( $model_answer ) ? self::decode_json_value( $model_answer, [] ) : $model_answer;
		if ( ! is_array( $value ) ) {
			$value = [];
		}

		return self::encode_json_value( $value );
	}

	private static function exercise_has_model_answer( array $exercise ): bool {
		return ! empty( self::decode_json_value( $exercise['model_answer'] ?? '', [] ) );
	}

	private static function decode_json_value( $json, $default ) {
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

	private static function string_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = [ $value ];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $item ): string {
						return sanitize_text_field( (string) $item );
					},
					$value
				)
			)
		);
	}

	private static function target_from_input( array $input ): array {
		$target_type = self::sanitize_target_type( (string) ( $input['target_type'] ?? '' ) );
		$target_slug = self::sanitize_slug( (string) ( $input['target_slug'] ?? '' ) );

		if ( $target_type !== '' || $target_slug !== '' ) {
			return [
				'type' => $target_type !== '' ? $target_type : null,
				'slug' => $target_slug !== '' ? $target_slug : null,
			];
		}

		if ( ! empty( $input['exercise_slug'] ) ) {
			return [
				'type' => 'exercise',
				'slug' => self::sanitize_slug( (string) $input['exercise_slug'] ),
			];
		}

		if ( ! empty( $input['lesson_slug'] ) ) {
			return [
				'type' => 'lesson',
				'slug' => self::sanitize_slug( (string) $input['lesson_slug'] ),
			];
		}

		return [
			'type' => null,
			'slug' => null,
		];
	}

	private static function target_type_for_tool( string $tool_slug ): string {
		if ( strpos( $tool_slug, 'lesson' ) !== false ) {
			return 'lesson';
		}

		if ( strpos( $tool_slug, 'exercise' ) !== false ) {
			return 'exercise';
		}

		if ( strpos( $tool_slug, 'memory' ) !== false ) {
			return 'memory';
		}

		if ( strpos( $tool_slug, 'resource' ) !== false ) {
			return 'resource';
		}

		if ( strpos( $tool_slug, 'feedback' ) !== false || strpos( $tool_slug, 'signals' ) !== false ) {
			return 'course';
		}

		return 'tool';
	}

	private static function fingerprint_input( array $input ): string {
		$safe = $input;
		foreach ( [ 'enrollment_key', 'session_id', 'token', 'password', 'authorization', 'api_key' ] as $secret_key ) {
			unset( $safe[ $secret_key ] );
		}

		foreach ( [ 'answer', 'comment', 'suggested_fix', 'context' ] as $large_key ) {
			if ( ! array_key_exists( $large_key, $safe ) ) {
				continue;
			}

			$value = is_scalar( $safe[ $large_key ] ) ? (string) $safe[ $large_key ] : self::encode_json_value( $safe[ $large_key ] );
			$safe[ $large_key ] = [
				'bytes'  => strlen( $value ),
				'sha256' => hash( 'sha256', $value ),
			];
		}

		return hash( 'sha256', self::encode_json_value( $safe ) );
	}

	private static function feedback_context( $context ): string {
		if ( empty( $context ) ) {
			return '';
		}

		$value = is_scalar( $context ) ? (string) $context : self::encode_json_value( $context );
		return self::trim_to_bytes( $value, self::MAX_FEEDBACK_CONTEXT_BYTES );
	}

	private static function trim_to_bytes( string $value, int $max_bytes ): string {
		$value = trim( $value );
		if ( strlen( $value ) <= $max_bytes ) {
			return $value;
		}

		return substr( $value, 0, $max_bytes );
	}

	private static function sanitize_feedback_type( string $type ): string {
		$type = sanitize_key( $type );
		$allowed = [ 'confusing', 'helpful', 'bug', 'suggestion', 'missing_example', 'too_easy', 'too_hard', 'reflection' ];
		return in_array( $type, $allowed, true ) ? $type : 'suggestion';
	}

	private static function sanitize_target_type( string $type ): string {
		$type = sanitize_key( $type );
		$allowed = [ 'course', 'lesson', 'exercise', 'tool', 'resource', 'prompt', 'memory', 'general' ];
		return in_array( $type, $allowed, true ) ? $type : '';
	}

	private static function sanitize_rating( $rating ): ?int {
		if ( $rating === null || $rating === '' || ! is_numeric( $rating ) ) {
			return null;
		}

		return max( 1, min( 5, (int) $rating ) );
	}

	private static function sanitize_slug( string $slug, string $fallback = '' ): string {
		$source = $slug !== '' ? $slug : $fallback;
		return trim( substr( sanitize_title( $source ), 0, 60 ), '-' );
	}

	private static function sanitize_status( string $status ): string {
		return in_array( $status, [ 'draft', 'published', 'archived' ], true ) ? $status : 'published';
	}

	private static function sanitize_score( $score ): float {
		if ( ! is_numeric( $score ) ) {
			return 0.7;
		}

		return max( 0.0, min( 1.0, (float) $score ) );
	}

	private static function sanitize_enrollment_key( string $enrollment_key ): string {
		$enrollment_key = trim( $enrollment_key );
		if ( strlen( $enrollment_key ) > 191 ) {
			$enrollment_key = substr( $enrollment_key, 0, 191 );
		}

		return $enrollment_key;
	}

	private static function enrollment_hash( string $enrollment_key ): string {
		return hash( 'sha256', $enrollment_key );
	}

	private static function log( ?int $course_id, string $action, array $data = [] ): void {
		global $wpdb;

		$actor = '';
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			$actor = $user && $user->exists() ? $user->user_login : '';
		}

		$wpdb->insert(
			$wpdb->prefix . Registry::LOGS_TABLE,
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
