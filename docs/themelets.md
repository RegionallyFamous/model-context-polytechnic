---
title: "Themelets: GitHub Pages Simplicity, WordPress-Shaped"
date: 2026-05-07
slug: themelets-github-pages-simplicity-wordpress-shaped
excerpt: "A themelet is a tiny static HTML/CSS site dressed like a WordPress theme, so it can live in WordPress without becoming a whole CMS project."
---

# Themelets: GitHub Pages Simplicity, WordPress-Shaped

I love GitHub Pages because the mental model is almost insultingly clear: make files, publish files, visit site. There is very little ceremony between the idea and the page.

WordPress is different. WordPress is a runtime, an admin, a plugin surface, a deployment target, a hosting norm, and a very old social contract with the web. Sometimes that is exactly what you want. Sometimes you just want the simplicity of a static site to live inside that world.

That is where I have been using the word "themelet."

A themelet is a tiny WordPress theme that behaves like a static HTML/CSS site. It has the files WordPress expects, but it does not pretend to be a full publishing system. It is a static site in WordPress clothing.

```text
my-themelet/
|-- style.css
|-- functions.php
|-- index.php
|-- site.css
|-- screenshot.png
`-- assets/
    |-- logo.png
    `-- hero.webp
```

The `style.css` file gives WordPress the theme identity. The `functions.php` file enqueues the CSS and sets up the handful of theme supports the page actually needs. The `index.php` file holds the mostly static page, with the important WordPress hooks still present: `wp_head()`, `wp_footer()`, `body_class()`, `language_attributes()`, and local asset helpers.

That is basically it.

## Why Not Just Use A Real Theme?

Often, you should. If the site needs archives, search, menus, comments, editable layouts, reusable templates, WooCommerce, or a long life as a publishing surface, make a proper WordPress theme. If the content model matters, let WordPress be WordPress.

But not every page wants to become a system.

Sometimes the thing is a focused project homepage, a small product page, a conference splash, a plugin demo, a docs front door, a tiny institutional gag that got out of hand in the best way, or a one-page experience that should be designed directly. GitHub Pages would be perfect, except the place it needs to live is WordPress.

A themelet gives that page enough WordPress shape to install cleanly without turning the whole thing into a site-building exercise.

## The Deal

The deal is simple:

1. Keep the page static by default.
2. Let WordPress load it like a theme.
3. Preserve the hooks plugins expect.
4. Enqueue assets the WordPress way.
5. Avoid inventing settings screens, content types, and template systems unless the project actually asks for them.

This is not anti-WordPress. It is WordPress restraint.

The point is not to smuggle a static site past WordPress. The point is to choose the smallest honest WordPress surface for the job.

## Anatomy Of A Themelet

A themelet usually needs three core files.

`style.css` is the passport:

```css
/*
Theme Name: Model Context Polytechnic Themelet
Description: A static WordPress themelet for the Model Context Polytechnic landing page.
Version: 1.0.0
Requires at least: 6.9
Requires PHP: 8.1
Text Domain: model-context-polytechnic-themelet
*/
```

`functions.php` is the tiny adapter:

```php
<?php
defined( 'ABSPATH' ) || exit;

const EXAMPLE_THEMELET_VERSION = '1.0.0';

add_action( 'after_setup_theme', 'example_themelet_setup' );
add_action( 'wp_enqueue_scripts', 'example_themelet_enqueue_assets' );

function example_themelet_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', [ 'script', 'style' ] );
}

function example_themelet_enqueue_assets(): void {
	wp_enqueue_style(
		'example-themelet-site',
		get_template_directory_uri() . '/site.css',
		[],
		EXAMPLE_THEMELET_VERSION
	);
}

function example_themelet_asset( string $path ): string {
	return esc_url( get_template_directory_uri() . '/assets/' . ltrim( $path, '/' ) );
}
```

`index.php` is the page:

```php
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<main id="main">
		<!-- Mostly static HTML goes here. -->
	</main>
	<?php wp_footer(); ?>
</body>
</html>
```

The trick is knowing when to stop. The moment the themelet starts growing a content model, it is probably no longer a themelet. That is fine. It graduated. Give it the architecture it deserves.

## Why I Like The Pattern

Themelets make small things easy to ship without leaving the WordPress ecosystem. They let a designer or developer keep direct control over the page. They avoid page-builder gravity. They reduce the gap between "I made a static thing" and "this can be installed on the WordPress site where it belongs."

They also make the boundary explicit. A themelet says: this is not a universal theme. This is not your magazine template. This is a precise little object.

That honesty is useful.

## The Model Context Polytechnic Example

I used this pattern for the Model Context Polytechnic site. The plugin is the real WordPress software: it exposes public MCP learning courses, stores anonymous learner progress, and teaches LLMs better WordPress plugin craft.

The public-facing campus page, though, did not need to be a dynamic WordPress site. It needed to look like an old technical school where language models enroll to stop writing suspicious plugin code.

So it became a themelet.

WordPress sees a normal theme folder. Visitors see the same focused HTML/CSS experience. The plugin remains separate from the public skin. That separation is the whole pleasure of the thing.

## A Skill For Making Them

I also turned the pattern into a Codex skill: `build-themelet`.

The skill teaches an agent to convert static HTML/CSS into an installable WordPress themelet, including the starter structure, the WordPress wrapper rules, the asset helper pattern, and the line where a themelet should stop being a themelet.

That last part matters. The useful thing is not just generating the files. The useful thing is preserving the idea: GitHub Pages simplicity, WordPress-shaped.

Themelets are small. That is the point.
