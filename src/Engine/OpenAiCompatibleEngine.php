<?php
/**
 * OpenAI-compatible translation engine adapter.
 *
 * Talks to the official OpenPoly gateway (or any custom OpenAI-compatible
 * translation endpoint) using WordPress HTTP API.
 *
 * Implements §7.1–7.5 of the engine interface contract with
 * idempotency keys, error-code handling, and usage tracking.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Sends segment batches to an OpenAI-compatible translation endpoint.
 *
 * @since 1.0.0-dev
 */
final class OpenAiCompatibleEngine implements EngineGateway {

	/**
	 * Gateway base URL.
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Customer token / API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model name (ignored when using the official gateway).
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Whether this is a custom endpoint (not the official gateway).
	 *
	 * @var bool
	 */
	private bool $is_custom;

	/**
	 * Constructor.
	 *
	 * @param string $base_url  Gateway base URL.
	 * @param string $api_key   API key.
	 * @param string $model     Model name (only used for custom endpoints).
	 * @param bool   $is_custom Whether this is a custom endpoint.
	 */
	public function __construct( string $base_url, string $api_key, string $model = '', bool $is_custom = false ) {
		$this->base_url  = trailingslashit( $base_url );
		$this->api_key   = $api_key;
		$this->model     = $model;
		$this->is_custom = $is_custom;
	}

	/**
	 * Translate a batch of segments.
	 *
	 * @param string                                  $source_language Source language code, e.g. "en_US".
	 * @param string                                  $target_language Target language code, e.g. "zh_CN".
	 * @param array<int, array{id:int, text:string}>  $segments        Segments to translate.
	 * @param string                                  $idempotency_key Idempotency key for safe retry.
	 * @return EngineResult
	 * @throws EngineException On gateway error.
	 */
	public function translate( string $source_language, string $target_language, array $segments, string $idempotency_key ): EngineResult {
		if ( array() === $segments ) {
			return new EngineResult( array() );
		}

		$url      = $this->base_url . 'v1/translate';
		$headers  = array(
			'Authorization'    => 'Bearer ' . $this->api_key,
			'Content-Type'     => 'application/json',
			'X-Site-Url'       => home_url(),
			'X-Request-Id'     => wp_generate_uuid4(),
			'Idempotency-Key'  => $idempotency_key,
		);

		$body = array(
			'source_lang' => $this->normalize_lang( $source_language ),
			'target_lang' => $this->normalize_lang( $target_language ),
			'segments'    => array_values(
				array_map(
					static function ( array $seg ): array {
						return array(
							'id'   => $seg['id'],
							'text' => $seg['text'],
						);
					},
					$segments
				)
			),
			'options' => array(
				'preserve_placeholders' => true,
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new EngineException(
				$response->get_error_message(),
				EngineErrorCodes::ENGINE_UNAVAILABLE
			);
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		if ( 200 !== $status ) {
			$this->handle_error( $status, $data, $response );
		}

		if ( ! is_array( $data ) || ! isset( $data['translations'] ) ) {
			throw new EngineException(
				__( 'Invalid response from translation engine.', 'openpoly' ),
				EngineErrorCodes::INVALID_REQUEST
			);
		}

		$translations = array();
		foreach ( $data['translations'] as $id => $text ) {
			$translations[ (int) $id ] = (string) $text;
		}

		$usage = array(
			'chars_billed'  => (int) ( $data['usage']['chars_billed'] ?? 0 ),
			'tokens_in'     => (int) ( $data['usage']['tokens_in'] ?? 0 ),
			'tokens_out'    => (int) ( $data['usage']['tokens_out'] ?? 0 ),
			'tokens_cached' => (int) ( $data['usage']['tokens_cached'] ?? 0 ),
		);

		$is_cached = isset( $response['headers']['x-idempotency-replay'] );

		return new EngineResult( $translations, $usage, $is_cached );
	}

	/**
	 * Generate an idempotency key from token prefix + job item id + segment fingerprint.
	 *
	 * @param int   $trid      Translation group id.
	 * @param array<int, string> $texts Source texts in order.
	 * @return string
	 */
	public static function idempotency_key( int $trid, array $texts ): string {
		$prefix = substr( md5( (string) $trid ), 0, 8 );
		$md5    = md5( implode( '|||', $texts ) );
		return $prefix . ':' . implode( ',', array_keys( $texts ) ) . ':' . $md5;
	}

	/**
	 * Build the engine from stored options.
	 *
	 * @return self
	 */
	public static function from_options(): self {
		$engine_type = get_option( 'openpoly_engine_type', 'gateway' );
		$base_url    = get_option( 'openpoly_engine_base_url', 'https://gateway.openpoly.example/v1/' );
		$api_key     = get_option( 'openpoly_engine_api_key', '' );
		$model       = get_option( 'openpoly_engine_model', '' );
		$is_custom   = 'custom' === $engine_type;

		return new self( $base_url, $api_key, $model, $is_custom );
	}

	/**
	 * Handle non-200 HTTP responses, throwing appropriate exceptions.
	 *
	 * @param int            $status   HTTP status code.
	 * @param mixed          $data     Decoded JSON body, or null.
	 * @param array<string, mixed> $response Raw response array.
	 * @return never
	 * @throws EngineException
	 */
	private function handle_error( int $status, $data, array $response ): void {
		$error_code  = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : '';
		$message     = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : '';

		$retry_after = 0;
		if ( 429 === $status ) {
			$retry_hdr   = wp_remote_retrieve_header( $response, 'retry-after' );
			$retry_after = (int) ( $retry_hdr ?? 0 );
		}

		switch ( $status ) {
			case 401:
				throw new EngineException(
					'' !== $message ? $message : __( 'Invalid API token.', 'openpoly' ),
					'' !== $error_code ? $error_code : EngineErrorCodes::AUTH_INVALID
				);
			case 402:
				throw new EngineException(
					'' !== $message ? $message : __( 'Translation quota exhausted.', 'openpoly' ),
					EngineErrorCodes::QUOTA_EXHAUSTED
				);
			case 403:
				throw new EngineException(
					'' !== $message ? $message : __( 'Site URL mismatch.', 'openpoly' ),
					EngineErrorCodes::AUTH_SITE_MISMATCH
				);
			case 429:
				throw new EngineException(
					'' !== $message ? $message : __( 'Rate limited.', 'openpoly' ),
					EngineErrorCodes::RATE_LIMITED,
					$retry_after
				);
			case 503:
				throw new EngineException(
					'' !== $message ? $message : __( 'Translation engine unavailable.', 'openpoly' ),
					EngineErrorCodes::ENGINE_UNAVAILABLE
				);
			default:
				throw new EngineException(
					'' !== $message ? $message : __( 'Unknown engine error.', 'openpoly' ),
					EngineErrorCodes::INVALID_REQUEST
				);
		}
	}

	/**
	 * Normalize a language code (underscore → hyphen, lowercase).
	 *
	 * @param string $code Language code, e.g. "en_US".
	 * @return string Normalized code, e.g. "en-us".
	 */
	private function normalize_lang( string $code ): string {
		return strtolower( str_replace( '_', '-', $code ) );
	}
}
