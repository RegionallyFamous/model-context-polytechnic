<?php
/**
 * Smoke test a public course endpoint over the MCP HTTP transport.
 */

declare( strict_types=1 );

$options = [
	'url'      => 'http://localhost:8888/mcp/wordpress-plugin-craft',
	'protocol' => '2025-11-25',
	'json'     => false,
	'headers'  => [],
];

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( $arg === '--json' ) {
		$options['json'] = true;
		continue;
	}

	if ( str_starts_with( $arg, '--url=' ) ) {
		$options['url'] = substr( $arg, 6 );
		continue;
	}

	if ( str_starts_with( $arg, '--protocol=' ) ) {
		$options['protocol'] = substr( $arg, 11 );
		continue;
	}

	if ( str_starts_with( $arg, '--header=' ) ) {
		$header = substr( $arg, 9 );
		if ( strpos( $header, ':' ) === false ) {
			fail( 'Headers must use --header="Name: value".' );
		}
		$options['headers'][] = $header;
		continue;
	}

	if ( $arg === '--help' || $arg === '-h' ) {
		echo "Usage: php bin/http-course-smoke.php --url=https://example.com/mcp/wordpress-plugin-craft [--header=\"Name: value\"] [--protocol=2025-11-25] [--json]\n";
		exit( 0 );
	}

	fail( "Unknown argument: {$arg}" );
}

$tool_prefix = 'model-context-polytechnic-wordpress-plugin-craft-';
$required_tools = [
	$tool_prefix . 'begin-course',
	$tool_prefix . 'take-course',
	$tool_prefix . 'get-exercise',
	$tool_prefix . 'attempt-exercise',
	$tool_prefix . 'get-learning-memory',
	$tool_prefix . 'get-certificate',
	$tool_prefix . 'get-next-work',
	$tool_prefix . 'get-campus-scene',
	$tool_prefix . 'get-campus-scene-image',
	$tool_prefix . 'submit-feedback',
	$tool_prefix . 'get-course-improvement-signals',
];

$summary = [
	'endpoint' => $options['url'],
	'checks'   => [],
];

$initialize = mcp_request(
	$options['url'],
	'initialize',
	[
		'protocolVersion' => $options['protocol'],
		'capabilities'    => new stdClass(),
		'clientInfo'      => [
			'name'    => 'model-context-polytechnic-http-smoke',
			'version' => '1.0.0',
		],
	],
	null,
	$options['protocol'],
	$options['headers'],
	1
);

$session_id = $initialize['headers']['mcp-session-id'][0] ?? '';
if ( $session_id === '' ) {
	fail( 'initialize did not return Mcp-Session-Id. Public anonymous session support may be broken.' );
}

$summary['protocol_version'] = $initialize['result']['protocolVersion'] ?? null;
$summary['checks'][] = 'initialize returned server info and Mcp-Session-Id';

mcp_notification( $options['url'], 'notifications/initialized', [], $session_id, $options['protocol'], $options['headers'] );

$tools_result = mcp_request( $options['url'], 'tools/list', [], $session_id, $options['protocol'], $options['headers'], 2 );
$tool_names = array_map(
	static function ( array $tool ): string {
		return (string) ( $tool['name'] ?? '' );
	},
	$tools_result['result']['tools'] ?? []
);

$missing_tools = array_values( array_diff( $required_tools, $tool_names ) );
if ( $missing_tools ) {
	fail( 'Missing expected course tools: ' . implode( ', ', $missing_tools ) );
}

$summary['tool_count'] = count( $tool_names );
$summary['checks'][] = 'tools/list exposed the public learning tools';

$resources_result = mcp_request( $options['url'], 'resources/list', [], $session_id, $options['protocol'], $options['headers'], 3 );
$resource_uris = array_map(
	static function ( array $resource ): string {
		return (string) ( $resource['uri'] ?? '' );
	},
	$resources_result['result']['resources'] ?? []
);

$required_resources = [
	'mcp://wordpress-plugin-craft/syllabus',
	'mcp://wordpress-plugin-craft/content/plugin-review-checklist',
];
$missing_resources = array_values( array_diff( $required_resources, $resource_uris ) );
if ( $missing_resources ) {
	fail( 'Missing expected course resources: ' . implode( ', ', $missing_resources ) );
}

$summary['resource_count'] = count( $resource_uris );
$summary['checks'][] = 'resources/list exposed the syllabus and public references';

$begin = call_tool( $options, $session_id, $tool_prefix . 'begin-course', [], 4 );
$enrollment_key = (string) ( $begin['enrollment_key'] ?? '' );
if ( $enrollment_key === '' ) {
	fail( 'begin-course did not return an enrollment_key.' );
}

assert_learning_status_shape( $begin, 'begin-course' );

$summary['enrollment_key_prefix'] = substr( $enrollment_key, 0, 8 );
$summary['checks'][] = 'begin-course issued anonymous enrollment key';
assert_suggested_tools_exist( $begin, $tool_names, 'begin-course' );

$take_course = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'take-course',
	[
		'enrollment_key' => $enrollment_key,
		'mode'           => 'module_batch',
		'batch_size'     => 1,
	],
	5
);

if ( empty( $take_course['materials'] ) || empty( $take_course['autopilot'] ) ) {
	fail( 'take-course did not return an autopilot course packet.' );
}

assert_learning_status_shape( $take_course, 'take-course' );

$summary['checks'][] = 'take-course returned autopilot materials';
assert_suggested_tools_exist( $take_course, $tool_names, 'take-course' );

$campus_scene = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'get-campus-scene',
	[
		'scene'          => 'matriculation',
		'enrollment_key' => $enrollment_key,
	],
	6
);

if ( empty( $campus_scene['display_markdown'] ) || empty( $campus_scene['image_url'] ) ) {
	fail( 'get-campus-scene did not return display_markdown and image_url.' );
}

$summary['checks'][] = 'get-campus-scene returned a visible markdown image packet';

$exercise = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'get-exercise',
	[
		'exercise_slug'        => 'design-plugin-bootstrap',
		'include_hints'        => true,
		'enrollment_key'       => $enrollment_key,
		'include_model_answer' => false,
	],
	7
);

if ( ( $exercise['exercise']['slug'] ?? '' ) !== 'design-plugin-bootstrap' ) {
	fail( 'get-exercise did not return design-plugin-bootstrap.' );
}

if ( empty( $exercise['exercise']['rubric_vocabulary']['required_terms'] ) ) {
	fail( 'get-exercise did not expose rubric_vocabulary.required_terms.' );
}

$summary['checks'][] = 'get-exercise returned the requested exercise';
$summary['checks'][] = 'get-exercise exposed rubric vocabulary before grading';
assert_suggested_tools_exist( $exercise, $tool_names, 'get-exercise' );

$attempt = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'attempt-exercise',
	[
		'exercise_slug'  => 'design-plugin-bootstrap',
		'enrollment_key' => $enrollment_key,
		'answer'         => wp_plugin_craft_smoke_answer(),
		'response_mode'  => 'gradebook',
	],
	8
);

if ( empty( $attempt['evaluation'] ) || ! array_key_exists( 'passed', $attempt['evaluation'] ) ) {
	fail( 'attempt-exercise did not return an evaluation.' );
}

if ( ( $attempt['response_mode'] ?? '' ) !== 'gradebook' || empty( $attempt['gradebook'] ) || isset( $attempt['campus_story'] ) ) {
	fail( 'attempt-exercise response_mode=gradebook did not return compact gradebook output.' );
}

$summary['attempt_score'] = $attempt['evaluation']['score'] ?? null;
$summary['attempt_passed'] = $attempt['evaluation']['passed'] ?? null;
$summary['checks'][] = 'attempt-exercise evaluated and stored the answer';
$summary['checks'][] = 'attempt-exercise gradebook mode stayed compact';
assert_suggested_tools_exist( $attempt, $tool_names, 'attempt-exercise' );

$memory = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'get-learning-memory',
	[
		'enrollment_key' => $enrollment_key,
	],
	9
);

if ( empty( $memory['recent_attempts'] ) ) {
	fail( 'get-learning-memory did not include the prior attempt.' );
}

$summary['memory_attempts'] = count( $memory['recent_attempts'] );
$summary['checks'][] = 'get-learning-memory recovered the prior attempt';
assert_suggested_tools_exist( $memory, $tool_names, 'get-learning-memory' );

$certificate = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'get-certificate',
	[
		'enrollment_key' => $enrollment_key,
	],
	10
);

if ( ! array_key_exists( 'eligible', $certificate ) || ! empty( $certificate['eligible'] ) ) {
	fail( 'get-certificate should report not-yet-eligible after a single smoke-test attempt.' );
}

if ( empty( $certificate['remaining_exercises'] ) || empty( $certificate['next_work'] ) ) {
	fail( 'get-certificate did not return remaining exercises and next work for an unfinished enrollment.' );
}

$summary['checks'][] = 'get-certificate reported not-yet-complete status with next work';
assert_suggested_tools_exist( $certificate, $tool_names, 'get-certificate' );

$model_answer = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'get-exercise',
	[
		'exercise_slug'        => 'design-plugin-bootstrap',
		'include_model_answer' => true,
		'enrollment_key'       => $enrollment_key,
	],
	11
);

if ( empty( $model_answer['exercise']['model_answer'] ) ) {
	fail( 'get-exercise include_model_answer=true did not return the exemplar.' );
}

$summary['checks'][] = 'model answer is available only when requested';
$summary['status'] = 'ok';

if ( $options['json'] ) {
	echo json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( 0 );
}

echo "HTTP course smoke passed for {$options['url']}\n";
foreach ( $summary['checks'] as $check ) {
	echo "- {$check}\n";
}
echo 'Attempt score: ' . json_encode( $summary['attempt_score'] ) . "\n";
echo 'Enrollment key prefix: ' . $summary['enrollment_key_prefix'] . "...\n";

function call_tool( array $options, string $session_id, string $tool_name, array $arguments, int $id ): array {
	$response = mcp_request(
		$options['url'],
		'tools/call',
		[
			'name'      => $tool_name,
			'arguments' => $arguments,
		],
		$session_id,
		$options['protocol'],
		$options['headers'],
		$id
	);

	$result = $response['result'];
	if ( ! empty( $result['isError'] ) ) {
		$message = $result['content'][0]['text'] ?? 'unknown tool error';
		fail( "{$tool_name} returned tool error: {$message}" );
	}

	return structured_content( $result );
}

function mcp_request( string $url, string $method, array $params, ?string $session_id, string $protocol, array $extra_headers, int $id ): array {
	$payload = [
		'jsonrpc' => '2.0',
		'id'      => $id,
		'method'  => $method,
		'params'  => $params,
	];

	$headers = default_headers( $session_id, $protocol, $extra_headers );
	$response = post_json( $url, $payload, $headers );
	if ( $response['status'] < 200 || $response['status'] >= 300 ) {
		fail( http_error_message( $method, $response ) );
	}

	$decoded = json_decode( $response['body'], true );
	if ( ! is_array( $decoded ) ) {
		fail( "{$method} returned invalid JSON: {$response['body']}" );
	}

	if ( isset( $decoded['error'] ) ) {
		fail( "{$method} returned JSON-RPC error: " . json_encode( $decoded['error'], JSON_UNESCAPED_SLASHES ) );
	}

	if ( ! isset( $decoded['result'] ) || ! is_array( $decoded['result'] ) ) {
		fail( "{$method} returned no result object." );
	}

	return [
		'headers' => $response['headers'],
		'result'  => $decoded['result'],
	];
}

function mcp_notification( string $url, string $method, array $params, string $session_id, string $protocol, array $extra_headers ): void {
	$payload = [
		'jsonrpc' => '2.0',
		'method'  => $method,
		'params'  => $params,
	];

	$response = post_json( $url, $payload, default_headers( $session_id, $protocol, $extra_headers ) );
	if ( ! in_array( $response['status'], [ 200, 202, 204 ], true ) ) {
		fail( http_error_message( $method . ' notification', $response ) );
	}
}

function default_headers( ?string $session_id, string $protocol, array $extra_headers ): array {
	$headers = array_merge(
		[
			'Content-Type: application/json',
			'Accept: application/json, text/event-stream',
			'Mcp-Protocol-Version: ' . $protocol,
		],
		$extra_headers
	);

	if ( $session_id ) {
		$headers[] = 'Mcp-Session-Id: ' . $session_id;
	}

	return $headers;
}

function post_json( string $url, array $payload, array $headers ): array {
	$body = json_encode( $payload, JSON_UNESCAPED_SLASHES );
	if ( $body === false ) {
		fail( 'Failed to encode JSON request.' );
	}

	if ( function_exists( 'curl_init' ) ) {
		return post_json_with_curl( $url, $body, $headers );
	}

	return post_json_with_streams( $url, $body, $headers );
}

function post_json_with_curl( string $url, string $body, array $headers ): array {
	$response_headers = [];
	$handle = curl_init( $url );
	if ( ! $handle ) {
		fail( 'Failed to initialize cURL.' );
	}

	curl_setopt_array(
		$handle,
		[
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADERFUNCTION => static function ( $curl, string $line ) use ( &$response_headers ): int {
				$length = strlen( $line );
				$line = trim( $line );
				if ( $line !== '' && strpos( $line, ':' ) !== false ) {
					[ $name, $value ] = explode( ':', $line, 2 );
					$response_headers[ strtolower( trim( $name ) ) ][] = trim( $value );
				}
				return $length;
			},
			CURLOPT_TIMEOUT        => 20,
		]
	);

	$response_body = curl_exec( $handle );
	$status = (int) curl_getinfo( $handle, CURLINFO_HTTP_CODE );
	$error = curl_error( $handle );

	if ( $response_body === false ) {
		fail( 'HTTP request failed: ' . $error );
	}

	return [
		'status'  => $status,
		'headers' => $response_headers,
		'body'    => (string) $response_body,
	];
}

function post_json_with_streams( string $url, string $body, array $headers ): array {
	$context = stream_context_create(
		[
			'http' => [
				'method'        => 'POST',
				'header'        => implode( "\r\n", $headers ),
				'content'       => $body,
				'ignore_errors' => true,
				'timeout'       => 20,
			],
		]
	);

	$response_body = file_get_contents( $url, false, $context );
	if ( $response_body === false ) {
		fail( 'HTTP request failed with stream transport.' );
	}

	$status = 0;
	$response_headers = [];
	foreach ( $http_response_header ?? [] as $line ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $line, $matches ) ) {
			$status = (int) $matches[1];
			continue;
		}

		if ( strpos( $line, ':' ) !== false ) {
			[ $name, $value ] = explode( ':', $line, 2 );
			$response_headers[ strtolower( trim( $name ) ) ][] = trim( $value );
		}
	}

	return [
		'status'  => $status,
		'headers' => $response_headers,
		'body'    => (string) $response_body,
	];
}

function structured_content( array $result ): array {
	if ( isset( $result['structuredContent'] ) && is_array( $result['structuredContent'] ) ) {
		return $result['structuredContent'];
	}

	$text = $result['content'][0]['text'] ?? '';
	if ( is_string( $text ) && $text !== '' ) {
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
	}

	fail( 'Tool response did not include structured content.' );
}

function assert_suggested_tools_exist( array $payload, array $tool_names, string $context ): void {
	$suggested = collect_suggested_tools( $payload );
	$missing = array_values( array_diff( $suggested, $tool_names ) );
	if ( $missing ) {
		fail( "{$context} suggested tool names not present in tools/list: " . implode( ', ', $missing ) );
	}
}

function collect_suggested_tools( array $payload ): array {
	$keys = [
		'tool',
		'next_tool',
		'first_call',
		'next_work_tool',
		'memory_tool',
		'certificate_tool',
		'feedback_tool',
		'signals_tool',
		'search_tool',
		'public_feedback_tool',
		'public_signals_tool',
		'campus_scene_tool',
	];
	$tools = [];
	foreach ( $payload as $key => $value ) {
		if ( in_array( $key, $keys, true ) && is_string( $value ) && str_starts_with( $value, 'model-context-polytechnic-' ) ) {
			$tools[] = $value;
		}

		if ( is_array( $value ) ) {
			$tools = array_merge( $tools, collect_suggested_tools( $value ) );
		}
	}

	return array_values( array_unique( $tools ) );
}

function assert_learning_status_shape( array $payload, string $context ): void {
	$status = $payload['learning_status'] ?? null;
	if ( ! is_array( $status ) ) {
		fail( "{$context} did not return a learning_status object." );
	}

	$status_fields = [ 'status', 'accessibility', 'headline', 'narration' ];
	$has_status = false;
	foreach ( $status_fields as $field ) {
		if ( ! empty( $status[ $field ] ) ) {
			$has_status = true;
			break;
		}
	}

	if ( ! $has_status ) {
		fail( "{$context} learning_status did not include semantic status text." );
	}

	foreach ( [ 'ascii', 'markdown', 'frames', 'frames_markdown' ] as $removed_key ) {
		if ( array_key_exists( $removed_key, $status ) ) {
			fail( "{$context} learning_status should not include {$removed_key}." );
		}
	}

	if ( isset( $status['progress'] ) && ! is_array( $status['progress'] ) ) {
		fail( "{$context} learning_status progress should be structured when present." );
	}

	if ( empty( $status['story_script'] ) || ! is_array( $status['story_script'] ) ) {
		fail( "{$context} learning_status did not include a story_script object." );
	}

	$read_aloud = (string) ( $status['story_script']['read_aloud'] ?? '' );
	if ( strlen( $read_aloud ) < 240 ) {
		fail( "{$context} learning_status story_script.read_aloud was not descriptive enough." );
	}
}

function wp_plugin_craft_smoke_answer(): string {
	return 'Use folder archive-faculty-notes and main file archive-faculty-notes.php with a Plugin Name header, version, Requires PHP, Requires at least, License, and text domain. Start with an ABSPATH guard, define path/url/version constants, load autoload dependencies, and register activation and deactivation hooks. Activation can create tables, schedule events, or flush rewrites through lifecycle classes; deactivation unschedules work and flushes only temporary rewrite state. The bootstrap should wire WordPress hooks and dependencies, not business logic, remote API calls, rendering loops, or migrations inline. Verify with php -l, Plugin Check, activation/deactivation smoke tests, and a review that feature code lives in classes.';
}

function http_error_message( string $method, array $response ): string {
	$location = (string) ( $response['headers']['location'][0] ?? '' );
	if ( $response['status'] >= 300 && $response['status'] < 400 && strpos( $location, 'wp-admin/install.php' ) !== false ) {
		return "{$method} returned HTTP {$response['status']} redirecting to {$location}. WordPress is not installed at this URL; install WordPress, activate the plugin, and flush permalinks before running the smoke test.";
	}

	if ( $response['status'] >= 300 && $response['status'] < 400 && $location !== '' ) {
		return "{$method} returned HTTP {$response['status']} redirecting to {$location}. Confirm the URL points at the activated MCP endpoint.";
	}

	return "{$method} returned HTTP {$response['status']}: {$response['body']}";
}

function fail( string $message ): void {
	fwrite( STDERR, "HTTP course smoke failed: {$message}\n" );
	exit( 1 );
}
