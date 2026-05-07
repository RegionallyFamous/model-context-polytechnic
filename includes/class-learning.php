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
	const SCHEMA_VERSION    = '4';
	const MAX_ANSWER_BYTES  = 20000;
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
		$feedback    = $wpdb->prefix . self::FEEDBACK_TABLE;

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
				WHERE a.id IS NULL
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
					self::learning_ability_name( $course_slug, 'get-study-plan' ),
					self::learning_ability_name( $course_slug, 'get-syllabus' ),
				self::learning_ability_name( $course_slug, 'get-lesson' ),
				self::learning_ability_name( $course_slug, 'get-exercise' ),
				self::learning_ability_name( $course_slug, 'attempt-exercise' ),
				self::learning_ability_name( $course_slug, 'get-next-work' ),
				self::learning_ability_name( $course_slug, 'get-progress' ),
				self::learning_ability_name( $course_slug, 'get-learning-memory' ),
				self::learning_ability_name( $course_slug, 'submit-feedback' ),
				self::learning_ability_name( $course_slug, 'get-course-improvement-signals' ),
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
				self::register_get_study_plan_tool( $course );
				self::register_get_syllabus_tool( $course );
			self::register_get_lesson_tool( $course );
			self::register_get_exercise_tool( $course );
			self::register_attempt_exercise_tool( $course );
			self::register_get_next_work_tool( $course );
			self::register_get_progress_tool( $course );
			self::register_get_learning_memory_tool( $course );
			self::register_submit_feedback_tool( $course );
			self::register_get_course_improvement_signals_tool( $course );
		}
	}

	public static function learning_ability_name( string $course_slug, string $ability_slug ): string {
		return Server::ABILITY_PREFIX . '/' . self::sanitize_slug( $course_slug ) . '-' . self::sanitize_slug( $ability_slug );
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
		if ( isset( $response['course_improvement'] ) ) {
			return $response;
		}

		$target = self::target_from_input( $input );
		$response['course_improvement'] = [
			'this_call_was_logged' => true,
			'feedback_tool'        => self::learning_ability_name( $course['slug'], 'submit-feedback' ),
			'signals_tool'         => self::learning_ability_name( $course['slug'], 'get-course-improvement-signals' ),
			'current_hint'         => self::top_improvement_hint( (int) $course['id'] ),
			'when_to_call_feedback'=> [
				'If a lesson, exercise, tool response, or next action was confusing.',
				'If something was unusually helpful and should be preserved.',
				'If an example, rubric term, or prerequisite seems missing.',
			],
			'feedback_arguments'   => [
				'feedback_type' => 'confusing | helpful | bug | suggestion | missing_example',
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

		return [
			'course'              => Registry::course_summary( $course ),
			'enrollment_key'      => $enrollment_key,
			'enrollment'          => [
				'created' => true,
				'note'    => __( 'Keep this enrollment_key in the MCP client or conversation when you want the Polytechnic to remember progress.', 'model-context-polytechnic' ),
			],
			'overview'            => [
				'instructions' => Registry::course_instructions( $course ),
				'study_model'  => __( 'Read the syllabus, study one lesson, attempt the linked exercise, revise against feedback, and fetch learning memory at the start of later sessions.', 'model-context-polytechnic' ),
			],
			'llm_contract'        => self::course_llm_contract( $course ),
			'first_recommended_lesson'   => $lesson ? self::lesson_summary( $lesson, false ) : null,
			'first_recommended_exercise' => $exercise ? self::exercise_summary( $exercise, false ) : null,
			'next_work'           => $next_work,
			'how_to_study_here'   => [
				'Call get-next-work whenever you need the next recommended lesson, exercise, and tool arguments.',
				'Call get-study-plan when you need a goal-aware path through the course.',
				'Call search-course when you need the right lesson or reference for a specific WordPress plugin topic.',
				'Call get-syllabus for the map of the course.',
				'Call get-lesson before attempting linked exercises.',
				'Call attempt-exercise with enrollment_key so feedback becomes durable memory.',
				'After attempting, call get-exercise with include_model_answer=true when you need an exemplar for calibration or revision.',
				'Call get-learning-memory at the start of a later session to recover what this learner has practiced.',
				'Call submit-feedback whenever a tool response, lesson, exercise, or next action is confusing or unusually helpful.',
				'Call get-course-improvement-signals before proposing course changes so suggestions are evidence-aware.',
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
				'Retrieve only the lesson or reference needed for the current task.',
				'Answer in the requested schema or with explicit implementation decisions.',
				'Attempt the linked exercise.',
				'Use feedback and missing rubric terms as the revision checklist.',
				'Use model answers only after an attempt or when calibrating a failed answer; do not skip the practice loop.',
				'Fetch learning memory before future work.',
			],
			'milestones'   => self::study_milestones( $course ),
			'progress'     => $progress,
			'next_work'    => self::next_work_response( $course, $progress['exercises'] ?? [], null, null, $enrollment_key ),
			'student_feedback_loop' => self::student_feedback_loop_guidance( $course ),
			'tool_calls'   => [
				[
					'tool'      => self::learning_ability_name( $course['slug'], 'get-next-work' ),
					'arguments' => $enrollment_key !== '' ? [ 'enrollment_key' => $enrollment_key ] : new \stdClass(),
				],
				[
					'tool'      => Registry::course_ability_name( $course['slug'], 'search-course' ),
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
				'tool'      => self::learning_ability_name( $course['slug'], 'attempt-exercise' ),
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

		return [
			'course'              => Registry::course_summary( $course ),
			'exercise'            => self::exercise_summary( $exercise, false ),
			'evaluation'          => $evaluation,
			'stored'              => $stored,
			'enrollment_key'      => $stored ? $enrollment_key : null,
			'enrollment_key_used' => $stored,
			'enrollment_key_issued' => $key_was_issued,
			'next_work'             => $stored ? self::next_work_response( $course, $stored_progress['exercises'] ?? [], null, null, $enrollment_key ) : null,
			'next_actions'          => self::attempt_next_actions( $course, $exercise, $evaluation, $enrollment_key ),
			'preserve'              => $stored ? [ 'enrollment_key' ] : [],
			'note'                  => $stored
				? __( 'Attempt recorded in the course gradebook for this enrollment_key. Use get-learning-memory with the same key in later sessions.', 'model-context-polytechnic' )
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

		return [
			'course'          => Registry::course_summary( $course ),
			'enrollment'      => [
				'enrollment_key'          => $enrollment_key,
				'enrollment_key_received' => true,
				'created_at'              => $enrollment['created_at'] ?? null,
				'last_seen_at'            => $enrollment['last_seen_at'] ?? null,
				'last_memory_at'          => current_time( 'mysql' ),
			],
			'progress'        => $progress,
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
					'tool'      => self::learning_ability_name( $course['slug'], 'submit-feedback' ),
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
			__( 'Starts an anonymous public enrollment and returns the first lesson, first exercise, and memory instructions.', 'model-context-polytechnic' ),
			self::empty_input_schema(),
			static function ( array $input ) use ( $course ) {
				return Learning::begin_course( $course, $input );
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
						'enum' => [ 'confusing', 'helpful', 'bug', 'suggestion', 'missing_example', 'too_easy', 'too_hard' ],
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

	private static function register_public_course_tool( array $course, string $slug, string $label, string $description, array $input_schema, callable $callback, bool $read_only ): void {
		wp_register_ability(
			self::learning_ability_name( $course['slug'], $slug ),
			[
				'label'               => $label,
				'description'         => $description,
				'category'            => Server::CATEGORY,
				'input_schema'        => $input_schema,
				'output_schema'       => self::public_course_tool_output_schema( $slug ),
				'permission_callback' => '__return_true',
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
				'Use get-next-work when you need the next recommended lesson, exercise, and exact tool arguments.',
				'Use search-course to retrieve targeted lessons, exercises, and references before answering a plugin-building question.',
				'Use get-syllabus, then read lessons in module order.',
				'Use get-exercise before attempting an exercise.',
				'Use attempt-exercise for feedback. Provide enrollment_key so progress becomes durable course memory.',
				'Use get-learning-memory at the start of later sessions to recover what this learner has practiced.',
				'Use submit-feedback when course material is confusing, helpful, stale, missing an example, or badly calibrated.',
				'Use get-course-improvement-signals when improving course material or diagnosing learner friction.',
				'Revise and attempt again until the rubric passes. A proper education has drafts in it.',
			],
			'llm_contract' => self::course_llm_contract( $course ),
			'student_feedback_loop' => self::student_feedback_loop_guidance( $course ),
		];
	}

	private static function course_llm_contract( array $course ): array {
		return [
			'course_slug'    => $course['slug'],
			'first_call'     => self::learning_ability_name( $course['slug'], 'begin-course' ),
			'next_work_tool' => self::learning_ability_name( $course['slug'], 'get-next-work' ),
			'memory_tool'    => self::learning_ability_name( $course['slug'], 'get-learning-memory' ),
			'feedback_tool'  => self::learning_ability_name( $course['slug'], 'submit-feedback' ),
			'signals_tool'   => self::learning_ability_name( $course['slug'], 'get-course-improvement-signals' ),
			'search_tool'    => Registry::course_ability_name( $course['slug'], 'search-course' ),
			'stable_handles' => [ 'enrollment_key', 'lesson_slug', 'exercise_slug' ],
			'operating_loop' => [
				'begin-course',
				'get-study-plan if you need a goal-aware route',
				'get-learning-memory if enrollment_key already exists',
				'get-next-work',
				'get-lesson',
				'get-exercise',
				'attempt-exercise',
				'get-exercise with include_model_answer=true after an attempt when you need exemplar calibration',
				'submit-feedback when the course helped or failed you',
				'get-course-improvement-signals before course revision',
				'revise and repeat',
			],
			'improvement_loop' => self::student_feedback_loop_guidance( $course ),
		];
	}

	private static function student_feedback_loop_guidance( array $course ): array {
		return [
			'purpose' => __( 'Every learner can leave one small signal that makes the next learner path clearer, while course changes still require maintainer review.', 'model-context-polytechnic' ),
			'public_feedback_tool' => self::learning_ability_name( $course['slug'], 'submit-feedback' ),
			'public_signals_tool' => self::learning_ability_name( $course['slug'], 'get-course-improvement-signals' ),
			'local_cohort_lab' => 'composer course-lab',
			'learner_loop' => [
				'Use begin-course and preserve enrollment_key.',
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
				'tool'      => self::learning_ability_name( $course['slug'], 'get-exercise' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[ 'exercise_slug' => $exercise['slug'] ],
					$enrollment_key
				),
			];
		}

		if ( ! $actions ) {
			$actions[] = [
				'tool'      => self::learning_ability_name( $course['slug'], 'get-next-work' ),
				'arguments' => $enrollment_key !== ''
					? [ 'enrollment_key' => $enrollment_key ]
					: [ 'enrollment_key' => 'Use the key returned by begin-course.' ],
			];
		}

		return $actions;
	}

	private static function attempt_next_actions( array $course, array $exercise, array $evaluation, string $enrollment_key = '' ): array {
		$actions = [];

		if ( empty( $evaluation['passed'] ) && self::exercise_has_model_answer( $exercise ) ) {
			$actions[] = [
				'tool'      => self::learning_ability_name( $course['slug'], 'get-exercise' ),
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
			$actions[] = [
				'tool'      => self::learning_ability_name( $course['slug'], empty( $evaluation['passed'] ) ? 'get-learning-memory' : 'get-next-work' ),
				'arguments' => [ 'enrollment_key' => $enrollment_key ],
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
			'lesson'   => $next_work['lesson'],
			'exercise' => $next_work['exercise'],
			'tool_calls' => $next_work['tool_calls'],
			'note'     => $next_work['note'],
		];
	}

	private static function next_work_response( array $course, array $exercise_progress = [], ?array $preferred_lesson = null, ?array $preferred_exercise = null, string $enrollment_key = '' ): array {
		$passed = [];
		foreach ( $exercise_progress as $item ) {
			if ( ! empty( $item['passed'] ) ) {
				$passed[ $item['exercise_slug'] ] = true;
			}
		}

		$exercise = $preferred_exercise;
		if ( ! $exercise || ! empty( $passed[ $exercise['slug'] ] ) ) {
			$exercise = null;
			foreach ( self::all_public_exercises( (int) $course['id'] ) as $candidate ) {
				if ( empty( $passed[ $candidate['slug'] ] ) ) {
					$exercise = $candidate;
					break;
				}
			}
		}

		$lesson = $preferred_lesson;
		if ( $exercise && ! empty( $exercise['lesson_id'] ) ) {
			$lesson = self::lesson_by_id( (int) $course['id'], (int) $exercise['lesson_id'], true );
		}

		if ( ! $lesson ) {
			$lesson = self::first_public_lesson( (int) $course['id'] );
		}

		$tool_calls = [];
		if ( $lesson ) {
			$tool_calls[] = [
				'tool'      => self::learning_ability_name( $course['slug'], 'get-lesson' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[ 'lesson_slug' => $lesson['slug'] ],
					$enrollment_key
				),
			];
		}

		if ( $exercise ) {
			$tool_calls[] = [
				'tool'      => self::learning_ability_name( $course['slug'], 'get-exercise' ),
				'arguments' => self::tool_arguments_with_enrollment(
					[ 'exercise_slug' => $exercise['slug'] ],
					$enrollment_key
				),
			];
			$tool_calls[] = [
				'tool'      => self::learning_ability_name( $course['slug'], 'attempt-exercise' ),
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
			'course'     => Registry::course_summary( $course ),
			'lesson'     => $lesson ? self::lesson_summary( $lesson, false ) : null,
			'exercise'   => $exercise ? self::exercise_summary( $exercise, false ) : null,
			'tool_calls' => $tool_calls,
			'note'       => $exercise
				? __( 'Recommended because it is the earliest published exercise not yet passed by this enrollment.', 'model-context-polytechnic' )
				: __( 'All published exercises have passing attempts. Review the syllabus or wait for the faculty to open another workshop.', 'model-context-polytechnic' ),
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
		$answer_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $answer ) : strtolower( $answer );

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
					$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term ) : strtolower( $term );
					if ( strpos( $answer_lower, $needle ) === false ) {
						$missing[] = $term;
					} else {
						$matched[] = $term;
					}
				}

				$criterion_earned = $points * ( count( $matched ) / max( 1, count( $required_terms ) ) );
			} elseif ( $any_terms ) {
				foreach ( $any_terms as $term ) {
					$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term ) : strtolower( $term );
					if ( strpos( $answer_lower, $needle ) !== false ) {
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
			'note'               => [ 'type' => 'string' ],
		];

		$schemas = [
			'begin-course' => [
				'enrollment_key' => [ 'type' => 'string' ],
				'enrollment' => [ 'type' => 'object' ],
				'overview' => [ 'type' => 'object' ],
				'llm_contract' => [ 'type' => 'object' ],
				'first_recommended_lesson' => [ 'type' => [ 'object', 'null' ] ],
				'first_recommended_exercise' => [ 'type' => [ 'object', 'null' ] ],
				'next_work' => [ 'type' => 'object' ],
				'how_to_study_here' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'memory_instructions' => [ 'type' => 'string' ],
				'preserve' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
			'get-study-plan' => [
				'goal' => [ 'type' => 'string' ],
				'prerequisites' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'study_loop' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
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
				'next_actions' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'preserve' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
			'get-next-work' => [
				'lesson' => [ 'type' => [ 'object', 'null' ] ],
				'exercise' => [ 'type' => [ 'object', 'null' ] ],
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
		$allowed = [ 'confusing', 'helpful', 'bug', 'suggestion', 'missing_example', 'too_easy', 'too_hard' ];
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
