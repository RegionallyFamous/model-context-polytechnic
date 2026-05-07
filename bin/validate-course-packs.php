#!/usr/bin/env php
<?php
require_once dirname( __DIR__ ) . '/includes/class-course-pack.php';

use ModelContextPolytechnic\Mcp\CoursePack;

$root  = $argv[1] ?? CoursePack::root();
$audit = CoursePack::audit_all( $root );

echo 'Course pack root: ' . $audit['root'] . PHP_EOL;
echo 'Fingerprint: ' . $audit['fingerprint'] . PHP_EOL;
echo 'Packs: ' . $audit['pack_count'] . PHP_EOL;

foreach ( $audit['errors'] as $error ) {
	echo '[error] ' . $error . PHP_EOL;
}

foreach ( $audit['packs'] as $pack ) {
	echo PHP_EOL;
	echo ( $pack['valid'] ? '[ok] ' : '[failed] ' ) . ( $pack['slug'] ?: $pack['path'] ) . PHP_EOL;
	echo '  modules: ' . $pack['counts']['modules'] . PHP_EOL;
	echo '  lessons: ' . $pack['counts']['lessons'] . PHP_EOL;
	echo '  exercises: ' . $pack['counts']['exercises'] . PHP_EOL;
	echo '  model answers: ' . ( $pack['counts']['model_answers'] ?? 0 ) . PHP_EOL;
	echo '  references: ' . ( $pack['counts']['references'] + ( $pack['counts']['sources'] > 0 ? 1 : 0 ) ) . PHP_EOL;
	echo '  sources: ' . $pack['counts']['sources'] . PHP_EOL;

	foreach ( $pack['warnings'] as $warning ) {
		echo '  [warning] ' . $warning . PHP_EOL;
	}

	foreach ( $pack['errors'] as $error ) {
		echo '  [error] ' . $error . PHP_EOL;
	}
}

echo PHP_EOL . ( $audit['valid'] ? 'Course packs are valid.' : 'Course pack validation failed.' ) . PHP_EOL;
exit( $audit['valid'] ? 0 : 1 );
