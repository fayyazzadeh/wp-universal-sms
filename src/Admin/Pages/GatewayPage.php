<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin\Pages;

use Fayyazdeh\UniversalSms\Admin\AdminSettings;

final class GatewayPage
{
    public function __construct(private readonly AdminSettings $settings)
    {
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage SMS settings.', 'wp-universal-sms'));
        }

        $config = $this->settings->getGatewayConfig();

        echo '<div class="wrap wpu-sms-admin" dir="rtl">';
        echo '<div class="wpu-sms-header"><div><span class="wpu-sms-eyebrow">Gateway</span><h1>Gateway Configuration</h1><p>Connect WordPress to the SMS panel already owned by your customer.</p></div></div>';
        echo '<div class="wpu-sms-panel"><form method="post">';
        wp_nonce_field('wpu_sms_save_gateway', 'wpu_sms_nonce');
        echo '<input type="hidden" name="wpu_sms_action" value="save_gateway">';
        $this->field('Provider ID', 'provider', $config['provider'], 'generic-http');
        $this->field('Sender', 'sender', $config['sender'], '3000');
        $this->field('Test recipient', 'test_recipient', $config['test_recipient'], '09120000000');
        echo '<div class="wpu-sms-actions"><button class="button button-primary" type="submit">Save Gateway</button><button class="button" type="button" disabled>Test Connection</button><button class="button" type="button" disabled>Send Test SMS</button></div>';
        echo '<p class="description">Provider-specific fields are edited from Providers. Connection and test actions are wired after the provider configuration screen is complete.</p>';
        echo '</form></div></div>';
    }

    private function field(string $label, string $name, string $value, string $placeholder): void
    {
        echo '<div class="wpu-sms-field"><label for="wpu-' . esc_attr($name) . '">' . esc_html($label) . '</label><input id="wpu-' . esc_attr($name) . '" name="wpu_sms[' . esc_attr($name) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text"></div>';
    }
}
