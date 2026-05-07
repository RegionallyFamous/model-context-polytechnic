#!/usr/bin/env php
<?php
/**
 * Repository lint checks for local development and CI.
 */

declare( strict_types=1 );

$root   = dirname( __DIR__ );
$failed = false;

$print = static function ( string $status, string $message ): void {
	echo '[' . $status . '] ' . $message . PHP_EOL;
};

$run = static function ( string $label, string $command ) use ( $root, $print, &$failed ): void {
	$previous = getcwd();
	chdir( $root );

	$output = [];
	$code   = 0;
	exec( $command . ' 2>&1', $output, $code );

	if ( $previous !== false ) {
		chdir( $previous );
	}

	if ( $code === 0 ) {
		$print( 'ok', $label );
		return;
	}

	$failed = true;
	$print( 'fail', $label );
	foreach ( $output as $line ) {
		echo '  ' . $line . PHP_EOL;
	}
};

$read = static function ( string $relative ) use ( $root ): string {
	$path = $root . DIRECTORY_SEPARATOR . $relative;
	if ( ! is_readable( $path ) ) {
		throw new RuntimeException( "Cannot read {$relative}." );
	}

	return (string) file_get_contents( $path );
};

$match = static function ( string $label, string $pattern, string $content ) use ( $print, &$failed ): string {
	if ( preg_match( $pattern, $content, $matches ) ) {
		return (string) $matches[1];
	}

	$failed = true;
	$print( 'fail', "Could not find {$label}." );
	return '';
};

$run( 'Composer manifest validates.', 'composer validate --no-check-publish' );
$run( 'Foundation check passes.', escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/bin/foundation-check.php' ) );

if ( is_dir( $root . '/.git' ) ) {
	$run( 'Git whitespace check passes.', 'git diff --check' );
}

try {
	$main   = $read( 'model-context-polytechnic.php' );
	$server = $read( 'includes/class-server.php' );

	$versions = [
		'plugin header' => $match( 'plugin header version', '/^\s*\*\s*Version:\s*([^\s]+)/m', $main ),
		'plugin constant' => $match( 'plugin version constant', "/define\\(\\s*'MODEL_CONTEXT_POLYTECHNIC_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $main ),
		'server constant' => $match( 'server version constant', "/const\\s+SERVER_VERSION\\s*=\\s*'([^']+)'/", $server ),
	];

	$unique = array_values( array_unique( array_filter( $versions ) ) );
	if ( count( $unique ) !== 1 ) {
		$failed = true;
		$print( 'fail', 'Project versions are inconsistent: ' . wp_json_encode_compat( $versions ) );
	} elseif ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $unique[0] ) ) {
		$failed = true;
		$print( 'fail', 'Project version is not semantic: ' . $unique[0] );
	} else {
		$print( 'ok', 'Project version is consistent: ' . $unique[0] . '.' );
	}
} catch ( Throwable $e ) {
	$failed = true;
	$print( 'fail', $e->getMessage() );
}

echo $failed ? 'Lint failed.' . PHP_EOL : 'Lint passed.' . PHP_EOL;
exit( $failed ? 1 : 0 );

function wp_json_encode_compat( array $value ): string {
	$encoded = json_encode( $value, JSON_UNESCAPED_SLASHES );
	return is_string( $encoded ) ? $encoded : '[]';
}
