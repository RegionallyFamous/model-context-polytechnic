<?php
namespace ModelContextPolytechnic\Mcp;

class Rewrite {
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'add_rules' ] );
	}

	public static function add_rules(): void {
		add_rewrite_rule(
			'^' . Server::VANITY_PATH . '/?$',
			'index.php?rest_route=/' . Server::REST_NS . '/' . Server::REST_ROUTE,
			'top'
		);

		add_rewrite_rule(
			'^' . Server::VANITY_PATH . '/([a-z0-9-]+)/?$',
			'index.php?rest_route=/' . Server::REST_NS . '/courses/$matches[1]',
			'top'
		);
	}
}
