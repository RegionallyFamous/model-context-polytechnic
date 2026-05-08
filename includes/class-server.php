<?php
namespace ModelContextPolytechnic\Mcp;

use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;
use WP\McpSchema\Common\Protocol\DTO\InitializeResult;

class Server {
	const SERVER_ID        = 'model-context-polytechnic';
	const REST_NS          = 'model_context_polytechnic';
	const REST_ROUTE       = 'mcp';
	const AUTH_MODE        = 'public'; // Transport auth: 'public' | 'bearer'.
	const WRITE_AUTH_MODE  = 'bearer';
	const ABILITY_PREFIX   = 'model-context-polytechnic';
	const FILTER_PREFIX    = 'model_context_polytechnic_mcp';
	const CATEGORY         = 'model-context-polytechnic';
	const PLUGIN_NAME      = 'Model Context Polytechnic';
	const DESCRIPTION      = 'A public MCP learning and diagnostics server for WordPress.';
	const VANITY_PATH      = 'mcp';
	const REQUIRED_WP      = '6.9';
	const REQUIRED_PHP     = '8.1';
	const SERVER_VERSION   = '1.0.11';
	const REMOTE_PROXY     = '@automattic/mcp-wordpress-remote@latest';
	const VOICE_NAME       = 'The Old Polytechnic';
	const AUTHORING_TOOLS_ENABLED = false;

	public static function init(): void {
		if ( ! class_exists( McpAdapter::class ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'missing_adapter_notice' ] );
			return;
		}

		McpAdapter::instance();
		add_action( 'mcp_adapter_init', [ __CLASS__, 'register_servers' ] );
		add_filter( 'mcp_adapter_initialize_response', [ __CLASS__, 'filter_initialize_response' ], 10, 2 );
	}

	public static function register_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => self::PLUGIN_NAME,
				'description' => __( 'Public MCP learning abilities for Model Context Polytechnic.', 'model-context-polytechnic' ),
			]
		);
	}

	public static function register_servers( $adapter ): void {
		self::ensure_abilities_registered();

		$tools     = self::tools();
		$resources = self::resources();
		$prompts   = self::prompts();

		$permission_callback = self::AUTH_MODE === 'public'
			? '__return_true'
			: [ Auth::class, 'check_bearer' ];

		$adapter->create_server(
			self::SERVER_ID,
			self::REST_NS,
			self::REST_ROUTE,
			self::PLUGIN_NAME,
			self::DESCRIPTION,
			self::SERVER_VERSION,
			[ HttpTransport::class ],
			ErrorLogMcpErrorHandler::class,
			NullMcpObservabilityHandler::class,
			$tools,
			$resources,
			$prompts,
			$permission_callback
		);

		foreach ( Registry::published_courses() as $course ) {
			$components = Registry::course_components( (int) $course['id'], $course['slug'] );
			if ( class_exists( Learning::class ) ) {
				$learning_components = Learning::course_components( (int) $course['id'], $course['slug'] );
				$components = [
					'tools'     => array_merge( $components['tools'], $learning_components['tools'] ),
					'resources' => array_merge( $components['resources'], $learning_components['resources'] ),
					'prompts'   => array_merge( $components['prompts'], $learning_components['prompts'] ),
				];
			}

			$course_name = self::canonical_brand_text( (string) $course['name'] );
			$adapter->create_server(
				Registry::course_server_id( $course['slug'] ),
				self::REST_NS,
				Registry::course_route( $course['slug'] ),
				$course_name,
				Registry::course_instructions( $course ),
				self::SERVER_VERSION,
				[ HttpTransport::class ],
				ErrorLogMcpErrorHandler::class,
				NullMcpObservabilityHandler::class,
				$components['tools'],
				$components['resources'],
				$components['prompts'],
				'__return_true'
			);
		}
	}

	public static function tools(): array {
		return apply_filters( self::FILTER_PREFIX . '_tools', [] );
	}

	private static function ensure_abilities_registered(): void {
		if ( class_exists( 'WP_Abilities_Registry' ) ) {
			\WP_Abilities_Registry::get_instance();
		}
	}

	public static function resources(): array {
		return apply_filters( self::FILTER_PREFIX . '_resources', [] );
	}

	public static function prompts(): array {
		return apply_filters( self::FILTER_PREFIX . '_prompts', [] );
	}

	public static function rest_endpoint(): string {
		return rest_url( self::REST_NS . '/' . self::REST_ROUTE );
	}

	public static function vanity_endpoint( string $site_url = '' ): string {
		$base = $site_url !== '' ? $site_url : home_url();
		return trailingslashit( self::normalize_site_url( $base ) ) . self::VANITY_PATH;
	}

	public static function normalize_site_url( string $site_url ): string {
		$site_url = trim( $site_url );

		if ( $site_url === '' ) {
			return home_url();
		}

		if ( ! preg_match( '#^https?://#i', $site_url ) ) {
			$site_url = 'https://' . $site_url;
		}

		return untrailingslashit( esc_url_raw( $site_url ) );
	}

	public static function manifest(): array {
		return [
			'plugin'      => [
				'name'        => self::PLUGIN_NAME,
				'description' => self::DESCRIPTION,
				'version'     => defined( 'MODEL_CONTEXT_POLYTECHNIC_VERSION' ) ? MODEL_CONTEXT_POLYTECHNIC_VERSION : self::SERVER_VERSION,
				'requires'    => [
					'wordpress' => self::REQUIRED_WP,
					'php'       => self::REQUIRED_PHP,
				],
			],
			'server'      => [
				'id'             => self::SERVER_ID,
				'name'           => self::PLUGIN_NAME,
				'description'    => self::DESCRIPTION,
				'version'        => self::SERVER_VERSION,
				'rest_namespace' => self::REST_NS,
				'rest_route'     => self::REST_ROUTE,
				'auth_mode'      => self::AUTH_MODE,
				'write_auth_mode' => self::WRITE_AUTH_MODE,
				'authoring_tools_enabled' => self::authoring_tools_enabled(),
				'public_http_sessions' => [
					'enabled' => self::AUTH_MODE === 'public',
					'storage' => 'Plugin-owned anonymous WordPress user meta for MCP HTTP session IDs.',
					'write_access' => 'Public session user cannot authorize private write tools.',
				],
			],
			'urls'        => [
				'rest'   => self::rest_endpoint(),
				'vanity' => self::vanity_endpoint(),
			],
			'abilities'   => [
				'tools'     => self::tools(),
				'resources' => self::resources(),
				'prompts'   => self::prompts(),
			],
			'courses'     => class_exists( Registry::class ) ? Registry::course_catalog() : [],
			'voice'       => self::voice_profile(),
			'instructions' => self::server_instructions(),
			'llm_interface' => self::llm_interface_contract(),
			'adapter'     => [
				'available' => class_exists( McpAdapter::class ),
				'class'     => McpAdapter::class,
				'version'   => defined( McpAdapter::class . '::VERSION' ) ? McpAdapter::VERSION : null,
				'proxy'     => self::REMOTE_PROXY,
			],
			'wordpress'   => [
				'version' => get_bloginfo( 'version' ),
			],
			'annotations' => [
				'readOnlyHint'    => true,
				'destructiveHint' => false,
				'idempotentHint'  => true,
				'openWorldHint'   => false,
			],
		];
	}

	public static function voice_profile(): array {
		return [
			'name'       => self::VOICE_NAME,
			'premise'    => 'Model Context Polytechnic is a long-established institution where MCP servers are treated as courses of study. WordPress is the campus infrastructure, not the subject matter.',
			'tone'       => [
				'venerable',
				'precise',
				'warmly professorial',
				'lightly witty',
				'never corporate',
			],
			'style'      => [
				'Use confident, compact explanations.',
				'Prefer course, syllabus, seminar, department, faculty, archive, and registrar language when it clarifies the product.',
				'Make setup feel ceremonial but not fussy.',
				'Keep technical steps exact and plain.',
			],
			'avoid'      => [
				'Startup hype.',
				'Fake Latin mottoes.',
				'Overexplaining WordPress when the server subject is not WordPress.',
				'Jokes that get in the way of instructions.',
			],
		];
	}

	public static function mcp_tool_name( string $ability_name ): string {
		$name = str_replace( '/', '-', trim( $ability_name ) );
		$name = (string) preg_replace( '/[^a-zA-Z0-9_.-]/', '-', $name );
		$name = (string) preg_replace( '/-+/', '-', $name );
		$name = trim( $name, '-_' );

		if ( strlen( $name ) > 128 ) {
			$name = substr( $name, 0, 115 ) . '-' . substr( md5( $ability_name ), 0, 12 );
		}

		return $name;
	}

	public static function server_instructions(): string {
		return implode(
			"\n",
			[
				self::PLUGIN_NAME . ' is a public MCP learning server hosted on WordPress.',
				'Treat WordPress as the campus runtime: routing, storage, lifecycle, cron, updates, and credentials. Do not assume the server subject matter is WordPress.',
				'The product metaphor is an old polytechnic: each MCP server is a course of study; abilities are coursework; setup is enrollment and syllabus design.',
				'Courses can include modules, lessons, exercises, rubrics, feedback, anonymous enrollment keys, learning memory, and completion certificates. This does not train model weights; it schools the active session through structured context and practice loops.',
				'Every public course tool call leaves privacy-safe improvement telemetry so the course can learn which tools, lessons, and exercises create friction. Use submit-feedback for explicit learner observations and get-course-improvement-signals before revising course material.',
				'LLM operating rule: first orient, then call begin-course on the relevant course endpoint, preserve enrollment_key, call the exact autopilot tool returned in tool_calls or llm_contract.autopilot_tool to continue automatically without lesson-by-lesson user prompts, use search-course for just-in-time retrieval, call get-certificate when the course reports complete, and call submit-feedback when something is confusing or helpful.',
				'Voice: venerable, precise, warmly professorial, lightly witty, and never corporate. Use institutional language when helpful, but keep operational instructions clear.',
				'Public learners do not need credentials. Course authoring tools are hidden from the default MCP surface unless explicitly enabled by the host site.',
				'Never expose stored secrets. When configuration requires a secret, tell the operator where to place it in their MCP client config.',
			]
		);
	}

	public static function authoring_tools_enabled(): bool {
		return (bool) apply_filters( 'model_context_polytechnic_authoring_tools_enabled', self::AUTHORING_TOOLS_ENABLED );
	}

	public static function llm_interface_contract(): array {
		return [
			'purpose'              => 'Make MCP-hosted coursework easy for LLMs to discover, start, retrieve, practice, remember, and recover from errors without WordPress credentials.',
			'first_call'           => self::mcp_tool_name( Server::ABILITY_PREFIX . '/orient' ),
			'course_first_call'    => 'model-context-polytechnic/{course-slug}-begin-course',
			'stable_handles'       => [
				'course_slug'     => 'Stable public course identifier used in endpoint paths and ability names.',
				'enrollment_key'  => 'Anonymous learner handle returned by begin-course and repeated by memory-related responses. Store it in the client conversation or project memory.',
				'lesson_slug'     => 'Stable lesson identifier accepted by get-lesson.',
				'exercise_slug'   => 'Stable exercise identifier accepted by get-exercise and attempt-exercise.',
				'resource_name'   => 'Stable MCP resource ability identifier for public references.',
			],
			'preferred_loop'        => [
				'Call orient on the registrar if you are not sure where to start.',
					'Call connection-playbook if the MCP client has trouble connecting or discovering tools.',
					'Connect to a course endpoint or generate client config for course_slug.',
					'Call begin-course once per learner and preserve enrollment_key.',
					'Call the exact autopilot tool returned by begin-course with enrollment_key and mode=full_course when the user wants to take the course.',
					'Use module_batch and next_cursor only when the client needs smaller packets.',
				'Call get-next-work with enrollment_key when you need to check current completion state.',
				'Call get-lesson, then get-exercise.',
				'Attempt the exercise with enrollment_key.',
				'When get-next-work reports complete, call get-certificate with enrollment_key.',
				'Call get-learning-memory at the start of later sessions.',
				'Use search-course before answering topical WordPress plugin questions.',
				'Call submit-feedback when a lesson, exercise, or tool response is confusing, helpful, stale, or missing an example.',
				'Call get-course-improvement-signals before proposing course revisions.',
			],
			'response_conventions'  => [
				'Prefer fields named tool_calls, next_actions, note, warnings, and memory_instructions over prose-only guidance.',
				'Treat WP_Error responses as recoverable tool-call feedback.',
				'Do not ask the human for WordPress credentials for public course learning.',
				'Do not invent enrollment_key values; use the one returned by the server.',
				'When course_improvement appears in a response, treat it as live institutional memory about what prior learners found hard.',
			],
			'public_boundaries'     => [
				'Public course tools may read course material and store anonymous exercise attempts, feedback, and privacy-safe usage telemetry.',
				'Authoring tools are hidden by default and require write access if enabled.',
				'Enrollment keys are not passwords, but whoever has one can retrieve that anonymous learner progress.',
				'Feedback and telemetry are improvement signals, not automatic course edits.',
			],
			'privacy_and_retention' => [
				'Exercise attempts are public-learning data keyed by a hashed anonymous enrollment key.',
				'Feedback comments are stored for the site owner; public improvement summaries return aggregate signals rather than raw comments.',
				'Tool telemetry stores target handles, status, duration, and input fingerprints without plaintext enrollment keys.',
				'Plaintext enrollment keys are returned to the client and are not stored by the plugin.',
				'Old anonymous attempts, feedback, and telemetry events are pruned by scheduled cleanup. The default retention window is 180 days and can be changed with the model_context_polytechnic_learning_retention_days filter.',
			],
		];
	}

	public static function filter_initialize_response( $result, $server ) {
		if (
			! is_object( $server )
			|| ! method_exists( $server, 'get_server_id' )
			|| ! method_exists( $result, 'toArray' )
		) {
			return $result;
		}

		$data      = $result->toArray();
		$server_id = $server->get_server_id();

		if ( $server_id === self::SERVER_ID ) {
			$data['instructions'] = self::server_instructions();
			return InitializeResult::fromArray( $data );
		}

		$prefix = self::SERVER_ID . '-course-';
		if ( strpos( $server_id, $prefix ) !== 0 ) {
			return $result;
		}

		$course = Registry::course_by_slug( substr( $server_id, strlen( $prefix ) ) );
		if ( ! $course ) {
			return $result;
		}

		$data['instructions'] = Registry::course_instructions( $course );
		if ( isset( $data['serverInfo'] ) && is_array( $data['serverInfo'] ) ) {
			$data['serverInfo']['name'] = self::canonical_brand_text( (string) $course['name'] );
		}

		return InitializeResult::fromArray( $data );
	}

	public static function canonical_brand_text( string $text ): string {
		return (string) preg_replace( '/\bwordpress\b/i', 'WordPress', $text );
	}

	public static function missing_adapter_notice(): void {
		echo '<div class="notice notice-error"><p><strong>Model Context Polytechnic</strong>: requires WordPress 6.9+ and the <code>wordpress/mcp-adapter</code> Composer package.</p></div>';
	}
}
