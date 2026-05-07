#!/usr/bin/env php
<?php
require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$options = parse_args( array_slice( $argv, 1 ) );
$passes = max( 1, min( 50, (int) ( $options['passes'] ?? 6 ) ) );
$students = max( 0, min( 50, (int) ( $options['students'] ?? 10 ) ) );
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

$lab = run_course_lab( $course, $passes, $students );

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

function run_course_lab( array $course, int $passes, int $students ): array {
	$inventory = course_inventory( $course );
	$findings = array_merge(
		check_public_contract( $course ),
		check_lesson_fitness( $course, $inventory ),
		check_exercise_fitness( $course, $inventory ),
		check_topic_coverage( $course, $inventory )
	);
	$pass_plan = build_pass_plan( $course, $inventory, $passes );
	$student_cohort = build_student_cohort( $course, $inventory, $students );
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
		'student_cohort' => $student_cohort,
		'findings' => $findings,
		'improvement_protocol' => [
			'Run this lab before and after course edits.',
			'Review the 10-student cohort before changing lessons, exercises, or tool contracts.',
			'Spawn a parallel student-reviewer with the agent brief for large course changes.',
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

function build_student_cohort( array $course, array $inventory, int $students ): array {
	$profiles = array_slice( student_profile_templates(), 0, $students );
	$reports  = [];

	foreach ( $profiles as $index => $profile ) {
		$reports[] = evaluate_student_profile( $course, $inventory, $profile, $index + 1 );
	}

	return [
		'student_count'   => count( $reports ),
		'method'          => 'Deterministic LLM-student simulation. Each profile stresses a different learner goal and returns submit-feedback-shaped observations.',
		'students'        => $reports,
		'themes'          => cohort_theme_summary( $reports ),
		'recommendations' => cohort_recommendations( $course, $reports ),
		'how_to_use'      => [
			'Treat the cohort as preflight friction testing, not a replacement for real learner feedback.',
			'Apply changes when multiple students point at the same target, a public workflow step is unclear, or a rubric cannot produce actionable feedback.',
			'After edits, rerun composer course-lab and compare the cohort themes.',
		],
	];
}

function student_profile_templates(): array {
	return [
		[
			'id' => 'first-day-model',
			'name' => 'Ada, First-Day Model',
			'lens' => 'orientation',
			'goal' => 'Connect with no prior context and find the first useful course move.',
			'keywords' => [ 'begin-course', 'enrollment_key', 'get-next-work', 'next_actions', 'tool_calls' ],
			'target_type' => 'course',
			'target_slug' => 'wordpress-plugin-craft',
		],
		[
			'id' => 'memory-constrained-model',
			'name' => 'Babbage, Memory-Constrained Model',
			'lens' => 'memory retrieval',
			'goal' => 'Return later with only a handle and recover what changed.',
			'keywords' => [ 'enrollment_key', 'get-learning-memory', 'progress', 'preserve', 'memory' ],
			'target_type' => 'memory',
			'target_slug' => 'get-learning-memory',
		],
		[
			'id' => 'security-reviewer',
			'name' => 'Grace, Security Reviewer',
			'lens' => 'permissions and trust boundaries',
			'goal' => 'Use the course to stop unsafe plugin write paths before code ships.',
			'keywords' => [ 'capability', 'nonce', 'sanitize', 'escape', 'permission_callback', 'WP_Error' ],
			'target_type' => 'lesson',
			'target_slug' => 'capabilities-nonces-rest-permissions',
		],
		[
			'id' => 'data-migration-student',
			'name' => 'Linus, Data Migration Student',
			'lens' => 'storage and lifecycle',
			'goal' => 'Choose storage and design custom table migrations without breaking sites.',
			'keywords' => [ 'custom table', 'dbDelta', 'schema version', 'uninstall', 'retention' ],
			'target_type' => 'lesson',
			'target_slug' => 'custom-tables-dbdelta',
		],
		[
			'id' => 'block-builder',
			'name' => 'Katherine, Block Builder',
			'lens' => 'modern editor and JavaScript',
			'goal' => 'Build block-facing plugins with current WordPress JavaScript and accessibility habits.',
			'keywords' => [ 'block.json', 'Interactivity API', '@wordpress/scripts', 'accessibility', 'progressive' ],
			'target_type' => 'lesson',
			'target_slug' => 'interactivity-and-build-tools',
		],
		[
			'id' => 'performance-reliability-reviewer',
			'name' => 'Donald, Performance Reviewer',
			'lens' => 'speed and reliability',
			'goal' => 'Catch expensive plugin behavior, brittle remote calls, and cron mistakes.',
			'keywords' => [ 'transient', 'cache', 'cron', 'unschedule', 'query', 'remote' ],
			'target_type' => 'lesson',
			'target_slug' => 'performance-discipline',
		],
		[
			'id' => 'release-reviewer',
			'name' => 'Radia, Release Reviewer',
			'lens' => 'distribution readiness',
			'goal' => 'Prepare the plugin for review, packaging, compatibility, and support.',
			'keywords' => [ 'Plugin Check', 'readme', 'stable tag', 'license', 'compatibility', 'wordpress.org' ],
			'target_type' => 'lesson',
			'target_slug' => 'wordpress-org-readiness',
		],
		[
			'id' => 'course-author',
			'name' => 'Seymour, Course Author',
			'lens' => 'course-pack maintainability',
			'goal' => 'Expand the course without turning it into a hidden PHP blob or a vague document dump.',
			'keywords' => [ 'course pack', 'sources.json', 'rubric', 'expected output schema', 'stable slug' ],
			'target_type' => 'resource',
			'target_slug' => 'course-pack-authoring',
		],
		[
			'id' => 'agent-interface-designer',
			'name' => 'Margaret, Agent Interface Designer',
			'lens' => 'LLM-native tool ergonomics',
			'goal' => 'Confirm the model can infer less, retrieve more, and preserve stable handles.',
			'keywords' => [ 'orientation', 'initialize instructions', 'stable handles', 'next_actions', 'tool_calls', 'search-course' ],
			'target_type' => 'lesson',
			'target_slug' => 'llm-native-plugin-interfaces',
		],
		[
			'id' => 'capstone-maintainer',
			'name' => 'Frances, Capstone Maintainer',
			'lens' => 'end-to-end plugin judgment',
			'goal' => 'Use the course to produce a safer plugin plan than an untrained model would.',
			'keywords' => [ 'planning canvas', 'review cadence', 'security', 'storage', 'testing', 'release' ],
			'target_type' => 'exercise',
			'target_slug' => 'capstone-plugin-plan',
		],
	];
}

function evaluate_student_profile( array $course, array $inventory, array $profile, int $number ): array {
	$topic = topic_match_summary( $inventory['corpus'], $profile['keywords'] );
	$target_lesson = first_matching_lesson( $inventory, $profile );
	$target_exercise = first_matching_exercise( $inventory, $profile );
	$missing = $topic['missing'];
	$coverage = $topic['coverage'];
	$rating = $coverage >= 0.8 ? 5 : ( $coverage >= 0.6 ? 4 : ( $coverage >= 0.4 ? 3 : 2 ) );
	$feedback_type = $rating >= 4 ? 'helpful' : ( $rating === 3 ? 'suggestion' : 'missing_example' );
	$comment = $rating >= 4
		? sprintf( '%s found a usable path for %s and could identify relevant stable handles or lesson material.', $profile['name'], $profile['lens'] )
		: sprintf( '%s found %s underexplained for the %s lens.', $profile['name'], implode( ', ', $missing ), $profile['lens'] );
	$suggested_fix = $missing
		? 'Add a short example or checklist covering: ' . implode( ', ', $missing ) . '.'
		: 'Preserve this path and keep returning exact next tool calls.';

	if ( $profile['id'] === 'first-day-model' && strpos( $course['instructions'], 'get-study-plan' ) === false ) {
		$rating = min( $rating, 3 );
		$feedback_type = 'suggestion';
		$comment = 'First-day orientation works, but top-level course instructions should name get-study-plan so goal-driven learners know it exists before reading a full response.';
		$suggested_fix = 'Mention get-study-plan and search-course in course.json instructions, not only in later tool responses.';
	}

	if ( $profile['id'] === 'course-author' && strpos( $inventory['corpus'], 'cohort' ) === false ) {
		$rating = min( $rating, 3 );
		$feedback_type = 'suggestion';
		$comment = 'Course authoring is strong, but the maintainer loop does not yet name cohort review as a first-class practice.';
		$suggested_fix = 'Add a cohort-feedback reference and mention composer course-lab as the 10-student preflight loop.';
	}

	return [
		'student' => $number,
		'id' => $profile['id'],
		'name' => $profile['name'],
		'lens' => $profile['lens'],
		'goal' => $profile['goal'],
		'route' => array_values( array_filter( [
			'begin-course',
			'get-study-plan',
			'get-syllabus',
			$target_lesson ? 'get-lesson:' . $target_lesson['slug'] : null,
			$target_exercise ? 'get-exercise:' . $target_exercise['slug'] : null,
			$target_exercise ? 'attempt-exercise:' . $target_exercise['slug'] : null,
			'get-learning-memory',
			'submit-feedback',
			'get-course-improvement-signals',
		] ) ),
		'targets' => [
			'lesson_slug' => $target_lesson['slug'] ?? null,
			'exercise_slug' => $target_exercise['slug'] ?? null,
		],
		'coverage' => $topic,
		'feedback' => [
			'feedback_type' => $feedback_type,
			'target_type' => $profile['target_type'],
			'target_slug' => $profile['target_slug'],
			'rating' => $rating,
			'comment' => $comment,
			'suggested_fix' => $suggested_fix,
		],
	];
}

function topic_match_summary( string $corpus, array $keywords ): array {
	$matched = [];
	$missing = [];

	foreach ( $keywords as $keyword ) {
		if ( strpos( $corpus, strtolower( $keyword ) ) !== false ) {
			$matched[] = $keyword;
		} else {
			$missing[] = $keyword;
		}
	}

	$total = max( 1, count( $keywords ) );

	return [
		'matched' => $matched,
		'missing' => $missing,
		'coverage' => round( count( $matched ) / $total, 3 ),
	];
}

function first_matching_lesson( array $inventory, array $profile ): ?array {
	return first_matching_item( $inventory['lessons'], $profile );
}

function first_matching_exercise( array $inventory, array $profile ): ?array {
	return first_matching_item( $inventory['exercises'], $profile );
}

function first_matching_item( array $items, array $profile ): ?array {
	$target_slug = (string) ( $profile['target_slug'] ?? '' );
	if ( isset( $items[ $target_slug ] ) ) {
		return $items[ $target_slug ];
	}

	$best = null;
	$best_score = 0;
	foreach ( $items as $item ) {
		$text = strtolower(
			implode(
				' ',
				array_filter(
					[
						$item['slug'] ?? '',
						$item['title'] ?? '',
						$item['summary'] ?? '',
						$item['body'] ?? '',
						$item['prompt'] ?? '',
						json_encode( $item['rubric'] ?? [], JSON_UNESCAPED_SLASHES ),
					]
				)
			)
		);
		$score = 0;
		foreach ( $profile['keywords'] as $keyword ) {
			if ( strpos( $text, strtolower( $keyword ) ) !== false ) {
				$score++;
			}
		}

		if ( $score > $best_score ) {
			$best = $item;
			$best_score = $score;
		}
	}

	return $best;
}

function cohort_theme_summary( array $reports ): array {
	$themes = [];
	foreach ( $reports as $report ) {
		$type = $report['feedback']['feedback_type'];
		$themes[ $type ] = ( $themes[ $type ] ?? 0 ) + 1;
		foreach ( $report['coverage']['missing'] as $missing ) {
			$key = 'missing:' . $missing;
			$themes[ $key ] = ( $themes[ $key ] ?? 0 ) + 1;
		}
	}

	arsort( $themes );
	return $themes;
}

function cohort_recommendations( array $course, array $reports ): array {
	$recommendations = [];
	$low_rated = array_filter(
		$reports,
		static function ( array $report ): bool {
			return (int) $report['feedback']['rating'] <= 3;
		}
	);

	foreach ( $low_rated as $report ) {
		$recommendations[] = [
			'target_type' => $report['feedback']['target_type'],
			'target_slug' => $report['feedback']['target_slug'],
			'action' => $report['feedback']['suggested_fix'],
			'source_student' => $report['id'],
		];
	}

	if ( strpos( $course['instructions'], 'get-study-plan' ) === false ) {
		array_unshift(
			$recommendations,
			[
				'target_type' => 'course',
				'target_slug' => $course['slug'],
				'action' => 'Name get-study-plan and search-course directly in course instructions so first-day learners do not have to discover them later.',
				'source_student' => 'first-day-model',
			]
		);
	}

	if ( ! $recommendations ) {
		$recommendations[] = [
			'target_type' => 'course',
			'target_slug' => $course['slug'],
			'action' => 'No blocking cohort friction found. Preserve current public enrollment, memory, and feedback contracts.',
			'source_student' => 'cohort',
		];
	}

	return array_values( $recommendations );
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

	if ( ! empty( $lab['student_cohort']['students'] ) ) {
		echo PHP_EOL . 'Ten-student cohort:' . PHP_EOL;
		foreach ( $lab['student_cohort']['students'] as $student ) {
			echo sprintf(
				'  %d. %s (%s) rating %d/5 - %s',
				$student['student'],
				$student['name'],
				$student['lens'],
				$student['feedback']['rating'],
				$student['feedback']['comment']
			) . PHP_EOL;
		}

		echo PHP_EOL . 'Cohort recommendations:' . PHP_EOL;
		foreach ( $lab['student_cohort']['recommendations'] as $recommendation ) {
			echo sprintf(
				'  - %s:%s - %s',
				$recommendation['target_type'],
				$recommendation['target_slug'],
				$recommendation['action']
			) . PHP_EOL;
		}
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
			'Student cohort themes: ' . json_encode( $lab['student_cohort']['themes'] ?? [], JSON_UNESCAPED_SLASHES ),
			'Student cohort recommendations: ' . json_encode( $lab['student_cohort']['recommendations'] ?? [], JSON_UNESCAPED_SLASHES ),
			'Known findings to inspect: ' . json_encode( array_slice( $lab['findings'], 0, 12 ), JSON_UNESCAPED_SLASHES ),
		]
	);
}
