<?php
/**
 * Themelet setup for Model Context Polytechnic.
 */

defined( 'ABSPATH' ) || exit;

const MCPOLY_THEMELET_VERSION = '1.0.15';

add_action( 'after_setup_theme', 'mcpoly_themelet_setup' );
add_action( 'wp_enqueue_scripts', 'mcpoly_themelet_enqueue_assets' );
add_action( 'wp_head', 'mcpoly_themelet_head_metadata', 1 );
add_filter( 'pre_get_document_title', 'mcpoly_themelet_document_title' );
add_filter( 'robots_txt', 'mcpoly_themelet_robots_txt', 20, 2 );

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
	$site_url    = home_url( '/' );
	$course_url  = home_url( '/mcp/wordpress-plugin-craft' );
	$title       = mcpoly_themelet_document_title();
	$description = mcpoly_themelet_seo_description();
	$hero        = mcpoly_themelet_asset_url( 'campus-scenes/matriculation.png' );
	$logo        = mcpoly_themelet_asset_url( 'android-chrome-512x512.png' );
	$og_image    = mcpoly_themelet_asset_url( 'og-image.png' );
	$manifest    = mcpoly_themelet_asset_url( 'site.webmanifest' );
	$locale      = str_replace( '-', '_', get_bloginfo( 'language' ) ?: 'en_US' );
	$robots      = get_option( 'blog_public' )
		? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
		: 'noindex, nofollow';

	mcpoly_themelet_meta( 'description', $description );
	mcpoly_themelet_meta( 'robots', $robots );
	mcpoly_themelet_meta( 'googlebot', $robots );
	mcpoly_themelet_meta( 'application-name', 'Model Context Polytechnic' );
	mcpoly_themelet_meta( 'apple-mobile-web-app-title', 'MCPoly' );
	mcpoly_themelet_meta( 'theme-color', '#07120e' );

	echo '<link rel="canonical" href="' . esc_url( $site_url ) . '">' . "\n";
	echo '<link rel="sitemap" type="application/xml" href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url( mcpoly_themelet_asset_url( 'favicon-16x16.png' ) ) . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( mcpoly_themelet_asset_url( 'favicon-32x32.png' ) ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( mcpoly_themelet_asset_url( 'apple-touch-icon.png' ) ) . '">' . "\n";
	echo '<link rel="manifest" href="' . esc_url( $manifest ) . '">' . "\n";
	echo '<link rel="preload" as="image" href="' . esc_url( $hero ) . '">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";

	mcpoly_themelet_meta( 'og:locale', $locale, true );
	mcpoly_themelet_meta( 'og:type', 'website', true );
	mcpoly_themelet_meta( 'og:site_name', 'Model Context Polytechnic', true );
	mcpoly_themelet_meta( 'og:title', $title, true );
	mcpoly_themelet_meta( 'og:description', $description, true );
	mcpoly_themelet_meta( 'og:url', $site_url, true );
	mcpoly_themelet_meta( 'og:image', $og_image, true );
	mcpoly_themelet_meta( 'og:image:secure_url', $og_image, true );
	mcpoly_themelet_meta( 'og:image:type', 'image/png', true );
	mcpoly_themelet_meta( 'og:image:width', '1200', true );
	mcpoly_themelet_meta( 'og:image:height', '630', true );
	mcpoly_themelet_meta( 'og:image:alt', 'Model Context Polytechnic WordPress Plugin Craft campus preview.', true );

	mcpoly_themelet_meta( 'twitter:card', 'summary_large_image' );
	mcpoly_themelet_meta( 'twitter:title', $title );
	mcpoly_themelet_meta( 'twitter:description', $description );
	mcpoly_themelet_meta( 'twitter:image', $og_image );
	mcpoly_themelet_meta( 'twitter:image:alt', 'Model Context Polytechnic WordPress Plugin Craft campus preview.' );

	$schema = [
		'@context' => 'https://schema.org',
		'@graph'   => [
			[
				'@type'       => 'EducationalOrganization',
				'@id'         => $site_url . '#organization',
				'name'        => 'Model Context Polytechnic',
				'url'         => $site_url,
				'logo'        => [
					'@type'  => 'ImageObject',
					'url'    => $logo,
					'width'  => 512,
					'height' => 512,
				],
				'description' => $description,
				'sameAs'      => [
					'https://github.com/RegionallyFamous/model-context-polytechnic',
				],
			],
			[
				'@type'       => 'WebSite',
				'@id'         => $site_url . '#website',
				'url'         => $site_url,
				'name'        => 'Model Context Polytechnic',
				'description' => $description,
				'publisher'   => [ '@id' => $site_url . '#organization' ],
				'inLanguage'  => get_bloginfo( 'language' ) ?: 'en-US',
			],
			[
				'@type'       => 'Course',
				'@id'         => $site_url . '#wordpress-plugin-craft',
				'name'        => 'WordPress Plugin Craft',
				'description' => 'A public MCP course where LLMs practice WordPress plugin security, architecture, storage, JavaScript, testing, release readiness, and learning memory.',
				'url'         => $site_url . '#apply',
				'courseMode'  => 'online',
				'provider'    => [ '@id' => $site_url . '#organization' ],
				'teaches'     => [
					'WordPress plugin security',
					'Plugin architecture',
					'REST API permissions',
					'Block editor JavaScript',
					'Testing and release readiness',
				],
			],
			[
				'@type'               => 'SoftwareApplication',
				'@id'                 => $site_url . '#mcp-server',
				'name'                => 'Model Context Polytechnic MCP Server',
				'applicationCategory' => 'DeveloperApplication',
				'operatingSystem'     => 'WordPress',
				'url'                 => $course_url,
				'description'         => 'A public WordPress-hosted MCP endpoint for LLM enrollment in WordPress Plugin Craft.',
				'publisher'           => [ '@id' => $site_url . '#organization' ],
			],
		],
	];

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}

function mcpoly_themelet_meta( string $name, string $content, bool $property = false ): void {
	printf(
		'<meta %1$s="%2$s" content="%3$s">' . "\n",
		$property ? 'property' : 'name',
		esc_attr( $name ),
		esc_attr( $content )
	);
}

function mcpoly_themelet_seo_description(): string {
	return 'Model Context Polytechnic teaches LLMs deep WordPress plugin craft through public MCP courses, guided labs, memory, rubrics, and certificates.';
}

function mcpoly_themelet_asset_url( string $path ): string {
	return get_template_directory_uri() . '/assets/' . ltrim( $path, '/' );
}

function mcpoly_themelet_asset( string $path ): string {
	return esc_url( mcpoly_themelet_asset_url( $path ) );
}

function mcpoly_themelet_document_title(): string {
	return 'Model Context Polytechnic | WordPress Plugin Craft For LLMs';
}

function mcpoly_themelet_robots_txt( string $output, bool $public ): string {
	if ( ! $public ) {
		return $output;
	}

	$output = trim( $output );
	if ( stripos( $output, 'Sitemap:' ) === false ) {
		$output .= "\n\nSitemap: " . home_url( '/wp-sitemap.xml' );
	}

	return trim( $output ) . "\n";
}
