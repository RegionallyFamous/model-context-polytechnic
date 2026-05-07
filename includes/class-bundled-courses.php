<?php
namespace ModelContextPolytechnic\Mcp;

class BundledCourses {
	const SEED_VERSION   = '4';
	const VERSION_OPTION = 'model_context_polytechnic_bundled_courses_version';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_seed' ], 30 );
	}

	public static function maybe_seed(): void {
		if ( get_option( self::VERSION_OPTION ) === self::seed_fingerprint() ) {
			return;
		}

		self::seed_all();
	}

	public static function seed_all(): bool {
		$audit = CoursePack::audit_all();
		if ( ! $audit['valid'] ) {
			self::log_course_pack_audit( $audit );
			return false;
		}

		$seeded = true;
		foreach ( self::course_definitions() as $course ) {
			if ( ! self::seed_course( $course ) ) {
				$seeded = false;
			}
		}

		if ( $seeded ) {
			update_option( self::VERSION_OPTION, self::seed_fingerprint(), false );
		}

		return $seeded;
	}

	public static function course_definitions(): array {
		return CoursePack::definitions();
	}

	private static function seed_course( array $definition ): bool {
		$slug = (string) $definition['slug'];
		$course_payload = [
			'slug'         => $slug,
			'name'         => (string) $definition['name'],
			'description'  => (string) $definition['description'],
			'voice'        => $definition['voice'],
			'instructions' => (string) $definition['instructions'],
			'status'       => 'published',
		];

		$course = Registry::course_by_slug( $slug );
		$result = $course ? Registry::update_course( $course_payload ) : Registry::create_course( $course_payload );
		if ( self::is_error( $result ) ) {
			self::log_seed_error( sprintf( 'Could not seed course "%s": %s', $slug, self::error_message( $result ) ) );
			return false;
		}

		$seeded = true;
		foreach ( $definition['modules'] as $module ) {
			$module_result = Learning::add_module(
				[
					'course_slug' => $slug,
					'slug'        => $module['slug'],
					'title'       => $module['title'],
					'summary'     => $module['summary'],
					'position'    => $module['position'],
					'status'      => 'published',
				]
			);

			if ( self::is_error( $module_result ) ) {
				$seeded = false;
				self::log_seed_error( sprintf( 'Could not seed module "%s": %s', $module['slug'], self::error_message( $module_result ) ) );
				continue;
			}

			foreach ( $module['lessons'] as $lesson ) {
				$lesson_result = Learning::add_lesson(
					[
						'course_slug' => $slug,
						'module_slug' => $module['slug'],
						'slug'        => $lesson['slug'],
						'title'       => $lesson['title'],
						'body'        => $lesson['body'],
						'objectives'  => $lesson['objectives'],
						'position'    => $lesson['position'],
						'status'      => 'published',
					]
				);

				if ( self::is_error( $lesson_result ) ) {
					$seeded = false;
					self::log_seed_error( sprintf( 'Could not seed lesson "%s": %s', $lesson['slug'], self::error_message( $lesson_result ) ) );
				}
			}

			foreach ( $module['exercises'] as $exercise ) {
				$exercise_result = Learning::add_exercise(
					[
						'course_slug'            => $slug,
						'module_slug'            => $module['slug'],
						'lesson_slug'            => $exercise['lesson_slug'] ?? '',
						'slug'                   => $exercise['slug'],
						'title'                  => $exercise['title'],
						'prompt'                 => $exercise['prompt'],
						'rubric'                 => $exercise['rubric'],
						'expected_output_schema' => $exercise['expected_output_schema'] ?? CoursePack::default_expected_output_schema(),
						'hints'                  => $exercise['hints'],
						'model_answer'           => $exercise['model_answer'] ?? [],
						'passing_score'          => $exercise['passing_score'] ?? 0.8,
						'position'               => $exercise['position'],
						'status'                 => 'published',
					]
				);

				if ( self::is_error( $exercise_result ) ) {
					$seeded = false;
					self::log_seed_error( sprintf( 'Could not seed exercise "%s": %s', $exercise['slug'], self::error_message( $exercise_result ) ) );
				}
			}
		}

		foreach ( $definition['references'] as $reference ) {
			$reference_result = Registry::add_content(
				[
					'course_slug' => $slug,
					'slug'        => $reference['slug'],
					'title'       => $reference['title'],
					'body'        => $reference['body'],
					'mime_type'   => $reference['mime_type'] ?? 'text/markdown',
					'visibility'  => 'public',
				]
			);

			if ( self::is_error( $reference_result ) ) {
				$seeded = false;
				self::log_seed_error( sprintf( 'Could not seed reference "%s": %s', $reference['slug'], self::error_message( $reference_result ) ) );
			}
		}

		return $seeded;
	}

	private static function seed_fingerprint(): string {
		return self::SEED_VERSION . ':' . CoursePack::fingerprint();
	}

	private static function log_course_pack_audit( array $audit ): void {
		foreach ( $audit['errors'] ?? [] as $error ) {
			self::log_seed_error( $error );
		}

		foreach ( $audit['packs'] ?? [] as $pack ) {
			foreach ( $pack['errors'] ?? [] as $error ) {
				self::log_seed_error( sprintf( '%s: %s', $pack['slug'] ?: $pack['path'], $error ) );
			}
		}
	}

	private static function log_seed_error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Model Context Polytechnic course pack: ' . $message );
		}
	}

	private static function error_message( $error ): string {
		return is_object( $error ) && method_exists( $error, 'get_error_message' )
			? $error->get_error_message()
			: 'Unknown error';
	}

	private static function is_error( $value ): bool {
		return function_exists( 'is_wp_error' ) && is_wp_error( $value );
	}
}
