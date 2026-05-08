#!/usr/bin/env php
<?php
require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$root   = dirname( __DIR__ );
$failed = false;

$print = static function ( string $status, string $message ): void {
	echo '[' . $status . '] ' . $message . PHP_EOL;
};

$php_files = [];
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( ! $file->isFile() ) {
		continue;
	}

	if ( strpos( $path, $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) === 0 ) {
		continue;
	}

	if ( substr( $path, -4 ) === '.php' ) {
		$php_files[] = $path;
	}
}

sort( $php_files, SORT_STRING );
foreach ( $php_files as $php_file ) {
	$command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $php_file ) . ' 2>&1';
	exec( $command, $output, $code );
	if ( $code !== 0 ) {
		$failed = true;
		$print( 'fail', 'PHP syntax: ' . substr( $php_file, strlen( $root ) + 1 ) );
		foreach ( $output as $line ) {
			echo '  ' . $line . PHP_EOL;
		}
	}
}

if ( ! $failed ) {
	$print( 'ok', 'PHP syntax passed for ' . count( $php_files ) . ' non-vendor files.' );
}

$json_files = [];
foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( ! $file->isFile() || substr( $path, -5 ) !== '.json' ) {
		continue;
	}

	if ( strpos( $path, $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) === 0 ) {
		continue;
	}

	$json_files[] = $path;
}

sort( $json_files, SORT_STRING );
foreach ( $json_files as $json_file ) {
	$decoded = json_decode( (string) file_get_contents( $json_file ), true );
	if ( ! is_array( $decoded ) ) {
		$failed = true;
		$print( 'fail', 'JSON parse: ' . substr( $json_file, strlen( $root ) + 1 ) . ' - ' . json_last_error_msg() );
	}
}

if ( ! $failed ) {
	$print( 'ok', 'JSON parsed for ' . count( $json_files ) . ' non-vendor files.' );
}

$brand_files = [];
$brand_extensions = [ 'php', 'json', 'md', 'html', 'css', 'js', 'svg', 'txt', 'xml', 'yml', 'yaml' ];
foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( ! $file->isFile() ) {
		continue;
	}

	if ( strpos( $path, $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) === 0 ) {
		continue;
	}

	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	if ( in_array( $extension, $brand_extensions, true ) ) {
		$brand_files[] = $path;
	}
}

$bad_brand = 'Word' . 'press';
foreach ( $brand_files as $brand_file ) {
	$contents = (string) file_get_contents( $brand_file );
	if ( strpos( $contents, $bad_brand ) !== false ) {
		$failed = true;
		$print( 'fail', 'Brand casing: ' . substr( $brand_file, strlen( $root ) + 1 ) . ' contains ' . $bad_brand . '; use WordPress.' );
	}
}

if ( ! $failed ) {
	$print( 'ok', 'WordPress brand casing passed for ' . count( $brand_files ) . ' text files.' );
}

$audit = CoursePack::audit_all();
if ( ! $audit['valid'] ) {
	$failed = true;
	foreach ( $audit['errors'] as $error ) {
		$print( 'fail', $error );
	}

	foreach ( $audit['packs'] as $pack ) {
		foreach ( $pack['errors'] as $error ) {
			$print( 'fail', ( $pack['slug'] ?: $pack['path'] ) . ': ' . $error );
		}
	}
} else {
	$lesson_count = 0;
	$exercise_count = 0;
	foreach ( $audit['packs'] as $pack ) {
		$lesson_count   += (int) $pack['counts']['lessons'];
		$exercise_count += (int) $pack['counts']['exercises'];
	}

	$print( 'ok', sprintf( 'Course packs valid: %d pack(s), %d lesson(s), %d exercise(s).', (int) $audit['pack_count'], $lesson_count, $exercise_count ) );
}

if ( ! is_readable( $root . '/vendor/autoload_packages.php' ) ) {
	$failed = true;
	$print( 'fail', 'Jetpack autoloader missing. Run composer install.' );
} else {
	$print( 'ok', 'Jetpack autoloader present.' );
}

echo $failed ? 'Foundation check failed.' . PHP_EOL : 'Foundation check passed.' . PHP_EOL;
exit( $failed ? 1 : 0 );
