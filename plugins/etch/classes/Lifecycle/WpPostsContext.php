<?php
/**
 * WordPress posts context implementation.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Lifecycle;

/**
 * WordPress implementation of PostsContextInterface.
 *
 * Queries WordPress posts table for content matching.
 */
class WpPostsContext implements PostsContextInterface {

	/**
	 * Check if any post content matches a pattern.
	 *
	 * @param string $pattern SQL LIKE pattern to match against post_content.
	 * @return bool True if any posts match.
	 */
	public function has_posts_matching( string $pattern ): bool {
		return $this->has_posts_matching_all( array( $pattern ) );
	}

	/**
	 * Check if any post content matches all given patterns.
	 *
	 * @param array<string> $patterns SQL LIKE patterns that must all match.
	 * @return bool True if any posts match all patterns.
	 */
	public function has_posts_matching_all( array $patterns ): bool {
		if ( empty( $patterns ) ) {
			return false;
		}

		global $wpdb;

		$where_clauses = array();
		foreach ( $patterns as $pattern ) {
			$where_clauses[] = $wpdb->prepare( 'post_content LIKE %s', $pattern );
		}

		// Each clause is already prepared via $wpdb->prepare() above.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE " . implode( ' AND ', $where_clauses );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $query );

		return $count > 0;
	}

	/**
	 * Check if any post content matches any of the given pattern groups.
	 *
	 * Each group is an array of patterns that must all match (AND).
	 * Returns true if any group matches (OR between groups).
	 *
	 * @param array<array<string>> $pattern_groups Array of pattern groups.
	 * @return bool True if any posts match any group.
	 */
	public function has_posts_matching_any_group( array $pattern_groups ): bool {
		if ( empty( $pattern_groups ) ) {
			return false;
		}

		global $wpdb;

		$group_clauses = array();
		foreach ( $pattern_groups as $patterns ) {
			if ( empty( $patterns ) ) {
				continue;
			}

			$and_clauses = array();
			foreach ( $patterns as $pattern ) {
				$and_clauses[] = $wpdb->prepare( 'post_content LIKE %s', $pattern );
			}
			$group_clauses[] = '(' . implode( ' AND ', $and_clauses ) . ')';
		}

		if ( empty( $group_clauses ) ) {
			return false;
		}

		// Each clause is already prepared via $wpdb->prepare() above.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE " . implode( ' OR ', $group_clauses );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $query );

		return $count > 0;
	}
}
