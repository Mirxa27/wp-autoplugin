<?php
/**
 * WP-Autoplugin Admin Settings class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that registers plugin settings in the admin.
 */
class Settings {

	/**
	 * Constructor hooks into 'admin_init'.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register the plugin settings fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_openai_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_anthropic_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_google_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_xai_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_model', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting(
			'wp_autoplugin_settings',
			'wp_autoplugin_custom_models',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_custom_models' ],
				'default'           => [],
			]
		);
	}

	/**
	 * Sanitize custom models array.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized custom models array.
	 */
	public function sanitize_custom_models( $value ) {
		// If it's a JSON string, decode it.
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $value as $model ) {
			if ( ! is_array( $model ) ) {
				continue;
			}
			if ( empty( $model['name'] ) || empty( $model['url'] ) || empty( $model['apiKey'] ) ) {
				continue;
			}

			// Validate and sanitize URL - ensure it uses HTTPS for secure API communications.
			$url = esc_url_raw( $model['url'], [ 'https' ] );
			if ( empty( $url ) ) {
				// Skip models with invalid or non-HTTPS URLs.
				continue;
			}

			$sanitized[] = [
				'name'           => sanitize_text_field( $model['name'] ),
				'url'            => $url,
				'modelParameter' => isset( $model['modelParameter'] ) ? sanitize_text_field( $model['modelParameter'] ) : '',
				'apiKey'         => sanitize_text_field( $model['apiKey'] ),
				'headers'        => isset( $model['headers'] ) && is_array( $model['headers'] )
					? array_map( 'sanitize_text_field', $model['headers'] )
					: [],
			];
		}

		return $sanitized;
	}
}
