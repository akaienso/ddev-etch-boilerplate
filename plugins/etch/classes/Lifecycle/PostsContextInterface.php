<?php
/**
 * Posts context interface for database operations.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Lifecycle;

/**
 * Interface for WordPress posts query operations.
 *
 * This abstraction enables dependency injection and testing of classes
 * that need to query WordPress posts without accessing the database.
 */
interface PostsContextInterface {

	/**
	 * Check if any post content matches a pattern.
	 *
	 * @param string $pattern SQL LIKE pattern to match against post_content.
	 * @return bool True if any posts match.
	 */
	public function has_posts_matching( string $pattern ): bool;

	/**
	 * Check if any post content matches all given patterns.
	 *
	 * @param array<string> $patterns SQL LIKE patterns that must all match.
	 * @return bool True if any posts match all patterns.
	 */
	public function has_posts_matching_all( array $patterns ): bool;

	/**
	 * Check if any post content matches any of the given pattern groups.
	 *
	 * Each group is an array of patterns that must all match (AND).
	 * Returns true if any group matches (OR between groups).
	 *
	 * @param array<array<string>> $pattern_groups Array of pattern groups.
	 * @return bool True if any posts match any group.
	 */
	public function has_posts_matching_any_group( array $pattern_groups ): bool;
}
