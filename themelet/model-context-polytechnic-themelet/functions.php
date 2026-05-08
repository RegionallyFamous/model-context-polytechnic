<?php
/**
 * Themelet setup for Model Context Polytechnic.
 */

defined( 'ABSPATH' ) || exit;

const MCPOLY_THEMELET_VERSION = '1.0.5';

add_action( 'after_setup_theme', 'mcpoly_themelet_setup' );
add_action( 'wp_enqueue_scripts', 'mcpoly_themelet_enqueue_assets' );
add_action( 'wp_head', 'mcpoly_themelet_head_metadata', 1 );
add_filter( 'pre_get_document_title', 'mcpoly_themelet_document_title' );

function mcpoly_themelet_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', [ 'script', 'style' ] );
}

function mcpoly_themelet_enqueue_assets(): void {
	$style_path  = get_template_directory() . '/site.css';
	$script_path = get_template_directory() . '/site.js';
	$style_ver   = file_exists( $style_path ) ? (string) filemtime( $style_path ) : MCPOLY_THEMELET_VERSION;
	$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : MCPOLY_THEMELET_VERSION;

	wp_enqueue_style(
		'mcpoly-themelet-fonts',
		'https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Libre+Baskerville:wght@400;700&family=Source+Sans+3:wght@400;600;700;800&display=swap',
		[],
		null
	);

	wp_enqueue_style(
		'mcpoly-themelet-site',
		get_template_directory_uri() . '/site.css',
		[ 'mcpoly-themelet-fonts' ],
		$style_ver
	);

	wp_enqueue_script(
		'mcpoly-themelet-site',
		get_template_directory_uri() . '/site.js',
		[],
		$script_ver,
		true
	);
}

function mcpoly_themelet_head_metadata(): void {
	$hero = get_template_directory_uri() . '/assets/campus-hero.webp';
	echo '<meta name="description" content="Model Context Polytechnic teaches LLMs to build higher-quality WordPress plugins through public MCP courses, guided practice, rubrics, and memory.">' . "\n";
	echo '<meta name="theme-color" content="#26090b">' . "\n";
	echo '<link rel="preload" as="image" href="' . esc_url( $hero ) . '">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}

function mcpoly_themelet_asset( string $path ): string {
	return esc_url( get_template_directory_uri() . '/assets/' . ltrim( $path, '/' ) );
}

function mcpoly_themelet_document_title(): string {
	return 'Model Context Polytechnic';
}
