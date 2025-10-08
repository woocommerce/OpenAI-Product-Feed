<?php

declare(strict_types=1);

namespace OAPFW\Admin\Controllers;

use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Core\FeedGeneratorInterface;
use OAPFW\Core\ValidatorInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller for WooCommerce integration
 */
class AdminController {

	private SettingsRepositoryInterface $settings;
	private FeedGeneratorInterface $feedGenerator;
	private ValidatorInterface $validator;
	private $logger;

	const SCHEDULED_ACTION_HOOK = 'oapfw_push_feed_event';

	public function __construct(
		SettingsRepositoryInterface $settings,
		FeedGeneratorInterface $feedGenerator,
		ValidatorInterface $validator
	) {
		$this->settings      = $settings;
		$this->feedGenerator = $feedGenerator;
		$this->validator     = $validator;
		$this->logger        = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
	}

	/**
	 * Initialize admin functionality
	 */
	public function init(): void {
		// WooCommerce settings tab integration
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'addWcSettingsTab' ), 50 );
		add_action( 'woocommerce_settings_tabs_oapfw', array( $this, 'renderWcSettingsTab' ) );
		add_action( 'woocommerce_update_options_oapfw', array( $this, 'saveWcSettings' ) );

		// Admin post handlers for actions
		add_action( 'admin_post_oapfw_download_feed', array( $this, 'handleDownloadFeed' ) );
		add_action( 'admin_post_oapfw_push_now', array( $this, 'handlePushNow' ) );

		// Action Scheduler hooks
		add_action( self::SCHEDULED_ACTION_HOOK, array( $this, 'cronPushFeed' ) );
		add_action( 'oapfw_push_delta_event', array( $this, 'pushDeltaToEndpoint' ), 10, 1 );
		add_action( 'update_option_' . $this->settings->getOptionName(), array( $this, 'maybeReschedule' ), 10, 3 );

		// Product change hooks for delta pushes
		add_action( 'woocommerce_update_product', array( $this, 'queueDeltaPush' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'queueDeltaPush' ), 10, 1 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'maybePushDeltaOnSave' ) );

		// Add custom cron schedule for WP Cron fallback
		add_filter( 'cron_schedules', array( $this, 'addCustomCronSchedules' ) );

		// Admin notice for successful actions
		add_action( 'admin_notices', array( $this, 'maybeShowAdminNotice' ) );
	}

	/**
	 * Add WooCommerce settings tab
	 */
	public function addWcSettingsTab( array $tabs ): array {
		$tabs['oapfw'] = __( 'OpenAI Feed', 'openai-product-feed-for-woo' );
		return $tabs;
	}

	/**
	 * Render WooCommerce settings tab content
	 */
	public function renderWcSettingsTab(): void {
		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'settings';

		$this->renderTabNavigation( $section );
		$this->renderTabContent( $section );
	}

	/**
	 * Render tab navigation
	 */
	private function renderTabNavigation( string $current_section ): void {
		echo '<ul class="subsubsub">';

		$sections = array(
			'push'     => __( 'Feed Delivery', 'openai-product-feed-for-woo' ),
			'settings' => __( 'Settings', 'openai-product-feed-for-woo' ),
		);

		$count = 0;
		foreach ( $sections as $id => $label ) {
			++$count;
			$class = $current_section === $id ? 'class="current"' : '';
			$url   = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'oapfw',
					'section' => $id,
				),
				admin_url( 'admin.php' )
			);

			printf(
				'<li><a %s href="%s">%s</a>%s</li>',
				$class,
				esc_url( $url ),
				esc_html( $label ),
				$count < count( $sections ) ? ' | ' : ''
			);
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Render tab content based on section
	 */
	private function renderTabContent( string $section ): void {
		echo '<h2>' . esc_html__( 'OpenAI Product Feed', 'openai-product-feed-for-woo' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Push your product feed to OpenAI so ChatGPT can index your products with up-to-date price and availability.', 'openai-product-feed-for-woo' ) . '</p>';

		switch ( $section ) {
			case 'push':
				$this->renderPushSection();
				break;
			case 'settings':
				$this->renderSettingsSection();
				break;
			default:
				$this->renderPushSection(); // Default to push
				break;
		}
	}

	/**
	 * Render push section
	 */
	private function renderPushSection(): void {
		echo '<h3>' . esc_html__( 'Feed Delivery Configuration', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p>' . esc_html__( 'Configure how your product feed is delivered to OpenAI. Feeds are pushed automatically every 15 minutes when enabled, plus immediately when products change.', 'openai-product-feed-for-woo' ) . '</p>';

		echo '<table class="form-table">';

		// Enable/Disable Push
		echo '<tr><th>' . esc_html__( 'Scheduled Delivery', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="oapfw_settings[delivery_enabled]" value="true" %s/> %s</label>',
			checked( $this->settings->get( 'delivery_enabled', 'false' ), 'true', false ),
			esc_html__( 'Enable push every ≤ 15 minutes', 'openai-product-feed-for-woo' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, your site will automatically POST the feed to OpenAI\'s endpoint.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		// OpenAI Endpoint URL
		echo '<tr><th>' . esc_html__( 'OpenAI Endpoint URL', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf(
			'<input type="url" class="regular-text" name="oapfw_settings[endpoint_url]" value="%s" placeholder="https://api.openai.com/v1/your-endpoint">',
			esc_attr( $this->settings->get( 'endpoint_url', '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Enter the HTTPS endpoint URL provided by OpenAI for your store.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		// Bearer Token
		echo '<tr><th>' . esc_html__( 'Bearer Token', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf(
			'<input type="text" class="regular-text" name="oapfw_settings[auth_token]" value="%s" placeholder="sk_live_...">',
			esc_attr( $this->settings->get( 'auth_token', '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Enter the authorization token provided by OpenAI.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';

		// Actions Section
		echo '<h4>' . esc_html__( 'Actions', 'openai-product-feed-for-woo' ) . '</h4>';
		$this->renderPushActions();

		// Status Section
		echo '<h4>' . esc_html__( 'Status', 'openai-product-feed-for-woo' ) . '</h4>';
		$this->renderPushStatus();
	}


	/**
	 * Render push actions section
	 */
	private function renderPushActions(): void {
		echo '<table class="form-table"><tr><td>';

		// Download button
		$download_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=oapfw_download_feed' ),
			'oapfw_download_feed'
		);
		echo '<a style="margin-right:8px;" href="' . esc_url( $download_url ) .
			'" class="button button-primary">' .
			esc_html__( 'Download Feed', 'openai-product-feed-for-woo' ) . '</a>';

		// Push button (if configured)
		$can_push = $this->canPushFeed();
		if ( $can_push ) {
			$push_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=oapfw_push_now' ),
				'oapfw_push_now'
			);
			echo '<a style="display:inline-block;" href="' . esc_url( $push_url ) .
				'" class="button">' .
				esc_html__( 'Push Now', 'openai-product-feed-for-woo' ) . '</a>';
		} else {
			echo '<button type="button" class="button" disabled>' .
				esc_html__( 'Push Now (configure endpoint + token)', 'openai-product-feed-for-woo' ) .
				'</button>';
		}

		echo '</td></tr></table>';
	}

	/**
	 * Render push status section
	 */
	private function renderPushStatus(): void {
		echo '<table class="form-table">';

		// Next scheduled push
		$next_push = null;
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$scheduled_actions = as_get_scheduled_actions(
				array(
					'hook'     => self::SCHEDULED_ACTION_HOOK,
					'status'   => 'pending',
					'per_page' => 1,
				)
			);
			if ( ! empty( $scheduled_actions ) ) {
				$next_push = $scheduled_actions[0]->get_schedule()->get_date()->getTimestamp();
			}
		}

		echo '<tr><th>' . esc_html__( 'Next Scheduled Push', 'openai-product-feed-for-woo' ) . '</th><td>';
		if ( $next_push ) {
			echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_push ) );
		} else {
			echo esc_html__( 'Not scheduled', 'openai-product-feed-for-woo' );
		}
		echo '</td></tr>';

		// Validation issues
		$issues = get_transient( 'oapfw_last_validation' );
		echo '<tr><th>' . esc_html__( 'Feed Validation', 'openai-product-feed-for-woo' ) . '</th><td>';
		if ( ! empty( $issues ) && is_array( $issues ) ) {
			echo '<span style="color:#d63638;">' . sprintf(
				esc_html__( '%d validation issues found', 'openai-product-feed-for-woo' ),
				count( $issues )
			) . '</span>';
			echo '<details style="margin-top:8px;"><summary>View Issues</summary>';
			echo '<ul style="margin-left:1em;">';
			$shown = 0;
			foreach ( $issues as $item ) {
				if ( $shown > 10 ) {
					echo '<li>…</li>';
					break;
				}
				$id       = isset( $item['id'] ) ? esc_html( (string) $item['id'] ) : '#';
				$messages = isset( $item['issues'] ) && is_array( $item['issues'] )
					? array_map( 'esc_html', $item['issues'] )
					: array();
				echo '<li><strong>' . $id . ':</strong> ' . implode( '; ', $messages ) . '</li>';
				++$shown;
			}
			echo '</ul></details>';
		} else {
			echo '<span style="color:#00a32a;">✓ ' . esc_html__( 'No issues found', 'openai-product-feed-for-woo' ) . '</span>';
		}
		echo '</td></tr>';

		echo '</table>';
	}


	/**
	 * Render validation issues (legacy method - keeping for compatibility)
	 */
	private function renderValidationIssues(): void {
		$issues = get_transient( 'oapfw_last_validation' );
		if ( ! empty( $issues ) && is_array( $issues ) ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'Recent feed validation issues:', 'openai-product-feed-for-woo' ) .
				'</p><ul style="margin-left:1em;">';

			$shown = 0;
			foreach ( $issues as $item ) {
				if ( $shown > 10 ) {
					echo '<li>…</li>';
					break;
				}

				$id       = isset( $item['id'] ) ? esc_html( (string) $item['id'] ) : '#';
				$messages = isset( $item['issues'] ) && is_array( $item['issues'] )
					? array_map( 'esc_html', $item['issues'] )
					: array();

				echo '<li><strong>' . $id . ':</strong> ' . implode( '; ', $messages ) . '</li>';
				++$shown;
			}

			echo '</ul></div>';
		}
	}


	/**
	 * Render settings section (shared settings for both push and pull)
	 */
	private function renderSettingsSection(): void {
		echo '<h3>' . esc_html__( 'Feed Content', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<table class="form-table">';

		// Feed format
		echo '<tr><th>' . esc_html__( 'Default Format', 'openai-product-feed-for-woo' ) . '</th><td><select name="oapfw_settings[format]">';
		foreach ( array( 'json', 'csv', 'xml', 'tsv' ) as $fmt ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $fmt ), selected( $this->settings->get( 'format', 'json' ), $fmt, false ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Default format for feeds. JSON recommended. Pull requests can override this.', 'openai-product-feed-for-woo' ) . '</p></td></tr>';

		echo '</table>';

		// Product Defaults
		echo '<h3>' . esc_html__( 'Product Defaults', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'Enable Search', 'openai-product-feed-for-woo' ) . '</th><td>';
		$search_val_wc = $this->settings->get( 'enable_search_default', 'true' );
		printf( '<label><input type="checkbox" name="oapfw_settings[enable_search_default]" value="true" %s/> %s</label>', checked( $search_val_wc, 'true', false ), esc_html__( 'Allow products in ChatGPT search by default', 'openai-product-feed-for-woo' ) );
		echo '<p class="description">' . esc_html__( 'Can be overridden per product in the product editor.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Enable Checkout', 'openai-product-feed-for-woo' ) . '</th><td>';
		$checkout_val_wc = $this->settings->get( 'enable_checkout_default', '' );
		if ( $checkout_val_wc === '' ) {
			$checkout_val_wc = 'false'; }
		printf( '<label><input type="checkbox" name="oapfw_settings[enable_checkout_default]" value="true" %s/> %s</label>', checked( $checkout_val_wc, 'true', false ), esc_html__( 'Allow ChatGPT instant checkout by default', 'openai-product-feed-for-woo' ) );
		echo '<p class="description">' . esc_html__( 'Requires enable_search=true and OpenAI approval. Can be overridden per product.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';

		// Merchant Information
		echo '<h3>' . esc_html__( 'Merchant Information', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'This information appears in all feeds and is required for checkout functionality.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '<table class="form-table">';

		$default_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$default_shop_url = $default_shop_url ?: home_url( '/' );
		$default_privacy  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
		$seller_name      = $this->settings->get( 'seller_name', '' );
		if ( $seller_name === '' ) {
			$seller_name = get_bloginfo( 'name' ); }
		$seller_url = $this->settings->get( 'seller_url', '' );
		if ( $seller_url === '' ) {
			$seller_url = $default_shop_url; }
		$privacy_url = $this->settings->get( 'privacy_url', '' );
		if ( $privacy_url === '' ) {
			$privacy_url = $default_privacy; }
		$tos = $this->settings->get( 'tos_url', '' );
		if ( $tos === '' && function_exists( 'wc_terms_and_conditions_page_id' ) ) {
			$pid = wc_terms_and_conditions_page_id();
			if ( $pid ) {
				$tos = get_permalink( $pid ); }
		}

		echo '<tr><th>' . esc_html__( 'Seller Name', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf( '<input type="text" class="regular-text" name="oapfw_settings[seller_name]" value="%s">', esc_attr( $seller_name ) );
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Store URL', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf( '<input type="url" class="regular-text" name="oapfw_settings[seller_url]" value="%s">', esc_attr( $seller_url ) );
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Privacy Policy URL', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf( '<input type="url" class="regular-text" name="oapfw_settings[privacy_url]" value="%s">', esc_attr( $privacy_url ) );
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Terms of Service URL', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf( '<input type="url" class="regular-text" name="oapfw_settings[tos_url]" value="%s">', esc_attr( $tos ) );
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Return Policy URL', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf( '<input type="url" class="regular-text" name="oapfw_settings[returns_url]" value="%s">', esc_attr( $this->settings->get( 'returns_url', '' ) ) );
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Return Window', 'openai-product-feed-for-woo' ) . '</th><td>';
		$return_window = (int) $this->settings->get( 'return_window', 0 );
		if ( $return_window === 0 ) {
			$return_window = 30; }
		printf( '<input type="number" class="small-text" min="0" step="1" name="oapfw_settings[return_window]" value="%s"> days', esc_attr( $return_window ) );
		echo '</td></tr>';

		echo '</table>';

		// Feed Preview
		echo '<h3>' . esc_html__( 'Feed Preview', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p>' . esc_html__( 'Preview your current feed data:', 'openai-product-feed-for-woo' ) . '</p>';
		$preview_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'wc/v3/openai-feed' ) );
		echo '<p><code>' . esc_html( rest_url( 'wc/v3/openai-feed' ) ) . '</code> ';
		echo '<a href="' . esc_url( $preview_url ) . '" target="_blank" class="button button-secondary">' .
			esc_html__( 'Open Preview', 'openai-product-feed-for-woo' ) . '</a></p>';

		// Reference
		echo '<p class="description" style="margin-top:2em;">' . sprintf(
			esc_html__( 'See the OpenAI Product Feed specification: %s', 'openai-product-feed-for-woo' ),
			'<a href="https://developers.openai.com/commerce/specs/feed/" target="_blank" rel="noopener">developers.openai.com/commerce/specs/feed/</a>'
		) . '</p>';
	}

	/**
	 * Save WooCommerce settings
	 */
	public function saveWcSettings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$posted = isset( $_POST['oapfw_settings'] ) && is_array( $_POST['oapfw_settings'] )
			? wp_unslash( $_POST['oapfw_settings'] )
			: array();

		$this->settings->save( $posted );
	}

	/**
	 * Handle download feed action
	 */
	public function handleDownloadFeed(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'openai-product-feed-for-woo' ) );
		}

		check_admin_referer( 'oapfw_download_feed' );

		$format   = $this->settings->get( 'format', 'json' );
		$filename = 'openai-feed-' . date( 'Ymd-His' ) . '.' . $format;

		$rows    = $this->feedGenerator->buildFeed();
		$payload = $this->feedGenerator->serialize( $rows, $format, $content_type );

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle push now action
	 */
	public function handlePushNow(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'openai-product-feed-for-woo' ) );
		}

		check_admin_referer( 'oapfw_push_now' );

		$this->pushToEndpoint();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'wc-settings',
					'tab'           => 'oapfw',
					'oapfw_message' => 'pushed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}


	/**
	 * Check if Action Scheduler is available
	 */
	private function isActionSchedulerAvailable(): bool {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Cron job to push feed
	 */
	public function cronPushFeed(): void {
		if ( $this->settings->get( 'delivery_enabled', 'false' ) !== 'true' ) {
			return;
		}

		$this->pushToEndpoint();
	}

	/**
	 * Push delta update for single product
	 */
	public function pushDeltaToEndpoint( int $product_id ): void {
		if ( $this->settings->get( 'delivery_enabled', 'false' ) !== 'true' ) {
			return;
		}

		$rows = $this->feedGenerator->buildForProductId( $product_id );
		if ( ! $rows ) {
			return;
		}

		$this->pushFeedData( $rows, true );
	}

	/**
	 * Queue delta push for product changes
	 */
	public function queueDeltaPush( $product_id_or_obj ): void {
		if ( $this->settings->get( 'delivery_enabled', 'false' ) !== 'true' ) {
			return;
		}

		// Check if Action Scheduler is available and no task is already scheduled
		if ( $this->isActionSchedulerAvailable() ) {
			if ( ! as_has_scheduled_action( self::SCHEDULED_ACTION_HOOK ) ) {
				as_schedule_single_action( time() + 120, self::SCHEDULED_ACTION_HOOK );
			}
		}
	}

	/**
	 * Maybe push delta on product save
	 */
	public function maybePushDeltaOnSave( $product ): void {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( $this->settings->get( 'delivery_enabled', 'false' ) === 'true' ) {
			$product_id = $product->get_id();

			// Use Action Scheduler if available
			if ( $this->isActionSchedulerAvailable() ) {
				as_schedule_single_action( time() + 30, 'oapfw_push_delta_event', array( $product_id ) );
			}
		}
	}

	/**
	 * Maybe reschedule cron based on settings changes
	 */
	public function maybeReschedule( $old_value, $value, $option ): void {
		$enabled = isset( $value['delivery_enabled'] ) && $value['delivery_enabled'] === 'true';

		// Debug logging
		if ( $this->logger ) {
			$this->logger->info( 'maybeReschedule called', array(
				'source'  => 'oapfw',
				'enabled' => $enabled,
				'as_available' => $this->isActionSchedulerAvailable()
			) );
		}

		// Clear existing scheduled actions (both Action Scheduler and WP-Cron)
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::SCHEDULED_ACTION_HOOK );
		}
		wp_clear_scheduled_hook( self::SCHEDULED_ACTION_HOOK );

		// Only schedule if enabled and Action Scheduler is available
		if ( $enabled && $this->isActionSchedulerAvailable() ) {
			// Schedule recurring action every 15 minutes using WooCommerce patterns
			$result = as_schedule_recurring_action( 
				time() + 60, 
				900, 
				self::SCHEDULED_ACTION_HOOK, 
				array(), 
				'oapfw' 
			); // 900 seconds = 15 minutes
			
			if ( $this->logger ) {
				$this->logger->info( 'Scheduled recurring action', array(
					'source' => 'oapfw',
					'hook'   => self::SCHEDULED_ACTION_HOOK,
					'result' => $result
				) );
			}
		} elseif ( $enabled && ! $this->isActionSchedulerAvailable() ) {
			// Fallback to WordPress cron if Action Scheduler is not available
			if ( ! wp_next_scheduled( self::SCHEDULED_ACTION_HOOK ) ) {
				wp_schedule_event( time() + 60, 'every_15_minutes', self::SCHEDULED_ACTION_HOOK );
			}
			
			if ( $this->logger ) {
				$this->logger->info( 'Scheduled using WP Cron (fallback)', array(
					'source' => 'oapfw',
					'hook'   => self::SCHEDULED_ACTION_HOOK
				) );
			}
		}

		if ( $this->logger ) {
			$this->logger->info( 'Rescheduling completed', array(
				'source' => 'oapfw',
				'enabled' => $enabled,
				'method' => $this->isActionSchedulerAvailable() ? 'Action Scheduler' : 'WP Cron'
			) );
		}
	}

	/**
	 * Add custom cron schedules
	 */
	public function addCustomCronSchedules( array $schedules ): array {
		$schedules['every_15_minutes'] = array(
			'interval' => 900, // 15 minutes in seconds
			'display'  => __( 'Every 15 Minutes', 'openai-product-feed-for-woo' ),
		);
		return $schedules;
	}

	/**
	 * Show admin notice if needed
	 */
	public function maybeShowAdminNotice(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-settings' ) {
			return;
		}

		if ( isset( $_GET['oapfw_message'] ) && $_GET['oapfw_message'] === 'pushed' ) {
			echo '<div class="notice notice-success"><p>' .
				esc_html__( 'Feed push triggered. Check debug log for status.', 'openai-product-feed-for-woo' ) .
				'</p></div>';
		}
	}

	/**
	 * Check if feed can be pushed
	 */
	private function canPushFeed(): bool {
		$delivery_enabled    = $this->settings->get( 'delivery_enabled', 'false' ) === 'true';
		$endpoint_configured = trim( (string) $this->settings->get( 'endpoint_url', '' ) ) !== '';
		$token_configured    = trim( (string) $this->settings->get( 'auth_token', '' ) ) !== '';

		return $delivery_enabled && $endpoint_configured && $token_configured;
	}

	/**
	 * Push feed to configured endpoint
	 */
	private function pushToEndpoint(): void {
		$rows = $this->feedGenerator->buildFeed();
		$this->pushFeedData( $rows, false );
	}

	/**
	 * Push feed data to endpoint
	 */
	private function pushFeedData( array $rows, bool $is_delta = false ): void {
		// Validate rows and record issues
		$issues = $this->validator->validateFeed( $rows );

		if ( $issues ) {
			set_transient( 'oapfw_last_validation', $issues, 5 * MINUTE_IN_SECONDS );
		} else {
			delete_transient( 'oapfw_last_validation' );
		}

		$format   = $this->settings->get( 'format', 'json' );
		$endpoint = trim( (string) $this->settings->get( 'endpoint_url', '' ) );

		if ( empty( $endpoint ) ) {
			return;
		}

		$payload = $this->feedGenerator->serialize( $rows, $format, $content_type );

		$headers = array( 'Content-Type' => $content_type );
		if ( $is_delta ) {
			$headers['X-Feed-Delta'] = 'true';
		}

		$token = $this->settings->get( 'auth_token', '' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $headers,
				'timeout' => 30,
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $this->logger ) {
				$this->logger->error( 'Feed push failed: ' . $response->get_error_message(), array( 'source' => 'oapfw' ) );
			}
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $this->logger ) {
				if ( $code >= 200 && $code < 300 ) {
					$this->logger->info( 'Feed push successful: HTTP ' . $code, array( 'source' => 'oapfw' ) );
				} else {
					$this->logger->warning( 'Feed push returned HTTP ' . $code, array( 'source' => 'oapfw' ) );
				}
			}
		}
	}
}
