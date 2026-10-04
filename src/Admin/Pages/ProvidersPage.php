<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin\Pages;

use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use Fayyazdeh\UniversalSms\Admin\GatewayConfig;
use Fayyazdeh\UniversalSms\Admin\ProviderDefinitions;

final class ProvidersPage
{
    public function __construct(private readonly AdminSettings $settings)
    {
    }

    public function render(): void
    {
        $definitions = ProviderDefinitions::all();
        $raw = $this->settings->getRawGatewayConfig();
        $config = $this->settings->getSafeGatewayConfig();
        $providerId = (string) ($config['provider'] ?? 'generic-http');
        $definition = $definitions[$providerId] ?? $definitions['generic-http'];

        echo '<div class="wrap wpu-sms-admin" dir="rtl">';
        echo '<div class="wpu-sms-header"><div><span class="wpu-sms-eyebrow">Providers</span><h1>Provider Configuration</h1><p>Configure the customer-owned SMS API. Save configuration before running tests.</p></div></div>';

        echo '<form method="post" class="wpu-sms-provider-form">';
        wp_nonce_field('wpu_sms_save_gateway', 'wpu_sms_nonce');

        echo '<input type="hidden" name="wpu_sms_action" value="save_gateway">';
        echo '<section class="wpu-sms-panel wpu-sms-section"><h2>Basic Configuration</h2>';
        $this->select('Provider Type', 'provider', $providerId, $definitions, 'id', 'label');
        $this->input('Endpoint', 'endpoint', $config['endpoint'] ?? '', 'https://sms.example.com/api/send', 'url', 'The SMS send endpoint. HTTPS is recommended.');
        $this->selectOptions('HTTP Method', 'method', $config['method'] ?? 'POST', $definition['methods'] ?? []);
        $this->input('Sender', 'sender', $config['sender'] ?? '', '3000', 'text');
        $this->input('Test recipient', 'test_recipient', $config['test_recipient'] ?? '', '09120000000', 'text');
        $this->input('Timeout (seconds)', 'timeout', (string) ($config['timeout'] ?? 15), '15', 'number', 'Allowed range: 1–120 seconds.');
        echo '</section>';

        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $authType = (string) ($auth['type'] ?? 'none');
        echo '<section class="wpu-sms-panel wpu-sms-section"><h2>Authentication</h2>';
        $this->selectOptions('Authentication type', 'auth[type]', $authType, $definition['auth'] ?? []);
        echo '<div data-wpu-auth-fields>';
        $this->input('Parameter / header name', 'auth[name]', $auth['name'] ?? '', 'X-API-Key', 'text', 'Used by API Key, Custom Header, and Query Parameter.');
        $this->secretInput('Credential', 'auth[value]', $auth['value'] ?? '');
        $this->input('Username', 'auth[username]', $auth['username'] ?? '', 'username');
        $this->secretInput('Password', 'auth[password]', $auth['password'] ?? '');
        echo '</div>';
        echo '<p class="description">Credentials are masked after saving. Leave a masked credential unchanged to keep the stored secret.</p>';
        echo '</section>';

        echo '<section class="wpu-sms-panel wpu-sms-section"><h2>Request</h2>';
        $this->pairs('Headers', 'headers', $config['headers'] ?? []);
        $this->pairs('Query parameters', 'query', $config['query'] ?? []);
        $body = $config['body'] ?? [];
        $bodyText = is_array($body) ? (string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $body;
        $this->textarea('Body template (JSON)', 'body', $bodyText, '{"to":"{{mobile}}","text":"{{message}}"}', 'Placeholders: {{mobile}}, {{message}}, {{sender}}, {{api_key}}.');
        echo '</section>';

        $response = is_array($config['response'] ?? null) ? $config['response'] : [];
        echo '<section class="wpu-sms-panel wpu-sms-section"><h2>Mapping</h2>';
        $this->input('Success path', 'response[success_path]', $response['success_path'] ?? '', 'success');
        $this->input('Expected success value', 'response[success_value]', $response['success_value'] ?? 'true', 'true');
        $this->input('Message ID path', 'response[message_id_path]', $response['message_id_path'] ?? '', 'message_id');
        echo '</section>';

        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        echo '<section class="wpu-sms-panel wpu-sms-section"><h2>Connection Test</h2>';
        echo '<p class="description">This endpoint is used only by Test Connection. It must not be the SMS send endpoint.</p>';
        $this->input('Connection endpoint', 'connection[endpoint]', $connection['endpoint'] ?? '', 'https://sms.example.com/health', 'url');
        $this->selectOptions('Connection HTTP method', 'connection[method]', $connection['method'] ?? 'GET', ['GET', 'POST', 'PUT', 'PATCH']);
        echo '</section>';

        echo '<section class="wpu-sms-panel wpu-sms-actions-panel"><div class="wpu-sms-actions">';
        echo '<button class="button button-primary" type="submit" name="wpu_sms_action" value="save_gateway">Save Configuration</button>';
        echo '<button class="button" type="submit" name="wpu_sms_action" value="test_connection">Test Connection</button>';
        echo '<button class="button" type="submit" name="wpu_sms_action" value="send_test_sms">Send Test SMS</button>';
        echo '</div><p class="description">Save and test actions are separate. Test Connection never sends an SMS.</p></section>';
        echo '</form>';

        // Keep raw access intentionally unused here: credentials are never rendered from it.
        unset($raw);
        echo '</div>';
    }

    private function input(string $label, string $name, string $value, string $placeholder, string $type = 'text', string $description = ''): void
    {
        echo '<div class="wpu-sms-field"><label for="wpu-' . esc_attr(str_replace(['[', ']'], '-', $name)) . '">' . esc_html($label) . '</label>';
        echo '<input id="wpu-' . esc_attr(str_replace(['[', ']'], '-', $name)) . '" type="' . esc_attr($type) . '" name="wpu_sms[' . esc_attr($name) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text">';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    private function secretInput(string $label, string $name, string $value): void
    {
        $value = $value !== '' ? $value : GatewayConfig::MASKED_SECRET;
        $this->input($label, $name, $value, 'Stored credential is masked', 'password');
    }

    private function textarea(string $label, string $name, string $value, string $placeholder, string $description = ''): void
    {
        echo '<div class="wpu-sms-field"><label for="wpu-' . esc_attr(str_replace(['[', ']'], '-', $name)) . '">' . esc_html($label) . '</label>';
        echo '<textarea id="wpu-' . esc_attr(str_replace(['[', ']'], '-', $name)) . '" name="wpu_sms[' . esc_attr($name) . ']" rows="8" placeholder="' . esc_attr($placeholder) . '" class="large-text code">' . esc_textarea($value) . '</textarea>';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    private function selectOptions(string $label, string $name, string $selected, array $options): void
    {
        echo '<div class="wpu-sms-field"><label for="wpu-' . esc_attr(str_replace(['[', ']'], '-', $name)) . '">' . esc_html($label) . '</label><select id="wpu-' . esc_attr(str_replace(['[', ']'], '-', $name)) . '" name="wpu_sms[' . esc_attr($name) . ']">';
        foreach ($options as $key => $labelText) {
            if (is_int($key)) {
                $key = $labelText;
            }
            echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) $key, $selected, false) . '>' . esc_html((string) $labelText) . '</option>';
        }
        echo '</select></div>';
    }

    private function select(string $label, string $name, string $selected, array $options, string $keyField, string $labelField): void
    {
        $mapped = [];
        foreach ($options as $key => $definition) {
            $mapped[$key] = $definition[$labelField] ?? $key;
        }
        $this->selectOptions($label, $name, $selected, $mapped);
    }

    private function pairs(string $label, string $name, mixed $values): void
    {
        $values = is_array($values) ? $values : [];
        echo '<div class="wpu-sms-field"><label>' . esc_html($label) . '</label><div data-wpu-pairs data-name="' . esc_attr($name) . '">';
        if ($values === []) {
            $values = ['' => ''];
        }
        $index = 0;
        foreach ($values as $key => $value) {
            echo '<div class="wpu-sms-pair"><input type="text" name="wpu_sms[' . esc_attr($name) . '][' . $index . '][name]" value="' . esc_attr((string) $key) . '" placeholder="Name"><input type="text" name="wpu_sms[' . esc_attr($name) . '][' . $index . '][value]" value="' . esc_attr((string) $value) . '" placeholder="Value"><button type="button" class="button" data-wpu-remove>Remove</button></div>';
            $index++;
        }
        echo '<button type="button" class="button" data-wpu-add>Add row</button></div></div>';
    }
}
