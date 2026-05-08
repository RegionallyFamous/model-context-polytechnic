#!/usr/bin/env php
<?php
require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$root = dirname( __DIR__ );
$options = parse_args( array_slice( $argv, 1 ) );
$course_slug = (string) ( $options['course'] ?? '' );
$scenario_dir = (string) ( $options['scenarios'] ?? $root . '/tests/course-scenarios' );
$golden_dir = (string) ( $options['golden'] ?? $root . '/tests/golden-exams' );
$json = ! empty( $options['json'] );

$course = select_course( CoursePack::definitions(), $course_slug );
if ( ! $course ) {
	fwrite( STDERR, "Course not found.\n" );
	exit( 1 );
}

$inventory = stress_inventory( $course, $root );
$scenario_report = run_scenarios( $scenario_dir, $course, $inventory );
$golden_report = run_golden_exams( $golden_dir, $course, $inventory );
$report = [
	'course' => [
		'slug' => $course['slug'],
		'name' => $course['name'],
	],
	'scenarios' => $scenario_report,
	'golden_exams' => $golden_report,
	'summary' => [
		'passed' => $scenario_report['failed'] === 0 && $golden_report['failed'] === 0,
		'failed' => $scenario_report['failed'] + $golden_report['failed'],
		'total_checks' => $scenario_report['total'] + $golden_report['total'],
	],
];

if ( $json ) {
	echo json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
} else {
	print_report( $report );
}

exit( $report['summary']['passed'] ? 0 : 1 );

function parse_args( array $args ): array {
	$options = [];
	foreach ( $args as $arg ) {
		if ( strpos( $arg, '--' ) !== 0 ) {
			continue;
		}

		$arg = substr( $arg, 2 );
		if ( strpos( $arg, '=' ) === false ) {
			$options[ $arg ] = true;
			continue;
		}

		[ $key, $value ] = explode( '=', $arg, 2 );
		$options[ $key ] = $value;
	}

	return $options;
}

function select_course( array $definitions, string $course_slug ): ?array {
	if ( $course_slug === '' ) {
		return $definitions[0] ?? null;
	}

	foreach ( $definitions as $definition ) {
		if ( (string) $definition['slug'] === $course_slug ) {
			return $definition;
		}
	}

	return null;
}

function stress_inventory( array $course, string $root ): array {
	$lessons = [];
	$exercises = [];
	$references = [];
	$course_corpus = [
		$course['name'],
		$course['description'],
		$course['instructions'],
	];

	foreach ( $course['modules'] as $module ) {
		$course_corpus[] = $module['title'];
		$course_corpus[] = $module['summary'];

		foreach ( $module['lessons'] as $lesson ) {
			$lessons[ $lesson['slug'] ] = $lesson;
			$course_corpus[] = $lesson['title'];
			$course_corpus[] = implode( ' ', $lesson['objectives'] );
			$course_corpus[] = $lesson['body'];
		}

		foreach ( $module['exercises'] as $exercise ) {
			$exercises[ $exercise['slug'] ] = $exercise;
			$course_corpus[] = $exercise['title'];
			$course_corpus[] = $exercise['prompt'];
			$course_corpus[] = implode( ' ', string_list( $exercise['hints'] ?? [] ) );
			$course_corpus[] = json_encode( $exercise['rubric'], JSON_UNESCAPED_SLASHES );
			$course_corpus[] = json_encode( $exercise['expected_output_schema'] ?? [], JSON_UNESCAPED_SLASHES );
			$course_corpus[] = json_encode( $exercise['model_answer'] ?? [], JSON_UNESCAPED_SLASHES );
		}
	}

	foreach ( $course['references'] as $reference ) {
		$references[ $reference['slug'] ] = $reference;
		$course_corpus[] = $reference['title'];
		$course_corpus[] = $reference['body'];
	}

	$project_corpus = $course_corpus;
	foreach ( project_text_files( $root ) as $file ) {
		$text = file_get_contents( $file );
		if ( is_string( $text ) ) {
			$project_corpus[] = $text;
		}
	}

	return [
		'lessons' => $lessons,
		'exercises' => $exercises,
		'references' => $references,
		'course_corpus' => normalize_text( implode( "\n", $course_corpus ) ),
		'project_corpus' => normalize_text( implode( "\n", $project_corpus ) ),
		'public_tools' => public_tools(),
	];
}

function project_text_files( string $root ): array {
	$files = [];
	$allowed = [ 'php', 'json', 'md', 'html', 'css' ];
	$skip_parts = [ '/vendor/', '/.git/' ];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		$path = $file->getPathname();
		foreach ( $skip_parts as $skip ) {
			if ( strpos( $path, $skip ) !== false ) {
				continue 2;
			}
		}

		if ( in_array( strtolower( $file->getExtension() ), $allowed, true ) ) {
			$files[] = $path;
		}
	}

	sort( $files, SORT_STRING );
	return $files;
}

function public_tools(): array {
	return [
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
		'search-course',
		'orient',
		'server-status',
		'client-config',
		'echo-schema',
	];
}

function run_scenarios( string $dir, array $course, array $inventory ): array {
	$suites = load_json_suites( $dir );
	$results = [];

	foreach ( $suites as $suite ) {
		if ( (string) ( $suite['course'] ?? '' ) !== (string) $course['slug'] ) {
			continue;
		}

		foreach ( (array) ( $suite['scenarios'] ?? [] ) as $scenario ) {
			if ( is_array( $scenario ) ) {
				$results[] = evaluate_scenario( $scenario, $inventory );
			}
		}
	}

	$failed = count(
		array_filter(
			$results,
			static function ( array $result ): bool {
				return empty( $result['passed'] );
			}
		)
	);

	return [
		'total' => count( $results ),
		'passed' => count( $results ) - $failed,
		'failed' => $failed,
		'results' => $results,
	];
}

function evaluate_scenario( array $scenario, array $inventory ): array {
	$checks = [];
	$required_tools = string_list( $scenario['expected_tools'] ?? [] );
	foreach ( $required_tools as $tool ) {
		$checks[] = check_result(
			in_array( $tool, $inventory['public_tools'], true ),
			'tool',
			$tool,
			"Public tool {$tool} should exist."
		);
	}

	foreach ( string_list( $scenario['route'] ?? [] ) as $step ) {
		[ $tool, $slug ] = route_step_parts( $step );
		$ok = in_array( $tool, $inventory['public_tools'], true );
		if ( $ok && $slug !== '' && in_array( $tool, [ 'get-lesson' ], true ) ) {
			$ok = isset( $inventory['lessons'][ $slug ] );
		}
		if ( $ok && $slug !== '' && in_array( $tool, [ 'get-exercise', 'attempt-exercise' ], true ) ) {
			$ok = isset( $inventory['exercises'][ $slug ] );
		}
		$checks[] = check_result( $ok, 'route', $step, "Route step {$step} should be available." );
	}

	foreach ( string_list( $scenario['target_lessons'] ?? [] ) as $slug ) {
		$checks[] = check_result( isset( $inventory['lessons'][ $slug ] ), 'lesson', $slug, "Lesson {$slug} should exist." );
	}

	foreach ( string_list( $scenario['target_exercises'] ?? [] ) as $slug ) {
		$checks[] = check_result( isset( $inventory['exercises'][ $slug ] ), 'exercise', $slug, "Exercise {$slug} should exist." );
	}

	foreach ( string_list( $scenario['target_references'] ?? [] ) as $slug ) {
		$checks[] = check_result( isset( $inventory['references'][ $slug ] ), 'reference', $slug, "Reference {$slug} should exist." );
	}

	foreach ( string_list( $scenario['required_course_terms'] ?? [] ) as $term ) {
		$checks[] = check_result( contains_term( $inventory['course_corpus'], $term ), 'course_term', $term, "Course should mention {$term}." );
	}

	foreach ( string_list( $scenario['required_project_terms'] ?? [] ) as $term ) {
		$checks[] = check_result( contains_term( $inventory['project_corpus'], $term ), 'project_term', $term, "Project should mention {$term}." );
	}

	$total = max( 1, count( $checks ) );
	$passed_count = count(
		array_filter(
			$checks,
			static function ( array $check ): bool {
				return $check['passed'];
			}
		)
	);
	$score = round( $passed_count / $total, 4 );
	$minimum = isset( $scenario['minimum_score'] ) && is_numeric( $scenario['minimum_score'] )
		? max( 0.0, min( 1.0, (float) $scenario['minimum_score'] ) )
		: 1.0;
	$passed = $score >= $minimum;

	return [
		'slug' => (string) ( $scenario['slug'] ?? 'unnamed-scenario' ),
		'title' => (string) ( $scenario['title'] ?? '' ),
		'learner' => (string) ( $scenario['learner'] ?? '' ),
		'failure_mode' => (string) ( $scenario['failure_mode'] ?? '' ),
		'score' => $score,
		'minimum_score' => $minimum,
		'passed' => $passed,
		'failed_checks' => array_values(
			array_filter(
				$checks,
				static function ( array $check ): bool {
					return ! $check['passed'];
				}
			)
		),
		'feedback' => [
			'feedback_type' => $passed ? 'helpful' : 'confusing',
			'target_type' => (string) ( $scenario['target_type'] ?? 'course' ),
			'target_slug' => (string) ( $scenario['target_slug'] ?? '' ),
			'rating' => $passed ? 5 : 2,
			'comment' => $passed
				? 'Stress path has enough public course guidance for this learner failure mode.'
				: 'Stress path is missing one or more handles, tools, targets, or recovery terms.',
			'suggested_fix' => $passed
				? 'Preserve this workflow path.'
				: missing_fix_message( $checks ),
		],
	];
}

function route_step_parts( string $step ): array {
	if ( strpos( $step, ':' ) === false ) {
		return [ $step, '' ];
	}

	[ $tool, $slug ] = explode( ':', $step, 2 );
	return [ $tool, $slug ];
}

function check_result( bool $passed, string $type, string $target, string $message ): array {
	return [
		'passed' => $passed,
		'type' => $type,
		'target' => $target,
		'message' => $message,
	];
}

function missing_fix_message( array $checks ): string {
	$failed = array_values(
		array_filter(
			$checks,
			static function ( array $check ): bool {
				return ! $check['passed'];
			}
		)
	);
	$targets = array_map(
		static function ( array $check ): string {
			return $check['type'] . ':' . $check['target'];
		},
		array_slice( $failed, 0, 8 )
	);

	return 'Add or clarify: ' . implode( ', ', $targets ) . '.';
}

function run_golden_exams( string $dir, array $course, array $inventory ): array {
	$suites = load_json_suites( $dir );
	$results = [];

	foreach ( $suites as $suite ) {
		if ( (string) ( $suite['course'] ?? '' ) !== (string) $course['slug'] ) {
			continue;
		}

		foreach ( (array) ( $suite['exams'] ?? [] ) as $exam ) {
			if ( is_array( $exam ) ) {
				$results[] = evaluate_exam( $exam, $inventory );
			}
		}
	}

	$failed = count(
		array_filter(
			$results,
			static function ( array $result ): bool {
				return empty( $result['passed'] );
			}
		)
	);

	$strong_scores = array_map(
		static function ( array $result ): float {
			return (float) $result['strong']['score'];
		},
		$results
	);

	return [
		'total' => count( $results ),
		'passed' => count( $results ) - $failed,
		'failed' => $failed,
		'average_strong_score' => $strong_scores ? round( array_sum( $strong_scores ) / count( $strong_scores ), 4 ) : 0.0,
		'results' => $results,
	];
}

function evaluate_exam( array $exam, array $inventory ): array {
	$slug = (string) ( $exam['exercise_slug'] ?? '' );
	$exercise = $inventory['exercises'][ $slug ] ?? null;
	if ( ! $exercise ) {
		return [
			'slug' => (string) ( $exam['slug'] ?? 'unnamed-exam' ),
			'exercise_slug' => $slug,
			'passed' => false,
			'error' => 'Exercise not found.',
			'weak' => [ 'score' => 0, 'passed' => false ],
			'strong' => [ 'score' => 0, 'passed' => false ],
		];
	}

	$weak = score_answer( $exercise, (string) ( $exam['weak_answer'] ?? '' ) );
	$strong = score_answer( $exercise, (string) ( $exam['strong_answer'] ?? '' ) );
	$expect = is_array( $exam['expect'] ?? null ) ? $exam['expect'] : [];
	$min_delta = isset( $expect['min_delta'] ) && is_numeric( $expect['min_delta'] ) ? (float) $expect['min_delta'] : 0.25;
	$expected_weak_passes = ! empty( $expect['weak_passes'] );
	$expected_strong_passes = array_key_exists( 'strong_passes', $expect ) ? ! empty( $expect['strong_passes'] ) : true;
	$delta = round( $strong['score'] - $weak['score'], 4 );
	$passed = $weak['passed'] === $expected_weak_passes
		&& $strong['passed'] === $expected_strong_passes
		&& $delta >= $min_delta;

	return [
		'slug' => (string) ( $exam['slug'] ?? $slug ),
		'exercise_slug' => $slug,
		'passed' => $passed,
		'delta' => $delta,
		'minimum_delta' => $min_delta,
		'weak' => $weak,
		'strong' => $strong,
		'feedback' => [
			'feedback_type' => $passed ? 'helpful' : 'bug',
			'target_type' => 'exercise',
			'target_slug' => $slug,
			'rating' => $passed ? 5 : 1,
			'comment' => $passed
				? 'Golden exam distinguishes weak and strong answers for this exercise.'
				: 'Golden exam did not separate weak and strong answers as expected.',
			'suggested_fix' => $passed
				? 'Preserve rubric coverage.'
				: 'Tighten rubric terms, improve the strong fixture, or adjust the weak fixture.',
		],
	];
}

function score_answer( array $exercise, string $answer ): array {
	$rubric = is_array( $exercise['rubric'] ?? null ) ? $exercise['rubric'] : [];
	$criteria = isset( $rubric['criteria'] ) && is_array( $rubric['criteria'] ) ? $rubric['criteria'] : [];
	$total = 0.0;
	$earned = 0.0;
	$missing_terms = [];
	$matched_terms = [];
	$answer_lower = normalize_text( $answer );

	foreach ( $criteria as $criterion ) {
		if ( ! is_array( $criterion ) ) {
			continue;
		}

		$points = isset( $criterion['points'] ) && is_numeric( $criterion['points'] ) ? max( 0.0, (float) $criterion['points'] ) : 1.0;
		if ( $points <= 0 ) {
			continue;
		}

		$required_terms = string_list( $criterion['required_terms'] ?? [] );
		$any_terms = string_list( $criterion['any_terms'] ?? [] );
		$total += $points;

		if ( $required_terms ) {
			$matched = [];
			$missing = [];
			foreach ( $required_terms as $term ) {
				if ( contains_term( $answer_lower, $term ) ) {
					$matched[] = $term;
					$matched_terms[] = $term;
				} else {
					$missing[] = $term;
					$missing_terms[] = $term;
				}
			}
			$earned += $points * ( count( $matched ) / max( 1, count( $required_terms ) ) );
		} elseif ( $any_terms ) {
			$matched = [];
			foreach ( $any_terms as $term ) {
				if ( contains_term( $answer_lower, $term ) ) {
					$matched[] = $term;
					$matched_terms[] = $term;
				}
			}
			if ( $matched ) {
				$earned += $points;
			} else {
				$missing_terms = array_merge( $missing_terms, $any_terms );
			}
		}
	}

	$total = $total > 0 ? $total : 1.0;
	$score = round( $earned / $total, 4 );
	$passing_score = isset( $exercise['passing_score'] ) && is_numeric( $exercise['passing_score'] )
		? max( 0.0, min( 1.0, (float) $exercise['passing_score'] ) )
		: 0.7;

	return [
		'score' => $score,
		'passed' => $score >= $passing_score,
		'passing_score' => $passing_score,
		'matched_terms' => array_values( array_unique( $matched_terms ) ),
		'missing_terms' => array_values( array_unique( $missing_terms ) ),
	];
}

function load_json_suites( string $dir ): array {
	if ( ! is_dir( $dir ) ) {
		return [];
	}

	$suites = [];
	foreach ( glob( rtrim( $dir, '/\\' ) . '/*.json' ) ?: [] as $file ) {
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( is_array( $data ) ) {
			$suites[] = $data;
		}
	}

	return $suites;
}

function string_list( $value ): array {
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
			),
			static function ( string $item ): bool {
				return $item !== '';
			}
		)
	);
}

function contains_term( string $haystack, string $term ): bool {
	$term = normalize_text( $term );
	if ( $term === '' ) {
		return false;
	}

	if ( strpos( $haystack, $term ) !== false ) {
		return true;
	}

	$subject = negated_term_subject( $term );
	if ( $subject === '' ) {
		return false;
	}

	return contains_negated_subject( $haystack, $subject );
}

function normalize_text( string $text ): string {
	$text = str_replace(
		[ "\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d" ],
		[ "'", "'", '"', '"' ],
		$text
	);
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );

	return (string) preg_replace( '/\s+/', ' ', trim( $text ) );
}

function negated_term_subject( string $term ): string {
	foreach ( [ 'not ', 'no ', 'without ', 'never ' ] as $prefix ) {
		if ( strpos( $term, $prefix ) === 0 ) {
			return trim( substr( $term, strlen( $prefix ) ) );
		}
	}

	return '';
}

function contains_negated_subject( string $haystack, string $subject ): bool {
	$subject_pattern = phrase_pattern( $subject );
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
		if ( preg_match( $pattern, $haystack ) ) {
			return true;
		}
	}

	return false;
}

function phrase_pattern( string $phrase ): string {
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

function print_report( array $report ): void {
	echo 'Stress Lab: ' . $report['course']['name'] . ' (' . $report['course']['slug'] . ')' . PHP_EOL;
	echo sprintf(
		'Scenarios: %d/%d passed.',
		$report['scenarios']['passed'],
		$report['scenarios']['total']
	) . PHP_EOL;
	echo sprintf(
		'Golden exams: %d/%d passed. Average strong score: %.4f',
		$report['golden_exams']['passed'],
		$report['golden_exams']['total'],
		$report['golden_exams']['average_strong_score']
	) . PHP_EOL;

	if ( ! $report['summary']['passed'] ) {
		echo PHP_EOL . 'Failures:' . PHP_EOL;
		foreach ( $report['scenarios']['results'] as $result ) {
			if ( empty( $result['passed'] ) ) {
				echo '  [scenario] ' . $result['slug'] . ' - ' . $result['feedback']['suggested_fix'] . PHP_EOL;
			}
		}
		foreach ( $report['golden_exams']['results'] as $result ) {
			if ( empty( $result['passed'] ) ) {
				echo '  [golden] ' . $result['slug'] . ' - ' . ( $result['feedback']['suggested_fix'] ?? $result['error'] ?? 'failed' ) . PHP_EOL;
			}
		}
	}
}
