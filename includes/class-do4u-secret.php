<?php
/**
 * Encrypted storage of the API key.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the API key encrypted with the WordPress salts. Same format as version 0.x, so a key saved
 * there keeps working after the update.
 */
final class Do4u_Secret {

	const OPTION = 'do4u_wp_key_cipher';

	/**
	 * Whether a key is stored. Does not decrypt, so it also works after a change of the salts.
	 *
	 * @return bool
	 */
	public static function has(): bool {
		return '' !== (string) get_option( self::OPTION, '' );
	}

	/**
	 * Whether a text looks like an API key.
	 *
	 * @param string $key Text.
	 * @return bool
	 */
	public static function valid_form( string $key ): bool {
		return (bool) preg_match( '/^d4u_[a-f0-9]{40}$/D', $key );
	}

	/** @return string 32 bytes derived from the salts. */
	private static function box_key(): string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			throw new Do4u_Error( esc_html__( 'The PHP extension Sodium is required to store the API key encrypted.', 'do4u' ), 'no_sodium' );
		}
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Reads the key.
	 *
	 * @return string Key, or an empty string if none is stored.
	 * @throws Do4u_Error When the stored value cannot be decrypted.
	 */
	public static function get(): string {
		$value = (string) get_option( self::OPTION, '' );
		if ( '' === $value ) {
			return '';
		}
		$box = self::box_key();
		try {
			// Standard Base64 with padding, the format stored since version 0.x.
			$raw = sodium_base642bin( $value, SODIUM_BASE64_VARIANT_ORIGINAL );
		} catch ( SodiumException $error ) {
			$raw = '';
		}
		if ( strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			throw new Do4u_Error( esc_html__( 'Please save the API key again.', 'do4u' ), 'bad_cipher' );
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $box );
		if ( false === $plain ) {
			throw new Do4u_Error( esc_html__( 'The WordPress salts have changed. Please save the API key again.', 'do4u' ), 'bad_cipher' );
		}
		return $plain;
	}

	/**
	 * Stores a key.
	 *
	 * @param string $key Key.
	 * @throws Do4u_Denied When the user is not an administrator.
	 * @throws Do4u_Error  When the key has the wrong form.
	 */
	public static function set( string $key ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new Do4u_Denied( esc_html__( 'Only administrators can change the API key.', 'do4u' ), 'forbidden', 403 );
		}
		if ( ! self::valid_form( $key ) ) {
			throw new Do4u_Error( esc_html__( 'This is not a valid API key. A do4u.eu API key starts with d4u_.', 'do4u' ), 'bad_key' );
		}
		$box   = self::box_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		update_option( self::OPTION, sodium_bin2base64( $nonce . sodium_crypto_secretbox( $key, $nonce, $box ), SODIUM_BASE64_VARIANT_ORIGINAL ), false );
	}

	/**
	 * Removes the key.
	 *
	 * @throws Do4u_Denied When the user is not an administrator.
	 */
	public static function clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new Do4u_Denied( esc_html__( 'Only administrators can change the API key.', 'do4u' ), 'forbidden', 403 );
		}
		delete_option( self::OPTION );
	}
}
