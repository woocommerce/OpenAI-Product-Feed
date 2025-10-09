<?php

declare(strict_types=1);

namespace OAPFW\Admin\Views;

use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Admin\Helpers\CredentialValidator;
use OAPFW\Admin\Helpers\FeedStatusProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminViewRenderer {

	private SettingsRepositoryInterface $settings;
	private CredentialValidator $credentialValidator;
	private FeedStatusProvider $statusProvider;

	public function __construct(
		SettingsRepositoryInterface $settings,
		CredentialValidator $credentialValidator,
		FeedStatusProvider $statusProvider
	) {
		$this->settings = $settings;
		$this->credentialValidator = $credentialValidator;
		$this->statusProvider = $statusProvider;
	}

	public function renderTabNavigation( string $current_section ): void {
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

	public function renderTabHeader(): void {
		echo '<h2>' . esc_html__( 'OpenAI Product Feed', 'openai-product-feed-for-woo' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Push your product feed to OpenAI so ChatGPT can index your products with up-to-date price and availability.', 'openai-product-feed-for-woo' ) . '</p>';
	}

	public function renderCredentialSetup(): void {
		if ( $this->credentialValidator->areCredentialsConfigured() ) {
			return;
		}

		echo '<div class="notice notice-info" style="margin: 20px 0; padding: 20px; border-left: 4px solid #72aee6;">';
		echo '<h3>' . esc_html__( 'Get Started with ChatGPT Shopping', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p>' . esc_html__( 'To enable your products in ChatGPT, you need OpenAI credentials. Choose one of the options below:', 'openai-product-feed-for-woo' ) . '</p>';
		
		echo '<div style="margin: 15px 0;">';
		echo '<a href="https://chatgpt.com/merchants" target="_blank" rel="noopener" class="button button-primary" style="margin-right: 10px;">';
		echo esc_html__( 'Apply to ChatGPT', 'openai-product-feed-for-woo' );
		echo '</a>';
		
		echo '<button type="button" class="button button-secondary" onclick="document.getElementById(\'oapfw-credentials-form\').style.display=\'block\'; this.style.display=\'none\';">';
		echo esc_html__( 'I have OpenAI credentials', 'openai-product-feed-for-woo' );
		echo '</button>';
		echo '</div>';
		
		echo '<div id="oapfw-credentials-form" style="display: none; margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">';
		echo '<h4>' . esc_html__( 'Configure Your Credentials', 'openai-product-feed-for-woo' ) . '</h4>';
		echo '<p>' . esc_html__( 'Enter your OpenAI endpoint URL and authentication token below, then save settings.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</div>';
		
		echo '</div>';
	}

	public function renderPushSection(): void {
		echo '<h3>' . esc_html__( 'Feed Delivery Configuration', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p>' . esc_html__( 'Configure how your product feed is delivered to OpenAI. Feeds are pushed automatically every 15 minutes when enabled, plus immediately when products change.', 'openai-product-feed-for-woo' ) . '</p>';

		$this->renderCredentialSetup();

		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Scheduled Delivery', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="oapfw_settings[delivery_enabled]" value="true" %s/> %s</label>',
			checked( $this->settings->get( 'delivery_enabled', 'false' ), 'true', false ),
			esc_html__( 'Enable push every ≤ 15 minutes', 'openai-product-feed-for-woo' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, your site will automatically POST the feed to OpenAI\'s endpoint.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'OpenAI Endpoint URL', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf(
			'<input type="url" class="regular-text" name="oapfw_settings[endpoint_url]" value="%s" placeholder="https://api.openai.com/v1/your-endpoint">',
			esc_attr( $this->settings->get( 'endpoint_url', '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Enter the HTTPS endpoint URL provided by OpenAI for your store.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Bearer Token', 'openai-product-feed-for-woo' ) . '</th><td>';
		printf(
			'<input type="text" class="regular-text" name="oapfw_settings[auth_token]" value="%s" placeholder="sk_live_...">',
			esc_attr( $this->settings->get( 'auth_token', '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Enter the authorization token provided by OpenAI.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';

		echo '<h4>' . esc_html__( 'Actions', 'openai-product-feed-for-woo' ) . '</h4>';
		$this->renderPushActions();

		$this->renderPushStatus();
	}

	public function renderPushActions(): void {
		echo '<table class="form-table"><tr><td>';

		$download_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=oapfw_download_feed' ),
			'oapfw_download_feed'
		);
		echo '<a style="margin-right:8px;" href="' . esc_url( $download_url ) .
			'" class="button button-primary">' .
			esc_html__( 'Download Feed', 'openai-product-feed-for-woo' ) . '</a>';

		$can_push = $this->credentialValidator->canPushFeed();
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

	public function renderPushStatus(): void {
		echo '<div id="oapfw-feed-status">';
		
		$status = $this->statusProvider->getFeedStatus( true );
		
		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Next Scheduled Push', 'openai-product-feed-for-woo' ) . '</th><td>';
		if ( $status['next_push'] ) {
			echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['next_push'] ) );
		} else {
			echo esc_html__( 'Not scheduled', 'openai-product-feed-for-woo' );
		}
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Feed Validation', 'openai-product-feed-for-woo' ) . '</th><td>';
		if ( $status['has_issues'] ) {
			$is_empty_feed = false;
			foreach ( $status['validation_issues'] as $issue ) {
				if ( isset( $issue['id'] ) && $issue['id'] === 'feed' && 
					 isset( $issue['issues'] ) && in_array( 'Feed is empty - no products to export', $issue['issues'] ) ) {
					$is_empty_feed = true;
					break;
				}
			}
			
			if ( $is_empty_feed ) {
				echo '<span style="color:#d63638;">⚠ ' . esc_html__( 'Feed is empty - no products to export', 'openai-product-feed-for-woo' ) . '</span>';
				echo '<br><small style="color:#666;">' . esc_html__( 'Add products to your store or check that they are published and in stock.', 'openai-product-feed-for-woo' ) . '</small>';
			} else {
				echo '<span style="color:#d63638;">⚠ ' . sprintf(
					esc_html__( '%d validation issues found', 'openai-product-feed-for-woo' ),
					$status['issue_count']
				) . '</span>';
				echo '<br><small style="color:#666;">' . esc_html__( 'Your feed has issues that need attention before it can be successfully processed by OpenAI.', 'openai-product-feed-for-woo' ) . '</small>';
				echo '<br><a href="' . esc_url( $status['logs_url'] ) . '">' . 
					 esc_html__( 'View detailed validation results →', 'openai-product-feed-for-woo' ) . '</a>';
			}
		} else {
			echo '<span style="color:#00a32a;">✓ ' . esc_html__( 'Feed meets OpenAI specifications', 'openai-product-feed-for-woo' ) . '</span>';
			echo '<br><small style="color:#666;">' . esc_html__( 'Your product feed is ready for ChatGPT indexing.', 'openai-product-feed-for-woo' ) . '</small>';
		}
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Recent Activity', 'openai-product-feed-for-woo' ) . '</th><td>';
		echo '<a href="' . esc_url( $status['logs_url'] ) . '" class="button button-secondary">' . 
			 esc_html__( 'View Activity Logs', 'openai-product-feed-for-woo' ) . '</a>';
		echo '<br><small style="color:#666; margin-top: 5px; display: inline-block;">' . 
			 esc_html__( 'Last validation: just now', 'openai-product-feed-for-woo' ) . '</small>';
		echo '</td></tr>';

		echo '</table>';
		echo '</div>';
	}

	public function renderSettingsSection(): void {
		echo '<h3>' . esc_html__( 'Feed Content', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Default Format', 'openai-product-feed-for-woo' ) . '</th><td><select name="oapfw_settings[format]">';
		foreach ( array( 'json', 'csv', 'xml', 'tsv' ) as $fmt ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $fmt ), selected( $this->settings->get( 'format', 'json' ), $fmt, false ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Default format for feeds. JSON recommended. Pull requests can override this.', 'openai-product-feed-for-woo' ) . '</p></td></tr>';

		echo '</table>';

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

		$this->renderMerchantInformation();
		$this->renderFeedPreview();
	}

	private function renderMerchantInformation(): void {
		echo '<h3>' . esc_html__( 'Merchant Information', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'This information appears in all feeds and is required for checkout functionality.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '<table class="form-table">';

		$defaults    = $this->settings->getDefaults();
		$seller_name = $this->settings->get( 'seller_name', $defaults['seller_name'] ?? '' );
		$seller_url  = $this->settings->get( 'seller_url', $defaults['seller_url'] ?? '' );
		$privacy_url = $this->settings->get( 'privacy_url', $defaults['privacy_url'] ?? '' );
		$tos         = $this->settings->get( 'tos_url', $defaults['tos_url'] ?? '' );

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
		$return_window = (int) $this->settings->get( 'return_window', $defaults['return_window'] ?? 30 );
		printf( '<input type="number" class="small-text" min="0" step="1" name="oapfw_settings[return_window]" value="%s"> days', esc_attr( $return_window ) );
		echo '</td></tr>';

		echo '</table>';
	}

	private function renderFeedPreview(): void {
		echo '<h3>' . esc_html__( 'Feed Preview', 'openai-product-feed-for-woo' ) . '</h3>';
		echo '<p>' . esc_html__( 'Preview your current feed data:', 'openai-product-feed-for-woo' ) . '</p>';
		$preview_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'wc/v3/openai-feed' ) );
		echo '<p><code>' . esc_html( rest_url( 'wc/v3/openai-feed' ) ) . '</code> ';
		echo '<a href="' . esc_url( $preview_url ) . '" target="_blank" class="button button-secondary">' .
			esc_html__( 'Open Preview', 'openai-product-feed-for-woo' ) . '</a></p>';

		echo '<p class="description" style="margin-top:2em;">' . sprintf(
			esc_html__( 'See the OpenAI Product Feed specification: %s', 'openai-product-feed-for-woo' ),
			'<a href="https://developers.openai.com/commerce/specs/feed/" target="_blank" rel="noopener">developers.openai.com/commerce/specs/feed/</a>'
		) . '</p>';
	}
}