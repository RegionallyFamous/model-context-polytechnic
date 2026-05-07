#!/usr/bin/env php
<?php
require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$definitions = CoursePack::definitions();
$course = $definitions[0] ?? null;

if ( ! $course ) {
	fwrite( STDERR, "No valid course packs found.\n" );
	exit( 1 );
}

$first_module = $course['modules'][0] ?? null;
$first_lesson = $first_module['lessons'][0] ?? null;
$first_exercise = $first_module['exercises'][0] ?? null;

if ( ! $first_module || ! $first_lesson || ! $first_exercise ) {
	fwrite( STDERR, "Course pack is missing first module, lesson, or exercise.\n" );
	exit( 1 );
}

$required_exercise_fields = [ 'slug', 'title', 'prompt', 'rubric', 'passing_score' ];
foreach ( $required_exercise_fields as $field ) {
	if ( ! array_key_exists( $field, $first_exercise ) ) {
		fwrite( STDERR, "First exercise is missing {$field}.\n" );
		exit( 1 );
	}
}

$rubric = $first_exercise['rubric'];
if ( empty( $rubric['criteria'] ) || ! is_array( $rubric['criteria'] ) ) {
	fwrite( STDERR, "First exercise has no rubric criteria.\n" );
	exit( 1 );
}

$summary = [
	'course' => [
		'slug'        => $course['slug'],
		'name'        => $course['name'],
		'module_count'=> count( $course['modules'] ),
	],
	'begin_course_shape' => [
		'stable_handles' => [ 'enrollment_key', 'lesson_slug', 'exercise_slug' ],
		'improvement_tools' => [ 'submit-feedback', 'get-course-improvement-signals' ],
		'first_lesson'   => [
			'slug'  => $first_lesson['slug'],
			'title' => $first_lesson['title'],
		],
		'first_exercise' => [
			'slug'  => $first_exercise['slug'],
			'title' => $first_exercise['title'],
		],
	],
	'next_actions_shape' => [
		[
			'tool'      => 'model-context-polytechnic/' . $course['slug'] . '/get-lesson',
			'arguments' => [ 'lesson_slug' => $first_lesson['slug'] ],
		],
		[
			'tool'      => 'model-context-polytechnic/' . $course['slug'] . '/get-exercise',
			'arguments' => [ 'exercise_slug' => $first_exercise['slug'] ],
		],
	],
	'exercise_contract' => [
		'has_prompt'       => trim( (string) $first_exercise['prompt'] ) !== '',
		'criteria_count'   => count( $rubric['criteria'] ),
		'passing_score'    => $first_exercise['passing_score'],
		'has_output_schema'=> isset( $first_exercise['expected_output_schema'] ),
		'has_model_answer' => ! empty( $first_exercise['model_answer'] ),
		'has_feedback_loop'=> strpos( $course['instructions'], 'submit-feedback' ) !== false,
	],
];

if ( empty( $summary['exercise_contract']['has_feedback_loop'] ) ) {
	fwrite( STDERR, "Course instructions do not mention submit-feedback.\n" );
	exit( 1 );
}

if ( empty( $summary['exercise_contract']['has_model_answer'] ) ) {
	fwrite( STDERR, "First exercise has no model_answer exemplar.\n" );
	exit( 1 );
}

echo wp_json_encode_compat( $summary ) . PHP_EOL;
echo "Course flow simulation passed.\n";

function wp_json_encode_compat( array $value ): string {
	$json = json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $json ) ? $json : '{}';
}
