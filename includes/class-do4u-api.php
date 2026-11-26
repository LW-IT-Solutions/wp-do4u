<?php
/**
 * HTTP client for the do4u.eu API.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Anything that went wrong while talking to do4u.eu or while preparing a request. */
class Do4u_Error extends RuntimeException {

	/**
	 * The message as plain text. Messages are escaped where they are thrown; this is for places that escape
	 * for their own output (esc_html() in a notice) or show text only (JSON for the dialog).
	 *
	 * @return string
	 */
	public function plain(): string {
		return html_entity_decode( $this->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}


	/** Error code of the API, or an own code such as "unreachable". @var string */
	public string $api_code;

	/** HTTP status of the answer, 0 without an answer. @var int */
	public int $status;

	/**
	 * @param string $message  Text for the user. Never contains the API key.
	 * @param string $api_code Code for program decisions.
	 * @param int    $status   HTTP status.
	 */
	public function __construct( string $message, string $api_code = '', int $status = 0 ) {
		parent::__construct( $message );
		$this->api_code = $api_code;
		$this->status   = $status;
	}
}

/** The user lacks a right for an item; Ajax answers with 403. */
class Do4u_Denied extends Do4u_Error {
}

/**
 * Requests to the API of do4u.eu (path /api/v1/).
 */
final class Do4u_Api {

	/** Host of the service. */
	const HOST = 'do4u.eu';

	/** Default API address. */
	const BASE = 'https://' . self::HOST . '/api/v1/';

	/**
	 * Checks an API address. Only https addresses on do4u.eu or a subdomain with the path /api/v1 pass,
	 * so a wrong constant in wp-config.php can never send the API key anywhere else.
	 *
	 * @param string $base Address.
	 * @return string Normalized address with a trailing slash.
	 * @throws Do4u_Error When the address is not allowed.
	 */
	public static function base_from( string $base ): string {
		if ( ! preg_match( '#^https://((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*do4u\.eu)/api/v1/?$#iD', $base, $match ) ) {
			throw new Do4u_Error( esc_html__( 'DO4U_API_BASE must be an https address on do4u.eu ending in /api/v1/.', 'do4u' ), 'bad_base' );
		}
		return 'https://' . strtolower( $match[1] ) . '/api/v1/';
	}

	/**
	 * API address: the default, or the constant DO4U_API_BASE from wp-config.php.
	 *
	 * @return string
	 */
	public static function base(): string {
		return self::base_from( defined( 'DO4U_API_BASE' ) ? (string) DO4U_API_BASE : self::BASE );
	}

	/**
	 * Web address that belongs to the API address (for the widget loader and for links).
	 *
	 * @return string Without a trailing slash.
	 */
	public static function site(): string {
		return (string) preg_replace( '#/api/v1/$#', '', self::base() );
	}

	/**
	 * Sends one request.
	 *
	 * @param string      $method  GET, POST or DELETE.
	 * @param string      $path    Path below /api/v1/.
	 * @param array|null  $body    JSON body.
	 * @param int         $timeout Seconds.
	 * @param string|null $key     API key; null reads the saved key.
	 * @param array       $headers More headers.
	 * @return array Decoded answer.
	 * @throws Do4u_Error On a network error, an error status or an invalid answer.
	 */
	public static function request( string $method, string $path, ?array $body = null, int $timeout = 15, ?string $key = null, array $headers = array() ): array {
		[ $status, $text ] = self::send( $method, $path, $body, $timeout, $key, $headers );
		$data = json_decode( $text, true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$code = is_array( $data ) ? sanitize_key( (string) ( $data['error']['code'] ?? 'error' ) ) : 'invalid_response';
			$text = is_array( $data ) ? sanitize_text_field( (string) ( $data['error']['message'] ?? '' ) ) : '';
			throw new Do4u_Error( esc_html( '' !== $text ? $text : __( 'do4u.eu sent an unexpected answer.', 'do4u' ) ), esc_html( $code ), absint( $status ) );
		}
		return $data;
	}

	/**
	 * Sends one request and returns status and body without judging them. The only place in the plugin that talks to
	 * do4u.eu; request() and the shop tools (Do4u_Shop_Api, which also reads CSV) build on it.
	 *
	 * @param string      $method  GET, POST or DELETE.
	 * @param string      $path    Path below /api/v1/.
	 * @param array|null  $body    JSON body.
	 * @param int         $timeout Seconds.
	 * @param string|null $key     API key; null reads the saved key.
	 * @param array       $headers More headers.
	 * @param int         $limit   Largest answer that is read, in bytes.
	 * @return array HTTP status and body.
	 * @throws Do4u_Error Without a key or on a network error.
	 */
	public static function send( string $method, string $path, ?array $body, int $timeout, ?string $key = null, array $headers = array(), int $limit = 2097152 ): array {
		$key = $key ?? Do4u_Secret::get();
		if ( '' === $key ) {
			throw new Do4u_Error( esc_html__( 'No API key is saved yet.', 'do4u' ), 'no_key' );
		}
		$response = wp_remote_request(
			self::base() . $path,
			array(
				'method'              => $method,
				'timeout'             => $timeout,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => $limit,
				// Own user agent: the WordPress default would send the address of the website with every request.
				'user-agent'          => 'do4u-WordPress/' . DO4U_WP_VERSION,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				) + $headers,
				'body'                => null === $body ? null : wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new Do4u_Error( esc_html__( 'do4u.eu could not be reached. Please try again in a moment.', 'do4u' ), 'unreachable' );
		}
		return array( (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
	}
}
