<?php
namespace OAPFW\Settings;

use OAPFW\Core\SettingsRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings form renderer
 */
class SettingsRenderer
{
    private SettingsRepositoryInterface $repository;

    public function __construct(SettingsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Register WordPress settings
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Register settings with WordPress
     */
    public function registerSettings(): void
    {
        register_setting($this->repository->getOptionName(), $this->repository->getOptionName(), [
            'sanitize_callback' => [$this->repository, 'sanitize'],
        ]);

        add_settings_section(
            'oapfw_main', 
            esc_html__('Feed Settings', 'openai-product-feed-for-woo'), 
            [$this, 'renderSectionDescription'], 
            $this->repository->getOptionName()
        );

        $this->addSettingsFields();
    }

    /**
     * Render section description
     */
    public function renderSectionDescription(): void
    {
        echo '<p>' . esc_html__('Configure feed format and merchant metadata. These values populate required fields in the feed. No external network calls are made by default.', 'openai-product-feed-for-woo') . '</p>';
    }

    /**
     * Add all settings fields
     */
    private function addSettingsFields(): void
    {
        $fields = [
            'format' => [
                'label' => esc_html__('Feed Format', 'openai-product-feed-for-woo'),
                'callback' => [$this, 'renderFormatField'],
            ],
            'pull_endpoint_enabled' => [
                'label' => esc_html__('Enable Pull Endpoint (REST)', 'openai-product-feed-for-woo'),
                'callback' => [$this, 'renderPullEndpointField'],
            ],
            'pull_access_token' => [
                'label' => esc_html__('Pull Access Token', 'openai-product-feed-for-woo'),
                'callback' => [$this, 'renderPullTokenField'],
            ],
            'delivery_enabled' => [
                'label' => esc_html__('Enable External Delivery (cron)', 'openai-product-feed-for-woo'),
                'callback' => [$this, 'renderDeliveryEnabledField'],
            ],
            'endpoint_url' => [
                'label' => esc_html__('Endpoint URL (HTTPS)', 'openai-product-feed-for-woo'),
                'callback' => [$this, 'renderEndpointUrlField'],
            ],
            'auth_token' => [
                'label' => esc_html__('Authorization Bearer Token', 'openai-product-feed-for-woo'),
                'callback' => [$this, 'renderAuthTokenField'],
            ],
        ];

        foreach ($fields as $key => $config) {
            add_settings_field(
                $key, 
                $config['label'], 
                $config['callback'], 
                $this->repository->getOptionName(), 
                'oapfw_main'
            );
        }
    }

    /**
     * Render format field
     */
    public function renderFormatField(): void
    {
        $value = $this->repository->get('format', 'json');
        $option_name = $this->repository->getOptionName();
        
        echo '<select name="' . esc_attr($option_name) . '[format]">';
        foreach (['json','csv','xml','tsv'] as $format) {
            printf(
                '<option value="%1$s" %2$s>%1$s</option>', 
                esc_attr($format), 
                selected($value, $format, false)
            );
        }
        echo '</select>';
    }

    /**
     * Render pull endpoint enabled field
     */
    public function renderPullEndpointField(): void
    {
        $value = $this->repository->get('pull_endpoint_enabled', 'false');
        $option_name = $this->repository->getOptionName();
        
        printf(
            '<label><input type="checkbox" name="%s[pull_endpoint_enabled]" value="true" %s /> %s</label>',
            esc_attr($option_name),
            checked($value, 'true', false),
            esc_html__('Expose read-only endpoint under wc/v3 for OpenAI to pull', 'openai-product-feed-for-woo')
        );
    }

    /**
     * Render pull token field
     */
    public function renderPullTokenField(): void
    {
        $this->renderTextField('pull_access_token');
    }

    /**
     * Render delivery enabled field
     */
    public function renderDeliveryEnabledField(): void
    {
        $value = $this->repository->get('delivery_enabled', 'false');
        $option_name = $this->repository->getOptionName();
        
        printf(
            '<label><input type="checkbox" name="%s[delivery_enabled]" value="true" %s /> %s</label>',
            esc_attr($option_name),
            checked($value, 'true', false),
            esc_html__('Push feed to endpoint every 15 minutes', 'openai-product-feed-for-woo')
        );
    }

    /**
     * Render endpoint URL field
     */
    public function renderEndpointUrlField(): void
    {
        $this->renderTextField('endpoint_url', 'url');
    }

    /**
     * Render auth token field
     */
    public function renderAuthTokenField(): void
    {
        $this->renderTextField('auth_token');
    }

    /**
     * Generic text field renderer
     */
    private function renderTextField(string $key, string $type = 'text'): void
    {
        $value = $this->repository->get($key, '');
        $option_name = $this->repository->getOptionName();
        
        printf(
            '<input type="%s" class="regular-text" name="%s[%s]" value="%s" />',
            esc_attr($type),
            esc_attr($option_name),
            esc_attr($key),
            esc_attr($value)
        );
    }
}