<?php
namespace ModelContextPolytechnic\Mcp;

class CoursePack {
	const SCHEMA_VERSION = 1;

	public static function root(): string {
		return dirname( __DIR__ ) . '/course-packs';
	}

	public static function definitions( string $root = '' ): array {
		$definitions = [];
		$root        = $root !== '' ? $root : self::root();

		foreach ( glob( rtrim( $root, '/\\' ) . '/*/course.json' ) ?: [] as $manifest_file ) {
			$definition = self::load( dirname( $manifest_file ) );
			if ( $definition ) {
				$definitions[] = $definition;
			}
		}

		return $definitions;
	}

	public static function load( string $dir ): ?array {
		$audit = self::audit( $dir );
		if ( ! $audit['valid'] ) {
			return null;
		}

		$manifest = self::read_json_file( $dir . '/course.json' );
		if ( ! $manifest ) {
			return null;
		}

		$definition = [
			'slug'         => (string) $manifest['slug'],
			'name'         => (string) $manifest['name'],
			'description'  => (string) ( $manifest['description'] ?? '' ),
			'voice'        => is_array( $manifest['voice'] ?? null ) ? $manifest['voice'] : [],
			'instructions' => (string) ( $manifest['instructions'] ?? '' ),
			'modules'      => [],
			'references'   => [],
		];

		foreach ( (array) ( $manifest['modules'] ?? [] ) as $module_meta ) {
			if ( ! is_array( $module_meta ) ) {
				continue;
			}

			$module = [
				'position'  => (int) ( $module_meta['position'] ?? 0 ),
				'slug'      => (string) $module_meta['slug'],
				'title'     => (string) $module_meta['title'],
				'summary'   => (string) ( $module_meta['summary'] ?? '' ),
				'lessons'   => [],
				'exercises' => [],
			];

			foreach ( (array) ( $module_meta['lessons'] ?? [] ) as $lesson_meta ) {
				if ( ! is_array( $lesson_meta ) ) {
					continue;
				}

				$lesson = self::load_lesson( $dir, $lesson_meta );
				if ( $lesson ) {
					$module['lessons'][] = $lesson;
				}
			}

			foreach ( (array) ( $module_meta['exercises'] ?? [] ) as $exercise_meta ) {
				if ( ! is_array( $exercise_meta ) ) {
					continue;
				}

				$exercise = self::load_exercise( $dir, $exercise_meta );
				if ( $exercise ) {
					$module['exercises'][] = $exercise;
				}
			}

			$definition['modules'][] = $module;
		}

		$sources = self::read_json_file( $dir . '/sources.json' );
		if ( $sources ) {
			$definition['references'][] = [
				'slug'      => 'source-bibliography',
				'title'     => 'Source Bibliography',
				'body'      => self::source_bibliography_markdown( $sources ),
				'mime_type' => 'text/markdown',
			];
		}

		foreach ( (array) ( $manifest['references'] ?? [] ) as $reference_meta ) {
			if ( ! is_array( $reference_meta ) ) {
				continue;
			}

			$reference = self::load_reference( $dir, $reference_meta );
			if ( $reference ) {
				$definition['references'][] = $reference;
			}
		}

		return $definition;
	}

	public static function audit_all( string $root = '' ): array {
		$root  = $root !== '' ? $root : self::root();
		$packs = [];
		$valid = true;

		if ( ! is_dir( $root ) ) {
			return [
				'valid'       => false,
				'root'        => $root,
				'pack_count'  => 0,
				'fingerprint' => self::fingerprint( $root ),
				'packs'       => [],
				'errors'      => [ 'Course pack root does not exist: ' . $root ],
			];
		}

		foreach ( glob( rtrim( $root, '/\\' ) . '/*/course.json' ) ?: [] as $manifest_file ) {
			$pack = self::audit( dirname( $manifest_file ) );
			$packs[] = $pack;
			if ( ! $pack['valid'] ) {
				$valid = false;
			}
		}

		$errors = [];
		if ( ! $packs ) {
			$valid    = false;
			$errors[] = 'No course packs found under: ' . $root;
		}

		return [
			'valid'       => $valid,
			'root'        => $root,
			'pack_count'  => count( $packs ),
			'fingerprint' => self::fingerprint( $root ),
			'packs'       => $packs,
			'errors'      => $errors,
		];
	}

	public static function audit( string $dir ): array {
		$errors   = [];
		$warnings = [];
		$counts   = [
			'modules'    => 0,
			'lessons'    => 0,
			'exercises'  => 0,
			'references' => 0,
			'sources'    => 0,
		];

		$manifest_result = self::decode_json_file( $dir . '/course.json' );
		if ( $manifest_result['error'] !== '' ) {
			return self::audit_result( $dir, '', $errors, $warnings, $counts, [ $manifest_result['error'] ] );
		}

		$manifest = $manifest_result['data'];
		$slug     = (string) ( $manifest['slug'] ?? '' );

		self::require_text( $manifest, 'slug', 'course.json', $errors );
		self::require_text( $manifest, 'name', 'course.json', $errors );
		self::require_text( $manifest, 'description', 'course.json', $errors );
		self::require_text( $manifest, 'instructions', 'course.json', $errors );

		if ( $slug !== '' && ! self::valid_slug( $slug ) ) {
			$errors[] = 'course.json: slug must contain only lowercase letters, numbers, and dashes.';
		}

		$schema_version = (int) ( $manifest['schema_version'] ?? 0 );
		if ( $schema_version !== self::SCHEMA_VERSION ) {
			$errors[] = sprintf( 'course.json: schema_version must be %d.', self::SCHEMA_VERSION );
		}

		if ( ! is_array( $manifest['modules'] ?? null ) || empty( $manifest['modules'] ) ) {
			$errors[] = 'course.json: modules must be a non-empty array.';
		}

		$module_slugs         = [];
		$lesson_slugs         = [];
		$exercise_slugs       = [];
		$exercise_lesson_refs = [];
		$referenced_files     = [
			'course.json' => true,
		];

		foreach ( (array) ( $manifest['modules'] ?? [] ) as $module_index => $module ) {
			$module_label = sprintf( 'course.json: modules[%d]', $module_index );
			if ( ! is_array( $module ) ) {
				$errors[] = $module_label . ' must be an object.';
				continue;
			}

			self::require_text( $module, 'slug', $module_label, $errors );
			self::require_text( $module, 'title', $module_label, $errors );
			$module_slug = (string) ( $module['slug'] ?? '' );
			self::record_slug( $module_slug, $module_label, $module_slugs, $errors );
			$counts['modules']++;

			if ( ! is_array( $module['lessons'] ?? null ) ) {
				$errors[] = $module_label . ': lessons must be an array.';
			}

			foreach ( (array) ( $module['lessons'] ?? [] ) as $lesson_index => $lesson ) {
				$lesson_label = sprintf( '%s.lessons[%d]', $module_label, $lesson_index );
				if ( ! is_array( $lesson ) ) {
					$errors[] = $lesson_label . ' must be an object.';
					continue;
				}

				self::require_text( $lesson, 'slug', $lesson_label, $errors );
				self::require_text( $lesson, 'title', $lesson_label, $errors );
				self::require_text( $lesson, 'file', $lesson_label, $errors );
				self::record_slug( (string) ( $lesson['slug'] ?? '' ), $lesson_label, $lesson_slugs, $errors );
				$lesson_path = self::validate_pack_file( $dir, (string) ( $lesson['file'] ?? '' ), $lesson_label, $errors );
				self::record_referenced_file( $dir, $lesson_path, $referenced_files );

				$body = self::read_pack_text( $dir, (string) ( $lesson['file'] ?? '' ) );
				if ( trim( $body ) === '' ) {
					$errors[] = $lesson_label . ': lesson file must not be empty.';
				}

				if ( ! is_array( $lesson['objectives'] ?? null ) || empty( $lesson['objectives'] ) ) {
					$warnings[] = $lesson_label . ': objectives should be a non-empty array.';
				}

				$counts['lessons']++;
			}

			if ( ! is_array( $module['exercises'] ?? null ) ) {
				$errors[] = $module_label . ': exercises must be an array.';
			}

			foreach ( (array) ( $module['exercises'] ?? [] ) as $exercise_index => $exercise_meta ) {
				$exercise_label = sprintf( '%s.exercises[%d]', $module_label, $exercise_index );
				if ( ! is_array( $exercise_meta ) ) {
					$errors[] = $exercise_label . ' must be an object.';
					continue;
				}

				self::require_text( $exercise_meta, 'file', $exercise_label, $errors );
				$exercise_path = self::validate_pack_file( $dir, (string) ( $exercise_meta['file'] ?? '' ), $exercise_label, $errors );
				if ( $exercise_path === '' ) {
					continue;
				}

				$exercise_result = self::decode_json_file( $exercise_path );
				if ( $exercise_result['error'] !== '' ) {
					$errors[] = $exercise_label . ': ' . $exercise_result['error'];
					continue;
				}

				self::record_referenced_file( $dir, $exercise_path, $referenced_files );
				self::audit_exercise( $exercise_result['data'], $exercise_label, $exercise_slugs, $exercise_lesson_refs, $errors, $warnings );
				$counts['exercises']++;
			}
		}

		foreach ( $exercise_lesson_refs as $ref ) {
			if ( ! isset( $lesson_slugs[ $ref['lesson_slug'] ] ) ) {
				$errors[] = sprintf( '%s: lesson_slug "%s" does not match a lesson in this pack.', $ref['label'], $ref['lesson_slug'] );
			}
		}

		$sources_path = $dir . '/sources.json';
		if ( is_readable( $sources_path ) ) {
			$referenced_files['sources.json'] = true;
			$sources_result = self::decode_json_file( $sources_path );
			if ( $sources_result['error'] !== '' ) {
				$errors[] = $sources_result['error'];
			} else {
				foreach ( $sources_result['data'] as $label => $url ) {
					$counts['sources']++;
					if ( ! is_string( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
						$errors[] = sprintf( 'sources.json: "%s" must be a valid URL.', (string) $label );
					}
				}
			}
		} else {
			$warnings[] = 'sources.json is recommended for serious course packs.';
		}

		$reference_slugs = [ 'source-bibliography' => true ];
		foreach ( (array) ( $manifest['references'] ?? [] ) as $reference_index => $reference ) {
			$reference_label = sprintf( 'course.json: references[%d]', $reference_index );
			if ( ! is_array( $reference ) ) {
				$errors[] = $reference_label . ' must be an object.';
				continue;
			}

			self::require_text( $reference, 'slug', $reference_label, $errors );
			self::require_text( $reference, 'title', $reference_label, $errors );
			self::require_text( $reference, 'file', $reference_label, $errors );
			self::record_slug( (string) ( $reference['slug'] ?? '' ), $reference_label, $reference_slugs, $errors );
			$reference_path = self::validate_pack_file( $dir, (string) ( $reference['file'] ?? '' ), $reference_label, $errors );
			self::record_referenced_file( $dir, $reference_path, $referenced_files );
			$counts['references']++;
		}

		if ( $counts['lessons'] === 0 ) {
			$errors[] = 'Course pack must contain at least one lesson.';
		}

		if ( $counts['exercises'] === 0 ) {
			$errors[] = 'Course pack must contain at least one exercise.';
		}

		foreach ( self::unused_pack_files( $dir, $referenced_files ) as $unused_file ) {
			$warnings[] = 'Unreferenced course-pack file: ' . $unused_file;
		}

		return self::audit_result( $dir, $slug, $errors, $warnings, $counts );
	}

	public static function fingerprint( string $root = '' ): string {
		$root = $root !== '' ? $root : self::root();
		$base = realpath( $root );
		if ( ! $base ) {
			return hash( 'sha256', 'missing:' . $root );
		}

		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files, SORT_STRING );
		$context = hash_init( 'sha256' );
		foreach ( $files as $file ) {
			$relative = substr( $file, strlen( $base ) + 1 );
			hash_update( $context, $relative . "\0" );
			hash_update_file( $context, $file );
			hash_update( $context, "\0" );
		}

		return hash_final( $context );
	}

	public static function default_expected_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'summary' => [ 'type' => 'string' ],
				'work'    => [ 'type' => 'string' ],
				'checks'  => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
			'required'   => [ 'summary', 'work', 'checks' ],
		];
	}

	private static function audit_exercise( array $exercise, string $label, array &$exercise_slugs, array &$lesson_refs, array &$errors, array &$warnings ): void {
		self::require_text( $exercise, 'slug', $label, $errors );
		self::require_text( $exercise, 'title', $label, $errors );
		self::require_text( $exercise, 'prompt', $label, $errors );
		self::record_slug( (string) ( $exercise['slug'] ?? '' ), $label, $exercise_slugs, $errors );

		$lesson_slug = (string) ( $exercise['lesson_slug'] ?? '' );
		if ( $lesson_slug !== '' ) {
			$lesson_refs[] = [
				'label'       => $label,
				'lesson_slug' => $lesson_slug,
			];
		}

		if ( isset( $exercise['passing_score'] ) && ( ! is_numeric( $exercise['passing_score'] ) || (float) $exercise['passing_score'] < 0 || (float) $exercise['passing_score'] > 1 ) ) {
			$errors[] = $label . ': passing_score must be between 0 and 1.';
		}

		if ( isset( $exercise['expected_output_schema'] ) && ! is_array( $exercise['expected_output_schema'] ) ) {
			$errors[] = $label . ': expected_output_schema must be an object.';
		} elseif ( ! isset( $exercise['expected_output_schema'] ) ) {
			$warnings[] = $label . ': expected_output_schema is recommended so LLM answers are machine-checkable without relying on the loader default.';
		}

		$rubric = $exercise['rubric'] ?? null;
		if ( ! is_array( $rubric ) || ! is_array( $rubric['criteria'] ?? null ) || empty( $rubric['criteria'] ) ) {
			$errors[] = $label . ': rubric.criteria must be a non-empty array.';
			return;
		}

		foreach ( $rubric['criteria'] as $criterion_index => $criterion ) {
			$criterion_label = sprintf( '%s.rubric.criteria[%d]', $label, $criterion_index );
			if ( ! is_array( $criterion ) ) {
				$errors[] = $criterion_label . ' must be an object.';
				continue;
			}

			self::require_text( $criterion, 'name', $criterion_label, $errors );
			if ( isset( $criterion['points'] ) && ( ! is_numeric( $criterion['points'] ) || (float) $criterion['points'] <= 0 ) ) {
				$errors[] = $criterion_label . ': points must be a positive number.';
			}

			$has_required_terms = ! empty( self::string_list( $criterion['required_terms'] ?? [] ) );
			$has_any_terms      = ! empty( self::string_list( $criterion['any_terms'] ?? [] ) );
			if ( ! $has_required_terms && ! $has_any_terms ) {
				$warnings[] = $criterion_label . ': add required_terms or any_terms for deterministic grading.';
			}
		}
	}

	private static function audit_result( string $dir, string $slug, array $errors, array $warnings, array $counts, array $extra_errors = [] ): array {
		$errors = array_merge( $errors, $extra_errors );

		return [
			'valid'    => empty( $errors ),
			'path'     => $dir,
			'slug'     => $slug,
			'counts'   => $counts,
			'errors'   => array_values( $errors ),
			'warnings' => array_values( $warnings ),
		];
	}

	private static function load_lesson( string $dir, array $lesson_meta ): ?array {
		if ( empty( $lesson_meta['slug'] ) || empty( $lesson_meta['title'] ) || empty( $lesson_meta['file'] ) ) {
			return null;
		}

		return [
			'position'   => (int) ( $lesson_meta['position'] ?? 0 ),
			'slug'       => (string) $lesson_meta['slug'],
			'title'      => (string) $lesson_meta['title'],
			'objectives' => self::string_list( $lesson_meta['objectives'] ?? [] ),
			'body'       => self::read_pack_text( $dir, (string) $lesson_meta['file'] ),
		];
	}

	private static function load_exercise( string $dir, array $exercise_meta ): ?array {
		if ( empty( $exercise_meta['file'] ) ) {
			return null;
		}

		$exercise = self::read_json_file( self::pack_path( $dir, (string) $exercise_meta['file'] ) );
		if ( ! $exercise || empty( $exercise['slug'] ) || empty( $exercise['title'] ) ) {
			return null;
		}

		$exercise['position']      = (int) ( $exercise['position'] ?? 0 );
		$exercise['lesson_slug']   = (string) ( $exercise['lesson_slug'] ?? '' );
		$exercise['prompt']        = (string) ( $exercise['prompt'] ?? '' );
		$exercise['hints']         = self::string_list( $exercise['hints'] ?? [] );
		$exercise['passing_score'] = isset( $exercise['passing_score'] ) ? (float) $exercise['passing_score'] : 0.8;
		$exercise['rubric']        = is_array( $exercise['rubric'] ?? null ) ? $exercise['rubric'] : [ 'criteria' => [] ];

		if ( ! isset( $exercise['expected_output_schema'] ) || ! is_array( $exercise['expected_output_schema'] ) ) {
			$exercise['expected_output_schema'] = self::default_expected_output_schema();
		}

		return $exercise;
	}

	private static function load_reference( string $dir, array $reference_meta ): ?array {
		if ( empty( $reference_meta['slug'] ) || empty( $reference_meta['title'] ) || empty( $reference_meta['file'] ) ) {
			return null;
		}

		return [
			'slug'      => (string) $reference_meta['slug'],
			'title'     => (string) $reference_meta['title'],
			'body'      => self::read_pack_text( $dir, (string) $reference_meta['file'] ),
			'mime_type' => (string) ( $reference_meta['mime_type'] ?? 'text/markdown' ),
		];
	}

	private static function require_text( array $data, string $field, string $label, array &$errors ): void {
		if ( ! isset( $data[ $field ] ) || trim( (string) $data[ $field ] ) === '' ) {
			$errors[] = sprintf( '%s: %s is required.', $label, $field );
		}
	}

	private static function record_slug( string $slug, string $label, array &$seen, array &$errors ): void {
		if ( $slug === '' ) {
			return;
		}

		if ( ! self::valid_slug( $slug ) ) {
			$errors[] = sprintf( '%s: slug "%s" must contain only lowercase letters, numbers, and dashes.', $label, $slug );
			return;
		}

		if ( isset( $seen[ $slug ] ) ) {
			$errors[] = sprintf( '%s: duplicate slug "%s".', $label, $slug );
			return;
		}

		$seen[ $slug ] = true;
	}

	private static function validate_pack_file( string $dir, string $relative_path, string $label, array &$errors ): string {
		$path = self::pack_path( $dir, $relative_path );
		if ( $relative_path === '' || $path === '' || ! is_readable( $path ) ) {
			$errors[] = sprintf( '%s: file "%s" must exist inside the course pack.', $label, $relative_path );
			return '';
		}

		return $path;
	}

	private static function record_referenced_file( string $dir, string $path, array &$referenced_files ): void {
		$relative_path = self::relative_pack_path( $dir, $path );
		if ( $relative_path !== '' ) {
			$referenced_files[ $relative_path ] = true;
		}
	}

	private static function unused_pack_files( string $dir, array $referenced_files ): array {
		$base = realpath( $dir );
		if ( ! $base ) {
			return [];
		}

		$unused = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$extension = strtolower( $file->getExtension() );
			if ( ! in_array( $extension, [ 'json', 'md' ], true ) ) {
				continue;
			}

			$relative_path = self::relative_pack_path( $dir, $file->getPathname() );
			if ( $relative_path !== '' && ! isset( $referenced_files[ $relative_path ] ) ) {
				$unused[] = $relative_path;
			}
		}

		sort( $unused, SORT_STRING );
		return $unused;
	}

	private static function relative_pack_path( string $dir, string $path ): string {
		$base = realpath( $dir );
		$real = realpath( $path );
		if ( ! $base || ! $real || strpos( $real, $base . DIRECTORY_SEPARATOR ) !== 0 ) {
			return '';
		}

		return str_replace( DIRECTORY_SEPARATOR, '/', substr( $real, strlen( $base ) + 1 ) );
	}

	private static function valid_slug( string $slug ): bool {
		return (bool) preg_match( '/^[a-z0-9-]+$/', $slug );
	}

	private static function pack_path( string $dir, string $relative_path ): string {
		$base = realpath( $dir );
		if ( ! $base ) {
			return '';
		}

		$path = realpath( $base . '/' . ltrim( $relative_path, '/\\' ) );
		if ( ! $path || strpos( $path, $base . DIRECTORY_SEPARATOR ) !== 0 ) {
			return '';
		}

		return $path;
	}

	private static function read_pack_text( string $dir, string $relative_path ): string {
		$path = self::pack_path( $dir, $relative_path );
		if ( $path === '' || ! is_readable( $path ) ) {
			return '';
		}

		$contents = file_get_contents( $path );
		return is_string( $contents ) ? $contents : '';
	}

	private static function read_json_file( string $path ): array {
		$result = self::decode_json_file( $path );
		return $result['error'] === '' ? $result['data'] : [];
	}

	private static function decode_json_file( string $path ): array {
		if ( $path === '' || ! is_readable( $path ) ) {
			return [
				'data'  => [],
				'error' => 'JSON file is not readable: ' . $path,
			];
		}

		$contents = file_get_contents( $path );
		if ( ! is_string( $contents ) ) {
			return [
				'data'  => [],
				'error' => 'Could not read JSON file: ' . $path,
			];
		}

		$decoded = json_decode( $contents, true );
		if ( ! is_array( $decoded ) ) {
			return [
				'data'  => [],
				'error' => 'Invalid JSON in ' . $path . ': ' . json_last_error_msg(),
			];
		}

		return [
			'data'  => $decoded,
			'error' => '',
		];
	}

	private static function source_bibliography_markdown( array $sources ): string {
		$lines = [ '# Source Bibliography', '' ];
		foreach ( $sources as $label => $url ) {
			$lines[] = '- ' . str_replace( '_', ' ', (string) $label ) . ': ' . (string) $url;
		}

		return implode( "\n", $lines );
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
						return trim( (string) $item );
					},
					$value
				)
			)
		);
	}
}
