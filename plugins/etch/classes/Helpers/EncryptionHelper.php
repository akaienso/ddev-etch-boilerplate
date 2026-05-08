<?php
/**
 * Encryption helper file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

/**
 * Handles symmetric encryption and decryption of strings.
 *
 * Uses PHP's OpenSSL extension (AES-256-CBC) with a key derived
 * from wp-config.php constants, making it independent of WordPress
 * core or plugin updates.
 */
final class EncryptionHelper {

	/**
	 * Cipher algorithm.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext The string to encrypt.
	 * @return string Base64-encoded ciphertext containing the IV and encrypted data.
	 * @throws \RuntimeException If can not resolve the iv lenght.
	 */
	public static function encrypt( string $plaintext ): string {
		$key = self::get_key();
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length ) {
			throw new \RuntimeException( 'Invalid cipher algorithm.' );
		}

		$iv  = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a previously encrypted string.
	 *
	 * @param string $ciphertext Base64-encoded ciphertext from encrypt().
	 * @return string|false The original plaintext, or false on failure.
	 * @throws \RuntimeException If can not resolve the iv lenght.
	 */
	public static function decrypt( string $ciphertext ): string|false {
		$key  = self::get_key();
		$data = base64_decode( $ciphertext, true );

		if ( false === $data ) {
			return false;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length ) {
			throw new \RuntimeException( 'Invalid cipher algorithm.' );
		}
		$iv        = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		if ( strlen( $iv ) !== $iv_length || '' === $encrypted ) {
			return false;
		}

		return openssl_decrypt( $encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Derive the encryption key from wp-config.php constants.
	 *
	 * Uses AUTH_KEY and SECURE_AUTH_KEY which are defined in wp-config.php
	 * and remain stable across WordPress and plugin updates.
	 *
	 * @return string A 32-byte binary key suitable for AES-256.
	 */
	private static function get_key(): string {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'etch-fallback-key';
		$salt .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';

		return hash( 'sha256', $salt, true );
	}
}
