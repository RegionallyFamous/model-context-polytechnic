<?php
namespace ModelContextPolytechnic\Mcp;

class Auth {
	const TABLE        = 'model_context_polytechnic_mcp_tokens';
	const TOKEN_PREFIX = 'mcpoly_';
	const SCHEMA_VERSION = '1';

	public static function init(): void {
		if ( Server::WRITE_AUTH_MODE !== 'bearer' ) {
			return;
		}

		add_action( 'init', [ __CLASS__, 'maybe_install_table' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'model-context-polytechnic auth config', [ __CLASS__, 'cli_auth_config' ] );
			\WP_CLI::add_command( 'model-context-polytechnic token mint', [ __CLASS__, 'cli_mint' ] );
			\WP_CLI::add_command( 'model-context-polytechnic token list', [ __CLASS__, 'cli_list' ] );
			\WP_CLI::add_command( 'model-context-polytechnic token revoke', [ __CLASS__, 'cli_revoke' ] );
		}
	}

	public static function maybe_install_table(): void {
		if ( get_option( 'model_context_polytechnic_auth_schema_version' ) === self::SCHEMA_VERSION ) {
			return;
		}

		self::install_table();
	}

	public static function install_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE $table (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				token_hash CHAR(64) NOT NULL,
				email VARCHAR(255) NULL,
				label VARCHAR(255) NULL,
				plan VARCHAR(50) NOT NULL DEFAULT 'free',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_used_at DATETIME NULL,
				revoked TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				UNIQUE KEY token_hash (token_hash)
			) $charset;"
		);

		update_option( 'model_context_polytechnic_auth_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function check_bearer( $args = null ): bool {
		$header = self::authorization_header();

		if ( ! preg_match( '/Bearer\s+(.+)/i', $header, $matches ) ) {
			return false;
		}

		if ( ! self::rate_limit() ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$hash  = hash( 'sha256', trim( $matches[1] ) );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, plan, revoked FROM $table WHERE token_hash = %s",
				$hash
			)
		);

		if ( ! $row || (int) $row->revoked === 1 ) {
			return false;
		}

		$wpdb->update(
			$table,
			[ 'last_used_at' => current_time( 'mysql' ) ],
			[ 'id' => $row->id ]
		);

		$GLOBALS['model_context_polytechnic_mcp_token'] = $row;
		return true;
	}

	public static function require_bearer( $args = null ) {
		if ( self::check_bearer( $args ) ) {
			return true;
		}

		return new \WP_Error(
			'model_context_polytechnic_auth_required',
			__( 'Bearer token required for write access.', 'model-context-polytechnic' ),
			[ 'status' => 401 ]
		);
	}

	public static function require_write_access( $args = null ) {
		if ( self::check_bearer( $args ) ) {
			return true;
		}

		$capability = (string) apply_filters( 'model_context_polytechnic_write_capability', 'edit_posts', $args );
		if (
			function_exists( 'current_user_can' )
			&& current_user_can( $capability )
		) {
			if ( function_exists( 'wp_get_current_user' ) ) {
				$GLOBALS['model_context_polytechnic_wp_user'] = wp_get_current_user();
			}

			return true;
		}

		return new \WP_Error(
			'model_context_polytechnic_write_auth_required',
			sprintf(
				/* translators: %s: WordPress capability name. */
				__( 'Write access requires a bearer token or a WordPress user with the "%s" capability.', 'model-context-polytechnic' ),
				$capability
			),
			[ 'status' => 401 ]
		);
	}

	private static function authorization_header(): string {
		$header = $_SERVER['HTTP_AUTHORIZATION']
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			?? '';

		if ( $header !== '' ) {
			return $header;
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			foreach ( $headers as $name => $value ) {
				if ( strtolower( (string) $name ) === 'authorization' ) {
					return (string) $value;
				}
			}
		}

		return '';
	}

	public static function mint_token( string $email = '', string $label = '', string $plan = 'free' ): string {
		$token = self::TOKEN_PREFIX . bin2hex( random_bytes( 32 ) );
		$hash  = hash( 'sha256', $token );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			[
				'token_hash' => $hash,
				'email'      => sanitize_email( $email ),
				'label'      => sanitize_text_field( $label ),
				'plan'       => sanitize_key( $plan ),
			]
		);

		return $token;
	}

	public static function rate_limit(): bool {
		$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key  = 'model_context_polytechnic_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );

		if ( $hits >= 60 ) {
			return false;
		}

		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return true;
	}

	public static function cli_mint( $args, $assoc_args ): void {
		$email = $assoc_args['email'] ?? '';
		$label = $assoc_args['label'] ?? '';
		$plan  = $assoc_args['plan'] ?? 'free';
		$token = self::mint_token( $email, $label, $plan );

		\WP_CLI::success( "Token (save this, it will not be shown again):\n$token" );
	}

	public static function cli_auth_config( $args, $assoc_args ): void {
		$access    = sanitize_key( $assoc_args['access'] ?? 'write' );
		$client    = sanitize_key( $assoc_args['client'] ?? 'generic' );
		$transport = sanitize_key( $assoc_args['transport'] ?? 'auto' );
		$email     = $assoc_args['email'] ?? '';
		$label     = $assoc_args['label'] ?? $client;
		$plan      = $assoc_args['plan'] ?? 'free';
		$site_url  = isset( $assoc_args['site-url'] )
			? Server::normalize_site_url( (string) $assoc_args['site-url'] )
			: home_url();
		$endpoint  = Server::vanity_endpoint( $site_url );
		$read_only = $access === 'read' || $access === 'read_only' || ! empty( $assoc_args['read-only'] );
		$use_proxy = $transport === 'proxy' || ( $transport === 'auto' && in_array( $client, [ 'claude', 'cursor', 'vscode' ], true ) );
		$token     = '';
		$headers   = [];

		if ( ! $read_only ) {
			$token   = self::mint_token( $email, $label, $plan );
			$headers = [
				'Authorization' => 'Bearer ' . $token,
			];
		}

		if ( $use_proxy ) {
			$server = [
				'command' => 'npx',
				'args'    => [ '-y', Server::REMOTE_PROXY ],
				'env'     => [
					'WP_API_URL'    => $endpoint,
					'OAUTH_ENABLED' => 'false',
				],
			];

			if ( ! $read_only ) {
				$server['env']['CUSTOM_HEADERS'] = wp_json_encode( $headers, JSON_UNESCAPED_SLASHES );
			}
		} else {
			$server = [
				'url' => $endpoint,
			];

			if ( ! $read_only ) {
				$server['headers'] = $headers;
			}
		}

		$config = [
			'mcpServers' => [
				Server::SERVER_ID => $server,
			],
		];

		if ( $read_only ) {
			\WP_CLI::line( 'Read-only MCP config:' );
		} else {
			\WP_CLI::line( "Token (save this, it will not be shown again):\n$token\n" );
			\WP_CLI::line( 'Write-enabled MCP config:' );
		}

		\WP_CLI::line( wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	public static function cli_list( $args, $assoc_args ): void {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$rows  = $wpdb->get_results(
			"SELECT id, email, label, plan, created_at, last_used_at, revoked FROM $table ORDER BY created_at DESC",
			ARRAY_A
		);

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'email', 'label', 'plan', 'created_at', 'last_used_at', 'revoked' ] );
	}

	public static function cli_revoke( $args, $assoc_args ): void {
		$id    = isset( $assoc_args['id'] ) ? absint( $assoc_args['id'] ) : 0;
		$token = isset( $assoc_args['token'] ) ? trim( (string) $assoc_args['token'] ) : '';

		if ( $id === 0 && $token === '' ) {
			\WP_CLI::error( 'Provide --id=<token-id> or --token=<plaintext-token>.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$where = $id > 0 ? [ 'id' => $id ] : [ 'token_hash' => hash( 'sha256', $token ) ];
		$updated = $wpdb->update( $table, [ 'revoked' => 1 ], $where );

		if ( ! $updated ) {
			\WP_CLI::error( 'No matching active token was revoked.' );
		}

		\WP_CLI::success( 'Token revoked.' );
	}
}
