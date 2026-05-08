<?php
namespace ModelContextPolytechnic\Mcp;

class FeedbackCli {
	public static function init(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'model-context-polytechnic feedback list', [ __CLASS__, 'cli_list' ] );
		\WP_CLI::add_command( 'model-context-polytechnic feedback summary', [ __CLASS__, 'cli_summary' ] );
		\WP_CLI::add_command( 'model-context-polytechnic feedback digest', [ __CLASS__, 'cli_digest' ] );
	}

	/**
	 * List raw learner feedback stored by public submit-feedback calls.
	 *
	 * ## OPTIONS
	 *
	 * [--course=<slug>]
	 * : Limit to a course slug. Defaults to all courses.
	 *
	 * [--type=<type>]
	 * : Limit to feedback type: confusing, helpful, bug, suggestion, missing_example.
	 *
	 * [--target-type=<type>]
	 * : Limit to a target type such as course, lesson, exercise, tool, resource, prompt, general.
	 *
	 * [--target-slug=<slug>]
	 * : Limit to a specific target slug.
	 *
	 * [--since=<period|date>]
	 * : Include feedback since a relative period like 30d or 12h, or a parseable date. Default: 30d.
	 *
	 * [--limit=<number>]
	 * : Maximum rows. Default: 50.
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml, count. Default: table.
	 */
	public static function cli_list( $args, $assoc_args ): void {
		$format = self::format_arg( $assoc_args, 'table' );
		$rows = self::feedback_rows( $assoc_args );
		$items = array_map(
			static function ( array $row ) use ( $format ): array {
				return self::format_feedback_row( $row, $format !== 'table' );
			},
			$rows
		);

		$fields = $format === 'table'
			? [ 'id', 'course', 'type', 'target', 'rating', 'comment', 'created_at' ]
			: [ 'id', 'course_slug', 'course_name', 'feedback_type', 'target_type', 'target_slug', 'rating', 'comment', 'suggested_fix', 'context', 'created_at' ];

		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}

	/**
	 * Show aggregate feedback counts for a course.
	 *
	 * ## OPTIONS
	 *
	 * [--course=<slug>]
	 * : Limit to a course slug. Defaults to all courses.
	 *
	 * [--since=<period|date>]
	 * : Include feedback since a relative period like 30d or 12h, or a parseable date. Default: 30d.
	 *
	 * [--limit=<number>]
	 * : Maximum rows per section. Default: 12.
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml, count. Default: table.
	 */
	public static function cli_summary( $args, $assoc_args ): void {
		$format = self::format_arg( $assoc_args, 'table' );
		$items = array_merge(
			self::feedback_type_rows( $assoc_args ),
			self::feedback_target_rows( $assoc_args )
		);

		\WP_CLI\Utils\format_items( $format, $items, [ 'group', 'course', 'key', 'count', 'average_rating' ] );
	}

	/**
	 * Print a Codex-friendly feedback digest with summary and recent raw notes.
	 *
	 * ## OPTIONS
	 *
	 * [--course=<slug>]
	 * : Limit to a course slug. Defaults to all courses.
	 *
	 * [--since=<period|date>]
	 * : Include feedback since a relative period like 30d or 12h, or a parseable date. Default: 30d.
	 *
	 * [--limit=<number>]
	 * : Maximum recent raw notes. Default: 10.
	 */
	public static function cli_digest( $args, $assoc_args ): void {
		$course = self::course_label_from_args( $assoc_args );
		$since = self::cutoff_from_args( $assoc_args );
		$limit = self::limit_arg( $assoc_args, 10, 100 );
		$rows = self::feedback_rows( $assoc_args + [ 'limit' => $limit ] );
		$summary = array_merge(
			self::feedback_type_rows( $assoc_args ),
			self::feedback_target_rows( $assoc_args )
		);

		\WP_CLI::line( '# Model Context Polytechnic Feedback Digest' );
		\WP_CLI::line( 'Course: ' . $course );
		\WP_CLI::line( 'Since: ' . $since );
		\WP_CLI::line( '' );
		\WP_CLI::line( '## Summary Signals' );
		if ( $summary ) {
			foreach ( $summary as $item ) {
				\WP_CLI::line(
					sprintf(
						'- [%s] %s: %d signal(s), average rating %s',
						$item['group'],
						$item['key'],
						(int) $item['count'],
						$item['average_rating'] === null ? 'n/a' : $item['average_rating']
					)
				);
			}
		} else {
			\WP_CLI::line( '- No feedback signals in this window yet.' );
		}

		\WP_CLI::line( '' );
		\WP_CLI::line( '## Recent Raw Notes' );
		if ( ! $rows ) {
			\WP_CLI::line( '- No raw feedback rows in this window yet.' );
			return;
		}

		foreach ( $rows as $row ) {
			$item = self::format_feedback_row( $row, true );
			\WP_CLI::line( sprintf( '- #%d %s %s rating=%s', (int) $item['id'], $item['feedback_type'], self::target_label( $item['target_type'], $item['target_slug'] ), $item['rating'] ?? 'n/a' ) );
			\WP_CLI::line( '  Comment: ' . self::one_line( $item['comment'], 500 ) );
			if ( $item['suggested_fix'] !== '' ) {
				\WP_CLI::line( '  Suggested fix: ' . self::one_line( $item['suggested_fix'], 500 ) );
			}
		}
	}

	private static function feedback_rows( array $assoc_args ): array {
		global $wpdb;

		$feedback = $wpdb->prefix . Learning::FEEDBACK_TABLE;
		$courses = $wpdb->prefix . Registry::COURSES_TABLE;
		$where = [ 'f.created_at >= %s' ];
		$args = [ self::cutoff_from_args( $assoc_args ) ];

		$course_slug = self::slug_arg( $assoc_args, 'course' );
		if ( $course_slug !== '' ) {
			$where[] = 'c.slug = %s';
			$args[] = $course_slug;
		}

		$type = self::slug_arg( $assoc_args, 'type' );
		if ( $type !== '' ) {
			$where[] = 'f.feedback_type = %s';
			$args[] = $type;
		}

		$target_type = self::slug_arg( $assoc_args, 'target-type' );
		if ( $target_type !== '' ) {
			$where[] = 'f.target_type = %s';
			$args[] = $target_type;
		}

		$target_slug = self::slug_arg( $assoc_args, 'target-slug' );
		if ( $target_slug !== '' ) {
			$where[] = 'f.target_slug = %s';
			$args[] = $target_slug;
		}

		$args[] = self::limit_arg( $assoc_args, 50, 500 );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.id, c.slug AS course_slug, c.name AS course_name, f.feedback_type, f.target_type, COALESCE(f.target_slug, '') AS target_slug, f.rating, f.comment, f.suggested_fix, f.context, f.created_at
				FROM $feedback f
				INNER JOIN $courses c ON c.id = f.course_id
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY f.created_at DESC, f.id DESC
				LIMIT %d",
				$args
			),
			ARRAY_A
		) ?: [];
	}

	private static function feedback_type_rows( array $assoc_args ): array {
		return self::aggregate_rows(
			$assoc_args,
			'type',
			'f.feedback_type',
			'f.feedback_type'
		);
	}

	private static function feedback_target_rows( array $assoc_args ): array {
		return self::aggregate_rows(
			$assoc_args,
			'target',
			"CONCAT(f.target_type, ':', COALESCE(f.target_slug, ''))",
			'f.target_type, f.target_slug'
		);
	}

	private static function aggregate_rows( array $assoc_args, string $group, string $select_key, string $group_by ): array {
		global $wpdb;

		$feedback = $wpdb->prefix . Learning::FEEDBACK_TABLE;
		$courses = $wpdb->prefix . Registry::COURSES_TABLE;
		$where = [ 'f.created_at >= %s' ];
		$args = [ self::cutoff_from_args( $assoc_args ) ];

		$course_slug = self::slug_arg( $assoc_args, 'course' );
		if ( $course_slug !== '' ) {
			$where[] = 'c.slug = %s';
			$args[] = $course_slug;
		}

		$args[] = self::limit_arg( $assoc_args, 12, 100 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.slug AS course_slug, $select_key AS item_key, COUNT(*) AS count, AVG(f.rating) AS average_rating
				FROM $feedback f
				INNER JOIN $courses c ON c.id = f.course_id
				WHERE " . implode( ' AND ', $where ) . "
				GROUP BY c.slug, $group_by
				ORDER BY count DESC, average_rating ASC, item_key ASC
				LIMIT %d",
				$args
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static function ( array $row ) use ( $group ): array {
				return [
					'group'          => $group,
					'course'         => $row['course_slug'],
					'key'            => $row['item_key'],
					'count'          => (int) $row['count'],
					'average_rating' => is_null( $row['average_rating'] ) ? null : round( (float) $row['average_rating'], 2 ),
				];
			},
			$rows
		);
	}

	private static function format_feedback_row( array $row, bool $full ): array {
		$target = self::target_label( (string) $row['target_type'], (string) $row['target_slug'] );

		if ( $full ) {
			return [
				'id'            => (int) $row['id'],
				'course_slug'   => $row['course_slug'],
				'course_name'   => $row['course_name'],
				'feedback_type' => $row['feedback_type'],
				'target_type'   => $row['target_type'],
				'target_slug'   => $row['target_slug'],
				'rating'        => is_null( $row['rating'] ) ? null : (int) $row['rating'],
				'comment'       => (string) $row['comment'],
				'suggested_fix' => (string) $row['suggested_fix'],
				'context'       => (string) $row['context'],
				'created_at'    => $row['created_at'],
			];
		}

		return [
			'id'         => (int) $row['id'],
			'course'     => $row['course_slug'],
			'type'       => $row['feedback_type'],
			'target'     => $target,
			'rating'     => is_null( $row['rating'] ) ? '' : (int) $row['rating'],
			'comment'    => self::one_line( (string) $row['comment'], 100 ),
			'created_at' => $row['created_at'],
		];
	}

	private static function cutoff_from_args( array $assoc_args ): string {
		$since = trim( (string) ( $assoc_args['since'] ?? '30d' ) );
		if ( preg_match( '/^(\d+)([dh])$/', $since, $matches ) ) {
			$amount = max( 1, (int) $matches[1] );
			$seconds = $matches[2] === 'h' ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
			return gmdate( 'Y-m-d H:i:s', time() - ( $amount * $seconds ) );
		}

		$timestamp = strtotime( $since );
		if ( $timestamp === false ) {
			\WP_CLI::error( 'Invalid --since value. Use a period like 30d or 12h, or a parseable date.' );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private static function limit_arg( array $assoc_args, int $default, int $max ): int {
		$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : $default;
		return max( 1, min( $max, $limit ) );
	}

	private static function slug_arg( array $assoc_args, string $key ): string {
		return sanitize_key( (string) ( $assoc_args[ $key ] ?? '' ) );
	}

	private static function format_arg( array $assoc_args, string $default ): string {
		$format = sanitize_key( (string) ( $assoc_args['format'] ?? $default ) );
		return $format !== '' ? $format : $default;
	}

	private static function course_label_from_args( array $assoc_args ): string {
		$course = self::slug_arg( $assoc_args, 'course' );
		return $course !== '' ? $course : 'all courses';
	}

	private static function target_label( string $target_type, string $target_slug ): string {
		$label = trim( $target_type . ':' . $target_slug, ':' );
		return $label !== '' ? $label : 'general';
	}

	private static function one_line( string $text, int $max ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		if ( strlen( $text ) > $max ) {
			return substr( $text, 0, max( 0, $max - 3 ) ) . '...';
		}

		return $text;
	}
}
