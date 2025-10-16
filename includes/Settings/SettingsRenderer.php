<?php
/**
 *  Settings Renderer class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Settings;

use OAPFW\Core\Interfaces\SettingsRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings form renderer
 */
class SettingsRenderer {

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepositoryInterface
	 */
	private SettingsRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepositoryInterface $repository The settings repository.
	 */
	public function __construct( SettingsRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register WordPress settings
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register settings with WordPress
	 */
	public function register_settings(): void {
		register_setting(
			$this->repository->get_option_name(),
			$this->repository->get_option_name(),
			[
				'sanitize_callback' => [ $this->repository, 'sanitize' ],
			]
		);

		add_settings_section(
			'oapfw_main',
			esc_html__( 'Feed Settings', 'openai-product-feed-for-woo' ),
			[ $this, 'render_section_description' ],
			$this->repository->get_option_name()
		);

		$this->add_settings_fields();
	}

	/**
	 * Render section description
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure feed format and merchant metadata. These values populate required fields in the feed. No external network calls are made by default.', 'openai-product-feed-for-woo' ) . '</p>';
	}

	/**
	 * Add all settings fields
	 */
	private function add_settings_fields(): void {
		$fields = [
			'format'                => [
				'label'    => esc_html__( 'Feed Format', 'openai-product-feed-for-woo' ),
				'callback' => [ $this, 'render_format_field' ],
			],
			'pull_endpoint_enabled' => [
				'label'    => esc_html__( 'Enable Pull Endpoint (REST)', 'openai-product-feed-for-woo' ),
				'callback' => [ $this, 'render_pull_endpoint_field' ],
			],
			'pull_access_token'     => [
				'label'    => esc_html__( 'Pull Access Token', 'openai-product-feed-for-woo' ),
				'callback' => [ $this, 'render_pull_token_field' ],
			],
			'delivery_enabled'      => [
				'label'    => esc_html__( 'Enable External Delivery (cron)', 'openai-product-feed-for-woo' ),
				'callback' => [ $this, 'render_delivery_enabled_field' ],
			],
			'endpoint_url'          => [
				'label'    => esc_html__( 'Endpoint URL (HTTPS)', 'openai-product-feed-for-woo' ),
				'callback' => [ $this, 'render_endpoint_url_field' ],
			],
			'auth_token'            => [
				'label'    => esc_html__( 'Authorization Bearer Token', 'openai-product-feed-for-woo' ),
				'callback' => [ $this, 'render_auth_token_field' ],
			],
		];

		foreach ( $fields as $key => $config ) {
			add_settings_field(
				$key,
				$config['label'],
				$config['callback'],
				$this->repository->get_option_name(),
				'oapfw_main'
			);
		}
	}

	/**
	 * Render format field
	 */
	public function render_format_field(): void {
		$value       = $this->repository->get( 'format', 'json' );
		$option_name = $this->repository->get_option_name();

		echo '<select name="' . esc_attr( $option_name ) . '[format]">';
		foreach ( [ 'json', 'csv', 'xml', 'tsv' ] as $format ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $format ),
				selected( $value, $format, false )
			);
		}
		echo '</select>';
	}

	/**
	 * Render pull endpoint enabled field
	 */
	public function render_pull_endpoint_field(): void {
		$value       = $this->repository->get( 'pull_endpoint_enabled', 'false' );
		$option_name = $this->repository->get_option_name();

		printf(
			'<label><input type="checkbox" name="%s[pull_endpoint_enabled]" value="true" %s /> %s</label>',
			esc_attr( $option_name ),
			checked( $value, 'true', false ),
			esc_html__( 'Expose read-only endpoint under wc/v3 for OpenAI to pull', 'openai-product-feed-for-woo' )
		);
	}

	/**
	 * Render pull token field
	 */
	public function render_pull_token_field(): void {
		$this->render_text_field( 'pull_access_token' );
	}

	/**
	 * Render delivery enabled field
	 */
	public function render_delivery_enabled_field(): void {
		$value       = $this->repository->get( 'delivery_enabled', 'false' );
		$option_name = $this->repository->get_option_name();

		printf(
			'<label><input type="checkbox" name="%s[delivery_enabled]" value="true" %s /> %s</label>',
			esc_attr( $option_name ),
			checked( $value, 'true', false ),
			esc_html__( 'Push feed to endpoint every 15 minutes', 'openai-product-feed-for-woo' )
		);
	}

	/**
	 * Render endpoint URL field
	 */
	public function render_endpoint_url_field(): void {
		$this->render_text_field( 'endpoint_url', 'url' );
	}

	/**
	 * Render auth token field
	 */
	public function render_auth_token_field(): void {
		$this->render_text_field( 'auth_token' );
	}

	/**
	 * Generic text field renderer.
	 *
	 * @param string $key The field key.
	 * @param string $type The input type.
	 */
	private function render_text_field( string $key, string $type = 'text' ): void {
		$value       = $this->repository->get( $key, '' );
		$option_name = $this->repository->get_option_name();

		printf(
			'<input type="%s" class="regular-text" name="%s[%s]" value="%s" />',
			esc_attr( $type ),
			esc_attr( $option_name ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}
}
