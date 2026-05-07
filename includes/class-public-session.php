<?php
namespace ModelContextPolytechnic\Mcp;

class PublicSession {
	const OPTION_USER_ID = 'model_context_polytechnic_public_session_user_id';
	const LOCK_OPTION    = 'model_context_polytechnic_public_session_lock';
	const USER_LOGIN     = 'model_context_polytechnic_public_session';
	const USER_EMAIL     = 'model-context-polytechnic-public-session@example.invalid';

	private static string $lock_token = '';

	public static function init(): void {
		if ( Server::AUTH_MODE !== 'public' ) {
			return;
		}

		add_filter( 'determine_current_user', [ __CLASS__, 'maybe_use_public_user' ], 20 );
		add_filter( 'mcp_adapter_session_max_per_user', [ __CLASS__, 'session_limit' ] );
	}

	public static function maybe_use_public_user( $user_id ) {
		if ( ! empty( $user_id ) || ! self::is_public_mcp_request() ) {
			return $user_id;
		}

		self::acquire_session_lock();

		return self::install_user();
	}

	public static function install_user(): int {
		$stored_user_id = (int) get_option( self::OPTION_USER_ID );
		if ( $stored_user_id > 0 && get_user_by( 'id', $stored_user_id ) ) {
			return $stored_user_id;
		}

		$existing = get_user_by( 'login', self::USER_LOGIN );
		if ( $existing ) {
			update_option( self::OPTION_USER_ID, (int) $existing->ID, false );
			return (int) $existing->ID;
		}

		$user_id = wp_insert_user(
			[
				'user_login'   => self::USER_LOGIN,
				'user_email'   => self::USER_EMAIL,
				'user_pass'    => wp_generate_password( 64, true, true ),
				'display_name' => 'Model Context Polytechnic Public MCP Session',
				'description'  => 'Plugin-owned anonymous user used only to store public MCP HTTP sessions.',
				'role'         => get_role( 'subscriber' ) ? 'subscriber' : '',
			]
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		update_option( self::OPTION_USER_ID, (int) $user_id, false );
		return (int) $user_id;
	}

	public static function session_limit( int $max_sessions ): int {
		return max( $max_sessions, 256 );
	}

	public static function release_session_lock(): void {
		if ( self::$lock_token === '' ) {
			return;
		}

		$lock = self::decode_lock_value( get_option( self::LOCK_OPTION ) );
		if ( ( $lock['token'] ?? '' ) === self::$lock_token ) {
			delete_option( self::LOCK_OPTION );
		}

		self::$lock_token = '';
	}

	private static function acquire_session_lock(): void {
		if ( self::$lock_token !== '' ) {
			return;
		}

		$token    = wp_generate_uuid4();
		$deadline = microtime( true ) + 5.0;

		do {
			$now      = microtime( true );
			$existing = self::decode_lock_value( get_option( self::LOCK_OPTION ) );

			if ( ! empty( $existing['expires'] ) && (float) $existing['expires'] < $now ) {
				delete_option( self::LOCK_OPTION );
			}

			$value = wp_json_encode(
				[
					'token'   => $token,
					'expires' => $now + 10.0,
				]
			);

			if ( is_string( $value ) && add_option( self::LOCK_OPTION, $value, '', 'no' ) ) {
				self::$lock_token = $token;
				add_action( 'shutdown', [ __CLASS__, 'release_session_lock' ], 0 );
				return;
			}

			usleep( 25000 );
		} while ( microtime( true ) < $deadline );
	}

	private static function decode_lock_value( $value ): array {
		if ( ! is_string( $value ) || $value === '' ) {
			return [];
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	private static function is_public_mcp_request(): bool {
		$route = self::request_route();
		if ( $route === '' ) {
			return false;
		}

		$base_route   = '/' . Server::REST_NS . '/' . Server::REST_ROUTE;
		$course_route = '/' . Server::REST_NS . '/courses/';
		$vanity       = '/' . trim( Server::VANITY_PATH, '/' );

		return $route === $base_route
			|| str_starts_with( $route, $course_route )
			|| $route === $vanity
			|| str_starts_with( $route, $vanity . '/' );
	}

	private static function request_route(): string {
		if ( isset( $_GET['rest_route'] ) ) {
			return '/' . ltrim( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/' );
		}

		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( $request_uri === '' ) {
			return '';
		}

		$path = parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || $path === '' ) {
			return '';
		}

		$rest_prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
		$rest_pos    = strpos( $path, $rest_prefix . Server::REST_NS . '/' );
		if ( $rest_pos !== false ) {
			return substr( $path, $rest_pos + strlen( $rest_prefix ) - 1 );
		}

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home_path !== '' && $home_path !== '/' && str_starts_with( $path, rtrim( $home_path, '/' ) . '/' ) ) {
			$path = substr( $path, strlen( rtrim( $home_path, '/' ) ) );
		}

		return '/' . trim( $path, '/' );
	}
}
