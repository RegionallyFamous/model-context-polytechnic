#!/usr/bin/env php
<?php
/**
 * Bump release versions across plugin and themelet headers.
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );
$version = parse_version_arg( $argv );

if ( $version === '' || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
	fail( 'Usage: php bin/bump-version.php --version=x.y.z' );
}

replace_once(
	$root . '/model-context-polytechnic.php',
	'/(\*\s*Version:\s*)[^\s]+/',
	static function ( array $matches ) use ( $version ): string {
		return $matches[1] . $version;
	}
);

replace_once(
	$root . '/model-context-polytechnic.php',
	"/define\\(\\s*'MODEL_CONTEXT_POLYTECHNIC_VERSION'\\s*,\\s*'[^']+'\\s*\\);/",
	static function () use ( $version ): string {
		return "define( 'MODEL_CONTEXT_POLYTECHNIC_VERSION', '{$version}' );";
	}
);

replace_once(
	$root . '/includes/class-server.php',
	"/const\\s+SERVER_VERSION\\s*=\\s*'[^']+';/",
	static function () use ( $version ): string {
		return "const SERVER_VERSION   = '{$version}';";
	}
);

$themelet_style = $root . '/themelet/model-context-polytechnic-themelet/style.css';
if ( is_readable( $themelet_style ) ) {
	replace_once(
		$themelet_style,
		'/(Version:\s*)[^\s]+/',
		static function ( array $matches ) use ( $version ): string {
			return $matches[1] . $version;
		}
	);
}

$themelet_functions = $root . '/themelet/model-context-polytechnic-themelet/functions.php';
if ( is_readable( $themelet_functions ) ) {
	replace_once(
		$themelet_functions,
		"/const\\s+MCPOLY_THEMELET_VERSION\\s*=\\s*'[^']+';/",
		static function () use ( $version ): string {
			return "const MCPOLY_THEMELET_VERSION = '{$version}';";
		}
	);
}

echo "[ok] Version bumped to {$version}." . PHP_EOL;

function parse_version_arg( array $argv ): string {
	foreach ( array_slice( $argv, 1 ) as $index => $arg ) {
		if ( str_starts_with( $arg, '--version=' ) ) {
			return substr( $arg, 10 );
		}

		if ( $arg === '--version' ) {
			return (string) ( $argv[ $index + 2 ] ?? '' );
		}

		if ( $index === 0 && strpos( $arg, '-' ) !== 0 ) {
			return $arg;
		}
	}

	return '';
}

function replace_once( string $path, string $pattern, callable $replacement ): void {
	$content = file_get_contents( $path );
	if ( ! is_string( $content ) ) {
		fail( "Could not read {$path}." );
	}

	$count = 0;
	$updated = preg_replace_callback( $pattern, $replacement, $content, 1, $count );
	if ( ! is_string( $updated ) || $count !== 1 ) {
		fail( "Could not update version in {$path}." );
	}

	file_put_contents( $path, $updated );
}

function fail( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}
