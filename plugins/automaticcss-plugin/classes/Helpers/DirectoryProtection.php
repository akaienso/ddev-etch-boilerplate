<?php
/**
 * DirectoryProtection - Blocks HTTP access to sensitive files.
 *
 * @package Automatic_CSS\Helpers
 */

namespace Automatic_CSS\Helpers;

/**
 * DirectoryProtection class.
 *
 * Creates .htaccess and index.php files to block HTTP access to log files.
 * Only blocks .log files - CSS and other assets remain accessible.
 * Designed to be called on every request to ensure protection files exist.
 */
class DirectoryProtection {

	/**
	 * Content for .htaccess file to deny access to .log files only.
	 */
	private const HTACCESS_CONTENT = <<<'HTACCESS'
# Block direct access to log files
<FilesMatch "\.log$">
	<IfModule mod_authz_core.c>
		Require all denied
	</IfModule>
	<IfModule !mod_authz_core.c>
		Order deny,allow
		Deny from all
	</IfModule>
</FilesMatch>
HTACCESS;

	/**
	 * Content for index.php file (WordPress standard silence marker).
	 */
	private const INDEX_CONTENT = <<<'PHP'
<?php
// Silence is golden.
PHP;

	/**
	 * Protect a directory by creating .htaccess and index.php files.
	 *
	 * Creates protection files only if they don't exist.
	 * Safe to call on every request.
	 *
	 * @param string $directory Absolute path to the directory to protect.
	 * @return bool True if protection is in place (files exist or were created), false on failure.
	 */
	public function protect( string $directory ): bool {
		if ( ! is_dir( $directory ) ) {
			return false;
		}

		$htaccess_result = $this->ensure_htaccess( $directory );
		$index_result = $this->ensure_index_php( $directory );

		return $htaccess_result && $index_result;
	}

	/**
	 * Ensure .htaccess file exists in the directory.
	 *
	 * Only creates the file if it doesn't exist.
	 *
	 * @param string $directory Absolute path to the directory.
	 * @return bool True if file exists or was created, false on failure.
	 */
	private function ensure_htaccess( string $directory ): bool {
		$htaccess_path = trailingslashit( $directory ) . '.htaccess';

		if ( file_exists( $htaccess_path ) ) {
			return true;
		}

		return $this->write_file( $htaccess_path, self::HTACCESS_CONTENT );
	}

	/**
	 * Ensure index.php file exists in the directory.
	 *
	 * Only creates the file if it doesn't exist.
	 *
	 * @param string $directory Absolute path to the directory.
	 * @return bool True if file exists or was created, false on failure.
	 */
	private function ensure_index_php( string $directory ): bool {
		$index_path = trailingslashit( $directory ) . 'index.php';

		if ( file_exists( $index_path ) ) {
			return true;
		}

		return $this->write_file( $index_path, self::INDEX_CONTENT );
	}

	/**
	 * Write content to a file.
	 *
	 * @param string $path    Absolute path to the file.
	 * @param string $content Content to write.
	 * @return bool True on success, false on failure.
	 */
	private function write_file( string $path, string $content ): bool {
		$result = file_put_contents( $path, $content, LOCK_EX );
		return false !== $result;
	}
}
