#!/usr/bin/env php
<?php
require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$options = parse_args( array_slice( $argv, 1 ) );
$passes = max( 1, min( 50, (int) ( $options['passes'] ?? 6 ) ) );
$course_slug = (string) ( $options['course'] ?? '' );
$json = ! empty( $options['json'] );
$agent_brief = ! empty( $options['agent-brief'] );
$fail_on = (string) ( $options['fail-on'] ?? 'critical' );

$definitions = CoursePack::definitions();
if ( ! $definitions ) {
	fwrite( STDERR, "No valid course packs found.\n" );
	exit( 1 );
}

$course = select_course( $definitions, $course_slug );
if ( ! $course ) {
	fwrite( STDERR, "Course not found: {$course_slug}\n" );
	exit( 1 );
}

$lab = run_course_lab( $course, $passes );

if ( $agent_brief ) {
	echo agent_brief( $lab ) . PHP_EOL;
	exit( 0 );
}

if ( $json ) {
	echo json_encode( $lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
} else {
	print_human_report( $lab );
}

$critical_count = count_by_severity( $lab['findings'], 'critical' );
$warning_count = count_by_severity( $lab['findings'], 'warning' );

if ( $fail_on === 'warning' && ( $critical_count > 0 || $warning_count > 0 ) ) {
	exit( 1 );
}

if ( $fail_on === 'critical' && $critical_count > 0 ) {
	exit( 1 );
}

exit( 0 );

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

function run_course_lab( array $course, int $passes ): array {
	$inventory = course_inventory( $course );
	$findings = array_merge(
		check_public_contract( $course ),
		check_lesson_fitness( $course, $inventory ),
		check_exercise_fitness( $course, $inventory ),
		check_topic_coverage( $course, $inventory )
	);
	$pass_plan = build_pass_plan( $course, $inventory, $passes );
	$score = score_course( $findings, $inventory );

	return [
		'course' => [
			'slug' => $course['slug'],
			'name' => $course['name'],
			'description' => $course['description'],
		],
		'score' => $score,
		'inventory' => [
			'modules' => count( $course['modules'] ),
			'lessons' => count( $inventory['lessons'] ),
			'exercises' => count( $inventory['exercises'] ),
			'references' => count( $course['references'] ),
			'exercised_lessons' => count( $inventory['lesson_exercises'] ),
			'unexercised_lessons' => array_values( array_diff( array_keys( $inventory['lessons'] ), array_keys( $inventory['lesson_exercises'] ) ) ),
		],
		'passes' => $pass_plan,
		'findings' => $findings,
		'improvement_protocol' => [
			'Run this lab before and after course edits.',
			'Spawn a parallel student-reviewer with the agent brief when changing lessons, exercises, or tool contracts.',
			'Apply repeated or high-severity findings to course-pack files first, then rerun composer release:check.',
			'Do not auto-apply public feedback to the syllabus without maintainer review.',
		],
	];
}

function course_inventory( array $course ): array {
	$lessons = [];
	$exercises = [];
	$lesson_exercises = [];
	$corpus = [
		$course['name'],
		$course['description'],
		$course['instructions'],
	];

	foreach ( $course['modules'] as $module ) {
		$corpus[] = $module['title'];
		$corpus[] = $module['summary'];

		foreach ( $module['lessons'] as $lesson ) {
			$lessons[ $lesson['slug'] ] = array_merge( $lesson, [ 'module_slug' => $module['slug'] ] );
			$corpus[] = $lesson['title'];
			$corpus[] = implode( ' ', $lesson['objectives'] );
			$corpus[] = $lesson['body'];
		}

		foreach ( $module['exercises'] as $exercise ) {
			$exercises[ $exercise['slug'] ] = array_merge( $exercise, [ 'module_slug' => $module['slug'] ] );
			if ( ! empty( $exercise['lesson_slug'] ) ) {
				$lesson_exercises[ $exercise['lesson_slug'] ][] = $exercise['slug'];
			}
			$corpus[] = $exercise['title'];
			$corpus[] = $exercise['prompt'];
			$corpus[] = json_encode( $exercise['rubric'], JSON_UNESCAPED_SLASHES );
		}
	}

	foreach ( $course['references'] as $reference ) {
		$corpus[] = $reference['title'];
		$corpus[] = $reference['body'];
	}

	return [
		'lessons' => $lessons,
		'exercises' => $exercises,
		'lesson_exercises' => $lesson_exercises,
		'corpus' => strtolower( implode( "\n", array_filter( $corpus ) ) ),
	];
}

function check_public_contract( array $course ): array {
	$findings = [];
	$required_terms = [
		'begin-course',
		'enrollment_key',
		'get-next-work',
		'attempt-exercise',
		'get-learning-memory',
		'submit-feedback',
		'get-course-improvement-signals',
	];

	foreach ( $required_terms as $term ) {
		if ( strpos( $course['instructions'], $term ) === false ) {
			$findings[] = finding( 'critical', 'course.json', 'missing-public-contract-term', "Course instructions should mention {$term}." );
		}
	}

	return $findings;
}

function check_lesson_fitness( array $course, array $inventory ): array {
	$findings = [];

	foreach ( $inventory['lessons'] as $slug => $lesson ) {
		$bytes = strlen( trim( (string) $lesson['body'] ) );
		if ( count( $lesson['objectives'] ) < 2 ) {
			$findings[] = finding( 'warning', 'lesson:' . $slug, 'thin-objectives', 'Lesson should expose at least two explicit objectives for an LLM learner.' );
		}

		if ( $bytes < 350 ) {
			$findings[] = finding( 'warning', 'lesson:' . $slug, 'lesson-too-thin', 'Lesson is under 350 bytes; consider adding a concrete example or decision rule.' );
		}

		if ( $bytes > 2400 ) {
			$findings[] = finding( 'notice', 'lesson:' . $slug, 'lesson-large', 'Lesson is large enough that search snippets and summaries should stay crisp.' );
		}

		if ( empty( $inventory['lesson_exercises'][ $slug ] ) ) {
			$findings[] = finding( 'notice', 'lesson:' . $slug, 'no-direct-exercise', 'No exercise directly references this lesson_slug. That can be fine, but LLM practice improves when key lessons have an attempt path.' );
		}
	}

	return $findings;
}

function check_exercise_fitness( array $course, array $inventory ): array {
	$findings = [];

	foreach ( $inventory['exercises'] as $slug => $exercise ) {
		if ( empty( $exercise['lesson_slug'] ) || empty( $inventory['lessons'][ $exercise['lesson_slug'] ] ) ) {
			$findings[] = finding( 'critical', 'exercise:' . $slug, 'missing-lesson-link', 'Exercise must point at an existing lesson_slug.' );
		}

		if ( empty( $exercise['expected_output_schema']['required'] ) ) {
			$findings[] = finding( 'warning', 'exercise:' . $slug, 'loose-output-schema', 'Expected output schema should require concrete fields so LLM answers stay comparable.' );
		}

		if ( empty( $exercise['hints'] ) ) {
			$findings[] = finding( 'notice', 'exercise:' . $slug, 'no-hints', 'Exercise has no hints. Add one if repeated attempts fail.' );
		}

		foreach ( (array) ( $exercise['rubric']['criteria'] ?? [] ) as $index => $criterion ) {
			$has_required = ! empty( $criterion['required_terms'] );
			$has_any = ! empty( $criterion['any_terms'] );
			if ( ! $has_required && ! $has_any ) {
				$findings[] = finding( 'warning', 'exercise:' . $slug, 'manual-rubric-' . $index, 'Rubric criterion lacks required_terms or any_terms, so automatic feedback will be weak.' );
			}
		}
	}

	return $findings;
}

function check_topic_coverage( array $course, array $inventory ): array {
	$findings = [];
	$topics = [
		'bootstrap' => [ 'bootstrap', 'plugin name', 'main file' ],
		'security' => [ 'capability', 'nonce', 'sanitize', 'escape' ],
		'rest' => [ 'rest', 'permission_callback' ],
		'storage' => [ 'option', 'custom table', 'dbdelta', 'transient' ],
		'background-work' => [ 'cron', 'schedule' ],
		'blocks' => [ 'block.json', 'interactivity' ],
		'testing' => [ 'phpunit', 'plugin check', 'php -l' ],
		'release' => [ 'wordpress.org', 'readme', 'zip' ],
		'observability' => [ 'log', 'diagnostic' ],
		'llm-interface' => [ 'mcp', 'schema', 'enrollment_key' ],
	];

	foreach ( $topics as $topic => $needles ) {
		$matched = 0;
		foreach ( $needles as $needle ) {
			if ( strpos( $inventory['corpus'], strtolower( $needle ) ) !== false ) {
				$matched++;
			}
		}

		if ( $matched === 0 ) {
			$findings[] = finding( 'critical', 'course:' . $course['slug'], 'missing-topic-' . $topic, "Course corpus does not appear to cover {$topic}." );
		} elseif ( $matched < min( 2, count( $needles ) ) ) {
			$findings[] = finding( 'warning', 'course:' . $course['slug'], 'thin-topic-' . $topic, "Course mentions {$topic}, but coverage may be thin." );
		}
	}

	return $findings;
}

function build_pass_plan( array $course, array $inventory, int $passes ): array {
	$templates = [
		[ 'name' => 'Enrollment and Orientation', 'goal' => 'Verify the LLM can connect, begin, preserve enrollment_key, and identify the first work.' ],
		[ 'name' => 'Retrieval and Syllabus Navigation', 'goal' => 'Use get-next-work, get-syllabus, and search-course to recover only the context needed for the current task.' ],
		[ 'name' => 'Exercise Attempt Quality', 'goal' => 'Attempt exercises using expected schemas and revise against rubric feedback.' ],
		[ 'name' => 'Memory Recovery', 'goal' => 'Use get-learning-memory to carry strengths, gaps, and next work into later sessions.' ],
		[ 'name' => 'Feedback and Improvement Signals', 'goal' => 'File submit-feedback for confusing/helpful material and inspect aggregate improvement signals.' ],
		[ 'name' => 'Capstone Plugin Judgment', 'goal' => 'Use the course to produce a safer WordPress plugin plan than an untrained model would.' ],
	];
	$exercise_slugs = array_keys( $inventory['exercises'] );
	$lesson_slugs = array_keys( $inventory['lessons'] );
	$plans = [];

	for ( $i = 0; $i < $passes; $i++ ) {
		$template = $templates[ $i % count( $templates ) ];
		$lesson_slug = $lesson_slugs[ $i % max( 1, count( $lesson_slugs ) ) ] ?? '';
		$exercise_slug = $exercise_slugs[ $i % max( 1, count( $exercise_slugs ) ) ] ?? '';
		$plans[] = [
			'pass' => $i + 1,
			'name' => $template['name'],
			'goal' => $template['goal'],
			'simulated_calls' => [
				'begin-course',
				'get-course-improvement-signals',
				'get-next-work',
				$lesson_slug !== '' ? 'get-lesson:' . $lesson_slug : 'get-lesson',
				$exercise_slug !== '' ? 'get-exercise:' . $exercise_slug : 'get-exercise',
				$exercise_slug !== '' ? 'attempt-exercise:' . $exercise_slug : 'attempt-exercise',
				'get-learning-memory',
				'submit-feedback',
			],
			'feedback_prompt' => 'What was confusing, helpful, missing, or too hard in this pass?',
		];
	}

	return $plans;
}

function score_course( array $findings, array $inventory ): array {
	$score = 100;
	foreach ( $findings as $finding ) {
		if ( $finding['severity'] === 'critical' ) {
			$score -= 20;
		} elseif ( $finding['severity'] === 'warning' ) {
			$score -= 5;
		} else {
			$score -= 1;
		}
	}

	$score = max( 0, min( 100, $score ) );

	return [
		'llm_friendliness' => $score,
		'critical' => count_by_severity( $findings, 'critical' ),
		'warning' => count_by_severity( $findings, 'warning' ),
		'notice' => count_by_severity( $findings, 'notice' ),
		'practice_density' => count( $inventory['exercises'] ) > 0 && count( $inventory['lessons'] ) > 0
			? round( count( $inventory['exercises'] ) / count( $inventory['lessons'] ), 3 )
			: 0,
	];
}

function finding( string $severity, string $target, string $code, string $message ): array {
	return [
		'severity' => $severity,
		'target' => $target,
		'code' => $code,
		'message' => $message,
	];
}

function count_by_severity( array $findings, string $severity ): int {
	return count(
		array_filter(
			$findings,
			static function ( array $finding ) use ( $severity ): bool {
				return $finding['severity'] === $severity;
			}
		)
	);
}

function print_human_report( array $lab ): void {
	echo 'Course Lab: ' . $lab['course']['name'] . ' (' . $lab['course']['slug'] . ')' . PHP_EOL;
	echo 'LLM friendliness: ' . $lab['score']['llm_friendliness'] . '/100' . PHP_EOL;
	echo sprintf(
		'Inventory: %d module(s), %d lesson(s), %d exercise(s), %d reference(s). Practice density: %.3f',
		$lab['inventory']['modules'],
		$lab['inventory']['lessons'],
		$lab['inventory']['exercises'],
		$lab['inventory']['references'],
		$lab['score']['practice_density']
	) . PHP_EOL;
	echo sprintf(
		'Findings: %d critical, %d warning, %d notice.',
		$lab['score']['critical'],
		$lab['score']['warning'],
		$lab['score']['notice']
	) . PHP_EOL . PHP_EOL;

	echo 'Six-pass student loop:' . PHP_EOL;
	foreach ( $lab['passes'] as $pass ) {
		echo '  ' . $pass['pass'] . '. ' . $pass['name'] . ' - ' . $pass['goal'] . PHP_EOL;
	}

	if ( $lab['findings'] ) {
		echo PHP_EOL . 'Findings:' . PHP_EOL;
		foreach ( $lab['findings'] as $finding ) {
			echo sprintf( '  [%s] %s %s - %s', $finding['severity'], $finding['target'], $finding['code'], $finding['message'] ) . PHP_EOL;
		}
	}
}

function agent_brief( array $lab ): string {
	return implode(
		"\n",
		[
			'You are a parallel LLM student-reviewer for Model Context Polytechnic.',
			'Course: ' . $lab['course']['name'] . ' (' . $lab['course']['slug'] . ').',
			'Take the course repeatedly using this loop: begin-course, get-course-improvement-signals, get-next-work, get-lesson, get-exercise, attempt-exercise, get-learning-memory, submit-feedback.',
			'Focus on whether the course makes you better at the target task, not whether it merely describes the topic.',
			'Report concrete findings with lesson_slug, exercise_slug, or tool slug. Do not edit files.',
			'Current lab score: ' . $lab['score']['llm_friendliness'] . '/100.',
			'Known findings to inspect: ' . json_encode( array_slice( $lab['findings'], 0, 12 ), JSON_UNESCAPED_SLASHES ),
		]
	);
}
