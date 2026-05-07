<?php
/**
 * Complete a public course endpoint over MCP HTTP using bundled model answers.
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$options = [
	'url'      => 'http://localhost:8888/mcp/wordpress-plugin-craft',
	'protocol' => '2025-11-25',
	'json'     => false,
	'headers'  => [],
	'course'   => 'wordpress-plugin-craft',
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

	if ( str_starts_with( $arg, '--course=' ) ) {
		$options['course'] = substr( $arg, 9 );
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
		echo "Usage: php bin/http-course-completion-smoke.php --url=https://example.com/mcp/wordpress-plugin-craft [--course=wordpress-plugin-craft] [--header=\"Name: value\"] [--protocol=2025-11-25] [--json]\n";
		exit( 0 );
	}

	fail( "Unknown argument: {$arg}" );
}

$course = course_definition( $options['course'] );
$exercises = public_exercises( $course );
if ( ! $exercises ) {
	fail( 'No exercises found in the course pack.' );
}

$tool_prefix = 'model-context-polytechnic-' . $course['slug'] . '-';
$summary = [
	'endpoint'       => $options['url'],
	'course'         => $course['slug'],
	'exercise_count' => count( $exercises ),
	'checks'         => [],
];

$initialize = mcp_request(
	$options['url'],
	'initialize',
	[
		'protocolVersion' => $options['protocol'],
		'capabilities'    => new stdClass(),
		'clientInfo'      => [
			'name'    => 'model-context-polytechnic-completion-smoke',
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
	fail( 'initialize did not return Mcp-Session-Id.' );
}

mcp_notification( $options['url'], 'notifications/initialized', [], $session_id, $options['protocol'], $options['headers'] );

$tools_result = mcp_request( $options['url'], 'tools/list', [], $session_id, $options['protocol'], $options['headers'], 2 );
$tool_names = array_map(
	static function ( array $tool ): string {
		return (string) ( $tool['name'] ?? '' );
	},
	$tools_result['result']['tools'] ?? []
);

$begin = call_tool( $options, $session_id, $tool_prefix . 'begin-course', [], 3 );
$enrollment_key = (string) ( $begin['enrollment_key'] ?? '' );
if ( $enrollment_key === '' ) {
	fail( 'begin-course did not return an enrollment_key.' );
}

$summary['checks'][] = 'begin-course issued anonymous enrollment key';
assert_suggested_tools_exist( $begin, $tool_names, 'begin-course' );

$passed = 0;
foreach ( $exercises as $index => $exercise ) {
	$answer = answer_for_exercise( $exercise );
	$attempt = call_tool(
		$options,
		$session_id,
		$tool_prefix . 'attempt-exercise',
		[
			'exercise_slug'  => $exercise['slug'],
			'enrollment_key' => $enrollment_key,
			'answer'         => $answer,
		],
		4 + $index
	);

	if ( empty( $attempt['evaluation']['passed'] ) ) {
		fail( sprintf( 'Exercise %s did not pass. Score: %s', $exercise['slug'], json_encode( $attempt['evaluation']['score'] ?? null ) ) );
	}

	$passed++;
}

$summary['passed_count'] = $passed;
$summary['checks'][] = 'all bundled model answers passed over MCP HTTP';

$next_work = call_tool( $options, $session_id, $tool_prefix . 'get-next-work', [ 'enrollment_key' => $enrollment_key ], 1001 );
if ( empty( $next_work['complete'] ) || empty( $next_work['certificate_available'] ) ) {
	fail( 'get-next-work did not report complete=true and certificate_available=true after all exercises passed.' );
}

$summary['checks'][] = 'get-next-work reported completion';
assert_suggested_tools_exist( $next_work, $tool_names, 'get-next-work' );

$certificate = call_tool(
	$options,
	$session_id,
	$tool_prefix . 'get-certificate',
	[
		'enrollment_key' => $enrollment_key,
		'recipient_name' => 'Completion Smoke Student',
	],
	1002
);

if ( empty( $certificate['eligible'] ) || empty( $certificate['certificate']['certificate_id'] ) || empty( $certificate['certificate']['verification_code'] ) ) {
	fail( 'get-certificate did not return an eligible certificate with identifiers.' );
}

$transcript = $certificate['certificate']['transcript'] ?? [];
if ( count( $transcript ) !== count( $exercises ) ) {
	fail( sprintf( 'Certificate transcript count %d did not match exercise count %d.', count( $transcript ), count( $exercises ) ) );
}

$summary['certificate_id'] = $certificate['certificate']['certificate_id'];
$summary['transcript_count'] = count( $transcript );
$summary['checks'][] = 'get-certificate issued anonymous certificate and transcript';
assert_suggested_tools_exist( $certificate, $tool_names, 'get-certificate' );
$summary['status'] = 'ok';

if ( $options['json'] ) {
	echo json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( 0 );
}

echo "HTTP course completion smoke passed for {$options['url']}\n";
foreach ( $summary['checks'] as $check ) {
	echo "- {$check}\n";
}
echo 'Certificate ID: ' . $summary['certificate_id'] . "\n";

function course_definition( string $slug ): array {
	foreach ( CoursePack::definitions() as $definition ) {
		if ( ( $definition['slug'] ?? '' ) === $slug ) {
			return $definition;
		}
	}

	fail( "Course pack not found: {$slug}" );
}

function public_exercises( array $course ): array {
	$exercises = [];
	foreach ( $course['modules'] as $module ) {
		foreach ( $module['exercises'] as $exercise ) {
			$exercises[] = $exercise;
		}
	}

	return $exercises;
}

function answer_for_exercise( array $exercise ): string {
	if ( ! empty( $exercise['model_answer'] ) ) {
		return json_encode( $exercise['model_answer'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	return (string) ( $exercise['prompt'] ?? '' );
}

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

	$response = post_json( $url, $payload, default_headers( $session_id, $protocol, $extra_headers ) );
	if ( $response['status'] < 200 || $response['status'] >= 300 ) {
		fail( http_error_message( $method, $response ) );
	}

	$decoded = json_decode( $response['body'], true );
	if ( ! is_array( $decoded ) ) {
		fail( "{$method} returned invalid JSON: {$response['body']}" );
	}

	if ( ! empty( $decoded['error'] ) ) {
		fail( "{$method} returned JSON-RPC error: " . json_encode( $decoded['error'] ) );
	}

	$decoded['headers'] = $response['headers'];
	return $decoded;
}

function mcp_notification( string $url, string $method, array $params, string $session_id, string $protocol, array $extra_headers ): void {
	$payload = [
		'jsonrpc' => '2.0',
		'method'  => $method,
		'params'  => $params,
	];

	$response = post_json( $url, $payload, default_headers( $session_id, $protocol, $extra_headers ) );
	if ( $response['status'] < 200 || $response['status'] >= 300 ) {
		fail( http_error_message( $method, $response ) );
	}
}

function default_headers( ?string $session_id, string $protocol, array $extra_headers ): array {
	$headers = [
		'Content-Type: application/json',
		'Accept: application/json, text/event-stream',
		'MCP-Protocol-Version: ' . $protocol,
	];

	if ( $session_id ) {
		$headers[] = 'Mcp-Session-Id: ' . $session_id;
	}

	foreach ( $extra_headers as $header ) {
		$headers[] = $header;
	}

	return $headers;
}

function post_json( string $url, array $payload, array $headers ): array {
	$handle = curl_init( $url );
	curl_setopt_array(
		$handle,
		[
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode( $payload, JSON_UNESCAPED_SLASHES ),
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_TIMEOUT        => 60,
		]
	);

	$raw = curl_exec( $handle );
	if ( $raw === false ) {
		$error = curl_error( $handle );
		fail( "HTTP request failed: {$error}" );
	}

	$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$header_size = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );

	$raw_headers = substr( $raw, 0, $header_size );
	$body = substr( $raw, $header_size );

	return [
		'status'  => $status,
		'headers' => parse_headers( $raw_headers ),
		'body'    => $body,
	];
}

function parse_headers( string $raw_headers ): array {
	$headers = [];
	foreach ( preg_split( '/\r\n|\n|\r/', trim( $raw_headers ) ) ?: [] as $line ) {
		if ( strpos( $line, ':' ) === false ) {
			continue;
		}
		[ $name, $value ] = explode( ':', $line, 2 );
		$key = strtolower( trim( $name ) );
		$headers[ $key ][] = trim( $value );
	}

	return $headers;
}

function structured_content( array $result ): array {
	if ( isset( $result['structuredContent'] ) && is_array( $result['structuredContent'] ) ) {
		return $result['structuredContent'];
	}

	$text = $result['content'][0]['text'] ?? '';
	$decoded = json_decode( (string) $text, true );
	if ( is_array( $decoded ) ) {
		return $decoded;
	}

	fail( 'Tool result did not include structured content.' );
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

function http_error_message( string $method, array $response ): string {
	$location = (string) ( $response['headers']['location'][0] ?? '' );
	if ( $response['status'] >= 300 && $response['status'] < 400 && strpos( $location, 'wp-admin/install.php' ) !== false ) {
		return "{$method} returned HTTP {$response['status']} redirecting to {$location}. WordPress is not installed at this URL; install WordPress, activate the plugin, and flush permalinks before running the completion smoke.";
	}

	if ( $response['status'] >= 300 && $response['status'] < 400 && $location !== '' ) {
		return "{$method} returned HTTP {$response['status']} redirecting to {$location}. Confirm the URL points at the activated MCP endpoint.";
	}

	return "{$method} returned HTTP {$response['status']}: {$response['body']}";
}

function fail( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}
