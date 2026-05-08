#!/usr/bin/env php
<?php
/**
 * Build an installable WordPress plugin ZIP.
 */

declare( strict_types=1 );

$root    = dirname( __DIR__ );
$slug    = 'model-context-polytechnic';
$version = parse_version_arg( $argv ) ?: read_project_version( $root );

if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
	fail( "Invalid release version: {$version}" );
}

assert_project_version( $root, $version );

if ( ! extension_loaded( 'zip' ) || ! class_exists( ZipArchive::class ) ) {
	fail( 'PHP zip extension is required to build a release.' );
}

if ( ! is_readable( $root . '/vendor/autoload_packages.php' ) ) {
	fail( 'vendor/autoload_packages.php is missing. Run composer install before building.' );
}

$dist_dir    = $root . '/dist';
$build_root  = $dist_dir . '/build';
$staging_dir = $build_root . '/' . $slug;
$zip_path    = $dist_dir . '/' . $slug . '-' . $version . '.zip';
$sha_path    = $zip_path . '.sha256';

remove_path( $build_root );
ensure_dir( $staging_dir );

$include_paths = [
	'model-context-polytechnic.php',
	'uninstall.php',
	'composer.json',
	'composer.lock',
	'README.md',
	'CHANGELOG.md',
	'assets',
	'includes',
	'vendor',
	'course-packs',
	'schemas',
];

foreach ( $include_paths as $relative ) {
	$source = $root . '/' . $relative;
	if ( ! file_exists( $source ) ) {
		fail( "Required release path is missing: {$relative}" );
	}

	copy_path( $source, $staging_dir . '/' . $relative, $root );
}

if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}
if ( file_exists( $sha_path ) ) {
	unlink( $sha_path );
}

$zip = new ZipArchive();
if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
	fail( "Could not create {$zip_path}." );
}

$files = collect_files( $staging_dir );
foreach ( $files as $file ) {
	$local_name = $slug . '/' . normalize_path( substr( $file, strlen( $staging_dir ) + 1 ) );
	$zip->addFile( $file, $local_name );
}

$zip->close();

$hash = hash_file( 'sha256', $zip_path );
if ( ! is_string( $hash ) ) {
	fail( 'Could not hash release ZIP.' );
}

file_put_contents( $sha_path, $hash . '  ' . basename( $zip_path ) . PHP_EOL );
remove_path( $build_root );

echo '[ok] Built ' . $zip_path . PHP_EOL;
echo '[ok] SHA256 ' . $hash . PHP_EOL;

function parse_version_arg( array $argv ): string {
	foreach ( array_slice( $argv, 1 ) as $index => $arg ) {
		if ( str_starts_with( $arg, '--version=' ) ) {
			return substr( $arg, 10 );
		}

		if ( $arg === '--version' ) {
			return (string) ( $argv[ $index + 2 ] ?? '' );
		}
	}

	return '';
}

function read_project_version( string $root ): string {
	$main = (string) file_get_contents( $root . '/model-context-polytechnic.php' );
	if ( preg_match( '/^\s*\*\s*Version:\s*([^\s]+)/m', $main, $matches ) ) {
		return (string) $matches[1];
	}

	fail( 'Could not read plugin header version.' );
}

function assert_project_version( string $root, string $expected ): void {
	$main   = (string) file_get_contents( $root . '/model-context-polytechnic.php' );
	$server = (string) file_get_contents( $root . '/includes/class-server.php' );

	$versions = [
		'plugin header' => match_version( '/^\s*\*\s*Version:\s*([^\s]+)/m', $main, 'plugin header version' ),
		'plugin constant' => match_version( "/define\\(\\s*'MODEL_CONTEXT_POLYTECHNIC_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $main, 'plugin version constant' ),
		'server constant' => match_version( "/const\\s+SERVER_VERSION\\s*=\\s*'([^']+)'/", $server, 'server version constant' ),
	];

	foreach ( $versions as $label => $version ) {
		if ( $version !== $expected ) {
			fail( "{$label} is {$version}, expected {$expected}." );
		}
	}
}

function match_version( string $pattern, string $content, string $label ): string {
	if ( preg_match( $pattern, $content, $matches ) ) {
		return (string) $matches[1];
	}

	fail( "Could not find {$label}." );
}

function copy_path( string $source, string $destination, string $root ): void {
	if ( should_skip( $source, $root ) ) {
		return;
	}

	if ( is_dir( $source ) ) {
		ensure_dir( $destination );
		$children = scandir( $source );
		if ( ! is_array( $children ) ) {
			fail( "Could not read {$source}." );
		}

		foreach ( $children as $child ) {
			if ( $child === '.' || $child === '..' ) {
				continue;
			}

			copy_path( $source . '/' . $child, $destination . '/' . $child, $root );
		}
		return;
	}

	ensure_dir( dirname( $destination ) );
	if ( ! copy( $source, $destination ) ) {
		fail( "Could not copy {$source}." );
	}
}

function should_skip( string $path, string $root ): bool {
	$relative = normalize_path( substr( $path, strlen( $root ) + 1 ) );
	$segments = explode( '/', $relative );

	if ( array_intersect( $segments, [ '.git', '.github' ] ) ) {
		return true;
	}

	if ( str_starts_with( $relative, 'vendor/' ) && array_intersect( $segments, [ 'test', 'tests', '.github' ] ) ) {
		return true;
	}

	return false;
}

function collect_files( string $directory ): array {
	$files = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files, SORT_STRING );
	return $files;
}

function remove_path( string $path ): void {
	if ( ! file_exists( $path ) ) {
		return;
	}

	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}

	rmdir( $path );
}

function ensure_dir( string $directory ): void {
	if ( is_dir( $directory ) ) {
		return;
	}

	if ( ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		fail( "Could not create {$directory}." );
	}
}

function normalize_path( string $path ): string {
	return str_replace( '\\', '/', $path );
}

function fail( string $message ): never {
	fwrite( STDERR, '[fail] ' . $message . PHP_EOL );
	exit( 1 );
}
